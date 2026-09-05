<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrAttendanceRule extends Model
{
    use Searchable;
    protected $table = 'hr_attendance_rule';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'clock_in_time', 'clock_out_time', 'late_grace', 'early_grace'];
    protected $casts = ['late_grace' => 'integer', 'early_grace' => 'integer'];
}
