<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 多租户作用域中间件（预留能力，本期未启用）
 *
 * 从 X-Tenant-Id 请求头读取租户ID，注入请求对象并设置模型全局作用域。
 *
 * 【状态】预留能力，未接线：config/middleware.php 全局链与 config/route.php 的
 * /admin 分组均未注册本中间件，当前对任何请求不生效。项目规划明确不做多租户
 * 完整商业化方案，本期仅文档化预留，详见 docs/ARCHITECTURE.md §22。
 *
 * 【启用步骤】后续如需启用多租户隔离：
 *   1. 在 config/route.php 的 /admin 分组 middleware() 中追加本中间件
 *      （置于 AdminAuth 之后，确保已认证）；
 *   2. 请求方在请求头携带 X-Tenant-Id（int 租户ID）；
 *   3. 为需要隔离的业务表增加 tenant_id 列并回填存量数据；
 *   4. 在需要隔离的模型 use app\model\concerns\TenantScope trait。
 *
 * 【已知限制（启用前必须解决）】
 *   - 本中间件经 TraitName::setCurrentTenantId() 写入的静态属性是 trait 自身的
 *     拷贝，使用该 trait 的模型类读取不到（PHP 8.3 实测），当前传递链路断裂，
 *     启用时需改为基于请求上下文（request()->tenantId）注入；
 *   - 静态全局状态在 Workerman 常驻进程内跨请求共享，若启用协程模式会发生
 *     跨租户串扰，需改为请求级绑定。
 */
class TenantScope implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $tenantId = $request->header('X-Tenant-Id');
        if ($tenantId !== null && $tenantId !== '') {
            $request->tenantId = (int)$tenantId;
            // Scoped models will use this to filter queries
            \app\model\concerns\TenantScope::setCurrentTenantId((int)$tenantId);
        }

        return $handler($request);
    }
}
