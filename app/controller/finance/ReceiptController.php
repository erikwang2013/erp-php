<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\Customer;
use app\model\FinanceBill;
use app\model\FinanceReceipt;
use app\model\FinanceSettlement;
use support\Request;
use support\Response;

/**
 * 收款管理
 */
#[\erikwang2013\apidoc\annotation\Tag('财务管理')]
#[\erikwang2013\apidoc\annotation\Title('收款')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class ReceiptController extends BaseController
{
    /**
     * 收款列表（分页）
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('收款列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取收款记录分页列表，支持关键字搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/receipt')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词(收款单号)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('list', type:'array', desc:'收款列表')]
    #[\erikwang2013\apidoc\annotation\Returned('total', type:'int', desc:'总条数')]
    #[\erikwang2013\apidoc\annotation\Returned('page', type:'int', desc:'当前页码')]
    #[\erikwang2013\apidoc\annotation\Returned('limit', type:'int', desc:'每页条数')]

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

        $query = FinanceReceipt::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补客户名（表无 name 列）；FK 编码供编辑弹窗下拉回填 hashid 匹配
        $names = Customer::whereIn('id', $models->pluck('customer_id')->all())
            ->pluck('name', 'id')->all();
        $list = $models->map(function ($item) use ($names) {
            $row = $this->encodeIds($item->toArray(), ['id', 'customer_id', 'bank_account_id']);
            $row['customer_name'] = $names[$item->customer_id] ?? '';

            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建收款记录
     */
    #[\erikwang2013\apidoc\annotation\Title('创建收款记录')]
    #[\erikwang2013\apidoc\annotation\Desc('创建一条新的收款记录，状态默认为待确认')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/receipt')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', require:true, desc:'收款单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'string', require:true, desc:'客户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', require:true, desc:'收款金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', default:'', desc:'银行账户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'method', type:'string', default:'bank', desc:'收款方式(cash/bank/wechat/alipay/other)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Param(name:'received_at', type:'string', default:'', desc:'收款日期(格式:Y-m-d H:i:s)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'收款记录')]

    public function store(Request $request): Response
    {
        // method 码表（并集口径）：cash/bank/wechat/alipay 出自 erp_finance_receipt.method
        // （install.sql:1202，DDL 注释只有这 4 个），other 出自两端词典 PAY_METHOD_DICTS
        // （表单 options 逐字对齐该词典）—— 值域取并集 5 值，与 apidoc 的 desc 一致。
        // 原先只校验 string（任意串落库，列表按码表渲染时表外值只能裸出），故补 in:。
        // 依赖：本表现有 3 行 method='d' 是 install-demo.sql 的演示填充串，数据侧同批修好前，
        // 这几行一旦在 Web 编辑弹窗保存就会被这条规则 422（弹窗会把表外值原样回送）。
        $validator = validator($request->all(), ['code' => 'nullable|string|max:50', 'customer_id' => 'required|string', 'amount' => 'required|numeric|min:0', 'bank_account_id' => 'string', 'method' => 'string|in:cash,bank,wechat,alipay,other', 'remark' => 'string', 'received_at' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceReceipt();
        $item->id = $this->generateId();
        $item->code = doc_code($request->input('code'), 'RCV');
        // customer_id/bank_account_id 双模：hashid 串解码，原生数字直用；垃圾串 422 拒绝
        $customerId = $this->decodeFlexibleId((string) $request->input('customer_id'));
        if ($customerId === null) {
            return $this->fail($this->trans('Invalid customer ID'), 422);
        }
        $item->customer_id = $customerId;
        // 空串/缺省 = 不指定账户（0 哨兵）；非空解不出 → 422，不静默归零
        $bankAccountId = $this->optionalId($request->input('bank_account_id', ''));
        if ($bankAccountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $item->bank_account_id = $bankAccountId;
        $item->amount = (float) $request->input('amount');
        $item->method = $request->input('method', 'bank');
        $item->remark = $request->input('remark', '');
        $item->status = 0; // Always start as pending - NOT settable by client
        $item->received_at = $request->input('received_at') ?: date('Y-m-d H:i:s');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 收款详情
     */
    #[\erikwang2013\apidoc\annotation\Title('收款详情')]
    #[\erikwang2013\apidoc\annotation\Desc('获取指定收款记录的详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'收款记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'收款详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新收款记录
     */
    #[\erikwang2013\apidoc\annotation\Title('更新收款记录')]
    #[\erikwang2013\apidoc\annotation\Desc('更新指定收款记录的信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'收款记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'收款单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'string', default:'', desc:'客户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'amount', type:'float', default:'', desc:'收款金额')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', default:'', desc:'银行账户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'method', type:'string', default:'', desc:'收款方式(cash/bank/wechat/alipay/other)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'received_at', type:'string', default:'', desc:'收款日期')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的收款记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'customer_id' => 'string',
            'amount' => 'numeric',
            'bank_account_id' => 'string',
            // 值域同 store（5 值并集，理由见 store 上注释）：UPDATE 只在 method 非 null 时落库，
            // 但原样落库的表外值会让该行此后按码表渲染时报废
            'method' => 'string|in:cash,bank,wechat,alipay,other',
            'remark' => 'string',
            'status' => 'integer',
            'received_at' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ((int) $item->status === 1) {
            return $this->fail($this->trans('Audited records cannot be modified'), 422);
        }

        if ($request->input('code') !== null) {
            $item->code = $request->input('code');
        }
        if ($request->input('customer_id') !== null) {
            $customerId = $this->decodeFlexibleId((string) $request->input('customer_id'));
            if ($customerId === null) {
                return $this->fail($this->trans('Invalid customer ID'), 422);
            }
            $item->customer_id = $customerId;
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
        if ($request->input('received_at') !== null && $request->input('received_at') !== '') {
            $item->received_at = $request->input('received_at');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除收款记录
     */
    #[\erikwang2013\apidoc\annotation\Title('删除收款记录')]
    #[\erikwang2013\apidoc\annotation\Desc('软删除指定收款记录，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'收款记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'当前管理员密码(二次确认)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'password' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceReceipt::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        // 引用守卫：已被核销（erp_finance_settlement）或已关联票据（erp_finance_bill.source）
        // 的收款单不可删——原实现直接软删，留下孤儿核销记录+断链票据
        if (FinanceSettlement::query()->where('receipt_payment_id', $id)->exists()) {
            return $this->fail($this->trans('The receipt has been written off and cannot be deleted'), 422);
        }
        if (FinanceBill::query()->where('source_type', 'receipt')->where('source_id', $id)->exists()) {
            return $this->fail($this->trans('The receipt is linked to a bill and cannot be deleted'), 422);
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
