<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceCashJournal extends Model
{
    use Searchable;
    protected $table = 'finance_cash_journal';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // install.sql 的 erp_finance_cash_journal 无 updated_at 列（仅 created_at），
    // 关闭 updated_at 自动维护，避免插入时生成未知列报错
    public const UPDATED_AT = null;
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['bank_account_id', 'direction', 'amount', 'balance', 'source_type', 'source_id', 'summary', 'journal_date'];
}
