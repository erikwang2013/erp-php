<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrAttendance extends Model
{
    protected $table = 'erik_hr_attendance';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['employee_id', 'rule_id', 'work_date', 'clock_in', 'clock_out', 'status', 'late_minutes', 'early_minutes'];
    protected $casts = ['employee_id' => 'integer', 'rule_id' => 'integer', 'status' => 'integer', 'late_minutes' => 'integer', 'early_minutes' => 'integer'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
    public function rule()
    {
        return $this->belongsTo(HrAttendanceRule::class, 'rule_id');
    }
}
