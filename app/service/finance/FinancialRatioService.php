<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

class FinancialRatioService
{
    public function calculate(array $balanceSheet, array $profitStatement): array
    {
        $currentAssets = $balanceSheet['current_assets'] ?? 0;
        $currentLiabilities = $balanceSheet['current_liabilities'] ?? 1;
        $totalLiabilities = $balanceSheet['total_liabilities'] ?? 0;
        $totalAssets = $balanceSheet['total_assets'] ?? 1;
        $netProfit = $profitStatement['net_profit'] ?? 0;
        $revenue = $profitStatement['revenue'] ?? 1;

        return [
            'current_ratio' => round($currentAssets / max($currentLiabilities, 1), 2),
            'debt_ratio' => round(($totalLiabilities / max($totalAssets, 1)) * 100, 2) . '%',
            'net_profit_margin' => round(($netProfit / max($revenue, 1)) * 100, 2) . '%',
            'return_on_assets' => round(($netProfit / max($totalAssets, 1)) * 100, 2) . '%',
        ];
    }
}
