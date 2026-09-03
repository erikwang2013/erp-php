<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\model\FinanceCashFlow;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 账套余额计算服务（F1）——三张单体报表按账套从已审核凭证实时重算。
 *
 * 数据源：erp_finance_voucher(status=1 且未删除) + voucher_item + account。
 * 金额一律 bcmath 字符串，SQL SUM 的 DECIMAL 直取不做 float。
 *
 * 口径约定：
 *  · 科目类型 type：1资产 2负债 3权益 4收入 5费用（erp_finance_account.type 为权威，
 *    不再按 account_id 数值区间猜——旧总账表回退即为该 bug，见 BalanceSheetController）。
 *  · 流动/非流动拆分沿用旧报表区间（对科目编码整数部分）：
 *    资产 1000-1499 流动 / 1500+ 非流动；负债 2000-2499 流动 / 2500+ 非流动。
 *  · 权益 = type3 净额 + 截至期末累计损益（简单单体口径，未单独做年结结转分录）。
 *  · 现金流量：现金类科目（1001 现金 / 1002 银行 / 1012 其他货币资金及下级）的凭证
 *    移动实时归类，对方科目按编码区间映射经营/投资/筹资。
 *    ponytail: 直接法启发式映射；待现金日记账带 activity 维度后替换。
 *  · 期初现金：优先取同账套上期快照 ending_cash，无快照则按期末前累计现金余额计算。
 *  · 余额采用记账方向带符号累加（资产=借-贷、负债/权益/收入=贷-借口径翻转在报表层），
 *    借贷平衡的凭证集保证 资产-负债-权益=0 恒成立（含计入权益的累计损益）。
 */
class LedgerBalanceService
{
    /** 现金类科目编码（整数段前缀，含其下级如 1002.01） */
    private const CASH_CODES = [1001, 1002, 1012];

    /** 报表期范围 [start, end]（YYYY-MM-DD） */
    private static function periodRange(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        return [$start, $end];
    }

    /** 科目编码整数段（'1001.01' → 1001），非数字返回 null */
    private static function codePrefix(string $code): ?int
    {
        return preg_match('/^(\d+)/', $code, $m) === 1 ? (int) $m[1] : null;
    }

    private static function isCashAccount(string $code): bool
    {
        $prefix = self::codePrefix($code);

        return $prefix !== null && in_array($prefix, self::CASH_CODES, true);
    }

    /**
     * 取某账套某科目区间在指定日前的凭证分录，按科目聚合带符号净额。
     *
     * @return array<int, array{code:string, name:string, type:int, net:string}> 按 account_id 索引
     */
    private static function accountNets(int $ledgerId, string $beforeDate, array $types = []): array
    {
        $sql = 'SELECT i.account_id, a.code, a.name, a.type, '
            . '(COALESCE(SUM(i.debit_amount),0) - COALESCE(SUM(i.credit_amount),0)) AS net '
            . 'FROM erp_finance_voucher_item i '
            . 'JOIN erp_finance_voucher v ON v.id = i.voucher_id AND v.status = 1 AND v.deleted_at IS NULL '
            . 'JOIN erp_finance_account a ON a.id = i.account_id '
            . 'WHERE v.ledger_id = ? AND v.voucher_date <= ?';
        $binds = [$ledgerId, $beforeDate];
        if ($types !== []) {
            $sql .= ' AND a.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            $binds = array_merge($binds, $types);
        }
        $sql .= ' GROUP BY i.account_id, a.code, a.name, a.type';

        $rows = [];
        foreach (DB::select($sql, $binds) as $row) {
            $rows[(int) $row->account_id] = [
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (int) $row->type,
                'net' => bc_norm((string) $row->net),
            ];
        }

        return $rows;
    }

    /**
     * 资产负债表（实时，账套维度）：截至期末的全部已审核分录按科目口径汇总。
     */
    public function computeBalanceSheet(int $ledgerId, int $year, int $month): array
    {
        [, $end] = self::periodRange($year, $month);
        $rows = self::accountNets($ledgerId, $end, [1, 2, 3]);

        $totalAssets = $totalLiabilities = $totalEquity = '0';
        $currentAssets = $nonCurrentAssets = '0';
        $currentLiabilities = $nonCurrentLiabilities = '0';
        $lines = [];

        foreach ($rows as $accountId => $row) {
            $net = $row['net'];
            $prefix = self::codePrefix($row['code']);
            // 负债/权益为贷方科目，展示口径取反；资产保持借方口径
            switch ($row['type']) {
                case 1:
                    $totalAssets = bcadd($totalAssets, $net, 6);
                    if ($prefix !== null && $prefix >= 1500) {
                        $nonCurrentAssets = bcadd($nonCurrentAssets, $net, 6);
                    } else {
                        $currentAssets = bcadd($currentAssets, $net, 6);
                    }
                    break;
                case 2:
                case 3:
                    // 负债/权益同为贷方科目，展示口径一并取反（权益=type3贷方净额）
                    $creditBalance = bcsub('0', $net, 6);
                    if ($row['type'] === 2) {
                        $totalLiabilities = bcadd($totalLiabilities, $creditBalance, 6);
                        if ($prefix !== null && $prefix >= 2500) {
                            $nonCurrentLiabilities = bcadd($nonCurrentLiabilities, $creditBalance, 6);
                        } else {
                            $currentLiabilities = bcadd($currentLiabilities, $creditBalance, 6);
                        }
                    } else {
                        $totalEquity = bcadd($totalEquity, $creditBalance, 6);
                    }
                    break;
            }
            $lines[] = [
                'account_id' => $accountId,
                'code' => $row['code'],
                'name' => $row['name'],
                'balance' => bc_round($row['type'] === 1 ? $net : bcsub('0', $net, 4), 2),
            ];
        }

        // 截至期末累计损益并入权益（未年结口径）
        $cumProfit = $this->cumulativeProfit($ledgerId, $end);
        $totalEquity = bcadd($totalEquity, $cumProfit, 6);

        return [
            'report_year' => $year,
            'report_month' => $month,
            'total_assets' => bc_round($totalAssets, 2),
            'total_liabilities' => bc_round($totalLiabilities, 2),
            'total_equity' => bc_round($totalEquity, 2),
            'current_assets' => bc_round($currentAssets, 2),
            'non_current_assets' => bc_round($nonCurrentAssets, 2),
            'current_liabilities' => bc_round($currentLiabilities, 2),
            'non_current_liabilities' => bc_round($nonCurrentLiabilities, 2),
            'report_data' => [
                'generated_from' => 'voucher',
                'lines' => $lines,
            ],
        ];
    }

    /** 累计损益（收入 type4 贷-借 为负的费用直接累加），日期前全部已审核分录 */
    public function cumulativeProfit(int $ledgerId, string $beforeDate): string
    {
        $sql = 'SELECT a.type, '
            . '(COALESCE(SUM(i.credit_amount),0) - COALESCE(SUM(i.debit_amount),0)) AS net '
            . 'FROM erp_finance_voucher_item i '
            . 'JOIN erp_finance_voucher v ON v.id = i.voucher_id AND v.status = 1 AND v.deleted_at IS NULL '
            . 'JOIN erp_finance_account a ON a.id = i.account_id '
            . 'WHERE v.ledger_id = ? AND v.voucher_date <= ? AND a.type IN (4,5) '
            . 'GROUP BY a.type';
        $profit = '0';
        foreach (DB::select($sql, [$ledgerId, $beforeDate]) as $row) {
            // net 统一为贷-借口径：收入正值、费用负值（借-贷=-net），直接累加即 收入−费用
            $profit = bcadd($profit, bc_norm((string) $row->net), 6);
        }

        return $profit;
    }

    /**
     * 利润表（实时，账套维度）：期内收入(type4 贷-借)与费用(type5 借-贷)。
     * cost 与 expense 不再拆分——旧表列保留为 0，费用全额入 expense。
     * ponytail: 拆分需成本类科目区间约定，有需要再加。
     */
    public function computeProfit(int $ledgerId, int $year, int $month): array
    {
        [$start, $end] = self::periodRange($year, $month);
        $sql = 'SELECT i.account_id, a.code, a.name, a.type, '
            . '(COALESCE(SUM(i.credit_amount),0) - COALESCE(SUM(i.debit_amount),0)) AS net '
            . 'FROM erp_finance_voucher_item i '
            . 'JOIN erp_finance_voucher v ON v.id = i.voucher_id AND v.status = 1 AND v.deleted_at IS NULL '
            . 'JOIN erp_finance_account a ON a.id = i.account_id '
            . 'WHERE v.ledger_id = ? AND v.voucher_date BETWEEN ? AND ? AND a.type IN (4,5) '
            . 'GROUP BY i.account_id, a.code, a.name, a.type';

        $revenue = $expense = '0';
        $lines = [];
        foreach (DB::select($sql, [$ledgerId, $start, $end]) as $row) {
            // type4 收入 net=贷-借；type5 费用取反为借-贷正数
            if ((int) $row->type === 4) {
                $revenue = bcadd($revenue, bc_norm((string) $row->net), 6);
                $amount = bc_norm((string) $row->net);
            } else {
                $amount = bcsub('0', bc_norm((string) $row->net), 6);
                $expense = bcadd($expense, $amount, 6);
            }
            $lines[] = [
                'account_id' => (int) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'amount' => bc_round($amount, 2),
            ];
        }

        return [
            'report_year' => $year,
            'report_month' => $month,
            'revenue' => bc_round($revenue, 2),
            'cost' => '0',
            'expense' => bc_round($expense, 2),
            'profit' => bc_round(bcsub($revenue, $expense, 6), 2),
            'report_data' => [
                'generated_from' => 'voucher',
                'lines' => $lines,
            ],
        ];
    }

    /**
     * 现金流量表（实时，账套维度）：现金类科目本期净变动，按对方科目归类。
     * 返回键与 erp_finance_cash_flow 快照列一致（net 为派生列一并给出）。
     */
    public function computeCashFlow(int $ledgerId, int $year, int $month, string $beginningCash = ''): array
    {
        [$start, $end] = self::periodRange($year, $month);
        $sql = 'SELECT i.account_id, a.code, a.type, i.debit_amount, i.credit_amount, i.voucher_id '
            . 'FROM erp_finance_voucher_item i '
            . 'JOIN erp_finance_voucher v ON v.id = i.voucher_id AND v.status = 1 AND v.deleted_at IS NULL '
            . 'JOIN erp_finance_account a ON a.id = i.account_id '
            . 'WHERE v.ledger_id = ? AND v.voucher_date BETWEEN ? AND ? '
            . 'ORDER BY i.voucher_id';
        $rows = DB::select($sql, [$ledgerId, $start, $end]);

        $buckets = ['operating' => '0', 'investing' => '0', 'financing' => '0'];
        foreach ($rows as $row) {
            $prefix = self::codePrefix((string) $row->code);
            if ($prefix === null || self::isCashAccount((string) $row->code)) {
                continue; // 现金科目行不参与对方归类
            }
            // 对方科目 → 桶：收入/费用/流动资产负债 = 经营；非流动资产/长期负债 = 投资/筹资
            $amount = bcsub(bc_norm((string) $row->debit_amount), bc_norm((string) $row->credit_amount), 6);
            $bucket = self::counterpartyBucket((int) $row->type, $prefix);
            $buckets[$bucket] = bcadd($buckets[$bucket], $amount, 6);
        }

        // 桶内净额 = 对方借-贷合计：<0 表示现金流入（对方记贷），>0 现金流出
        $sum = static function (string $bucket) use ($buckets): array {
            $net = $buckets[$bucket];
            $inflow = bccomp($net, '0', 6) === -1 ? bcsub('0', $net, 6) : '0';
            $outflow = bccomp($net, '0', 6) === 1 ? $net : '0';

            return [$inflow, $outflow, $net];
        };
        [$operatingInflow, $operatingOutflow, $operatingNet] = $sum('operating');
        [$investingInflow, $investingOutflow, $investingNet] = $sum('investing');
        [$financingInflow, $financingOutflow, $financingNet] = $sum('financing');

        if ($beginningCash === '') {
            $beginningCash = $this->cashBalanceAt($ledgerId, $start, $start); // voucher_date < 期初
        }
        $totalDelta = bcadd($operatingNet, $investingNet, 6);
        $totalDelta = bcadd($totalDelta, $financingNet, 6);
        $endingCash = bcadd(bc_norm($beginningCash), $totalDelta, 6);

        return [
            'report_year' => $year,
            'report_month' => $month,
            'operating_inflow' => bc_round($operatingInflow, 2),
            'operating_outflow' => bc_round($operatingOutflow, 2),
            'operating_net' => bc_round($operatingNet, 2),
            'investing_inflow' => bc_round($investingInflow, 2),
            'investing_outflow' => bc_round($investingOutflow, 2),
            'investing_net' => bc_round($investingNet, 2),
            'financing_inflow' => bc_round($financingInflow, 2),
            'financing_outflow' => bc_round($financingOutflow, 2),
            'financing_net' => bc_round($financingNet, 2),
            'beginning_cash' => bc_round($beginningCash, 2),
            'ending_cash' => bc_round($endingCash, 2),
            'report_data' => [
                'generated_from' => 'voucher',
                'voucher_count' => count($rows) === 0 ? 0 : count(array_unique(array_column($rows, 'voucher_id'))),
            ],
        ];
    }

    /** 对方科目类型+编码段 → 现金流量桶 */
    private static function counterpartyBucket(int $type, int $prefix): string
    {
        if ($type === 4 || $type === 5 || ($type === 2 && $prefix < 2500) || ($type === 1 && $prefix < 1500)) {
            return 'operating';
        }
        if ($type === 1) {
            return 'investing'; // 1500+ 长期资产
        }

        return 'financing'; // type2 2500+ 长期负债 / type3 权益
    }

    /** 某时点前的现金科目余额（期初口径：end 前一日为止的累计） */
    public function cashBalanceAt(int $ledgerId, string $throughDate, string $excludeFrom = ''): string
    {
        $sql = 'SELECT a.code, '
            . '(COALESCE(SUM(i.debit_amount),0) - COALESCE(SUM(i.credit_amount),0)) AS net '
            . 'FROM erp_finance_voucher_item i '
            . 'JOIN erp_finance_voucher v ON v.id = i.voucher_id AND v.status = 1 AND v.deleted_at IS NULL '
            . 'JOIN erp_finance_account a ON a.id = i.account_id '
            . 'WHERE v.ledger_id = ? AND v.voucher_date <= ?';
        $binds = [$ledgerId, $throughDate];
        if ($excludeFrom !== '') {
            $sql .= ' AND v.voucher_date < ?';
            $binds[] = $excludeFrom;
        }
        $sql .= ' GROUP BY a.code';
        $balance = '0';
        foreach (DB::select($sql, $binds) as $row) {
            if (self::isCashAccount((string) $row->code)) {
                $balance = bcadd($balance, bc_norm((string) $row->net), 6);
            }
        }

        return $balance;
    }

    /** 期初现金：同账套上期快照优先，否则实时计算（computeCashFlow 缺省来源） */
    public function beginningCash(int $ledgerId, int $year, int $month): string
    {
        $prevYear = $year;
        $prevMonth = $month - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            --$prevYear;
        }
        $snapshot = FinanceCashFlow::where('ledger_id', $ledgerId)
            ->where('report_year', $prevYear)->where('report_month', $prevMonth)
            ->first();
        if ($snapshot) {
            return bc_norm((string) ($snapshot->ending_cash ?? '0'));
        }
        [$start] = self::periodRange($year, $month);

        return $this->cashBalanceAt($ledgerId, $start, $start);
    }
}
