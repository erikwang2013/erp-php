<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrSalaryItem extends Model
{
    protected $table = 'erp_hr_salary_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'type', 'is_taxable', 'default_amount'];
    protected $casts = ['type' => 'integer', 'is_taxable' => 'integer', 'default_amount' => 'float'];
}
