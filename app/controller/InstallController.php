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
#[\erikwang2013\apidoc\annotation\Title("安装向导")]
#[\erikwang2013\apidoc\annotation\Group("系统管理")]

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
     */
#[\erikwang2013\apidoc\annotation\Title("安装向导")]
#[\erikwang2013\apidoc\annotation\Desc("四步安装向导(环境检查/数据库配置/管理员账号/确认安装)，GET 展示表单，POST 提交步骤；已安装时返回完成提示页(HTML)")]
#[\erikwang2013\apidoc\annotation\Url("/install")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统")]

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
     */
#[\erikwang2013\apidoc\annotation\Title("测试数据库连接")]
#[\erikwang2013\apidoc\annotation\Desc("安装向导第 1 步使用，校验 MySQL 连通性与版本(需 >= 8.0)；系统已安装后禁止调用")]
#[\erikwang2013\apidoc\annotation\Url("/install/test-db")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("系统")]
#[\erikwang2013\apidoc\annotation\Param(name:"host", type:"string", default:"127.0.0.1", desc:"数据库主机(仅字母数字._-:字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"port", type:"int", default:"3306", desc:"数据库端口(1-5位数字)")]
#[\erikwang2013\apidoc\annotation\Param(name:"database", type:"string", desc:"数据库名(可空,传空则不连库校验)")]
#[\erikwang2013\apidoc\annotation\Param(name:"username", type:"string", default:"root", desc:"数据库用户")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"数据库密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=连接成功,1=失败")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"结果信息(成功含 MySQL 版本)")]

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

    private function view(string $tpl, array $vars = []): string
    {
        $file = app_path() . '/view/install/' . $tpl . '.php';
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
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
        $envs = [];
        $allOk = true;
        foreach ($this->checkEnvironment() as $item) {
            $envs[] = [
                'icon'  => match ($item['status']) { 'ok' => '✅', 'warn' => '⚠️', default => '❌' },
                'name'  => $item['name'],
                'value' => $item['value'],
            ];
            if ($item['status'] === 'fail') {
                $allOk = false;
            }
        }

        return $this->view('step0', ['envs' => $envs, 'allOk' => $allOk]);
    }

    private function renderStep1(array $old): string
    {
        return $this->view('step1', ['old' => $old]);
    }

    private function renderStep2(array $old): string
    {
        return $this->view('step2', ['old' => $old]);
    }

    private function renderStep3(array $old): string
    {
        $summary = [];
        $labels = [
            ['host', '数据库主机'], ['port', '端口'], ['database', '数据库名'],
            ['username', '数据库用户'], ['prefix', '表前缀'], ['admin_username', '管理员账号'],
            ['http_port', '启动端口'], ['ws_port', 'WebSocket 端口'],
        ];
        foreach ($labels as [$k, $label]) {
            $v = $old[$k] ?? '';
            if ($v === '' || $v === null) {
                continue;
            }
            $summary[] = [$label, (string) $v];
        }

        return $this->view('step3', ['old' => $old, 'summary' => $summary]);
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
        // 仅做字段格式校验；连通性由页内「测试连接」按钮实时验证，
        // 权威连接在最终安装(executeInstall)执行 —— 步骤推进不重复活连
        $errors = [];
        if (!$request->input('host')) {
            $errors[] = '请输入数据库主机地址';
        }
        if (!preg_match('/^\d{1,5}$/', (string) $request->input('port', ''))) {
            $errors[] = '请输入正确的数据库端口';
        }
        if (!$request->input('database')) {
            $errors[] = '请输入数据库名（不存在将自动创建）';
        }
        if (!$request->input('username')) {
            $errors[] = '请输入数据库用户名';
        }
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', (string) $request->input('host', ''))) {
            $errors[] = '数据库主机地址只能包含字母、数字、._-字符';
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

            $this->writeEnv($db, $this->collectAdvanced($request));
            file_put_contents($this->lockFile, date('Y-m-d H:i:s') . ' — installed');

            return [];
        } catch (\Throwable $e) {
            // 安装失败已回显给操作者，同时留日志便于运维排查
            Log::error('系统安装失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return ['安装失败: ' . $e->getMessage()];
        }
    }

    private function collectAdvanced(Request $request): array
    {
        $adv = [];
        $hex = '/^[A-Za-z0-9]{16,128}$/';
        $map = [
            'jwt_secret' => 'JWT_SECRET_KEY',
            'encryption_key' => 'ENCRYPTION_KEY',
            'encryptable_key' => 'ENCRYPTABLE_KEY',
            'hashids_salt' => 'HASHIDS_SALT',
            'http_port' => 'APP_HTTP_PORT',
            'ws_port' => 'APP_WS_PORT',
        ];
        foreach ($map as $field => $envKey) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '') {
                $adv[$envKey] = str_ends_with($envKey, '_PORT')
                    ? ($envKey === 'APP_HTTP_PORT' ? '8788' : '8282')
                    : bin2hex(random_bytes(24));
                continue;
            }
            if (str_ends_with($envKey, '_PORT')) {
                if (!preg_match('/^\d{2,5}$/', $raw)) {
                    throw new \InvalidArgumentException($envKey . ' 必须是 2-5 位数字端口');
                }
                $adv[$envKey] = $raw;
            } elseif (!preg_match($hex, $raw)) {
                throw new \InvalidArgumentException($field . ' 必须是 16-128 位字母数字密钥（或留空自动生成）');
            } else {
                $adv[$envKey] = $raw;
            }
        }

        return $adv;
    }

    private function writeEnv(array $db, array $extra = []): void
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

        foreach ($extra as $key => $value) {
            if (preg_match('/^' . $key . '=.*$/m', $template)) {
                $template = preg_replace('/^' . $key . '=.*$/m', $key . '=' . $value, $template);
            } else {
                $template .= $key . '=' . $value . "\n";
            }
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
        :root{--pri:#4f46e5;--pri-d:#4338ca;--pri-l:#eef2ff;--ok:#059669;--ok-l:#ecfdf5;--warn:#d97706;--warn-l:#fffbeb;--err:#dc2626;--err-l:#fef2f2;--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--bg:#f8fafc}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",Arial,sans-serif;color:var(--ink);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:48px 20px 60px;background:
          radial-gradient(1200px 500px at 15% -10%,#eef2ff 0%,transparent 55%),
          radial-gradient(900px 420px at 110% 0%,#ecfdf5 0%,transparent 50%),
          var(--bg)}
        .brand{display:flex;align-items:center;gap:14px;margin-bottom:26px;user-select:none}
        .brand-mark{width:46px;height:46px;border-radius:13px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:800;box-shadow:0 8px 20px rgba(79,70,229,.35)}
        .brand-name{font-size:21px;font-weight:700;letter-spacing:.2px}
        .brand-sub{font-size:12.5px;color:var(--mut);margin-top:2px;letter-spacing:.3px}
        .steps{display:flex;align-items:center;justify-content:center;width:100%;max-width:720px;margin-bottom:22px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:10px 18px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
        .step{display:flex;align-items:center;gap:8px;font-size:13px}
        .step-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
        .step.pending .step-num{background:#e2e8f0;color:#64748b}
        .step.active .step-num{background:var(--pri);color:#fff;box-shadow:0 0 0 4px rgba(79,70,229,.15)}
        .step.done .step-num{background:var(--ok);color:#fff}
        .step.active .step-label{color:var(--pri);font-weight:700}
        .step.done .step-label{color:var(--ok);font-weight:600}
        .step.pending .step-label{color:#94a3b8}
        .step-line{flex:1;height:2px;background:#e2e8f0;margin:0 10px;max-width:56px;border-radius:2px}
        .step.done + .step-line{background:var(--ok)}
        .card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 3px rgba(15,23,42,.05),0 12px 32px -12px rgba(15,23,42,.12);padding:34px 36px;max-width:720px;width:100%}
        .card h1,.step-title{font-size:19px;font-weight:700;margin-bottom:22px;display:flex;align-items:center;gap:10px}
        .card h1:before,.step-title:before{content:"";width:4px;height:18px;border-radius:2px;background:linear-gradient(180deg,#6366f1,#4f46e5);display:inline-block}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#334155}
        .form-group input,.form-group select{width:100%;padding:10px 13px;border:1px solid #cbd5e1;border-radius:10px;font-size:14.5px;background:#fff;transition:border-color .18s,box-shadow .18s}
        .form-group input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(79,70,229,.14)}
        .form-group .hint{font-size:12px;color:#94a3b8;margin-top:5px}
        .form-row{display:flex;gap:14px}
        .form-row .form-group{flex:1}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px 26px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;box-shadow:0 4px 12px rgba(79,70,229,.25);transition:transform .12s,box-shadow .2s,filter .2s}
        .btn:hover{filter:brightness(1.06);box-shadow:0 6px 16px rgba(79,70,229,.32);transform:translateY(-1px)}
        .btn:active{transform:translateY(0)}
        .btn-secondary{background:#fff;color:var(--pri);border:1px solid #c7d2fe;box-shadow:none;font-weight:600}
        .btn-secondary:hover{background:var(--pri-l);box-shadow:none;transform:none}
        .btn-install{background:linear-gradient(135deg,#059669,#047857);font-size:15.5px;padding:12px 34px;box-shadow:0 4px 14px rgba(5,150,105,.28)}
        .btn-install:hover{box-shadow:0 6px 18px rgba(5,150,105,.35)}
        .form-actions,.install-actions{display:flex;gap:12px;margin-top:26px;align-items:center}
        .alert-error{background:var(--err-l);border:1px solid #fecaca;color:var(--err);padding:12px 16px;border-radius:10px;font-size:14px}
        .alert-warn{background:var(--warn-l);border:1px solid #fde68a;color:#92400e;padding:13px 16px;border-radius:10px;font-size:14px;margin:16px 0;text-align:left;line-height:1.8}
        .summary-card{background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-bottom:18px}
        .sum-head{padding:12px 20px;background:#f8fafc;border-bottom:1px solid var(--line);font-weight:700;font-size:14px}
        .sum-item{display:flex;justify-content:space-between;gap:16px;padding:10px 20px;border-bottom:1px solid #f1f5f9;font-size:14px}
        .sum-item:last-child{border-bottom:none}
        .sum-label{color:var(--mut);flex-shrink:0}
        .sum-value{font-weight:600;word-break:break-all;text-align:right}
        .notice-box{background:var(--warn-l);border:1px solid #fde68a;border-radius:12px;padding:14px 20px;margin-bottom:8px}
        .notice-title{color:#92400e;font-weight:700;margin-bottom:8px;font-size:13.5px}
        .notice-list{margin:0 0 0 18px;line-height:2;font-size:14px;color:#78350f}
        .notice-tip{margin-top:8px;font-size:12.5px;color:#a16207;border-top:1px dashed #fcd34d;padding-top:8px}
        .env-table{width:100%;border-collapse:collapse;margin-bottom:8px}
        .env-table td{padding:9px 8px;border-bottom:1px solid #f1f5f9;font-size:13.5px}
        .env-table td:first-child{width:40px;text-align:center}
        .env-table tr:last-child td{border-bottom:none}
        code{background:#f1f5f9;padding:2px 6px;border-radius:6px;font-size:12.5px;color:#475569}
        .env-ok{display:inline-flex;align-items:center;gap:6px;color:var(--ok);font-weight:600}
        .env-fail{display:inline-flex;align-items:center;gap:6px;color:var(--err);font-weight:600}
        #test-result{margin-top:10px;font-size:13.5px}
        .foot{color:#94a3b8;font-size:12px;margin-top:26px;letter-spacing:.3px}
        #progress-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:99;padding:20px}
        .pm-card{background:#fff;border-radius:16px;padding:30px 34px;width:min(480px,100%);box-shadow:0 24px 60px -16px rgba(15,23,42,.4);text-align:center}
        .pm-title{font-size:16.5px;font-weight:700;margin-bottom:18px}
        .pm-track{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}
        .pm-bar{height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#6366f1,#059669);transition:width .6s ease}
        .pm-step{margin-top:12px;font-size:13.5px;color:#64748b;min-height:20px}
        .pm-err{margin-top:10px;font-size:13px;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;text-align:left;line-height:1.6;word-break:break-all}
        #pm-retry{margin-top:14px}
        .adv-panel{margin:18px 0;border:1px solid #c7d2fe;border-radius:12px;background:#fafaff;padding:4px 18px 14px}
        .adv-panel .adv-panel-title{font-size:14px;font-weight:700;color:#4338ca;padding:12px 0 6px}
        @media(max-width:720px){body{padding:28px 14px 40px}.card{padding:24px 20px}.form-row{flex-direction:column;gap:0}.step-label{display:none}.step-line{max-width:26px}}
        </style>
        </head>
        <body>
        <div class="brand">
            <div class="brand-mark">E</div>
            <div>
                <div class="brand-name">open-erp</div>
                <div class="brand-sub">开放 ERP 系统 · 安装向导</div>
            </div>
        </div>
        HTML;
    }

    private function htmlFooter(): string
    {
        // 界面版本水印：用于区分浏览器是否加载到最新代码（升级排查用）
        return '<div class="foot">install-ui v7 · ' . date('Y-m-d H:i') . '</div></body>\n</html>';
    }
}
