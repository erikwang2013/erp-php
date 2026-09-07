<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Location;
use app\model\Warehouse;
use app\service\product\ProductService;
use support\Container;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("库位")]
#[\erikwang2013\apidoc\annotation\Group("商品基础数据")]

class LocationController extends BaseController
{
    /**
     * 库位列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("库位列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取库位列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/location")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（名称/编码）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选（0=禁用,1=启用）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $result = $this->product()->list(Location::class, [
            'keyword' => $keyword,
            'status' => $status,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
        ]);
        // 行补引用名：仓库名（表无仓库名列，页面展示此前取幻列 warehouse 恒为空）
        $warehouseNames = Warehouse::whereIn('id', array_unique(array_column($result['list'], 'warehouse_id')))
            ->pluck('name', 'id')->all();
        $list = array_map(function ($item) use ($warehouseNames) {
            $row = $this->encodeIds($item, ['id', 'warehouse_id']);
            $row['warehouse_name'] = $warehouseNames[$item['warehouse_id']] ?? '';
            return $row;
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 按仓库获取库位列表
     */
#[\erikwang2013\apidoc\annotation\Title("按仓库获取库位")]
#[\erikwang2013\apidoc\annotation\Desc("根据仓库ID获取该仓库下的所有库位列表")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"仓库hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"库位列表")]

    public function byWarehouse(Request $request, string $warehouseHashid): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $warehouseId = $this->decodeId($warehouseHashid);
        $list = $this->product()->all(Location::class, [
            'warehouse_id' => $warehouseId,
        ], [
            'eqFilters' => ['warehouse_id'],
            'orderBy' => 'id',
            'orderDir' => 'desc',
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'warehouse_id']), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 创建库位
     */
#[\erikwang2013\apidoc\annotation\Title("创建库位")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个库位记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/location")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"库位名称（必填）")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"库位编码")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"string", default:"", desc:"所属仓库hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态（0=禁用,1=启用）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"库位记录")]

    public function store(Request $request): Response
    {
        // 库位旧表单以仓库名（幻列 warehouse）代替 warehouse_id 提交 → 引用永不落库；
        // 现改为显式赋值：warehouse_id 必填且 hashid/原生数字双模解码，垃圾串 422 拒绝
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'code' => 'string', 'warehouse_id' => 'string', 'status' => 'integer']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $warehouseId = $this->decodeFlexibleId((string) $request->input('warehouse_id', ''));
        if ($warehouseId === null || $warehouseId < 1) {
            return $this->fail('仓库ID无效', 422);
        }

        $item = new Location();
        $item->id = $this->generateId();
        $item->warehouse_id = $warehouseId;
        // code 留空自动生成（uk(warehouse_id,code)：时间戳+随机后缀降低同仓同秒碰撞）
        $item->code = (string) $request->input('code', '');
        if ($item->code === '') {
            $item->code = 'LOC' . date('YmdHis') . mt_rand(10, 99);
        }
        $item->name = (string) $request->input('name', '');
        $statusRaw = $request->input('status');
        $item->status = ($statusRaw === null || $statusRaw === '') ? 1 : (int) $statusRaw;
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '创建成功');
    }

    /**
     * 库位详情
     */
#[\erikwang2013\apidoc\annotation\Title("库位详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取库位详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"库位详情")]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->product()->find(Location::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']));
    }

    /**
     * 更新库位
     */
#[\erikwang2013\apidoc\annotation\Title("更新库位")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新库位信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"库位名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"库位编码")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"string", default:"", desc:"所属仓库hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态（0=禁用,1=启用）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的库位记录")]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'name' => 'string',
            'code' => 'string',
            'warehouse_id' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = Location::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->input('code') !== null) {
            $item->code = (string) $request->input('code');
        }
        if ($request->input('name') !== null) {
            $item->name = (string) $request->input('name');
        }
        $warehouseRaw = $request->input('warehouse_id');
        if ($warehouseRaw !== null && $warehouseRaw !== '') {
            $warehouseId = $this->decodeFlexibleId((string) $warehouseRaw);
            if ($warehouseId === null || $warehouseId < 1) {
                return $this->fail('仓库ID无效', 422);
            }
            $item->warehouse_id = $warehouseId;
        }
        $statusRaw = $request->input('status');
        if ($statusRaw !== null && $statusRaw !== '') {
            $item->status = (int) $statusRaw;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除库位（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除库位")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除库位，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->product()->find(Location::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->product()->delete(Location::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 商品模块薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function product(): ProductService
    {
        return Container::get(ProductService::class);
    }
}
