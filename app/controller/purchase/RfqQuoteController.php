<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseRfq;
use app\model\PurchaseRfqItem;
use app\model\PurchaseRfqQuote;
use app\model\PurchaseRfqQuoteItem;
use app\model\Supplier;
use app\service\purchase\RfqService;
// 门面 \Illuminate\Support\Facades\DB 在本项目没有根（无 Facade::setFacadeApplication），
// 一调用就抛 RuntimeException「A facade root has not been set.」——询价全链路必失败；
// 统一用 Capsule 管理器（其余控制器同款）。
use Illuminate\Database\Capsule\Manager as DB;
use support\Container;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('报价')]
#[\erikwang2013\apidoc\annotation\Group('采购管理')]

class RfqQuoteController extends BaseController
{
    /**
     * 报价列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('报价列表')]
    #[\erikwang2013\apidoc\annotation\Desc('指定询价单下的供应商报价列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/rfq-quote')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $rfqId = $request->input('rfq_id');

        $query = PurchaseRfqQuote::query()->withCount('items');
        if ($rfqId) {
            // 双模解码（hashid/原生数字）；无法解析即视为未筛选（同收货列表的过滤惯例）
            $decodedRfq = $this->decodeFlexibleId($rfqId);
            if ($decodedRfq !== null && $decodedRfq > 0) {
                $query->where('rfq_id', $decodedRfq);
            }
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get();
        // 供应商名/询价单号按 id 批量带出（同收货/退货列表惯例）：原出参只有 hashid 与金额，
        // 「中标转订单」的报价下拉里每个选项都是一串看不出归属的数字
        $supplierNames = Supplier::query()->whereIn('id', $models->pluck('supplier_id')->all())->pluck('name', 'id')->all();
        $rfqNos = PurchaseRfq::query()->whereIn('id', $models->pluck('rfq_id')->all())->pluck('rfq_no', 'id')->all();
        $list = $models->map(function ($item) use ($supplierNames, $rfqNos) {
            $row = $item->toArray();
            $row['supplier_name'] = $supplierNames[(int) ($row['supplier_id'] ?? 0)] ?? '';
            $row['rfq_no'] = $rfqNos[(int) ($row['rfq_id'] ?? 0)] ?? '';

            return $this->encodeIds($row, ['id', 'rfq_id', 'supplier_id']);
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 登记报价：金额全部由服务端按询价单需求数量计算，客户端只传单价
     */
    #[\erikwang2013\apidoc\annotation\Title('登记报价')]
    #[\erikwang2013\apidoc\annotation\Desc('仅已发布(询价中)询价单可报价；行金额 = 单价 × 询价数量(bcmath)，报价总额 = Σ行金额')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/rfq-quote')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function store(Request $request): Response
    {
        $rfqId = $this->decodeFlexibleId((string) $request->input('rfq_id', ''));
        $supplierId = $this->decodeFlexibleId((string) $request->input('supplier_id', ''));
        if ($rfqId === null || $supplierId === null) {
            return $this->fail($this->trans('Missing a valid rfq_id or supplier_id'), 422);
        }
        $validator = validator($request->all(), [
            'items' => 'required|array|min:1',
            // rfq_item_id 是询价明细行的 hashid，没有任何界面能查到（选中询价单也不带出明细），
            // 故与采购收货同一契约：可缺省，缺省时按 product_id 在本询价单内反查
            'items.*.rfq_item_id' => 'nullable|string',
            'items.*.product_id' => 'nullable',
            'items.*.unit_price' => 'required|numeric|gt:0|max:9999999999.99',
            // 日期串无此规则会直落 DATETIME/DATE 报 1292，被 catch 成 422 原始 SQL 文案
            'quote_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
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
                $lines = $this->resolveQuoteLines($rfqId, (array) $request->input('items'));
                $this->assertFullCoverage($rfqId, $lines);

                $quote = new PurchaseRfqQuote();
                $quote->id = $this->generateId();
                $quote->rfq_id = $rfqId;
                $quote->supplier_id = $supplierId;
                $quote->quote_date = $request->input('quote_date') ?: date('Y-m-d H:i:s');
                $quote->valid_until = $request->input('valid_until') ?: null;
                $quote->awarded = 0;
                $quote->status = 0;
                $quote->save();
                $this->saveQuoteItems($quote->id, $lines);
                $quote->amount = $this->recalcAmount($quote->id);
                $quote->save();

                return $quote;
            });
        } catch (\Throwable $e) {
            $this->logError('rfq-quote.store', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']), $this->trans('Quotation submitted successfully'));
    }

    /**
     * 报价详情（含逐行单价）
     */
    #[\erikwang2013\apidoc\annotation\Title('报价详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function show(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::with('items')->find($this->decodeId($id));
        if (!$quote) {
            return $this->fail($this->trans('Quotation not found'), 404);
        }
        $data = $this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']);
        $data['items'] = array_map(fn ($i) => $this->encodeIds($i, ['id', 'quote_id', 'rfq_item_id', 'product_id']), $data['items'] ?? []);

        return $this->success($data);
    }

    /**
     * 更新报价（整单替换明细，金额重算；已中标或询价单非询价中不可改）
     */
    #[\erikwang2013\apidoc\annotation\Title('更新报价')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function update(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::find($this->decodeId($id));
        if (!$quote) {
            return $this->fail($this->trans('Quotation not found'), 404);
        }
        if ((int) $quote->awarded === 1) {
            return $this->fail($this->trans('This quotation has been awarded; it cannot be modified'), 422);
        }
        $rfq = PurchaseRfq::find((int) $quote->rfq_id);
        if (!$rfq || (int) $rfq->status !== PurchaseRfq::STATUS_SUBMITTED) {
            return $this->fail($this->trans('The RFQ is no longer open; the quotation cannot be edited'), 422);
        }

        // 与 store 同一套边界（原 update 无 validator：日期串 → 1292 原始 SQL 被当业务文案抛出）
        $validator = validator($request->all(), [
            'items' => 'nullable|array|min:1',
            'items.*.rfq_item_id' => 'nullable|string',
            'items.*.product_id' => 'nullable',
            'items.*.unit_price' => 'required|numeric|gt:0|max:9999999999.99',
            'quote_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            DB::transaction(function () use ($request, $quote, $rfq) {
                foreach (['quote_date', 'valid_until'] as $field) {
                    if ($request->has($field)) {
                        // 两列均可空，'' 语义即「不填」；落 datetime/date 前统一归一为 null
                        $quote->{$field} = $request->input($field) ?: null;
                    }
                }
                if ($request->has('items')) {
                    $items = (array) $request->input('items');
                    if ($items === []) {
                        throw new \RuntimeException('报价至少保留一条明细');
                    }
                    $lines = $this->resolveQuoteLines((int) $rfq->id, $items);
                    $this->assertFullCoverage((int) $rfq->id, $lines);
                    PurchaseRfqQuoteItem::query()->where('quote_id', $quote->id)->delete();
                    $this->saveQuoteItems((int) $quote->id, $lines);
                }
                $quote->amount = $this->recalcAmount((int) $quote->id);
                $quote->save();
            });
        } catch (\Throwable $e) {
            $this->logError('rfq-quote.update', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($quote->toArray(), ['id', 'rfq_id', 'supplier_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除报价（软删除；已中标不可删）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除报价')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function destroy(Request $request, string $id): Response
    {
        $quote = PurchaseRfqQuote::find($this->decodeId($id));
        if (!$quote) {
            return $this->fail($this->trans('Quotation not found'), 404);
        }
        if ((int) $quote->awarded === 1) {
            return $this->fail($this->trans('This quotation has been awarded; it cannot be deleted'), 422);
        }
        // 前端资源声明 deleteNeedsPassword（会弹口令框），后端原样丢掉 password 直接删：
        // 口令门在信任边界上，缺了等于前端提示形同虚设（其余 7 个采购控制器同款校验）
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $quote->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 报价行归一（事务内调用）：把客户端输入解析成「询价明细行 id + 单价 + 行金额」。
     * 单价须 ≤2 位小数——unit_price 列为 DECIMAL(12,2)，3 位以上小数会被落库舍入，
     * 导致报价行金额（按原值 bc 计算）与中标后按库内单价重算的订单行金额漂移 0.01~0.02；
     * 行来源二选一：显式 rfq_item_id（须属本询价单）或 product_id（本单唯一才可判定）。
     *
     * @return array<int, array{rfq_item_id:int, product_id:int, unit_price:string, amount:string}>
     */
    private function resolveQuoteLines(int $rfqId, array $items): array
    {
        $service = Container::get(RfqService::class);
        $lines = [];
        foreach ($items as $i => $row) {
            $rfqItem = null;
            $rawItemId = $row['rfq_item_id'] ?? '';   // ?? 已吃掉 null，缺省/显式 null 都是 ''
            if ($rawItemId !== '') {
                $rfqItemId = $this->decodeFlexibleId((string) $rawItemId);
                if ($rfqItemId === null) {
                    throw new \RuntimeException('第 ' . ($i + 1) . ' 行询价明细行（rfq_item_id）无效');
                }
                $rfqItem = PurchaseRfqItem::query()
                    ->where('id', $rfqItemId)->where('rfq_id', $rfqId)->first();
                if (!$rfqItem) {
                    throw new \RuntimeException('报价明细不属于该询价单');
                }
            } else {
                $productId = $this->decodeFlexibleId((string) ($row['product_id'] ?? ''));
                if ($productId === null) {
                    throw new \RuntimeException('第 ' . ($i + 1) . ' 行缺少有效的 rfq_item_id 或 product_id');
                }
                $candidates = PurchaseRfqItem::query()
                    ->where('rfq_id', $rfqId)->where('product_id', $productId)->get()->keyBy('id');
                if ($candidates->count() !== 1) {
                    throw new \RuntimeException('第 ' . ($i + 1) . " 行未指定询价明细行，且本询价单商品({$productId})对应 {$candidates->count()} 行，无法确定，请显式传入 rfq_item_id");
                }
                // 取集合键即明细行 id（不读模型主键属性，避免 property.notFound）
                $rfqItemId = (int) $candidates->keys()->first();
                $rfqItem = $candidates->first();
            }

            $unitPrice = bc_norm($row['unit_price'] ?? '0');
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $unitPrice)) {
                throw new \RuntimeException('第 ' . ($i + 1) . ' 行单价格式无效：须为正数且最多 2 位小数');
            }
            // 列宽上限：unit_price DECIMAL(12,2)、amount DECIMAL(14,2)（单价 × 询价数量）。
            // 超限原样落库报 1264 Out of range，会以「Data too long」式原始 SQL 抛给用户
            if (bccomp($unitPrice, '9999999999.99', 2) > 0) {
                throw new \RuntimeException('第 ' . ($i + 1) . ' 行单价超出上限 9999999999.99');
            }
            $amount = $service->lineAmount($unitPrice, (string) $rfqItem->quantity);
            if (bccomp($amount, '999999999999.99', 2) > 0) {
                throw new \RuntimeException('第 ' . ($i + 1) . ' 行金额（单价 × 询价数量）超出上限 999999999999.99');
            }

            $lines[] = [
                'rfq_item_id' => $rfqItemId,
                'product_id' => (int) $rfqItem->product_id,
                'unit_price' => $unitPrice,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /** 保存已归一的报价行（事务内调用） */
    private function saveQuoteItems(int $quoteId, array $lines): void
    {
        foreach ($lines as $line) {
            $qi = new PurchaseRfqQuoteItem();
            $qi->id = $this->generateId();
            $qi->quote_id = $quoteId;
            $qi->rfq_item_id = $line['rfq_item_id'];
            $qi->product_id = $line['product_id'];
            $qi->unit_price = $line['unit_price'];
            $qi->amount = $line['amount'];
            $qi->save();
        }
    }

    /**
     * 报价须覆盖询价单全部明细行：单次中标 + 总额比价口径下，部分报价会使总额
     * 不可比（覆盖行越少总额天然越低），且中标转单后未报价行会被静默丢弃
     */
    private function assertFullCoverage(int $rfqId, array $lines): void
    {
        $required = PurchaseRfqItem::query()->where('rfq_id', $rfqId)
            ->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $given = array_map(fn ($line) => (int) $line['rfq_item_id'], $lines);
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
