<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\inventory\InventoryService;
use PHPUnit\Framework\TestCase;

/**
 * 库存服务扩展：stockOut / reserveQuantity 的前置参数校验（DB 事务之前即抛异常）
 */
class InventoryServiceExtendedTest extends TestCase
{
    public function testStockOutRejectsNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('数量必须大于0');

        (new InventoryService())->stockOut(1, 0, 1, 0, 'ORDER', -3, 'test', 1);
    }

    public function testStockOutRejectsZeroQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('数量必须大于0');

        (new InventoryService())->stockOut(1, 0, 1, 0, 'ORDER', 0, 'test', 1);
    }

    public function testReserveQuantityRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('预占数量必须大于0');

        (new InventoryService())->reserveQuantity(1, 0, 1, 0, '', -1, 'ORDER', 1);
    }

    public function testReserveQuantityRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('预占数量必须大于0');

        (new InventoryService())->reserveQuantity(1, 0, 1, 0, '', 0, 'ORDER', 1);
    }

    public function testGetAvailableQuantityAcceptsValidArgs(): void
    {
        // 纯查询路径；无 DB 时抛 QueryException 而非参数错误，证明校验前置成立
        try {
            (new InventoryService())->getAvailableQuantity(1, 0);
            $this->assertTrue(true);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringNotContainsString('数量', $e->getMessage());
        }
    }
}
