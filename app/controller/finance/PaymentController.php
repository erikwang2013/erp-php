<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinancePayment;
use support\Request;
use support\Response;

class PaymentController extends BaseController
{
    /**
     * 付款单列表（分页）
     * @Apidoc\Title("付款单列表")
     * @Apidoc\Desc("分页查询付款单记录")
     * @Apidoc\Url("/admin/finance/payment")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
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

        $query = FinancePayment::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建付款单
     * @Apidoc\Title("创建付款单")
     * @Apidoc\Desc("新增付款单记录，状态默认为待付款")
     * @Apidoc\Url("/admin/finance/payment")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="code", type="string", desc="付款单号，必填")
     * @Apidoc\Param(name="supplier_id", type="string", desc="供应商ID，必填")
     * @Apidoc\Param(name="amount", type="float", desc="金额，必填")
     * @Apidoc\Param(name="bank_account_id", type="string", desc="银行账户ID")
     * @Apidoc\Param(name="method", type="string", desc="付款方式")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:50', 'supplier_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinancePayment();
        $item->id = $this->generateId();
        $item->code = $request->input('code');
        $item->supplier_id = $this->decodeId($request->input('supplier_id'));
        $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        $item->amount = (float) $request->input('amount');
        $item->method = $request->input('method', 'bank');
        $item->remark = $request->input('remark', '');
        $item->status = 0;
        $item->paid_at = $request->input('paid_at') ?: date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 付款单详情
     * @Apidoc\Title("付款单详情")
     * @Apidoc\Desc("查看付款单详细信息")
     * @Apidoc\Url("/admin/finance/payment/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="付款单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新付款单
     * @Apidoc\Title("更新付款单")
     * @Apidoc\Desc("修改付款单信息")
     * @Apidoc\Url("/admin/finance/payment/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="付款单ID")
     * @Apidoc\Param(name="code", type="string", desc="付款单号")
     * @Apidoc\Param(name="amount", type="float", desc="金额")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        if ($request->has('code')) $item->code = $request->input('code');
        if ($request->has('supplier_id')) $item->supplier_id = $this->decodeId($request->input('supplier_id'));
        if ($request->has('bank_account_id')) $item->bank_account_id = $this->decodeId($request->input('bank_account_id', '0'));
        if ($request->has('amount')) $item->amount = (float) $request->input('amount');
        if ($request->has('method')) $item->method = $request->input('method');
        if ($request->has('remark')) $item->remark = $request->input('remark');
        if ($request->has('status')) $item->status = (int) $request->input('status');
        if ($request->has('paid_at')) $item->paid_at = $request->input('paid_at');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除付款单
     * @Apidoc\Title("删除付款单")
     * @Apidoc\Desc("删除付款单记录，需密码确认")
     * @Apidoc\Url("/admin/finance/payment/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="付款单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinancePayment::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
