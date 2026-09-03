<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 计件工资月度归集（P1-M1b）
 *
 * 报工审核时按员工+期间(报工年月)累加，uk_employee_period 唯一；
 * 作为 HR 薪资的计件来源自动并入 erp_hr_salary.piece_wage。
 */
class MfgPieceWage extends Model
{
    protected $table = 'erp_mfg_piece_wage';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['employee_id', 'period_year', 'period_month', 'quantity', 'amount'];
    protected $casts = [
        'employee_id' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'quantity' => 'float',
        'amount' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
