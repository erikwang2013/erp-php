<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\FinanceLedger;
use app\service\finance\ConsolidationService;
use app\service\finance\LedgerBalanceService;
use app\service\finance\LedgerService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

/**
 * F1/F2 独立验证（tester 自建夹具，与 F12MultiCompanyConsolidationTest 互补）：
 * 组织 code 用 ISOX/ISOY（不动 MAIN/SUBUS），科目/汇率 id 每次运行随机生成并记录清理，
 * 聚焦主覆盖缺口：多账套数据隔离、关账写保护、非法入参拒绝、折算半值舍入。
 * 清理只按本类写入的 code/id 级联删除，绝不 TRUNCATE；金额一律字符串等值断言。
 */
#[Group('integration')]
class F12IsolationGuardTest extends F12MultiCompanyScaffold
{
    /** 本套件组织 code（与父类 MAIN/SUBUS 及默认公司错开） */
    protected const COMPANY_CODES = ['ISOX', 'ISOY'];

    /** @var list<int> 本次运行插入的科目 id（随机生成，tearDown 清理） */
    private array $accountIds = [];

    /** @var list<int> 本次运行插入的汇率 id */
    private array $rateIds = [];

    private LedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ledger = new LedgerService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->accountIds = [];
        $this->rateIds = [];
    }

    /**
     * 本套件清理：仅按 ISOX/ISOY 级联删除 + 本次运行随机写入的科目/汇率/币种行。
     * （不复用父类固定 id 清理，避免触碰其它套件夹具。）
     */
    protected function resetFixtures(): void
    {
        $db = Capsule::connection();
        $companyIds = array_map('intval', $db->table('erp_company')
            ->whereIn('code', self::COMPANY_CODES)->pluck('id')->all());
        $reportIds = $companyIds === [] ? [] : array_map('intval', $db->table('erp_finance_consolidation_report')
            ->whereIn('company_id', $companyIds)->pluck('id')->all());
        $ledgerIds = $companyIds === [] ? [] : array_map('intval', $db->table('erp_finance_ledger')
            ->whereIn('company_id', $companyIds)->pluck('id')->all());
        $voucherIds = $ledgerIds === [] ? [] : array_map('intval', $db->table('erp_finance_voucher')
            ->whereIn('ledger_id', $ledgerIds)->pluck('id')->all());

        $tryDelete = function (string $table, string $column, array $ids): void {
            if ($ids === []) {
                return;
            }
            try {
                Capsule::table($table)->whereIn($column, $ids)->delete();
            } catch (\Throwable) {
                // 清理失败仅记录，不掩盖测试结论
            }
        };
        $tryDelete('erp_finance_elimination_item', 'report_id', $reportIds);
        $tryDelete('erp_finance_consolidation_report', 'id', $reportIds);
        $tryDelete('erp_finance_voucher_item', 'voucher_id', $voucherIds);
        $tryDelete('erp_finance_voucher', 'id', $voucherIds);
        $tryDelete('erp_finance_period', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_balance_sheet', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_profit', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_cash_flow', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_ledger', 'id', $ledgerIds);
        $tryDelete('erp_company', 'id', $companyIds);
        $tryDelete('erp_finance_account', 'id', $this->accountIds);
        $tryDelete('erp_finance_exchange_rate', 'id', $this->rateIds);
        if ($this->insertedCurrencyIds !== []) {
            $tryDelete('erp_finance_currency', 'id', $this->insertedCurrencyIds);
        }
    }

    // ---- 夹具写入（id 随机化；同 code 残留先删，防跨套件 uk 冲突） ----

    /** 插入科目（随机 id，记录待清理；先删同 code/id 残留行以幂等） */
    private function insertAccountFixture(string $code, string $name, int $type): int
    {
        Capsule::table('erp_finance_account')->where('code', $code)->delete();
        $id = random_int(1_000_000, 1_900_000_000);
        $this->insertAccount($id, $code, $name, $type);
        $this->accountIds[] = $id;

        return $id;
    }

    /** 建公司 ISOX/ISOY（CNY 账套），返回默认账套 id */
    private function makeTwoCompanies(): array
    {
        $this->insertCurrency(self::CUR_CNY_ID, 'CNY');
        $x = $this->ledger->createCompany(['code' => 'ISOX', 'name' => '隔离公司X']);
        $y = $this->ledger->createCompany(['code' => 'ISOY', 'name' => '隔离公司Y']);
        $xLedger = FinanceLedger::where('company_id', (int) $x->id)->where('is_default', 1)->first();
        $yLedger = FinanceLedger::where('company_id', (int) $y->id)->where('is_default', 1)->first();
        $this->assertNotNull($xLedger, 'ISOX 默认账套缺失');
        $this->assertNotNull($yLedger, 'ISOY 默认账套缺失');

        return [(int) $xLedger->id, (int) $yLedger->id];
    }

    /** 期末汇率（from→to；随机 id 记录清理） */
    private function insertRateFixture(int $fromCurrencyId, int $toCurrencyId, string $rate, string $effectiveDate): void
    {
        $id = random_int(1_000_000, 1_900_000_000);
        Capsule::table('erp_finance_exchange_rate')->insert([
            'id' => $id,
            'from_currency_id' => $fromCurrencyId,
            'to_currency_id' => $toCurrencyId,
            'rate' => $rate,
            'effective_date' => $effectiveDate,
        ]);
        $this->rateIds[] = $id;
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

    // ---- 用例 ----

    /** 多账套数据隔离：X 组织的凭证不得进入 Y 的单体报表，也不得进入未请求它的合并口径 */
    public function testCrossLedgerDataIsolation(): void
    {
        [$xLedgerId, $yLedgerId] = $this->makeTwoCompanies();
        $bankId = $this->insertAccountFixture('1002', '银行存款', 1);
        $capitalId = $this->insertAccountFixture('3001', '实收资本', 3);
        $revenueId = $this->insertAccountFixture('4001', '主营业务收入', 4);
        $this->makeAuditedVoucher($xLedgerId, '2026-08-20', 'iso-x-1', [
            ['account_id' => $bankId, 'debit_amount' => '500.00', 'summary' => '注册资本入账'],
            ['account_id' => $capitalId, 'credit_amount' => '200.00', 'summary' => '注册资本入账'],
            ['account_id' => $revenueId, 'credit_amount' => '300.00', 'summary' => '主营收入'],
        ]);
        // Y 账套一条凭证都没有

        $bs = new LedgerBalanceService();
        // X 单体实时口径：只含 X 凭证
        $xBs = $bs->computeBalanceSheet($xLedgerId, 2026, 8);
        $this->assertSame('500.00', $xBs['total_assets']);
        $this->assertSame('0.00', $xBs['total_liabilities']);
        $this->assertSame('500.00', $xBs['total_equity']);
        // Y 单体：全零，X 的 500 未泄漏
        $yBs = $bs->computeBalanceSheet($yLedgerId, 2026, 8);
        $this->assertSame('0.00', $yBs['total_assets']);
        $this->assertSame('0.00', $yBs['total_liabilities']);
        $this->assertSame('0.00', $yBs['total_equity']);
        $xPl = $bs->computeProfit($xLedgerId, 2026, 8);
        $this->assertSame('300.00', $xPl['revenue']);
        $this->assertSame('300.00', $xPl['profit']);
        $yPl = $bs->computeProfit($yLedgerId, 2026, 8);
        $this->assertSame('0.00', $yPl['revenue']);
        $this->assertSame('0.00', $yPl['profit']);

        // 合并口径隔离：consolidate 只加总显式请求的账套
        $con = new ConsolidationService();
        $onlyY = $con->consolidate([
            ['ledger_id' => $yLedgerId, 'report_year' => 2026, 'report_month' => 8],
        ], 'CNY');
        $this->assertSame('0.00', $onlyY['total_assets']);
        $this->assertSame('0.00', $onlyY['revenue']);
        $this->assertSame('0.00', $onlyY['net_profit']);
        $onlyX = $con->consolidate([
            ['ledger_id' => $xLedgerId, 'report_year' => 2026, 'report_month' => 8],
        ], 'CNY');
        $this->assertSame('500.00', $onlyX['total_assets']);
        $this->assertSame('500.00', $onlyX['total_equity']);
        $this->assertSame('300.00', $onlyX['revenue']);
        $this->assertSame('300.00', $onlyX['net_profit']);
        $both = $con->consolidate([
            ['ledger_id' => $xLedgerId, 'report_year' => 2026, 'report_month' => 8],
            ['ledger_id' => $yLedgerId, 'report_year' => 2026, 'report_month' => 8],
        ], 'CNY');
        $this->assertSame('500.00', $both['total_assets'], 'X+Y 合并与 X 单独一致（Y 为空）');
        $this->assertCount(2, $both['report_data']['subsidiaries']);
    }

    /** 期间写保护：关账只锁该账套该期间，期间外（同账套下一开账月）仍可记账 */
    public function testClosedPeriodBlocksPostingOnlyInThatPeriod(): void
    {
        [$xLedgerId, $yLedgerId] = $this->makeTwoCompanies();
        $bankId = $this->insertAccountFixture('1002', '银行存款', 1);
        $capitalId = $this->insertAccountFixture('3001', '实收资本', 3);
        $this->makeAuditedVoucher($xLedgerId, '2026-08-15', 'iso-x-aug', [
            ['account_id' => $bankId, 'debit_amount' => '500.00', 'summary' => '资本入账'],
            ['account_id' => $capitalId, 'credit_amount' => '500.00', 'summary' => '资本入账'],
        ]);
        $this->ledger->openPeriod($xLedgerId, '2026-08');
        $this->ledger->closePeriod($xLedgerId, '2026-08');

        // 同期间再记账 → 拒绝（createVoucher 的 assertPeriodOpen 守卫）
        $de = new \app\service\finance\DoubleEntryService();
        $this->expectThrow('2026-08 期间已关账，不能记账或审核', function () use ($de, $xLedgerId, $bankId, $capitalId): void {
            $de->createVoucher(['voucher_date' => '2026-08-25', 'remark' => '关账后补记'], [
                ['account_id' => $bankId, 'debit_amount' => '10.00', 'summary' => 'x'],
                ['account_id' => $capitalId, 'credit_amount' => '10.00', 'summary' => 'x'],
            ], $xLedgerId);
        });
        // 重复关账 → 拒绝
        $this->expectThrow('期间不存在或已关账', fn () => $this->ledger->closePeriod($xLedgerId, '2026-08'));
        // 期间外（ISOX 自动开账的 2026-09）照常记账+审核；Y 的 2026-08 也未受影响（宽松期放行）
        $v = $de->createVoucher(['voucher_date' => '2026-09-05', 'remark' => '下月凭证'], [
            ['account_id' => $bankId, 'debit_amount' => '20.00', 'summary' => 'y'],
            ['account_id' => $capitalId, 'credit_amount' => '20.00', 'summary' => 'y'],
        ], $xLedgerId);
        $de->audit((int) $v->id);
        $fresh = Capsule::table('erp_finance_voucher')->where('id', $v->id)->first();
        $this->assertSame(1, (int) $fresh->status, '2026-09 凭证应可审核');
        $this->makeAuditedVoucher($yLedgerId, '2026-08-10', 'iso-y-aug', [
            ['account_id' => $bankId, 'debit_amount' => '30.00', 'summary' => 'z'],
            ['account_id' => $capitalId, 'credit_amount' => '30.00', 'summary' => 'z'],
        ]);
    }

    /** 关账前置校验：期间内存在未审核凭证则拒绝关账；审核后关账成功并固化快照 */
    public function testClosePeriodRejectsUnauditedDraftThenSucceedsAfterAudit(): void
    {
        [$xLedgerId] = $this->makeTwoCompanies();
        $bankId = $this->insertAccountFixture('1002', '银行存款', 1);
        $capitalId = $this->insertAccountFixture('3001', '实收资本', 3);
        $revenueId = $this->insertAccountFixture('4001', '主营业务收入', 4);
        $this->ledger->openPeriod($xLedgerId, '2026-08');
        $de = new \app\service\finance\DoubleEntryService();
        $draft = $de->createVoucher(['voucher_date' => '2026-08-10', 'remark' => '未审草稿'], [
            ['account_id' => $bankId, 'debit_amount' => '500.00', 'summary' => '资本入账'],
            ['account_id' => $capitalId, 'credit_amount' => '200.00', 'summary' => '资本入账'],
            ['account_id' => $revenueId, 'credit_amount' => '300.00', 'summary' => '主营收入'],
        ], $xLedgerId);

        // 1 张未审核 → 关账被拒并点名数量
        $this->expectThrow('2026-08 期间存在 1 张未审核凭证，请先审核或删除', function () use ($xLedgerId): void {
            $this->ledger->closePeriod($xLedgerId, '2026-08');
        });
        // 期间保持开账态，快照未落库
        $fp = Capsule::table('erp_finance_period')->where('ledger_id', $xLedgerId)->where('period', '2026-08')->first();
        $this->assertSame(0, (int) $fp->status);
        $this->assertSame(0, Capsule::table('erp_finance_balance_sheet')
            ->where('ledger_id', $xLedgerId)->where('report_year', 2026)->where('report_month', 8)->count());

        // 审核后关账成功：期间置 1、快照行落库（资产 500/权益 500，收入 300 计入损益）
        $de->audit((int) $draft->id);
        $this->ledger->closePeriod($xLedgerId, '2026-08');
        $fp2 = Capsule::table('erp_finance_period')->where('ledger_id', $xLedgerId)->where('period', '2026-08')->first();
        $this->assertSame(1, (int) $fp2->status);
        $this->assertNotNull($fp2->closed_at);
        $bsRow = Capsule::table('erp_finance_balance_sheet')
            ->where('ledger_id', $xLedgerId)->where('report_year', 2026)->where('report_month', 8)->first();
        $this->assertNotNull($bsRow, '关账应固化资产负债表快照');
        $this->assertSame('500.00', $bsRow->total_assets);
        $this->assertSame('500.00', $bsRow->total_equity);
        $plRow = Capsule::table('erp_finance_profit')
            ->where('ledger_id', $xLedgerId)->where('year', 2026)->where('month', 8)->first();
        $this->assertNotNull($plRow, '关账应固化利润表快照');
        $this->assertSame('300.00', $plRow->revenue);
        $this->assertSame('300.00', $plRow->profit);
    }

    /** 非法入参拒绝：公司编码/名称/重复编码、期间格式/账套存在性/重复开账 */
    public function testIllegalInputRejection(): void
    {
        $this->insertCurrency(self::CUR_CNY_ID, 'CNY');
        $this->expectThrow('公司名称必填', fn () => $this->ledger->createCompany(['code' => 'ISOZ']));
        $this->expectThrow('公司编码须为 2-50 位字母/数字/_-', function (): void {
            $this->ledger->createCompany(['code' => 'A', 'name' => '单字符公司']);
        });
        $this->expectThrow('公司编码须为 2-50 位字母/数字/_-', function (): void {
            $this->ledger->createCompany(['code' => 'ISO X', 'name' => '含空格公司']);
        });
        $x = $this->ledger->createCompany(['code' => 'ISOX', 'name' => '隔离公司X']);
        // 重复组织编码 → uk_code 拦截并转业务异常
        $this->expectThrow('公司编码已存在', function (): void {
            $this->ledger->createCompany(['code' => 'ISOX', 'name' => '重复公司']);
        });
        $ledger = FinanceLedger::where('company_id', (int) $x->id)->where('is_default', 1)->first();
        $this->assertNotNull($ledger);

        $this->expectThrow('期间格式须为 YYYY-MM', fn () => $this->ledger->openPeriod((int) $ledger->id, '2026-13'));
        $this->expectThrow('期间格式须为 YYYY-MM', fn () => $this->ledger->openPeriod((int) $ledger->id, '2026-8'));
        $this->expectThrow('账套不存在', fn () => $this->ledger->openPeriod(9_999_999_999, '2026-08'));
        // createCompany 已自动开当前自然月 → 再开同月拒绝
        $current = date('Y-m');
        $this->expectThrow('该期间已存在', fn () => $this->ledger->openPeriod((int) $ledger->id, $current));
        // 正常开一个历史月成功
        $this->ledger->openPeriod((int) $ledger->id, '2026-08');
        $this->assertSame(1, Capsule::table('erp_finance_period')
            ->where('ledger_id', $ledger->id)->where('period', '2026-08')->count());
    }

    /** 外币折算：USD 收入 1.00 × 期末汇率 7.155 落在半值边界 → bc 半值进位 7.16（非截断 7.15） */
    public function testFxConsolidationHalfUpRounding(): void
    {
        $usdId = $this->insertCurrency(self::CUR_USD_ID, 'USD');
        $cnyId = $this->insertCurrency(self::CUR_CNY_ID, 'CNY');
        $y = $this->ledger->createCompany(['code' => 'ISOY', 'name' => '折算公司Y', 'base_currency' => 'USD']);
        $yLedger = FinanceLedger::where('company_id', (int) $y->id)->where('is_default', 1)->first();
        $this->assertNotNull($yLedger, 'ISOY 默认账套缺失');
        $bankId = $this->insertAccountFixture('1002', '银行存款', 1);
        $revenueId = $this->insertAccountFixture('4001', '主营业务收入', 4);
        $this->insertRateFixture($usdId, $cnyId, '7.155000', '2026-09-30');
        $this->makeAuditedVoucher((int) $yLedger->id, '2026-09-20', 'iso-y-usd', [
            ['account_id' => $bankId, 'debit_amount' => '1.00', 'summary' => 'USD 收入'],
            ['account_id' => $revenueId, 'credit_amount' => '1.00', 'summary' => 'USD 收入'],
        ]);

        $out = (new ConsolidationService())->consolidate([
            ['ledger_id' => (int) $yLedger->id, 'report_year' => 2026, 'report_month' => 9],
        ], 'CNY');
        $row = $out['report_data']['subsidiaries'][0];
        $this->assertSame('7.155000', $row['rate'], '期末 2026-09-30 汇率生效');
        // 手算：1.00 × 7.155 = 7.155 → 2 位半值进位 = 7.16（与项目 bc_round 半值约定一致；
        // 明细行金额为折算后本位币口径，与 SUBUS 净利 -710.00(-100USD×7.1) 同构）
        $this->assertSame('7.16', $row['revenue'], '明细行收入为折算后 CNY 口径');
        $this->assertSame('7.16', $out['total_assets']);
        $this->assertSame('0.00', $out['total_liabilities']);
        $this->assertSame('7.16', $out['total_equity']);
        $this->assertSame('7.16', $out['revenue']);
        $this->assertSame('7.16', $out['net_profit']);
    }
}
