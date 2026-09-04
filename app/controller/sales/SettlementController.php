<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("销售管理")
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\FinanceArAp;
use app\model\FinanceSettlement;
use app\service\finance\FinanceService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 销售结算 = erp_finance_ar_ap（source_type=sales_delivery）的薄视图，
 * 状态由 settled_amount/amount 推导；核销走 FinanceService::settleReceipt。
 */
class SettlementController extends BaseController
{
    private const AR_TYPE = 1;
    private const SOURCE_TYPE = 'sales_delivery';

    /**
     * 销售结算列表（分页）
     * @Apidoc\Title("销售结算列表")
     * @Apidoc\Desc("基于应收记录查询销售结算，状态按已核销金额推导")
     * @Apidoc\Url("/admin/v1/sales/settlement")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（客户名称）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态: 0=未结算 1=部分结算 2=已结算（服务端推导）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinanceArAp::query()->where('type', self::AR_TYPE)->where('source_type', self::SOURCE_TYPE);
        if ($keyword) {
            $query->join('erp_customer', 'erp_customer.id', '=', 'erp_finance_ar_ap.partner_id')
                  ->select('erp_finance_ar_ap.*')
                  ->where('erp_customer.name', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->whereRaw(match ((int) $status) {
                0 => 'settled_amount <= 0',
                1 => 'settled_amount > 0 AND settled_amount < amount',
                2 => 'settled_amount >= amount',
                default => '1 = 0',
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('erp_finance_ar_ap.id', 'desc')->get();

        $settledAtMap = [];
        if (!$list->isEmpty()) {
            // 先 get() 取分组行再 Collection::pluck：Query::pluck 会替换 select 导致 MAX 聚合丢失
            $settledAtMap = FinanceSettlement::whereIn('ar_ap_id', $list->pluck('id'))
                ->selectRaw('ar_ap_id, MAX(settled_at) AS settled_at')
                ->groupBy('ar_ap_id')
                ->get()
                ->pluck('settled_at', 'ar_ap_id')
                ->all();
        }

        $rows = $list->map(fn (FinanceArAp $item) => $this->format($item, $settledAtMap[$item->id] ?? null))->values();

        return $this->success(['list' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 销售结算核销（经服务层）
     * @Apidoc\Title("创建销售结算核销")
     * @Apidoc\Desc("对发货单应收记录执行收款核销，状态由服务层推导，客户端传 status 一律忽略")
     * @Apidoc\Url("/admin/v1/sales/settlement")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="delivery_id", type="string", default="", desc="发货单ID hashid（必填）")
     * @Apidoc\Param(name="receipt_payment_id", type="string", default="", desc="收款单ID hashid（必填，需已审核）")
     * @Apidoc\Param(name="amount", type="number", default="", desc="核销金额（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'delivery_id' => 'required',
            'receipt_payment_id' => 'required',
            'amount' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $deliveryId = $this->decodeId((string) $request->input('delivery_id'));
        $arAp = FinanceArAp::where('type', self::AR_TYPE)->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $deliveryId)->first();
        if (!$arAp) {
            return $this->fail('发货单应收记录不存在，请先确认发货已审核', 404);
        }

        try {
            /** @var FinanceService $service */
            $service = Container::get(FinanceService::class);
            $service->settleReceipt(
                $this->decodeId((string) $request->input('receipt_payment_id')),
                (int) $arAp->id,
                (float) $request->input('amount')
            );
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '核销成功');
    }

    /**
     * 销售结算详情
     * @Apidoc\Title("销售结算详情")
     * @Apidoc\Desc("根据应收记录ID获取销售结算详细信息")
     * @Apidoc\Url("/admin/v1/sales/settlement/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="应收记录hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="销售结算详情")
     */
    public function show(Request $request, string $id): Response
    {
        $item = FinanceArAp::where('id', $this->decodeId($id))
            ->where('type', self::AR_TYPE)->where('source_type', self::SOURCE_TYPE)->first();
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $settledAt = FinanceSettlement::where('ar_ap_id', $item->id)->max('settled_at');

        return $this->success($this->format($item, $settledAt));
    }

    /**
     * 更新销售结算（仅应收金额）
     * @Apidoc\Title("更新销售结算")
     * @Apidoc\Desc("仅允许调整应收金额，且不得小于已核销金额；状态由服务端推导")
     * @Apidoc\Url("/admin/v1/sales/settlement/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="应收记录hashid")
     * @Apidoc\Param(name="amount", type="number", default="", desc="应收金额")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的销售结算记录")
     */
    public function update(Request $request, string $id): Response
    {
        $item = FinanceArAp::where('id', $this->decodeId($id))
            ->where('type', self::AR_TYPE)->where('source_type', self::SOURCE_TYPE)->first();
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->input('amount') !== null && $request->input('amount') !== '') {
            $amount = bc_norm($request->input('amount'));
            if (bccomp($amount, bc_norm($item->settled_amount), 4) < 0) {
                return $this->fail('金额不能小于已核销金额', 422);
            }
            $item->amount = $amount;
            // status 由核销流程(FinanceService)维护、format() 推导，此处不写避免双源
            $item->save();
        }

        $settledAt = FinanceSettlement::where('ar_ap_id', $item->id)->max('settled_at');

        return $this->success($this->format($item, $settledAt), '更新成功');
    }

    /**
     * 删除销售结算（仅未核销记录）
     * @Apidoc\Title("删除销售结算")
     * @Apidoc\Desc("删除未核销的应收记录，需管理员密码二次确认；已核销记录不可删除")
     * @Apidoc\Url("/admin/v1/sales/settlement/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="应收记录hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $item = FinanceArAp::where('id', $this->decodeId($id))
            ->where('type', self::AR_TYPE)->where('source_type', self::SOURCE_TYPE)->first();
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if (bccomp(bc_norm($item->settled_amount), '0', 4) > 0) {
            return $this->fail('已核销记录不可删除', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }

    private function format(FinanceArAp $item, ?string $settledAt): array
    {
        $data = $item->toArray();
        $data['customer_id'] = $item->partner_id;
        $data['delivery_id'] = $item->source_id;
        $data['received_amount'] = $item->settled_amount;
        $settled = bc_norm($item->settled_amount);
        $data['status'] = bccomp($settled, bc_norm($item->amount), 4) >= 0 ? 2 : (bccomp($settled, '0', 4) > 0 ? 1 : 0);
        $data['settled_at'] = $settledAt;
        unset($data['partner_id'], $data['source_id'], $data['source_type'], $data['settled_amount']);

        return $this->encodeIds($data, ['id', 'customer_id', 'delivery_id']);
    }
}
