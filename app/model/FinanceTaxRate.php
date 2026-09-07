<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceTaxRate extends Model
{
    use Searchable;
    protected $table = 'finance_tax_rate';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // guarded-only 时 getFillable()=[]，fill() 不落任何列 → 建税率存空行。
    // 显式列白名单（表无 code/status 列，枚举语义列见 DDL）。
    protected $fillable = ['name', 'rate', 'type', 'enabled'];
}
