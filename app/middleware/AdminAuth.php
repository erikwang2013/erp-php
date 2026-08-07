<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AdminAuth implements MiddlewareInterface
{
    private static function getJWT(): JWT
    {
        return jwt_instance();
    }

    public function process(Request $request, callable $next): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return json(['code' => 401, 'message' => '未登录', 'data' => []]);
        }

        // 检查 JWT 黑名单
        $blacklistKey = 'jwt_blacklist:' . md5($token);
        try {
            if (Redis::get($blacklistKey)) {
                return json(['code' => 401, 'message' => 'Token已失效，请重新登录', 'data' => []]);
            }
        } catch (\Throwable $e) {
            // Redis down, skip blacklist check
        }

        try {
            $payload = self::getJWT()->decode($token);
        } catch (JWTException | \Exception $e) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }

        // 刷新令牌不能当访问令牌使用
        if (($payload['token_type'] ?? '') === 'refresh') {
            return json(['code' => 401, 'message' => '请使用访问令牌', 'data' => []]);
        }

        $userId = (int)($payload['sub'] ?? 0);
        if ($userId === 0) {
            return json(['code' => 401, 'message' => 'Token已过期或无效', 'data' => []]);
        }
        if (!self::isUserActive($userId)) {
            return json(['code' => 401, 'message' => '账号已被禁用', 'data' => []]);
        }

        $request->adminId = $userId;
        $request->adminUsername = $payload['username'] ?? '';

        return $next($request);
    }

    /** 用户启用状态（Redis 缓存 60 秒，避免每请求查库） */
    private static function isUserActive(int $userId): bool
    {
        $cacheKey = "user_status:{$userId}";
        try {
            $cached = Redis::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached === '1';
            }
        } catch (\Throwable) {
        }

        $user = \app\model\AdminUser::find($userId);
        $active = $user && (int)$user->status === 1;

        try {
            Redis::setex($cacheKey, 60, $active ? '1' : '0');
        } catch (\Throwable) {
        }

        return $active;
    }
}
