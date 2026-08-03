<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceArAp;
use support\Request;
use support\Response;

class ArApController extends BaseController
{
    /**
     * 应收应付列表（分页）
     * @Apidoc\Title("应收应付列表")
     * @Apidoc\Desc("分页查询应收应付记录")
     * @Apidoc\Url("/admin/finance/ar-ap")
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

        $query = FinanceArAp::query();
        if ($keyword) {
            $query->where('partner_id', $this->decodeId($keyword));
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建应收应付
     * @Apidoc\Title("创建应收应付")
     * @Apidoc\Desc("新增应收应付记录")
     * @Apidoc\Url("/admin/finance/ar-ap")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="type", type="int", desc="类型：1应收2应付")
     * @Apidoc\Param(name="partner_id", type="string", desc="往来方ID")
     * @Apidoc\Param(name="source_type", type="string", desc="来源类型")
     * @Apidoc\Param(name="source_id", type="string", desc="来源ID")
     * @Apidoc\Param(name="amount", type="float", desc="金额")
     * @Apidoc\Param(name="due_date", type="string", desc="到期日")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['type' => 'required|integer', 'partner_id' => 'required|integer', 'amount' => 'required|numeric|min:0']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceArAp();
        $item->id = $this->generateId();
        $item->type = (int) $request->input('type');
        $item->partner_id = $this->decodeId($request->input('partner_id'));
        $item->source_type = $request->input('source_type', '');
        $item->source_id = $this->decodeId($request->input('source_id', '0'));
        $item->amount = (float) $request->input('amount');
        $item->settled_amount = 0;
        $item->status = 0;
        $item->due_date = $request->input('due_date');
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 应收应付详情
     * @Apidoc\Title("应收应付详情")
     * @Apidoc\Desc("查看应收应付记录详情")
     * @Apidoc\Url("/admin/finance/ar-ap/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="记录ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新应收应付
     * @Apidoc\Title("更新应收应付")
     * @Apidoc\Desc("修改应收应付记录")
     * @Apidoc\Url("/admin/finance/ar-ap/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="记录ID")
     * @Apidoc\Param(name="partner_id", type="string", desc="往来方ID")
     * @Apidoc\Param(name="amount", type="float", desc="金额")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="due_date", type="string", desc="到期日")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->has('partner_id')) {
            $item->partner_id = $this->decodeId($request->input('partner_id'));
        }
        if ($request->has('amount')) {
            $item->amount = (float) $request->input('amount');
        }
        if ($request->has('status')) {
            $item->status = (int) $request->input('status');
        }
        if ($request->has('due_date')) {
            $item->due_date = $request->input('due_date');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除应收应付
     * @Apidoc\Title("删除应收应付")
     * @Apidoc\Desc("删除应收应付记录，需密码确认")
     * @Apidoc\Url("/admin/finance/ar-ap/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="记录ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceArAp::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }
}
