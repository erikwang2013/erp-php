<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\oms;

use app\common\SnowflakeService;
use app\model\Inventory;
use app\model\InventoryFlow;
use app\model\OmsOrder;
use app\model\OmsRma;
use app\model\OmsRmaItem;
use app\model\SalesOrder;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;
use support\Log;

class RmaService
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    /** 创建RMA（头+明细同事务，任一项失败整体回滚） */
    public function create(int $orderId, int $customerId, int $type, string $reason, array $items, array $options = []): OmsRma
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('RMA明细不能为空');
        }

        return DB::transaction(function () use ($orderId, $customerId, $type, $reason, $items, $options) {
            $rma = new OmsRma();
            $rma->id = SnowflakeService::generate();
            $rma->code = $options['code'] ?? ('RMA' . SnowflakeService::generate());
            $rma->order_id = $orderId;
            $rma->customer_id = $customerId;
            $rma->type = $type;
            $rma->reason = $reason;
            $rma->status = 0;
            $rma->refund_amount = $options['refund_amount'] ?? 0;
            $rma->return_shipping_fee = $options['return_shipping_fee'] ?? 0;
            $rma->save();

            foreach ($items as $item) {
                $ri = new OmsRmaItem();
                $ri->id = SnowflakeService::generate();
                $ri->rma_id = $rma->id;
                $ri->order_item_id = $item['order_item_id'];
                $ri->product_id = $item['product_id'];
                $ri->sku_id = $item['sku_id'] ?? 0;
                $ri->quantity = $item['quantity'];
                $ri->price = $item['price'] ?? 0;
                $ri->amount = $item['amount'] ?? ($item['quantity'] * ($item['price'] ?? 0));
                $ri->unit = $item['unit'] ?? '';
                $ri->save();
            }

            return $rma;
        });
    }

    /** 批准RMA */
    public function approve(int $rmaId, int $approverId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) {
            throw new \RuntimeException('RMA不存在');
        }
        if ($rma->status !== 0) {
            throw new \RuntimeException('当前状态不可审批');
        }
        $rma->status = 1;
        $rma->approved_by = $approverId;
        $rma->approved_at = date('Y-m-d H:i:s');
        $rma->save();
    }

    /** 标记客户已退回 */
    public function markReturned(int $rmaId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) {
            throw new \RuntimeException('RMA不存在');
        }
        if ($rma->status !== 1) {
            throw new \RuntimeException('请先批准RMA');
        }
        $rma->status = 2;
        $rma->returned_at = date('Y-m-d H:i:s');
        $rma->save();
    }

    /** 退货收货并入库 */
    public function receive(int $rmaId, int $warehouseId, int $locationId): void
    {
        DB::transaction(function () use ($rmaId, $warehouseId, $locationId) {
            $rma = OmsRma::where('id', $rmaId)->lockForUpdate()->first();
            if (!$rma) {
                throw new \RuntimeException('RMA不存在');
            }
            if ($rma->status !== 2) {
                throw new \RuntimeException('请等待退货寄回');
            }

            $items = OmsRmaItem::where('rma_id', $rmaId)->get();
            foreach ($items as $item) {
                // 入库成本取原出库成本，避免用销售价污染加权平均成本
                $cost = $this->getReturnCostBasis($item->product_id, $item->sku_id);
                $this->inventory->stockIn(
                    $item->product_id,
                    $item->sku_id,
                    $warehouseId,
                    $locationId,
                    '',
                    $item->quantity,
                    $cost,
                    'oms_rma',
                    $rmaId
                );
            }

            $rma->status = 3;
            $rma->received_at = date('Y-m-d H:i:s');
            $rma->save();
        });
    }

    private function getReturnCostBasis(int $productId, int $skuId): float
    {
        $lastOut = InventoryFlow::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->where('direction', 2)
            ->orderByDesc('id')
            ->first();
        if ($lastOut) {
            return max((float)$lastOut->cost_price, 0.0);
        }
        $inv = Inventory::where('product_id', $productId)->where('sku_id', $skuId)->first();

        return $inv ? max((float)$inv->cost_price, 0.0) : 0.0;
    }

    /** 退款（翻状态 + 同步订单支付状态为已退款 + 留退款日志；无 Refund 表，金额以 rma.refund_amount 为准） */
    public function refund(int $rmaId): void
    {
        DB::transaction(function () use ($rmaId) {
            $rma = OmsRma::where('id', $rmaId)->lockForUpdate()->first();
            if (!$rma) {
                throw new \RuntimeException('RMA不存在');
            }
            if (!in_array($rma->status, [1, 3])) {
                throw new \RuntimeException('当前状态不可退款');
            }
            $rma->status = 4;
            $rma->save();

            // 累计已退款 vs 订单总额：不足为部分退款(2)，补足为全额退款(3)
            $refundedTotal = (float) OmsRma::where('order_id', $rma->order_id)->where('status', 4)->sum('refund_amount');
            $orderTotal = (float) SalesOrder::where('id', $rma->order_id)->value('total_amount');
            $paymentStatus = ($orderTotal > 0 && $refundedTotal >= $orderTotal) ? 3 : 2;
            OmsOrder::where('id', $rma->order_id)->update(['payment_status' => $paymentStatus]);
            Log::info('RMA退款', [
                'rma_id' => $rmaId,
                'order_id' => $rma->order_id,
                'refund_amount' => $rma->refund_amount,
            ]);
        });
    }

    /** 拒绝RMA */
    public function reject(int $rmaId): void
    {
        $rma = OmsRma::find($rmaId);
        if (!$rma) {
            throw new \RuntimeException('RMA不存在');
        }
        if ($rma->status !== 0) {
            throw new \RuntimeException('当前状态不可操作');
        }
        $rma->status = 5;
        $rma->save();
    }
}
