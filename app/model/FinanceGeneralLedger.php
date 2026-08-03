<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class FinanceGeneralLedger extends Model
{
    protected $table = 'erik_finance_general_ledger';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['account_id', 'period_year', 'period_month', 'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'];
    protected $casts = ['account_id'=>'integer', 'period_year'=>'integer', 'period_month'=>'integer', 'opening_debit'=>'float', 'opening_credit'=>'float', 'period_debit'=>'float', 'period_credit'=>'float', 'closing_debit'=>'float', 'closing_credit'=>'float'];
}
