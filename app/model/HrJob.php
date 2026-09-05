<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrJob extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'hr_job';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['job_title', 'department_id', 'headcount', 'requirement', 'status', 'publish_at', 'close_at'];
    protected $casts = [
        'department_id' => 'integer',
        'headcount' => 'integer',
        'status' => 'integer',
    ];
}
