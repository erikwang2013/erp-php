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
        $currentAssets = bc_norm($balanceSheet['current_assets'] ?? 0);
        $currentLiabilities = bc_norm($balanceSheet['current_liabilities'] ?? 1);
        $totalLiabilities = bc_norm($balanceSheet['total_liabilities'] ?? 0);
        $totalAssets = bc_norm($balanceSheet['total_assets'] ?? 1);
        $netProfit = bc_norm($profitStatement['net_profit'] ?? 0);
        $revenue = bc_norm($profitStatement['revenue'] ?? 1);

        return [
            'current_ratio' => $this->ratio($currentAssets, $currentLiabilities),
            'debt_ratio' => $this->percentage($totalLiabilities, $totalAssets),
            'net_profit_margin' => $this->percentage($netProfit, $revenue),
            'return_on_assets' => $this->percentage($netProfit, $totalAssets),
        ];
    }

    /** bc 相除（除数 <1 按 1 计，防除零），结果两位小数 */
    private function ratio(string $dividend, string $divisor): float
    {
        $denominator = bccomp($divisor, '1', 4) >= 0 ? $divisor : '1';

        return (float) bc_round(bcdiv($dividend, $denominator, 6), 2);
    }

    /** 百分比（×100 在 bc 域内完成），返回 "33.33%" 形式文本 */
    private function percentage(string $dividend, string $divisor): string
    {
        return (float) bc_round(bcmul($this->ratioRaw($dividend, $divisor), '100', 6), 2) . '%';
    }

    /** 带分母钳位的原始商（scale 6，供百分比链式运算，避免中间转 float） */
    private function ratioRaw(string $dividend, string $divisor): string
    {
        $denominator = bccomp($divisor, '1', 4) >= 0 ? $divisor : '1';

        return bcdiv($dividend, $denominator, 6);
    }
}
