<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 社保基数规则（H4）
 *
 * city + rule_name 唯一；social_base_min/max 为 DECIMAL(14,2)，0.00 = 该方向不设限。
 * 删除规则前须无员工绑定（SocialSecurityService 守卫），否则物理删除会悬空引用。
 */
class HrSocialRule extends Model
{
    use Searchable;
    protected $table = 'hr_social_rule';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['city', 'rule_name', 'social_base_min', 'social_base_max'];

    // 上下限金额不加 cast：DECIMAL 原生返回字符串，供服务层 bcmath 精确比较/展示。

    // 比例行排序由加载方负责（SocialSecurityService::ratesOrdered() 按 id 升序预加载，
    // 与 calculate() 行序一致，避免走 (rule_id, insurance_type) 唯一索引的无序/字母序漂移）。
    // 关联方法本身不做链式排序：模型层 orderBy 会被 phpstan 推断为 Query\Builder 返回。
    public function rates(): HasMany
    {
        return $this->hasMany(HrSocialRate::class, 'rule_id');
    }
}
