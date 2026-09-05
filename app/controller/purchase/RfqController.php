<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("寻源采购")
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\common\SnowflakeService;
use app\model\PurchaseRfq;
use app\model\PurchaseRfqItem;
use app\model\PurchaseRfqQuote;
use app\service\purchase\RfqService;
use Illuminate\Support\Facades\DB;
use support\Container;
use support\Request;
use support\Response;

class RfqController extends BaseController
{
    /**
     * 询价单列表（分页）
     * @Apidoc\Title("询价单列表")
     * @Apidoc\Desc("询比价单列表，支持状态筛选与 rfq_no 关键词")
     * @Apidoc\Url("/admin/v1/purchase/rfq")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("询价单列表")]
#[\erikwang2013\apidoc\annotation\Desc("询比价单列表，支持状态筛选与 rfq_no 关键词")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/rfq")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $status = $request->input('status');
        $keyword = (string) $request->input('keyword', '');

        $query = PurchaseRfq::query();
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($keyword) {
            $query->where('rfq_no', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $list = $query->withCount('quotes')->withCount('items')
            ->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'buyer_id', 'awarded_quote_id', 'auditor_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建询价单（头 + 明细同事务）
     * @Apidoc\Title("创建询价单")
     * @Apidoc\Desc("询价单头与明细行（product_id/quantity/unit/target_price）一并保存")
     * @Apidoc\Url("/admin/v1/purchase/rfq")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("创建询价单")]
#[\erikwang2013\apidoc\annotation\Desc("询价单头与明细行（product_id/quantity/unit/target_price）一并保存")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/purchase/rfq")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|gt:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $rfq = DB::transaction(function () use ($request) {
                $rfq = new PurchaseRfq();
                $rfq->id = $this->generateId();
                $rfq->rfq_no = 'RFQ' . SnowflakeService::generate();
                $rfq->buyer_id = (int) ($request->input('buyer_id', 0)) ?: (int) ($request->adminId ?? 0);
                $rfq->supplier_range = (string) $request->input('supplier_range', '');
                $rfq->require_date = $request->input('require_date') ?: null;
                $rfq->status = PurchaseRfq::STATUS_DRAFT;
                $rfq->remark = (string) $request->input('remark', '');
                $rfq->save();
                $this->saveItems($rfq->id, $request->input('items', []));

                return $rfq;
            });
        } catch (\Throwable $e) {
            $this->logError('rfq.store', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), '创建成功');
    }

    /**
     * 询价单详情（含明细与报价）
     * @Apidoc\Title("询价单详情")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("询价单详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function show(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::with(['items', 'quotes' => fn ($q) => $q->with('items')->orderBy('id', 'desc')])
            ->find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }

        $data = $this->encodeIds($rfq->toArray(), ['id', 'buyer_id', 'awarded_quote_id', 'auditor_id']);
        $data['items'] = array_map(fn ($i) => $this->encodeIds($i, ['id', 'rfq_id', 'product_id']), $data['items'] ?? []);
        $data['quotes'] = array_map(function ($q) {
            return $this->encodeIds($q, ['id', 'rfq_id', 'supplier_id']);
        }, $data['quotes'] ?? []);

        return $this->success($data);
    }

    /**
     * 更新询价单（仅草稿可改头与明细）
     * @Apidoc\Title("更新询价单")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("更新询价单")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function update(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }
        if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
            return $this->fail('仅草稿状态的询价单可编辑', 422);
        }

        try {
            DB::transaction(function () use ($request, $rfq) {
                foreach (['buyer_id', 'supplier_range', 'require_date', 'remark'] as $field) {
                    if ($request->has($field)) {
                        $value = $request->input($field);
                        $rfq->{$field} = ($field === 'remark' || $field === 'supplier_range') ? (string) $value : ($value ?: null);
                    }
                }
                $rfq->save();
                if ($request->has('items')) {
                    $items = (array) $request->input('items');
                    if ($items === []) {
                        throw new \RuntimeException('询价单至少保留一条明细');
                    }
                    PurchaseRfqItem::query()->where('rfq_id', $rfq->id)->delete();
                    $this->saveItems($rfq->id, $items);
                }
            });
        } catch (\Throwable $e) {
            $this->logError('rfq.update', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), '更新成功');
    }

    /**
     * 删除询价单（软删除，仅草稿，需管理员密码二次确认）
     * @Apidoc\Title("删除询价单")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("删除询价单")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function destroy(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }
        if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
            return $this->fail('仅草稿状态的询价单可删除', 422);
        }
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $rfq->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 发布询价（草稿 → 已发布，开放报价登记）
     * @Apidoc\Title("发布询价")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}/submit")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("发布询价")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function submit(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }
        if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
            return $this->fail('仅草稿状态的询价单可发布', 422);
        }
        $rfq->status = PurchaseRfq::STATUS_SUBMITTED;
        $rfq->save();

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), '发布成功');
    }

    /**
     * 比价汇总：报价按金额升序（bccomp）+ 行单价对比目标价
     * @Apidoc\Title("比价汇总")
     * @Apidoc\Desc("全部有效报价按总额升序排列并标注最低价；逐行给出各供应商单价与目标价对比")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}/compare")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("比价汇总")]
#[\erikwang2013\apidoc\annotation\Desc("全部有效报价按总额升序排列并标注最低价；逐行给出各供应商单价与目标价对比")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function compare(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::with('items')->find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }
        $quotes = PurchaseRfqQuote::with('items')
            ->where('rfq_id', $rfq->id)->where('status', 0)
            ->get();

        $service = Container::get(RfqService::class);
        $lowestId = $service->pickLowest($quotes->map(fn ($q) => ['id' => $q->id, 'amount' => (string) $q->amount])->all());
        $targetTotal = $service->sumAmounts(
            $rfq->items->map(fn ($i) => $service->lineAmount((string) $i->target_price, (string) $i->quantity))->all()
        );

        // 报价排序：已中标置顶，其余按总额 bcmath 升序
        $quoteArr = $quotes->map(fn ($q) => [
            'id' => (int) $q->id,
            'awarded' => (int) $q->awarded,
            'amount' => (string) $q->amount,
        ])->all();
        usort($quoteArr, function (array $a, array $b): int {
            if ($a['awarded'] !== $b['awarded']) {
                return $a['awarded'] ? -1 : 1;
            }

            return bccomp($a['amount'], $b['amount'], 4);
        });
        $quoteRows = array_map(fn ($q) => $this->encodeIds([
            'id' => $q['id'], 'amount' => $q['amount'],
            'is_lowest' => $lowestId === $q['id'] ? 1 : 0,
        ]), $quoteArr);

        // 行对比矩阵：询价明细 → 各供应商报价单价（键为报价 hashid）
        $matrix = $rfq->items->map(function ($item) use ($quotes, $service) {
            $prices = [];
            foreach ($quotes as $quote) {
                $qi = $quote->items->firstWhere('rfq_item_id', (int) $item->id);
                if ($qi) {
                    $prices[$this->encodeId((int) $quote->id)] = (string) $qi->unit_price;
                }
            }
            $targetAmount = $service->lineAmount((string) $item->target_price, (string) $item->quantity);

            return [
                'rfq_item_id' => $this->encodeId((int) $item->id),
                'product_id' => $item->product_id,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'target_price' => (string) $item->target_price,
                'target_amount' => $targetAmount,
                'quote_prices' => $prices,
            ];
        })->values()->all();

        return $this->success([
            'rfq' => $this->encodeIds($rfq->toArray(), ['id', 'buyer_id', 'awarded_quote_id', 'auditor_id']),
            'target_total' => $targetTotal,
            'lowest_quote_id' => $lowestId ? $this->encodeId($lowestId) : null,
            'items' => $matrix,
            'quotes' => $quoteRows,
        ]);
    }

    /**
     * 中标：选中报价 → 生成采购订单草稿（RfqService->award 事务）
     * @Apidoc\Title("中标")
     * @Apidoc\Desc("报价置中标、询价单置已中标，并按中标行生成 erp_purchase_order 草稿（状态 0 待审核）")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}/award")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("中标")]
#[\erikwang2013\apidoc\annotation\Desc("报价置中标、询价单置已中标，并按中标行生成 erp_purchase_order 草稿（状态 0 待审核）")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function award(Request $request, string $id): Response
    {
        $quoteId = $this->decodeIdSafe((string) $request->input('quote_id', ''));
        if ($quoteId === null || $quoteId <= 0) {
            return $this->fail('缺少有效的 quote_id', 422);
        }
        try {
            $order = Container::get(RfqService::class)->award($this->decodeId($id), $quoteId, (int) ($request->adminId ?? 0));
        } catch (\Throwable $e) {
            $this->logError('rfq.award', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($order->toArray(), ['id', 'supplier_id']), '中标成功，采购订单草稿已生成');
    }

    /**
     * 关闭询价单：已发布/已中标 → 关闭；草稿 → 取消
     * @Apidoc\Title("关闭询价单")
     * @Apidoc\Url("/admin/v1/purchase/rfq/{id}/close")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */#[\erikwang2013\apidoc\annotation\Title("关闭询价单")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Tag("寻源采购")]

    public function close(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail('询价单不存在', 404);
        }
        $status = (int) $rfq->status;
        if (in_array($status, [PurchaseRfq::STATUS_CLOSED, PurchaseRfq::STATUS_CANCELLED], true)) {
            return $this->fail('询价单已关闭或取消', 422);
        }
        $rfq->status = $status === PurchaseRfq::STATUS_DRAFT ? PurchaseRfq::STATUS_CANCELLED : PurchaseRfq::STATUS_CLOSED;
        $rfq->save();

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), '操作成功');
    }

    /** 保存明细行（事务内调用，新行插入；整体替换由调用方先删除旧行） */
    private function saveItems(int $rfqId, array $items): void
    {
        foreach ($items as $row) {
            if (!isset($row['product_id'])) {
                throw new \RuntimeException('明细缺少 product_id');
            }
            $item = new PurchaseRfqItem();
            $item->id = $this->generateId();
            $item->rfq_id = $rfqId;
            $item->product_id = (int) $row['product_id'];
            $item->quantity = bc_norm($row['quantity'] ?? '0');
            $item->unit = (string) ($row['unit'] ?? '');
            $item->target_price = bc_norm($row['target_price'] ?? '0');
            $item->save();
        }
    }
}
