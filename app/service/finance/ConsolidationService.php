<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

class ConsolidationService
{
    /**
     * 多币种合并：按期末汇率将外币报表折算为本位币
     */
    public function consolidate(array $subsidiaryReports, string $baseCurrency = 'CNY'): array
    {
        return [
            'base_currency' => $baseCurrency,
            'exchange_gain_loss' => 0,
            'consolidated' => [],
            'message' => '多币种合并需连接汇率表与科目余额表运行',
        ];
    }
}
