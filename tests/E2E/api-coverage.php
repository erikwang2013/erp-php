<?php

/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/**
 * Open ERP 管理端接口覆盖测试（全模块只读遍历）
 *
 * 覆盖 25 个业务模块 + 系统管理的全部 GET 列表/详情端点：
 * - 每个资源端点: GET index（带分页参数）断言 code=0 + data.list 契约，
 *   再用列表首条 hashid 断言 GET show 详情；
 * - 每个只读 index 端点: 断言 code=0 + data 契约；
 * - 系统管理额外覆盖: 登录(自动完成点击验证码)/刷新/登出、用户/角色/权限/配置/日志
 *   读操作、个人中心（用自身当前 real_name 回写，幂等无副作用）。
 * - 采购/销售结算: PUT 原值幂等回写 + DELETE 空密码被拒（验证资源路由存在，无副作用）。
 * 全部读操作为主，不新建/删除任何数据。
 *
 * 用法:
 *   php tests/E2E/api-coverage.php [--base-url=http://127.0.0.1:8788] [--list]
 * 环境变量: BASE_URL / E2E_USER / E2E_PASS
 *
 * 种子适配: CI e2e 流程导入的是最小种子（database/e2e-seed.sql，仅 6 张核心表），
 * 业务表不存在时端点返回 500 + SQL "Base table or view not found"，
 * 此类断言标记为 SKIP(缺种子表) 而非 FAIL；需 install.sql 全量种子才能全绿。
 */

declare(strict_types=1);

const EXIT_OK = 0;
const EXIT_FAIL = 1;

$argvList = $argv ?? [];
$opts = ['list' => false, 'base-url' => getenv('BASE_URL') ?: 'http://127.0.0.1:8788'];
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

$dotenv = parseDotenv(__DIR__ . '/../../.env');
$E2E_USER = getenv('E2E_USER') ?: ($dotenv['E2E_USER'] ?? 'admin');
$E2E_PASS = getenv('E2E_PASS') ?: ($dotenv['E2E_PASS'] ?? 'admin123');
$BASE_URL = $opts['base-url'];

// ============================================================
// 工具函数（与 smoke.php 同款）
// ============================================================

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
        $status = 200;
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

/** 业务码（body.code），非 JSON 时返回 -1 */
function bizCode(array $resp): int
{
    if (isset($resp['body']['code']) && is_int($resp['body']['code'])) {
        return $resp['body']['code'];
    }

    return -1;
}

/** 500 且报 SQL 缺表（e2e 最小种子无业务表）→ 判为环境缺种子，非接口缺陷 */
function missingSeedTable(array $resp): bool
{
    if (bizCode($resp) !== 500) {
        return false;
    }
    $msg = (string) ($resp['body']['message'] ?? '');

    return preg_match('/Base table or view not found|doesn\'t exist|1146/i', $msg) === 1;
}

/** 输出一行结果 */
function emitResult(string $name, string $verdict, string $detail): void
{
    $tag = ['PASS' => 'PASS', 'FAIL' => 'FAIL', 'SKIP' => 'SKIP'][$verdict] ?? $verdict;
    printf("[%s] %-46s %s\n", $tag, $name, $detail);
}

// ============================================================
// 模块 × 端点矩阵: [模块名, 路径前缀, 资源端点(列表+详情), 只读index端点(仅列表)]
// ============================================================

$MATRIX = [
    ['系统管理', '/admin', ['/user', '/role', '/permission'],
        ['/config', '/log', '/dashboard', '/dashboard/sales', '/dashboard/inventory', '/dashboard/finance', '/dashboard/oms', '/dashboard/wms', '/dashboard/tms']],
    ['商品基础', '/admin', ['/product', '/category', '/brand', '/warehouse', '/location', '/supplier', '/customer'], ['/customer-level']],
    ['采购', '/admin', ['/purchase/apply', '/purchase/order', '/purchase/receive', '/purchase/return', '/purchase/settlement'], []],
    ['销售', '/admin', ['/sales/quotation', '/sales/order', '/sales/delivery', '/sales/return', '/sales/settlement'], []],
    ['库存', '/admin', ['/inventory/transfer', '/inventory/check', '/inventory/alert'], ['/inventory', '/inventory/flow']],
    ['财务', '/admin', ['/finance/ar-ap', '/finance/voucher', '/finance/receipt', '/finance/payment', '/finance/expense',
        '/finance/bank-account', '/finance/asset', '/finance/currency', '/finance/exchange-rate', '/finance/budget',
        '/finance/cost-center', '/finance/profit-center'],
        ['/finance/cash-journal', '/finance/general-ledger', '/finance/subsidiary-ledger', '/finance/tax-rate',
            '/finance/tax-record', '/finance/report/balance-sheet', '/finance/report/cash-flow',
            '/finance/report/trial-balance', '/finance/report/account-balance', '/finance/report/profit']],
    ['CRM', '/admin', ['/crm/opportunity', '/crm/follow', '/crm/funnel', '/crm/contact', '/crm/contract',
        '/crm/quotation', '/crm/campaign', '/crm/ticket'],
        ['/crm/pool', '/crm/pool/rules', '/crm/analytics/report', '/crm/analytics/metric']],
    ['审批工作流', '/admin', ['/workflow'], ['/approval/my']],
    ['通知', '/admin', [], ['/notification/my', '/notification/unread-count']],
    ['项目管理', '/admin', ['/project', '/project/task', '/project/timesheet'], []],
    ['人力资源', '/admin', ['/hr/department', '/hr/employee', '/hr/position', '/hr/salary', '/hr/salary-item'],
        ['/hr/attendance', '/hr/leave']],
    ['生产制造', '/admin', ['/mfg/bom', '/mfg/production', '/mfg/routing', '/mfg/workstation', '/mfg/mrp'], []],
    ['自定义报表', '/admin', ['/report', '/report/schedule'], []],
    ['OMS', '/admin', ['/oms/order', '/oms/fulfillment', '/oms/rma', '/oms/channel'], []],
    ['WMS', '/admin', ['/wms/zone', '/wms/location', '/wms/asn', '/wms/receiving', '/wms/putaway',
        '/wms/wave', '/wms/pick', '/wms/pack'], []],
    ['TMS', '/admin', ['/tms/carrier', '/tms/service', '/tms/freight-rate', '/tms/shipment',
        '/tms/tracking', '/tms/freight-invoice'], ['/tms/freight-rate/rate-shop']],
    ['质量管理', '/admin', ['/quality/standard', '/quality/iqc', '/quality/ipqc', '/quality/oqc',
        '/quality/nonconformity'], []],
    ['设备管理', '/admin', ['/eam/equipment', '/eam/maintenance', '/eam/repair', '/eam/spare-part'], []],
    ['文档管理', '/admin', ['/dms/document'], ['/dms/categories']],
    ['BI 数据看板', '/admin', ['/bi/dashboard', '/bi/widget', '/bi/dataset'], []],
];

// ============================================================
// 执行器
// ============================================================

function runAll(string $base, string $user, string $pass, array $matrix): int
{
    $results = []; // ['name','verdict'=>'PASS|FAIL|SKIP','detail']
    $token = null;
    $refreshToken = '';

    // ---- 1. 健康检查 ----
    $resp = httpRequest('GET', "{$base}/health");
    $ok = $resp['status'] === 200 && bizCode($resp) === 0;
    $results[] = ['name' => '1. GET /health', 'verdict' => $ok ? 'PASS' : 'FAIL',
        'detail' => sprintf('HTTP %d, code=%d', $resp['status'], bizCode($resp))];

    // ---- 2. 未授权访问被拒 ----
    $resp = httpRequest('GET', "{$base}/admin/user");
    $code = bizCode($resp);
    $ok = $resp['status'] === 401 || $resp['status'] === 403 || $code === 401 || $code === 403;
    $results[] = ['name' => '2. GET /admin/user 无token被拒', 'verdict' => $ok ? 'PASS' : 'FAIL',
        'detail' => sprintf('HTTP %d, code=%d', $resp['status'], $code)];

    // ---- 3. 登录（自动完成点击验证码） ----
    $cap = httpRequest('POST', "{$base}/api/captcha/generate", ['difficulty' => 'easy']);
    $detail = '';
    if (bizCode($cap) === 0 && isset($cap['body']['data']['key'], $cap['body']['data']['extra']['targets'])) {
        $clicks = [];
        foreach ($cap['body']['data']['extra']['targets'] as $t) {
            $clicks[] = ['x' => (int) $t['x'], 'y' => (int) $t['y']];
        }
        $login = httpRequest('POST', "{$base}/api/auth/login", [
            'username' => $user, 'password' => $pass,
            'captcha_key' => $cap['body']['data']['key'], 'clicks' => $clicks,
        ]);
        $code = bizCode($login);
        if ($code === 0 && !empty($login['body']['data']['access_token'])) {
            $token = $login['body']['data']['access_token'];
            $refreshToken = $login['body']['data']['refresh_token'] ?? '';
            $detail = sprintf('HTTP %d, code=0', $login['status']);
        } else {
            $detail = sprintf('HTTP %d, code=%d, message=%s', $login['status'], $code, $login['body']['message'] ?? '');
        }
    } else {
        $detail = sprintf('验证码生成失败 HTTP %d code=%d (%s)', $cap['status'], bizCode($cap), $cap['body']['message'] ?? '');
    }
    $results[] = ['name' => '3. POST /api/auth/login', 'verdict' => $token !== null ? 'PASS' : 'FAIL', 'detail' => $detail];
    $auth = fn (): array => $token ? ['Authorization' => 'Bearer ' . $token] : [];

    // ---- 4. 系统管理读操作 ----
    $sysEndpoints = [
        ['name' => '4.1 GET /admin/user 列表', 'path' => '/admin/user'],
        ['name' => '4.2 GET /admin/role 列表', 'path' => '/admin/role'],
        ['name' => '4.3 GET /admin/permission 列表', 'path' => '/admin/permission'],
        ['name' => '4.4 GET /admin/config 配置列表', 'path' => '/admin/config'],
        ['name' => '4.5 GET /admin/log 操作日志', 'path' => '/admin/log'],
        ['name' => '4.6 GET /admin/dashboard 仪表盘', 'path' => '/admin/dashboard'],
    ];
    $firstIds = ['/admin/user' => null, '/admin/role' => null, '/admin/permission' => null];
    $selfId = null;
    foreach ($sysEndpoints as $e) {
        if ($token === null) {
            $results[] = ['name' => $e['name'], 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
            continue;
        }
        $resp = httpRequest('GET', "{$base}{$e['path']}?page=1&limit=5", [], $auth());
        $code = bizCode($resp);
        $list = $resp['body']['data']['list'] ?? null;
        $ok = $code === 0 && is_array($list);
        $verdict = $ok ? 'PASS' : (missingSeedTable($resp) ? 'SKIP' : 'FAIL');
        if ($ok && isset($firstIds[$e['path']]) && $list !== []) {
            $firstIds[$e['path']] = $list[0]['id'] ?? null;
        }
        $results[] = ['name' => $e['name'], 'verdict' => $verdict,
            'detail' => sprintf('HTTP %d, code=%d, list=%s', $resp['status'], $code, is_array($list) ? count($list) . ' 条' : '非数组' . ($resp['body']['message'] ?? ''))];
    }
    // 用户详情: 取列表首条 id + 定位当前登录用户（供个人中心幂等回写）
    if ($token !== null && is_array($resp['body']['data']['list'] ?? null)) {
        foreach ($resp['body']['data']['list'] as $item) {
            if (($item['username'] ?? '') === $user) {
                $selfId = $item['id'] ?? null;
                break;
            }
        }
    }
    foreach ($firstIds as $path => $id) {
        $label = ['/admin/user' => '用户', '/admin/role' => '角色', '/admin/permission' => '权限'][$path];
        if ($id === null) {
            $results[] = ['name' => "4.7 GET {$path}/{id} {$label}详情", 'verdict' => $token === null ? 'SKIP' : 'SKIP',
                'detail' => $token === null ? '前置登录失败' : '列表无数据, 跳过详情'];
            continue;
        }
        $resp = httpRequest('GET', "{$base}{$path}/{$id}", [], $auth());
        $code = bizCode($resp);
        $data = $resp['body']['data'] ?? null;
        $ok = $code === 0 && is_array($data) && isset($data['id']);
        $results[] = ['name' => "4.7 GET {$path}/{$id} {$label}详情", 'verdict' => $ok ? 'PASS' : 'FAIL',
            'detail' => sprintf('HTTP %d, code=%d%s', $resp['status'], $code, $ok ? ', 含id' : ', ' . ($resp['body']['message'] ?? ''))];
    }

    // ---- 5. 个人中心（用自身当前 real_name 幂等回写，无副作用） ----
    if ($token !== null && $selfId !== null) {
        $me = httpRequest('GET', "{$base}/admin/user/{$selfId}", [], $auth());
        $realName = $me['body']['data']['real_name'] ?? '';
        $up = httpRequest('PUT', "{$base}/admin/profile", ['real_name' => $realName], $auth());
        $code = bizCode($up);
        $ok = $code === 0 && ($up['body']['data']['real_name'] ?? null) === $realName;
        $results[] = ['name' => '5. PUT /admin/profile 个人中心(幂等回写)', 'verdict' => $ok ? 'PASS' : 'FAIL',
            'detail' => sprintf('HTTP %d, code=%d', $up['status'], $code)];
    } else {
        $results[] = ['name' => '5. PUT /admin/profile 个人中心', 'verdict' => 'SKIP', 'detail' => '前置登录失败或未找到自身用户'];
    }

    // ---- 6. 刷新 token 并可继续使用 ----
    if ($token !== null && $refreshToken !== '') {
        $resp = httpRequest('POST', "{$base}/api/auth/refresh", ['refresh_token' => $refreshToken]);
        $code = bizCode($resp);
        $newToken = $resp['body']['data']['access_token'] ?? null;
        $probe = $code === 0 && is_string($newToken) && $newToken !== ''
            ? httpRequest('GET', "{$base}/admin/user?page=1&limit=1", [], ['Authorization' => 'Bearer ' . $newToken])
            : null;
        $ok = $code === 0 && $probe !== null && bizCode($probe) === 0;
        $results[] = ['name' => '6. POST /api/auth/refresh', 'verdict' => $ok ? 'PASS' : 'FAIL',
            'detail' => sprintf('HTTP %d, code=%d%s', $resp['status'], $code, $probe !== null ? ', 新token访问 code=' . bizCode($probe) : '')];
    } else {
        $results[] = ['name' => '6. POST /api/auth/refresh', 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
    }

    // ---- 7. 业务模块矩阵遍历 ----
    $listCount = 0;
    $showCount = 0;
    foreach ($matrix as $mod) {
        [$module, $prefix, $resources, $indexes] = $mod;
        foreach ($resources as $path) {
            $listCount++;
            $name = "7. GET {$prefix}{$path} 列表[{$module}]";
            if ($token === null) {
                $results[] = ['name' => $name, 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
                continue;
            }
            $resp = httpRequest('GET', "{$base}{$prefix}{$path}?page=1&limit=5", [], $auth());
            $code = bizCode($resp);
            $list = $resp['body']['data']['list'] ?? null;
            $ok = $code === 0 && is_array($list);
            $verdict = $ok ? 'PASS' : (missingSeedTable($resp) ? 'SKIP' : 'FAIL');
            $detail = sprintf('HTTP %d, code=%d, list=%s', $resp['status'], $code, is_array($list) ? count($list) . ' 条' : '非数组');
            if (!$ok) {
                $detail .= $verdict === 'SKIP' ? ' [e2e最小种子无业务表, 需install.sql全量种子]' : ' [' . ($resp['body']['message'] ?? '') . ']';
            }
            $results[] = ['name' => $name, 'verdict' => $verdict, 'detail' => $detail];

            // 详情: 用列表首条 hashid
            $firstId = is_array($list) && $list !== [] ? ($list[0]['id'] ?? null) : null;
            $showName = "7. GET {$prefix}{$path}/{{id}} 详情[{$module}]";
            if ($firstId === null) {
                $results[] = ['name' => $showName, 'verdict' => 'SKIP', 'detail' => '列表无数据, 跳过详情'];
                continue;
            }
            $showCount++;
            $resp = httpRequest('GET', "{$base}{$prefix}{$path}/{$firstId}", [], $auth());
            $code = bizCode($resp);
            $data = $resp['body']['data'] ?? null;
            $ok = $code === 0 && is_array($data) && isset($data['id']);
            $verdict = $ok ? 'PASS' : (missingSeedTable($resp) ? 'SKIP' : 'FAIL');
            $results[] = ['name' => $showName, 'verdict' => $verdict,
                'detail' => sprintf('HTTP %d, code=%d%s', $resp['status'], $code, $ok ? ', 含id' : ($verdict === 'SKIP' ? ' [缺种子表]' : ', ' . ($resp['body']['message'] ?? '')))];
        }
        foreach ($indexes as $path) {
            $listCount++;
            $name = "7. GET {$prefix}{$path} 只读[{$module}]";
            if ($token === null) {
                $results[] = ['name' => $name, 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
                continue;
            }
            $resp = httpRequest('GET', "{$base}{$prefix}{$path}", [], $auth());
            $code = bizCode($resp);
            $ok = $code === 0 && array_key_exists('data', $resp['body'] ?? []);
            $verdict = $ok ? 'PASS' : (missingSeedTable($resp) ? 'SKIP' : 'FAIL');
            $results[] = ['name' => $name, 'verdict' => $verdict,
                'detail' => sprintf('HTTP %d, code=%d%s', $resp['status'], $code, $ok ? '' : ($verdict === 'SKIP' ? ' [缺种子表]' : ' [' . ($resp['body']['message'] ?? '') . ']'))];
        }
    }

    // ---- 8. 结算 PUT/DELETE 路由存在断言（幂等/无副作用，同个人中心回写模式） ----
    // PUT: 读取当前 name 原值回写 → code=0（路由未注册时 fallback 返回 "404 Not Found"）；
    // DELETE: 空密码被二次确认拒绝 → code=422（证明路由分发到 destroy，不删除任何数据）。
    $settlementChecks = [
        ['name' => '采购结算', 'path' => '/purchase/settlement'],
        ['name' => '销售结算', 'path' => '/sales/settlement'],
    ];
    foreach ($settlementChecks as $s) {
        $label = "8. PUT/DELETE {$s['path']}/{{id}} {$s['name']}";
        if ($token === null) {
            $results[] = ['name' => $label, 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
            continue;
        }
        $list = httpRequest('GET', "{$base}{$s['path']}?page=1&limit=1", [], $auth());
        $firstId = $list['body']['data']['list'][0]['id'] ?? null;
        if ($firstId === null) {
            $results[] = ['name' => $label, 'verdict' => 'SKIP',
                'detail' => missingSeedTable($list) ? 'e2e最小种子缺业务表, 跳过' : '列表无数据, 跳过'];
            continue;
        }
        $show = httpRequest('GET', "{$base}{$s['path']}/{$firstId}", [], $auth());
        $name = $show['body']['data']['name'] ?? null;
        if (!is_string($name) || $name === '') {
            $results[] = ['name' => $label, 'verdict' => 'SKIP', 'detail' => '详情无 name 字段, 跳过 PUT 幂等回写'];
            continue;
        }
        $put = httpRequest('PUT', "{$base}{$s['path']}/{$firstId}", ['name' => $name], $auth());
        $putOk = bizCode($put) === 0 && ($put['body']['data']['id'] ?? null) === $firstId;
        $results[] = ['name' => "{$label} PUT幂等回写", 'verdict' => $putOk ? 'PASS' : 'FAIL',
            'detail' => sprintf('HTTP %d, code=%d%s', $put['status'], bizCode($put), $putOk ? '' : ', ' . ($put['body']['message'] ?? ''))];
        $del = httpRequest('DELETE', "{$base}{$s['path']}/{$firstId}", [], $auth());
        $delOk = bizCode($del) === 422;
        $results[] = ['name' => "{$label} DELETE空密码被拒", 'verdict' => $delOk ? 'PASS' : 'FAIL',
            'detail' => sprintf('HTTP %d, code=%d%s', $del['status'], bizCode($del), $delOk ? '' : ', ' . ($del['body']['message'] ?? ''))];
    }

    // ---- 9. 登出并验证 token 失效 ----
    if ($token !== null) {
        $resp = httpRequest('POST', "{$base}/admin/profile/logout", [], $auth());
        $code = bizCode($resp);
        $probe = httpRequest('GET', "{$base}/admin/user?page=1&limit=1", [], $auth());
        $pCode = bizCode($probe);
        $ok = $code === 0 && ($pCode === 401 || $pCode === 403);
        $results[] = ['name' => '9. POST /admin/profile/logout', 'verdict' => $ok ? 'PASS' : 'FAIL',
            'detail' => sprintf('登出 code=%d, 登出后访问 code=%d', $code, $pCode)];
    } else {
        $results[] = ['name' => '9. POST /admin/profile/logout', 'verdict' => 'SKIP', 'detail' => '前置登录失败'];
    }

    // ---- 汇总 ----
    echo "\n===== API 覆盖测试结果 =====\n";
    echo "BASE_URL : {$base}\n";
    echo '覆盖模块 : ' . count($matrix) . ' 个, 端点 ' . $listCount . ' 个(含详情 ' . $showCount . ")\n";
    echo "----------------------------------------\n";
    $stats = ['PASS' => 0, 'FAIL' => 0, 'SKIP' => 0];
    foreach ($results as $r) {
        emitResult($r['name'], $r['verdict'], $r['detail']);
        $stats[$r['verdict']]++;
    }
    echo "----------------------------------------\n";
    printf("汇总: PASS=%d FAIL=%d SKIP=%d (共 %d 条断言)\n", $stats['PASS'], $stats['FAIL'], $stats['SKIP'], count($results));
    if ($stats['SKIP'] > 0) {
        echo "提示: SKIP 多为 e2e 最小种子缺少业务表, 请用 install.sql 全量种子执行以获得完整覆盖。\n";
    }

    return $stats['FAIL'] === 0 ? EXIT_OK : EXIT_FAIL;
}

// ============================================================
// 入口
// ============================================================

if ($opts['list']) {
    echo "===== API 覆盖测试 — 模块×端点矩阵 =====\n";
    foreach ($MATRIX as $mod) {
        printf(
            "  %-12s %s%s\n",
            $mod[0],
            implode(' ', array_map(fn ($p) => $mod[1] . $p, $mod[2])),
            $mod[3] !== [] ? ' | 只读: ' . implode(' ', array_map(fn ($p) => $mod[1] . $p, $mod[3])) : ''
        );
    }
    echo "\n用法: php tests/E2E/api-coverage.php [--base-url=http://127.0.0.1:8788] [--list]\n";
    exit(EXIT_OK);
}

exit(runAll($BASE_URL, $E2E_USER, $E2E_PASS, $MATRIX));
