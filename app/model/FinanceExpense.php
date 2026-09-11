<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class FinanceExpense extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_expense';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // guarded-only 时 getFillable()=[]，fill() 不落任何列 → NOT NULL(code/apply_user_id/
    // account_id) 缺省直插 500。显式列白名单（表无 name 列）。
    protected $fillable = ['code', 'apply_user_id', 'account_id', 'amount', 'status', 'remark', 'approved_at', 'approved_by', 'paid_at'];
}
