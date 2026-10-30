<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\OrderStatusMachine;
use app\common\StockLockOrder;
use PHPUnit\Framework\TestCase;

/**
 * 订单状态机：API 只能人工审核/取消，2/3 由发货收货动作驱动。
 * 纯逻辑直测 + 控制器源码契约（父单状态/删除守卫需要 DB，无库时以源码断言固化）。
 */
class OrderStatusMachineTest extends TestCase
{
    public function testAllowedUpdateTransitions(): void
    {
        // 0→1 审核、0→4 取消未审核、1→4 取消已审核
        foreach ([[0, 1], [0, 4], [1, 4]] as [$from, $to]) {
            $this->assertTrue(
                OrderStatusMachine::canUpdate($from, $to),
                "{$from} → {$to} 应允许"
            );
        }
    }

    public function testSameValueIsNoop(): void
    {
        foreach ([0, 1, 2, 3, 4] as $status) {
            $this->assertTrue(
                OrderStatusMachine::canUpdate($status, $status),
                "同值 {$status} no-op 应允许"
            );
        }
    }

    public function testBusinessDrivenAndTerminalTransitionsRejected(): void
    {
        $rejected = [
            [1, 2], [1, 3], [2, 3], [2, 4], [3, 0], [3, 1], [3, 2], [3, 4],
            [4, 0], [4, 1], [4, 2], [4, 3], [0, 2], [0, 3],
        ];
        foreach ($rejected as [$from, $to]) {
            $this->assertFalse(
                OrderStatusMachine::canUpdate($from, $to),
                "{$from} → {$to} 不应由更新接口放行"
            );
        }
    }

    public function testOutOfDomainStatusRejected(): void
    {
        $this->assertFalse(OrderStatusMachine::canUpdate(0, 5));
        $this->assertFalse(OrderStatusMachine::canUpdate(0, -1));
        $this->assertFalse(OrderStatusMachine::canUpdate(5, 1));
    }

    public function testDeliveryOrReceiveOnlyFromApprovedOrPartial(): void
    {
        $this->assertTrue(OrderStatusMachine::canDeliverOrReceive(1), '已审核可发货/收货');
        $this->assertTrue(OrderStatusMachine::canDeliverOrReceive(2), '部分执行可继续发货/收货');
        foreach ([0, 3, 4] as $status) {
            $this->assertFalse(
                OrderStatusMachine::canDeliverOrReceive($status),
                "status={$status} 不应允许创建发货/收货单"
            );
        }
    }

    public function testOrderStoreRejectsBusinessDrivenStatusBeforePersisting(): void
    {
        // 创建即「部分/已发货」必须被校验拦下（校验失败路径不触库）
        $probes = [
            'sales/OrderController' => (new \app\controller\sales\OrderController())->store(
                new FakeRequest(['customer_id' => '1', 'status' => 2])
            ),
            'purchase/OrderController' => (new \app\controller\purchase\OrderController())->store(
                new FakeRequest(['supplier_id' => '1', 'status' => 3])
            ),
        ];
        foreach ($probes as $site => $resp) {
            $this->assertSame(422, (int) (json_decode((string) $resp->rawBody(), true)['code'] ?? 0), "{$site} 应拒绝创建即完成");
        }
    }

    public function testOrderControllersEnforceCreateAndUpdateFlow(): void
    {
        foreach (['sales', 'purchase'] as $module) {
            $src = (string) file_get_contents(__DIR__ . "/../app/controller/{$module}/OrderController.php");
            $this->assertStringContainsString(
                "'status' => 'integer|in:0,1'",
                $src,
                "{$module} 订单创建只允许 0/1，防止创建即完成"
            );
            $this->assertStringContainsString(
                'OrderStatusMachine::canUpdate',
                $src,
                "{$module} 订单更新必须走状态机"
            );
            $this->assertStringContainsString(
                'withTrashed()->where(\'order_id\', $id)->exists()',
                $src,
                "{$module} 删除守卫必须包含软删除的发货/收货记录"
            );
        }
    }

    public function testDeliveryAndReceiveRequireApprovedParentStatus(): void
    {
        foreach (['sales/DeliveryController', 'purchase/ReceiveController'] as $file) {
            $src = (string) file_get_contents(__DIR__ . "/../app/controller/{$file}.php");
            $this->assertStringContainsString(
                'OrderStatusMachine::canDeliverOrReceive((int) $order->status)',
                $src,
                "{$file} 创建前必须校验父单状态"
            );
        }
    }

    public function testOrderUpdateLocksRowBeforeTransitionCheck(): void
    {
        $cases = [
            'sales' => ['app/controller/sales/OrderController.php', 'SalesOrder::query()->whereKey($id)->lockForUpdate()->first()'],
            'purchase' => ['app/controller/purchase/OrderController.php', 'PurchaseOrder::query()->whereKey($id)->lockForUpdate()->first()'],
        ];
        foreach ($cases as $module => [$file, $lock]) {
            $update = $this->methodSource($file, 'update');
            $tx = strpos($update, 'DB::transaction(');
            $lockPos = strpos($update, $lock);
            $checkPos = strpos($update, 'OrderStatusMachine::canUpdate');
            $this->assertNotFalse($tx, "{$module} 更新必须在事务内");
            $this->assertNotFalse($lockPos, "{$module} 更新必须锁订单行");
            $this->assertNotFalse($checkPos, "{$module} 更新必须走状态机");
            $this->assertLessThan($lockPos, $tx, "{$module} 先开事务再锁行");
            $this->assertLessThan($checkPos, $lockPos, "{$module} 锁行后复核状态，防并发 PUT 用旧状态绕过");
            $this->assertStringContainsString(
                'withTrashed()->where(\'order_id\', $id)->exists()',
                (string) substr($update, (int) $lockPos),
                "{$module} 取消前的引用检查必须在锁后"
            );
        }
    }

    public function testDeliveryReceiveLockParentOrderBeforeStatusRecheck(): void
    {
        $cases = [
            'app/controller/sales/DeliveryController.php' => ['SalesOrder::query()->whereKey($orderId)->lockForUpdate()->first()', '发货'],
            'app/controller/purchase/ReceiveController.php' => ['PurchaseOrder::query()->whereKey($orderId)->lockForUpdate()->first()', '收货'],
        ];
        foreach ($cases as $file => [$lock, $label]) {
            $store = $this->methodSource($file, 'store');
            $tx = strpos($store, 'DB::beginTransaction();');
            $lockPos = strpos($store, $lock);
            $this->assertNotFalse($tx, "{$file} 创建必须在事务内");
            $this->assertNotFalse($lockPos, "{$file} 创建前必须锁父订单行");
            $this->assertLessThan($lockPos, $tx, "{$file} 先开事务再锁行");
            $checkPos = strpos($store, 'OrderStatusMachine::canDeliverOrReceive', (int) $lockPos + 1);
            $this->assertNotFalse($checkPos, "{$file} 锁后必须复核父单状态（{$label}）");
            $this->assertLessThan($checkPos, $lockPos, "{$file} 复核在锁后，取消先提交时能看到 4");
        }
    }

    public function testOrderDestroyRechecksReferencesUnderLock(): void
    {
        $cases = [
            'sales' => ['app/controller/sales/OrderController.php', 'SalesDelivery'],
            'purchase' => ['app/controller/purchase/OrderController.php', 'PurchaseReceive'],
        ];
        foreach ($cases as $module => [$file, $relation]) {
            $destroy = $this->methodSource($file, 'destroy');
            $tx = strpos($destroy, 'DB::connection()->transaction');
            $lockPos = strpos($destroy, 'lockForUpdate()->first()');
            $refPos = strpos($destroy, "{$relation}::withTrashed()->where('order_id', \$id)->exists()");
            $deletePos = strpos($destroy, '$item->delete();');
            foreach (['事务' => $tx, '订单行锁' => $lockPos, '引用检查' => $refPos, '软删除' => $deletePos] as $what => $pos) {
                $this->assertNotFalse($pos, "{$module} destroy 缺少{$what}");
            }
            $this->assertLessThan((int) $lockPos, (int) $tx, "{$module} 先开事务再锁行");
            $this->assertLessThan((int) $refPos, (int) $lockPos, "{$module} 引用检查必须在锁后");
            $this->assertLessThan((int) $deletePos, (int) $refPos, "{$module} 软删除必须在引用检查之后");
        }
    }

    public function testStockLockOrderSortsByInventoryDimension(): void
    {
        $line = static fn (int $product, int $sku, int $location, string $batch): array => [
            'product_id' => $product,
            'sku_id' => $sku,
            'location_id' => $location,
            'batch_code' => $batch,
        ];
        $sorted = StockLockOrder::sort([
            $line(2, 0, 0, ''),
            $line(1, 5, 3, 'B2'),
            $line(1, 5, 3, 'B1'),
            $line(1, 5, 2, ''),
        ]);

        $this->assertSame(
            [[1, 5, 2, ''], [1, 5, 3, 'B1'], [1, 5, 3, 'B2'], [2, 0, 0, '']],
            array_map(static fn (array $l): array => [
                $l['product_id'], $l['sku_id'], $l['location_id'], $l['batch_code'],
            ], $sorted)
        );
        $this->assertSame([], StockLockOrder::sort([]));
    }

    public function testStockLockOrderIgnoresRequestOrder(): void
    {
        $lines = [
            ['product_id' => 1, 'sku_id' => 1, 'location_id' => 1, 'batch_code' => 'A'],
            ['product_id' => 2, 'sku_id' => 2, 'location_id' => 2, 'batch_code' => 'B'],
        ];

        // 明细顺序相反的两张单必须得到同一加锁顺序，否则并发会互等（1213）
        $this->assertSame(StockLockOrder::sort($lines), StockLockOrder::sort(array_reverse($lines)));
    }

    public function testStockLockOrderIncludesWarehouseAndToleratesMissingKeys(): void
    {
        $sorted = StockLockOrder::sort([
            ['product_id' => 1, 'sku_id' => 1, 'warehouse_id' => 2, 'location_id' => 0, 'batch_code' => ''],
            ['product_id' => 1, 'sku_id' => 1, 'warehouse_id' => 1, 'location_id' => 9, 'batch_code' => 'Z'],
            ['product_id' => 1, 'sku_id' => 1], // OMS 预占允许缺省 warehouse/location/batch
        ]);

        $this->assertSame(
            [0, 1, 2],
            array_map(static fn (array $l): int => (int) ($l['warehouse_id'] ?? 0), $sorted),
            'warehouse_id 必须先于 location/batch 参与排序，缺省键按 0 兜底'
        );
    }

    public function testDeliveryReceiveSortBeforeStockAction(): void
    {
        $cases = [
            'app/controller/sales/DeliveryController.php' => ['stockOut(', '发货'],
            'app/controller/purchase/ReceiveController.php' => ['stockIn(', '收货'],
        ];
        foreach ($cases as $file => [$stockCall, $label]) {
            $store = $this->methodSource($file, 'store');
            $tx = strpos($store, 'DB::beginTransaction();');
            $collect = strpos($store, '$lines[] = [');
            $sort = strpos($store, 'StockLockOrder::sort($lines)');
            $stock = strpos($store, $stockCall, (int) $sort + 1);
            foreach (['事务' => $tx, '明细收集' => $collect, '库存维度排序' => $sort, '库存动作' => $stock] as $what => $pos) {
                $this->assertNotFalse($pos, "{$label} store 缺少{$what}");
            }
            $this->assertLessThan((int) $collect, (int) $tx, "{$label} 明细收集在事务内");
            $this->assertLessThan((int) $sort, (int) $collect, "{$label} 先解码/校验完全部明细再排序");
            $this->assertLessThan((int) $stock, (int) $sort, "{$label} 库存动作必须按排好序的行执行");
        }
    }

    /** 截取单个方法源码（到下一个 public 方法或文件尾），供锁序断言 */
    private function methodSource(string $file, string $method): string
    {
        $src = (string) file_get_contents(__DIR__ . '/../' . $file);
        $start = strpos($src, "public function {$method}(");
        $this->assertNotFalse($start, "{$file} 缺少 {$method}()");
        $end = strpos($src, "\n    public function ", (int) $start + 1);

        return $end === false ? substr($src, (int) $start) : substr($src, (int) $start, $end - (int) $start);
    }
}
