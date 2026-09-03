<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class FinanceConsolidationReport extends Model
{
    protected $table = 'erp_finance_consolidation_report';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'report_data' => 'array',
    ];
}
