<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 银企对账核销匹配 — P2 F6
 *
 * 对账目标为 erp_finance_cash_journal 日记账行；1:1 匹配由
 * uk_statement/uk_journal 唯一键硬保证（同账户内一行流水 ↔ 一笔日记账）。
 */
class FinanceBankReconMatch extends Model
{
    protected $table = 'erp_finance_bank_recon_match';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at'];
    // 表仅 created_at 列（无 updated_at），关闭自动维护避免插入报错
    public const UPDATED_AT = null;
}
