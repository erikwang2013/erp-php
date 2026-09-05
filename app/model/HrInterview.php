<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrInterview extends Model
{
    use Searchable;
    protected $table = 'hr_interview';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['candidate_id', 'round_no', 'interviewer_id', 'interview_date', 'result', 'comment'];
    protected $casts = [
        'candidate_id' => 'integer',
        'round_no' => 'integer',
        'interviewer_id' => 'integer',
        'result' => 'integer',
    ];
}
