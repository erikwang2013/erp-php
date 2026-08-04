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
}
