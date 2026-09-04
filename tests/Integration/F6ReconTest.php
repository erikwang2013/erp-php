<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\finance\BankReconService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Group;

/**
 * F6 银企对账集成测试：导入幂等 / 自动核销两段式（金额日期→摘要）/ 手工核销守卫 /
 * 取消核销 / 对账报告汇总。全部金额断言走 bcmath（assertBcEquals），杜绝 float。
 */
#[Group('integration')]
class F6ReconTest extends F6FundScaffold
{
    private const D = '2026-06-10';

    /** 导入成功：行数/标记返回正确，落库金额规整到 2 位小数 */
    public function testImportValidRows(): void
    {
        $accountId = $this->seedBankAccount('imp');
        $r = $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '100.5', 'counterparty' => '甲公司'],
            ['stmt_date' => '2026-06-11', 'direction' => 2, 'amount' => '0.05', 'reference' => '流水-2'],
            ['stmt_date' => '2026-06-12', 'direction' => 1, 'amount' => '88.888', 'balance_after' => '1000.9'],
        ]);

        $this->assertNull($r[1]);
        $this->assertSame(['imported' => 3, 'skipped' => 0, 'duplicated' => false], $r[0]);
        $rows = Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)
            ->orderBy('stmt_date')->get();
        $this->assertSame(3, $rows->count());
        $this->assertSame('100.50', (string) $rows[0]->amount);
        $this->assertSame('0.05', (string) $rows[1]->amount);
        $this->assertSame('88.89', (string) $rows[2]->amount);   // bc_round half-up
        $this->assertSame('1000.90', (string) $rows[2]->balance_after);
    }

    /** 幂等：同账户同批次重复导入整批跳过；同批次不同账户可正常导入 */
    public function testImportDuplicateBatch(): void
    {
        $accountId = $this->seedBankAccount('imp-dup');
        $other = $this->seedBankAccount('imp-dup-b');
        $batch = $this->newBatch();
        $rows = [['stmt_date' => self::D, 'direction' => 1, 'amount' => '10.00']];
        $r1 = $this->importBatch($accountId, $batch, $rows);
        $this->assertSame(1, $r1[0]['imported']);

        $r2 = $this->importBatch($accountId, $batch, $rows);
        $this->assertNull($r2[1]);
        $this->assertSame(['imported' => 0, 'skipped' => 1, 'duplicated' => true], $r2[0]);
        $this->assertRowCount('erp_finance_bank_statement', ['bank_account_id' => $accountId], 1, '同批重复不落行');

        $r3 = $this->importBatch($other, $batch, $rows);
        $this->assertSame(1, $r3[0]['imported'], '同批次不同账户不受幂等影响');
    }

    /** 整批原子：任一行非法整批拒绝，错误带行号前缀 */
    public function testImportInvalidRowRejectsWholeBatch(): void
    {
        $accountId = $this->seedBankAccount('imp-bad');
        $base = ['stmt_date' => self::D, 'direction' => 1, 'amount' => '10.00'];
        $cases = [
            [array_merge($base, ['stmt_date' => '2026-13-40']), '第 1 行交易日期非法'],
            [array_merge($base, ['stmt_date' => '2026/06/10']), '第 1 行交易日期非法'],
            [array_merge($base, ['amount' => '-1']), '第 1 行发生额必须大于 0'],
            [array_merge($base, ['amount' => '1e3']), '第 1 行发生额非法'],   // 科学计数法（regex 拦截于 bcmath 前）
            [array_merge($base, ['amount' => 'abc']), '第 1 行发生额非法'],
            [array_merge($base, ['direction' => 3]), '第 1 行方向非法: 仅支持 1=收入 2=支出'],
            [array_merge($base, ['counterparty' => str_repeat('长', 201)]), '第 1 行对方户名/摘要超长(200)'],
            [array_merge($base, ['balance_after' => '1,000.00']), '第 1 行交易后余额非法'],
        ];
        foreach ($cases as [$row, $expectedErr]) {
            // 每例用独立批次：坏行置于第 1 行，断言报行号=1
            $r = $this->importBatch($accountId, $this->newBatch(), [$row]);
            $this->assertNull($r[0]);
            $this->assertSame($expectedErr, $r[1]);
        }
        // 首位合法、第 2 行非法 → 错误指向第 2 行
        $r = $this->importBatch($accountId, $this->newBatch(), [$base, array_merge($base, ['amount' => '0'])]);
        $this->assertNull($r[0]);
        $this->assertSame('第 2 行发生额必须大于 0', $r[1]);
        // 坏数据在第一批示例之后的情况：3 行批次中第 2 行日期非法
        $rows = [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '1.00'],
            ['stmt_date' => 'not-a-date', 'direction' => 1, 'amount' => '2.00'],
            ['stmt_date' => self::D, 'direction' => 2, 'amount' => '3.00'],
        ];
        $r = $this->importBatch($accountId, $this->newBatch(), $rows);
        $this->assertSame('第 2 行交易日期非法', $r[1]);
        $this->assertRowCount('erp_finance_bank_statement', ['bank_account_id' => $accountId], 0, '整批原子性：零落行');
    }

    /** 批次号/账户守卫 */
    public function testImportGuards(): void
    {
        $accountId = $this->seedBankAccount('imp-g');
        $r = $this->reconService()->importStatement($this->nextId(), 'b1', [['stmt_date' => self::D, 'direction' => 1, 'amount' => '1.00']]);
        $this->assertSame('银行账户不存在', $r[1]);
        $r = $this->reconService()->importStatement($accountId, '', [['stmt_date' => self::D, 'direction' => 1, 'amount' => '1.00']]);
        $this->assertSame('导入批次号必填且不超过 50 字符', $r[1]);
        $r = $this->reconService()->importStatement($accountId, str_repeat('长', 51), []);
        $this->assertSame('导入批次号必填且不超过 50 字符', $r[1]);
        $r = $this->reconService()->importStatement($accountId, 'b-ok', []);
        $this->assertSame('导入行不能为空', $r[1]);
    }

    /** 自动-金额日期：同额同向同窗口唯一候选 → MATCH_AUTO_DATE；异向同额不误配 */
    public function testAutoUniqueMatch(): void
    {
        $accountId = $this->seedBankAccount('auto-1');
        $j1 = $this->seedJournal($accountId, self::D, 1, '100.00', '货款入账');
        $j2 = $this->seedJournal($accountId, self::D, 2, '100.00', '付款支出'); // 同额异向：须排除
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '100.00', 'counterparty' => '乙公司'],
        ]);

        $r = $this->reconService()->autoReconcile($accountId, self::D, self::D, 3);
        $this->assertNull($r[1]);
        $data = $r[0];
        $this->assertCount(1, $data['matched']);
        $m = $data['matched'][0];
        $this->assertSame($j1, $m['cash_journal_id']);
        $this->assertSame(BankReconService::MATCH_AUTO_DATE, $m['match_type']);
        $this->assertSame(self::D, $m['stmt_date']);
        $this->assertSame(self::D, $m['journal_date']);
        $this->assertSame(1, $m['direction']);
        $this->assertBcEquals('100.00', $m['amount']);
        $this->assertSame([], $data['manual_candidates']);
        // 异向同额日记账未核销 → 未达清单
        $this->assertCount(1, $data['unmatched_journals']);
        $this->assertSame($j2, $data['unmatched_journals'][0]['id']);
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 1);

        // 确定性：重复执行同结果，不重复落库
        $r2 = $this->reconService()->autoReconcile($accountId, self::D, self::D, 3);
        $this->assertNull($r2[1]);
        $this->assertCount(0, $r2[0]['matched'], '已对账流水不再参与');
        $this->assertCount(1, $r2[0]['unmatched_journals']);
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 1, '重复执行不重复写轨');
    }

    /** 日期窗口闭区间：D+3 命中（含边界）；窗口外 D+5 不参与任何候选 */
    public function testAutoDateWindowBoundary(): void
    {
        // 用例 A：日记账恰在 D+3（窗口上界内）→ 自动命中
        $accountId = $this->seedBankAccount('auto-w');
        $this->seedJournal($accountId, '2026-06-13', 1, '200.00');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '200.00', 'counterparty' => '丙公司'],
        ]);
        $r = $this->reconService()->autoReconcile($accountId, self::D, self::D, 3);
        $this->assertCount(1, $r[0]['matched']);
        $this->assertSame(BankReconService::MATCH_AUTO_DATE, $r[0]['matched'][0]['match_type']);

        // 用例 B：日记账 D+5 超出窗口 → 无金额日期候选；摘要无命中 → 银行未达(人工清单空 journals)
        $accountB = $this->seedBankAccount('auto-w2');
        $this->seedJournal($accountB, '2026-06-15', 1, '300.00');
        $this->importBatch($accountB, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '300.00', 'counterparty' => '丁公司'],
        ]);
        $r = $this->reconService()->autoReconcile($accountB, self::D, self::D, 3);
        $this->assertNull($r[1]);
        $this->assertSame([], $r[0]['matched']);
        $this->assertCount(1, $r[0]['manual_candidates']);
        $this->assertSame([], $r[0]['manual_candidates'][0]['journals'], '银行未达：无候选日记账');
        $this->assertSame([], $r[0]['unmatched_journals'], '窗口外日记账不进入未达池');
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountB], 0);
    }

    /** 自动-摘要第二段：放宽日期（记账错位 D+8），按对方户名命中唯一 → MATCH_AUTO_SUMMARY */
    public function testAutoSummaryByCounterparty(): void
    {
        $accountId = $this->seedBankAccount('auto-sum');
        $kw = 'T-F6-华东电子';   // 完整对方户名串进摘要
        $jHit = $this->seedJournal($accountId, '2026-06-18', 1, '500.00', $kw . ' 货款到账');
        $this->seedJournal($accountId, '2026-06-12', 1, '499.00', '无关摘要'); // 同窗口异额：两段都不命中
        $this->seedJournal($accountId, '2026-06-13', 2, '500.00', $kw . ' 支出'); // 同额异向：两段都不命中
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => '2026-06-10', 'direction' => 1, 'amount' => '500.00', 'counterparty' => $kw, 'reference' => 'REF-88'],
        ]);

        $r = $this->reconService()->autoReconcile($accountId, '2026-06-10', '2026-06-20', 3);
        $this->assertNull($r[1]);
        $this->assertCount(1, $r[0]['matched']);
        $m = $r[0]['matched'][0];
        $this->assertSame($jHit, $m['cash_journal_id']);
        $this->assertSame(BankReconService::MATCH_AUTO_SUMMARY, $m['match_type'], '金额日期段无候选，走摘要段');
        $this->assertSame('2026-06-18', $m['journal_date']);
        $this->assertSame([], $r[0]['manual_candidates']);
    }

    /** 摘要关键词顺序：对方户名为空时退而匹配流水号 */
    public function testAutoSummaryByReference(): void
    {
        $accountId = $this->seedBankAccount('auto-ref');
        $jHit = $this->seedJournal($accountId, '2026-06-19', 1, '60.00', '平台结算 R-9527');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => '2026-06-10', 'direction' => 1, 'amount' => '60.00', 'counterparty' => '', 'reference' => 'R-9527'],
        ]);
        $r = $this->reconService()->autoReconcile($accountId, '2026-06-10', '2026-06-20', 3);
        $this->assertNull($r[1]);
        $this->assertCount(1, $r[0]['matched']);
        $this->assertSame($jHit, $r[0]['matched'][0]['cash_journal_id']);
        $this->assertSame(BankReconService::MATCH_AUTO_SUMMARY, $r[0]['matched'][0]['match_type']);
    }

    /** 摘要多候选不猜配：两条同额同向含关键词日记账 → 人工清单 2 候选，零落库 */
    public function testAutoSummaryAmbiguous(): void
    {
        $accountId = $this->seedBankAccount('auto-amb');
        $kw = 'T-F6-华南钢贸';
        $jA = $this->seedJournal($accountId, '2026-06-18', 1, '800.00', $kw . ' 六月货款');
        $jB = $this->seedJournal($accountId, '2026-06-19', 1, '800.00', $kw . ' 预收定金');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => '2026-06-10', 'direction' => 1, 'amount' => '800.00', 'counterparty' => $kw],
        ]);
        $r = $this->reconService()->autoReconcile($accountId, '2026-06-10', '2026-06-20', 3);
        $this->assertNull($r[1]);
        $this->assertSame([], $r[0]['matched']);
        $this->assertCount(1, $r[0]['manual_candidates']);
        $cand = $r[0]['manual_candidates'][0];
        $this->assertCount(2, $cand['journals']);
        $this->assertSame([$jA, $jB], array_column($cand['journals'], 'id'));
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 0, '歧义不落库');
    }

    /** 同日同额双日记账 → 人工清单；手工核销其一后再跑自动 → 结果稳定 */
    public function testAutoAmbiguityThenManual(): void
    {
        $accountId = $this->seedBankAccount('auto-2');
        $jA = $this->seedJournal($accountId, self::D, 1, '300.00', '货款-甲');
        $jB = $this->seedJournal($accountId, self::D, 1, '300.00', '货款-乙');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '300.00', 'counterparty' => '戊公司'],
        ]);
        $r = $this->reconService()->autoReconcile($accountId, self::D, self::D, 3);
        $this->assertNull($r[1]);
        $this->assertSame([], $r[0]['matched']);
        $cand = $r[0]['manual_candidates'][0];
        $this->assertCount(2, $cand['journals']);
        $stmtId = (int) Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)->value('id');

        // 人工选定 jA → type 3
        $err = $this->reconService()->manualReconcile($accountId, $stmtId, $jA, 9);
        $this->assertNull($err);
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId, 'cash_journal_id' => $jA], 1);
        $this->assertSame(3, (int) Capsule::table('erp_finance_bank_recon_match')->where('cash_journal_id', $jA)->value('match_type'));
        $this->assertSame(9, (int) Capsule::table('erp_finance_bank_recon_match')->where('cash_journal_id', $jA)->value('created_by'));

        // 再跑自动：该流水已核销不参与；剩 jB 进未达
        $r2 = $this->reconService()->autoReconcile($accountId, self::D, self::D, 3);
        $this->assertSame([], $r2[0]['matched']);
        $this->assertSame([], $r2[0]['manual_candidates']);
        $this->assertSame([$jB], array_column($r2[0]['unmatched_journals'], 'id'));
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 1, '自动不覆盖人工轨');
    }

    /** 手工核销守卫全链（顺序断言稳定消息） + 取消核销释放 */
    public function testManualReconcileGuards(): void
    {
        $accountId = $this->seedBankAccount('manual');
        $other = $this->seedBankAccount('manual-other');
        $jMatch = $this->seedJournal($accountId, self::D, 1, '400.00');
        $jOtherAccount = $this->seedJournal($other, self::D, 1, '400.00');
        $jDiffAmount = $this->seedJournal($accountId, self::D, 1, '999.00');
        $jDiffDir = $this->seedJournal($accountId, self::D, 2, '400.00');
        $jFree = $this->seedJournal($accountId, self::D, 1, '400.00');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '400.00'],
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '400.00'],
        ]);
        $stmtIds = Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)
            ->orderBy('id')->pluck('id')->all();

        $svc = $this->reconService();
        $this->assertSame('对账单行不存在', $svc->manualReconcile($accountId, $this->nextId(), $jMatch, 1));
        $this->assertSame('日记账行不存在或不属于该银行账户', $svc->manualReconcile($accountId, $stmtIds[0], $jOtherAccount, 1));
        $this->assertSame('流水金额与日记账金额不一致，不能核销', $svc->manualReconcile($accountId, $stmtIds[0], $jDiffAmount, 1));
        $this->assertSame('收支方向不一致，不能核销', $svc->manualReconcile($accountId, $stmtIds[0], $jDiffDir, 1));

        // 合法核销
        $this->assertNull($svc->manualReconcile($accountId, $stmtIds[0], $jMatch, 7));
        $this->assertSame('该对账单行已核销', $svc->manualReconcile($accountId, $stmtIds[0], $jFree, 7));
        $this->assertSame('该日记账行已核销', $svc->manualReconcile($accountId, $stmtIds[1], $jMatch, 7));
        // 取消核销：不存在记录 → 报错；删除后同对可重新核销（1:1 双释放）
        $this->assertSame('核销记录不存在', $svc->unreconcile($accountId, $this->nextId()));
        $this->assertNull($svc->unreconcile($accountId, $stmtIds[0]));
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 0);
        $this->assertNull($svc->manualReconcile($accountId, $stmtIds[0], $jMatch, 7), '取消后可重新核销');
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 1);
    }

    /** DB 级 1:1 硬约束：uk_statement/uk_journal 撞键 → QueryException（兜底并发场景） */
    public function testUniqueConstraints(): void
    {
        $accountId = $this->seedBankAccount('uk');
        $jA = $this->seedJournal($accountId, self::D, 1, '50.00');
        $jB = $this->seedJournal($accountId, self::D, 1, '50.00');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '50.00'],
            ['stmt_date' => self::D, 'direction' => 1, 'amount' => '50.00'],
        ]);
        $stmtIds = Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)
            ->orderBy('id')->pluck('id')->all();

        // 首次写入（合法、显式 snowflake id）：成功，作为后续撞键基线
        Capsule::table('erp_finance_bank_recon_match')->insert([
            'id' => $this->nextId(),
            'bank_account_id' => $accountId, 'statement_id' => $stmtIds[0], 'cash_journal_id' => $jA,
            'match_type' => 3, 'created_by' => 0,
        ]);
        // uk_statement：同流水配第二笔日记账（重复 (stmt[0], jA) 撞键）
        try {
            Capsule::table('erp_finance_bank_recon_match')->insert([
                'id' => $this->nextId(),
                'bank_account_id' => $accountId, 'statement_id' => $stmtIds[0], 'cash_journal_id' => $jA,
                'match_type' => 3, 'created_by' => 0,
            ]);
            $this->fail('应触发 uk_statement 唯一键冲突');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uk_statement', $e->getMessage());
        }
        // uk_journal：同日记账配第二笔流水（(stmt[1], jA) 撞日记账键）
        try {
            Capsule::table('erp_finance_bank_recon_match')->insert([
                'id' => $this->nextId(),
                'bank_account_id' => $accountId, 'statement_id' => $stmtIds[1], 'cash_journal_id' => $jA,
                'match_type' => 3, 'created_by' => 0,
            ]);
            $this->fail('应触发 uk_journal 唯一键冲突');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uk_journal', $e->getMessage());
        }
        $this->assertRowCount('erp_finance_bank_recon_match', ['bank_account_id' => $accountId], 1, '冲突插入全部失败，仅首次成功');
    }

    /** 对账报告：已对清单 + 双方未达 + 分向汇总（in/out 字符串，2 位小数） */
    public function testReconReport(): void
    {
        $accountId = $this->seedBankAccount('report');
        $this->importBatch($accountId, $this->newBatch(), [
            ['stmt_date' => '2026-06-10', 'direction' => 1, 'amount' => '100.00', 'counterparty' => 'A公司'],
            ['stmt_date' => '2026-06-11', 'direction' => 2, 'amount' => '50.5', 'counterparty' => 'B公司'],
        ]);
        $jIn = $this->seedJournal($accountId, '2026-06-12', 1, '100.00', 'A公司货款');
        $jOut = $this->seedJournal($accountId, '2026-06-13', 2, '50.50', 'B公司付款');
        $stmtIds = Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)
            ->orderBy('id')->pluck('id')->all();

        $this->assertNull($this->reconService()->manualReconcile($accountId, $stmtIds[0], $jIn, 1));
        [$data, $err] = $this->reconService()->reconReport($accountId, '2026-06-01', '2026-06-30');
        $this->assertNull($err);

        $this->assertCount(1, $data['matched']);
        $m = $data['matched'][0];
        $this->assertSame((int) $stmtIds[0], $m['statement_id']);
        $this->assertSame($jIn, $m['cash_journal_id']);
        $this->assertSame(3, $m['match_type']);
        $this->assertSame('A公司', $m['counterparty']);
        $this->assertSame('A公司货款', $m['summary']);

        $this->assertCount(1, $data['unmatched_statements']);
        $this->assertSame((int) $stmtIds[1], $data['unmatched_statements'][0]['id']);
        $this->assertCount(1, $data['unmatched_journals']);
        $this->assertSame($jOut, $data['unmatched_journals'][0]['id']);

        $sum = $data['summary'];
        $this->assertSame(['in' => '100.00', 'out' => '0.00'], $sum['matched']);
        $this->assertSame(['in' => '0.00', 'out' => '50.50'], $sum['unmatched_stmt']);
        $this->assertSame(['in' => '0.00', 'out' => '50.50'], $sum['unmatched_journal']);
    }

    /** 对账单列表：日期范围/批次/对账状态筛选 */
    public function testStatementList(): void
    {
        $accountId = $this->seedBankAccount('list');
        $batch = $this->newBatch();
        $this->importBatch($accountId, $batch, [
            ['stmt_date' => '2026-06-10', 'direction' => 1, 'amount' => '10.00'],
            ['stmt_date' => '2026-06-11', 'direction' => 2, 'amount' => '20.00'],
        ]);
        $j = $this->seedJournal($accountId, '2026-06-12', 1, '10.00');
        $stmtId = (int) Capsule::table('erp_finance_bank_statement')->where('bank_account_id', $accountId)->value('id');
        $this->assertNull($this->reconService()->manualReconcile($accountId, $stmtId, $j, 1));

        [$data, $err] = $this->reconService()->statementList($accountId, '2026-06-01', '2026-06-30');
        $this->assertNull($err);
        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $this->reconService()->statementList($accountId, '2026-06-01', '2026-06-30', '', 1)[0]['total'], '已对账 1');
        $this->assertSame(1, $this->reconService()->statementList($accountId, '2026-06-01', '2026-06-30', '', 0)[0]['total'], '未对账 1');
        $this->assertSame(2, $this->reconService()->statementList($accountId, '2026-06-01', '2026-06-30', $batch)[0]['total'], '批次筛选');
        $this->assertSame(0, $this->reconService()->statementList($accountId, '2026-07-01', '2026-07-31')[0]['total'], '范围外为空');
        $this->assertSame('日期范围非法', $this->reconService()->statementList($accountId, '2026-07-01', '2026-06-30')[1]);
    }
}
