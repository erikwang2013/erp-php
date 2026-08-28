<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use support\Db;
use Throwable;

/**
 * 资金余额关键路径：AccountBalanceService 已接入真实聚合（已审核凭证分录
 * 按科目汇总），查询依赖数据库连接；无可用连接时优雅跳过（契约与
 * IntegrationTestCase 一致）。本类固化返回契约：字段齐全、数值合法、
 * 科目与期间回显正确。
 */
class AccountBalanceCriticalTest extends TestCase
{
    private function requireDatabase(): void
    {
        try {
            Db::select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('无可用数据库连接，跳过真实聚合断言: ' . $e->getMessage());
        }
    }

    public function testGetBalanceStructureInvariants(): void
    {
        $this->requireDatabase();
        $result = (new \app\service\finance\AccountBalanceService())->getBalance(42);

        foreach (['opening_debit', 'opening_credit', 'current_debit', 'current_credit', 'closing_debit', 'closing_credit'] as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertIsNumeric($result[$key]);
        }
        $this->assertSame(42, $result['account_subject_id']);
        $this->assertSame(date('Y-m'), $result['period']);
    }

    public function testGetBalanceHonorsCustomPeriod(): void
    {
        $this->requireDatabase();
        $result = (new \app\service\finance\AccountBalanceService())->getBalance(1, '2026-01');
        $this->assertSame('2026-01', $result['period']);
    }

    public function testGetTrialBalanceStructure(): void
    {
        $this->requireDatabase();
        $result = (new \app\service\finance\AccountBalanceService())->getTrialBalance('2026-08');
        $this->assertSame('2026-08', $result['period']);
        $this->assertIsNumeric($result['total_debit']);
        $this->assertIsNumeric($result['total_credit']);
        $this->assertIsArray($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('account_id', $item);
            $this->assertArrayHasKey('debit', $item);
            $this->assertArrayHasKey('credit', $item);
        }
    }

    public function testGetTrialBalanceRejectsMalformedPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new \app\service\finance\AccountBalanceService())->getTrialBalance('2026/08');
    }

    public function testMutationPathRequiresDatabase(): void
    {
        // 余额增减（含 lockForUpdate 行锁与"余额不足"守卫）位于 FinanceService::recordJournal，
        // 执行体在 DB::transaction 内依赖真实数据库连接，纯单测环境无法覆盖；
        // 该路径由 tests/Integration（TEST_DB_* 环境变量就绪时）覆盖。
        $this->markTestSkipped('余额增减路径依赖 DB 事务与行锁，需集成测试环境（TEST_DB_*）执行');
    }
}
