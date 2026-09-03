<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\FinanceConsolidationReport;
use app\model\FinanceLedger;
use app\service\finance\ConsolidationService;
use app\service\finance\LedgerBalanceService;
use app\service\finance\LedgerService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

/**
 * F1/F2 合并报表端到端：MAIN(CNY, 实记) + SUBUS(USD, 关账快照×7.1) → 草稿/抵销/出表。
 * 断言语料与 /tmp/f1f2_smoke.php part2 相同（测试库移植）；金额一律字符串等值。
 */
#[Group('integration')]
class F12MultiCompanyConsolidationTest extends F12MultiCompanyScaffold
{
    /**
     * 单体底稿：固定科目/币种 + MAIN 默认账套实记一张凭证（借银行500/贷资本200/贷收入300），
     * SUBUS(USD) 两张凭证（借银行1000/贷资本1000；借费用100/贷银行100），期间 2026-08 已开未关，
     * 挂 parent 集团关系 + USD→CNY 期末汇率 7.1。
     *
     * @return array{main_company_id:int, main_ledger_id:int, sub_company_id:int, sub_ledger_id:int}
     */
    private function buildBase(): array
    {
        $this->insertAccount(self::ACC_BANK_ID, '1002', '银行存款', 1);
        $this->insertAccount(self::ACC_CAPITAL_ID, '3001', '实收资本', 3);
        $this->insertAccount(self::ACC_REVENUE_ID, '4001', '主营业务收入', 4);
        $this->insertAccount(self::ACC_EXPENSE_ID, '5001', '管理费用', 5);
        $this->insertCurrency(self::CUR_CNY_ID, 'CNY');
        $this->insertCurrency(self::CUR_USD_ID, 'USD');

        $ledger = new LedgerService();
        [$mainCompany, $mainLedger] = $ledger->ensureDefaultCompanyLedger();
        $this->makeAuditedVoucher((int) $mainLedger->id, '2026-08-20', 'main-1', [
            ['account_id' => self::ACC_BANK_ID, 'debit_amount' => '500.00', 'summary' => '注册资本入账'],
            ['account_id' => self::ACC_CAPITAL_ID, 'credit_amount' => '200.00', 'summary' => '注册资本入账'],
            ['account_id' => self::ACC_REVENUE_ID, 'credit_amount' => '300.00', 'summary' => '主营收入'],
        ]);

        $subCompany = $ledger->createCompany([
            'code' => 'SUBUS',
            'name' => '子公司US',
            'base_currency' => 'USD',
        ]);
        $subLedger = FinanceLedger::where('company_id', (int) $subCompany->id)
            ->where('is_default', 1)->first();
        $this->assertNotNull($subLedger, 'SUBUS 默认账套缺失');
        $this->makeAuditedVoucher((int) $subLedger->id, '2026-08-10', 'sub-1', [
            ['account_id' => self::ACC_BANK_ID, 'debit_amount' => '1000.00', 'summary' => '资本入账'],
            ['account_id' => self::ACC_CAPITAL_ID, 'credit_amount' => '1000.00', 'summary' => '资本入账'],
        ]);
        $this->makeAuditedVoucher((int) $subLedger->id, '2026-08-15', 'sub-2', [
            ['account_id' => self::ACC_EXPENSE_ID, 'debit_amount' => '100.00', 'summary' => '管理费'],
            ['account_id' => self::ACC_BANK_ID, 'credit_amount' => '100.00', 'summary' => '管理费'],
        ]);
        $ledger->openPeriod((int) $subLedger->id, '2026-08');
        Capsule::table('erp_company')->where('id', (int) $subCompany->id)
            ->update(['parent_id' => (int) $mainCompany->id]);
        $this->insertRateUsdToCny();

        return [
            'main_company_id' => (int) $mainCompany->id,
            'main_ledger_id' => (int) $mainLedger->id,
            'sub_company_id' => (int) $subCompany->id,
            'sub_ledger_id' => (int) $subLedger->id,
        ];
    }

    /** 关掉 SUBUS 2026-08（快照固化）；若 $closeMain 为真 MAIN 同期一并关闭（验证账套维度唯一键） */
    private function closeSubPeriod(array $ids, bool $closeMain = false): void
    {
        $ledger = new LedgerService();
        if ($closeMain) {
            $ledger->openPeriod($ids['main_ledger_id'], '2026-08');
            $ledger->closePeriod($ids['main_ledger_id'], '2026-08');
        }
        $ledger->closePeriod($ids['sub_ledger_id'], '2026-08');
    }

    private function decodeReportData(FinanceConsolidationReport $report): array
    {
        $data = $report->report_data;

        return is_array($data) ? $data : (array) json_decode((string) $data, true);
    }

    /** 草稿金额口径（MAIN 实时/快照×1 + SUBUS ×7.1 共用同一数值），与 smoke part2 一致 */
    private function assertDraftCoreTotals(FinanceConsolidationReport $draft): void
    {
        $this->assertSame('6890.00', $draft->total_assets);
        $this->assertSame('0.00', $draft->total_liabilities);
        $this->assertSame('6890.00', $draft->total_equity);
        $this->assertSame('300.00', $draft->revenue);
        $this->assertSame('-410.00', $draft->net_profit);
        $this->assertSame(0, (int) $draft->status);
        $this->assertSame('CNY', $draft->base_currency);
    }

    private function assertSubsidiaryRow(array $row, string $rate): void
    {
        $this->assertSame('snapshot', $row['source'], 'SUBUS 应为关账快照取数');
        $this->assertSame($rate, $row['rate'], 'SUBUS 折算汇率');
        $this->assertSame('6390.00', $row['total_assets'], 'SUBUS 资产 900USD×7.1');
        $this->assertSame('0.00', $row['total_liabilities']);
        $this->assertSame('6390.00', $row['total_equity'], 'SUBUS 权益');
        $this->assertSame('0.00', $row['revenue']);
        $this->assertSame('-710.00', $row['net_profit'], 'SUBUS 净利 -100USD×7.1');
    }

    // ---- 用例 ----

    public function testBothLedgersCloseSamePeriodAndDraft(): void
    {
        $ids = $this->buildBase();
        $this->closeSubPeriod($ids, true);

        // 账套维度唯一键：MAIN/SUBUS 同关 2026-08 各自落库，互不冲突（uk_ledger_report 生效）
        $periods = Capsule::table('erp_finance_period')
            ->whereIn('ledger_id', [$ids['main_ledger_id'], $ids['sub_ledger_id']])
            ->where('period', '2026-08')->orderBy('ledger_id')->get();
        $this->assertCount(2, $periods);
        foreach ($periods as $period) {
            $this->assertSame(1, (int) $period->status, '关账后期间状态应为 1');
            $this->assertNotNull($period->closed_at);
        }
        // 重复关账拒绝（幂等守卫）
        $ledger = new LedgerService();
        try {
            $ledger->closePeriod($ids['sub_ledger_id'], '2026-08');
            $this->fail('重复关账未被拒绝');
        } catch (RuntimeException) {
        }

        // 两子公司均已关账 → 草稿全部走快照口径
        $con = new ConsolidationService();
        $draft = $con->generateDraft($ids['main_company_id'], 2026, 8, 'CNY');
        $this->assertDraftCoreTotals($draft);
        $data = $this->decodeReportData($draft);
        $this->assertCount(2, $data['subsidiaries']);
        $mainRow = $data['subsidiaries'][0];
        $subRow = $data['subsidiaries'][1];
        $this->assertSame($ids['main_company_id'], (int) $mainRow['company_id']);
        $this->assertSame('snapshot', $mainRow['source']);
        $this->assertSame('1', $mainRow['rate']);
        $this->assertSame('500.00', $mainRow['total_assets']);
        $this->assertSame('500.00', $mainRow['total_equity']);
        $this->assertSame('300.00', $mainRow['revenue']);
        $this->assertSame('300.00', $mainRow['net_profit']);
        $this->assertSubsidiaryRow($subRow, '7.100000');
        $this->assertSame(5, count($data['base_totals']));
        $this->assertSame('6890.00', $data['base_totals']['total_equity']);
        $this->assertSame([], $data['eliminations']);
    }

    public function testConsolidateContractErrorsAndLiveTotals(): void
    {
        $ids = $this->buildBase();

        // 关账前 SUBUS 实时单体：资产 900 / 权益 900（费用 -100 计入累计损益，验证权益符号口径）
        $bs = (new LedgerBalanceService())->computeBalanceSheet($ids['sub_ledger_id'], 2026, 8);
        $this->assertSame('900.00', $bs['total_assets']);
        $this->assertSame('0.00', $bs['total_liabilities']);
        $this->assertSame('900.00', $bs['total_equity']);
        $this->assertSame(0, bccomp(bcsub($bs['total_assets'], $bs['total_liabilities'], 2), $bs['total_equity'], 2), '资产=负债+权益恒等式');

        // consolidate() 单账套实时 happy path
        $con = new ConsolidationService();
        $out = $con->consolidate([
            ['ledger_id' => $ids['main_ledger_id'], 'report_year' => 2026, 'report_month' => 8],
        ], 'CNY');
        $this->assertSame('500.00', $out['total_assets']);
        $this->assertSame('0.00', $out['total_liabilities']);
        $this->assertSame('500.00', $out['total_equity']);
        $this->assertSame('300.00', $out['revenue']);
        $this->assertSame('300.00', $out['net_profit']);
        $rows = $out['report_data']['subsidiaries'] ?? null;
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertSame('live', $rows[0]['source']);
        $this->assertSame('1', $rows[0]['rate']);

        // 各子公司期间须一致
        $this->expectThrow('合并报表各子公司期间须一致', function () use ($con, $ids): void {
            $con->consolidate([
                ['ledger_id' => $ids['main_ledger_id'], 'report_year' => 2026, 'report_month' => 8],
                ['ledger_id' => $ids['sub_ledger_id'], 'report_year' => 2026, 'report_month' => 7],
            ], 'CNY');
        });
        // 反向外币缺口（只有 USD→CNY，无 CNY→USD）
        $this->expectThrow('缺少 2026-08 期末汇率 CNY→USD', function () use ($con, $ids): void {
            $con->consolidate([
                ['ledger_id' => $ids['main_ledger_id'], 'report_year' => 2026, 'report_month' => 8],
            ], 'USD');
        });
        // 月份上界
        $this->expectThrow('月份必须在1-12之间', function () use ($con, $ids): void {
            $con->generateDraft($ids['main_company_id'], 2026, 13, 'CNY');
        });
    }

    public function testEliminationValidationAndAppendSemantics(): void
    {
        $ids = $this->buildBase();
        $this->closeSubPeriod($ids);
        $con = new ConsolidationService();
        $draft = $con->generateDraft($ids['main_company_id'], 2026, 8, 'CNY');
        $this->assertDraftCoreTotals($draft);
        $data = $this->decodeReportData($draft);
        $this->assertSame('live', $data['subsidiaries'][0]['source']);
        $this->assertSame('snapshot', $data['subsidiaries'][1]['source']);

        // 三类非法抵销逐一拒绝，且不得污染草稿
        $draftId = (int) $draft->id;
        $this->expectThrow('抵销分录不能为空', fn () => $con->addElimination($draftId, []));
        $this->assertEliminationState($draftId, '6890.00', 0);
        $this->expectThrow('抵销科目不存在：9999', function () use ($con, $draftId): void {
            $con->addElimination($draftId, [
                ['account_code' => '9999', 'summary' => '无此科目', 'debit_amount' => '10.00', 'credit_amount' => '10.00'],
            ]);
        });
        $this->assertEliminationState($draftId, '6890.00', 0);
        $this->expectThrow('抵销分录借贷不平衡：借 100.00 ≠ 贷 200.00', function () use ($con, $draftId): void {
            $con->addElimination($draftId, [
                ['account_code' => '1002', 'summary' => '不平', 'debit_amount' => '100.00', 'credit_amount' => '200.00'],
            ]);
        });
        $this->assertEliminationState($draftId, '6890.00', 0);

        // 追加语义：批1 → 资产/权益 6790 + 2 行；再追加同批 → 6690 + 4 行（非幂等覆盖）
        $fresh = $con->addElimination($draftId, [
            ['account_code' => '3001', 'summary' => '内部往来抵销', 'debit_amount' => '100.00', 'credit_amount' => '0.00'],
            ['account_code' => '1002', 'summary' => '内部往来抵销', 'debit_amount' => '0.00', 'credit_amount' => '100.00'],
        ]);
        $this->assertSame('6790.00', $fresh->total_assets);
        $this->assertSame('6790.00', $fresh->total_equity);
        $this->assertEliminationState($draftId, '6790.00', 2);

        $fresh2 = $con->addElimination($draftId, [
            ['account_code' => '3001', 'summary' => '内部往来抵销', 'debit_amount' => '100.00', 'credit_amount' => '0.00'],
            ['account_code' => '1002', 'summary' => '内部往来抵销', 'debit_amount' => '0.00', 'credit_amount' => '100.00'],
        ]);
        $this->assertSame('6690.00', $fresh2->total_assets);
        $this->assertSame('6690.00', $fresh2->total_equity);
        $this->assertEliminationState($draftId, '6690.00', 4);

        $data2 = $this->decodeReportData($fresh2);
        $eliminations = $data2['eliminations'];
        $this->assertCount(4, $eliminations, 'report_data.eliminations 为全部抵销行的扁平列表');
        $this->assertSame('3001', $eliminations[0]['account_code']);
        $this->assertSame('100.00', $eliminations[0]['debit_amount']);
        $this->assertSame('0.00', $eliminations[0]['credit_amount']);
        $this->assertSame('1002', $eliminations[3]['account_code']);
    }

    public function testIssueLocksDraftAndRegenerateVersioning(): void
    {
        $ids = $this->buildBase();
        $this->closeSubPeriod($ids);
        $con = new ConsolidationService();
        $draft = $con->generateDraft($ids['main_company_id'], 2026, 8, 'CNY');
        $this->assertDraftCoreTotals($draft);
        $draftId = (int) $draft->id;

        $issued = $con->issue($draftId);
        $this->assertSame(1, (int) $issued->status);
        $this->assertNotNull($issued->issued_at);

        // 出表后锁定：不可重复出表、不可补抵销
        $this->expectThrow('报表已出，不能重复出表', fn () => $con->issue($draftId));
        $this->expectThrow('报表已出表，抵销分录不可修改；如需修订请重新生成草稿', function () use ($con, $draftId): void {
            $con->addElimination($draftId, [
                ['account_code' => '1002', 'summary' => 'x', 'debit_amount' => '1.00', 'credit_amount' => '1.00'],
            ]);
        });

        // 重新生成 → 新草稿行（按 (company_id,year,month) 版本化，latest 取最新）
        $regenerated = $con->generateDraft($ids['main_company_id'], 2026, 8, 'CNY');
        $this->assertNotSame($draftId, (int) $regenerated->id);
        $this->assertDraftCoreTotals($regenerated);
        $this->assertSame(0, (int) $regenerated->status);

        $latest = $con->latest($ids['main_company_id'], 2026, 8);
        $this->assertNotNull($latest);
        $this->assertSame((int) $regenerated->id, (int) $latest->id);
        $this->assertCount(2, $con->list($ids['main_company_id'], 2026, 8));
        // 旧行保持出表态，草稿行可再出表
        $issued2 = $con->issue((int) $regenerated->id);
        $this->assertSame(1, (int) $issued2->status);
    }

    /** 非法抵销拒绝后：latest 草稿金额/行数均未被污染 */
    private function assertEliminationState(int $reportId, string $totalAssets, int $rowCount): void
    {
        $report = Capsule::table('erp_finance_consolidation_report')->where('id', $reportId)->first();
        $this->assertNotNull($report);
        $this->assertSame($totalAssets, $report->total_assets);
        $this->assertSame($rowCount, Capsule::table('erp_finance_elimination_item')
            ->where('report_id', $reportId)->count());
    }

    private function expectThrow(string $message, callable $fn): void
    {
        try {
            $fn();
            $this->fail("预期异常未抛出: {$message}");
        } catch (RuntimeException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }
}
