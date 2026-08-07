<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

class PeriodCloseService
{
    /**
     * 期末损益结转：将损益类科目余额结转至本年利润
     */
    public function closeProfitAndLoss(int $periodYear, int $periodMonth): array
    {
        // Revenue accounts (贷方余额) → debit to close, credit to 本年利润
        // Expense accounts (借方余额) → credit to close, debit to 本年利润
        // In production, this would query account_balances. For now, return the structure.
        return [
            'period' => "{$periodYear}-{$periodMonth}",
            'revenue_total' => 0,
            'expense_total' => 0,
            'net_profit' => 0,
            'voucher_id' => null,
            'status' => 'pending',
            'message' => '期末结转需连接科目余额表运行',
        ];
    }
}
