<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use support\Db;
use Throwable;

/**
 * PeriodCloseService 关账冒烟：已接入真实损益发生额聚合（依赖数据库连接，
 * 无连接时跳过）。status=calculated 表示仅完成计算、未生成结转凭证。
 */
class PeriodCloseSmokeTest extends TestCase
{
    private function requireDatabase(): void
    {
        try {
            Db::select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('无可用数据库连接，跳过真实聚合断言: ' . $e->getMessage());
        }
    }

    public function testCloseProfitAndLossReturnsExpectedStructure(): void
    {
        $this->requireDatabase();
        $result = (new \app\service\finance\PeriodCloseService())->closeProfitAndLoss(2026, 8);

        $this->assertSame('2026-08', $result['period']);
        $this->assertIsNumeric($result['revenue_total']);
        $this->assertIsNumeric($result['expense_total']);
        $this->assertIsNumeric($result['net_profit']);
        $this->assertArrayHasKey('voucher_id', $result);
        $this->assertSame('calculated', $result['status']);
        $this->assertNotEmpty($result['message']);
    }

    public function testPeriodFormatMatchesMonthPadding(): void
    {
        $this->requireDatabase();
        $result = (new \app\service\finance\PeriodCloseService())->closeProfitAndLoss(2026, 12);
        $this->assertSame('2026-12', $result['period']);
    }
}
