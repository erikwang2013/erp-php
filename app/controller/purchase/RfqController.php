<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\common\SnowflakeService;
use app\model\PurchaseRfq;
use app\model\PurchaseRfqItem;
use app\model\PurchaseRfqQuote;
use app\service\purchase\RfqService;
// 门面 \Illuminate\Support\Facades\DB 在本项目没有根（无 Facade::setFacadeApplication），
// 一调用就抛 RuntimeException「A facade root has not been set.」——询价全链路必失败；
// 统一用 Capsule 管理器（其余控制器同款）。
use Illuminate\Database\Capsule\Manager as DB;
use support\Container;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('询价单')]
#[\erikwang2013\apidoc\annotation\Group('采购管理')]

class RfqController extends BaseController
{
    /**
     * 询价单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('询价单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('询比价单列表，支持状态筛选与 rfq_no 关键词')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/rfq')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
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
     */
    #[\erikwang2013\apidoc\annotation\Title('创建询价单')]
    #[\erikwang2013\apidoc\annotation\Desc('询价单头与明细行（product_id/quantity/unit/target_price）一并保存')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/rfq')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function store(Request $request): Response
    {
        // 头字段同样按列型校验（原先只校验 items）：supplier_range/remark 超 varchar(500)
        // 报 1406、require_date 落 datetime 报 1292、明细 unit 超 varchar(20)/target_price
        // 非数值落 DECIMAL 报 1265——都被下方 catch 包成 422 并把原始 SQL 文案抛给用户
        $validator = validator($request->all(), [
            'items' => 'required|array|min:1',
            // product_id 是商品下拉下发的 hashid（与收货/发货明细同一契约），
            // 用 integer 规则会把它整条挡回 422；解码在 saveItems 里做，垃圾串在那里报错
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999999999.99',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.target_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'buyer_id' => 'nullable',
            'supplier_range' => 'nullable|string|max:500',
            'remark' => 'nullable|string|max:500',
            'require_date' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $rfq = DB::transaction(function () use ($request) {
                $rfq = new PurchaseRfq();
                $rfq->id = $this->generateId();
                $rfq->rfq_no = 'RFQ' . SnowflakeService::generate();
                // buyer_id 由采购员下拉下发 hashid（详情/列表也按 hashid 出参）：原写法 (int)'rwkrlayn'
                // = 0 → 静默落回当前登录管理员，采购员被悄悄记错。缺省仍取当前登录管理员
                $rawBuyer = $request->input('buyer_id');
                $buyerId = ($rawBuyer === null || $rawBuyer === '')
                    ? (int) ($request->adminId ?? 0)
                    : $this->decodeFlexibleId($rawBuyer);
                if ($buyerId === null || $buyerId < 1) {
                    throw new \RuntimeException('采购员（buyer_id）无效');
                }
                $rfq->buyer_id = $buyerId;
                $rfq->supplier_range = (string) $request->input('supplier_range', '');
                // 清空下发 ''：列可空但 '' 落 datetime 报 1292，空串语义即「不填」
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

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), $this->trans('Created successfully'));
    }

    /**
     * 询价单详情（含明细与报价）
     */
    #[\erikwang2013\apidoc\annotation\Title('询价单详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function show(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::with(['items', 'quotes' => fn ($q) => $q->with('items')->orderBy('id', 'desc')])
            ->find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
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
     */
    #[\erikwang2013\apidoc\annotation\Title('更新询价单')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function update(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
        }
        if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
            return $this->fail($this->trans('Only draft RFQs can be edited'), 422);
        }

        // 与 store 同一套边界：头字段直写列型不符会以 1264/1292/1366/1406 被 catch 成 422 原始 SQL
        $validator = validator($request->all(), [
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999999999.99',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.target_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'buyer_id' => 'nullable',
            'supplier_range' => 'nullable|string|max:500',
            'remark' => 'nullable|string|max:500',
            'require_date' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            // 返回值即事务内取得行锁的那份实例：响应若沿用闭包外的旧实例会回显改前值
            $rfq = DB::transaction(function () use ($request, $id) {
                // 事务内行锁重读：上面的 DRAFT 检查在事务外，并发 award 会 lockForUpdate 后把
                // 状态置为已中标，两边都通过校验就会出现「已中标单被改写头/明细」（award 同款双保险）
                $rfq = PurchaseRfq::query()->lockForUpdate()->find($this->decodeId($id));
                if (!$rfq) {
                    throw new \RuntimeException($this->trans('RFQ not found'));
                }
                if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
                    throw new \RuntimeException($this->trans('Only draft RFQs can be edited'));
                }
                // buyer_id 原写法把下拉下发的 hashid 直写 buyer_id BIGINT UNSIGNED → 1366；
                // 而 show() 出参的 buyer_id 恰是 hashid（编辑表单回显即是它），故「改采购员」必失败
                if ($request->has('buyer_id')) {
                    $rawBuyer = $request->input('buyer_id');
                    if ($rawBuyer !== null && $rawBuyer !== '') {
                        $buyerId = $this->decodeFlexibleId($rawBuyer);
                        if ($buyerId === null || $buyerId < 1) {
                            throw new \RuntimeException('采购员（buyer_id）无效');
                        }
                        $rfq->buyer_id = $buyerId;
                    }
                }
                foreach (['supplier_range', 'remark'] as $field) {
                    if ($request->has($field)) {
                        $rfq->{$field} = (string) $request->input($field);
                    }
                }
                if ($request->has('require_date')) {
                    // 清空下发 ''：列可空但 '' 落 datetime 报 1292，空串语义即「不填」
                    $rfq->require_date = $request->input('require_date') ?: null;
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

                return $rfq;
            });
        } catch (\Throwable $e) {
            $this->logError('rfq.update', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除询价单（软删除，仅草稿，需管理员密码二次确认）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除询价单')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function destroy(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
        }
        // 草稿经 close() 变「已取消」，只认草稿会让取消单永远删不掉（终态死路）。
        // 取消态只可能来自草稿（报价须已发布），故不存在「删主单留孤儿报价」
        if (!in_array((int) $rfq->status, [PurchaseRfq::STATUS_DRAFT, PurchaseRfq::STATUS_CANCELLED], true)) {
            return $this->fail($this->trans('Only draft RFQs can be deleted'), 422);
        }
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $rfq->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 发布询价（草稿 → 已发布，开放报价登记）
     */
    #[\erikwang2013\apidoc\annotation\Title('发布询价')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function submit(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
        }
        if ((int) $rfq->status !== PurchaseRfq::STATUS_DRAFT) {
            return $this->fail($this->trans('Only draft RFQs can be published'), 422);
        }
        $rfq->status = PurchaseRfq::STATUS_SUBMITTED;
        $rfq->save();

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), $this->trans('Published successfully'));
    }

    /**
     * 比价汇总：报价按金额升序（bccomp）+ 行单价对比目标价
     */
    #[\erikwang2013\apidoc\annotation\Title('比价汇总')]
    #[\erikwang2013\apidoc\annotation\Desc('全部有效报价按总额升序排列并标注最低价；逐行给出各供应商单价与目标价对比')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function compare(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::with('items')->find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
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
     */
    #[\erikwang2013\apidoc\annotation\Title('中标')]
    #[\erikwang2013\apidoc\annotation\Desc('报价置中标、询价单置已中标，并按中标行生成 erp_purchase_order 草稿（状态 0 待审核）')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function award(Request $request, string $id): Response
    {
        $quoteId = $this->decodeFlexibleId((string) $request->input('quote_id', ''));
        if ($quoteId === null || $quoteId <= 0) {
            return $this->fail($this->trans('Missing a valid quote_id'), 422);
        }
        try {
            $order = Container::get(RfqService::class)->award($this->decodeId($id), $quoteId, (int) ($request->adminId ?? 0));
        } catch (\Throwable $e) {
            $this->logError('rfq.award', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($order->toArray(), ['id', 'supplier_id']), $this->trans('Awarded successfully; a draft purchase order has been created'));
    }

    /**
     * 关闭询价单：已发布/已中标 → 关闭；草稿 → 取消
     */
    #[\erikwang2013\apidoc\annotation\Title('关闭询价单')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function close(Request $request, string $id): Response
    {
        $rfq = PurchaseRfq::find($this->decodeId($id));
        if (!$rfq) {
            return $this->fail($this->trans('RFQ not found'), 404);
        }
        $status = (int) $rfq->status;
        if (in_array($status, [PurchaseRfq::STATUS_CLOSED, PurchaseRfq::STATUS_CANCELLED], true)) {
            return $this->fail($this->trans('The RFQ has been closed or cancelled'), 422);
        }
        $rfqId = $this->decodeId($id);
        try {
            $rfq = DB::transaction(function () use ($rfqId) {
                // 状态判定须在行锁内重做：与 award 并发时，事务外读到「询价中」再写关闭会把
                // 刚落定的「已中标」（及其已生成的采购订单）覆盖成「已关闭」
                $locked = PurchaseRfq::query()->lockForUpdate()->find($rfqId);
                if (!$locked) {
                    throw new \RuntimeException($this->trans('RFQ not found'));
                }
                $status = (int) $locked->status;
                if (in_array($status, [PurchaseRfq::STATUS_CLOSED, PurchaseRfq::STATUS_CANCELLED], true)) {
                    throw new \RuntimeException($this->trans('The RFQ has been closed or cancelled'));
                }
                $locked->status = $status === PurchaseRfq::STATUS_DRAFT ? PurchaseRfq::STATUS_CANCELLED : PurchaseRfq::STATUS_CLOSED;
                $locked->save();

                return $locked;
            });
        } catch (\Throwable $e) {
            $this->logError('rfq.close', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($rfq->toArray(), ['id', 'buyer_id']), $this->trans('Operation successful'));
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
            // hashid 双模解码：原写法 (int)'rwkrlayn0eAr' = 0，垃圾/未解码串会静默落 0 成孤儿行
            $productId = $this->decodeFlexibleId($row['product_id']);
            if ($productId === null || $productId < 1) {
                throw new \RuntimeException('明细 product_id 无效');
            }
            $item->product_id = $productId;
            $item->quantity = bc_norm($row['quantity'] ?? '0');
            $item->unit = (string) ($row['unit'] ?? '');
            $item->target_price = bc_norm($row['target_price'] ?? '0');
            $item->save();
        }
    }
}
