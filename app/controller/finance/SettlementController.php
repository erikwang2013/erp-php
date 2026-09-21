<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
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

#[\erikwang2013\apidoc\annotation\Title('核销记录')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class SettlementController extends BaseController
{
    /**
     * 核销记录列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('核销记录列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询核销记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/settlement')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);

        $query = FinanceSettlement::query();
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建核销记录（经服务层校验余额并同步 erp_finance_ar_ap.settled_amount）
     */
    #[\erikwang2013\apidoc\annotation\Title('创建核销记录')]
    #[\erikwang2013\apidoc\annotation\Desc('按应收应付类型走收款/付款核销，超出未核销余额将拒绝')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/settlement')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'ar_ap_id', type:'int', desc:'应收应付ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'receipt_payment_id', type:'int', desc:'收付款ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'核销金额，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['ar_ap_id' => 'required|string', 'receipt_payment_id' => 'required|string', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 双模解码：两个 ID 是 hashid 串，原 validator 要 integer、这里用 decodeId 解 hashid，
        // 两条路互斥 → 任何入参都 422，端点根本进不来
        $arApId = $this->decodeFlexibleId($request->input('ar_ap_id', ''));
        $receiptPaymentId = $this->decodeFlexibleId($request->input('receipt_payment_id', ''));
        if ($arApId === null || $arApId < 1 || $receiptPaymentId === null || $receiptPaymentId < 1) {
            return $this->fail($this->trans('Invalid ar_ap_id or receipt_payment_id'), 422);
        }
        $amount = (float) $request->input('amount');

        $arAp = FinanceArAp::find($arApId);
        if (!$arAp) {
            return $this->fail($this->trans('Receivable/payable record not found'), 404);
        }

        try {
            /** @var FinanceService $service */
            $service = Container::get(FinanceService::class);
            if ($arAp->type === 1) {
                $service->settleReceipt($receiptPaymentId, $arApId, $amount);
            } elseif ($arAp->type === 2) {
                $service->settlePayment($receiptPaymentId, $arApId, $amount);
            } else {
                return $this->fail($this->trans('Illegal write-off target type'), 422);
            }
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], $this->trans('Created successfully'));
    }

    /**
     * 核销记录详情
     */
    #[\erikwang2013\apidoc\annotation\Title('核销记录详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看核销记录详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceSettlement::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

}
