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
}
