<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use app\model\concerns\TenantScope;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 利润快照（公司族多租户试点模型：tenant 上下文下按 company_id 过滤）
 */
class FinanceProfit extends Model
{
    use Searchable;
    use TenantScope;

    protected static function tenantScopeByCompany(): bool
    {
        return true;
    }

    protected $table = 'finance_profit';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
