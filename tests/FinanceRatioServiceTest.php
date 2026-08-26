<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\FinancialRatioService;
use PHPUnit\Framework\TestCase;

class FinanceRatioServiceTest extends TestCase
{
    public function testCurrentRatio(): void
    {
        $svc = new FinancialRatioService();
        $r = $svc->calculate(
            ['current_assets' => 200, 'current_liabilities' => 100, 'total_liabilities' => 300, 'total_assets' => 500],
            ['net_profit' => 50, 'revenue' => 400]
        );
        $this->assertEquals(2.0, $r['current_ratio']);
        $this->assertEquals('60%', $r['debt_ratio']);
        $this->assertEquals('12.5%', $r['net_profit_margin']);
        $this->assertEquals('10%', $r['return_on_assets']);
    }

    public function testZeroInputsDoNotDivideByZero(): void
    {
        $svc = new FinancialRatioService();
        $r = $svc->calculate([], []);
        // 分母缺省兜底（?? 1 与 max(_,1)）：空输入不得产生除零/NaN/Inf
        $this->assertSame(0.0, $r['current_ratio']);
        $this->assertSame('0%', $r['debt_ratio']);
        $this->assertSame('0%', $r['net_profit_margin']);
        $this->assertSame('0%', $r['return_on_assets']);
    }
}
