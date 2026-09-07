<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 银企对账单行 — P2 F6
 *
 * 银行流水导入明细（不可编辑删除）；对账状态由 erp_finance_bank_recon_match
 * 是否存在推导，金额列不加 float cast，字符串直出 JSON。
 */
class FinanceBankStatement extends Model
{
    use Searchable;
    protected $table = 'finance_bank_statement';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['bank_account_id', 'stmt_date', 'direction', 'amount', 'counterparty', 'reference', 'balance_after', 'import_batch'];
}
