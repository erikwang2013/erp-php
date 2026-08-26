<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\finance\PeriodCloseService;
use PHPUnit\Framework\TestCase;

/**
 * 期末结转服务：损益结转结构（当前为预留实现，返回 pending 状态）
 */
class PeriodCloseServiceTest extends TestCase
{
    public function testPeriodFormatIsYearMonth(): void
    {
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 8);
        $this->assertSame('2026-8', $result['period']);
    }

    public function testZeroTotalsBeforeClose(): void
    {
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 8);
        $this->assertSame(0, $result['revenue_total']);
        $this->assertSame(0, $result['expense_total']);
        $this->assertSame(0, $result['net_profit']);
    }

    public function testStatusIsPendingWithoutBalances(): void
    {
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 8);
        $this->assertSame('pending', $result['status']);
        $this->assertNull($result['voucher_id']);
    }

    public function testSingleDigitMonthPaddedByCallerNotService(): void
    {
        // 服务端直接拼接 "年-月"，不做补零；01 月需调用方传 1
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 1);
        $this->assertSame('2026-1', $result['period']);
    }

    public function testResultContainsRequiredKeys(): void
    {
        $result = (new PeriodCloseService())->closeProfitAndLoss(2026, 12);
        foreach (['period', 'revenue_total', 'expense_total', 'net_profit', 'voucher_id', 'status', 'message'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
