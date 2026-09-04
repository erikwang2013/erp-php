<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 银企对账单行 — P2 F6
 *
 * 银行流水导入明细（不可编辑删除）；对账状态由 erp_finance_bank_recon_match
 * 是否存在推导，金额列不加 float cast，字符串直出 JSON。
 */
class FinanceBankStatement extends Model
{
    protected $table = 'erp_finance_bank_statement';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
