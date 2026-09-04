<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration\Fixtures;

use app\model\concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * 集成测试专用公司族模型：$tenantScopeByCompany=true，映射测试表 erp_it_company_data。
 *
 * 镜像 erp_finance_ledger/profit 等生产公司族试点模型的用法：tenant 上下文中
 * 按 company_id 过滤；无上下文不过滤（回归线）；company_id 为 NULL 的旧数据
 * 行在租户上下文中不可见（安全默认）。见 B5TenantTest 隔离用例。
 */
class CompanyAwareModel extends Model
{
    use TenantScope;

    protected static function tenantScopeByCompany(): bool
    {
        return true;
    }

    protected $table = 'erp_it_company_data';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['id', 'company_id', 'name'];
}
