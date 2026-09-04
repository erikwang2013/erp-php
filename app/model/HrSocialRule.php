<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use support\Model;

/**
 * 社保基数规则（H4）
 *
 * city + rule_name 唯一；social_base_min/max 为 DECIMAL(14,2)，0.00 = 该方向不设限。
 * 删除规则前须无员工绑定（SocialSecurityService 守卫），否则物理删除会悬空引用。
 */
class HrSocialRule extends Model
{
    protected $table = 'erp_hr_social_rule';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['city', 'rule_name', 'social_base_min', 'social_base_max'];

    // 上下限金额不加 cast：DECIMAL 原生返回字符串，供服务层 bcmath 精确比较/展示。

    public function rates(): HasMany
    {
        return $this->hasMany(HrSocialRate::class, 'rule_id');
    }
}
