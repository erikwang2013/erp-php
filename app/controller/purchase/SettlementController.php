<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("采购管理")
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseSettlement;
use support\Request;
use support\Response;

class SettlementController extends BaseController
{
    /**
     * 采购结算列表（分页）
     * @Apidoc\Title("采购结算列表")
     * @Apidoc\Desc("获取采购结算列表，支持分页、关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/purchase/settlement")
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

        $query = PurchaseSettlement::query();
        if ($keyword) {
            $query->join('erik_supplier', 'erik_supplier.id', '=', 'erik_purchase_settlement.supplier_id')
                  ->where('erik_supplier.name', 'like', "%{$keyword}%");
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
     * 创建采购结算
     * @Apidoc\Title("创建采购结算")
     * @Apidoc\Desc("新增一个采购结算记录")
     * @Apidoc\Url("/admin/purchase/settlement")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="supplier_id", type="string", default="", desc="供应商ID hashid（必填）")
     * @Apidoc\Param(name="receive_id", type="string", default="", desc="收货单ID hashid（必填）")
     * @Apidoc\Param(name="amount", type="number", default="", desc="应付金额（必填）")
     * @Apidoc\Param(name="status", type="int", default=0, desc="状态: 0=未结算 1=部分结算 2=已结算")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="采购结算记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'supplier_id' => 'required',
            'receive_id' => 'required',
            'amount' => 'required|numeric',
            'status' => 'nullable|integer|between:0,2',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new PurchaseSettlement();
        $item->id = $this->generateId();
        $item->supplier_id = $this->decodeId((string) $request->input('supplier_id'));
        $item->receive_id = $this->decodeId((string) $request->input('receive_id'));
        $item->amount = (float) $request->input('amount');
        $item->status = (int) $request->input('status', 0);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 采购结算详情
     * @Apidoc\Title("采购结算详情")
     * @Apidoc\Desc("根据ID获取采购结算详细信息")
     * @Apidoc\Url("/admin/purchase/settlement/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购结算hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="采购结算详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseSettlement::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新采购结算
     * @Apidoc\Title("更新采购结算")
     * @Apidoc\Desc("根据ID更新采购结算信息")
     * @Apidoc\Url("/admin/purchase/settlement/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购结算hashid")
     * @Apidoc\Param(name="supplier_id", type="string", default="", desc="供应商ID hashid")
     * @Apidoc\Param(name="receive_id", type="string", default="", desc="收货单ID hashid")
     * @Apidoc\Param(name="amount", type="number", default="", desc="应付金额")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态: 0=未结算 1=部分结算 2=已结算")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的采购结算记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseSettlement::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->input('supplier_id')) {
            $item->supplier_id = $this->decodeId((string) $request->input('supplier_id'));
        }
        if ($request->input('receive_id')) {
            $item->receive_id = $this->decodeId((string) $request->input('receive_id'));
        }
        if ($request->input('amount') !== null && $request->input('amount') !== '') {
            $item->amount = (float) $request->input('amount');
        }
        if ($request->input('status') !== null && $request->input('status') !== '') {
            $item->status = (int) $request->input('status');
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除采购结算（软删除）
     * @Apidoc\Title("删除采购结算")
     * @Apidoc\Desc("根据ID软删除采购结算，需管理员密码二次确认")
     * @Apidoc\Url("/admin/purchase/settlement/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="采购结算hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = PurchaseSettlement::find($id);
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
