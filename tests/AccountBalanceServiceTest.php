<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\AccountBalanceService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Throwable;

/**
 * 科目余额服务：余额查询与试算平衡（从已审核凭证分录真实聚合，依赖数据库连接；
 * 无可用连接时跳过，不把占位零值当作契约）
 */
class AccountBalanceServiceTest extends TestCase
{
    private function requireDatabase(): void
    {
        try {
            Db::select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('无可用数据库连接，跳过真实聚合断言: ' . $e->getMessage());
        }
    }

    public function testGetBalanceReturnsSubjectAndCurrentPeriodByDefault(): void
    {
        $this->requireDatabase();
        $result = (new AccountBalanceService())->getBalance(101);
        $this->assertSame(101, $result['account_subject_id']);
        $this->assertSame(date('Y-m'), $result['period']);
    }

    public function testGetBalanceHonorsCustomPeriod(): void
    {
        $this->requireDatabase();
        $result = (new AccountBalanceService())->getBalance(202, '2026-08');
        $this->assertSame('2026-08', $result['period']);
    }

    public function testGetBalanceReturnsNumericSections(): void
    {
        $this->requireDatabase();
        $result = (new AccountBalanceService())->getBalance(101, '2026-08');
        foreach (['opening_debit', 'opening_credit', 'current_debit', 'current_credit', 'closing_debit', 'closing_credit'] as $key) {
            $this->assertIsNumeric($result[$key]);
        }
    }

    public function testTrialBalanceReturnsStructure(): void
    {
        $this->requireDatabase();
        $result = (new AccountBalanceService())->getTrialBalance('2026-08');
        $this->assertSame('2026-08', $result['period']);
        $this->assertIsNumeric($result['total_debit']);
        $this->assertIsNumeric($result['total_credit']);
        $this->assertIsArray($result['items']);
    }
}
