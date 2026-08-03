<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrSalary extends Model
{
    protected $table = 'erik_hr_salary';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['employee_id', 'period_year', 'period_month', 'base_salary', 'performance', 'overtime', 'deduction', 'tax', 'net_salary', 'status'];
    protected $casts = [
        'employee_id' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'base_salary' => 'float',
        'performance' => 'float',
        'overtime' => 'float',
        'deduction' => 'float',
        'tax' => 'float',
        'net_salary' => 'float',
        'status' => 'integer',
    ];

    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
}
