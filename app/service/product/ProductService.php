<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\product;

use app\model\Product;
use app\model\ProductPrice;
use app\model\ProductSku;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use Throwable;

/**
 * 商品管理模块薄服务层（P2-F2）
 *
 * 承接 product 模块 7 个控制器的模型查询/写入逻辑：
 *  - 通用 CRUD（品牌/分类/客户/供应商/仓库/库位等）由 AbstractCrudService 提供；
 *  - 本类沉淀商品特有逻辑：创建商品（事务内同时写入 SKU 与价格）、
 *    商品更新（按字段缺省保留原值）、商品详情关联加载等。
 *
 * 事务在服务内开启/提交/回滚，失败时向控制器抛出异常（控制器负责
 * 记录日志并返回 500 响应，与旧控制器行为一致）。
 */
class ProductService extends AbstractCrudService
{
    /**
     * 创建商品：事务内写入商品主表 + SKU 列表 + 价格列表
     *
     * @param array<string, mixed> $data 商品字段（category_id / brand_id 已由控制器解码为 int）
     * @param array<int, array<string, mixed>> $skus SKU 数据列表
     * @param array<int, array<string, mixed>> $prices 价格数据列表
     * @throws Throwable 任一写入失败时回滚事务并抛出
     */
    public function createProductWithRelations(array $data, array $skus = [], array $prices = []): Product
    {
        DB::beginTransaction();
        try {
            $product = new Product();
            $product->id = $this->generateId();
            $product->code = $data['code'] ?? '';
            $product->name = $data['name'] ?? '';
            $product->category_id = (int) ($data['category_id'] ?? 0);
            $product->brand_id = (int) ($data['brand_id'] ?? 0);
            $product->barcode = $data['barcode'] ?? '';
            $product->spec = $data['spec'] ?? '';
            $product->unit = $data['unit'] ?? '';
            $product->image = $data['image'] ?? '';
            $product->description = $data['description'] ?? '';
            $product->status = (int) ($data['status'] ?? 1);
            $product->save();

            foreach ($skus as $skuData) {
                $sku = new ProductSku();
                $sku->id = $this->generateId();
                $sku->product_id = $product->id;
                foreach ($this->normalizeSku($skuData) as $field => $value) {
                    $sku->$field = $value;
                }
                $sku->save();
            }

            foreach ($prices as $priceData) {
                $price = new ProductPrice();
                $price->id = $this->generateId();
                $price->product_id = $product->id;
                $price->sku_id = 0;
                $price->price_type = $priceData['price_type'];
                $price->price = (float) $priceData['price'];
                $price->save();
            }

            DB::commit();

            return $product;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 更新商品：请求中未出现的字段保留原值；category_id / brand_id 已由控制器解码
     *
     * @param array<string, mixed> $input 请求字段（仅包含实际传入的键）
     * @return Product|null 商品不存在返回 null
     */
    public function updateProduct(int $id, array $input): ?Product
    {
        $product = Product::find($id);
        if (!$product) {
            return null;
        }

        foreach (['name', 'barcode', 'spec', 'unit', 'image', 'description'] as $field) {
            if (array_key_exists($field, $input)) {
                $product->$field = (string) $input[$field];
            }
        }
        if (array_key_exists('status', $input)) {
            $product->status = (int) $input['status'];
        }
        // category_id / brand_id：控制器仅在原值为真时解码后传入，此处按键存在即更新
        if (array_key_exists('category_id', $input)) {
            $product->category_id = (int) $input['category_id'];
        }
        if (array_key_exists('brand_id', $input)) {
            $product->brand_id = (int) $input['brand_id'];
        }
        $product->save();

        return $product;
    }

    /**
     * 商品详情（含分类/品牌/SKU/价格/单位关联）
     *
     * @return Product|null 商品不存在返回 null
     */
    public function findProductWithRelations(int $id): ?Product
    {
        $query = Product::with(['category', 'brand', 'skus', 'prices', 'units']);

        return $query->find($id);
    }

    /**
     * SKU 数据归一化（纯逻辑，可单测）
     * 与旧控制器创建 SKU 时的字段赋值语义完全一致：
     * sku_code/barcode 缺省为空串，spec_attrs 以 JSON 存储，cost_price 缺省 0，status 固定 1。
     *
     * @param array<string, mixed> $skuData 原始 SKU 数据
     * @return array<string, mixed> 归一化后的 SKU 字段
     */
    public function normalizeSku(array $skuData): array
    {
        return [
            'sku_code' => (string) ($skuData['sku_code'] ?? ''),
            'barcode' => (string) ($skuData['barcode'] ?? ''),
            'spec_attrs' => json_encode($skuData['spec_attrs'] ?? [], JSON_UNESCAPED_UNICODE),
            'cost_price' => (float) ($skuData['cost_price'] ?? 0),
            'status' => 1,
        ];
    }
}
