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
 * 多租户作用域中间件
 * 从 X-Tenant-Id 请求头读取租户ID，注入请求对象并设置模型全局作用域
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
