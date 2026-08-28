<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use support\Db;

class PeriodCloseService
{
    /**
     * 期末损益结转：按期间汇总损益类科目发生额（收入类=贷方-借方、费用类=借方-贷方，
     * 借方冲减收入/贷方冲减费用为销售退回等冲减分录）。
     * 仅统计已审核凭证且启用科目；不生成结转凭证（缺本年利润科目配置与防重复结转规则），
     * status=calculated 表示已完成计算、未执行结转。
     */
    public function closeProfitAndLoss(int $periodYear, int $periodMonth): array
    {
        $period = sprintf('%04d-%02d', $periodYear, $periodMonth);
        $start = sprintf('%04d-%02d-01', $periodYear, $periodMonth);
        $end = date('Y-m-t', strtotime($start));

        $rows = Db::select(
            "SELECT a.type, ROUND(SUM(vi.debit_amount), 2) AS debit, ROUND(SUM(vi.credit_amount), 2) AS credit
             FROM erp_finance_voucher_item vi
             JOIN erp_finance_voucher v ON v.id = vi.voucher_id AND v.status = 1 AND v.deleted_at IS NULL
             JOIN erp_finance_account a ON a.id = vi.account_id AND a.deleted_at IS NULL AND a.status = 1
             WHERE v.voucher_date BETWEEN ? AND ? AND a.type IN (4, 5)
             GROUP BY a.type",
            [$start, $end]
        );

        $revenue = 0.0;
        $expense = 0.0;
        foreach ($rows as $r) {
            if ((int) $r->type === 4) {
                $revenue += (float) $r->credit - (float) $r->debit;
            } else {
                $expense += (float) $r->debit - (float) $r->credit;
            }
        }

        return [
            'period' => $period,
            'revenue_total' => round($revenue, 2),
            'expense_total' => round($expense, 2),
            'net_profit' => round($revenue - $expense, 2),
            'voucher_id' => null,
            'status' => 'calculated',
            'message' => '已按期间汇总损益类科目发生额；结转凭证生成尚未实现（缺本年利润科目与防重复结转规则）',
        ];
    }
}
