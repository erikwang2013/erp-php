<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model\concerns;

/**
 * 多租户全局作用域 Trait（P2-4 B5 修复版）
 *
 * 使用本 Trait 的模型在查询时自动追加租户过滤。租户上下文两级解析：
 *   1. 请求上下文（首选）：request()->tenantId / request()->companyId，
 *      由 app/middleware/TenantScope 经 X-Tenant-Code 请求头查 erp_tenant 后注入。
 *      该路径无静态状态，消除常驻进程内跨请求串扰（P0 缺陷修复）；
 *   2. 静态门面（弃用兼容，仅供测试/CLI 兜底）：经【使用类】调用的
 *      setCurrentTenantId / setCurrentCompanyId。注意 PHP trait 静态属性是
 *      "每个使用类一份拷贝"，经 trait 名调用写入的值不会传导到模型类
 *      （PHP 8.3 实测的 P0 缺陷）——请求上下文注入已取代该传递链路，
 *      trait 名直调仅保留给既有机制契约测试（见 TenantScopeIntegrationTest）。
 *
 * 过滤族由使用类声明（PHP 8.3 属性覆写要求默认值一致，故用方法而非属性）：
 *   - 覆写 tenantScopeByCompany() 返回 true（公司族，如 erp_finance_ledger/
 *     profit 等）：过滤 {table}.company_id（当前生产表全为公司族，全库无
 *     tenant_id 列）；
 *   - 默认 false（租户族）：过滤 {table}.tenant_id（保留给机制契约测试
 *     tests/Integration/Fixtures/TenantAwareModel）。
 *
 * 【回归线】无租户上下文（request 为空且静态门面未设置）时不做任何过滤，
 * 单租户行为与未使用本 Trait 完全一致。公司族过滤为严格等值——company_id
 * 为 NULL 的历史旧数据（如 erp_finance_profit 注释"NULL=旧数据(默认公司)"）
 * 在租户上下文中不可见（安全默认），回填归属见 LedgerService 默认公司约定。
 */
trait TenantScope
{
    /** @deprecated 静态传递已由请求上下文取代（见类头），仅供测试/CLI 兜底 */
    private static ?int $currentTenantId = null;

    /** @deprecated 同上 */
    private static ?int $currentCompanyId = null;

    /** 公司族开关：false=按 tenant_id 过滤（租户族）；true=按 company_id 过滤 */
    protected static function tenantScopeByCompany(): bool
    {
        return false;
    }

    public static function setCurrentTenantId(?int $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    public static function getCurrentTenantId(): ?int
    {
        return self::$currentTenantId;
    }

    public static function setCurrentCompanyId(?int $companyId): void
    {
        self::$currentCompanyId = $companyId;
    }

    public static function getCurrentCompanyId(): ?int
    {
        return self::$currentCompanyId;
    }

    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', static function ($query) {
            $table = $query->getModel()->getTable();
            if (static::tenantScopeByCompany()) {
                $companyId = self::resolveCompanyId();
                if ($companyId !== null) {
                    $query->where("{$table}.company_id", $companyId);
                }
            } else {
                $tenantId = self::resolveTenantId();
                if ($tenantId !== null) {
                    $query->where("{$table}.tenant_id", $tenantId);
                }
            }
        });
    }

    private static function resolveTenantId(): ?int
    {
        $req = request();
        $requestTenantId = $req->tenantId ?? null;
        if ($requestTenantId !== null) {
            return (int) $requestTenantId;
        }

        return self::$currentTenantId;
    }

    private static function resolveCompanyId(): ?int
    {
        $req = request();
        $requestCompanyId = $req->companyId ?? null;
        if ($requestCompanyId !== null) {
            return (int) $requestCompanyId;
        }

        return self::$currentCompanyId;
    }
}
