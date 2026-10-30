<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

/**
 * 库存动作的加锁顺序。
 *
 * 发货/收货明细按请求顺序逐行 stockOut/stockIn，两张单若明细顺序相反
 * （P1→P2 / P2→P1），并发时 InnoDB 会对同一库存行互等（1213 死锁）。
 * 统一按库存维度排序后再执行，保证所有请求的加锁顺序一致。
 */
final class StockLockOrder
{
    /**
     * 统一库存维度锁序：product → sku → warehouse → location → batch。
     * 缺失的可选维度键按 0/空串处理（OMS 预占明细允许只传 product_id/quantity）。
     *
     * @param list<array<string, mixed>> $lines
     * @return list<array<string, mixed>>
     */
    public static function sort(array $lines): array
    {
        usort($lines, static fn (array $a, array $b): int => [
            (int) ($a['product_id'] ?? 0),
            (int) ($a['sku_id'] ?? 0),
            (int) ($a['warehouse_id'] ?? 0),
            (int) ($a['location_id'] ?? 0),
            (string) ($a['batch_code'] ?? ''),
        ] <=> [
            (int) ($b['product_id'] ?? 0),
            (int) ($b['sku_id'] ?? 0),
            (int) ($b['warehouse_id'] ?? 0),
            (int) ($b['location_id'] ?? 0),
            (string) ($b['batch_code'] ?? ''),
        ]);

        return $lines;
    }
}
