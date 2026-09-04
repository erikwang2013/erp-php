<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrCandidate extends Model
{
    protected $table = 'erp_hr_candidate';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'phone', 'source', 'job_id', 'status', 'expected_salary', 'resume_summary'];
    protected $casts = [
        'job_id' => 'integer',
        'status' => 'integer',
        'expected_salary' => 'decimal:2',
    ];
}
