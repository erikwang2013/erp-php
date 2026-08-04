<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

class AccountBalanceService
{
    public function getBalance(int $accountSubjectId, string $period = ''): array
    {
        return [
            'account_subject_id' => $accountSubjectId,
            'period' => $period ?: date('Y-m'),
            'opening_debit' => 0,
            'opening_credit' => 0,
            'current_debit' => 0,
            'current_credit' => 0,
            'closing_debit' => 0,
            'closing_credit' => 0,
        ];
    }

    public function getTrialBalance(string $period): array
    {
        return [
            'period' => $period,
            'total_debit' => 0,
            'total_credit' => 0,
            'items' => [],
        ];
    }
}
