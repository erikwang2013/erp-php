<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrPerfPlan extends Model
{
    use Searchable;
    protected $table = 'hr_perf_plan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'period_start', 'period_end', 'status', 'created_by'];
    protected $casts = [
        'template_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
    ];
}
