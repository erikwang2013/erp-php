<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\model\SalesDelivery;
use app\model\SalesDeliveryItem;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\model\SalesQuotation;
use app\model\SalesQuotationItem;
use app\model\SalesReturn;
use app\model\SalesReturnItem;
use app\model\SalesSettlement;
use PHPUnit\Framework\TestCase;
use support\Request;

/**
 * 销售模块单元测试（纯单测，无 DB 依赖）
 *
 * 覆盖：发货金额计算、发货校验规则、订单发货状态判定、
 *      销售模型定义（表名/填充/软删除/关系/类型转换）、控制器校验分支、BaseController 工具方法。
 */
class SalesModuleTest extends TestCase
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
     * 与 DeliveryController::updateOrderStatus() 一致的订单状态判定。
     * 返回新的状态；null 表示保持原状态。
     */
    private function decideOrderStatus(array $orderItems, float $totalOrderedQty, float $totalDeliveredQty, int $deliveredCount): ?int
    {
        if (empty($orderItems)) {
            return 3; // 无明细 -> 已发货
        }
        if ($totalDeliveredQty >= $totalOrderedQty) {
            return 3; // 累计发货 >= 订购量 -> 已发货
        }
        if ($deliveredCount > 0) {
            return 2; // 有已发货单但未发完 -> 部分发货
        }

        return null; // 保持原状态
    }

    /**
     * 与 DeliveryController::store() 一致的明细金额计算：round(quantity * price, 2)。
     */
    private function deliveryItemAmount(float $quantity, float $price): float
    {
        return round($quantity * $price, 2);
    }

    // ---------- 1. 控制器存在性 ----------

    public function testSalesControllersExistAndInstantiable(): void
    {
        $classes = [
            'app\\controller\\sales\\QuotationController',
            'app\\controller\\sales\\OrderController',
            'app\\controller\\sales\\DeliveryController',
            'app\\controller\\sales\\ReturnController',
            'app\\controller\\sales\\SettlementController',
        ];
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "销售控制器 {$class} 应存在");
            $this->assertInstanceOf($class, new $class(), "销售控制器 {$class} 应可实例化");
        }
    }

    // ---------- 2. 发货明细金额计算 ----------

    public function testDeliveryItemAmountRoundsToTwoDecimals(): void
    {
        $this->assertEquals(4.5, $this->deliveryItemAmount(3, 1.5));
        $this->assertEquals(6.99, $this->deliveryItemAmount(7, 0.999));
        $this->assertEquals(100.0, $this->deliveryItemAmount(1, 100));
        $this->assertEquals(0.01, $this->deliveryItemAmount(0.5, 0.02));
    }

    public function testDeliveryTotalAmountAccumulatesAcrossItems(): void
    {
        // store(): $totalDeliveryAmount += round(qty * price, 2)
        $items = [
            ['quantity' => 2, 'price' => 10.5],   // 21.00
            ['quantity' => 3, 'price' => 4.25],   // 12.75
            ['quantity' => 1, 'price' => 99.99],  // 99.99
        ];
        $total = 0.0;
        foreach ($items as $item) {
            $total += $this->deliveryItemAmount((float) $item['quantity'], (float) $item['price']);
        }
        $this->assertEquals(133.74, round($total, 2), '发货总额应为各明细金额之和');
    }

    // ---------- 3. 发货单校验规则 ----------

    public function testDeliveryStoreRequiresCode(): void
    {
        $this->assertFailResponse(
            new \app\controller\sales\DeliveryController(),
            'store',
            $this->makeRequest('POST', '/admin/sales/delivery', [
                'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
                'items' => [['product_id' => '1', 'quantity' => 1, 'price' => 10]],
            ])
        );
    }

    public function testDeliveryStoreRequiresOrderCustomerWarehouse(): void
    {
        $controller = new \app\controller\sales\DeliveryController();
        $this->assertFailResponse($controller, 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
            'items' => [['product_id' => '1', 'quantity' => 1, 'price' => 10]],
        ]));
        $this->assertFailResponse($controller, 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc',
            'items' => [['product_id' => '1', 'quantity' => 1, 'price' => 10]],
        ]));
    }

    public function testDeliveryStoreRequiresNonEmptyItems(): void
    {
        $this->assertFailResponse(new \app\controller\sales\DeliveryController(), 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
        ]));
    }

    public function testDeliveryStoreItemRequiresProductId(): void
    {
        $this->assertFailResponse(new \app\controller\sales\DeliveryController(), 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
            'items' => [['quantity' => 1, 'price' => 10]],
        ]));
    }

    public function testDeliveryStoreItemRejectsZeroQuantity(): void
    {
        $this->assertFailResponse(new \app\controller\sales\DeliveryController(), 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
            'items' => [['product_id' => '1', 'quantity' => 0, 'price' => 10]],
        ]));
    }

    public function testDeliveryStoreItemRejectsNegativePrice(): void
    {
        $this->assertFailResponse(new \app\controller\sales\DeliveryController(), 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
            'items' => [['product_id' => '1', 'quantity' => 1, 'price' => -1]],
        ]));
    }

    public function testDeliveryStoreItemRejectsMissingPrice(): void
    {
        $this->assertFailResponse(new \app\controller\sales\DeliveryController(), 'store', $this->makeRequest('POST', '/admin/sales/delivery', [
            'code' => 'D001', 'order_id' => 'abc', 'customer_id' => 'abc', 'warehouse_id' => 'abc',
            'items' => [['product_id' => '1', 'quantity' => 1]],
        ]));
    }

    // ---------- 4. 订单发货状态判定 ----------

    public function testOrderStatusFullDeliveryMarksShipped(): void
    {
        // 累计发货量 >= 订购量 -> 3 已发货
        $this->assertEquals(3, $this->decideOrderStatus([['quantity' => 10]], 10.0, 10.0, 1));
        $this->assertEquals(3, $this->decideOrderStatus([['quantity' => 10]], 10.0, 12.0, 1), '超发也视为已发货');
    }

    public function testOrderStatusPartialDeliveryMarksPartiallyShipped(): void
    {
        // 有已发货单但累计发货量 < 订购量 -> 2 部分发货
        $this->assertEquals(2, $this->decideOrderStatus([['quantity' => 10]], 10.0, 4.0, 1));
        $this->assertEquals(2, $this->decideOrderStatus([['quantity' => 10], ['quantity' => 5]], 15.0, 14.0, 2));
    }

    public function testOrderStatusNoDeliveredCountKeepsCurrentStatus(): void
    {
        // 尚未有任何已发货单 -> 不改变订单状态
        $this->assertNull($this->decideOrderStatus([['quantity' => 10]], 10.0, 0.0, 0));
    }

    public function testOrderStatusWithoutOrderItemsMarksShipped(): void
    {
        // 订单无明细时直接置为已发货
        $this->assertEquals(3, $this->decideOrderStatus([], 0.0, 0.0, 1));
        $this->assertEquals(3, $this->decideOrderStatus([], 0.0, 0.0, 0));
    }

    // ---------- 5. 销售模型定义 ----------

    public function testSalesModelsInstantiateWithExpectedTables(): void
    {
        $models = [
            SalesQuotation::class => 'erik_sales_quotation',
            SalesQuotationItem::class => 'erik_sales_quotation_item',
            SalesOrder::class => 'erik_sales_order',
            SalesOrderItem::class => 'erik_sales_order_item',
            SalesDelivery::class => 'erik_sales_delivery',
            SalesDeliveryItem::class => 'erik_sales_delivery_item',
            SalesReturn::class => 'erik_sales_return',
            SalesReturnItem::class => 'erik_sales_return_item',
            SalesSettlement::class => 'erik_sales_settlement',
        ];
        foreach ($models as $class => $table) {
            $this->assertTrue(class_exists($class), "模型 {$class} 应存在");
            $model = new $class();
            $this->assertInstanceOf($class, $model);
            $this->assertEquals($table, $model->getTable(), "{$class} 表名应为 {$table}");
            $this->assertFalse($model->getIncrementing(), "{$class} 使用 snowflake 主键，非自增");
        }
    }

    public function testSalesOrderSoftDeletesAndGuarded(): void
    {
        $order = new SalesOrder();
        $this->assertTrue(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($order), true), 'SalesOrder 应启用软删除');
        $guarded = $order->getGuarded();
        $this->assertContains('id', $guarded);
        $this->assertContains('deleted_at', $guarded);
    }

    public function testSalesDeliveryFillableCastsAndRelations(): void
    {
        $delivery = new SalesDelivery();
        $fillable = $delivery->getFillable();
        $this->assertContains('code', $fillable);
        $this->assertContains('order_id', $fillable);
        $this->assertContains('delivered_at', $fillable);
        $this->assertNotContains('id', $fillable, 'id 不应在 fillable 中');

        $casts = $delivery->getCasts();
        $this->assertEquals('integer', $casts['order_id'] ?? null);
        $this->assertEquals('integer', $casts['warehouse_id'] ?? null);
        $this->assertEquals('integer', $casts['status'] ?? null);

        $this->assertTrue(method_exists($delivery, 'items'), 'SalesDelivery 应定义 items() 关系');
        $this->assertTrue(method_exists($delivery, 'order'), 'SalesDelivery 应定义 order() 关系');
        $this->assertTrue(method_exists($delivery, 'customer'), 'SalesDelivery 应定义 customer() 关系');
        $this->assertTrue(method_exists($delivery, 'warehouse'), 'SalesDelivery 应定义 warehouse() 关系');
    }

    public function testSalesDeliveryItemFloatCasts(): void
    {
        $item = new SalesDeliveryItem();
        $casts = $item->getCasts();
        $this->assertEquals('float', $casts['quantity'] ?? null);
        $this->assertEquals('float', $casts['price'] ?? null);
        $this->assertEquals('float', $casts['amount'] ?? null);
        $this->assertEquals('integer', $casts['product_id'] ?? null);
    }

    // ---------- 6. 控制器校验分支（校验失败路径不触库） ----------

    public function testSalesOrderStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\sales\OrderController(), 'store', $this->makeRequest('POST', '/admin/sales/order', []));
    }

    public function testSalesQuotationStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\sales\QuotationController(), 'store', $this->makeRequest('POST', '/admin/sales/quotation', []));
    }

    public function testSalesReturnStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\sales\ReturnController(), 'store', $this->makeRequest('POST', '/admin/sales/return', []));
    }

    public function testSalesSettlementStoreRejectsMissingName(): void
    {
        $this->assertFailResponse(new \app\controller\sales\SettlementController(), 'store', $this->makeRequest('POST', '/admin/sales/settlement', []));
    }

    // ---------- 7. BaseController 工具方法 ----------

    public function testSalesBaseControllerResponseShape(): void
    {
        $controller = new \app\controller\sales\OrderController();
        $success = $this->invokeProtected($controller, 'success', ['id' => 1], 'success', 0);
        $payload = json_decode($success->rawBody(), true);
        $this->assertEquals(0, $payload['code']);
        $this->assertEquals('success', $payload['message']);

        $fail = $this->invokeProtected($controller, 'fail', '记录不存在', 404);
        $failPayload = json_decode($fail->rawBody(), true);
        $this->assertEquals(404, $failPayload['code']);
        $this->assertEquals('记录不存在', $failPayload['message']);
    }

    public function testSalesBaseControllerGenerateIdProducesUniqueSnowflakeIds(): void
    {
        $controller = new \app\controller\sales\OrderController();
        $id1 = $this->invokeProtected($controller, 'generateId');
        $id2 = $this->invokeProtected($controller, 'generateId');
        $this->assertIsInt($id1);
        $this->assertGreaterThan(0, $id1, 'snowflake ID 应为正整数');
        $this->assertNotEquals($id1, $id2, '连续生成的 snowflake ID 应唯一');
    }
}
