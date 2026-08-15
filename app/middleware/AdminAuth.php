<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Erikwang2013\Jwt\JWT;
use Erikwang2013\Jwt\JWTException;
use support\Log;
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

        // 复用统一的 JWT 校验逻辑（与 WebSocket 鉴权共用）
        $result = self::validateToken($token);
        if (!$result['ok']) {
            return json(['code' => 401, 'message' => $result['error'], 'data' => []]);
        }

        $payload = $result['payload'];
        $request->adminId = (int)$payload['sub'];
        $request->adminUsername = $payload['username'] ?? '';

        return $next($request);
    }

    /**
     * 校验 JWT 访问令牌（HTTP 中间件与 WebSocket 鉴权共用，可复用）
     *
     * 校验顺序：空令牌 → Redis 黑名单 → 签名/有效期 → refresh 令牌拦截
     * → sub 用户 ID → 用户启用状态。
     *
     * @return array{ok: bool, payload?: array, error?: string} ok=true 时携带 payload，否则携带 error 提示
     */
    public static function validateToken(string $token): array
    {
        if ($token === '') {
            return ['ok' => false, 'error' => '未登录'];
        }

        // 检查 JWT 黑名单（Redis 不可用时跳过，不阻断鉴权）
        // 注意：这是有意的 fail-open 降级 —— Redis 故障期间已注销的令牌无法被黑名单拦截，
        // 但签名校验仍有效。必须记录告警日志以便运维及时发现并修复 Redis。
        $blacklistKey = 'jwt_blacklist:' . md5($token);
        try {
            if (Redis::get($blacklistKey)) {
                return ['ok' => false, 'error' => 'Token已失效，请重新登录'];
            }
        } catch (\Throwable $e) {
            Log::warning('鉴权：Redis 不可用，跳过 JWT 黑名单检查（fail-open 降级）: '
                . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        try {
            $payload = self::getJWT()->decode($token);
        } catch (JWTException | \Exception) {
            return ['ok' => false, 'error' => 'Token已过期或无效'];
        }

        // 刷新令牌不能当访问令牌使用
        if (($payload['token_type'] ?? '') === 'refresh') {
            return ['ok' => false, 'error' => '请使用访问令牌'];
        }

        $userId = (int)($payload['sub'] ?? 0);
        if ($userId === 0) {
            return ['ok' => false, 'error' => 'Token已过期或无效'];
        }
        if (!self::isUserActive($userId)) {
            return ['ok' => false, 'error' => '账号已被禁用'];
        }

        return ['ok' => true, 'payload' => $payload];
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
        } catch (\Throwable $e) {
            // 缓存读取失败：降级走数据库（fail-safe），但需记录日志
            Log::warning('用户状态缓存读取失败，降级查库: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        $user = \app\model\AdminUser::find($userId);
        $active = $user && (int)$user->status === 1;

        try {
            Redis::setex($cacheKey, 60, $active ? '1' : '0');
        } catch (\Throwable $e) {
            // 缓存写入失败不影响本次鉴权结果，仅记录日志
            Log::warning('用户状态缓存写入失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        return $active;
    }
}
