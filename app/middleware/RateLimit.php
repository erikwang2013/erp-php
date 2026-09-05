<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use support\Log;
use support\Redis;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class RateLimit implements MiddlewareInterface
{
    private int $defaultLimit = 60;
    private int $defaultWindow = 60;

    private array $sensitive = [
        '/api/auth/login' => ['limit' => 10, 'window' => 60],
        '/api/auth/register' => ['limit' => 5, 'window' => 60],
        '/api/auth/refresh' => ['limit' => 20, 'window' => 60],
        '/admin/user' => ['limit' => 30, 'window' => 60],
        '/admin/user/batch' => ['limit' => 10, 'window' => 60],
        '/api/auth/change-password' => ['limit' => 5, 'window' => 60],
    ];

    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        // 安装向导（/install*）放行（引导阶段用户 IP 高频操作属正常）
        if (str_starts_with($path, 'install')) {
            return $handler($request);
        }
        // apidoc 文档接口（/apidoc*）放行（静态注解浏览/生成属低频但需批量遍历）
        if (str_starts_with($path, 'apidoc')) {
            return $handler($request);
        }
        $ip = $request->getRealIp();

        $limit = $this->defaultLimit;
        $window = $this->defaultWindow;

        foreach ($this->sensitive as $pattern => $cfg) {
            if ($path === $pattern || str_starts_with($path, rtrim($pattern, '/') . '/')) {
                $limit = $cfg['limit'];
                $window = $cfg['window'];
                break;
            }
        }

        $safePath = preg_replace('/[^a-zA-Z0-9_-]/', '_', $path);
        $key = "erp:rate_limit:{$ip}:{$safePath}";
        $now = (int) (microtime(true) * 1000);
        $windowStart = $now - $window * 1000;

        // 原子化滑动窗口：Lua 脚本避免 TOCTOU 竞态
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
            // 有意的 fail-open 降级：Redis 故障期间放开限流，避免正常用户被误伤；
            // 但敏感接口（登录/注册等）的防爆破能力随之失效，必须记录告警日志。
            Log::warning('限流：Redis 不可用，本次请求跳过限流（fail-open 降级）: '
                . $e->getMessage() . ' | Path: ' . $path . ' | TraceId: ' . trace_id());

            return $handler($request);
        }
        $count = (int) ($result[1] ?? 0);
        $remaining = max($limit - $count, 0);
        $reset = time() + $window;

        if (empty($result[0])) {
            return json([
                'code' => 429,
                'message' => '请求过于频繁，请稍后再试',
                'data' => [],
            ])->withStatus(429)->withHeaders([
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) $reset,
                'Retry-After' => (string) $window,
            ]);
        }

        $response = $handler($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $reset,
        ]);
    }
}
