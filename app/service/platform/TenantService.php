<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\platform;

use app\common\SnowflakeService;
use app\model\Tenant;
use Illuminate\Database\QueryException;

/**
 * 租户服务 — P2-4 B5（erp_tenant 状态机）
 *
 * 状态机：
 *   status: 0待开通 → 1启用 → 2停用（suspend）/ 3到期（expireMark）
 *           （1启用）--resume--> 恢复启用
 *           （3到期）--renew--> 1启用（自动恢复，续费即复活）
 *           （2停用）--renew--> 仍为 2（仅延长期限，恢复须先 resume）
 *   provision 直通开通：创建即 status=1 + opened_at=now（无独立"开通"动作，
 *   status=0 保留给后续线下/导入流程）；expireMark 到期后必须 renew 复活，
 *   无"重新启用"入口（renew 语义 = SaaS 续费即恢复）。
 *
 * 状态转换前置校验：越级/重复转换一律拒绝并返回中文消息，消息文本为稳定
 * 契约（供测试与前端断言，勿随意改动）。日期一律 Y-m-d 字符串比较与存储。
 *
 * 无外键约定（同全库惯例）：company_id 指向 erp_company 的有效性由上层保证，
 * 本服务只保证"一个公司至多一个租户"与租户编码唯一。
 */
class TenantService
{
    /** 套餐：1=标准 2=专业 3=旗舰 */
    public const PLANS = [1, 2, 3];

    /** 状态常量：0待开通 1启用 2停用 3到期 */
    public const STATUS_PENDING = 0;
    public const STATUS_ENABLED = 1;
    public const STATUS_SUSPENDED = 2;
    public const STATUS_EXPIRED = 3;

    /** 单次续费天数上限（DATE 列容量内的业务上限） */
    private const MAX_RENEW_DAYS = 3650;

    /** 预警默认窗口（天） */
    private const DEFAULT_WARNING_DAYS = 30;

    /**
     * 开通租户（创建即启用）。失败返回 [null, 中文消息]。
     *
     * @return array{0: ?Tenant, 1: ?string} [租户行, 错误(null=成功)]
     */
    public function provision(array $data): array
    {
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : 0;
        if ($companyId <= 0) {
            return [null, '公司不能为空'];
        }
        $tenantCode = trim((string) ($data['tenant_code'] ?? ''));
        if ($tenantCode === '') {
            return [null, '租户编码不能为空'];
        }
        if (!preg_match('/^[A-Za-z0-9_-]{2,50}$/', $tenantCode)) {
            return [null, '租户编码只能包含字母、数字、_、-（2-50位）'];
        }
        $plan = isset($data['plan']) ? (int) $data['plan'] : 0;
        if (!in_array($plan, self::PLANS, true)) {
            return [null, '套餐参数错误（1=标准 2=专业 3=旗舰）'];
        }
        $expireAt = (string) ($data['expire_at'] ?? '');
        if ($expireAt === '') {
            return [null, '到期日期必填'];
        }
        if (!$this->isDate($expireAt)) {
            return [null, '到期日期非法'];
        }
        if ($expireAt < date('Y-m-d')) {
            return [null, '到期日期不能早于今天'];
        }

        // 唯一性预检（仅未软删行；并发竞态由 uk_company / uk_tenant_code 兜底）
        if (Tenant::query()->where('company_id', $companyId)->exists()) {
            return [null, '公司已开通租户'];
        }
        if (Tenant::query()->where('tenant_code', $tenantCode)->exists()) {
            return [null, '租户编码已存在'];
        }

        // guarded ['id',...] 会阻断 create() 批量写入 id → 沿用库内惯例
        // （LedgerService：new Model + 直接属性赋值后 save）
        $tenant = new Tenant();
        $tenant->id = SnowflakeService::generate();
        $tenant->company_id = $companyId;
        $tenant->tenant_code = $tenantCode;
        $tenant->plan = $plan;
        $tenant->status = self::STATUS_ENABLED;
        $tenant->expire_at = $expireAt;
        $tenant->opened_at = date('Y-m-d H:i:s');
        $tenant->remark = trim((string) ($data['remark'] ?? ''));
        $tenant->created_by = (int) ($data['created_by'] ?? 0);
        try {
            $tenant->save();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                // uk_company/uk_tenant_code 并发竞态兜底（正常路径已由上方预检查拦截）
                return [null, '公司已开通租户或租户编码已存在'];
            }
            throw $e;
        }

        return [$tenant, null];
    }

    /**
     * 停用租户：仅 1启用 → 2停用。
     *
     * @return array{0: ?Tenant, 1: ?string}
     */
    public function suspend(int $tenantId): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return [null, '租户不存在'];
        }
        if ((int) $tenant->status !== self::STATUS_ENABLED) {
            return [null, '仅启用状态可停用'];
        }
        $tenant->status = self::STATUS_SUSPENDED;
        $tenant->save();

        return [$tenant, null];
    }

    /**
     * 恢复启用：仅 2停用 → 1启用（到期(3)恢复须走 renew 续费复活）。
     *
     * @return array{0: ?Tenant, 1: ?string}
     */
    public function resume(int $tenantId): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return [null, '租户不存在'];
        }
        if ((int) $tenant->status !== self::STATUS_SUSPENDED) {
            return [null, '仅停用状态可恢复'];
        }
        $tenant->status = self::STATUS_ENABLED;
        $tenant->save();

        return [$tenant, null];
    }

    /**
     * 标记到期：1启用 / 2停用 → 3到期（0待开通与已到期拒绝）。
     *
     * @return array{0: ?Tenant, 1: ?string}
     */
    public function expireMark(int $tenantId): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return [null, '租户不存在'];
        }
        $status = (int) $tenant->status;
        if ($status === self::STATUS_PENDING) {
            return [null, '待开通租户无需标记到期'];
        }
        if ($status === self::STATUS_EXPIRED) {
            return [null, '租户已到期，无需重复标记'];
        }
        $tenant->status = self::STATUS_EXPIRED;
        $tenant->save();

        return [$tenant, null];
    }

    /**
     * 续费 N 天（SaaS 叠加语义）：
     *   base = max(当前到期日, 今天)（已到期租户从今天起重计）；
     *   到期(3)续费自动恢复为 1启用（续费即复活）；停用(2)续费仅延长
     *   到期日、状态保持停用（恢复须另调 resume）。
     *
     * @return array{0: ?Tenant, 1: ?string}
     */
    public function renew(int $tenantId, int $days): array
    {
        $tenant = $this->findTenant($tenantId);
        if ($tenant === null) {
            return [null, '租户不存在'];
        }
        if ($days < 1 || $days > self::MAX_RENEW_DAYS) {
            return [null, '续费天数必须在1-' . self::MAX_RENEW_DAYS . '之间'];
        }
        if ((int) $tenant->status === self::STATUS_PENDING) {
            return [null, '待开通租户不可续费'];
        }

        $base = (string) $tenant->expire_at > date('Y-m-d')
            ? (string) $tenant->expire_at
            : date('Y-m-d');
        $tenant->expire_at = date('Y-m-d', strtotime("+{$days} days", strtotime($base)));
        if ((int) $tenant->status === self::STATUS_EXPIRED) {
            $tenant->status = self::STATUS_ENABLED;
        }
        $tenant->save();

        return [$tenant, null];
    }

    /**
     * 到期预警：status=1 且到期日落在 [今天, 今天+$days]（含边界）的租户，
     * 按到期日升序。不含已过期未标记（status=3）与停用/待开通租户。
     *
     * @return array{0: ?array, 1: ?string} [行数组(list), 错误(null=成功)]
     */
    public function expiryWarnings(int $days = self::DEFAULT_WARNING_DAYS): array
    {
        if ($days < 1 || $days > 365) {
            return [null, '预警天数必须在1-365之间'];
        }
        $end = date('Y-m-d', strtotime("+{$days} days"));

        $rows = Tenant::query()
            ->where('status', self::STATUS_ENABLED)
            ->whereBetween('expire_at', [date('Y-m-d'), $end])
            ->orderBy('expire_at')
            ->get()
            ->map(static fn (Tenant $t): array => [
                'id' => (int) $t->id,
                'tenant_code' => $t->tenant_code,
                'company_id' => (int) $t->company_id,
                'plan' => (int) $t->plan,
                'status' => (int) $t->status,
                'expire_at' => (string) $t->expire_at,
            ])
            ->all();

        return [$rows, null];
    }

    private function findTenant(int $tenantId): ?Tenant
    {
        return Tenant::query()->find($tenantId);
    }

    private function isDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        return checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
    }
}
