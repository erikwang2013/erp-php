<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 采购收货超收/归属校验修复测试：
 *  - 超收（累计实收 > 采购数量）严格拒绝；
 *  - order_item_id 必须属于该采购单，否则拒绝；
 *  - 订单状态改为逐明细行比较（每行实收 ≥ 行采购量才算该行完成），
 *    不再用跨明细总量比较。
 *
 * 采用仓库既有约定（参照 PurchaseModuleTest）：落库路径跳过，
 * 以源码契约断言 + 状态决策矩阵复刻的方式锁定修复行为。
 */
class ReceiveOverReceiptFixTest extends TestCase
{
    private function receiveSource(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../app/controller/purchase/ReceiveController.php');
    }

    private function deliverySource(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../app/controller/sales/DeliveryController.php');
    }

    public function testStoreRejectsOverReceipt(): void
    {
        $src = $this->receiveSource();

        // 超收校验：历史累计实收 + 本次 ≤ 采购数量，否则严格拒绝
        $this->assertStringContainsString('SUM(erik_purchase_receive_item.quantity)', $src);
        $this->assertStringContainsString('($receivedSoFar + $quantity) > $orderedQty', $src, '应校验累计实收不超过采购数量');
        $this->assertStringContainsString('超收拒绝', $src, '超收应以业务异常拒绝');
    }

    public function testStoreRequiresOrderItemIdBelongsToOrder(): void
    {
        $src = $this->receiveSource();

        // 校验器强制要求 order_item_id
        $this->assertStringContainsString("'items.*.order_item_id' => 'required'", $src);
        // 归属校验：先查明细归属（预取订单明细 + isset 判断）再入库
        $this->assertStringContainsString('$orderItems = PurchaseOrderItem::query()->where(\'order_id\', $orderId)->get()->keyBy(\'id\')', $src);
        $this->assertStringContainsString('order_item_id 缺失或不属于该采购订单', $src);
        $this->assertStringContainsString('!isset($orderItems[$orderItemId])', $src);
    }

    public function testUpdateOrderStatusIsPerLine(): void
    {
        $src = $this->receiveSource();

        // 状态判定按明细行聚合比较，而非跨明细总量
        $this->assertStringContainsString('groupBy(\'erik_purchase_receive_item.order_item_id\')', $src);
        $this->assertStringContainsString('SUM(erik_purchase_receive_item.quantity) as total_received', $src);
        $this->assertStringNotContainsString('$totalReceivedQty >= $totalOrderedQty', $src, '应移除跨明细总量比较');
        // 全行完成 → 已收货；有行完成 → 部分收货
        $this->assertStringContainsString('每行实收均达采购量 → 已收货', $src);
        $this->assertStringContainsString('部分收货', $src);
    }

    /** 复刻逐行状态决策矩阵（纯函数版） */
    private function decideOrderStatus(array $lines): int
    {
        // $lines: [['ordered' => x, 'received' => y], ...]
        if ($lines === []) {
            return 3; // 无明细 => 已收货
        }
        $allComplete = true;
        $anyReceived = false;
        foreach ($lines as $line) {
            if ((float) $line['received'] <= 0) {
                $allComplete = false;
                continue;
            }
            $anyReceived = true;
            if ((float) $line['received'] < (float) $line['ordered']) {
                $allComplete = false;
            }
        }
        if ($allComplete) {
            return 3; // 每行实收均达采购量 => 已收货
        }
        if ($anyReceived) {
            return 2; // 部分收货
        }

        return 1; // 未收货
    }

    public function testPerLineStatusDecisionMatrix(): void
    {
        // 跨明细补偿不再成立：行A收齐、行B未收 → 部分收货（旧逻辑总量相等会误判已收货）
        $this->assertSame(2, $this->decideOrderStatus([
            ['ordered' => 10, 'received' => 10],
            ['ordered' => 5, 'received' => 0],
        ]), '一行未收应判部分收货，即使总量被另一行补平');
        // 全行收齐 → 已收货
        $this->assertSame(3, $this->decideOrderStatus([
            ['ordered' => 10, 'received' => 10],
            ['ordered' => 5, 'received' => 5],
        ]));
        // 全部未收 → 保持原状态
        $this->assertSame(1, $this->decideOrderStatus([
            ['ordered' => 10, 'received' => 0],
        ]));
        // 无明细 → 已收货
        $this->assertSame(3, $this->decideOrderStatus([]));
    }

    public function testSalesDeliveryControllerHasSameGuards(): void
    {
        $src = $this->deliverySource();

        // 销售发货同模式修复：超发拒绝 + order_item 归属校验 + 逐行状态
        $this->assertStringContainsString("'items.*.order_item_id' => 'required'", $src);
        $this->assertStringContainsString('超发拒绝', $src);
        $this->assertStringContainsString('order_item_id 缺失或不属于该销售订单', $src);
        $this->assertStringContainsString('SUM(erik_sales_delivery_item.quantity) as total_delivered', $src);
    }

    public function testReceiveStoreEndToEndRequiresDatabase(): void
    {
        // 收货落库 + 超收校验 + 状态更新的完整流程依赖 MySQL 事务。
        $this->markTestSkipped('依赖 MySQL: 收货单落库 + 超收/归属校验 + updateOrderStatus');
    }
}
