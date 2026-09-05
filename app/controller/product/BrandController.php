<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
 */
declare(strict_types=1);

namespace app\controller\product;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\Brand;
use app\service\product\ProductService;
use support\Container;
use support\Request;
use support\Response;

class BrandController extends BaseController
{
    /**
     * 品牌列表（分页）
     * @Apidoc\Title("品牌列表")
     * @Apidoc\Desc("分页查询品牌记录")
     * @Apidoc\Url("/admin/v1/brand")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("品牌列表")]
#[Apidoc\Desc("分页查询品牌记录")]
#[Apidoc\Url("/admin/v1/brand")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("CRM")]
#[Apidoc\Param(name:"page", type:"int", desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", desc:"关键词")]
#[Apidoc\Param(name:"status", type:"int", desc:"状态")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $result = $this->product()->list(Brand::class, [
            'keyword' => $keyword,
            'status' => $status,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建品牌
     * @Apidoc\Title("创建品牌")
     * @Apidoc\Desc("新增品牌记录")
     * @Apidoc\Url("/admin/v1/brand")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="name", type="string", desc="品牌名称，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建品牌")]
#[Apidoc\Desc("新增品牌记录")]
#[Apidoc\Url("/admin/v1/brand")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("CRM")]
#[Apidoc\Param(name:"name", type:"string", desc:"品牌名称，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->product()->create(Brand::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 品牌详情
     * @Apidoc\Title("品牌详情")
     * @Apidoc\Desc("查看品牌详细信息")
     * @Apidoc\Url("/admin/v1/brand/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="品牌ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("品牌详情")]
#[Apidoc\Desc("查看品牌详细信息")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("CRM")]
#[Apidoc\Param(name:"id", type:"string", desc:"品牌ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->product()->find(Brand::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新品牌
     * @Apidoc\Title("更新品牌")
     * @Apidoc\Desc("修改品牌信息")
     * @Apidoc\Url("/admin/v1/brand/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="品牌ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("更新品牌")]
#[Apidoc\Desc("修改品牌信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("CRM")]
#[Apidoc\Param(name:"id", type:"string", desc:"品牌ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->product()->update(Brand::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除品牌
     * @Apidoc\Title("删除品牌")
     * @Apidoc\Desc("删除品牌记录，需密码确认")
     * @Apidoc\Url("/admin/v1/brand/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="品牌ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除品牌")]
#[Apidoc\Desc("删除品牌记录，需密码确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("CRM")]
#[Apidoc\Param(name:"id", type:"string", desc:"品牌ID")]
#[Apidoc\Param(name:"password", type:"string", desc:"管理员密码")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->product()->find(Brand::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->product()->delete(Brand::class, $id);

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
