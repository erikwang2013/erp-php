<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\AdminUser;
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

        $path = $request->path();
        $method = $request->method();

        $permissions = $this->getUserPermissions($adminId);

        if (in_array('*', $permissions)) {
            return $next($request);
        }

        $requiredPermission = strtolower($method) . '.' . trim($path, '/');

        if (!$this->hasPermission($permissions, $requiredPermission)) {
            return json(['code' => 403, 'message' => '无权限访问', 'data' => []]);
        }

        return $next($request);
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

        if (str_starts_with($required, 'any.')) {
            $path = substr($required, 4);
            foreach (['get', 'post', 'put', 'delete', 'patch'] as $m) {
                if (in_array("{$m}.{$path}", $permissions, true)) {
                    return true;
                }
                $seg = $path;
                while (str_contains($seg, '/')) {
                    $seg = substr($seg, 0, strrpos($seg, '/'));
                    if (in_array("{$m}.{$seg}", $permissions, true)) {
                        return true;
                    }
                }
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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
        }

        return $permissions;
    }
}
