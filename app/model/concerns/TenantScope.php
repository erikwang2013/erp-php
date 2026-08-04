<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model\concerns;

/**
 * 多租户全局作用域 Trait
 * 使用该 Trait 的模型会在查询时自动追加 tenant_id 过滤条件
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
