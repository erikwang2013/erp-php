<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("销售管理")
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\SalesSettlement;
use support\Request;
use support\Response;

class SettlementController extends BaseController
{
    /**
     * 销售结算列表（分页）
     * @Apidoc\Title("销售结算列表")
     * @Apidoc\Desc("获取销售结算列表，支持分页、关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/sales/settlement")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（名称/编码）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
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

        $query = SalesSettlement::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
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
     * 创建销售结算
     * @Apidoc\Title("创建销售结算")
     * @Apidoc\Desc("新增一个销售结算记录")
     * @Apidoc\Url("/admin/sales/settlement")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="name", type="string", default="", desc="结算名称（必填）")
     * @Apidoc\Param(name="code", type="string", default="", desc="结算单号")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="销售结算记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new SalesSettlement();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') {
                $item->$k = $v;
            }
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 销售结算详情
     * @Apidoc\Title("销售结算详情")
     * @Apidoc\Desc("根据ID获取销售结算详细信息")
     * @Apidoc\Url("/admin/sales/settlement/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="销售结算hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="销售结算详情")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = SalesSettlement::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新销售结算
     * @Apidoc\Title("更新销售结算")
     * @Apidoc\Desc("根据ID更新销售结算信息")
     * @Apidoc\Url("/admin/sales/settlement/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="销售结算hashid")
     * @Apidoc\Param(name="name", type="string", default="", desc="结算名称")
     * @Apidoc\Param(name="code", type="string", default="", desc="结算单号")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的销售结算记录")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = SalesSettlement::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') {
                $item->$k = $v;
            }
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除销售结算（软删除）
     * @Apidoc\Title("删除销售结算")
     * @Apidoc\Desc("根据ID软删除销售结算，需管理员密码二次确认")
     * @Apidoc\Url("/admin/sales/settlement/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="销售结算hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = SalesSettlement::find($id);
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
