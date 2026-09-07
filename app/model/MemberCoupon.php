<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员卡券实例 — P2-3 C1
 *
 * 状态机：0=未使用 → 1=已核销 / 2=已过期（2 在核销判拒过期时惰性置位，
 * 无定时扫表；查询可用性按 expire_at 实时判定；order_source 记录核销来源单号）。
 */
class MemberCoupon extends Model
{
    use Searchable;
    protected $table = 'member_coupon';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['member_id', 'template_id', 'status', 'received_at', 'expire_at', 'used_at', 'order_source'];
}
