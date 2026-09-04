<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("采购管理")
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseOrder;
use support\Request;
use support\Response;

class OrderController extends BaseController
{
    /**
     * 采购订单列表（分页）
     * @Apidoc\Title("采购订单列表")
     * @Apidoc\Desc("获取采购订单列表，支持分页、关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/purchase/order")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
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

        $query = PurchaseOrder::query();
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

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建采购订单
     * @Apidoc\Title("创建采购订单")
     * @Apidoc\Desc("新增一个采购订单记录")
     * @Apidoc\Url("/admin/v1/purchase/order")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="name", type="string", default="", desc="订单名称（必填）")
     * @Apidoc\Param(name="code", type="string", default="", desc="订单编号")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="采购订单记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new PurchaseOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 采购订单详情
     * @Apidoc\Title("采购订单详情")
     * @Apidoc\Desc("根据ID获取采购订单详细信息")
     * @Apidoc\Url("/admin/v1/purchase/order/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购订单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="采购订单详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新采购订单
     * @Apidoc\Title("更新采购订单")
     * @Apidoc\Desc("根据ID更新采购订单信息")
     * @Apidoc\Url("/admin/v1/purchase/order/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购订单hashid")
     * @Apidoc\Param(name="name", type="string", default="", desc="订单名称")
     * @Apidoc\Param(name="code", type="string", default="", desc="订单编号")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的采购订单记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除采购订单（软删除）
     * @Apidoc\Title("删除采购订单")
     * @Apidoc\Desc("根据ID软删除采购订单，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/purchase/order/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购订单hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
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
