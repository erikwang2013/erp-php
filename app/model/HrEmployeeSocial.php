<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use support\Model;

/**
 * 员工社保绑定（H4）
 *
 * employee_id UNIQUE：一名员工同时仅绑定一条规则（换城市/换年度先 unbind 再 bind）。
 * base_amount DECIMAL(14,2)，0.00 = 自动按下限：
 *   规则下限 > 0 → 以规则下限计费；规则未设下限 → 按 0.00 计费（语义见 SocialSecurityService）。
 * 显式基数须落在规则 [min, max] 内（0 的一侧不设限则跳过该校验）。
 */
class HrEmployeeSocial extends Model
{
    protected $table = 'erp_hr_employee_social';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['employee_id', 'rule_id', 'base_amount'];

    protected $casts = [
        'employee_id' => 'integer',
        'rule_id' => 'integer',
        // base_amount 不加 cast：DECIMAL 原生返回字符串，供服务层 bcmath 精确比较。
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(HrSocialRule::class, 'rule_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
