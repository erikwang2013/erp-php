<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\purchase;

use app\common\SnowflakeService;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\model\PurchaseRfq;
use app\model\PurchaseRfqItem;
use app\model\PurchaseRfqQuote;
use app\model\PurchaseRfqQuoteItem;
use Illuminate\Support\Facades\DB;

/**
 * 寻源采购核心逻辑：报价金额 bcmath 汇总、比价取最低、中标转采购订单草稿。
 *
 * 金额纪律：一律 bcmath（bcadd/bcmul/bc_round/bccomp），禁止 float 算术。
 * 行金额 = 单价 × 需求数量，scale=4 乘、scale=2 四舍五入后再汇总，
 * 保证「3×0.10=0.30」精确且头表总额 == Σ行金额。
 *
 * 无状态服务，经 support\Container 注入获取（同域新代码惯例）；
 * 纯决策方法（pickLowest / canAward / buildOrderDraft）可 new 实例直测，
 * 落库路径经 award() 事务调用同一套逻辑。
 */
class RfqService
{
    /**
     * 行金额 = 单价 × 数量（bcmath，scale=4 乘后按 2 位舍入存储口径）
     */
    public function lineAmount(string|int|float $unitPrice, string|int|float $quantity): string
    {
        return bc_round(bcmul(bc_norm($unitPrice), bc_norm($quantity), 4), 2);
    }

    /**
     * 多行金额求和（逐项 scale=2 累加，与存储口径一致，杜绝尾噪）
     */
    public function sumAmounts(array $amounts): string
    {
        $total = '0.00';
        foreach ($amounts as $amount) {
            $total = bcadd($total, bc_norm($amount), 2);
        }

        return $total;
    }

    /**
     * 比价：从 [['id'=>..,'amount'=>'..'], ...] 中按 bccomp 选出总额最低报价
     *
     * @param array $quotes 报价数组，需含 id 与 amount 键
     * @return int|null 最低价报价 id，无候选时返回 null
     */
    public function pickLowest(array $quotes): ?int
    {
        $lowest = null;
        $lowestAmount = null;
        foreach ($quotes as $quote) {
            if ($lowestAmount === null || bccomp($quote['amount'], $lowestAmount, 4) < 0) {
                $lowest = (int) $quote['id'];
                $lowestAmount = $quote['amount'];
            }
        }

        return $lowest;
    }

    /**
     * 是否可以中标：询价单处于「已发布」且尚无中标报价（防重复中标）
     */
    public function canAward(int $rfqStatus, int $awardedQuoteId): bool
    {
        return $rfqStatus === PurchaseRfq::STATUS_SUBMITTED && $awardedQuoteId === 0;
    }

    /**
     * 生成落库前的采购订单草稿载荷（纯函数，供 award() 与单元测试复用）
     *
     * @param array $lines [['quantity'=>, 'unit'=>, 'unit_price'=>], ...] 已按中标报价单价
     */
    public function buildOrderDraft(string $rfqNo, int $supplierId, string $totalAmount, array $lines): array
    {
        $orderItems = [];
        foreach ($lines as $line) {
            $amount = $this->lineAmount($line['unit_price'], $line['quantity']);
            $orderItems[] = [
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $amount,
                'unit' => $line['unit'] ?? '',
            ];
        }

        return [
            'code' => 'PO' . SnowflakeService::generate(),
            'total_amount' => $totalAmount,
            'remark' => '由询比价单 ' . $rfqNo . ' 中标生成',
            'items' => $orderItems,
        ];
    }

    /**
     * 中标：将中标报价落定并生成采购订单草稿（status=0 待审核，不直接审核）
     *
     * 并发防护：事务内对询价单行加锁重读，二次校验状态与 awarded_quote_id，
     * 与 canAward 双保险，杜绝并发下重复中标/重复转单。
     *
     * @throws \RuntimeException 业务前置不满足时抛出（中文消息由控制器转响应）
     * @return PurchaseOrder 生成的采购订单（草稿，含 items 关联）
     */
    public function award(int $rfqId, int $quoteId, int $operatorId = 0): PurchaseOrder
    {
        return DB::transaction(function () use ($rfqId, $quoteId, $operatorId) {
            /** @var PurchaseRfq|null $rfq */
            $rfq = PurchaseRfq::query()->lockForUpdate()->find($rfqId);
            if (!$rfq) {
                throw new \RuntimeException('询价单不存在');
            }
            if (!$this->canAward((int) $rfq->status, (int) $rfq->awarded_quote_id)) {
                throw new \RuntimeException('仅已发布且未中标的询价单可执行中标');
            }

            /** @var PurchaseRfqQuote|null $quote */
            $quote = PurchaseRfqQuote::query()
                ->where('rfq_id', $rfqId)->where('id', $quoteId)
                ->lockForUpdate()->first();
            if (!$quote) {
                throw new \RuntimeException('报价不存在或不属于该询价单');
            }
            if ((int) $quote->awarded === 1 || (int) $quote->status !== 0) {
                throw new \RuntimeException('该报价已中标或已作废，不可重复中标');
            }

            // 行单价 × 询价单需求数量，逐行 bcmath 出金额
            $rfqItems = PurchaseRfqItem::query()->where('rfq_id', $rfqId)->get()->keyBy('id');
            $quoteItems = PurchaseRfqQuoteItem::query()->where('quote_id', $quoteId)->get();
            $lines = [];
            $amounts = [];
            foreach ($quoteItems as $qi) {
                /** @var PurchaseRfqItem|null $rfqItem */
                $rfqItem = $rfqItems->get((int) $qi->rfq_item_id);
                $quantity = $rfqItem ? (string) $rfqItem->quantity : '0';
                $amount = $this->lineAmount((string) $qi->unit_price, $quantity);
                $amounts[] = $amount;
                $lines[] = [
                    'quantity' => $quantity,
                    'unit' => $rfqItem ? (string) $rfqItem->unit : '',
                    'unit_price' => (string) $qi->unit_price,
                    'amount' => $amount,
                    'product_id' => (int) $qi->product_id,
                ];
            }
            if ($lines === []) {
                throw new \RuntimeException('中标报价缺少报价明细，无法转采购订单');
            }
            $totalAmount = $this->sumAmounts($amounts);

            // 1) 报价标记中标、询价单置「已中标」
            $quote->awarded = 1;
            $quote->save();
            $rfq->status = PurchaseRfq::STATUS_WON;
            $rfq->awarded_quote_id = $quoteId;
            $rfq->auditor_id = $operatorId;
            $rfq->audited_at = date('Y-m-d H:i:s');
            $rfq->save();

            // 2) 转采购订单草稿（状态 0=待审核，复用既有 erp_purchase_order 表）
            $draft = $this->buildOrderDraft((string) $rfq->rfq_no, (int) $quote->supplier_id, $totalAmount, $lines);
            $order = new PurchaseOrder();
            $order->id = SnowflakeService::generate();
            $order->code = $draft['code'];
            $order->apply_id = 0;
            $order->supplier_id = (int) $quote->supplier_id;
            $order->warehouse_id = 0;
            $order->total_amount = $draft['total_amount'];
            $order->status = 0;
            $order->remark = $draft['remark'];
            $order->ordered_at = date('Y-m-d H:i:s');
            $order->save();

            foreach ($draft['items'] as $i => $oi) {
                $line = $lines[$i];
                $orderItem = new PurchaseOrderItem();
                $orderItem->id = SnowflakeService::generate();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $line['product_id'];
                $orderItem->sku_id = 0;
                $orderItem->quantity = $oi['quantity'];
                $orderItem->received_quantity = '0.00';
                $orderItem->price = $oi['unit_price'];
                $orderItem->amount = $oi['amount'];
                $orderItem->unit = $oi['unit'];
                $orderItem->save();
            }

            return $order;
        });
    }
}
