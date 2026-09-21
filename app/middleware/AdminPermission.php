<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\AdminUser;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;

class AdminPermission
{
    private const CACHE_TTL = 60; // 权限缓存 60 秒

    public function process(Request $request, callable $next): Response
    {
        $adminId = $request->adminId ?? 0;
        if (!$adminId) {
            return $next($request);
        }

        $permissions = $this->getUserPermissions($adminId);

        if (in_array('*', $permissions)) {
            return $next($request);
        }

        if (!$this->hasPermission($permissions, $this->permissionOf($request))) {
            return json(['code' => 403, 'message' => '无权限访问', 'data' => []]);
        }

        return $next($request);
    }

    /**
     * 请求 → 权限 slug：小写方法 + unversioned 路径（get.admin/product）。
     * 路由挂在 /admin/v1/* 下，而种子 slug 不带版本段；Request::path()
     * 返回含前导斜杠的路径（'/admin/v1/product'），剥离版本段后补回 '/admin'。
     * 版本段正则与 RateLimit 中间件同款（/v\d+ 且后接 '/' 或结尾，避免误伤 v1x）。
     */
    protected function permissionOf(Request $request): string
    {
        $path = $request->path();
        if (preg_match('#^/admin/v\d+(?=/|$)#', $path, $m)) {
            $path = '/admin' . substr($path, strlen($m[0]));
        }

        return strtolower($request->method()) . '.' . trim($path, '/');
    }

    /**
     * 权限匹配规则（与种子 slug 兼容）:
     * 1. 精确匹配 get.admin/product
     * 2. 动态段回退: put.admin/user/123 命中 put.admin/user（资源级权限）
     * 3. Route::any 兼容: any.admin/x 命中 get/post/put/delete/patch.admin/x
     */
    private function hasPermission(array $permissions, string $required): bool
    {
        if (in_array($required, $permissions, true)) {
            return true;
        }

        // Route::any 兼容: 用户权限含 any.admin/x 时命中任意方法请求（含动态段回退）
        $dotPos = strpos($required, '.');
        $path = $dotPos !== false ? substr($required, $dotPos + 1) : $required;
        while ($path !== '') {
            if (in_array("any.{$path}", $permissions, true)) {
                return true;
            }
            if (!str_contains($path, '/')) {
                break;
            }
            $path = substr($path, 0, strrpos($path, '/'));
        }

        if (str_contains($required, '/')) {
            $prefix = substr($required, 0, strrpos($required, '/'));
            while ($prefix !== '') {
                if (in_array($prefix, $permissions, true)) {
                    return true;
                }
                if (!str_contains($prefix, '/')) {
                    break;
                }
                $prefix = substr($prefix, 0, strrpos($prefix, '/'));
            }
        }

        return false;
    }

    private function getUserPermissions(int $adminId): array
    {
        // Redis 缓存，避免每请求 N+1 查询
        $cacheKey = "perm:{$adminId}";
        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Throwable $e) {
            // 缓存读取失败：降级从数据库取权限（fail-safe，权限校验不会被绕过），仅记录日志
            Log::warning('权限缓存读取失败，降级查库: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        $user = AdminUser::find($adminId);
        if (!$user) {
            return [];
        }

        $permissions = [];
        foreach ($user->roles as $role) {
            if ($role->status === 0) {
                continue;
            }
            foreach ($role->permissions as $perm) {
                $permissions[] = $perm->slug;
            }
        }
        $permissions = array_unique($permissions);

        try {
            Redis::setex($cacheKey, self::CACHE_TTL, json_encode($permissions));
        } catch (\Throwable $e) {
            // 缓存写入失败不影响本次鉴权结果，仅记录日志
            Log::warning('权限缓存写入失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        return $permissions;
    }
}
