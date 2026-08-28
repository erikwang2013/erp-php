<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

class ConsolidationService
{
    /**
     * 多币种报表合并：尚未实现（缺汇率折算与子公司间抵销规则），显式拒绝而非返回占位数据。
     */
    public function consolidate(array $subsidiaryReports, string $baseCurrency = 'CNY'): array
    {
        throw new \RuntimeException('多币种报表合并尚未实现：缺少汇率折算与子公司间抵销规则');
    }
}
