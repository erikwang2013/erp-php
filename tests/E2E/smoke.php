<?php

/**
 * Open ERP E2E 冒烟测试脚本
 *
 * 真实 HTTP 走通核心链路，供 CI 或本地服务运行时执行。
 * 用法:
 *   php tests/E2E/smoke.php                # 跑全部 10 条链路
 *   php tests/E2E/smoke.php --list         # 仅列出链路清单
 *   php tests/E2E/smoke.php --base-url=http://127.0.0.1:8788
 *
 * 环境变量（优先级: 命令行参数 > 环境变量 > .env 文件 > 内置默认）:
 *   BASE_URL      服务地址，默认 http://127.0.0.1:8787
 *   E2E_USER      测试账号用户名，默认 admin（需预先在数据库中创建）
 *   E2E_PASS      测试账号密码，默认 admin123（需与预置账号一致）
 *
 * 说明:
 * - 登录需点击验证码（captcha_key + clicks），脚本自动调用
 *   POST /api/captcha/generate 获取 targets 坐标并回填，无需人工预置；
 *   若验证码服务不可用（generate 失败），脚本会 FAIL 并提示
 *   使用 E2E_CAPTCHA_CODE（预留：将来若切换为文本验证码，可在此注入）。
 * - 10 条链路相互独立执行，任一失败不中断后续；全部通过 exit 0，有失败 exit 1。
 * - 注意: 本项目 webman 的 json() 始终返回 HTTP 200，业务码在 body.code，
 *   因此"未授权被拒"以 body.code ∈ {401,403} 判定，HTTP 状态码仅作参考输出。
 */

declare(strict_types=1);

const EXIT_OK = 0;
const EXIT_FAIL = 1;

// ============================================================
// 参数解析
// ============================================================

$argvList = $argv ?? [];
$opts = [
    'list' => false,
    'base-url' => getenv('BASE_URL') ?: 'http://127.0.0.1:8787',
];
for ($i = 1; $i < count($argvList); $i++) {
    $arg = $argvList[$i];
    if ($arg === '--list') {
        $opts['list'] = true;
    } elseif (str_starts_with($arg, '--base-url=')) {
        $opts['base-url'] = rtrim(substr($arg, 11), '/');
    } elseif (str_starts_with($arg, '--')) {
        fwrite(STDERR, "未知参数: {$arg}\n");
        exit(EXIT_FAIL);
    }
}

// 测试账号: 命令行/环境变量 > .env 文件 > 默认
$envFile = __DIR__ . '/../../.env';
$dotenv = parseDotenv($envFile);
$E2E_USER = getenv('E2E_USER') ?: ($dotenv['E2E_USER'] ?? 'admin');
$E2E_PASS = getenv('E2E_PASS') ?: ($dotenv['E2E_PASS'] ?? 'admin123');
$E2E_CAPTCHA_CODE = getenv('E2E_CAPTCHA_CODE') ?: ($dotenv['E2E_CAPTCHA_CODE'] ?? '');

$BASE_URL = $opts['base-url'];

// ============================================================
// 工具函数
// ============================================================

/** 解析 .env 文件（简单 KEY=VALUE，忽略注释与空行） */
function parseDotenv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
            $result[$m[1]] = trim($m[2], " \t\"'");
        }
    }

    return $result;
}

/** HTTP 请求。返回 ['status'=>int, 'body'=>mixed(已解析 JSON), 'raw'=>string] */
function httpRequest(string $method, string $url, array $body = [], array $headers = [], int $timeout = 10): array
{
    $json = $body === [] ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    $headerLines = ['Content-Type: application/json'];
    foreach ($headers as $k => $v) {
        $headerLines[] = "{$k}: {$v}";
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0 || $raw === false) {
            return ['status' => 0, 'body' => null, 'raw' => '', 'error' => curl_strerror($errno)];
        }
    } else {
        // 无 curl 扩展时退化为 stream context
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $json,
            'ignore_errors' => true,
            'timeout' => $timeout,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['status' => 0, 'body' => null, 'raw' => '', 'error' => 'stream request failed'];
        }
        $status = 200; // ignore_errors 模式下解析状态行
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => $status, 'body' => null, 'raw' => $raw];
    }

    return ['status' => $status, 'body' => $decoded, 'raw' => $raw];
}

/** 断言帮助：检查业务 code 字段 */
function bizCode(array $resp): int
{
    if (isset($resp['body']['code']) && is_int($resp['body']['code'])) {
        return $resp['body']['code'];
    }

    return -1; // 非 JSON 或缺少 code
}

/** 输出一行结果 */
function emitResult(string $name, bool $ok, string $detail): void
{
    printf("[%s] %-42s %s\n", $ok ? 'PASS' : 'FAIL', $name, $detail);
}

/** 汇总 10 条链路的执行器（由 CLI 调用） */
function runAll(array $config): int
{
    $base = $config['base_url'];
    $user = $config['user'];
    $pass = $config['pass'];
    $results = []; // ['name'=>, 'ok'=>bool, 'detail'=>]
    $shared = ['token' => null, 'refresh_token' => null, 'created_id' => null];

    // ---- 链路 1: 健康检查 ----
    $name = '1.health 健康检查';
    $resp = httpRequest('GET', "{$base}/health");
    $ok = $resp['status'] === 200 && bizCode($resp) === 0;
    $detail = sprintf('HTTP %d, code=%d', $resp['status'], bizCode($resp));
    if ($ok && isset($resp['body']['data'])) {
        $d = $resp['body']['data'];
        $detail .= sprintf(', db=%s redis=%s', $d['database'] ?? '?', $d['redis'] ?? '?');
        if (($d['database'] ?? '') !== 'ok') {
            $detail .= ' [警告: database 非 ok]';
        }
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 2: 登录（自动完成点击验证码） ----
    $name = '2.login 登录获取 token';
    $ok = false;
    $detail = '';
    // 2a. 生成验证码
    $cap = httpRequest('POST', "{$base}/api/captcha/generate", ['difficulty' => 'easy']);
    if (bizCode($cap) === 0 && isset($cap['body']['data']['key'], $cap['body']['data']['extra']['targets'])) {
        $capKey = $cap['body']['data']['key'];
        $clicks = [];
        foreach ($cap['body']['data']['extra']['targets'] as $t) {
            $clicks[] = ['x' => (int) $t['x'], 'y' => (int) $t['y']];
        }
        $loginBody = [
            'username' => $user,
            'password' => $pass,
            'captcha_key' => $capKey,
            'clicks' => $clicks,
        ];
        $login = httpRequest('POST', "{$base}/api/auth/login", $loginBody);
        $code = bizCode($login);
        if ($code === 0 && isset($login['body']['data']['access_token'])) {
            $shared['token'] = $login['body']['data']['access_token'];
            $shared['refresh_token'] = $login['body']['data']['refresh_token'] ?? '';
            $ok = true;
            $detail = sprintf('HTTP %d, code=0, token 获取成功', $login['status']);
        } else {
            $msg = $login['body']['message'] ?? '未知错误';
            $detail = sprintf('HTTP %d, code=%d, message=%s', $login['status'], $code, $msg);
            if ($code === 422 && str_contains($msg, '验证码')) {
                $detail .= ' [验证码自动完成失败; 若持续出现请检查验证码服务或设置 E2E_CAPTCHA_CODE]';
            }
        }
    } else {
        $detail = sprintf(
            '验证码生成失败 HTTP %d code=%d (%s)',
            $cap['status'],
            bizCode($cap),
            $cap['body']['message'] ?? ($cap['error'] ?? 'JSON 解析失败')
        );
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // 依赖 token 的链路: token 缺失时标记失败但继续执行
    $authHeaders = fn (): array => $shared['token'] ? ['Authorization' => 'Bearer ' . $shared['token']] : [];

    // ---- 链路 3: 带 token 访问受保护列表 ----
    $name = '3.admin/user 列表(带token)';
    $resp = httpRequest('GET', "{$base}/admin/user?page=1&limit=5", [], $authHeaders());
    $code = bizCode($resp);
    $ok = $shared['token'] !== null && $resp['status'] === 200 && $code === 0;
    if ($shared['token'] === null) {
        $detail = 'SKIP: 前置登录失败';
    } else {
        $list = $resp['body']['data']['list'] ?? null;
        $detail = sprintf('HTTP %d, code=%d, list=%s', $resp['status'], $code, is_array($list) ? count($list) . ' 条' : '非数组');
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 4: 不带 token 访问受保护端点被拒 ----
    $name = '4.admin/user 拒绝未授权';
    $resp = httpRequest('GET', "{$base}/admin/user");
    $code = bizCode($resp);
    $ok = $resp['status'] === 401 || $resp['status'] === 403 || $code === 401 || $code === 403;
    $detail = sprintf('HTTP %d, code=%d (%s)', $resp['status'], $code, $resp['body']['message'] ?? '');
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 5: 创建资源（用户） ----
    $name = '5.admin/user 创建用户';
    $username = 'e2e_' . date('YmdHis') . '_' . random_int(100, 999);
    $createBody = [
        'username' => $username,
        'password' => 'E2ePass' . random_int(1000, 9999),
        'real_name' => 'E2E测试用户',
        'status' => 1,
    ];
    $resp = httpRequest('POST', "{$base}/admin/user", $createBody, $authHeaders());
    $code = bizCode($resp);
    $ok = $shared['token'] !== null && $code === 0 && !empty($resp['body']['data']['id']);
    if ($ok) {
        $shared['created_id'] = $resp['body']['data']['id'];
        $shared['created_username'] = $username;
        $detail = sprintf('HTTP %d, code=0, id=%s', $resp['status'], $shared['created_id']);
    } else {
        $detail = sprintf(
            'HTTP %d, code=%d, message=%s%s',
            $resp['status'],
            $code,
            $resp['body']['message'] ?? '',
            $shared['token'] === null ? ' [前置登录失败]' : ''
        );
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 6: 读取刚创建的资源 ----
    $name = '6.admin/user/{id} 读取';
    $ok = false;
    if ($shared['token'] !== null && $shared['created_id'] !== null) {
        $resp = httpRequest('GET', "{$base}/admin/user/{$shared['created_id']}", [], $authHeaders());
        $code = bizCode($resp);
        $realName = $resp['body']['data']['real_name'] ?? null;
        $ok = $code === 0 && $realName === $createBody['real_name'];
        $detail = sprintf('HTTP %d, code=%d, real_name=%s', $resp['status'], $code, var_export($realName, true));
    } else {
        $detail = 'SKIP: 前置创建失败';
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 7: 更新资源 ----
    $name = '7.admin/user/{id} 更新';
    $ok = false;
    if ($shared['token'] !== null && $shared['created_id'] !== null) {
        $resp = httpRequest(
            'PUT',
            "{$base}/admin/user/{$shared['created_id']}",
            ['real_name' => 'E2E已更新'],
            $authHeaders()
        );
        $code = bizCode($resp);
        $realName = $resp['body']['data']['real_name'] ?? null;
        $ok = $code === 0 && $realName === 'E2E已更新';
        $detail = sprintf('HTTP %d, code=%d, real_name=%s', $resp['status'], $code, var_export($realName, true));
    } else {
        $detail = 'SKIP: 前置创建失败';
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 8: 删除资源（需密码二次确认） ----
    $name = '8.admin/user/{id} 删除';
    $ok = false;
    if ($shared['token'] !== null && $shared['created_id'] !== null) {
        $resp = httpRequest(
            'DELETE',
            "{$base}/admin/user/{$shared['created_id']}",
            ['password' => $pass],
            $authHeaders()
        );
        $code = bizCode($resp);
        $ok = $code === 0;
        $detail = sprintf('HTTP %d, code=%d, message=%s', $resp['status'], $code, $resp['body']['message'] ?? '');
        if ($code === 422) {
            $detail .= ' [删除需当前管理员密码二次确认, 已使用 E2E_PASS]';
        }
    } else {
        $detail = 'SKIP: 前置创建失败';
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 9: 仪表盘业务数据 ----
    $name = '9.admin/dashboard 仪表盘';
    $resp = httpRequest('GET', "{$base}/admin/dashboard", [], $authHeaders());
    $code = bizCode($resp);
    $hasStats = isset($resp['body']['data']['stats']) && is_array($resp['body']['data']['stats']);
    $ok = $shared['token'] !== null && $code === 0 && $hasStats;
    if ($shared['token'] === null) {
        $detail = 'SKIP: 前置登录失败';
    } else {
        $statsCount = is_array($resp['body']['data']['stats'] ?? null) ? count($resp['body']['data']['stats']) : 0;
        $detail = sprintf('HTTP %d, code=%d, stats=%d 项', $resp['status'], $code, $statsCount);
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 链路 10: 刷新 token 并可继续使用 ----
    $name = '10.auth/refresh 刷新token';
    $ok = false;
    if ($shared['token'] !== null && $shared['refresh_token'] !== '') {
        $resp = httpRequest('POST', "{$base}/api/auth/refresh", ['refresh_token' => $shared['refresh_token']]);
        $code = bizCode($resp);
        $newToken = $resp['body']['data']['access_token'] ?? null;
        if ($code === 0 && is_string($newToken) && $newToken !== '') {
            $probe = httpRequest('GET', "{$base}/admin/user?page=1&limit=1", [], ['Authorization' => 'Bearer ' . $newToken]);
            $ok = bizCode($probe) === 0;
            $detail = sprintf('HTTP %d, code=0, 新token访问 /admin/user code=%d', $resp['status'], bizCode($probe));
        } else {
            $detail = sprintf('HTTP %d, code=%d, message=%s', $resp['status'], $code, $resp['body']['message'] ?? '');
        }
    } else {
        $detail = 'SKIP: 前置登录失败';
    }
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];

    // ---- 输出与退出码 ----
    echo "\n===== E2E 冒烟测试结果 =====\n";
    echo "BASE_URL : {$base}\n";
    echo "E2E_USER : {$user}\n";
    echo "----------------------------------------\n";
    $passed = 0;
    foreach ($results as $r) {
        emitResult($r['name'], $r['ok'], $r['detail']);
        if ($r['ok']) {
            $passed++;
        }
    }
    $total = count($results);
    echo "----------------------------------------\n";
    printf("汇总: %d/%d 通过\n", $passed, $total);

    return $passed === $total ? EXIT_OK : EXIT_FAIL;
}

// ============================================================
// 链路清单
// ============================================================

function listChains(): void
{
    $chains = [
        '1.  GET  /health                   健康检查返回 200 且 code=0 (db/redis ok)',
        '2.  POST /api/auth/login           登录(自动完成点击验证码) 获取 access_token',
        '3.  GET  /admin/user                带 token 访问受保护列表成功',
        '4.  GET  /admin/user                不带 token 被拒 (body.code 401/403)',
        '5.  POST /admin/user                创建资源成功并返回 id',
        '6.  GET  /admin/user/{id}           读取刚创建资源, 数据一致',
        '7.  PUT  /admin/user/{id}           更新资源成功',
        '8.  DELETE /admin/user/{id}         删除资源成功(需密码二次确认)',
        '9.  GET  /admin/dashboard           仪表盘返回业务数据 (stats)',
        '10. POST /api/auth/refresh          刷新 token, 新 token 可访问受保护端点',
    ];
    echo "===== Open ERP E2E 冒烟测试 — 10 条链路 =====\n";
    foreach ($chains as $c) {
        echo "  {$c}\n";
    }
    echo "\n用法: php tests/E2E/smoke.php [--base-url=http://127.0.0.1:8787] [--list]\n";
    echo "环境变量: BASE_URL / E2E_USER / E2E_PASS / E2E_CAPTCHA_CODE (预留)\n";
}

// ============================================================
// 入口
// ============================================================

if ($opts['list']) {
    listChains();
    exit(EXIT_OK);
}

exit(runAll([
    'base_url' => $BASE_URL,
    'user' => $E2E_USER,
    'pass' => $E2E_PASS,
]));
