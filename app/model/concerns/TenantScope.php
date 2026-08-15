<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model\concerns;

/**
 * 多租户全局作用域 Trait（预留能力，本期未启用）
 *
 * 使用该 Trait 的模型会在查询时自动追加 tenant_id 过滤条件。Eloquent 约定：
 * boot{TraitName} 会在模型引导时自动注册全局作用域；当前无任何模型使用本 Trait。
 *
 * 【状态】预留能力，未接线：本项目未启用多租户，tenant_id 过滤仅在
 * $currentTenantId 非 null 时生效，单租户（不设置租户）行为与未使用本 Trait
 * 完全一致。详见 docs/ARCHITECTURE.md §22。
 *
 * 【启用步骤】在需要隔离的模型类中 use app\model\concerns\TenantScope;
 * 即可自动按当前租户过滤；租户来源由中间件/请求上下文注入
 * （见 app/middleware/TenantScope.php 注释的启用步骤与已知限制）。
 *
 * 【已知限制（启用前必须解决）】
 *   - 静态属性 $currentTenantId 为"每个使用类一份拷贝"：经 trait 名直接调用
 *     setCurrentTenantId() 写入的值不会传导到使用本 Trait 的模型类（PHP 8.3
 *     实测），启用时需改为请求上下文（request()->tenantId）注入；
 *   - 静态全局状态跨请求共享，协程模式下存在跨租户串扰风险，需请求级绑定；
 *   - 使用本 Trait 的模型对应数据表必须存在 tenant_id 列（当前全库无该列）。
 */
trait TenantScope
{
    private static ?int $currentTenantId = null;

    public static function setCurrentTenantId(?int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    public static function getCurrentTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            if (self::$currentTenantId !== null) {
                $table = (new static())->getTable();
                $query->where("{$table}.tenant_id", self::$currentTenantId);
            }
        });
    }
}
