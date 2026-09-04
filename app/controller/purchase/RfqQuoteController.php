<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("寻源采购")
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseRfq;
use app\model\PurchaseRfqItem;
use app\model\PurchaseRfqQuote;
use app\model\PurchaseRfqQuoteItem;
use app\service\purchase\RfqService;
use Illuminate\Support\Facades\DB;
use support\Container;
use support\Request;
use support\Response;

class RfqQuoteController extends BaseController
{
    /**
     * 报价列表（分页）
     * @Apidoc\Title("报价列表")
     * @Apidoc\Desc("指定询价单下的供应商报价列表")
     * @Apidoc\Url("/admin/v1/purchase/rfq-quote")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $rfqId = $request->input('rfq_id');

        $query = PurchaseRfqQuote::query()->withCount('items');
        if ($rfqId) {
            $query->where('rfq_id', $this->decodeId($rfqId));
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'rfq_id', 'supplier_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 登记报价：金额全部由服务端按询价单需求数量计算，客户端只传单价
     * @Apidoc\Title("登记报价")
     * @Apidoc\Desc("仅已发布(询价中)询价单可报价；行金额 = 单价 × 询价数量(bcmath)，报价总额 = Σ行金额")
     * @Apidoc\Url("/admin/v1/purchase/rfq-quote")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */
    public function store(Request $request): Response
    {
        $rfqId = $this->decodeIdSafe((string) $request->input('rfq_id', ''));
        $supplierId = $this->decodeIdSafe((string) $request->input('supplier_id', ''));
        if ($rfqId === null || $supplierId === null) {
            return $this->fail('缺少有效的 rfq_id 或 supplier_id', 422);
        }
        $validator = validator($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.rfq_item_id' => 'required|string',
            'items.*.unit_price' => 'required|numeric|gt:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $quote = DB::transaction(function () use ($request, $rfqId, $supplierId) {
                $rfq = PurchaseRfq::query()->lockForUpdate()->find($rfqId);
                if (!$rfq) {
                    throw new \RuntimeException('询价单不存在');
                }
                if ((int) $rfq->status !== PurchaseRfq::STATUS_SUBMITTED) {
                    throw new \RuntimeException('仅已发布(询价中)的询价单可登记报价');
                }
                $exists = PurchaseRfqQuote::query()
                    ->where('rfq_id', $rfqId)->where('supplier_id', $supplierId)->exists();
                if ($exists) {
                    throw new \RuntimeException('该供应商已报价，请使用编辑更新报价');
                }
                $this->assertFullCoverage((int) $rfq->id, (array) $request->input('items'));

                $quote = new PurchaseRfqQuote();
                $quote->id = $this->generateId();
                $quote->rfq_id = $rfqId;
                $quote->supplier_id = $supplierId;
                $quote->quote_date = $request->input('quote_date') ?: date('Y-m-d H:i:s');
                $quote->valid_until = $request->input('valid_until') ?: null;
                $quote->awarded = 0;
                $quote->status = 0;
                $quote->save();
                $this->saveQuoteItems($quote->id, $rfqId, $request->input('items', []));
                $quote->amount = $this->recalcAmount($quote->id);
                $quote->save();

                return $quote;
            });
        } catch (\Throwable $e) {
            $this->logError('rfq-quote.store', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']), '报价成功');
    }

    /**
     * 报价详情（含逐行单价）
     * @Apidoc\Title("报价详情")
     * @Apidoc\Url("/admin/v1/purchase/rfq-quote/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */
    public function show(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::with('items')->find($this->decodeId($id));
        if (!$quote) {
            return $this->fail('报价不存在', 404);
        }
        $data = $this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']);
        $data['items'] = array_map(fn ($i) => $this->encodeIds($i, ['id', 'quote_id', 'rfq_item_id', 'product_id']), $data['items'] ?? []);

        return $this->success($data);
    }

    /**
     * 更新报价（整单替换明细，金额重算；已中标或询价单非询价中不可改）
     * @Apidoc\Title("更新报价")
     * @Apidoc\Url("/admin/v1/purchase/rfq-quote/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Tag("寻源采购")
     */
    public function update(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::find($this->decodeId($id));
        if (!$quote) {
            return $this->fail('报价不存在', 404);
        }
        if ((int) $quote->awarded === 1) {
            return $this->fail('该报价已中标，不可修改', 422);
        }
        $rfq = PurchaseRfq::find((int) $quote->rfq_id);
        if (!$rfq || (int) $rfq->status !== PurchaseRfq::STATUS_SUBMITTED) {
            return $this->fail('询价单已不在询价中，不可修改报价', 422);
        }

        try {
            DB::transaction(function () use ($request, $quote, $rfq) {
                foreach (['quote_date', 'valid_until'] as $field) {
                    if ($request->has($field)) {
                        $quote->{$field} = $request->input($field) ?: null;
                    }
                }
                if ($request->has('items')) {
                    $items = (array) $request->input('items');
                    if ($items === []) {
                        throw new \RuntimeException('报价至少保留一条明细');
                    }
                    $this->assertFullCoverage((int) $rfq->id, $items);
                    PurchaseRfqQuoteItem::query()->where('quote_id', $quote->id)->delete();
                    $this->saveQuoteItems((int) $quote->id, (int) $rfq->id, $items);
                }
                $quote->amount = $this->recalcAmount((int) $quote->id);
                $quote->save();
            });
        } catch (\Throwable $e) {
            $this->logError('rfq-quote.update', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']), '更新成功');
    }

    /**
     * 删除报价（软删除；已中标不可删）
     * @Apidoc\Title("删除报价")
     * @Apidoc\Url("/admin/v1/purchase/rfq-quote/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Tag("寻源采购")
     */
    public function destroy(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::find($this->decodeId($id));
        if (!$quote) {
            return $this->fail('报价不存在', 404);
        }
        if ((int) $quote->awarded === 1) {
            return $this->fail('该报价已中标，不可删除', 422);
        }
        $quote->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 保存报价行（事务内调用）：单价须 ≤2 位小数——unit_price 列为 DECIMAL(12,2)，
     * 3 位以上小数会被落库舍入，导致报价行金额（按原值 bc 计算）与中标后
     * 按库内单价重算的订单行金额漂移 0.01~0.02；并逐行校验 rfq_item_id 归属
     */
    private function saveQuoteItems(int $quoteId, int $rfqId, array $items): void
    {
        $service = Container::get(RfqService::class);
        foreach ($items as $i => $row) {
            $rfqItemId = $this->decodeIdSafe((string) ($row['rfq_item_id'] ?? ''));
            if ($rfqItemId === null) {
                throw new \RuntimeException('报价明细缺少有效的 rfq_item_id');
            }
            $rfqItem = PurchaseRfqItem::query()
                ->where('id', $rfqItemId)->where('rfq_id', $rfqId)->first();
            if (!$rfqItem) {
                throw new \RuntimeException('报价明细不属于该询价单');
            }
            $unitPrice = bc_norm($row['unit_price'] ?? '0');
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $unitPrice)) {
                throw new \RuntimeException('第 ' . ($i + 1) . ' 行单价格式无效：须为正数且最多 2 位小数');
            }

            $qi = new PurchaseRfqQuoteItem();
            $qi->id = $this->generateId();
            $qi->quote_id = $quoteId;
            $qi->rfq_item_id = $rfqItemId;
            $qi->product_id = (int) $rfqItem->product_id;
            $qi->unit_price = $unitPrice;
            $qi->amount = $service->lineAmount($unitPrice, (string) $rfqItem->quantity);
            $qi->save();
        }
    }

    /**
     * 报价须覆盖询价单全部明细行：单次中标 + 总额比价口径下，部分报价会使总额
     * 不可比（覆盖行越少总额天然越低），且中标转单后未报价行会被静默丢弃
     */
    private function assertFullCoverage(int $rfqId, array $items): void
    {
        $required = PurchaseRfqItem::query()->where('rfq_id', $rfqId)
            ->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $given = [];
        foreach ($items as $row) {
            $rfqItemId = $this->decodeIdSafe((string) ($row['rfq_item_id'] ?? ''));
            if ($rfqItemId === null) {
                throw new \RuntimeException('报价明细缺少有效的 rfq_item_id');
            }
            $given[] = $rfqItemId;
        }
        sort($given);
        if ($required !== array_values($given)) {
            throw new \RuntimeException(
                '报价须覆盖询价单全部 ' . count($required) . ' 条明细（当前 ' . count($given) . ' 条，不接受部分报价）'
            );
        }
    }

    /** 行金额求和回写报价总额（服务端口径，杜绝前端凑数） */
    private function recalcAmount(int $quoteId): string
    {
        $service = Container::get(RfqService::class);
        $amounts = PurchaseRfqQuoteItem::query()->where('quote_id', $quoteId)
            ->get()->map(fn ($i) => (string) $i->amount)->all();

        return $service->sumAmounts($amounts);
    }
}
