<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller;

use app\common\SnowflakeService;
use support\Log;
use support\Request;
use support\Response;

class InstallController
{
    private string $lockFile;
    private string $envPath;
    private string $envExamplePath;
    private string $sqlPath;

    public function __construct()
    {
        $base = base_path();
        $this->lockFile = runtime_path() . '/installed.lock';
        $this->envPath = $base . '/.env';
        $this->envExamplePath = $base . '/.env.example';
        $this->sqlPath = $base . '/database/install.sql';
    }

    /**
     * 安装向导页
     * @Apidoc\Title("安装向导")
     * @Apidoc\Desc("四步安装向导(环境检查/数据库配置/管理员账号/确认安装)，GET 展示表单，POST 提交步骤；已安装时返回完成提示页(HTML)")
     * @Apidoc\Url("/install")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("系统")
     */
    public function index(Request $request): Response
    {
        try {
            return $this->doIndex($request);
        } catch (\Throwable $e) {
            // 安装向导自诊断：异常直接回显（引导阶段无敏感数据，便于定位启动类问题）
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES)
                . '<br>@' . htmlspecialchars((string) $e->getFile(), ENT_QUOTES)
                . ':' . $e->getLine();
            return new Response(500, ['Content-Type' => 'text/html; charset=utf-8'],
                $this->htmlHeader('安装错误') . '<div class="card"><h1 style="color:#c62828">❌ ' . $msg . '</h1></div>' . $this->htmlFooter());
        }
    }

    private function doIndex(Request $request): Response
    {
        if ($this->isInstalled()) {
            return $this->renderInstalled();
        }

        $step = (int) $request->input('step', 0);
        $errors = [];

        if ($request->method() === 'POST') {
            $errors = $this->processStep($step, $request);
            if (empty($errors)) {
                if ($step === 3) {
                    return $this->renderSuccess();
                }
                $step++;
            }
        }

        return $this->renderStep($step, $errors, $request);
    }

    /**
     * 测试数据库连接
     * @Apidoc\Title("测试数据库连接")
     * @Apidoc\Desc("安装向导第 1 步使用，校验 MySQL 连通性与版本(需 >= 8.0)；系统已安装后禁止调用")
     * @Apidoc\Url("/install/test-db")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("系统")
     * @Apidoc\Param(name="host", type="string", default="127.0.0.1", desc="数据库主机(仅字母数字._-:字符)")
     * @Apidoc\Param(name="port", type="int", default="3306", desc="数据库端口(1-5位数字)")
     * @Apidoc\Param(name="database", type="string", desc="数据库名(可空,传空则不连库校验)")
     * @Apidoc\Param(name="username", type="string", default="root", desc="数据库用户")
     * @Apidoc\Param(name="password", type="string", desc="数据库密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=连接成功,1=失败")
     * @Apidoc\Returned("message", type="string", desc="结果信息(成功含 MySQL 版本)")
     */
    public function testDb(Request $request): Response
    {
        if ($this->isInstalled()) {
            return json(['code' => 1, 'message' => '系统已安装，禁止调用']);
        }

        try {
            $host = $request->input('host', '127.0.0.1');
            $port = $request->input('port', '3306');
            $database = $request->input('database', '');
            $username = $request->input('username', 'root');
            $password = $request->input('password', '');

            if (!preg_match('/^[a-zA-Z0-9._\-:]+$/', (string) $host) || !preg_match('/^\d{1,5}$/', (string) $port)) {
                return json(['code' => 1, 'message' => '非法的主机或端口参数']);
            }

            // 连通性测试只连服务器（不带 dbname）：数据库可能尚未创建
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare($version, '8.0', '<')) {
                return json(['code' => 1, 'message' => "MySQL 版本需 >= 8.0，当前: {$version}"]);
            }

            // 库存在性单独探测（缺失不阻塞连通性结论）
            $dbExists = false;
            if ($database && preg_match('/^[a-zA-Z0-9_\-]+$/', $database)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
                $stmt->execute([$database]);
                $dbExists = (int) $stmt->fetchColumn() > 0;
            }

            return json(['code' => 0, 'message' => "连接成功，MySQL {$version}" . ($database
                ? ($dbExists ? "；数据库 {$database} 已存在" : "；数据库 {$database} 尚不存在，安装时将自动创建")
                : '')]);
        } catch (\PDOException $e) {
            return json(['code' => 1, 'message' => '连接失败: ' . $e->getMessage()]);
        }
    }

    private function isInstalled(): bool
    {
        $lockExists = file_exists($this->lockFile);
        $envMarked = false;
        if (file_exists($this->envPath)) {
            $env = file_get_contents($this->envPath);
            $envMarked = str_contains($env, 'APP_INSTALLED=true');
        }

        return $lockExists || $envMarked;
    }

    private function renderInstalled(): Response
    {
        $html = $this->htmlHeader('系统已安装');
        $html .= <<<'HTML'
        <div class="card">
            <h1>✅ 系统已安装</h1>
            <p style="font-size:16px;color:#666;margin-bottom:12px;">安装向导已完成。如需重新安装：</p>
            <p style="background:#f8f9fa;padding:8px 12px;border-radius:4px;font-family:monospace;font-size:13px;">
                rm runtime/installed.lock
            </p>
            <p style="font-size:14px;color:#888;margin:8px 0 20px;">并在 <code>.env</code> 中移除 <code>APP_INSTALLED=true</code></p>
            <a href="/admin/dashboard" class="btn">进入后台</a>
        </div>
        HTML;
        $html .= $this->htmlFooter();

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function renderSuccess(): Response
    {
        $html = $this->htmlHeader('安装完成');
        $html .= <<<'HTML'
        <div class="card" style="text-align:center;">
            <h1 style="color:#2e7d32;">🎉 安装完成</h1>
            <p style="font-size:16px;color:#555;">开放ERP系统已成功安装。</p>
            <div style="background:#e8f5e9;padding:16px;border-radius:8px;margin:20px 0;text-align:left;">
                <p style="margin:4px 0;">📌 请使用刚才设置的管理员账号登录后台</p>
                <p style="margin:4px 0;color:#888;font-size:13px;">登录后将自动跳转至后台仪表盘</p>
            </div>
            <a href="/admin/dashboard" class="btn">进入后台</a>
        </div>
        HTML;
        $html .= $this->htmlFooter();

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function renderStep(int $step, array $errors, \support\Request $request): Response
    {
        $steps = ['环境检查', '数据库配置', '管理员账号', '确认安装'];
        $old = $request->post();   // 表单回填（原签名 $old 参数在改 $request 传递时并入）
        $html = $this->htmlHeader('安装向导 — ' . $steps[$step]);

        // 步骤指示器
        $html .= '<div class="steps">';
        foreach ($steps as $i => $label) {
            $cls = match (true) {
                $i < $step => 'done',
                $i === $step => 'active',
                default => 'pending',
            };
            $num = $i < $step ? '✓' : ($i + 1);
            $html .= "<div class=\"step {$cls}\"><span class=\"step-num\">{$num}</span><span class=\"step-label\">{$label}</span></div>";
            if ($i < 3) {
                $html .= '<div class="step-line"></div>';
            }
        }
        $html .= '</div>';

        // 错误提示
        if (!empty($errors)) {
            $html .= '<div class="card" style="border-left:4px solid #e57373;padding:16px;"><ul style="margin:0;padding-left:18px;color:#c62828;">';
            foreach ($errors as $e) {
                $html .= '<li>' . htmlspecialchars($e) . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '<div class="card">';
        $html .= match ($step) {
            0 => $this->renderStep0(),
            1 => $this->renderStep1($old),
            2 => $this->renderStep2($old),
            3 => $this->renderStep3($old),
            default => '<p>未知步骤</p>',
        };
        $html .= '</div>';
        $html .= $this->htmlFooter();

        // 内联 <script> 注入 CSP nonce（script-src 严格模式必需），style 已由 style-src unsafe-inline 放行
        $nonce = htmlspecialchars((string) ($request->cspNonce ?? ''), ENT_QUOTES);
        $html = str_replace('<script>', '<script nonce="' . $nonce . '">', $html);

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function renderStep0(): string
    {
        $results = $this->checkEnvironment();
        $allOk = true;
        $rows = '';
        foreach ($results as $item) {
            $icon = match ($item['status']) {
                'ok' => '✅',
                'warn' => '⚠️',
                default => '❌',
            };
            if ($item['status'] === 'fail') {
                $allOk = false;
            }
            $rows .= "<tr><td>{$icon}</td><td>{$item['name']}</td><td style=\"color:#888;font-size:13px\">{$item['value']}</td></tr>";
        }

        $html = '<h1>环境检查</h1>';
        $html .= '<table class="env-table">' . $rows . '</table>';
        $html .= $allOk
            ? '<form method="post"><input type="hidden" name="step" value="0"><button type="submit" class="btn">下一步：数据库配置</button></form>'
            : '<div class="alert alert-error">请先解决以上 ❌ 标记的问题，然后刷新本页重新检查。</div>';

        return $html;
    }

    private function renderStep1(array $old): string
    {
        $h = fn (string $k, string $d = '') => htmlspecialchars($old[$k] ?? $d);

        return <<<HTML
        <h1>数据库配置</h1>
        <form method="post" id="db-form">
        <input type="hidden" name="step" value="1">
        <div class="form-row">
            <div class="form-group" style="flex:2"><label>主机地址</label><input type="text" name="host" value="{$h('host', '127.0.0.1')}" required></div>
            <div class="form-group" style="flex:1"><label>端口</label><input type="number" name="port" value="{$h('port', '3306')}" required></div>
        </div>
        <div class="form-group"><label>数据库名</label><input type="text" name="database" value="{$h('database', 'erp')}" required placeholder="请提前创建数据库"></div>
        <div class="form-group"><label>用户名</label><input type="text" name="username" value="{$h('username', 'root')}" required></div>
        <div class="form-group"><label>密码</label><input type="password" name="password" value="{$h('password')}"></div>
        <div class="form-group"><label>表前缀</label><input type="text" name="prefix" value="{$h('prefix', 'erp_')}" required></div>
        <div class="form-actions">
            <button type="button" id="test-db-btn" class="btn btn-secondary">测试连接</button>
            <button type="submit" class="btn">下一步：管理员账号</button>
        </div>
        <div id="test-result" style="margin-top:12px;"></div>
        </form>
        <script>
        // CSP 严格模式：事件属性级 handler 不在 nonce 覆盖范围，用 addEventListener 绑定
        document.getElementById('test-db-btn')?.addEventListener('click', testDb);

        async function testDb() {
            const r = document.getElementById('test-result');
            r.innerHTML = '<span style="color:#999;">⏳ 测试中...</span>';
            const fd = new FormData(document.getElementById('db-form'));
            const p = new URLSearchParams();
            for (const [k, v] of fd) { if (k !== 'step' && k !== 'prefix') p.append(k, v); }
            try {
                const resp = await fetch('/install/test-db?' + p.toString());
                const json = await resp.json();
                r.innerHTML = json.code === 0
                    ? '<span style="color:#2e7d32;">✅ ' + json.message + '</span>'
                    : '<span style="color:#c62828;">❌ ' + json.message + '</span>';
            } catch (e) {
                r.innerHTML = '<span style="color:#c62828;">❌ 请求失败: ' + e.message + '</span>';
            }
        }
        </script>
        HTML;
    }

    private function renderStep2(array $old): string
    {
        $u = htmlspecialchars($old['admin_username'] ?? 'admin');
        $hidden = '';
        foreach (['host', 'port', 'database', 'username', 'password', 'prefix'] as $k) {
            $v = htmlspecialchars($old[$k] ?? '');
            $hidden .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\">";
        }

        return <<<HTML
        <h1>管理员账号</h1>
        <form method="post">
        <input type="hidden" name="step" value="2">
        {$hidden}
        <div class="form-group"><label>管理员用户名</label><input type="text" name="admin_username" value="{$u}" required minlength="3"></div>
        <div class="form-group"><label>管理员密码</label><input type="password" name="admin_password" required minlength="6" placeholder="至少6位"></div>
        <div class="form-group"><label>确认密码</label><input type="password" name="admin_password_confirm" required minlength="6" placeholder="再次输入密码"></div>
        <button type="submit" class="btn">下一步：确认安装</button>
        </form>
        HTML;
    }

    private function renderStep3(array $old): string
    {
        $rows = '';
        $labels = [
            'host' => '数据库主机', 'port' => '端口', 'database' => '数据库名',
            'username' => '数据库用户', 'prefix' => '表前缀', 'admin_username' => '管理员账号',
        ];
        foreach ($labels as $k => $label) {
            $v = htmlspecialchars((string) ($old[$k] ?? ''));
            $rows .= "<tr><td>{$label}</td><td><strong>{$v}</strong></td></tr>";
        }

        $hidden = '';
        foreach (['host', 'port', 'database', 'username', 'password', 'prefix', 'admin_username', 'admin_password'] as $k) {
            $v = htmlspecialchars($old[$k] ?? '');
            $hidden .= "<input type=\"hidden\" name=\"{$k}\" value=\"{$v}\">";
        }

        return <<<HTML
        <h1>确认安装</h1>
        <table class="env-table">{$rows}</table>
        <div class="alert alert-warn">
            ⚠️ 点击"开始安装"后将执行：<br>
            ① 写入 .env 配置文件<br>
            ② 创建 122 张数据库表并导入种子数据<br>
            ③ 创建管理员账号并关联超级管理员角色
        </div>
        <form method="post">
        <input type="hidden" name="step" value="3">
        {$hidden}
        <button type="submit" class="btn btn-install">开始安装</button>
        </form>
        HTML;
    }

    private function processStep(int $step, Request $request): array
    {
        return match ($step) {
            0 => [],
            1 => $this->validateStep1($request),
            2 => $this->validateStep2($request),
            3 => $this->executeInstall($request),
            default => ['无效的步骤'],
        };
    }

    private function validateStep1(Request $request): array
    {
        $errors = [];
        if (!$request->input('host')) {
            $errors[] = '请输入数据库主机地址';
        }
        if (!$request->input('port')) {
            $errors[] = '请输入数据库端口';
        }
        if (!$request->input('database')) {
            $errors[] = '请输入数据库名';
        }
        if (!$request->input('username')) {
            $errors[] = '请输入数据库用户名';
        }
        if (!empty($errors)) {
            return $errors;
        }

        try {
            $dsn = 'mysql:host=' . $request->input('host') . ';port=' . $request->input('port') . ';dbname=' . $request->input('database') . ';charset=utf8mb4';
            new \PDO($dsn, $request->input('username'), $request->input('password'), [
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $errors[] = '数据库连接失败: ' . $e->getMessage();
        }

        return $errors;
    }

    private function validateStep2(Request $request): array
    {
        $errors = [];
        $username = trim($request->input('admin_username', ''));
        $password = $request->input('admin_password', '');
        $confirm = $request->input('admin_password_confirm', '');
        if (strlen($username) < 3) {
            $errors[] = '管理员用户名至少3个字符';
        }
        if (strlen($password) < 6) {
            $errors[] = '密码至少6位';
        }
        if ($password !== $confirm) {
            $errors[] = '两次输入的密码不一致';
        }

        return $errors;
    }

    private function executeInstall(Request $request): array
    {
        $db = [
            'host' => $request->input('host'),
            'port' => $request->input('port'),
            'database' => $request->input('database'),
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'prefix' => $request->input('prefix', 'erp_'),
        ];
        $adminUser = trim($request->input('admin_username'));
        $adminPass = $request->input('admin_password');

        // 库名白名单（防注入）；库不存在时自动创建（测试连接不再要求预建库）
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', (string) $db['database'])) {
            return ['数据库名只能包含字母、数字、下划线与连字符'];
        }

        try {
            $server = new \PDO(
                "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4",
                $db['username'],
                $db['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $server->exec('CREATE DATABASE IF NOT EXISTS `' . $db['database'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $pdo = new \PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",
                $db['username'],
                $db['password'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $sql = file_get_contents($this->sqlPath);
            if (!$sql) {
                return ['无法读取 install.sql'];
            }
            $pdo->exec($sql);

            $adminId = SnowflakeService::generate();
            $passwordHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO `' . $db['prefix'] . "admin_user` (`id`, `username`, `password`, `real_name`, `status`) VALUES (:id, :username, :password, '系统管理员', 1)");
            $stmt->execute(['id' => $adminId, 'username' => $adminUser, 'password' => $passwordHash]);

            $stmt = $pdo->prepare('INSERT INTO `' . $db['prefix'] . 'admin_user_role` (`user_id`, `role_id`) VALUES (:uid, :rid)');
            $stmt->execute(['uid' => $adminId, 'rid' => 10000000000000001]);

            $this->writeEnv($db);
            file_put_contents($this->lockFile, date('Y-m-d H:i:s') . ' — installed');

            return [];
        } catch (\Throwable $e) {
            // 安装失败已回显给操作者，同时留日志便于运维排查
            Log::error('系统安装失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return ['安装失败: ' . $e->getMessage()];
        }
    }

    private function writeEnv(array $db): void
    {
        $template = file_get_contents($this->envExamplePath);
        if (!$template) {
            $template = '';
        }

        $replacements = [
            'DB_HOST=127.0.0.1' => "DB_HOST={$db['host']}",
            'DB_PORT=3306' => "DB_PORT={$db['port']}",
            'DB_DATABASE=erp' => "DB_DATABASE={$db['database']}",
            'DB_USERNAME=root' => "DB_USERNAME={$db['username']}",
            'DB_PASSWORD=' => "DB_PASSWORD={$db['password']}",
        ];
        foreach ($replacements as $search => $replace) {
            $template = str_replace($search, $replace, $template);
        }

        $jwtSecret = bin2hex(random_bytes(32));
        $template = preg_replace('/JWT_SECRET=.*/', "JWT_SECRET={$jwtSecret}", $template);

        if (!preg_match('/^APP_KEY=/m', $template)) {
            $appKey = bin2hex(random_bytes(16));
            $template = preg_replace('/^(APP_URL=.*)$/m', "\$1\nAPP_KEY={$appKey}", $template);
        }

        $template = rtrim($template) . "\nAPP_INSTALLED=true\n";
        file_put_contents($this->envPath, $template);
    }

    private function checkEnvironment(): array
    {
        $phpVersion = PHP_VERSION;
        $requiredVersion = '8.3';
        $extensions = ['pdo_mysql', 'redis', 'json', 'mbstring', 'openssl', 'fileinfo'];

        $results = [];
        $results[] = [
            'name' => 'PHP 版本',
            'value' => $phpVersion . ' (需要 >= ' . $requiredVersion . ')',
            'status' => version_compare($phpVersion, $requiredVersion, '>=') ? 'ok' : 'fail',
        ];

        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => "PHP 扩展: {$ext}",
                'value' => $loaded ? '已加载' : '未加载',
                'status' => $loaded ? 'ok' : 'fail',
            ];
        }

        $runtimeWritable = is_writable(runtime_path());
        $results[] = [
            'name' => 'runtime/ 目录可写',
            'value' => $runtimeWritable ? '可写' : '不可写: ' . runtime_path(),
            'status' => $runtimeWritable ? 'ok' : 'fail',
        ];

        $envDirWritable = is_writable(dirname($this->envPath));
        $envFileWritable = file_exists($this->envPath) ? is_writable($this->envPath) : $envDirWritable;
        $results[] = [
            'name' => '.env 文件可写',
            'value' => $envFileWritable ? '可写' : '不可写',
            'status' => $envFileWritable ? 'ok' : 'fail',
        ];

        $sqlExists = file_exists($this->sqlPath);
        $results[] = [
            'name' => 'install.sql 存在',
            'value' => $sqlExists ? '存在' : '缺失: ' . $this->sqlPath,
            'status' => $sqlExists ? 'ok' : 'fail',
        ];

        return $results;
    }

    private function htmlHeader(string $title): string
    {
        $title = htmlspecialchars($title);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{$title}</title>
        <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#f1f1f1;color:#333;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:40px 20px}
        h1{font-size:22px;font-weight:600;margin-bottom:20px;color:#1d2327}
        .card{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:32px;max-width:600px;width:100%;margin-top:20px}
        .steps{display:flex;align-items:center;justify-content:center;max-width:600px;width:100%;margin-bottom:8px}
        .step{display:flex;align-items:center;gap:8px;font-size:14px}
        .step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600}
        .step.pending .step-num{background:#e0e0e0;color:#888}
        .step.active .step-num{background:#2271b1;color:#fff}
        .step.done .step-num{background:#2e7d32;color:#fff}
        .step.active .step-label{color:#2271b1;font-weight:600}
        .step.pending .step-label,.step.done .step-label{color:#888}
        .step-line{flex:1;height:2px;background:#e0e0e0;margin:0 12px;max-width:60px}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:14px;font-weight:500;margin-bottom:4px;color:#555}
        .form-group input{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:4px;font-size:15px;transition:border-color .2s}
        .form-group input:focus{outline:none;border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
        .form-row{display:flex;gap:16px}
        .btn{display:inline-block;padding:10px 24px;background:#2271b1;color:#fff;border:none;border-radius:4px;font-size:15px;cursor:pointer;text-decoration:none;font-weight:500}
        .btn:hover{background:#135e96}
        .btn-secondary{background:#f0f0f1;color:#2271b1;border:1px solid #2271b1}
        .btn-secondary:hover{background:#e0e0e0}
        .btn-install{background:#2e7d32;font-size:16px;padding:12px 32px}
        .btn-install:hover{background:#1b5e20}
        .form-actions{display:flex;gap:12px;margin-top:20px}
        .alert-error{background:#fce4ec;border:1px solid #e57373;color:#c62828;padding:12px 16px;border-radius:4px;font-size:14px}
        .alert-warn{background:#fff3e0;border:1px solid #ffb74d;color:#e65100;padding:12px 16px;border-radius:4px;font-size:14px;margin:16px 0;text-align:left;line-height:1.8}
        .env-table{width:100%;border-collapse:collapse;margin-bottom:20px}
        .env-table td{padding:8px 12px;border-bottom:1px solid #f0f0f1;font-size:14px}
        .env-table td:first-child{width:36px;text-align:center}
        .env-table tr:last-child td{border-bottom:none}
        code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:13px}
        @media(max-width:640px){.card{padding:20px}.form-row{flex-direction:column;gap:0}.step-label{display:none}}
        </style>
        </head>
        <body>
        HTML;
    }

    private function htmlFooter(): string
    {
        return "</body>\n</html>";
    }
}
