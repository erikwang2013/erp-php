<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\Inventory;
use app\service\inventory\InventoryService;
use support\Container;
use support\Request;
use support\Response;
use Throwable;

/**
 * 库存管理
 */
#[\erikwang2013\apidoc\annotation\Tag('库存管理')]
#[\erikwang2013\apidoc\annotation\Title('库存')]
#[\erikwang2013\apidoc\annotation\Group('库存管理')]

class InventoryController extends BaseController
{
    /**
     * 库存列表（分页）
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('库存列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存分页列表，支持按商品名称/编码/批次号关键字搜索')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词(商品名称/编码/批次号)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('list', type:'array', desc:'库存列表(含 product_name/product_code/warehouse_name)')]
    #[\erikwang2013\apidoc\annotation\Returned('total', type:'int', desc:'总条数')]
    #[\erikwang2013\apidoc\annotation\Returned('page', type:'int', desc:'当前页码')]
    #[\erikwang2013\apidoc\annotation\Returned('limit', type:'int', desc:'每页条数')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');

        // erp_inventory 实列仅 product_id/sku_id/warehouse_id/location_id/batch_code/
        // quantity/cost_price（见 install.sql，无 name/code/status 幻列）；商品名称/编码
        // 与仓库名称经 leftJoin 带出，关键字搜 product.name/product.code/inventory.batch_code
        $query = Inventory::query()
            ->leftJoin('product', 'product.id', '=', 'inventory.product_id')
            ->leftJoin('warehouse', 'warehouse.id', '=', 'inventory.warehouse_id')
            ->select(
                'inventory.*',
                'product.name as product_name',
                'product.code as product_code',
                'warehouse.name as warehouse_name'
            );
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('product.name', 'like', "%{$keyword}%")
                  ->orWhere('product.code', 'like', "%{$keyword}%")
                  ->orWhere('inventory.batch_code', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('inventory.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建库存记录
     */
    #[\erikwang2013\apidoc\annotation\Title('创建库存记录')]
    #[\erikwang2013\apidoc\annotation\Desc('手动创建一条库存记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/inventory')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', require:true, desc:'名称')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存记录')]

    public function store(Request $request): Response
    {
        // 旧实现校验的是 name —— 而 erp_inventory 没有 name 列（模板残留）：正常库存 payload 必被
        // 422 拒；就算传了 name 也只能被 fillable 丢弃，插入一行全零库存。这里改为校验真实列。
        $validator = validator($request->all(), [
            'product_id' => 'required|string',
            'warehouse_id' => 'required|string',
            'quantity' => 'required|numeric',
            'sku_id' => 'string',
            'location_id' => 'string',
            'batch_code' => 'string',
            'cost_price' => 'numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 库存不允许裸插：必须经 InventoryService，才能同时写库存流水、更新实时库存、
        // 重算移动加权平均成本（裸插会绕开这三件事，是账实不符的源头）。
        try {
            Container::get(InventoryService::class)->stockIn(
                $this->decodeId((string) $request->input('product_id')),
                $request->input('sku_id') ? $this->decodeId((string) $request->input('sku_id')) : 0,
                $this->decodeId((string) $request->input('warehouse_id')),
                $request->input('location_id') ? $this->decodeId((string) $request->input('location_id')) : 0,
                (string) $request->input('batch_code', ''),
                (float) $request->input('quantity'),
                (float) $request->input('cost_price', 0),
                'manual',
                0,
            );
        } catch (Throwable $e) {
            $this->logError('库存手工入库', $e);

            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '入库成功');
    }

    /**
     * 库存详情
     */
    #[\erikwang2013\apidoc\annotation\Title('库存详情')]
    #[\erikwang2013\apidoc\annotation\Desc('获取指定库存记录的详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'库存记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'库存详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = Inventory::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新库存记录
     */
    #[\erikwang2013\apidoc\annotation\Title('更新库存记录')]
    #[\erikwang2013\apidoc\annotation\Desc('更新指定库存记录的信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'库存记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的库存记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = Inventory::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        // 禁止直接改数量：会绕开库存流水与移动加权平均成本重算（账实不符的源头）。
        // 需要按实际库存修正请走盘点/入库调整，那会生成盘盈盘亏流水。
        if ($request->input('quantity') !== null) {
            return $this->fail('不能直接修改库存数量（会绕开库存流水与成本重算）；请通过盘点或库存调整修正', 422);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除库存记录
     */
    #[\erikwang2013\apidoc\annotation\Title('删除库存记录')]
    #[\erikwang2013\apidoc\annotation\Desc('软删除指定库存记录，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('库存管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'库存记录ID(hashid)')]
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
        $item = Inventory::find($id);
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
