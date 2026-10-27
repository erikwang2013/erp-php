<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 资产负债表快照
 */
class FinanceBalanceSheet extends Model
{
    use Searchable;

    protected $table = 'finance_balance_sheet';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['company_id', 'ledger_id', 'report_year', 'report_month', 'total_assets', 'total_liabilities', 'total_equity', 'current_assets', 'non_current_assets', 'current_liabilities', 'non_current_liabilities', 'report_data'];
    // 表无 updated_at 列（仅 created_at DEFAULT CURRENT_TIMESTAMP），关闭 Eloquent 时间戳自动维护
    public $timestamps = false;
}
