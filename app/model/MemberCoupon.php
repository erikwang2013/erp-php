<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 会员卡券实例 — P2-3 C1
 *
 * 状态机：0=未使用 → 1=已核销 / 2=已过期（2 在核销判拒过期时惰性置位，
 * 无定时扫表；查询可用性按 expire_at 实时判定；order_source 记录核销来源单号）。
 */
class MemberCoupon extends Model
{
    protected $table = 'erp_member_coupon';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
