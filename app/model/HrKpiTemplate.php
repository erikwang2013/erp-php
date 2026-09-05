<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrKpiTemplate extends Model
{
    use Searchable;
    protected $table = 'hr_kpi_template';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'period_type', 'status'];
    protected $casts = [
        'status' => 'integer',
    ];
}
