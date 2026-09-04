<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceArAp;
use app\model\FinanceSettlement;
use app\service\finance\FinanceService;
use support\Container;
use support\Request;
use support\Response;

class SettlementController extends BaseController
{
    /**
     * 核销记录列表（分页）
     * @Apidoc\Title("核销记录列表")
     * @Apidoc\Desc("分页查询核销记录")
     * @Apidoc\Url("/admin/v1/finance/settlement")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = FinanceSettlement::query();
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建核销记录（经服务层校验余额并同步 erp_finance_ar_ap.settled_amount）
     * @Apidoc\Title("创建核销记录")
     * @Apidoc\Desc("按应收应付类型走收款/付款核销，超出未核销余额将拒绝")
     * @Apidoc\Url("/admin/v1/finance/settlement")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="ar_ap_id", type="int", desc="应收应付ID，必填")
     * @Apidoc\Param(name="receipt_payment_id", type="int", desc="收付款ID，必填")
     * @Apidoc\Param(name="amount", type="float", desc="核销金额，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['ar_ap_id' => 'required|integer', 'receipt_payment_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $arApId = $this->decodeId($request->input('ar_ap_id'));
        $receiptPaymentId = $this->decodeId($request->input('receipt_payment_id'));
        $amount = (float) $request->input('amount');

        $arAp = FinanceArAp::find($arApId);
        if (!$arAp) {
            return $this->fail('应收应付记录不存在', 404);
        }

        try {
            /** @var FinanceService $service */
            $service = Container::get(FinanceService::class);
            if ($arAp->type === 1) {
                $service->settleReceipt($receiptPaymentId, $arApId, $amount);
            } elseif ($arAp->type === 2) {
                $service->settlePayment($receiptPaymentId, $arApId, $amount);
            } else {
                return $this->fail('核销对象类型非法', 422);
            }
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '创建成功');
    }

    /**
     * 核销记录详情
     * @Apidoc\Title("核销记录详情")
     * @Apidoc\Desc("查看核销记录详细信息")
     * @Apidoc\Url("/admin/v1/finance/settlement/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="记录ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceSettlement::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

}
