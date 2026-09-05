<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Product;
use app\service\product\ProductService;
use support\Container;
use support\Request;
use support\Response;
use Throwable;

/**
 * 商品管理
 */
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Title("商品")]

class ProductController extends BaseController
{
    /**
     * 商品列表（分页）
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("商品列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取商品分页列表，支持关键字/分类/状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词(名称/编码/条码)")]
#[\erikwang2013\apidoc\annotation\Param(name:"category_id", type:"string", default:"", desc:"分类ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选:0禁用1启用")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"商品列表")]
#[\erikwang2013\apidoc\annotation\Returned("total", type:"int", desc:"总条数")]
#[\erikwang2013\apidoc\annotation\Returned("page", type:"int", desc:"当前页码")]
#[\erikwang2013\apidoc\annotation\Returned("limit", type:"int", desc:"每页条数")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $filters = [
            'keyword' => $keyword,
            'status' => $status,
        ];
        if ($categoryId !== null && $categoryId !== '') {
            $filters['category_id'] = $this->decodeId($categoryId);
        }

        $result = $this->product()->list(Product::class, $filters, $page, $limit, [
            'searchFields' => ['name', 'code', 'barcode'],
            'eqFilters' => ['status', 'category_id'],
            'with' => ['category', 'brand'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'category_id', 'brand_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建商品
     */
#[\erikwang2013\apidoc\annotation\Title("创建商品")]
#[\erikwang2013\apidoc\annotation\Desc("创建新商品，可同时创建SKU和价格")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"商品名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"商品编码")]
#[\erikwang2013\apidoc\annotation\Param(name:"category_id", type:"string", require:true, desc:"分类ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"unit", type:"string", require:true, desc:"单位")]
#[\erikwang2013\apidoc\annotation\Param(name:"brand_id", type:"string", default:"", desc:"品牌ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"barcode", type:"string", default:"", desc:"条码")]
#[\erikwang2013\apidoc\annotation\Param(name:"spec", type:"string", default:"", desc:"规格型号")]
#[\erikwang2013\apidoc\annotation\Param(name:"image", type:"string", default:"", desc:"图片URL")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"商品描述")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态:0禁用1启用")]
#[\erikwang2013\apidoc\annotation\Param(name:"skus", type:"array", default:"", desc:"SKU列表")]
#[\erikwang2013\apidoc\annotation\Param(name:"prices", type:"array", default:"", desc:"价格列表")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"商品信息")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50',
            'category_id' => 'required|string',
            'unit' => 'required|string|max:20',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $product = $this->product()->createProductWithRelations([
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'category_id' => $this->decodeId($request->input('category_id')),
                'brand_id' => $request->input('brand_id') ? $this->decodeId($request->input('brand_id')) : 0,
                'barcode' => $request->input('barcode', ''),
                'spec' => $request->input('spec', ''),
                'unit' => $request->input('unit'),
                'image' => $request->input('image', ''),
                'description' => $request->input('description', ''),
                'status' => (int) $request->input('status', 1),
            ], is_array($request->input('skus')) ? $request->input('skus') : [], is_array($request->input('prices')) ? $request->input('prices') : []);

            return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), $this->trans('created'));
        } catch (Throwable $e) {
            $this->logError('创建商品', $e);

            return $this->fail($this->trans('fail') . ': ' . $e->getMessage(), 500);
        }
    }

    /**
     * 商品详情
     */
#[\erikwang2013\apidoc\annotation\Title("商品详情")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定商品的详细信息，包含分类、品牌、SKU、价格和单位")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"商品ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"商品详情(含关联数据)")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $product = $this->product()->findProductWithRelations($id);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']));
    }

    /**
     * 更新商品
     */
#[\erikwang2013\apidoc\annotation\Title("更新商品")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定商品的信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"商品ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"商品名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"barcode", type:"string", default:"", desc:"条码")]
#[\erikwang2013\apidoc\annotation\Param(name:"spec", type:"string", default:"", desc:"规格型号")]
#[\erikwang2013\apidoc\annotation\Param(name:"unit", type:"string", default:"", desc:"单位")]
#[\erikwang2013\apidoc\annotation\Param(name:"image", type:"string", default:"", desc:"图片URL")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"商品描述")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态:0禁用1启用")]
#[\erikwang2013\apidoc\annotation\Param(name:"category_id", type:"string", default:"", desc:"分类ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"brand_id", type:"string", default:"", desc:"品牌ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的商品信息")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);

        $input = $request->all();
        // 与原控制器一致：category_id / brand_id 为空时不更新，非空时解码为 int
        foreach (['category_id', 'brand_id'] as $fk) {
            if (empty($input[$fk])) {
                unset($input[$fk]);
            } else {
                $input[$fk] = $this->decodeId($input[$fk]);
            }
        }

        $product = $this->product()->updateProduct($id, $input);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), $this->trans('updated'));
    }

    /**
     * 删除商品
     */
#[\erikwang2013\apidoc\annotation\Title("删除商品")]
#[\erikwang2013\apidoc\annotation\Desc("软删除指定商品，需要密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("商品管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"商品ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前管理员密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $product = $this->product()->find(Product::class, $id);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->product()->delete(Product::class, $id);

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 商品模块薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function product(): ProductService
    {
        return Container::get(ProductService::class);
    }
}
