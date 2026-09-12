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
use InvalidArgumentException;
use JsonException;
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

        // SKU 差量同步：控制器仅在显式传 skus 键时放入（id/spec_id 已解码为 int）
        if (array_key_exists('skus', $input) && is_array($input['skus'])) {
            $this->syncSkus($product, $input['skus']);
        }

        // 标量 price：替换产品级默认价（sku_id=0/price_type=default）；空串不更新
        if (array_key_exists('price', $input) && trim((string) $input['price']) !== '') {
            ProductPrice::where('product_id', $product->id)
                ->where('sku_id', 0)
                ->where('price_type', 'default')
                ->delete();
            $priceRow = new ProductPrice();
            $priceRow->id = $this->generateId();
            $priceRow->product_id = $product->id;
            $priceRow->sku_id = 0;
            $priceRow->price_type = 'default';
            $priceRow->price = (float) $input['price'];
            $priceRow->save();
        }

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
            'spec_id' => (int) ($skuData['spec_id'] ?? 0),
            'sku_code' => (string) ($skuData['sku_code'] ?? ''),
            'barcode' => (string) ($skuData['barcode'] ?? ''),
            'spec_attrs' => json_encode($skuData['spec_attrs'] ?? [], JSON_UNESCAPED_UNICODE),
            'cost_price' => (float) ($skuData['cost_price'] ?? 0),
            'status' => 1,
        ];
    }

    /**
     * SKU 差量同步（更新商品时）：按行 id 分「更新 / 新增」，本次未出现的既有行删除。
     *
     * 顺序很关键：**先算差量、做引用守卫，再写** —— 商品主表在调用本方法前已 save()，
     * 若把守卫放在写循环之后，被拒时会留下「商品已更新、SKU 未同步」的半成品。
     *
     * 行 id 仅当属于本商品时才视为更新（防止把别的商品的 SKU 抢过来），否则一律新建。
     * 删除前查 erp_product_price.sku_id 引用：被引用则抛 InvalidArgumentException（控制器转 422）。
     * 行未显式带 status 时保留既有状态，避免差量更新把已禁用 SKU 悄悄启用。
     *
     * @param array<int, array<string, mixed>> $skus 控制器已解码的行（id/spec_id 为 int）
     * @throws InvalidArgumentException 待删除的 SKU 仍被价格记录引用
     */
    private function syncSkus(Product $product, array $skus): void
    {
        $existing = ProductSku::where('product_id', $product->id)->get()->keyBy('id');

        $incoming = [];
        foreach ($skus as $skuData) {
            $rawId = isset($skuData['id']) ? (int) $skuData['id'] : 0;
            if ($rawId > 0 && $existing->has($rawId)) {
                $incoming[] = $rawId;
            }
        }
        $removed = array_values(array_diff($existing->keys()->all(), $incoming));
        if ($removed !== [] && ProductPrice::whereIn('sku_id', $removed)->exists()) {
            throw new InvalidArgumentException('SKU 已被价格记录引用，不能删除；请先清理相关价格');
        }

        foreach ($skus as $skuData) {
            $rawId = isset($skuData['id']) ? (int) $skuData['id'] : 0;
            $sku = ($rawId > 0 && $existing->has($rawId)) ? $existing->get($rawId) : null;
            if (!$sku) {
                $sku = new ProductSku();
                $sku->id = $this->generateId();
                $sku->product_id = $product->id;
            }
            $normalized = $this->normalizeSku($skuData);
            if ($sku->exists && !array_key_exists('status', $skuData)) {
                unset($normalized['status']);
            }
            foreach ($normalized as $field => $value) {
                $sku->$field = $value;
            }
            $sku->save();
        }

        if ($removed !== []) {
            ProductSku::whereIn('id', $removed)->delete();
        }
    }

    /**
     * 规格属性归一化（纯逻辑，可单测）
     * 接受 JSON 字符串或已解码的 PHP 数组/对象，统一存为 JSON 对象字符串
     * （JSON_UNESCAPED_UNICODE，与 ProductSku::normalizeSku 的 spec_attrs 同风格）。
     *
     * 空值（null / '' / {} / []）归一化为 '{}'：AbstractCrudService::fillableOnly()
     * 用 isset() 过滤，NULL 会被静默丢弃导致"清空属性"无法保存，故用 '{}' 表达空。
     *
     * @param mixed $raw 请求传入的 attrs（字符串或数组/对象）
     * @return string 归一化后的 JSON 对象字符串，恒为对象（如 '{}'）
     * @throws InvalidArgumentException 非 JSON 对象、值非字符串数组时抛出（控制器转 422）
     */
    public function normalizeSpecAttrs(mixed $raw): string
    {
        if ($raw === null) {
            return '{}';
        }
        if (is_string($raw)) {
            if (trim($raw) === '') {
                return '{}';
            }
            try {
                $raw = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException('attrs 不是合法的 JSON 字符串');
            }
        }
        if (is_object($raw)) {
            // JSON 字符串解出的对象：{} 与 {"0":...} 的真身，取属性即可，不参与下面的 list 判定
            $raw = get_object_vars($raw);
        } elseif (!is_array($raw)) {
            throw new InvalidArgumentException('attrs 必须是 JSON 对象（属性名 => 值数组）');
        } elseif ($raw !== [] && array_is_list($raw)) {
            // 仅 PHP 数组入参需判 list（JSON 对象已由 is_object 分支排除）；空数组视同空对象
            throw new InvalidArgumentException('attrs 必须是 JSON 对象（属性名 => 值数组），不能是数组');
        }
        if ($raw === []) {
            return '{}';
        }
        foreach ($raw as $name => $values) {
            if (!is_array($values) || !array_is_list($values)) {
                throw new InvalidArgumentException("attrs.{$name} 必须是字符串数组");
            }
            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new InvalidArgumentException("attrs.{$name} 的值必须是字符串");
                }
            }
        }

        // 强制对象编码：PHP 会把数字字符串键（如 {"0":[...]}）还原为 int 键，直接编码会退化成 JSON 数组
        return json_encode((object) $raw, JSON_UNESCAPED_UNICODE);
    }
}
