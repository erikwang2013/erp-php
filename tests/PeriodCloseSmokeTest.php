<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * PeriodCloseService 关账冒烟：当前为结构占位实现（"期末结转需连接科目
 * 余额表运行"），固化其返回契约（期间格式、数值字段、状态机占位）。
 * 真实结转（查询 account_balances 生成凭证）依赖 DB，待服务接入数据层后
 * 由集成测试覆盖。
 */
class PeriodCloseSmokeTest extends TestCase
{
    public function testCloseProfitAndLossReturnsExpectedStructure(): void
    {
        $result = (new \app\service\finance\PeriodCloseService())->closeProfitAndLoss(2026, 8);

        $this->assertSame('2026-8', $result['period']);
        $this->assertIsNumeric($result['revenue_total']);
        $this->assertIsNumeric($result['expense_total']);
        $this->assertIsNumeric($result['net_profit']);
        $this->assertArrayHasKey('voucher_id', $result);
        $this->assertSame('pending', $result['status']);
        $this->assertNotEmpty($result['message']);
    }

    public function testPeriodFormatMatchesMonthPadding(): void
    {
        $result = (new \app\service\finance\PeriodCloseService())->closeProfitAndLoss(2026, 12);
        $this->assertSame('2026-12', $result['period']);
    }
}
