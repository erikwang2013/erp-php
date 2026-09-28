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

    // 敏感路径键不带版本段：匹配前会先把 /api|admin|open 后的 /v{N} 归一掉，
    // 这样将来升 v2 无需改此表（且 v1/v2 共用同一计数器，堵住换版本绕限流）。
    // 注意顺序：前缀匹配取第一个命中，更长的键必须排在更短的前面（/admin/user/batch 先于 /admin/user）。
    private array $sensitive = [
        '/admin/user/batch' => ['limit' => 10, 'window' => 60],
        '/admin/user' => ['limit' => 30, 'window' => 60],
        '/api/auth/login' => ['limit' => 10, 'window' => 60],
        '/api/auth/register' => ['limit' => 5, 'window' => 60],
        '/api/auth/refresh' => ['limit' => 20, 'window' => 60],
        '/api/auth/change-password' => ['limit' => 5, 'window' => 60],
    ];

    public function process(Request $request, callable $handler): Response
    {
        // 把客户端 IP 暴露给依赖 $_SERVER['REMOTE_ADDR'] 的第三方限流器 —— 典型是 poster-php 的
        // 验证码限流（Captcha\RateLimiter）。它的身份解析是
        //   session_id() ?: ($_SERVER['REMOTE_ADDR'] ?? 'cli')
        // 而本栈里没有原生 session（未装 webman/session、全仓无 session_start()），workerman 也不填
        // REMOTE_ADDR ⇒ 身份恒为字面量 'cli'，30 次/分钟的预算退化成**全应用共用一桶**：单个 IP
        // 打满就会让所有人的验证码校验失败，且表现与「答错」无异，没有风控信号。赋值取自与下方
        // 同源的 getRealIp()，保证这两处限流看到同一个身份。
        // ponytail: 超级全局在常驻进程里会跨请求残留 —— 请求路径每次都重设故恒新鲜；队列/CLI 路径
        //   可能读到上一个请求的 IP（对验证码限流无影响）。根治应在上游：Webman 适配器目前写死
        //   `new CaptchaManager(driver, storage)` 两个参数，未注入身份解析器。
        $_SERVER['REMOTE_ADDR'] = $request->getRealIp();

        // 归一化：/api/v1/auth/login => /api/auth/login（/api/tms/... 等非版本段路径不受影响）。
        // 归一后的 $path 同时用于下面的 Redis 键，键粒度不变（仍是「每 IP 每端点一个桶」），
        // 仅键名一次性变化（_api_v1_auth_login => _api_auth_login），部署后计数器从 0 重新累计。
        $path = preg_replace('#^(/(?:api|admin|open))/v\d+(?=/|$)#', '$1', $request->path()) ?? $request->path();
        // 安装向导（/install*）放行（引导阶段用户 IP 高频操作属正常）
        if ($path === '/install' || str_starts_with($path, '/install/')) {
            return $handler($request);
        }
        // apidoc 文档接口（/apidoc*）放行（静态注解浏览/生成属低频但需批量遍历）
        if ($path === '/apidoc' || str_starts_with($path, '/apidoc/')) {
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
