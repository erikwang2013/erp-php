<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\hr\SalaryEngineService;
use PHPUnit\Framework\TestCase;

class SalaryEngineServiceTest extends TestCase
{
    public function testBasicSalary(): void
    {
        $svc = new SalaryEngineService();
        $r = $svc->calculate(10000);
        $this->assertEquals(10000, $r['gross']);
        $this->assertEquals(1050, $r['social_insurance']);
        $this->assertEquals(700, $r['housing_fund']);
        $this->assertEquals(3250, $r['taxable_income']);
    }

    public function testLowIncomeNoTax(): void
    {
        $r = (new SalaryEngineService())->calculate(5000);
        $this->assertEquals(0, $r['tax']);
    }

    public function testSiCap(): void
    {
        $r = (new SalaryEngineService())->calculate(50000);
        $this->assertEquals(2774.21, $r['social_insurance']);
    }

    public function testHfRateConfig(): void
    {
        $svc = new SalaryEngineService();
        $svc->configure(['housingFundRate' => 0.12]);
        $this->assertEquals(1200, $svc->calculate(10000)['housing_fund']);
    }

    public function testProgressiveTaxNoDoubleDeduction(): void
    {
        // 年度应税 144000: 累进 36000×3% + 108000×10% = 11880，不得再减 2520 速算扣除数
        $this->assertEquals(11880.0, (new SalaryEngineService())->calculateTax(144000));
        // 年度应税 50000: 36000×3% + 14000×10% = 2480
        $this->assertEquals(2480.0, (new SalaryEngineService())->calculateTax(50000));
        $this->assertEquals(0.0, (new SalaryEngineService())->calculateTax(0));
    }

    public function testMonthlyTaxAnnualized(): void
    {
        // 月度应税 3250 → 年度 39000 → 1380 / 12 = 115
        $svc = new SalaryEngineService();
        $r = $svc->calculate(10000);
        $this->assertEquals(115, $r['tax']);
        $this->assertEquals(3250, $r['taxable_income']);
        // 高薪月度应税 50000 → 税负不再为 0
        $r2 = $svc->calculate(60000);
        $this->assertGreaterThan(0, $r2['tax']);
    }
}
