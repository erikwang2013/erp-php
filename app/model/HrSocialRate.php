<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 社保缴费比例行（H4）
 *
 * 行式存储：一规则多险种（rule_id + insurance_type 唯一）。
 * 比例存百分比数值（8.00 = 8%），计算口径 rate%×基数/100（bcmath scale4 → round2）。
 * personal_rate = 0.00 表示无个人缴费（工伤/生育通常公司全缴）。
 */
class HrSocialRate extends Model
{
    use Searchable;
    protected $table = 'hr_social_rate';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['rule_id', 'insurance_type', 'personal_rate', 'company_rate'];

    protected $casts = [
        'rule_id' => 'integer',
        // personal_rate/company_rate 不加 cast：DECIMAL 原生返回字符串，供 bcmath 精确计算。
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(HrSocialRule::class, 'rule_id');
    }
}
