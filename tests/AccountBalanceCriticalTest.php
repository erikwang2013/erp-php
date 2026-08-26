<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * 资金余额关键路径：AccountBalanceService 当前为结构占位实现（无 DB），
 * 此处固化其返回契约；真正的加/减余额与锁（lockForUpdate）路径在
 * FinanceService::recordJournal / settleReceipt / settlePayment，
 * 强依赖 DB 事务，无法纯单测，见 testMutationPathRequiresDatabase。
 */
class AccountBalanceCriticalTest extends TestCase
{
    public function testGetBalanceStructureInvariants(): void
    {
        $result = (new \app\service\finance\AccountBalanceService())->getBalance(42);

        foreach (['opening_debit', 'opening_credit', 'current_debit', 'current_credit', 'closing_debit', 'closing_credit'] as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertIsNumeric($result[$key]);
        }
        $this->assertSame(42, $result['account_subject_id']);
    }

    public function testGetBalanceDefaultsPeriodToCurrentMonth(): void
    {
        $result = (new \app\service\finance\AccountBalanceService())->getBalance(1);
        $this->assertSame(date('Y-m'), $result['period']);

        $custom = (new \app\service\finance\AccountBalanceService())->getBalance(1, '2026-01');
        $this->assertSame('2026-01', $custom['period']);
    }

    public function testGetTrialBalanceStructure(): void
    {
        $result = (new \app\service\finance\AccountBalanceService())->getTrialBalance('2026-08');
        $this->assertSame('2026-08', $result['period']);
        $this->assertIsNumeric($result['total_debit']);
        $this->assertIsNumeric($result['total_credit']);
        $this->assertIsArray($result['items']);
    }

    public function testMutationPathRequiresDatabase(): void
    {
        // 余额增减（含 lockForUpdate 行锁与"余额不足"守卫）位于 FinanceService::recordJournal，
        // 执行体在 DB::transaction 内依赖真实数据库连接，纯单测环境无法覆盖；
        // 该路径由 tests/Integration（TEST_DB_* 环境变量就绪时）覆盖。
        $this->markTestSkipped('余额增减路径依赖 DB 事务与行锁，需集成测试环境（TEST_DB_*）执行');
    }
}
