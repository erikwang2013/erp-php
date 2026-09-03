<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use support\Db;

/**
 * 科目余额/试算平衡：从已审核凭证分录（erp_finance_voucher_item）按科目真实聚合。
 * 使用原始 SQL：模型表名已含 erp_ 前缀，而 config/database.php 的 prefix 配置
 * 会二次加前缀（erp_erp_xxx，既有配置问题，不在本服务修复范围）。
 */
class AccountBalanceService
{
    public function getBalance(int $accountSubjectId, string $period = ''): array
    {
        $period = $period ?: date('Y-m');
        [$start, $end] = $this->periodRange($period);

        [$priorDebit, $priorCredit] = $this->aggregate($accountSubjectId, $start, null);
        [$curDebit, $curCredit] = $this->aggregate($accountSubjectId, $start, $end);

        $openingNet = bcsub($priorDebit, $priorCredit, 6);
        $closingNet = bcadd($openingNet, bcsub($curDebit, $curCredit, 6), 6);

        return [
            'account_subject_id' => $accountSubjectId,
            'period' => $period,
            'opening_debit' => (float) $this->debitSplit($openingNet),
            'opening_credit' => (float) $this->creditSplit($openingNet),
            'current_debit' => (float) bc_round($curDebit, 2),
            'current_credit' => (float) bc_round($curCredit, 2),
            'closing_debit' => (float) $this->debitSplit($closingNet),
            'closing_credit' => (float) $this->creditSplit($closingNet),
        ];
    }

    public function getTrialBalance(string $period): array
    {
        [$start, $end] = $this->periodRange($period);

        $rows = Db::select(
            'SELECT vi.account_id, a.parent_id, a.code, a.name, a.direction,
                    ROUND(SUM(vi.debit_amount), 2) AS debit, ROUND(SUM(vi.credit_amount), 2) AS credit
             FROM erp_finance_voucher_item vi
             JOIN erp_finance_voucher v ON v.id = vi.voucher_id AND v.status = 1 AND v.deleted_at IS NULL
             JOIN erp_finance_account a ON a.id = vi.account_id AND a.deleted_at IS NULL AND a.status = 1
             WHERE v.voucher_date BETWEEN ? AND ?
             GROUP BY vi.account_id, a.parent_id, a.code, a.name, a.direction
             ORDER BY a.code',
            [$start, $end]
        );

        $items = array_map(static fn ($r) => [
            'account_id' => (int) $r->account_id,
            'parent_id' => (int) $r->parent_id,
            'code' => $r->code,
            'name' => $r->name,
            'direction' => (int) $r->direction,
            'debit' => (float) $r->debit,
            'credit' => (float) $r->credit,
        ], $rows);

        $totalDebit = '0';
        $totalCredit = '0';
        foreach ($items as $item) {
            $totalDebit = bcadd($totalDebit, bc_norm($item['debit']), 6);
            $totalCredit = bcadd($totalCredit, bc_norm($item['credit']), 6);
        }

        return [
            'period' => $period,
            'total_debit' => (float) bc_round($totalDebit, 2),
            'total_credit' => (float) bc_round($totalCredit, 2),
            'items' => $items,
        ];
    }

    /** 已审核凭证在某科目的借贷发生额合计；$end 为 null 时统计 $start 之前 */
    private function aggregate(int $accountId, string $start, ?string $end): array
    {
        $sql = 'SELECT COALESCE(SUM(vi.debit_amount), 0) AS debit, COALESCE(SUM(vi.credit_amount), 0) AS credit
                FROM erp_finance_voucher_item vi
                JOIN erp_finance_voucher v ON v.id = vi.voucher_id AND v.status = 1 AND v.deleted_at IS NULL
                WHERE vi.account_id = ?';
        $params = [$accountId];
        if ($end === null) {
            $sql .= ' AND v.voucher_date < ?';
            $params[] = $start;
        } else {
            $sql .= ' AND v.voucher_date BETWEEN ? AND ?';
            $params[] = $start;
            $params[] = $end;
        }
        $row = Db::selectOne($sql, $params);

        return [bc_norm($row->debit), bc_norm($row->credit)];
    }

    /** bc 净额拆分：非负归入借方（贷方记 0），负值归入贷方（绝对值） */
    private function debitSplit(string $net): string
    {
        return bccomp($net, '0', 4) >= 0 ? bc_round($net, 2) : '0';
    }

    private function creditSplit(string $net): string
    {
        return bccomp($net, '0', 4) < 0 ? bc_round(bc_abs($net), 2) : '0';
    }

    /** @return array{string, string} 期间起止日期 [start, end] */
    private function periodRange(string $period): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('期间格式必须为 YYYY-MM');
        }
        $start = $period . '-01';

        return [$start, date('Y-m-t', strtotime($start))];
    }
}
