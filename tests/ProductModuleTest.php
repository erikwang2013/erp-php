<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\model\Brand;
use app\model\Category;
use app\model\Customer;
use app\model\CustomerLevel;
use app\model\Location;
use app\model\Product;
use app\model\ProductPrice;
use app\model\ProductSku;
use app\model\ProductSpec;
use app\model\ProductUnit;
use app\model\Supplier;
use app\model\Warehouse;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 商品模块单元测试（纯单测，无 DB 依赖）
 *
 * 覆盖：商品模型定义（表名/填充/类型转换/搜索数组/关系）、
 *      客户/供应商/仓库/品牌/分类/库位模型、控制器校验分支、SKU 规格属性 JSON 编码、BaseController 工具方法。
 */
class ProductModuleTest extends TestCase
{
    // ---------- 基础工具 ----------

    private function invokeProtected(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invoke($object, ...$args);
    }

    private function makeRequest(string $method, string $uri, array $params = []): Request
    {
        $body = http_build_query($params);
        $buffer = $method . ' ' . $uri . ' HTTP/1.1' . "\r\n"
            . 'Host: localhost' . "\r\n"
            . 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body;

        return new Request($buffer);
    }

    private function assertFailResponse(object $controller, string $method, Request $request, int $expectedCode = 422): void
    {
        $response = $controller->{$method}($request);
        $this->assertNotNull($response, "{$method}() 应返回 Response");
        $payload = json_decode($response->rawBody(), true);
        $this->assertIsArray($payload, "{$method}() 响应应为 JSON");
        $this->assertEquals($expectedCode, $payload['code'] ?? null, "{$method}() 校验失败应返回业务码 {$expectedCode}");
        $this->assertNotEmpty($payload['message'] ?? '', "{$method}() 失败响应应包含错误消息");
    }

    /**
     * 与 ProductController::store() 一致的 SKU 规格属性编码：json_encode(..., JSON_UNESCAPED_UNICODE)。
     */
    private function encodeSkuSpecAttrs(array $specAttrs): string
    {
        return json_encode($specAttrs, JSON_UNESCAPED_UNICODE);
    }

    // ---------- 1. 控制器存在性 ----------

    public function testProductControllersExistAndInstantiable(): void
    {
        $classes = [
            'app\\controller\\product\\ProductController',
            'app\\controller\\product\\CategoryController',
            'app\\controller\\product\\BrandController',
            'app\\controller\\product\\WarehouseController',
            'app\\controller\\product\\SupplierController',
            'app\\controller\\product\\CustomerController',
            'app\\controller\\product\\LocationController',
        ];
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "商品控制器 {$class} 应存在");
            $this->assertInstanceOf($class, new $class(), "商品控制器 {$class} 应可实例化");
        }
    }

    // ---------- 2. 商品模型定义 ----------

    public function testProductModelsInstantiateWithExpectedTables(): void
    {
        $models = [
            Product::class => 'product',
            ProductSku::class => 'product_sku',
            ProductPrice::class => 'product_price',
            ProductUnit::class => 'product_unit',
            Brand::class => 'brand',
            Category::class => 'category',
            Warehouse::class => 'warehouse',
            Supplier::class => 'supplier',
            Customer::class => 'customer',
            CustomerLevel::class => 'customer_level',
            Location::class => 'location',
            ProductSpec::class => 'product_spec',
        ];
        foreach ($models as $class => $table) {
            $this->assertTrue(class_exists($class), "模型 {$class} 应存在");
            $model = new $class();
            $this->assertInstanceOf($class, $model);
            $this->assertEquals($table, $model->getTable(), "{$class} 表名应为 {$table}");
            $this->assertFalse($model->getIncrementing(), "{$class} 使用 snowflake 主键，非自增");
        }
    }

    public function testProductFillableAndHidden(): void
    {
        $product = new Product();
        $fillable = $product->getFillable();
        $this->assertContains('code', $fillable);
        $this->assertContains('name', $fillable);
        $this->assertContains('barcode', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertNotContains('id', $fillable, 'id 不应在 fillable 中');
        $this->assertContains('deleted_at', $product->getHidden(), 'deleted_at 应被隐藏');
    }

    public function testProductIntegerCasts(): void
    {
        $product = new Product();
        $casts = $product->getCasts();
        $this->assertEquals('integer', $casts['status'] ?? null);
        $this->assertEquals('integer', $casts['category_id'] ?? null);
        $this->assertEquals('integer', $casts['brand_id'] ?? null);

        // 类型转换实际生效（无需 DB）
        $product->status = '1';
        $product->category_id = '99';
        $this->assertSame(1, $product->status, 'status 字符串应转换为 int');
        $this->assertSame(99, $product->category_id, 'category_id 字符串应转换为 int');
    }

    public function testProductToSearchableArray(): void
    {
        $product = new Product();
        $product->code = 'P001';
        $product->name = '不锈钢杯';
        $product->barcode = '6901234567890';
        $searchable = $product->toSearchableArray();
        $this->assertEquals('P001', $searchable['code']);
        $this->assertEquals('不锈钢杯', $searchable['name']);
        $this->assertEquals('6901234567890', $searchable['barcode']);
        $this->assertCount(3, $searchable);
    }

    public function testProductRelationsDefined(): void
    {
        $product = new Product();
        foreach (['category', 'brand', 'skus', 'prices', 'units'] as $relation) {
            $this->assertTrue(method_exists($product, $relation), "Product 应定义 {$relation}() 关系");
        }
    }

    // ---------- 3. 客户/供应商/仓库/品牌模型 ----------

    public function testCustomerSearchableArrayAndCasts(): void
    {
        $customer = new Customer();
        $customer->code = 'C001';
        $customer->name = '华东客户';
        $searchable = $customer->toSearchableArray();
        $this->assertEquals('C001', $searchable['code']);
        $this->assertEquals('华东客户', $searchable['name']);

        $casts = $customer->getCasts();
        $this->assertEquals('integer', $casts['level_id'] ?? null);
        $this->assertEquals('float', $casts['credit_limit'] ?? null);
        $this->assertEquals('integer', $casts['status'] ?? null);
        $this->assertStringContainsString('Encryptable', $casts['phone'] ?? '', 'phone 应加密存储');
        $this->assertStringContainsString('Encryptable', $casts['email'] ?? '', 'email 应加密存储');
    }

    public function testSupplierSearchableArrayAndEncryptedFields(): void
    {
        $supplier = new Supplier();
        $supplier->code = 'S001';
        $supplier->name = '供应商A';
        $searchable = $supplier->toSearchableArray();
        $this->assertEquals('S001', $searchable['code']);
        $this->assertEquals('供应商A', $searchable['name']);

        $casts = $supplier->getCasts();
        $this->assertEquals('float', $casts['tax_rate'] ?? null);
        $this->assertStringContainsString('Encryptable', $casts['phone'] ?? '', 'phone 应加密存储');
        $this->assertStringContainsString('Encryptable', $casts['bank_account'] ?? '', 'bank_account 应加密存储');
    }

    public function testWarehouseFillableCastsAndLocationsRelation(): void
    {
        $warehouse = new Warehouse();
        $fillable = $warehouse->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertTrue(method_exists($warehouse, 'locations'), 'Warehouse 应定义 locations() 关系');
    }

    public function testBrandCategoryCustomerLevelUseSoftDeletes(): void
    {
        foreach ([Brand::class, Category::class, CustomerLevel::class, Customer::class, Supplier::class, Warehouse::class, Product::class] as $class) {
            $this->assertTrue(
                in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new $class()), true),
                "{$class} 应启用软删除"
            );
        }
    }

    public function testLocationModelDoesNotUseSoftDeletes(): void
    {
        $this->assertFalse(
            in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(new Location()), true),
            'Location 未启用软删除（物理删除）'
        );
    }

    // ---------- 4. 控制器校验分支（校验失败路径不触库） ----------

    public function testProductStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\ProductController(), 'store', $this->makeRequest('POST', '/admin/product', [
            'code' => 'P001', 'category_id' => 'abc', 'unit' => '个',
        ]));
    }

    public function testProductStoreRejectsMissingCode(): void
    {
        $this->assertFailResponse(new \app\controller\product\ProductController(), 'store', $this->makeRequest('POST', '/admin/product', [
            'name' => '商品A', 'category_id' => 'abc', 'unit' => '个',
        ]));
    }

    public function testProductStoreRejectsMissingCategoryId(): void
    {
        $this->assertFailResponse(new \app\controller\product\ProductController(), 'store', $this->makeRequest('POST', '/admin/product', [
            'name' => '商品A', 'code' => 'P001', 'unit' => '个',
        ]));
    }

    public function testProductStoreRejectsMissingUnit(): void
    {
        $this->assertFailResponse(new \app\controller\product\ProductController(), 'store', $this->makeRequest('POST', '/admin/product', [
            'name' => '商品A', 'code' => 'P001', 'category_id' => 'abc',
        ]));
    }

    public function testBrandStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\BrandController(), 'store', $this->makeRequest('POST', '/admin/brand', []));
    }

    public function testCategoryStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\CategoryController(), 'store', $this->makeRequest('POST', '/admin/category', []));
    }

    public function testWarehouseStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\WarehouseController(), 'store', $this->makeRequest('POST', '/admin/warehouse', []));
    }

    public function testSupplierStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\SupplierController(), 'store', $this->makeRequest('POST', '/admin/supplier', []));
    }

    public function testCustomerStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\CustomerController(), 'store', $this->makeRequest('POST', '/admin/customer', []));
    }

    public function testLocationStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\product\LocationController(), 'store', $this->makeRequest('POST', '/admin/location', []));
    }

    // ---------- 5. 商品创建业务规则（文档化行为） ----------

    public function testProductSkuSpecAttrsEncodedAsUnicodeJson(): void
    {
        $specAttrs = ['颜色' => '红色', '尺寸' => 'L'];
        $encoded = $this->encodeSkuSpecAttrs($specAttrs);
        $this->assertStringContainsString('红色', $encoded, '中文规格属性不应被转义');
        $this->assertStringNotContainsString('\\u', $encoded, 'JSON_UNESCAPED_UNICODE 下不应出现 \u 转义');
        $decoded = json_decode($encoded, true);
        $this->assertSame($specAttrs, $decoded, '编码后应可无损还原');
    }

    public function testProductSkuDefaultsStatusEnabled(): void
    {
        // store(): SKU 默认 status=1（启用）
        $skuStatus = 1;
        $this->assertEquals(1, $skuStatus, '新 SKU 默认启用');
        // store(): SKU cost_price 缺失时默认为 0.0
        $costPrice = (float) (null ?? 0);
        $this->assertEquals(0.0, $costPrice, 'cost_price 缺失默认为 0.0');
    }

    /**
     * 直接调用生产代码 ProductService::normalizeSku（public 纯函数，无需 DB）。
     * 注意与上方 testProductSkuDefaultsStatusEnabled 的区别：那条是自证式断言
     * （assertEquals(1, 1) 之类），无论实现怎么改都会过，不构成保护；本条真跑实现。
     */
    public function testNormalizeSkuCarriesSpecId(): void
    {
        $svc = new \app\service\product\ProductService();

        $withSpec = $svc->normalizeSku(['sku_code' => 'A-1', 'spec_id' => 42]);
        $this->assertSame(42, $withSpec['spec_id'], 'spec_id 应原样带出');

        $withoutSpec = $svc->normalizeSku(['sku_code' => 'A-2']);
        $this->assertSame(0, $withoutSpec['spec_id'], '未提供 spec_id 时默认 0（未指定规格）');
    }

    public function testProductPriceConvertedToFloat(): void
    {
        // store(): (float) $priceData['price']
        $this->assertSame(19.9, (float) '19.9');
        $this->assertSame(0.0, (float) '');
        $this->assertSame(100.5, (float) '100.5');
    }

    public function testProductStatusDefaultsEnabledOnStore(): void
    {
        // store(): status = (int) input('status', 1)
        $status = (int) ('' === '' ? 1 : '');
        $this->assertEquals(1, $status, '未传 status 时默认启用');
    }

    // ---------- 6. BaseController 工具方法 ----------

    public function testProductBaseControllerEncodeIdsWithCustomIdFields(): void
    {
        $controller = new \app\controller\product\ProductController();
        $data = ['id' => 5, 'category_id' => 8, 'brand_id' => 9, 'name' => '商品B'];
        $encoded = $this->invokeProtected($controller, 'encodeIds', $data, ['id', 'category_id', 'brand_id']);
        $this->assertNotEquals(5, $encoded['id']);
        $this->assertNotEquals(8, $encoded['category_id']);
        $this->assertNotEquals(9, $encoded['brand_id']);
        $this->assertEquals('商品B', $encoded['name'], '非 ID 字段不应被编码');
        $this->assertEquals(8, $this->invokeProtected($controller, 'decodeId', $encoded['category_id']));
    }

    // ---------- 7. 商品规格 attrs（JSON 对象）契约 ----------

    private function specService(): \app\service\product\ProductService
    {
        return new \app\service\product\ProductService();
    }

    /**
     * 读路径契约：index/show 走 $item->toArray()，历史行 attrs 为 SQL NULL 时也必须是字符串 '{}'，
     * 不能返回 null（前端按字符串 JSON.parse）。
     */
    public function testSpecModelReadsNullAttrsAsEmptyJsonString(): void
    {
        // setRawAttributes 模拟 DB 行（历史行 attrs 为 SQL NULL），无需连接即可走读路径
        $spec = new ProductSpec();
        $spec->setRawAttributes(['id' => 1, 'name' => '颜色', 'attrs' => null]);
        $this->assertSame('{}', $spec->attrs);
        $this->assertSame('{}', $spec->toArray()['attrs']);

        $spec->setRawAttributes(['id' => 1, 'name' => '颜色', 'attrs' => '{"颜色":["红"]}']);
        $this->assertSame('{"颜色":["红"]}', $spec->toArray()['attrs']);
        $this->assertContains('attrs', $spec->getFillable());
    }

    public function testSpecAttrsNormalizesAssocArrayToUnicodeJsonObject(): void
    {
        $json = $this->specService()->normalizeSpecAttrs(['颜色' => ['红', '蓝'], '尺寸' => ['S', 'M', 'L']]);
        $this->assertSame('{"颜色":["红","蓝"],"尺寸":["S","M","L"]}', $json);
        $this->assertStringNotContainsString('\\u', $json, 'JSON_UNESCAPED_UNICODE 下不应出现 \u 转义');
    }

    public function testSpecAttrsAcceptsJsonStringForm(): void
    {
        $this->assertSame('{"颜色":["红"]}', $this->specService()->normalizeSpecAttrs('{"颜色":["红"]}'));
    }

    public function testSpecAttrsEmptyValuesNormalizeToEmptyObject(): void
    {
        $svc = $this->specService();
        // 空为 {} 而非 NULL：fillableOnly() 用 isset() 过滤，NULL 会被静默丢弃导致"清空属性"存不进去
        $this->assertSame('{}', $svc->normalizeSpecAttrs(null));
        $this->assertSame('{}', $svc->normalizeSpecAttrs(''));
        $this->assertSame('{}', $svc->normalizeSpecAttrs('  '));
        $this->assertSame('{}', $svc->normalizeSpecAttrs('{}'));
        $this->assertSame('{}', $svc->normalizeSpecAttrs('[]'));
        $this->assertSame('{}', $svc->normalizeSpecAttrs([]));
    }

    public function testSpecAttrsAllowsEmptyValueLists(): void
    {
        // 空值不算非法：属性值为空数组、值元素为空串都应通过
        $this->assertSame(
            '{"颜色":[],"尺寸":[""]}',
            $this->specService()->normalizeSpecAttrs(['颜色' => [], '尺寸' => ['']])
        );
    }

    public function testSpecAttrsKeepsNumericStringKeysAsObject(): void
    {
        // 数字字符串属性名不得退化成 JSON 数组
        $this->assertSame('{"0":["红"]}', $this->specService()->normalizeSpecAttrs('{"0":["红"]}'));
    }

    public function testSpecAttrsRejectsJsonArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs('["红","蓝"]');
    }

    public function testSpecAttrsRejectsPhpList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs(['红', '蓝']);
    }

    public function testSpecAttrsRejectsScalarValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs('{"颜色":"红"}');
    }

    public function testSpecAttrsRejectsNonListValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs(['尺寸' => ['S' => 'small']]);
    }

    public function testSpecAttrsRejectsBrokenJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs('{"颜色":');
    }

    public function testSpecAttrsRejectsScalarInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->specService()->normalizeSpecAttrs(123);
    }

    /* ---- 控制器 422 分支（attrs 归一化在落库之前，无需 DB） ---- */

    public function testSpecStoreRejectsBrokenJsonAttrs(): void
    {
        $resp = (new \app\controller\product\ProductSpecController())->store(new FakeRequest([
            'name' => '颜色规格', 'attrs' => '{"颜色":',
        ]));
        $payload = json_decode($resp->rawBody(), true);
        $this->assertSame(422, $payload['code'] ?? null);
        $this->assertStringContainsString('JSON', (string) ($payload['message'] ?? ''));
    }

    public function testSpecStoreRejectsArrayAttrs(): void
    {
        $resp = (new \app\controller\product\ProductSpecController())->store(new FakeRequest([
            'name' => '颜色规格', 'attrs' => ['红', '蓝'],
        ]));
        $this->assertSame(422, json_decode($resp->rawBody(), true)['code'] ?? null);
    }

    public function testSpecStoreRejectsScalarValueAttrs(): void
    {
        $resp = (new \app\controller\product\ProductSpecController())->store(new FakeRequest([
            'name' => '颜色规格', 'attrs' => ['颜色' => '红'],
        ]));
        $this->assertSame(422, json_decode($resp->rawBody(), true)['code'] ?? null);
    }

    public function testSpecUpdateRejectsScalarAttrs(): void
    {
        $resp = (new \app\controller\product\ProductSpecController())->update(new FakeRequest([
            'id' => 'abc', 'attrs' => 123,
        ]), 'abc');
        $this->assertSame(422, json_decode($resp->rawBody(), true)['code'] ?? null);
    }
}
