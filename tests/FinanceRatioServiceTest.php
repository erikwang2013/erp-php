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
}
