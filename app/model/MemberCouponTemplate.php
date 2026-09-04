<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 会员卡券模板 — P2-3 C1（营销活动配置，管理端手工维护）
 */
class MemberCouponTemplate extends Model
{
    protected $table = 'erp_member_coupon_template';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
