<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceBankAccount;
use app\model\FinancePayment;
use app\model\FinanceSettlement;
use app\model\Supplier;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('付款单')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class PaymentController extends BaseController
{
    /**
     * 付款单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('付款单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询付款单记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/payment')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = FinancePayment::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补供应商名（表无 name 列）；FK 编码供编辑弹窗下拉回填 hashid 匹配
        $names = Supplier::whereIn('id', $models->pluck('supplier_id')->all())
            ->pluck('name', 'id')->all();
        $accountNames = FinanceBankAccount::query()
            ->whereIn('id', $models->pluck('bank_account_id')->all())
            ->pluck('name', 'id')->all();
        $list = $models->map(function ($item) use ($names, $accountNames) {
            $data = $item->toArray();
            $row = $this->encodeIds($data, ['id', 'supplier_id', 'bank_account_id']);
            $row['supplier_name'] = $names[$item->supplier_id] ?? '';
            // 名称按裸 ID 查（$row 里已是 hashid）
            $row['bank_account_name'] = $accountNames[$data['bank_account_id']] ?? '';

            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建付款单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建付款单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增付款单记录，状态默认为待付款')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/payment')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'付款单号，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'supplier_id', type:'string', desc:'供应商ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'金额，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', desc:'银行账户ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'method', type:'string', desc:'付款方式(cash/bank/wechat/alipay/other)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // method 码表（并集口径）：cash/bank/wechat/alipay 出自 erp_finance_payment.method
        // （install.sql:1221，与 receipt 那张列注释逐字相同），other 出自两端词典 PAY_METHOD_DICTS
        // （表单 options 逐字对齐该词典）。本字段只校验 string、无 in: 白名单，改的只是注释文字
        $validator = validator($request->all(), ['code' => 'nullable|string|max:50', 'supplier_id' => 'required|string', 'amount' => 'required|numeric|min:0', 'bank_account_id' => 'string', 'method' => 'string', 'remark' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinancePayment();
        $item->id = $this->generateId();
        $item->code = doc_code($request->input('code'), 'PAY');
        // supplier_id/bank_account_id 双模：hashid 串解码，原生数字直用；垃圾串 422 拒绝
        $supplierId = $this->decodeFlexibleId((string) $request->input('supplier_id'));
        if ($supplierId === null) {
            return $this->fail($this->trans('Invalid supplier ID'), 422);
        }
        $item->supplier_id = $supplierId;
        // 空串/缺省 = 不指定账户（0 哨兵）；非空解不出 → 422，不静默归零
        $bankAccountId = $this->optionalId($request->input('bank_account_id', ''));
        if ($bankAccountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $item->bank_account_id = $bankAccountId;
        $item->amount = (float) $request->input('amount');
        $item->method = $request->input('method', 'bank');
        $item->remark = $request->input('remark', '');
        $item->status = 0;
        $item->paid_at = $request->input('paid_at') ?: date('Y-m-d H:i:s');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 付款单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('付款单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看付款单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'付款单ID')]
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
        $item = FinancePayment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新付款单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新付款单')]
    #[\erikwang2013\apidoc\annotation\Desc('修改付款单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'付款单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'付款单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', desc:'金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'amount' => 'numeric',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinancePayment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail($this->trans('Audited records cannot be modified'), 422);
        }

        if ($request->input('code') !== null) {
            $item->code = $request->input('code');
        }
        if ($request->input('supplier_id') !== null) {
            $supplierId = $this->decodeFlexibleId((string) $request->input('supplier_id'));
            if ($supplierId === null) {
                return $this->fail($this->trans('Invalid supplier ID'), 422);
            }
            $item->supplier_id = $supplierId;
        }
        if ($request->input('bank_account_id') !== null && $request->input('bank_account_id') !== '') {
            $bankAccountId = $this->optionalId($request->input('bank_account_id'));
            if ($bankAccountId === null) {
                return $this->fail($this->trans('Invalid bank account ID'), 422);
            }
            $item->bank_account_id = $bankAccountId;
        }
        if ($request->input('amount') !== null) {
            $item->amount = (float) $request->input('amount');
        }
        if ($request->input('method') !== null) {
            $item->method = $request->input('method');
        }
        if ($request->input('remark') !== null) {
            $item->remark = $request->input('remark');
        }
        // status 仅可 0→1（审核动作），客户端传其他值一律拒绝
        if ($request->input('status') !== null) {
            if ((int) $request->input('status') !== 1) {
                return $this->fail($this->trans('Only audited status (1) is supported'), 422);
            }
            $item->status = 1;
        }
        // 空串视同未填：列 NOT NULL 且非字符串，写入 '' 会走 MySQL 1292 → 500；
        // 前端「空串一律不送」的约定下 '' 也不该表达"清空时间"（无法清空）
        if ($request->input('paid_at') !== null && $request->input('paid_at') !== '') {
            $item->paid_at = $request->input('paid_at');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除付款单
     */
    #[\erikwang2013\apidoc\annotation\Title('删除付款单')]
    #[\erikwang2013\apidoc\annotation\Desc('删除付款单记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'付款单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinancePayment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        // 引用守卫：已被核销（erp_finance_settlement）的付款单不可删——原实现直接软删，
        // 留下孤儿核销记录（应付台账的已核销额与凭证断链）
        if (FinanceSettlement::query()->where('receipt_payment_id', $id)->exists()) {
            return $this->fail($this->trans('The payment has been written off and cannot be deleted'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 可选银行账户入参：缺省/null/空串 → 0（未指定哨兵）；非空 → decodeFlexibleId，
     * 解不出（垃圾串/数组）→ null 由调用方 422。
     * 不用 `decodeFlexibleId($v) ?? 0`：垃圾串会被当成"未指定"，静默清空已设账户。
     */
    private function optionalId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        return $this->decodeFlexibleId($raw);
    }
}
