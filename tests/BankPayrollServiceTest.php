<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\hr\BankPayrollService;
use PHPUnit\Framework\TestCase;

class BankPayrollServiceTest extends TestCase
{
    public function testGeneratePayrollFile(): void
    {
        $svc = new BankPayrollService();
        $csv = $svc->generatePayrollFile([
            ['employee_name' => '张三', 'bank_account' => '6222000012345678', 'net_salary' => 8250.5, 'bank_branch' => '深圳分行'],
            ['employee_name' => '李四', 'bank_account' => '6222000087654321', 'net_salary' => 15600, 'bank_branch' => '北京分行'],
        ]);
        $lines = explode("\n", $csv);
        $this->assertCount(3, $lines); // header + 2 rows
        $this->assertStringContainsString('张三', $lines[1]);
        $this->assertStringContainsString('8250.50', $lines[1]);
        $this->assertStringContainsString('李四', $lines[2]);
    }

    public function testValidateMissingAccounts(): void
    {
        $svc = new BankPayrollService();
        $result = $svc->validateAccounts([
            ['bank_account' => '123'],
            ['bank_account' => ''],
        ]);
        $this->assertFalse($result['valid']);
    }
}
