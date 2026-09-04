<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrKpiTemplateItem extends Model
{
    protected $table = 'erp_hr_kpi_template_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'indicator', 'weight', 'target_value', 'rater_type', 'sort'];
    protected $casts = [
        'template_id' => 'integer',
        'weight' => 'decimal:2',
        'rater_type' => 'integer',
        'sort' => 'integer',
    ];
}
