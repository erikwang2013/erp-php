<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

/**
 * 销售/采购订单状态机（纯逻辑，可单测）
 *
 * 0=待审核 1=已审核 2=部分发货/收货 3=已发货/已收货 4=已取消
 * 2/3 只能由发货/收货业务动作写入；取消只允许发生在 0/1（1→4 还需无发货/收货记录）。
 */
final class OrderStatusMachine
{
    public const PENDING = 0;
    public const APPROVED = 1;
    public const PARTIAL = 2;
    public const COMPLETED = 3;
    public const CANCELLED = 4;

    /** update API 允许的流转（业务动作驱动的 1→2/2→3 等不在其中） */
    private const UPDATE_FLOW = [
        self::PENDING => [self::APPROVED, self::CANCELLED],
        self::APPROVED => [self::CANCELLED],
    ];

    /** 订单更新接口是否允许 $from → $to（同值视为 no-op） */
    public static function canUpdate(int $from, int $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::UPDATE_FLOW[$from] ?? [], true);
    }

    /** 发货/收货仅可在已审核或部分执行状态下创建 */
    public static function canDeliverOrReceive(int $status): bool
    {
        return $status === self::APPROVED || $status === self::PARTIAL;
    }
}
