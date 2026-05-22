<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Product;
use app\model\ProductSku;
use app\model\ProductPrice;
use support\Request;
use support\Response;
use Illuminate\Database\Capsule\Manager as DB;

class ProductController extends BaseController
{
    /**
     * 商品列表（分页，支持关键字/分类/状态筛选）
     * GET /product
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
     * 创建商品（可同时创建 SKU 和价格）
     * POST /product
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50',
            'category_id' => 'required|string',
            'unit' => 'required|string|max:20',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

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
            return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), '创建成功');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->fail('创建失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 商品详情（含关联数据）
     * GET /product/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with(['category', 'brand', 'skus', 'prices', 'units'])->find($id);
        if (!$product) return $this->fail('商品不存在', 404);
        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']));
    }

    /**
     * 更新商品
     * PUT /product/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) return $this->fail('商品不存在', 404);

        $product->name = $request->input('name', $product->name);
        $product->barcode = $request->input('barcode', $product->barcode);
        $product->spec = $request->input('spec', $product->spec);
        $product->unit = $request->input('unit', $product->unit);
        $product->image = $request->input('image', $product->image);
        $product->description = $request->input('description', $product->description);
        $product->status = (int) $request->input('status', $product->status);
        if ($request->input('category_id')) $product->category_id = $this->decodeId($request->input('category_id'));
        if ($request->input('brand_id')) $product->brand_id = $this->decodeId($request->input('brand_id'));
        $product->save();
        return $this->success($this->encodeIds($product->toArray(), ['id', 'category_id', 'brand_id']), '更新成功');
    }

    /**
     * 删除商品（软删除，需密码二次确认）
     * DELETE /product/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::find($id);
        if (!$product) return $this->fail('商品不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $product->delete();
        return $this->success([], '删除成功');
    }
}
