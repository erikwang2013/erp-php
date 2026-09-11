<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

/**
 * @property int|null $ledger_id 账套维度（F1 加列；NULL = 旧数据历史全局账）
 */
class FinanceVoucher extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_voucher';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // guarded-only 时 getFillable()=[]，fill() 不落任何列 → 无 items 建单时
    // NOT NULL(code/voucher_date) 缺省直插 500。显式列白名单（表无 name 列）。
    protected $fillable = ['code', 'voucher_date', 'remark'];
}
