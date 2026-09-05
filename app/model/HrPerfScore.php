<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrPerfScore extends Model
{
    use Searchable;
    protected $table = 'hr_perf_score';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['plan_id', 'employee_id', 'rater_id', 'rater_type', 'indicator', 'score', 'comment'];
    protected $casts = [
        'plan_id' => 'integer',
        'employee_id' => 'integer',
        'rater_id' => 'integer',
        'rater_type' => 'integer',
        'score' => 'decimal:2',
    ];
}
