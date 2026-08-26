<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\finance\AccountBalanceService;
use PHPUnit\Framework\TestCase;

/**
 * 科目余额服务：余额查询与试算平衡（当前为纯结构返回，无 DB 依赖）
 */
class AccountBalanceServiceTest extends TestCase
{
    public function testGetBalanceReturnsSubjectAndCurrentPeriodByDefault(): void
    {
        $result = (new AccountBalanceService())->getBalance(101);
        $this->assertSame(101, $result['account_subject_id']);
        $this->assertSame(date('Y-m'), $result['period']);
    }

    public function testGetBalanceHonorsCustomPeriod(): void
    {
        $result = (new AccountBalanceService())->getBalance(202, '2026-08');
        $this->assertSame('2026-08', $result['period']);
    }

    public function testGetBalanceReturnsZeroedSections(): void
    {
        $result = (new AccountBalanceService())->getBalance(101, '2026-08');
        $this->assertSame(0, $result['opening_debit']);
        $this->assertSame(0, $result['opening_credit']);
        $this->assertSame(0, $result['current_debit']);
        $this->assertSame(0, $result['current_credit']);
        $this->assertSame(0, $result['closing_debit']);
        $this->assertSame(0, $result['closing_credit']);
    }

    public function testTrialBalanceReturnsStructure(): void
    {
        $result = (new AccountBalanceService())->getTrialBalance('2026-08');
        $this->assertSame('2026-08', $result['period']);
        $this->assertSame(0, $result['total_debit']);
        $this->assertSame(0, $result['total_credit']);
        $this->assertSame([], $result['items']);
    }
}
