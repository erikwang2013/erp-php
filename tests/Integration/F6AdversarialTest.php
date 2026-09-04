<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * P2-F6 对抗回归测试（lead 接管验证轮 —— f6-tester 因 autocompact thrashing 阵亡）。
 *
 * 锁定验证轮发现的 ValueError 类缺陷：金额/余额输入若走 is_numeric 宽松判定，
 * '1e3'/'INF' 等 bcmath 不可解析形态会直达 bcadd/bc_round 抛未捕获 ValueError。
 * 修复：BankReconService 金额类输入一律严格十进制正则（系统边界校验）。
 * 本文件回归该缺陷 + 票据校验锐边 + 对账报告形状。
 */
#[Group('integration')]
class F6AdversarialTest extends F6FundScaffold
{
    #[TestDox('回归：科学计数/INF 形态的余额与发生额 → 明确错误消息而非 ValueError')]
    public function testScientificNotationInputsRejectedCleanly(): void
    {
        $account = $this->seedBankAccount('ADV1');
        $svc = $this->reconService();

        foreach (['1e3', 'INF', '-INF', '0x1A'] as $bad) {
            [$data, $err] = $svc->importStatement($account, $this->newBatch(), [[
                'stmt_date' => '2026-09-01',
                'direction' => 1,
                'amount' => '100.00',
                'balance_after' => $bad,
            ]]);
            self::assertNull($data, "balance_after={$bad} 应整批拒绝");
            self::assertStringContainsString('交易后余额非法', (string) $err, "balance_after={$bad}");

            [$data2, $err2] = $svc->importStatement($account, $this->newBatch(), [[
                'stmt_date' => '2026-09-01',
                'direction' => 1,
                'amount' => $bad,
            ]]);
            self::assertNull($data2, "amount={$bad} 应整批拒绝");
            self::assertStringContainsString('发生额非法', (string) $err2, "amount={$bad}");
        }

        // 票据侧同族回归：票面金额亦为严格正则
        [$bdata, $berr] = $this->billService()->store([
            'bill_no' => 'ADV-BILL-E3',
            'type' => 1,
            'direction' => 1,
            'amount' => '1e3',
            'due_date' => '2026-12-31',
        ]);
        self::assertNull($bdata, '票据金额 1e3 应拒绝');
        self::assertStringContainsString('票面金额非法', (string) $berr);

        self::assertRowCount('erp_finance_bank_statement', ['bank_account_id' => $account], 0, '拒绝行不落库');
    }

    #[TestDox('输入形态：.5/5./+5/空白/零 → 各自明确拒绝，负余额是合法形态')]
    public function testAmountShapeStrictness(): void
    {
        $account = $this->seedBankAccount('ADV2');
        $svc = $this->reconService();

        foreach (['.5', '5.', '+5', ' 5', '5 ', '1.2.3'] as $bad) {
            [$data, $err] = $svc->importStatement($account, $this->newBatch(), [[
                'stmt_date' => '2026-09-01',
                'direction' => 1,
                'amount' => $bad,
            ]]);
            self::assertNull($data, "amount={$bad} 应拒绝");
            self::assertStringContainsString('发生额非法', (string) $err, "amount={$bad}");
        }

        [$data, $err] = $svc->importStatement($account, $this->newBatch(), [[
            'stmt_date' => '2026-09-01',
            'direction' => 1,
            'amount' => '0',
        ]]);
        self::assertNull($data, '金额 0 应拒绝');
        self::assertStringContainsString('必须大于 0', (string) $err);

        // 负余额（透支形态）允许，金额精度按 bc_round 2 位归一
        $batch = $this->newBatch();
        $this->statementBatches[] = $batch;
        [$ok, $err2] = $svc->importStatement($account, $batch, [[
            'stmt_date' => '2026-09-01',
            'direction' => 2,
            'amount' => '100.005',
            'balance_after' => '-0.05',
        ]]);
        self::assertNull($err2, '合法行应导入: ' . (string) $err2);
        self::assertSame(1, (int) ($ok['imported'] ?? 0));
        $stored = Capsule::table('erp_finance_bank_statement')
            ->where('import_batch', $batch)
            ->first();
        self::assertNotNull($stored, '行落库');
        self::assertSame('100.01', (string) $stored->amount, '0.005 半值 half-up → 100.01');
        self::assertSame('-0.05', (string) $stored->balance_after);
    }

    #[TestDox('票据：store 校验锐边 + 背书规则（空背书人/过期/应付票/重复背书）')]
    public function testBillValidationAndEndorseEdges(): void
    {
        // 自愈：清掉历史失败 run 可能泄漏的同前缀票据
        Capsule::table('erp_finance_bill')->where('bill_no', 'like', 'ADV-%')->delete();

        $svc = $this->billService();
        $today = date('Y-m-d');

        // store 锐边
        [$d, $e] = $svc->store(['bill_no' => '', 'type' => 1, 'direction' => 1, 'amount' => '100', 'due_date' => '2026-12-31']);
        self::assertStringContainsString('票号必填', (string) $e);
        [$d, $e] = $svc->store(['bill_no' => 'ADV-T3', 'type' => 3, 'direction' => 1, 'amount' => '100', 'due_date' => '2026-12-31']);
        self::assertStringContainsString('票据类型非法', (string) $e);
        // 过期票可登记（拦截点在背书/贴现层），登记后背书被拒
        [$d, $e] = $svc->store(['bill_no' => 'ADV-D1', 'type' => 1, 'direction' => 1, 'amount' => '100', 'due_date' => '2025-01-01']);
        self::assertNull($e, '过期票登记应成功: ' . (string) $e);
        $billPast = (int) Capsule::table('erp_finance_bill')->where('bill_no', 'ADV-D1')->value('id');
        $this->billIds[] = $billPast;
        self::assertStringContainsString('已到期', (string) $svc->endorse($billPast, 'X'), '过期票登记后不可背书');

        // 合法收票（应收）
        [$ok, $err] = $svc->store([
            'bill_no' => 'ADV-B1', 'type' => 1, 'direction' => 1,
            'amount' => '100000.00', 'issue_date' => $today, 'due_date' => date('Y-m-d', strtotime('+60 days')),
        ]);
        self::assertNull($err, '收票应成功: ' . (string) $err);
        self::assertNotNull($ok, '成功返回票据模型');
        $billId = (int) Capsule::table('erp_finance_bill')->where('bill_no', 'ADV-B1')->value('id');
        self::assertGreaterThan(0, $billId);
        $this->billIds[] = $billId;

        // 背书：空被背书人拒绝
        self::assertStringContainsString('被背书人必填', (string) $svc->endorse($billId, '  '));
        // 背书成功 0→1
        self::assertNull($svc->endorse($billId, '下游公司'));
        // 重复背书拒绝
        self::assertStringContainsString('仅 在库', (string) $svc->endorse($billId, '第三方'));

        // 应付票背书被拒 + 过期应收票背书被拒
        [$ok2, $err2] = $svc->store([
            'bill_no' => 'ADV-B2', 'type' => 2, 'direction' => 2,
            'amount' => '50000.00', 'due_date' => date('Y-m-d', strtotime('+30 days')),
        ]);
        self::assertNull($err2);
        $billPayable = (int) Capsule::table('erp_finance_bill')->where('bill_no', 'ADV-B2')->value('id');
        $this->billIds[] = $billPayable;
        self::assertStringContainsString('不能背书转让', (string) $svc->endorse($billPayable, 'X'));

        [$ok3, $err3] = $svc->store([
            'bill_no' => 'ADV-B3', 'type' => 1, 'direction' => 1,
            'amount' => '100.00', 'issue_date' => date('Y-m-d', strtotime('-30 days')), 'due_date' => date('Y-m-d', strtotime('-1 day')),
        ]);
        self::assertNull($err3);
        $billExpired = (int) Capsule::table('erp_finance_bill')->where('bill_no', 'ADV-B3')->value('id');
        $this->billIds[] = $billExpired;
        self::assertStringContainsString('已到期', (string) $svc->endorse($billExpired, 'X'), '过期票据不可背书');

        // 票号唯一（含软删，经 store 二次登记同一票号）
        [$d, $e] = $svc->store([
            'bill_no' => 'ADV-B1', 'type' => 1, 'direction' => 1,
            'amount' => '1.00', 'due_date' => '2026-12-31',
        ]);
        self::assertStringContainsString('票号已存在', (string) $e);
    }

    #[TestDox('对账报告：导入未匹配行后 matched/unmatched 两侧结构与计数正确')]
    public function testReconReportShape(): void
    {
        $account = $this->seedBankAccount('ADV4');
        $svc = $this->reconService();
        $this->seedJournal($account, '2026-09-01', 1, '88.00', 'ADV-J-1');

        [$data, $err] = $svc->importStatement($account, $this->newBatch(), [
            ['stmt_date' => '2026-09-01', 'direction' => 1, 'amount' => '88.00', 'counterparty' => 'ADV-J-1'],
            ['stmt_date' => '2026-09-02', 'direction' => 1, 'amount' => '12.34', 'counterparty' => '无对应行'],
        ]);
        self::assertNull($err, '导入成功: ' . (string) $err);

        [$report, $rerr] = $svc->reconReport($account, '2026-09-01', '2026-09-30');
        self::assertNull($rerr);
        self::assertIsArray($report);
        self::assertArrayHasKey('matched', $report);
        self::assertArrayHasKey('unmatched_statements', $report);
        self::assertArrayHasKey('unmatched_journals', $report);
        self::assertArrayHasKey('summary', $report);
        self::assertCount(2, $report['unmatched_statements'], '导入 2 行均未匹配');
        self::assertCount(1, $report['unmatched_journals'], '1 笔日记账未匹配');
        self::assertSame(0, count($report['matched']));

        // summary 金额为 bcmath 字符串
        $s = $report['summary'];
        self::assertSame('0.00', (string) ($s['matched']['in'] ?? '0.00'));
        self::assertSame('100.34', (string) ($s['unmatched_stmt']['in'] ?? ''), '88.00+12.34');
        self::assertSame('88.00', (string) ($s['unmatched_journal']['in'] ?? ''));

        // matched 行键名（m.cash_journal_id / m.statement_id 经 ->get() 返回）
        [$data2, $err2] = $svc->autoReconcile($account, '2026-09-01', '2026-09-30', 2);
        self::assertNull($err2, '自动核销运行: ' . (string) $err2);
        [$report2] = $svc->reconReport($account, '2026-09-01', '2026-09-30');
        self::assertNotEmpty($report2['matched'], '88.00 唯一候选自动命中');
        $hit = $report2['matched'][0];
        self::assertArrayHasKey('cash_journal_id', $hit, 'matched 行含 cash_journal_id');
    }
}
