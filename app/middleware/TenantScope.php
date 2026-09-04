<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\middleware;

use app\model\Tenant;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 多租户上下文中间件（P2-4 B5 修复版）——X-Tenant-Code 请求头 → erp_tenant 查表注入
 *
 * 【状态】已实现、默认未注册：config/route.php 的 /admin 分组链
 * （AdminAuth → AdminPermission → OperationLog）与全局 config/middleware.php
 * 均未挂载本中间件，当前对任何线上请求不生效。注册点为 /admin 分组
 * AdminAuth 之后（先认证、后取租户头）。
 *
 * 【信任边界（注册前必须解决）】租户上下文来源为 X-Tenant-Code 请求头，属
 * 可伪造输入：在 erp_admin_user 与公司/租户建立绑定（管理员归属判定）之前
 * 启用本中间件会造成越权数据面缺口（任意已认证管理员可声明任意租户，若该
 * 租户启用即读取其数据）。本批次仅交付机制 + 试点隔离模型，注册启用待平台
 * 侧完成绑定（docs/ARCHITECTURE.md §22 同步更新）。
 *
 * 行为契约：
 *   - 无/空请求头 → 直接放行，不注入（单租户兼容，回归线：与现状完全一致）；
 *   - 有请求头 → erp_tenant 按 tenant_code 精确查（SoftDeletes 自动排除已删行）：
 *     查无 → 403「租户不存在」；status=0 → 「租户未开通」；status=2 → 「租户已停用」；
 *     status=3 → 「租户已到期」；status=1 但已过到期日（到期标记任务未跑）→ 「租户已到期」；
 *   - 通过 → 注入 $request->tenantId / $request->companyId，
 *     供 TenantScope trait 解析过滤（公司族模型过滤 company_id）。
 *
 * 拒绝响应形状对齐 AdminAuth（body code 403，业务错误码承载于 body）。
 */
class TenantScope implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $tenantCode = $request->header('X-Tenant-Code');
        if ($tenantCode === null || $tenantCode === '') {
            return $handler($request);
        }

        $tenant = Tenant::query()->where('tenant_code', $tenantCode)->first();
        if (!$tenant) {
            return $this->reject('租户不存在');
        }
        $status = (int) $tenant->status;
        if ($status === 0) {
            return $this->reject('租户未开通');
        }
        if ($status === 2) {
            return $this->reject('租户已停用');
        }
        if ($status === 3 || (string) $tenant->expire_at < date('Y-m-d')) {
            return $this->reject('租户已到期');
        }

        // 动态属性写入需窄化到 support\Request（phpstan 中该类为 universalObjectCrates，
        // 允许动态属性；运行时请求实例即其子类实例，见 AdminAuth 同款写法的基线说明）
        if (!$request instanceof \support\Request) {
            return $handler($request);
        }
        $request->tenantId = (int) $tenant->id;
        $request->companyId = (int) $tenant->company_id;

        return $handler($request);
    }

    private function reject(string $message): Response
    {
        return json(['code' => 403, 'message' => $message, 'data' => []]);
    }
}
