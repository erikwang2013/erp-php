<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Product;
use app\model\ProductPrice;
use app\model\ProductSku;
use Illuminate\Database\Capsule\Manager as DB;
use support\Request;
use support\Response;

/**
 * 商品管理
 * @Apidoc\Tag("商品管理")
 */
class ProductController extends BaseController
{
    /**
     * 商品列表（分页）
     * @Apidoc\Title("商品列表")
     * @Apidoc\Desc("获取商品分页列表，支持关键字/分类/状态筛选")
     * @Apidoc\Url("/admin/product")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(名称/编码/条码)")
     * @Apidoc\Param(name="category_id", type="string", default="", desc="分类ID(hashid)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选:0禁用1启用")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="商品列表"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $query = Product::with(['category', 'brand']);
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%")
                  ->orWhere('barcode', 'like', "%{$keyword}%");
            });
        }
        if ($categoryId !== null && $categoryId !== '') {
            $query->where('category_id', $this->decodeId($categoryId));
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')->get()->map(function ($p) {
                return $this->encodeIds($p->toArray(), ['id', 'category_id', 'brand_id']);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建商品
     * @Apidoc\Title("创建商品")
     * @Apidoc\Desc("创建新商品，可同时创建SKU和价格")
     * @Apidoc\Url("/admin/product")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="name", type="string", require=true, desc="商品名称")
     * @Apidoc\Param(name="code", type="string", require=true, desc="商品编码")
     * @Apidoc\Param(name="category_id", type="string", require=true, desc="分类ID(hashid)")
     * @Apidoc\Param(name="unit", type="string", require=true, desc="单位")
     * @Apidoc\Param(name="brand_id", type="string", default="", desc="品牌ID(hashid)")
     * @Apidoc\Param(name="barcode", type="string", default="", desc="条码")
     * @Apidoc\Param(name="spec", type="string", default="", desc="规格型号")
     * @Apidoc\Param(name="image", type="string", default="", desc="图片URL")
     * @Apidoc\Param(name="description", type="string", default="", desc="商品描述")
     * @Apidoc\Param(name="status", type="int", default=1, desc="状态:0禁用1启用")
     * @Apidoc\Param(name="skus", type="array", default="", desc="SKU列表")
     * @Apidoc\Param(name="prices", type="array", default="", desc="价格列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="商品信息")
     */
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

        DB::beginTransaction();
        try {
            $product = new Product();
            $product->id = $this->generateId();
            $product->code = $request->input('code');
            $product->name = $request->input('name');
            $product->category_id = $this->decodeId($request->input('category_id'));
            $product->brand_id = $request->input('brand_id') ? $this->decodeId($request->input('brand_id')) : 0;
            $product->barcode = $request->input('barcode', '');
            $product->spec = $request->input('spec', '');
            $product->unit = $request->input('unit');
            $product->image = $request->input('image', '');
            $product->description = $request->input('description', '');
            $product->status = (int) $request->input('status', 1);
            $product->save();

            if ($request->has('skus') && is_array($request->input('skus'))) {
                foreach ($request->input('skus') as $skuData) {
                    $sku = new ProductSku();
                    $sku->id = $this->generateId();
                    $sku->product_id = $product->id;
                    $sku->sku_code = $skuData['sku_code'] ?? '';
                    $sku->barcode = $skuData['barcode'] ?? '';
                    $sku->spec_attrs = json_encode($skuData['spec_attrs'] ?? [], JSON_UNESCAPED_UNICODE);
                    $sku->cost_price = (float) ($skuData['cost_price'] ?? 0);
                    $sku->status = 1;
                    $sku->save();
                }
            }

            if ($request->has('prices') && is_array($request->input('prices'))) {
                foreach ($request->input('prices') as $priceData) {
                    $price = new ProductPrice();
                    $price->id = $this->generateId();
                    $price->product_id = $product->id;
                    $price->sku_id = 0;
                    $price->price_type = $priceData['price_type'];
                    $price->price = (float) $priceData['price'];
                    $price->save();
                }
            }

            DB::commit();

            return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), $this->trans('created'));
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->fail($this->trans('fail') . ': ' . $e->getMessage(), 500);
        }
    }

    /**
     * 商品详情
     * @Apidoc\Title("商品详情")
     * @Apidoc\Desc("获取指定商品的详细信息，包含分类、品牌、SKU、价格和单位")
     * @Apidoc\Url("/admin/product/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="商品ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="商品详情(含关联数据)")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with(['category', 'brand', 'skus', 'prices', 'units'])->find($id);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']));
    }

    /**
     * 更新商品
     * @Apidoc\Title("更新商品")
     * @Apidoc\Desc("更新指定商品的信息")
     * @Apidoc\Url("/admin/product/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="商品ID(hashid)")
     * @Apidoc\Param(name="name", type="string", default="", desc="商品名称")
     * @Apidoc\Param(name="barcode", type="string", default="", desc="条码")
     * @Apidoc\Param(name="spec", type="string", default="", desc="规格型号")
     * @Apidoc\Param(name="unit", type="string", default="", desc="单位")
     * @Apidoc\Param(name="image", type="string", default="", desc="图片URL")
     * @Apidoc\Param(name="description", type="string", default="", desc="商品描述")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态:0禁用1启用")
     * @Apidoc\Param(name="category_id", type="string", default="", desc="分类ID(hashid)")
     * @Apidoc\Param(name="brand_id", type="string", default="", desc="品牌ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的商品信息")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $product->name = $request->input('name', $product->name);
        $product->barcode = $request->input('barcode', $product->barcode);
        $product->spec = $request->input('spec', $product->spec);
        $product->unit = $request->input('unit', $product->unit);
        $product->image = $request->input('image', $product->image);
        $product->description = $request->input('description', $product->description);
        $product->status = (int) $request->input('status', $product->status);
        if ($request->input('category_id')) {
            $product->category_id = $this->decodeId($request->input('category_id'));
        }
        if ($request->input('brand_id')) {
            $product->brand_id = $this->decodeId($request->input('brand_id'));
        }
        $product->save();

        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), $this->trans('updated'));
    }

    /**
     * 删除商品
     * @Apidoc\Title("删除商品")
     * @Apidoc\Desc("软删除指定商品，需要密码二次确认")
     * @Apidoc\Url("/admin/product/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("商品管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="商品ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $product->delete();

        return $this->success([], $this->trans('deleted'));
    }
}
