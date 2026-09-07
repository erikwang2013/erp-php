<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员卡券模板 — P2-3 C1（营销活动配置，管理端手工维护）
 */
class MemberCouponTemplate extends Model
{
    use Searchable;
    protected $table = 'member_coupon_template';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['name', 'coupon_type', 'threshold_amount', 'discount_value', 'valid_days', 'total_qty', 'issued_qty', 'status'];
}
