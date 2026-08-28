<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\PeriodCloseService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Throwable;

/**
 * 期末结转服务：按期间真实汇总损益类科目发生额（依赖数据库连接，无连接时跳过）；
 * status=calculated 表示仅完成计算、未生成结转凭证。
 */
class PeriodCloseServiceTest extends TestCase
{
    private function requireDatabase(): void
    {
        try {
            Db::select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('无可用数据库连接，跳过真实聚合断言: ' . $e->getMessage());
        }
    }

    public function testPeriodFormatIsYearMonth(): void
    {
        $this->requireDatabase();
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 8);
        $this->assertSame('2026-08', $result['period']);
    }

    public function testReturnsNumericTotalsAndCalculatedStatus(): void
    {
        $this->requireDatabase();
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 8);
        $this->assertIsNumeric($result['revenue_total']);
        $this->assertIsNumeric($result['expense_total']);
        $this->assertIsNumeric($result['net_profit']);
        $this->assertSame('calculated', $result['status']);
        $this->assertNull($result['voucher_id']);
        $this->assertNotEmpty($result['message']);
    }

    public function testSingleDigitMonthPaddedByService(): void
    {
        $this->requireDatabase();
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 1);
        $this->assertSame('2026-01', $result['period']);
    }

    public function testResultContainsRequiredKeys(): void
    {
        $this->requireDatabase();
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 12);
        foreach (['period', 'revenue_total', 'expense_total', 'net_profit', 'voucher_id', 'status', 'message'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
