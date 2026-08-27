<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration\Fixtures;

use app\model\concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * 集成测试专用租户模型：使用 TenantScope trait，映射测试表 erp_it_tenant。
 *
 * 用于验证多租户全局作用域的隔离契约：
 * - 经【使用类】调用 TenantAwareModel::setCurrentTenantId() 时，作用域机制应生效
 *   （每个使用类持有自己的静态拷贝，这是 trait 的正常用法）；
 * - 经【trait 名】调用 TenantScope::setCurrentTenantId() 时存在已知缺陷
 *   （写的是 trait 自身的拷贝，使用类读不到），见 TenantScopeIntegrationTest
 *   中对应测试的注释说明。
 */
class TenantAwareModel extends Model
{
    use TenantScope;

    protected $table = 'erp_it_tenant';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['id', 'tenant_id', 'name'];
}
