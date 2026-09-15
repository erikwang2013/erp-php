<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Erikwang2013\Security\SecurityGuard as Guard;
use Erikwang2013\Security\ThreatResult;
use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;
use Webman\MiddlewareInterface;

/**
 * 全局安全检测与拦截（erikwang2013/security-php 的应用侧适配层）
 *
 * 本文件原是一套自研正则检测（XSS/SQL注入/路径遍历/命令注入/上传/CSRF + IP 攻击升级封禁）。
 * 现改由 security-php 插件承担检测：两者能力重叠，且升级阈值逐字相同
 * （5 次 / 60 秒 / 封禁 900 秒），并行只会双扫 + 维护两个互相打架的封禁库。
 *
 * 不用 vendor 里现成的 Webman\SecurityMiddleware，有两个本项目特有的原因：
 *  1. 它把 $request->getRealIp() 当客户端 IP。getRealIp() 在对端是内网地址时取
 *     X-Forwarded-For 的**最左值**（webman Request.php:217），而 nginx 用的是追加式
 *     $proxy_add_x_forwarded_for（docs/nginx-default.conf:50）—— 最左值由客户端自己
 *     塞入，等于来源 IP 可任意伪造，会污染插件的黑名单与身份基线。见 clientIp()。
 *  2. storage 需要 Redis 实例，而 support\Redis::connection() 给的是协程连接池里的连接，
 *     会被 Context::onDestroy 回收复用，不能交给插件长期持有。见 redis()。
 */
class SecurityFilter implements MiddlewareInterface
{
    private static ?bool $ready = null;
    private static ?\Redis $redis = null;

    public function process(Request $request, callable $handler): Response
    {
        // 基础设施端点放行。插件没有这类例外，由适配层保留：
        //  - /install：引导阶段尚无会话与数据可护，且表单含密码/DSN/路径等字段，
        //    按攻击特征扫必然误伤。前缀放行正好覆盖安装向导两条路由
        //    （config/route.php:26-27 的 /install 与 /install/test-db），无第三条由此漏网。
        //
        // /health 曾在此放行，理由是监控以裸 IP 直连而 dns_rebinding 把裸 IP Host 判 critical。
        // 该检测器已降为 log 模式（见 config/plugin/erikwang2013/security-php/app.php），
        // 理由消失，例外一并删除 —— 少一条无依据的放行。
        if (str_starts_with($request->path(), '/install')) {
            return $handler($request);
        }

        if (!self::boot()) {
            return $handler($request); // 故障放行：安全组件绝不能让业务不可用
        }

        $ip = self::clientIp($request);
        $payload = self::payload($request);

        try {
            $threats = array_merge(
                Guard::guard($payload, self::meta($request, $ip)),
                self::gapThreats($payload)
            );
            $headers = Guard::securityHeaders();
            $block = Guard::blockDecision($threats);
        } catch (\Throwable $e) {
            // fail-open + 响亮告警：静默放行会让安全防护看起来还开着
            Log::error('安全过滤：检测链异常，本请求放行（fail-open）: ' . $e->getMessage()
                . ' | Path: ' . $request->path() . ' | TraceId: ' . trace_id());

            return $handler($request);
        }

        if ($block !== null) {
            return new Response(
                $block['status'],
                array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers),
                $block['message']
            );
        }

        // PSR-7：withHeader() 不可变，每次都要重新赋值
        $response = $handler($request);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * 每进程初始化一次插件。
     *
     * @return bool 初始化是否可用；false 时本进程内安全检测停用（fail-open）
     */
    private static function boot(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $config = require config_path() . '/plugin/erikwang2013/security-php/app.php';
            $config['storage']['redis_instance'] = self::redis();
            Guard::init($config);
            self::$ready = true;
        } catch (\Throwable $e) {
            Log::error('安全过滤：security-php 初始化失败，本进程安全检测停用: ' . $e->getMessage());
            self::$ready = false;
        }

        return self::$ready;
    }

    /**
     * 本进程独占的 Redis 连接。
     *
     * 刻意不走 support\Redis —— 那是协程连接池，借出的连接在 Context 销毁时会被归还复用，
     * 而插件要把存储句柄留在自己身上跨请求使用。这里按 config/redis.php 的参数自建一条，
     * 复用同一份配置但不共享连接。进程数 = cpu_count()*4，连接数即进程数，可接受。
     */
    private static function redis(): \Redis
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        $cfg = (array) config('redis.default', []);
        $redis = new \Redis();
        $redis->connect((string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 6379), 1.0);
        if (!empty($cfg['password'])) {
            $redis->auth((string) $cfg['password']);
        }
        if (!empty($cfg['database'])) {
            $redis->select((int) $cfg['database']);
        }

        return self::$redis = $redis;
    }

    /**
     * 真实客户端 IP。
     *
     * 仅当 TCP 对端本身是内网/回环地址（即我们的 nginx）时才采信 X-Forwarded-For，
     * 且取**最右一跳**：nginx 用追加式时末位是它实际看到的对端，用覆盖式时只有一个值，
     * 两种写法下最右一跳都是真客户端；最左值则是客户端可以自行塞进去的。
     *
     * 控制器侧记录来源 IP（登录地点基线等）也必须走这里，保持与检测链同一个口径。
     */
    public static function clientIp(Request $request): string
    {
        return self::resolveIp(
            $request->getRemoteIp(),
            (string) $request->header('x-forwarded-for', '')
        );
    }

    /**
     * 纯函数形态，便于直接夹逼验证（见 tests/SecurityFilterIpTest.php）。
     *
     * @param string $peer TCP 对端地址（webman Request::getRemoteIp()）
     * @param string $xff  原始 X-Forwarded-For 头
     */
    public static function resolveIp(string $peer, string $xff): string
    {
        $isProxy = filter_var(
            $peer,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
        if (!$isProxy) {
            return $peer;
        }

        $hops = array_filter(array_map('trim', explode(',', $xff)));
        $last = $hops === [] ? '' : (string) end($hops);

        return filter_var($last, FILTER_VALIDATE_IP) !== false ? $last : $peer;
    }

    /**
     * 待检测数据。插件的 guard() 会自行拼 cookie/get/post/file，这里额外补两处：
     *  - 上传文件：把 UploadFile 归一成 {name, tmp_name}，否则整棵子树被跳过；
     *  - UA / Referer：插件只把 _server.* 六项注入待检数据，不含这两个头，
     *    而这两个头在原 SecurityFilter 里是扫的，不补就是能力回退。
     */
    private static function payload(Request $request): array
    {
        // 同名冲突合并交给插件：v1.3.3 起 SecurityGuard::mergeRequestSources() 承担这件事 ——
        // 被顶掉的旧值改挂到 _<来源>.<键>（如 _cookie.evil）下继续进扫描面，值相同时不重复拷，
        // 键在白名单里也不拷（检测器本就跳过）。同一个洞插件自带中间件也中过（v1.3.3 一并修）。
        // 本仓在 v1.3.3 之前自己写过一份等价实现，插件补上后即删除，不留两份互相打架。
        //
        // 调用位置**必须**在 boot() 之后：该 API 内部会 getConfig()，而配置未初始化时
        // 它兜底 init(插件默认配置) —— 那会把本仓这 135 键的配置静默换成默认值。
        $data = Guard::mergeRequestSources([
            'cookie' => $request->cookie() ?? [],
            'get' => $request->get() ?? [],
            'post' => $request->post() ?? [],
            'file' => self::files($request->file() ?? []),
        ]);

        $data['headers.User-Agent'] = (string) $request->header('user-agent', '');
        $data['headers.Referer'] = (string) $request->header('referer', '');
        // 路径本身也是可控输入：旧实现扫 path + queryString，插件只把 path 放进 meta
        // 供日志用（SecurityGuard.php:154-161 只注入五个 _server.*），不扫就等于回退。
        // 查询串已随 get() 进扫描面，这里补的是路径段（如 /admin/v1/user/1%27%20OR…）。
        $data['uri'] = $request->path();

        return $data;
    }

    private static function files(array $files): array
    {
        $out = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadFile) {
                // 临时路径用 getPathname()：UploadFile 继承 SplFileInfo，
                // 构造时把 tmp 路径作为 $fileName 交给父类，没有 getUploadTmpPath() 这个方法。
                $out[$key] = [
                    'name' => $file->getUploadName() ?? '',
                    'tmp_name' => $file->getPathname(),
                ];
            } elseif (is_array($file)) {
                $out[$key] = self::files($file);
            }
        }

        return $out;
    }

    /**
     * 插件检测器的缺口补齐。
     *
     * 逐条对着旧实现（git show HEAD:app/middleware/SecurityFilter.php）比过：下面这几类载荷
     * 在 vendor/erikwang2013/security-php/src/Detector/ 下 grep 无任何命中，而旧实现是拦的。
     * 不补就等于以「换成插件」的名义悄悄降低覆盖 —— 前两条尤其要紧：本仓根目录就有 .env
     * （含数据库口令），且本身就是 git 仓库。
     *
     * key 借用插件已有的 block 模式检测器名：威胁对象最终仍交回 Guard::blockDecision()，
     * 由它按 detectors.<type>.mode 查表决定拦不拦、状态码与文案也都取自插件配置，
     * 不在这里另造一套响应。
     * 插件补上这些规则后，本常量与 gapThreats()/values() 可整体删除。
     */
    private const GAP_PATTERNS = [
        // 插件覆盖 ../ 与 %2e%2e、/etc/(passwd|shadow|hosts|group)、C:\Windows\System32|win.ini、
        // php:// data:// phar:// file:// 与空字节，但不含下面这几类版本库/配置/容器路径。
        'path_traversal' => '/(?:^|[\/\\\\])(?:\.env\b|\.git\/|WEB-INF\/|proc\/self\/|boot\.ini)/i',
        // 插件覆盖 information_schema/pg_catalog 一类侦察语句，但无任何危险 DDL
        // （grep -icE 'drop|truncate|alter' SqlInjectionDetector.php = 0）
        'sql_injection' => '/\b(?:drop|alter|truncate)\s+(?:table|database|index|view)\b/i',
        // 插件覆盖 ;wget|curl|fetch|lynx、|nc、/dev/tcp、> /dev/null、&&wget 一类、
        // 以及 ;bash|sh|python|perl|ruby|php；裸 rm 仅在反引号与 $() 内。下面这些无覆盖。
        'command_injection' => '/[;|&]\s*(?:ls|rm|cmd|powershell|whoami)\s+[-\/]/i',
    ];

    /**
     * @return ThreatResult[] 与 Guard::guard() 同构，可直接并入其返回值
     */
    private static function gapThreats(array $data): array
    {
        $found = [];
        foreach (self::values($data) as $value) {
            foreach (self::GAP_PATTERNS as $type => $pattern) {
                if (!isset($found[$type]) && preg_match($pattern, $value) === 1) {
                    $found[$type] = new ThreatResult(
                        type: $type,
                        severity: 'high',
                        field: 'payload',
                        payload: mb_substr($value, 0, 200),
                        detail: '命中插件未覆盖的载荷模式（SecurityFilter 补齐）'
                    );
                }
            }
        }

        return array_values($found);
    }

    /**
     * 递归取所有标量值。与插件 flattenData() 同口径：只扫值不扫键。
     */
    private static function values(array $data): \Generator
    {
        foreach ($data as $value) {
            if (is_array($value)) {
                yield from self::values($value);
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }

            $raw = (string) $value;
            yield $raw;

            // 归一化：插件的 NormalizationScanner 只作用于 guard() 自己收集的数据，
            // 补不上这里，而 path() 给的是未解码的原始路径（/%2Egit/config 逃得掉匹配）。
            // 沿用插件同款廉价预检：值里没有 % 就绝不调用 urldecode。
            if (str_contains($raw, '%')) {
                $decoded = urldecode($raw);
                if ($decoded !== $raw) {
                    yield $decoded;
                }
            }
        }
    }

    private static function meta(Request $request, string $ip): array
    {
        return [
            'ip' => $ip,
            'method' => $request->method(),
            'uri' => $request->path(),
            'content_length' => (string) $request->header('content-length', ''),
            'content_type' => (string) $request->header('content-type', ''),
            'origin' => (string) $request->header('origin', ''),
            'host' => (string) $request->header('host', ''),
            'x_forwarded_for' => (string) $request->header('x-forwarded-for', ''),
            'transfer_encoding' => (string) $request->header('transfer-encoding', ''),
            'cookies' => $request->cookie() ?? [],
            'user_agent' => (string) $request->header('user-agent', ''),
            // 名称需与 identity.session.headers 保持一致
            'headers' => [
                'authorization' => (string) $request->header('authorization', ''),
                'x-token' => (string) $request->header('x-token', ''),
                'x-auth-token' => (string) $request->header('x-auth-token', ''),
            ],
        ];
    }
}
