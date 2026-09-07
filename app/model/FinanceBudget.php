<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceBudget extends Model
{
    use Searchable;
    use SoftDeletes;
    protected $table = 'finance_budget';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // guarded-only 时 getFillable()=[]，fill() 不落任何列 → NOT NULL(name/period_year)
    // 缺省直插 500。显式列白名单。
    protected $fillable = ['code', 'name', 'period_year', 'cost_center_id', 'status', 'remark'];
}
