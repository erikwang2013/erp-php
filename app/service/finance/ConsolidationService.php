<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\Company;
use app\model\FinanceAccount;
use app\model\FinanceBalanceSheet;
use app\model\FinanceConsolidationReport;
use app\model\FinanceCurrency;
use app\model\FinanceEliminationItem;
use app\model\FinanceExchangeRate;
use app\model\FinanceLedger;
use app\model\FinanceProfit;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;

/**
 * 集团合并报表服务（F2）——多币种折算 + 简单汇总 + 手工抵销。
 *
 * 两条使用面：
 *  1) consolidate()：纯内存一次合并（legacy 契约，ReportController 用）。
 *     输入每项为 DB 引用 {ledger_id|company_id, report_year, report_month}，
 *     服务内解析账套 → 快照优先、无快照按已审核凭证实时重算 → 期末汇率折算
 *     → 按科目汇总。
 *  2) generateDraft/latest/list/addElimination/issue()：F2 持久化工作流，
 *     合并结果落 erp_finance_consolidation_report（同 (company_id, year, month)
 *     可多版本，最新 created_at 行为当前草稿），抵销行落 elimination_item。
 *
 * 折算口径：
 *  · 汇率：同币种短路 1；否则查 erp_finance_exchange_rate（from→to、effective_date
 *    ≤ 期末，取最近一条）；缺失抛业务异常。金额一律 bcmath 字符串 6dp 累加，
 *    仅汇总行 bc_round 2dp。
 *  · 权益：Σ 各子公司折算后资产负债表 equity（单体 equity 已含期末累计损益），
 *    不再叠加 Δnet_profit。
 *  · 手工抵销：addElimination 整批借贷平衡（差 ≤0.001）；每行按科目类型映射到
 *    报表行——资产 借-贷、负债/权益/收入 贷-借、费用 借-贷（抵销净资产/往来类
 *    分录可维持 资产-负债-权益=0）；重算一律以 report_data.base_totals 为底，
 *    幂等可重复执行。
 *  · 版本策略：出表(issue)后如需修订须重新 generateDraft 生成新草稿。
 */
class ConsolidationService
{
    /** 抵销借贷平衡容差 */
    private const ELIMINATION_TOLERANCE = '0.001';

    private LedgerBalanceService $balance;

    public function __construct(?LedgerBalanceService $balance = null)
    {
        $this->balance = $balance ?? new LedgerBalanceService();
    }

    /**
     * 多币种报表合并（legacy，纯内存）：输入子公司报表引用，输出折算后汇总。
     *
     * @param array<int, array{ledger_id?:int, company_id?:int, report_year:int, report_month:int}> $subsidiaryReports
     */
    public function consolidate(array $subsidiaryReports, string $baseCurrency = 'CNY'): array
    {
        if ($subsidiaryReports === []) {
            throw new RuntimeException('subsidiary_reports 不能为空');
        }
        $base = strtoupper(trim($baseCurrency));
        if ($base === '') {
            $base = 'CNY';
        }

        $year = $month = null;
        $lines = [];
        foreach ($subsidiaryReports as $i => $item) {
            if (!is_array($item)) {
                throw new RuntimeException('subsidiary_reports[' . $i . '] 必须为对象');
            }
            $ledger = $this->resolveLedger($item, 'subsidiary_reports[' . $i . ']');
            $y = (int) ($item['report_year'] ?? 0);
            $m = (int) ($item['report_month'] ?? 0);
            if ($y < 2000 || $m < 1 || $m > 12) {
                throw new RuntimeException('subsidiary_reports[' . $i . '] 缺少合法的 report_year/report_month');
            }
            if ($year === null) {
                $year = $y;
                $month = $m;
            } elseif ($y !== $year || $m !== $month) {
                throw new RuntimeException('合并报表各子公司期间须一致');
            }
            $lines[] = $this->translateLedger($ledger, $y, $m, $base);
        }

        $totalAssets = $totalLiabilities = $totalEquity = '0';
        $revenue = $netProfit = '0';
        foreach ($lines as $line) {
            $totalAssets = bcadd($totalAssets, $line['total_assets'], 6);
            $totalLiabilities = bcadd($totalLiabilities, $line['total_liabilities'], 6);
            $totalEquity = bcadd($totalEquity, $line['total_equity'], 6);
            $revenue = bcadd($revenue, $line['revenue'], 6);
            $netProfit = bcadd($netProfit, $line['net_profit'], 6);
        }

        return [
            'base_currency' => $base,
            'report_year' => $year,
            'report_month' => $month,
            'total_assets' => bc_round($totalAssets, 2),
            'total_liabilities' => bc_round($totalLiabilities, 2),
            'total_equity' => bc_round($totalEquity, 2),
            'revenue' => bc_round($revenue, 2),
            'net_profit' => bc_round($netProfit, 2),
            'report_data' => [
                'generated_from' => 'consolidated',
                'base_currency' => $base,
                'subsidiaries' => $lines,
            ],
        ];
    }

    /**
     * 生成合并报表草稿（新版本）：合并主体(公司自身) + 直接子公司（parent_id=主体）
     * 的默认账套入范围；base_currency 缺省取合并主体本位币。
     */
    public function generateDraft(int $companyId, int $year, int $month, string $baseCurrency = ''): FinanceConsolidationReport
    {
        $company = Company::find($companyId);
        if (!$company) {
            throw new RuntimeException('合并主体公司不存在');
        }
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('月份必须在1-12之间');
        }
        $base = strtoupper(trim($baseCurrency));
        if ($base === '') {
            $base = strtoupper((string) ($company->base_currency ?: 'CNY'));
        }

        // 范围 = 自身 + 直接子公司（全部经默认账套入范围）
        $ids = [$companyId];
        foreach (Company::where('parent_id', $companyId)->where('status', 1)->get() as $child) {
            $ids[] = (int) $child->id;
        }
        $items = [];
        foreach ($ids as $cid) {
            $ledger = FinanceLedger::where('company_id', $cid)->where('is_default', 1)->first();
            if (!$ledger || (int) $ledger->status !== 1) {
                $c = Company::find($cid);
                throw new RuntimeException('公司 ' . ($c->name ?? $cid) . ' 未设置启用的默认账套，无法合并');
            }
            $items[] = ['ledger_id' => (int) $ledger->id, 'report_year' => $year, 'report_month' => $month];
        }
        $consolidated = $this->consolidate($items, $base);

        $report = new FinanceConsolidationReport();
        $report->id = SnowflakeService::generate();
        $report->company_id = $companyId;
        $report->report_year = $year;
        $report->report_month = $month;
        $report->base_currency = $base;
        $report->status = 0;
        $report->total_assets = $consolidated['total_assets'];
        $report->total_liabilities = $consolidated['total_liabilities'];
        $report->total_equity = $consolidated['total_equity'];
        $report->revenue = $consolidated['revenue'];
        $report->net_profit = $consolidated['net_profit'];
        $report->report_data = $consolidated['report_data'] + [
            'base_totals' => [
                'total_assets' => $consolidated['total_assets'],
                'total_liabilities' => $consolidated['total_liabilities'],
                'total_equity' => $consolidated['total_equity'],
                'revenue' => $consolidated['revenue'],
                'net_profit' => $consolidated['net_profit'],
            ],
            'eliminations' => [],
        ];
        $report->save();

        return $report;
    }

    /** 当前草稿/最新版本：同 (company_id, year, month) 取 created_at 最新行，无则 null */
    public function latest(int $companyId, int $year, int $month): ?FinanceConsolidationReport
    {
        return FinanceConsolidationReport::where('company_id', $companyId)
            ->where('report_year', $year)->where('report_month', $month)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->first();
    }

    /** 版本列表：同 (company_id, year, month) 的全部历史行（新→旧） */
    public function list(int $companyId, int $year, int $month): array
    {
        return FinanceConsolidationReport::where('company_id', $companyId)
            ->where('report_year', $year)->where('report_month', $month)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()->all();
    }

    /**
     * 批量追加手工抵销分录（整批借贷平衡，差 ≤0.001）。
     * 仅最新草稿可编辑；每次调用各插一行 erp_finance_elimination_item（追加语义，非幂等覆盖），
     * 重算以 base_totals 为底并把全部抵销行按序平铺进 report_data.eliminations。
     *
     * @param array<int, array{account_code:string, summary?:string, debit_amount?:string, credit_amount?:string}> $rows
     */
    public function addElimination(int $reportId, array $rows): FinanceConsolidationReport
    {
        $report = FinanceConsolidationReport::find($reportId);
        if (!$report) {
            throw new RuntimeException('合并报表不存在');
        }
        if ((int) $report->status !== 0) {
            throw new RuntimeException('报表已出表，抵销分录不可修改；如需修订请重新生成草稿');
        }
        if ($rows === []) {
            throw new RuntimeException('抵销分录不能为空');
        }

        $totalDebit = $totalCredit = '0';
        $codes = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                throw new RuntimeException('eliminations[' . $i . '] 必须为对象');
            }
            $code = trim((string) ($row['account_code'] ?? ''));
            if ($code === '') {
                throw new RuntimeException('eliminations[' . $i . '] 缺少 account_code');
            }
            $codes[$code] = true;
            $debit = bc_norm((string) ($row['debit_amount'] ?? '0'));
            $credit = bc_norm((string) ($row['credit_amount'] ?? '0'));
            if (bccomp($debit, '0', 2) === 0 && bccomp($credit, '0', 2) === 0) {
                throw new RuntimeException('eliminations[' . $i . '] 借贷金额不能同时为 0');
            }
            $rows[$i]['account_code'] = $code;
            $rows[$i]['debit_amount'] = $debit;
            $rows[$i]['credit_amount'] = $credit;
            $rows[$i]['summary'] = trim((string) ($row['summary'] ?? ''));
            $totalDebit = bcadd($totalDebit, $debit, 6);
            $totalCredit = bcadd($totalCredit, $credit, 6);
        }
        if (bccomp(bcsub($totalDebit, $totalCredit, 6), self::ELIMINATION_TOLERANCE, 3) === 1
            || bccomp(bcsub($totalCredit, $totalDebit, 6), self::ELIMINATION_TOLERANCE, 3) === 1) {
            throw new RuntimeException('抵销分录借贷不平衡：借 ' . bc_round($totalDebit, 2)
                . ' ≠ 贷 ' . bc_round($totalCredit, 2));
        }

        // 科目类型权威校验：编码必须真实存在于 erp_finance_account
        $types = [];
        foreach (FinanceAccount::whereIn('code', array_keys($codes))->get() as $account) {
            $types[$account->code] = (int) $account->type;
        }
        foreach ($rows as $row) {
            if (!isset($types[$row['account_code']])) {
                throw new RuntimeException('抵销科目不存在：' . $row['account_code']);
            }
        }

        DB::transaction(function () use ($report, $rows): void {
            foreach ($rows as $row) {
                $item = new FinanceEliminationItem();
                $item->id = SnowflakeService::generate();
                $item->report_id = (int) $report->id;
                $item->account_code = $row['account_code'];
                $item->summary = $row['summary'];
                $item->debit_amount = $row['debit_amount'];
                $item->credit_amount = $row['credit_amount'];
                $item->save();
            }
            $this->recomputeReport($report);
        });

        return $report->fresh();
    }

    /** 出表：草稿置 1 + 记录出表时间；重复出表拒绝 */
    public function issue(int $reportId): FinanceConsolidationReport
    {
        $report = FinanceConsolidationReport::find($reportId);
        if (!$report) {
            throw new RuntimeException('合并报表不存在');
        }
        if ((int) $report->status === 1) {
            throw new RuntimeException('报表已出，不能重复出表');
        }
        $report->status = 1;
        $report->issued_at = date('Y-m-d H:i:s');
        $report->save();

        return $report;
    }

    // ---------------------------------------------------------------- private

    /** 解析报表项 → 账套（ledger_id 优先，其次 company_id 的默认账套） */
    private function resolveLedger(array $item, string $where): FinanceLedger
    {
        $ledgerId = (int) ($item['ledger_id'] ?? 0);
        $ledger = $ledgerId > 0
            ? FinanceLedger::find($ledgerId)
            : null;
        if (!$ledger && !empty($item['company_id'])) {
            $ledger = FinanceLedger::where('company_id', (int) $item['company_id'])
                ->where('is_default', 1)->first();
        }
        if (!$ledger || (int) $ledger->status !== 1) {
            throw new RuntimeException($where . ' 指向的账套不存在或已停用');
        }

        return $ledger;
    }

    /**
     * 单账套折算：快照优先 → 实时重算兜底 → 期末汇率折算。
     *
     * @return array{ledger_id:int, company_id:int, code:string, name:string, currency:string,
     *               rate:string, source:string, total_assets:string, total_liabilities:string,
     *               total_equity:string, revenue:string, net_profit:string}
     */
    private function translateLedger(FinanceLedger $ledger, int $year, int $month, string $baseCurrency): array
    {
        [$assets, $liabilities, $equity] = ['0', '0', '0'];
        [$revenue, $profit] = ['0', '0'];
        $source = 'snapshot';

        $snapBs = FinanceBalanceSheet::where('ledger_id', $ledger->id)
            ->where('report_year', $year)->where('report_month', $month)->first();
        if ($snapBs) {
            $assets = bc_norm((string) $snapBs->total_assets);
            $liabilities = bc_norm((string) $snapBs->total_liabilities);
            $equity = bc_norm((string) $snapBs->total_equity);
            $snapPl = FinanceProfit::where('ledger_id', $ledger->id)
                ->where('year', $year)->where('month', $month)->first();
            if ($snapPl) {
                $revenue = bc_norm((string) $snapPl->revenue);
                $profit = bc_norm((string) $snapPl->profit);
            }
        } else {
            $bs = $this->balance->computeBalanceSheet((int) $ledger->id, $year, $month);
            $pl = $this->balance->computeProfit((int) $ledger->id, $year, $month);
            $assets = $bs['total_assets'];
            $liabilities = $bs['total_liabilities'];
            $equity = $bs['total_equity'];
            $revenue = $pl['revenue'];
            $profit = $pl['profit'];
            $source = 'live';
        }

        $rate = $this->rateToBase((string) ($ledger->currency ?: 'CNY'), $baseCurrency, $year, $month);
        $company = Company::find((int) $ledger->company_id);

        return [
            'ledger_id' => (int) $ledger->id,
            'company_id' => (int) $ledger->company_id,
            'code' => (string) ($company->code ?? $ledger->code),
            'name' => (string) ($company->name ?? $ledger->name),
            'currency' => strtoupper((string) ($ledger->currency ?: 'CNY')),
            'rate' => $rate,
            'source' => $source,
            'total_assets' => $this->mulRound($assets, $rate),
            'total_liabilities' => $this->mulRound($liabilities, $rate),
            'total_equity' => $this->mulRound($equity, $rate),
            'revenue' => $this->mulRound($revenue, $rate),
            'net_profit' => $this->mulRound($profit, $rate),
        ];
    }

    /** 期末汇率：同币种短路 1；否则取 effective_date ≤ 期末的最近一条 from→to */
    private function rateToBase(string $fromCurrency, string $toCurrency, int $year, int $month): string
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);
        if ($from === $to) {
            return '1';
        }
        $fromModel = FinanceCurrency::where('code', $from)->first();
        $toModel = FinanceCurrency::where('code', $to)->first();
        if (!$fromModel || !$toModel) {
            throw new RuntimeException('币种不存在：' . ($fromModel ? $to : $from));
        }
        $periodEnd = sprintf(
            '%04d-%02d-%02d',
            $year,
            $month,
            (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)))
        );
        $rate = FinanceExchangeRate::where('from_currency_id', (int) $fromModel->id)
            ->where('to_currency_id', (int) $toModel->id)
            ->where('effective_date', '<=', $periodEnd)
            ->orderByDesc('effective_date')
            ->first();
        if (!$rate) {
            throw new RuntimeException(sprintf('缺少 %04d-%02d 期末汇率 %s→%s', $year, $month, $from, $to));
        }

        return bc_norm((string) $rate->rate);
    }

    /** 金额 × 汇率，6dp 累加后 2dp 展示 */
    private function mulRound(string $amount, string $rate): string
    {
        return bc_round(bcmul(bc_norm($amount), bc_norm($rate), 6), 2);
    }

    /** 抵销行 → 报表行映射增量（科目类型：资产 借-贷；负债/权益/收入 贷-借；费用 借-贷） */
    private function lineDelta(int $type, string $debit, string $credit): array
    {
        $dc = bcsub($debit, $credit, 6);
        $cd = bcsub($credit, $debit, 6);
        $row = ['assets' => '0', 'liabilities' => '0', 'equity' => '0', 'revenue' => '0', 'expense' => '0'];
        switch ($type) {
            case 1:
                $row['assets'] = $dc;
                break;
            case 2:
                $row['liabilities'] = $cd;
                break;
            case 3:
                $row['equity'] = $cd;
                break;
            case 4:
                $row['revenue'] = $cd;
                break;
            case 5:
                $row['expense'] = $dc;
                break;
        }

        return $row;
    }

    /**
     * 以 base_totals 为底重算报表汇总（读库内全部抵销行，幂等）。
     * 净损益调整 = 收入增量 − 费用增量。
     */
    private function recomputeReport(FinanceConsolidationReport $report): void
    {
        $base = is_array($report->report_data) ? ($report->report_data['base_totals'] ?? []) : [];
        $totals = [
            'total_assets' => bc_norm((string) ($base['total_assets'] ?? '0')),
            'total_liabilities' => bc_norm((string) ($base['total_liabilities'] ?? '0')),
            'total_equity' => bc_norm((string) ($base['total_equity'] ?? '0')),
            'revenue' => bc_norm((string) ($base['revenue'] ?? '0')),
            'net_profit' => bc_norm((string) ($base['net_profit'] ?? '0')),
        ];

        $types = [];
        $list = [];
        foreach (FinanceEliminationItem::where('report_id', (int) $report->id)->get() as $item) {
            $types[$item->account_code] ??= (int) (FinanceAccount::where('code', $item->account_code)
                ->value('type') ?? 0);
            $list[] = [
                'account_code' => (string) $item->account_code,
                'summary' => (string) $item->summary,
                'debit_amount' => bc_norm((string) $item->debit_amount),
                'credit_amount' => bc_norm((string) $item->credit_amount),
            ];
        }

        $revenueDelta = $expenseDelta = '0';
        foreach ($list as $row) {
            $delta = $this->lineDelta($types[$row['account_code']], $row['debit_amount'], $row['credit_amount']);
            $totals['total_assets'] = bcadd($totals['total_assets'], $delta['assets'], 6);
            $totals['total_liabilities'] = bcadd($totals['total_liabilities'], $delta['liabilities'], 6);
            $totals['total_equity'] = bcadd($totals['total_equity'], $delta['equity'], 6);
            $revenueDelta = bcadd($revenueDelta, $delta['revenue'], 6);
            $expenseDelta = bcadd($expenseDelta, $delta['expense'], 6);
        }
        $totals['revenue'] = bcadd($totals['revenue'], $revenueDelta, 6);
        // 净损益调整 = 收入增量 − 费用增量（base.net_profit 已含合并时点的收入费用）
        $totals['net_profit'] = bcadd(bcadd($totals['net_profit'], $revenueDelta, 6), bcsub('0', $expenseDelta, 6), 6);

        $report->total_assets = bc_round($totals['total_assets'], 2);
        $report->total_liabilities = bc_round($totals['total_liabilities'], 2);
        $report->total_equity = bc_round($totals['total_equity'], 2);
        $report->revenue = bc_round($totals['revenue'], 2);
        $report->net_profit = bc_round($totals['net_profit'], 2);
        $data = $report->report_data ?: [];
        $data['eliminations'] = $list;
        $report->report_data = $data;
        $report->save();
    }
}
