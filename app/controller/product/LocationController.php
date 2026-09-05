<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("商品管理")
 */
declare(strict_types=1);

namespace app\controller\product;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\Location;
use app\service\product\ProductService;
use support\Container;
use support\Request;
use support\Response;

class LocationController extends BaseController
{
    /**
     * 库位列表（分页）
     * @Apidoc\Title("库位列表")
     * @Apidoc\Desc("获取库位列表，支持分页、关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/location")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（名称/编码）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选（0=禁用,1=启用）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("库位列表")]
#[Apidoc\Desc("获取库位列表，支持分页、关键词搜索和状态筛选")]
#[Apidoc\Url("/admin/v1/location")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"page", type:"int", default:1, desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（名称/编码）")]
#[Apidoc\Param(name:"status", type:"int", default:"", desc:"状态筛选（0=禁用,1=启用）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
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
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'warehouse_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 按仓库获取库位列表
     * @Apidoc\Title("按仓库获取库位")
     * @Apidoc\Desc("根据仓库ID获取该仓库下的所有库位列表")
     * @Apidoc\Url("/admin/v1/warehouse/{id}/locations")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="仓库hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="库位列表")
     */#[Apidoc\Title("按仓库获取库位")]
#[Apidoc\Desc("根据仓库ID获取该仓库下的所有库位列表")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"仓库hashid")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"库位列表")]

    public function byWarehouse(Request $request, string $warehouseHashid): Response
    {
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
     * @Apidoc\Title("创建库位")
     * @Apidoc\Desc("新增一个库位记录")
     * @Apidoc\Url("/admin/v1/location")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="name", type="string", default="", desc="库位名称（必填）")
     * @Apidoc\Param(name="code", type="string", default="", desc="库位编码")
     * @Apidoc\Param(name="warehouse_id", type="string", default="", desc="所属仓库hashid")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态（0=禁用,1=启用）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="库位记录")
     */#[Apidoc\Title("创建库位")]
#[Apidoc\Desc("新增一个库位记录")]
#[Apidoc\Url("/admin/v1/location")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"name", type:"string", default:"", desc:"库位名称（必填）")]
#[Apidoc\Param(name:"code", type:"string", default:"", desc:"库位编码")]
#[Apidoc\Param(name:"warehouse_id", type:"string", default:"", desc:"所属仓库hashid")]
#[Apidoc\Param(name:"status", type:"int", default:1, desc:"状态（0=禁用,1=启用）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"库位记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->product()->create(Location::class, $request->all());

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '创建成功');
    }

    /**
     * 库位详情
     * @Apidoc\Title("库位详情")
     * @Apidoc\Desc("根据ID获取库位详细信息")
     * @Apidoc\Url("/admin/v1/location/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="库位hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="库位详情")
     */#[Apidoc\Title("库位详情")]
#[Apidoc\Desc("根据ID获取库位详细信息")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"库位详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->product()->find(Location::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']));
    }

    /**
     * 更新库位
     * @Apidoc\Title("更新库位")
     * @Apidoc\Desc("根据ID更新库位信息")
     * @Apidoc\Url("/admin/v1/location/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="库位hashid")
     * @Apidoc\Param(name="name", type="string", default="", desc="库位名称")
     * @Apidoc\Param(name="code", type="string", default="", desc="库位编码")
     * @Apidoc\Param(name="warehouse_id", type="string", default="", desc="所属仓库hashid")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态（0=禁用,1=启用）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的库位记录")
     */#[Apidoc\Title("更新库位")]
#[Apidoc\Desc("根据ID更新库位信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[Apidoc\Param(name:"name", type:"string", default:"", desc:"库位名称")]
#[Apidoc\Param(name:"code", type:"string", default:"", desc:"库位编码")]
#[Apidoc\Param(name:"warehouse_id", type:"string", default:"", desc:"所属仓库hashid")]
#[Apidoc\Param(name:"status", type:"int", default:"", desc:"状态（0=禁用,1=启用）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"更新后的库位记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->product()->update(Location::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除库位（软删除）
     * @Apidoc\Title("删除库位")
     * @Apidoc\Desc("根据ID软删除库位，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/location/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="库位hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */#[Apidoc\Title("删除库位")]
#[Apidoc\Desc("根据ID软删除库位，需管理员密码二次确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("商品管理")]
#[Apidoc\Param(name:"id", type:"string", default:"", desc:"库位hashid")]
#[Apidoc\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
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
