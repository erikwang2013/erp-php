<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceConsolidationReport extends Model
{
    use Searchable;
    protected $table = 'finance_consolidation_report';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'report_data' => 'array',
    ];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['company_id', 'report_year', 'report_month', 'base_currency', 'status', 'total_assets', 'total_liabilities', 'total_equity', 'revenue', 'net_profit', 'report_data', 'issued_at', 'remark'];
}
