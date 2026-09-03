<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\model\OpenApiApp;
use support\Log;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * P0 OpenAPI 第三方应用认证 + 限流中间件（仅挂载在 /open/v1 等开放路由组）
 *
 * 认证协议（见 config/openapi.php 顶部注释）：
 *   请求头 X-API-Key / X-Timestamp / X-Signature；
 *   签名串 = {timestamp}{METHOD}{path}{rawBody} 无分隔符拼接，
 *   HMAC-SHA256(app_secret 解密明文) 十六进制小写，hash_equals 恒时比较；
 *   时间戳 ±300s 防重放；app.scopes 为空/NULL 表示不限制，否则路径须前缀命中任一 scope。
 *
 * 失败响应与 AdminAuth 统一：{code,message,data}，并用 withStatus 携带 HTTP 状态码
 * （同 TrackingSignature/RateLimit 先例）。通过后注入 $request->openapiApp 供控制器使用。
 */
class OpenApiAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $appKey = (string) $request->header('X-API-Key', '');
        $timestamp = (int) $request->header('X-Timestamp', '0');
        $signature = (string) $request->header('X-Signature', '');

        $app = self::findApp($appKey);
        if (!$app) {
            return self::deny('API Key 无效或已被禁用', 401);
        }

        $tolerance = (int) config('openapi.timestamp_tolerance', 300);
        if ($timestamp <= 0 || abs(time() - $timestamp) > $tolerance) {
            return self::deny('请求时间戳无效或超出容差范围', 401);
        }

        $canonical = $timestamp . $request->method() . $request->path() . $request->rawBody();
        $secret = (string) ($app->app_secret ?: ''); // Encryptable cast 自动解密
        if ($secret === '' || !self::verifySignature($canonical, $secret, $signature)) {
            return self::deny('签名校验失败', 403);
        }

        if (!self::withinScopes($app, $request->path())) {
            return self::deny('无权访问该路径（超出应用授权范围）', 403);
        }

        $rate = self::checkRateLimit((string) $app->app_key);
        if (empty($rate[0])) {
            $window = (int) config('openapi.rate_limit.window', 60);

            // 响应头约定与 app/middleware/RateLimit.php 一致
            return json([
                'code' => 429,
                'message' => '请求过于频繁，请稍后再试',
                'data' => [],
            ])->withStatus(429)->withHeaders([
                'X-RateLimit-Limit' => (string) config('openapi.rate_limit.limit', 60),
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) (time() + $window),
                'Retry-After' => (string) $window,
            ]);
        }

        $request->openapiApp = $app;

        return $next($request);
    }

    /**
     * 按 app_key 查找启用中的应用（软删除自动排除）
     */
    public static function findApp(string $appKey): ?OpenApiApp
    {
        if ($appKey === '') {
            return null;
        }

        return OpenApiApp::query()->where('app_key', $appKey)->where('status', 1)->first();
    }

    /**
     * HMAC-SHA256 验签（恒时比较）
     */
    public static function verifySignature(string $canonical, string $secret, string $signature): bool
    {
        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, strtolower($signature));
    }

    /**
     * 应用授权范围校验：scopes 为空/NULL = 不限制；否则路径须等于某 scope 或以 scope + "/" 为前缀
     */
    public static function withinScopes(OpenApiApp $app, string $path): bool
    {
        $scopes = $app->scopes ?: [];
        if (!is_array($scopes) || $scopes === []) {
            return true;
        }

        foreach ($scopes as $scope) {
            $scope = rtrim((string) $scope, '/');
            if ($scope !== '' && ($path === $scope || str_starts_with($path, $scope . '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 限流：app_key 维度原子滑动窗口（复用 app/middleware/RateLimit.php 同款 Lua，
     * 计数维度由 IP×路径换为 app_key）。返回 [是否放行, 窗口内已计数]；
     * Redis 故障 fail-open（记录告警，与 RateLimit 一致）。
     *
     * @return array{0: bool, 1: int}
     */
    public static function checkRateLimit(string $appKey): array
    {
        $limit = (int) config('openapi.rate_limit.limit', 60);
        $window = (int) config('openapi.rate_limit.window', 60);
        $key = self::rateKey($appKey);
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - $window * 1000;

        $lua = <<<'LUA'
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, ARGV[1])
local count = redis.call('ZCARD', KEYS[1])
if count >= tonumber(ARGV[2]) then
    return {0, count}
end
redis.call('ZADD', KEYS[1], ARGV[3], ARGV[4])
redis.call('EXPIRE', KEYS[1], ARGV[5])
return {1, count + 1}
LUA;
        try {
            $result = Redis::eval($lua, 1, $key, $windowStart, $limit, $now, $now . '.' . mt_rand(), $window + 10);
        } catch (\Throwable $e) {
            Log::warning('OpenAPI 限流：Redis 不可用，本次请求跳过限流（fail-open 降级）: '
                . $e->getMessage() . ' | AppKey: ' . $appKey . ' | TraceId: ' . trace_id());

            return [true, 0];
        }

        return [empty($result[0]) ? false : true, (int) ($result[1] ?? 0)];
    }

    /**
     * 限流计数 key（测试与运维排查复用同一规则）
     */
    public static function rateKey(string $appKey): string
    {
        return 'erp:openapi:rate:' . $appKey;
    }

    /**
     * 统一认证失败响应
     */
    private static function deny(string $message, int $status): Response
    {
        return json(['code' => $status, 'message' => $message, 'data' => []])->withStatus($status);
    }
}
