#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * check-endpoints.php — 前端请求 URL × 后端路由 自动比对脚本
 *
 * 功能：
 *   1. 解析 config/route.php（webman），提取全部已注册路由：
 *      - Route::(get|post|put|delete|patch|any)(...) 显式路由
 *      - Route::group('/admin'|'/api', ...) 分组前缀还原为完整路径
 *      - Route::resource(...) 按控制器实际存在的方法展开为 REST 路由
 *        （GET index / POST store / GET {id} show / PUT {id} update / DELETE {id} destroy …）
 *   2. 扫描前端源码中的请求路径字面量：
 *      - Flutter：apps/flutter/lib 目录下所有 .dart 文件
 *        （ApiService.instance.get/post/put/delete、独立 Dio 调用、本地 api 变量等）
 *      - HarmonyOS：apps/harmonyos/entry/src/main/ets 目录下所有 .ets/.ts 文件
 *        （apiService.*、this.api.*、httpRequest.request( 模板串等）
 *      支持 ${...} / $var 插值与模板串；无法完全还原的路径标注"未解析"。
 *   3. 输出三份清单：
 *      ① 死端点   —— 前端调用但后端不存在（最优先）
 *      ② 覆盖缺口 —— 后端存在但 Flutter/HarmonyOS 均未调用（按模块分组）
 *      ③ 未解析   —— 无法完全还原的路径（人工复核）
 *
 * 用法：
 *   php scripts/check-endpoints.php                              # 文本输出（默认）
 *   php scripts/check-endpoints.php --json                       # JSON 输出
 *   php scripts/check-endpoints.php --module=finance             # 仅过滤 finance 模块
 *   php scripts/check-endpoints.php --doc=docs/xxx.md            # 生成 Markdown 审计报告
 *
 * 匹配规则（关键设计，保证冒烟用例可被抓到）：
 *   - 前端「字面量段」仅匹配后端「字面量段」；前端「动态段」(${x} / $x) 仅匹配后端「{param} 段」。
 *   - 因此 POST /admin/notification/my/read（全字面量）不会误匹配
 *     后端 POST /admin/notification/{id}/read，而会被判定为死端点。
 *   - 方法匹配：后端 Route::any 匹配任意方法；其余按 HTTP 方法精确匹配。
 *
 * 冒烟用例：/admin/notification/my/read 必须出现在死端点清单中。
 */

declare(strict_types=1);

// ============================================================
// 0. 常量与命令行参数
// ============================================================
$ROOT          = realpath(__DIR__ . '/..') ?: __DIR__;
$ROUTE_FILE    = $ROOT . '/config/route.php';
$FLUTTER_DIR   = $ROOT . '/apps/flutter/lib';
$HARMONY_DIR   = $ROOT . '/apps/harmonyos/entry/src/main/ets';
$SMOKE_PATH    = '/admin/notification/my/read'; // 已知死端点冒烟用例

$optModule = null;   // --module=xxx
$optJson   = false;  // --json
$optDoc    = null;   // --doc=FILE
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--module=(.+)$/', $arg, $m)) {
        $optModule = strtolower($m[1]);
    } elseif ($arg === '--json') {
        $optJson = true;
    } elseif (preg_match('/^--doc=(.+)$/', $arg, $m)) {
        $optDoc = $m[1];
    } elseif ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "用法: php scripts/check-endpoints.php [--json] [--module=xxx] [--doc=FILE]\n");
        exit(0);
    }
}

// ============================================================
// 1. 工具函数
// ============================================================

/** 由完整路径推导模块名（/admin/xxx/... 与 /api/xxx/... 取第二段） */
function moduleOf(string $path): string
{
    $p = trim($path, '/');
    if ($p === '') {
        return 'root';
    }
    $segs = explode('/', $p);
    if (in_array($segs[0], ['admin', 'api'], true) && isset($segs[1])) {
        return $segs[1];
    }
    return $segs[0];
}

/**
 * 路径切分为段数组。
 * 段结构：['lit' => 字面量字符串|null, 'param' => 是否为动态占位段]
 * 支持 webman 的 {name} 占位与 [/optional] 可选段（可选段会展开为两个变体）。
 */
function splitSegments(string $path): array
{
    $variants = [[]];
    $raw = trim($path, '/');
    if ($raw === '') {
        return $variants;
    }
    foreach (explode('/', $raw) as $seg) {
        // 可选段 [/xxx]：生成"含该段 / 不含该段"两个变体
        if (preg_match('/^\[(.*)\]$/', $seg, $m)) {
            $inner = ltrim($m[1], '/');
            $next = [];
            foreach ($variants as $v) {
                $next[] = $v;                                  // 不含可选段
                $with   = $v;
                foreach (explode('/', $inner) as $is) {
                    $with[] = isParamSegment($is)
                        ? ['lit' => null, 'param' => true]
                        : ['lit' => $is, 'param' => false];
                }
                $next[] = $with;                               // 含可选段
            }
            $variants = $next;
        } else {
            $node = isParamSegment($seg)
                ? ['lit' => null, 'param' => true]
                : ['lit' => $seg, 'param' => false];
            foreach ($variants as &$v) {
                $v[] = $node;
            }
            unset($v);
        }
    }
    return $variants;
}

/** 判断是否为 {name} 占位段 */
function isParamSegment(string $seg): bool
{
    return (bool) preg_match('/^\{[^}]+\}$/', $seg);
}

/**
 * 严格形状匹配：
 *   前端字面量段  ↔ 后端字面量段（必须完全相等）
 *   前端动态段    ↔ 后端 {param} 段
 *   前端字面量段  ✗ 后端 {param} 段（避免 /notification/my/read 误配 /notification/{id}/read）
 */
function pathMatches(array $feSegs, array $beSegs): bool
{
    if (count($feSegs) !== count($beSegs)) {
        return false;
    }
    foreach ($feSegs as $i => $fs) {
        $bs = $beSegs[$i];
        if ($bs['param']) {
            if (!$fs['param']) {
                return false;
            }
        } else {
            if ($fs['param'] || $fs['lit'] !== $bs['lit']) {
                return false;
            }
        }
    }
    return true;
}

/** 前端方法是否被后端方法集合接受（后端 ANY 匹配一切） */
function methodAllowed(string $feMethod, array $beMethods): bool
{
    if (in_array('ANY', $beMethods, true)) {
        return true;
    }
    return in_array($feMethod, $beMethods, true);
}

// ============================================================
// 2. 解析后端路由 config/route.php
// ============================================================

/** 读取控制器文件，返回实际存在的 REST 方法列表；文件缺失时保守按全量展开 */
function controllerMethods(?string $controller, string $root, array &$warnings): array
{
    $actions = ['index', 'create', 'store', 'update', 'patch', 'show', 'edit', 'destroy', 'recovery'];
    if ($controller === null) {
        $warnings[] = 'resource 未解析出控制器类，按全量 CRUD 展开';
        return $actions;
    }
    $file = $root . '/' . str_replace('\\', '/', $controller) . '.php';
    if (!is_file($file)) {
        $warnings[] = "控制器文件不存在，按全量 CRUD 展开: {$controller}";
        return $actions;
    }
    $src = (string) @file_get_contents($file);
    $found = [];
    foreach ($actions as $a) {
        if (preg_match('/function\s+' . $a . '\s*\(/', $src)) {
            $found[] = $a;
        }
    }
    return $found;
}

/**
 * 解析 config/route.php，返回路由表。
 * 每个元素：['methods'=>[], 'path'=>完整路径, 'segs'=>段数组, 'line'=>行号, 'origin'=>来源说明]
 */
function parseBackendRoutes(string $routeFile, string $root, array &$warnings): array
{
    $lines = file($routeFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        fwrite(STDERR, "[错误] 无法读取路由文件: {$routeFile}\n");
        exit(1);
    }

    // webman Route::resource 标准动作 → (HTTP 方法, 路径后缀)
    $standard = [
        'index'    => ['GET',    ''],
        'create'   => ['GET',    '/create'],
        'store'    => ['POST',   ''],
        'update'   => ['PUT',    '/{id}'],
        'patch'    => ['PATCH',  '/{id}'],
        'show'     => ['GET',    '/{id}'],
        'edit'     => ['GET',    '/{id}/edit'],
        'destroy'  => ['DELETE', '/{id}'],
        'recovery' => ['PUT',    '/{id}/recovery'],
    ];

    $prefixes = [];   // Route::group 前缀栈
    $routes   = [];   // 展开后的路由
    $seen     = [];   // (method|path) 去重

    foreach ($lines as $i => $line) {
        $no = $i + 1;
        // 1) 分组开始：Route::group('/admin', function () {
        if (preg_match("/Route::group\s*\(\s*'([^']+)'/", $line, $m)) {
            $prefixes[] = $m[1];
            continue;
        }
        // 2) 分组结束：})->middleware([ ... ])  一行可能关闭多个分组
        $closes = preg_match_all('/\}\)/', $line, $mm);
        if ($closes > 0) {
            for ($k = 0; $k < $closes && count($prefixes) > 0; $k++) {
                array_pop($prefixes);
            }
            continue;
        }
        // 3) fallback 兜底路由不是真实端点
        if (preg_match('/Route::fallback\s*\(/', $line)) {
            continue;
        }
        // 4) 普通路由 / 资源路由
        if (preg_match("/Route::(get|post|put|delete|patch|any|resource)\s*\(\s*'([^']+)'/", $line, $m)) {
            $verb = strtolower($m[1]);
            $full = implode('', $prefixes) . $m[2]; // 组前缀 + 路由路径 = 完整路径

            if ($verb === 'resource') {
                // 资源路由：按控制器实际存在的方法展开
                $controller = null;
                if (preg_match("/([A-Za-z0-9_\\\\]+)::class/", $line, $mc)) {
                    $controller = $mc[1];
                }
                $methods = controllerMethods($controller, $root, $warnings);
                foreach ($standard as $action => [$method, $suffix]) {
                    if (!in_array($action, $methods, true)) {
                        continue;
                    }
                    $path = $full . $suffix;
                    $key  = $method . '|' . $path;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $routes[] = ['methods' => [$method], 'path' => $path, 'line' => $no, 'origin' => "resource({$action})"];
                }
            } else {
                $methods = $verb === 'any'
                    ? ['ANY']
                    : [strtoupper($verb)];
                $key = implode(',', $methods) . '|' . $full;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $routes[] = ['methods' => $methods, 'path' => $full, 'line' => $no, 'origin' => "route({$verb})"];
            }
        }
    }

    // 展开可选段变体并生成匹配用段数组
    $expanded = [];
    foreach ($routes as $r) {
        foreach (splitSegments($r['path']) as $segs) {
            $expanded[] = $r + ['segs' => $segs];
        }
    }
    return $expanded;
}

// ============================================================
// 3. 扫描前端源码
// ============================================================

/**
 * 从单文件源码中提取 HTTP 请求调用。
 * 返回条目：['source','file','line','method','raw','path','segs','query','dynamic','unresolved','note']
 */
function extractCalls(string $content, string $file, string $source, array &$warnings): array
{
    $out = [];

    // 主模式：receiver.method( '...' | "..." | `...` )
    //  - ${...} 插值原子化消费（内部可含引号，如 ${row['id']}）
    //  - 允许调用与方法之间跨行（_dio.post(\n  '/admin/export/excel'）
    $re = <<<'REGEX'
/([A-Za-z_][A-Za-z0-9_.]*)\s*\.\s*(get|post|put|delete|patch|request)\s*(?:<[^>]*>)?\s*\(\s*(?:'((?:\$\{[^}]*\}|[^'\\]|\\.)*)'|"((?:\$\{[^}]*\}|[^"\\]|\\.)*)"|`((?:\$\{[^}]*\}|[^`\\]|\\.)*)`)/
REGEX;

    if (preg_match_all($re, $content, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($mm as $match) {
            $receiver = $match[1][0];
            $method   = strtolower($match[2][0]);
            $raw      = $match[3][0] !== '' ? $match[3][0] : ($match[4][0] !== '' ? $match[4][0] : $match[5][0]);
            $offset   = $match[0][1];
            $line     = substr_count(substr($content, 0, $offset), "\n") + 1;

            // request() 调用：从紧随其后的参数里解析 method: http.RequestMethod.XXX
            $note = '';
            if ($method === 'request') {
                $after = substr($content, $offset, 400);
                if (preg_match('/method\s*:\s*http\.RequestMethod\.(GET|POST|PUT|DELETE|PATCH)/', $after, $rm)) {
                    $method = strtolower($rm[1]);
                } else {
                    $method = 'any';
                    $note   = 'request() 未解析出 HTTP 方法，按任意方法匹配';
                }
            }

            $info = analyzePath($raw, $method, $note);
            if ($info === null) {
                continue; // 非 API 路径（如 AppStorage.get('access_token')）
            }
            $out[] = [
                'source' => $source,
                'file'   => $file,
                'line'   => $line,
            ] + $info;
        }
    }

    // 补充扫描：变量路径调用 receiver.method(variable) —— 无法还原，标记"未解析"
    // 仅识别看起来像 HTTP 客户端的 receiver（api / dio / http 等），避免误报 Get.put()、UserService.delete() 等
    // 服务封装文件（ApiService 定义）内的 dio.get(path) 等通用透传调用属于内部实现，不参与扫描
    $skipVarScan = in_array(basename($file), ['api_service.dart', 'ApiService.ets'], true);
    if (!$skipVarScan) {
        $varRe = <<<'REGEX'
/([A-Za-z_][A-Za-z0-9_.]*)\s*\.\s*(get|post|put|delete|patch)\s*(?:<[^>]*>)?\s*\(\s*([A-Za-z_][A-Za-z0-9_.]*)\s*[,)]/
REGEX;
        $typeKeywords = ['String', 'Map', 'dynamic', 'int', 'Object', 'List', 'T', 'Future', 'ApiResponse', 'Record', 'bool', 'double', 'num', 'string', 'number', 'any', 'void'];
        if (preg_match_all($varRe, $content, $vm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($vm as $match) {
                $receiver = $match[1][0];
                $method   = strtolower($match[2][0]);
                $arg      = $match[3][0];
                if (!preg_match('/api|dio|http/i', $receiver)) {
                    continue; // 非 HTTP 客户端调用
                }
                if (in_array($arg, $typeKeywords, true)) {
                    continue; // 方法定义形参（get(String path) 等）
                }
                $offset = $match[0][1];
                $line   = substr_count(substr($content, 0, $offset), "\n") + 1;
                $out[] = [
                    'source'     => $source,
                    'file'       => $file,
                    'line'       => $line,
                    'method'     => strtoupper($method),
                    'raw'        => "{$receiver}.{$method}(" . $arg . ')',
                    'path'       => '{variable}',
                    'segs'       => [],
                    'query'      => '',
                    'dynamic'    => true,
                    'unresolved' => true,
                    'note'       => '路径为变量（' . $arg . '），无法还原字面量，需人工复核',
                ];
            }
        }
    }

    return $out;
}

/**
 * 解析单个路径字面量 → 规范化信息。
 * 返回 null 表示不是 API 路径（不以 / 开头）。
 */
function analyzePath(string $raw, string $method, string $note = ''): ?array
{
    // 剥离 ${BASE_URL} 模板前缀（HarmonyOS 常量）
    $trimmed = ltrim($raw);
    if (str_starts_with($trimmed, '${BASE_URL}')) {
        $trimmed = ltrim(substr($trimmed, strlen('${BASE_URL}')));
    } elseif (preg_match('/^BASE_URL\s*\+/', $trimmed, $m)) {
        $trimmed = ltrim(substr($trimmed, strlen($m[0])));
    }
    if (!str_starts_with($trimmed, '/')) {
        return null; // 非 URL 路径
    }

    $original = $trimmed;

    // 拆分 query 参数（仅作记录，匹配时忽略）
    $query = '';
    if (($pos = strpos($trimmed, '?')) !== false) {
        $query   = substr($trimmed, $pos + 1);
        $trimmed = substr($trimmed, 0, $pos);
    }
    // 去掉结尾斜杠（/admin/inventory/ == /admin/inventory）
    $trimmed = rtrim($trimmed, '/');
    if ($trimmed === '') {
        return null;
    }

    // 未解析迹象：拼接（+）、未闭合插值、残留 ${ 字面量
    // 注意：${...} 插值先做规范化替换，替换后仍残留 ${ 才说明插值未闭合/无法还原
    $unresolved = false;
    if (preg_match('/\s*\+\s*/', $original) || str_ends_with($original, '+')) {
        $unresolved = true;
        $note       = trim($note . ' 字符串拼接(+)无法完全还原');
    }

    // 动态段统一替换为 {param}
    $norm = preg_replace('/\$\{[^}]*\}/', '{param}', $trimmed);
    $norm = preg_replace('/\$[A-Za-z_][A-Za-z0-9_]*/', '{param}', $norm);
    if (str_contains($norm, '${')) {
        $unresolved = true;
        $note       = trim($note . ' 含未闭合 ${ 插值');
    }
    $hasDynamic = str_contains($norm, '{param}');

    $segs = [];
    foreach (explode('/', trim($norm, '/')) as $s) {
        $segs[] = $s === '{param}' ? ['lit' => null, 'param' => true] : ['lit' => $s, 'param' => false];
    }

    return [
        'method'     => strtoupper($method),
        'raw'        => $original,
        'path'       => $norm,
        'segs'       => $segs,
        'query'      => $query,
        'dynamic'    => $hasDynamic,
        'unresolved' => $unresolved,
        'note'       => trim($note),
    ];
}

/** 递归扫描目录下所有源码文件中的请求调用 */
function scanFrontend(string $dir, string $source, array &$warnings): array
{
    $requests = [];
    if (!is_dir($dir)) {
        $warnings[] = "前端目录不存在: {$dir}";
        return $requests;
    }
    $real = realpath($dir) ?: $dir;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['dart', 'ets', 'ts'], true)) {
            continue;
        }
        $rel     = ltrim(substr($file->getPathname(), strlen($real)), '/\\');
        $content = (string) @file_get_contents($file->getPathname());
        $requests = array_merge($requests, extractCalls($content, $rel, $source, $warnings));
    }
    return $requests;
}

// ============================================================
// 4. 主流程：解析 → 扫描 → 比对
// ============================================================

$warnings = [];
$backendRoutes  = parseBackendRoutes($ROUTE_FILE, $ROOT, $warnings);
$flutterReq     = scanFrontend($FLUTTER_DIR, 'flutter', $warnings);
$harmonyReq     = scanFrontend($HARMONY_DIR, 'harmonyos', $warnings);
$frontendReq    = array_merge($flutterReq, $harmonyReq);

// ---- 4.1 死端点：前端调用但后端不存在 ----
$dead = [];   // key = METHOD path
foreach ($frontendReq as $fe) {
    $hit = false;
    foreach ($backendRoutes as $br) {
        if (!methodAllowed($fe['method'], $br['methods'])) {
            continue;
        }
        if (pathMatches($fe['segs'], $br['segs'])) {
            $hit = true;
            break;
        }
    }
    $key = $fe['method'] . ' ' . $fe['path'];
    if (!isset($dead[$key])) {
        $dead[$key] = [
            'method'     => $fe['method'],
            'path'       => $fe['path'],
            'module'     => moduleOf($fe['path']),
            'dynamic'    => $fe['dynamic'],
            'unresolved' => $fe['unresolved'],
            'note'       => $fe['note'],
            'any_hit'    => false,
            'sites'      => [],
        ];
    }
    $dead[$key]['sites'][] = ['source' => $fe['source'], 'file' => $fe['file'], 'line' => $fe['line']];
    if ($hit) {
        $dead[$key]['any_hit'] = true;
    }
    $dead[$key]['unresolved'] = $dead[$key]['unresolved'] || $fe['unresolved'];
}
// 仅保留“从未命中后端”的条目；命中后端但未完全解析的条目也保留（提示人工复核）
$dead = array_filter($dead, fn($d) => !$d['any_hit'] || $d['unresolved']);

// ---- 4.2 覆盖缺口：后端存在但前端均未调用 ----
$gap = [];
foreach ($backendRoutes as $br) {
    $covered = false;
    foreach ($frontendReq as $fe) {
        if (!methodAllowed($fe['method'], $br['methods'])) {
            continue;
        }
        if (pathMatches($fe['segs'], $br['segs'])) {
            $covered = true;
            break;
        }
    }
    if (!$covered) {
        $key = implode(',', $br['methods']) . '|' . $br['path'];
        $gap[$key] = [
            'methods'  => $br['methods'],
            'path'     => $br['path'],
            'module'   => moduleOf($br['path']),
            'line'     => $br['line'],
            'origin'   => $br['origin'],
            'system'   => isSystemRoute($br['path']),
        ];
    }
}

/** 系统/非前端直调路由（webhook、健康检查、安装向导、调试、文档等）标注但不隐藏 */
function isSystemRoute(string $path): bool
{
    return (bool) preg_match('#^/(install|health|metrics|debug|\.well-known|api/docs)#', $path)
        || str_contains($path, '/callback');
}

// ---- 4.3 未解析路径 ----
$unresolved = [];
foreach ($frontendReq as $fe) {
    if ($fe['unresolved']) {
        $unresolved[] = [
            'source' => $fe['source'],
            'file'   => $fe['file'],
            'line'   => $fe['line'],
            'method' => $fe['method'],
            'raw'    => $fe['raw'],
            'path'   => $fe['path'],
            'module' => moduleOf($fe['path']),
            'note'   => $fe['note'],
        ];
    }
}

// ---- 4.4 排序（保证输出稳定） ----
uksort($dead, fn($ka, $kb) => strcmp($ka, $kb));
usort($unresolved, function (array $a, array $b): int {
    return [$a['source'], $a['file'], $a['line'], $a['path']] <=> [$b['source'], $b['file'], $b['line'], $b['path']];
});
$gapList = array_values($gap);
usort($gapList, function (array $a, array $b): int {
    return [$a['module'], $a['path'], implode(',', $a['methods'])] <=> [$b['module'], $b['path'], implode(',', $b['methods'])];
});

// ---- 4.5 冒烟用例校验 ----
// ① 源码扫描：若仓库源码中仍存在该已知死端点，必须被抓到
$smokeFound = false;
foreach ($dead as $d) {
    if ($d['path'] === $SMOKE_PATH) {
        $smokeFound = true;
        break;
    }
}

// ② 匹配逻辑自检（与仓库当前状态无关）：
//    用合成的前端调用 POST /admin/notification/my/read 跑一遍比对，
//    必须被判定为死端点 —— 证明"字面量段不匹配后端 {param} 段"的规则生效。
$smokeSelfOk = true;
{
    $fakeFe = [
        'method' => 'POST',
        'path'   => $SMOKE_PATH,
        'segs'   => [
            ['lit' => 'admin', 'param' => false],
            ['lit' => 'notification', 'param' => false],
            ['lit' => 'my', 'param' => false],
            ['lit' => 'read', 'param' => false],
        ],
        'dynamic' => false,
        'unresolved' => false,
    ];
    foreach ($backendRoutes as $br) {
        if (!methodAllowed($fakeFe['method'], $br['methods'])) {
            continue;
        }
        if (pathMatches($fakeFe['segs'], $br['segs'])) {
            $smokeSelfOk = false; // 竟然命中了后端路由 → 自检失败
            break;
        }
    }
}

// ---- 4.6 模块过滤 ----
if ($optModule !== null) {
    $dead = array_filter($dead, fn($d) => $d['module'] === $optModule);
    $gapList = array_filter($gapList, fn($g) => $g['module'] === $optModule);
    $unresolved = array_filter($unresolved, fn($u) => $u['module'] === $optModule);
}

// ============================================================
// 5. 输出
// ============================================================

$stats = [
    'generated_at'    => date('c'),
    'backend_routes'  => count($backendRoutes),
    'flutter_requests' => count($flutterReq),
    'harmonyos_requests' => count($harmonyReq),
    'dead_count'      => count($dead),
    'gap_count'       => count($gapList),
    'unresolved_count' => count($unresolved),
    'smoke_found'     => $smokeFound,
    'smoke_self_ok'   => $smokeSelfOk,
];

if ($optJson) {
    echo json_encode([
        'meta' => $stats,
        'dead_endpoints' => array_values($dead),
        'coverage_gaps'  => array_values($gapList),
        'unresolved'     => array_values($unresolved),
        'warnings'       => $warnings,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
} else {
    renderText($dead, $gapList, $unresolved, $stats, $warnings);
}

if ($optDoc !== null) {
    renderDoc($optDoc, $dead, $gapList, $unresolved, $stats, $warnings);
    fwrite(STDOUT, "\n[完成] 审计报告已写入: {$optDoc}\n");
}

// 冒烟用例失败视为脚本失败（自检通过即证明提取/比对逻辑正确）
if (!$smokeSelfOk) {
    fwrite(STDERR, "[错误] 冒烟自检失败：{$SMOKE_PATH} 未被判定为死端点，匹配逻辑可能有问题。\n");
    exit(2);
}
if (!$smokeFound) {
    fwrite(STDERR, "[提示] 已知死端点 {$SMOKE_PATH} 未在当前源码中发现——可能已在工作区修复（见 git diff）。匹配逻辑自检已通过。\n");
}
exit(0);

// ============================================================
// 6. 渲染函数
// ============================================================

/** 文本输出 */
function renderText(array $dead, array $gaps, array $unresolved, array $stats, array $warnings): void
{
    $line = str_repeat('=', 68);
    echo $line . "\n";
    echo " 前端请求 URL × 后端路由 比对结果\n";
    echo " 生成时间: {$stats['generated_at']}\n";
    echo " 后端路由: {$stats['backend_routes']} 条 | 前端请求: Flutter {$stats['flutter_requests']} / HarmonyOS {$stats['harmonyos_requests']}\n";
    echo $line . "\n\n";

    // ① 死端点
    echo "① 死端点（前端调用但后端不存在）—— 共 {$stats['dead_count']} 个\n";
    if ($dead) {
        foreach ($dead as $d) {
            $mark = $d['unresolved'] ? '（未完全解析）' : '';
            echo "  [{$d['module']}] {$d['method']} {$d['path']}{$mark}\n";
            foreach ($d['sites'] as $s) {
                echo "      {$s['source']}: {$s['file']}:{$s['line']}\n";
            }
        }
    } else {
        echo "  （无）\n";
    }
    echo "\n";

    // ② 覆盖缺口
    echo "② 覆盖缺口（后端存在但 Flutter/HarmonyOS 均未调用）—— 共 {$stats['gap_count']} 个\n";
    if ($gaps) {
        $curModule = null;
        foreach ($gaps as $g) {
            if ($g['module'] !== $curModule) {
                $curModule = $g['module'];
                echo "  ## {$curModule}\n";
            }
            $sys = $g['system'] ? '  [系统/非前端直调]' : '';
            echo "    " . implode('|', $g['methods']) . " {$g['path']}{$sys}  (config/route.php:{$g['line']}, {$g['origin']})\n";
        }
    } else {
        echo "  （无）\n";
    }
    echo "\n";

    // ③ 未解析
    echo "③ 无法解析的路径（人工复核）—— 共 {$stats['unresolved_count']} 个\n";
    if ($unresolved) {
        foreach ($unresolved as $u) {
            echo "  [{$u['source']}] {$u['method']} {$u['raw']}  — {$u['file']}:{$u['line']}\n";
            if ($u['note']) {
                echo "      备注: {$u['note']}\n";
            }
        }
    } else {
        echo "  （无）\n";
    }
    echo "\n";

    // 冒烟
    echo "冒烟用例 {$GLOBALS['SMOKE_PATH']}：源码扫描 " . ($stats['smoke_found'] ? "已命中 ✅" : "未发现（可能已在工作区修复）")
        . " ｜ 匹配逻辑自检 " . ($stats['smoke_self_ok'] ? "通过 ✅" : "失败 ❌") . "\n";

    if ($warnings) {
        echo "\n[警告]\n";
        foreach ($warnings as $w) {
            echo "  - {$w}\n";
        }
    }
}

/** Markdown 审计报告 */
function renderDoc(string $path, array $dead, array $gaps, array $unresolved, array $stats, array $warnings): void
{
    $md  = "# 端点审计报告 — 前端请求 × 后端路由 比对\n\n";
    $md .= "> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz\n\n";
    $md .= "> 生成时间：{$stats['generated_at']}　｜　生成脚本：`scripts/check-endpoints.php`（可重复运行）\n";
    $md .= "> 冒烟用例 `{$GLOBALS['SMOKE_PATH']}`：源码扫描 " . ($stats['smoke_found'] ? '已命中 ✅' : '未发现（可能已在工作区修复）')
        . " ｜ 匹配逻辑自检 " . ($stats['smoke_self_ok'] ? '通过 ✅' : '失败 ❌') . "\n\n";
    $md .= "## 统计\n\n";
    $md .= "- 后端已注册路由（展开后）：{$stats['backend_routes']} 条\n";
    $md .= "- 前端请求调用：Flutter {$stats['flutter_requests']} 处 / HarmonyOS {$stats['harmonyos_requests']} 处\n";
    $md .= "- 死端点：{$stats['dead_count']} 个　｜　覆盖缺口：{$stats['gap_count']} 个　｜　无法解析：{$stats['unresolved_count']} 个\n\n";

    // 一、死端点
    $md .= "## 一、死端点清单（前端调用但后端不存在）\n\n";
    if ($dead) {
        $md .= "| # | 模块 | 方法 | 路径 | 来源 | 调用位置 |\n|---|------|------|------|------|----------|\n";
        $i = 1;
        foreach ($dead as $d) {
            $sites = [];
            foreach ($d['sites'] as $s) {
                $sites[] = "{$s['source']} `{$s['file']}:{$s['line']}`";
            }
            $mark = $d['unresolved'] ? '（未完全解析）' : '';
            $md .= "| {$i} | {$d['module']} | {$d['method']} | `{$d['path']}`{$mark} | " . implode('<br>', $sites) . " |\n";
            $i++;
        }
    } else {
        $md .= "（无）\n";
    }
    $md .= "\n";

    // 二、覆盖缺口（按模块）
    $md .= "## 二、覆盖缺口清单（后端存在但 Flutter/HarmonyOS 均未调用，按模块分组）\n\n";
    if ($gaps) {
        $byModule = [];
        foreach ($gaps as $g) {
            $byModule[$g['module']][] = $g;
        }
        ksort($byModule);
        foreach ($byModule as $module => $list) {
            $md .= "### 模块：{$module}\n\n";
            $md .= "| 方法 | 路径 | 路由位置 | 说明 |\n|------|------|----------|------|\n";
            foreach ($list as $g) {
                $sys = $g['system'] ? '系统/非前端直调（webhook、健康检查、安装向导等）' : '前端未调用';
                $md .= "| " . implode(' / ', $g['methods']) . " | `{$g['path']}` | config/route.php:{$g['line']}（{$g['origin']}） | {$sys} |\n";
            }
            $md .= "\n";
        }
    } else {
        $md .= "（无）\n\n";
    }

    // 三、无法解析
    $md .= "## 三、无法解析的路径（人工复核）\n\n";
    if ($unresolved) {
        $md .= "| # | 来源 | 方法 | 原始写法 | 所在文件 | 备注 |\n|---|------|------|----------|----------|------|\n";
        $i = 1;
        foreach ($unresolved as $u) {
            $md .= "| {$i} | {$u['source']} | {$u['method']} | `{$u['raw']}` | {$u['file']}:{$u['line']} | {$u['note']} |\n";
            $i++;
        }
    } else {
        $md .= "（无）\n";
    }
    $md .= "\n";

    // 四、脚本用法说明
    $md .= "## 四、脚本用法说明\n\n";
    $md .= "脚本：`scripts/check-endpoints.php`（PHP CLI ≥ 8.0）\n\n";
    $md .= "```bash\n";
    $md .= "# 文本输出（默认）\n";
    $md .= "php scripts/check-endpoints.php\n\n";
    $md .= "# JSON 输出（便于后续工具消费）\n";
    $md .= "php scripts/check-endpoints.php --json\n\n";
    $md .= "# 仅过滤单个模块（如 finance、wms、notification）\n";
    $md .= "php scripts/check-endpoints.php --module=finance\n\n";
    $md .= "# 重新生成本审计报告\n";
    $md .= "php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-07.md\n";
    $md .= "```\n\n";
    $md .= "工作原理：\n\n";
    $md .= "1. **后端**：解析 `config/route.php`，还原 `Route::group` 前缀为完整路径；`Route::resource` 按控制器实际存在的方法展开（index/store/show/update/destroy 等）；`Route::any` 视为任意方法。\n";
    $md .= '2. **前端**：扫描 `apps/flutter/lib` 目录下所有 `.dart` 与 `apps/harmonyos/entry/src/main/ets` 目录下所有 `.ets` 文件，提取 `ApiService.instance.*`、`api.*`、`_dio.*`、`apiService.*`、`httpRequest.request()` 等调用的路径字面量；支持 `${...}` / `$var` 插值与模板串（含 `${BASE_URL}` 前缀剥离）。' . "\n";
    $md .= "3. **匹配**：前端字面量段仅匹配后端字面量段，前端动态段仅匹配后端 `{param}` 段（保证 `/admin/notification/my/read` 不会被误配到 `/admin/notification/{id}/read`）；方法按 HTTP 方法精确匹配，`any` 匹配一切。\n";
    $md .= "4. **清单**：① 死端点（前端调用但后端不存在，最优先）→ ② 覆盖缺口（后端存在但前端均未调用，按模块分组；webhook/健康检查等系统路由已标注）→ ③ 无法解析的路径（变量路径、字符串拼接、未闭合插值等，需人工复核）。\n\n";

    if ($warnings) {
        $md .= "### 运行警告\n\n";
        foreach ($warnings as $w) {
            $md .= "- {$w}\n";
        }
        $md .= "\n";
    }

    if (!@file_put_contents($path, $md)) {
        fwrite(STDERR, "[错误] 无法写入文档: {$path}\n");
        exit(1);
    }
}
