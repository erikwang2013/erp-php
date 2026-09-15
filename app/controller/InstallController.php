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

#[\erikwang2013\apidoc\annotation\Title('安装向导')]
#[\erikwang2013\apidoc\annotation\Group('系统管理')]

class InstallController
{
    /** 语言选择落 cookie：向导的后退是裸 GET、前进是 POST，只有 cookie 两种都活 */
    private const LANG_COOKIE = 'install_lang';

    /** 向导语种白名单（= resource/translations 下的目录）；不在此列一律回英文 */
    private const LANGS = [
        'zh_CN' => '中文', 'en' => 'English', 'ja' => '日本語', 'ko' => '한국어', 'de' => 'Deutsch',
        'fr' => 'Français', 'es' => 'Español', 'pt' => 'Português', 'ru' => 'Русский',
        'ar' => 'العربية', 'hi' => 'हिन्दी', 'bn' => 'বাংলা', 'id' => 'Indonesia',
    ];

    /** 本次请求的语种：控制器方法级传递，不落静态属性 —— webman 常驻进程里静态会串请求 */
    private string $locale = 'zh_CN';

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
    #[\erikwang2013\apidoc\annotation\Title('安装向导')]
    #[\erikwang2013\apidoc\annotation\Desc('六步安装向导(环境检查/数据库配置/密钥与端口/搜索引擎可选/管理员账号/确认安装)，GET 展示表单，POST 提交步骤；已安装时返回完成提示页(HTML)')]
    #[\erikwang2013\apidoc\annotation\Url('/install')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('系统')]

    public function index(Request $request): Response
    {
        $picked = (string) $request->get('lang', '');
        $this->locale = $this->resolveLocale($picked, (string) $request->cookie(self::LANG_COOKIE, ''), $request);

        $response = $this->render($request);
        if ($picked !== '' && $picked === $this->locale) {
            // 只在选择生效时落 cookie，避免被 fuzz 的 ?lang=xx 反复改写（30 天）
            $response->cookie(self::LANG_COOKIE, $this->locale, 2592000, '/');
        }

        return $response;
    }

    private function render(Request $request): Response
    {
        try {
            return $this->doIndex($request);
        } catch (\Throwable $e) {
            // 安装向导自诊断：异常直接回显（引导阶段无敏感数据，便于定位启动类问题）
            $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES)
                . '<br>@' . htmlspecialchars((string) $e->getFile(), ENT_QUOTES)
                . ':' . $e->getLine();

            return new Response(
                500,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $this->htmlHeader($this->t('Installation error')) . '<div class="card"><h1 style="color:#c62828">❌ ' . $msg . '</h1></div>' . $this->htmlFooter()
            );
        }
    }

    /**
     * 语种解析：?lang= 显式选择 > cookie（上次选择）> Accept-Language > 配置默认。
     *
     * 关键差异（与 I18n 默认行为不同）：**不在白名单里的语种回英文，而不是回 zh_CN**。
     * I18n 的 fallback 链是 ['zh_CN','en']，荷兰语用户会落到中文；安装向导是给陌生人用的，
     * 看不懂的中文不如英文原文（en 词典留空 ⇒ 直接回 key，即英文）。
     */
    private function resolveLocale(string $picked, string $cookie, Request $request): string
    {
        foreach ([$picked, $cookie, \app\common\I18n::getLocale($request)] as $cand) {
            if (isset(self::LANGS[$cand])) {
                return $cand;
            }
        }

        return 'en';
    }

    /** 向导文案翻译：键在本步内唯一，前缀 install. 由这里统一补，调用处只写英文原文 */
    private function t(string $key, array $replace = []): string
    {
        return \app\common\I18n::trans('install.' . $key, $replace, $this->locale);
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
                if ($step === 5) {
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
    #[\erikwang2013\apidoc\annotation\Title('测试数据库连接')]
    #[\erikwang2013\apidoc\annotation\Desc('安装向导第 1 步使用，校验 MySQL 连通性与版本(需 >= 8.0)；系统已安装后禁止调用')]
    #[\erikwang2013\apidoc\annotation\Url('/install/test-db')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('系统')]
    #[\erikwang2013\apidoc\annotation\Param(name:'host', type:'string', default:'127.0.0.1', desc:'数据库主机(仅字母数字._-:字符)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'port', type:'int', default:'3306', desc:'数据库端口(1-5位数字)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'database', type:'string', desc:'数据库名(可空,传空则不连库校验)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'username', type:'string', default:'root', desc:'数据库用户')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'数据库密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=连接成功,1=失败')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'结果信息(成功含 MySQL 版本)')]

    public function testDb(Request $request): Response
    {
        $validator = validator($request->all(), [
            'host' => 'string',
            'port' => 'integer',
            'database' => 'string',
            'username' => 'string',
        ]);
        if ($validator->fails()) {
            return json(['code' => 1, 'message' => $validator->errors()->first()]);
        }
        if ($this->isInstalled()) {
            return json(['code' => 1, 'message' => $this->t('System already installed, this endpoint is disabled')]);
        }

        try {
            $host = $request->input('host', '127.0.0.1');
            $port = $request->input('port', '3306');
            $database = $request->input('database', '');
            $username = $request->input('username', 'root');
            $password = $request->input('password', '');

            if (!preg_match('/^[a-zA-Z0-9._\-:]+$/', (string) $host) || !preg_match('/^\d{1,5}$/', (string) $port)) {
                return json(['code' => 1, 'message' => $this->t('Invalid host or port parameter')]);
            }

            // 连通性测试只连服务器（不带 dbname）：数据库可能尚未创建
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare($version, '8.0', '<')) {
                return json(['code' => 1, 'message' => $this->t('MySQL version must be >= 8.0, current: :version', ['version' => $version])]);
            }

            // 库存在性单独探测（缺失不阻塞连通性结论）
            $dbExists = false;
            if ($database && preg_match('/^[a-zA-Z0-9_\-]+$/', $database)) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
                $stmt->execute([$database]);
                $dbExists = (int) $stmt->fetchColumn() > 0;
            }

            return json(['code' => 0, 'message' => $this->t('Connected successfully, MySQL :version', ['version' => $version]) . ($database
                ? ($dbExists
                    ? $this->t('; database :name already exists', ['name' => $database])
                    : $this->t('; database :name does not exist yet and will be created during installation', ['name' => $database]))
                : '')]);
        } catch (\PDOException $e) {
            return json(['code' => 1, 'message' => $this->t('Connection failed: :msg', ['msg' => $e->getMessage()])]);
        }
    }

    private function isInstalled(): bool
    {
        $lockExists = file_exists($this->lockFile);
        $envMarked = false;
        if (file_exists($this->envPath)) {
            $env = file_get_contents($this->envPath);
            // 按赋值行精确匹配（^...$ 行锚定），注释掉的 #APP_INSTALLED=true 不算已安装
            $envMarked = preg_match('/^[ \t]*APP_INSTALLED=true[ \t]*$/m', $env) === 1;
        }

        return $lockExists || $envMarked;
    }

    private function renderInstalled(): Response
    {
        $t = fn (string $k): string => $this->t($k);
        $html = $this->htmlHeader($t('System already installed'));
        $html .= <<<HTML
        <div class="card">
            <h1>{$t('✅ System already installed')}</h1>
            <p style="font-size:16px;color:#666;margin-bottom:12px;">{$t('The installation wizard has already completed. To reinstall:')}</p>
            <p style="background:#f8f9fa;padding:8px 12px;border-radius:4px;font-family:monospace;font-size:13px;">
                rm runtime/installed.lock
            </p>
            <p style="font-size:14px;color:#888;margin:8px 0 20px;">{$t('and remove <code>APP_INSTALLED=true</code> from <code>.env</code>')}</p>
            <a href="/admin/dashboard" class="btn">{$t('Go to admin panel')}</a>
        </div>
        HTML;
        $html .= $this->htmlFooter();

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function renderSuccess(): Response
    {
        $t = fn (string $k): string => $this->t($k);
        $html = $this->htmlHeader($t('Installation complete'));
        $html .= <<<HTML
        <div class="card" id="install-success" style="text-align:center;">
            <h1 style="color:#2e7d32;">{$t('🎉 Installation complete')}</h1>
            <p style="font-size:16px;color:#555;">{$t('Open ERP has been installed successfully.')}</p>
            <div style="background:#e8f5e9;padding:16px;border-radius:8px;margin:20px 0;text-align:left;">
                <p style="margin:4px 0;">{$t('📌 Log in with the administrator account you just created')}</p>
                <p style="margin:4px 0;color:#888;font-size:13px;">{$t('You will be redirected to the dashboard after logging in')}</p>
            </div>
            <a href="/admin/dashboard" class="btn">{$t('Go to admin panel')}</a>
        </div>
        <script>
        // 安装已落幕，向导暂存里的数据库口令/密钥等明文没有继续留着的理由
        try { sessionStorage.removeItem('open_erp_install_wizard'); } catch (e) {}
        </script>
        HTML;
        $html .= $this->htmlFooter();

        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    private function view(string $tpl, array $vars = []): string
    {
        $file = app_path() . '/view/install/' . $tpl . '.php';
        $vars['t'] = fn (string $k, array $r = []): string => $this->t($k, $r);
        extract($vars, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    private function renderStep(int $step, array $errors, \support\Request $request): Response
    {
        $steps = [
            $this->t('Environment check'), $this->t('Database configuration'), $this->t('Keys and ports'),
            $this->t('Search engine (optional)'), $this->t('Administrator account'), $this->t('Confirm installation'),
        ];
        $old = $request->post();   // 表单回填（原签名 $old 参数在改 $request 传递时并入）
        $html = $this->htmlHeader(
            $this->t('Installation wizard — :step', ['step' => $steps[$step] ?? $this->t('Unknown step')]),
            $step
        );

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
            if ($i < count($steps) - 1) {
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
            4 => $this->renderStep4($old),
            5 => $this->renderStep5($old),
            default => '<p>' . $this->t('Unknown step') . '</p>',
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
                'icon' => match ($item['status']) {
                    'ok' => '✅', 'warn' => '⚠️', default => '❌'
                },
                'name' => $item['name'],
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
        return $this->view('step3', ['old' => $old]);
    }

    private function renderStep4(array $old): string
    {
        return $this->view('step4', ['old' => $old]);
    }

    private function renderStep5(array $old): string
    {
        $summary = [];
        $engineNames = ['elasticsearch' => 'Elasticsearch', 'opensearch' => 'OpenSearch', 'none' => $this->t('Disabled')];
        $engineDriver = (string) ($old['engine_driver'] ?? 'none');
        $summary[] = [$this->t('Search engine'), $engineNames[$engineDriver] ?? $this->t('Disabled')];
        if ($engineDriver !== 'none' && ($old['engine_host'] ?? '') !== '') {
            $summary[] = [$this->t('Search service address'), (string) $old['engine_host']];
        }

        // 演示数据是第 1 步「数据库配置」的选择，确认页得看得见 —— 否则勾没勾全凭记忆
        $summary[] = [$this->t('Demo data'), ($old['demo_data'] ?? '') === '1' ? $this->t('Import') : $this->t('Do not import')];

        $labels = [
            ['host', $this->t('Database host')], ['port', $this->t('Port')], ['database', $this->t('Database name')],
            ['username', $this->t('Database user')], ['prefix', $this->t('Table prefix')],
            ['http_port', $this->t('Startup port')], ['ws_port', $this->t('WebSocket port')],
            ['admin_username', $this->t('Administrator account')],
        ];
        foreach ($labels as [$k, $label]) {
            $v = $old[$k] ?? '';
            if ($v === '') {
                continue;
            }
            $summary[] = [$label, (string) $v];
        }

        return $this->view('step5', ['old' => $old, 'summary' => $summary]);
    }

    private function processStep(int $step, Request $request): array
    {
        return match ($step) {
            0 => [],
            1 => $this->validateStep1($request),
            2 => $this->validateSecrets($request),
            3 => $this->validateEngine($request),
            4 => $this->validateAdmin($request),
            5 => $this->executeInstall($request),
            default => [$this->t('Invalid step')],
        };
    }

    private function validateStep1(Request $request): array
    {
        // 仅做字段格式校验；连通性由页内「测试连接」按钮实时验证，
        // 权威连接在最终安装(executeInstall)执行 —— 步骤推进不重复活连
        $errors = [];
        if (!$request->input('host')) {
            $errors[] = $this->t('Please enter the database host address');
        }
        if (!preg_match('/^\d{1,5}$/', (string) $request->input('port', ''))) {
            $errors[] = $this->t('Please enter a valid database port');
        }
        if (!$request->input('database')) {
            $errors[] = $this->t('Please enter the database name (it will be created if missing)');
        }
        if (!$request->input('username')) {
            $errors[] = $this->t('Please enter the database user name');
        }
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', (string) $request->input('host', ''))) {
            $errors[] = $this->t('The database host may only contain letters, digits, and ._-');
        }
        if (!$request->input('prefix')) {
            $errors[] = $this->t('Please enter the table prefix');
        }

        return $errors;
    }

    /**
     * 密钥与启动端口步骤：仅格式校验；留空项安装时自动生成
     */
    private function validateSecrets(Request $request): array
    {
        $errors = [];
        $hexFields = [
            'jwt_secret' => $this->t('JWT signing key'),
            'encryption_key' => $this->t('API transport key'),
            'encryptable_key' => $this->t('Storage encryption key'),
            'hashids_salt' => $this->t('ID obfuscation salt'),
            'hashids_alt_salt' => $this->t('ID obfuscation salt (alternate)'),
        ];
        foreach ($hexFields as $field => $label) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw !== '' && !preg_match('/^[A-Za-z0-9]{16,128}$/', $raw)) {
                $errors[] = $this->t(':label must be 16-128 alphanumeric characters (leave blank to auto-generate)', ['label' => $label]);
            }
        }
        foreach (['http_port' => $this->t('Startup port'), 'ws_port' => $this->t('WebSocket port')] as $field => $label) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw !== '' && !preg_match('/^\d{2,5}$/', $raw)) {
                $errors[] = $this->t(':label must be 2-5 digits (leave blank for the default)', ['label' => $label]);
            }
        }
        $raw = trim((string) $request->input('rabbitmq_password', ''));
        if ($raw !== '' && !self::isEnvPasswordSafe($raw)) {
            $errors[] = $this->t('RABBITMQ_PASSWORD may only contain visible characters and must not contain $ or backslash (leave blank to keep the .env.example value)');
        }

        return $errors;
    }

    /**
     * 搜索引擎步骤（webman-scout，可选）：none 之外需补齐连接参数
     */
    private function validateEngine(Request $request): array
    {
        $errors = [];
        $driver = (string) $request->input('engine_driver', 'none');
        if (!in_array($driver, ['none', 'elasticsearch', 'opensearch'], true)) {
            return [$this->t('Search engine supports only: disabled / elasticsearch / opensearch')];
        }
        if ($driver === 'none') {
            return $errors;
        }
        $host = trim((string) $request->input('engine_host', ''));
        if (!preg_match('#^https?://[A-Za-z0-9._\-:]+$#', $host)) {
            $errors[] = $this->t('Search service address must look like http(s)://host:port');
        }
        if (!trim((string) $request->input('engine_username', ''))) {
            $errors[] = $this->t('Please enter the search service user name');
        }
        $raw = (string) $request->input('engine_password', '');
        if ($raw === '' || !self::isEnvPasswordSafe($raw)) {
            $errors[] = $this->t('Please enter the search service password (visible characters only, no $ or backslash)');
        }

        return $errors;
    }

    private function validateAdmin(Request $request): array
    {
        $errors = [];
        $username = trim($request->input('admin_username', ''));
        $password = $request->input('admin_password', '');
        $confirm = $request->input('admin_password_confirm', '');
        if (strlen($username) < 3) {
            $errors[] = $this->t('Administrator user name must be at least 3 characters');
        }
        if (strlen($password) < 6) {
            $errors[] = $this->t('Password must be at least 6 characters');
        }
        if ($password !== $confirm) {
            $errors[] = $this->t('The two passwords do not match');
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
            return [$this->t('The database name may only contain letters, digits, underscores and hyphens')];
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
                return [$this->t('Unable to read install.sql')];
            }
            $pdo->exec($sql);

            // 演示（测试）数据：向导勾选「带测试数据」时追加执行。
            // 该文件只有数据行、无 DDL —— schema 的唯一事实源始终是 install.sql，避免两份 DDL 漂移。
            if ($request->input('demo_data')) {
                $demoPath = base_path() . '/database/install-demo.sql';
                if (is_file($demoPath)) {
                    $pdo->exec((string) file_get_contents($demoPath));
                }
            }

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

            return [$this->t('Installation failed: :msg', ['msg' => $e->getMessage()])];
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
            'hashids_alt_salt' => 'HASHIDS_ALT_SALT',
            'http_port' => 'APP_HTTP_PORT',
            'ws_port' => 'APP_WS_PORT',
        ];

        // 服务账号密码：留空跳过（保持 .env.example 原值），不自动生成 —— 随机值无法匹配部署环境真实口令
        foreach (['rabbitmq_password' => 'RABBITMQ_PASSWORD'] as $field => $envKey) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '') {
                continue;
            }
            if (!self::isEnvPasswordSafe($raw)) {
                throw new \InvalidArgumentException($this->t(':envKey may only contain visible characters and must not contain $ or backslash', ['envKey' => $envKey]));
            }
            $adv[$envKey] = $raw;
        }

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
                    throw new \InvalidArgumentException($this->t(':envKey must be a 2-5 digit port', ['envKey' => $envKey]));
                }
                $adv[$envKey] = $raw;
            } elseif (!preg_match($hex, $raw)) {
                throw new \InvalidArgumentException($this->t(':field must be a 16-128 character alphanumeric key (or leave blank to auto-generate)', ['field' => $field]));
            } else {
                $adv[$envKey] = $raw;
            }
        }

        // 搜索引擎（webman-scout，可选）: 不启用 => 写 SCOUT_DRIVER=null 走空引擎（默认值 opensearch，不显式禁用会误连）
        $engineDriver = (string) $request->input('engine_driver', 'none');
        if (in_array($engineDriver, ['', 'none'], true)) {
            $adv['SCOUT_DRIVER'] = 'null';
        } elseif ($engineDriver === 'elasticsearch') {
            $adv['SCOUT_DRIVER'] = 'elasticsearch';
            $adv['SCOUT_HOSTS'] = trim((string) $request->input('engine_host', ''));
            $adv['ES_USERNAME'] = trim((string) $request->input('engine_username', ''));
            $adv['ES_PASSWORD'] = (string) $request->input('engine_password', '');
        } elseif ($engineDriver === 'opensearch') {
            $adv['SCOUT_DRIVER'] = 'opensearch';
            $adv['SCOUT_OPENSEARCH_HOST'] = trim((string) $request->input('engine_host', ''));
            $adv['SCOUT_OPENSEARCH_USERNAME'] = trim((string) $request->input('engine_username', ''));
            $adv['SCOUT_OPENSEARCH_PASSWORD'] = (string) $request->input('engine_password', '');
        }

        return $adv;
    }

    /**
     * .env 单行值安全校验：仅可见字符，禁止 $ 与反斜杠（防写环境变量注入/破坏行格式）
     */
    private static function isEnvPasswordSafe(string $raw): bool
    {
        return strlen($raw) <= 128 && preg_match('/^[^\\\\$\\x00-\\x1F\\x7F]+$/u', $raw) === 1;
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
        ];
        foreach ($replacements as $search => $replace) {
            $template = str_replace($search, $replace, $template);
        }

        // 口令必须整行替换：.env.example 的 DB_PASSWORD= 后面本来就有值，只替换「DB_PASSWORD=」
        // 会把模板残留值拼在用户填的口令后面（曾写出 20 位输入 + 20 位残留 = 40 位，登录 1045）。
        // 用 callback 而非直接 preg_replace，避免口令里的 $1/\1 被当成反向引用吃掉。
        $template = preg_replace_callback(
            '/^DB_PASSWORD=.*$/m',
            static fn (): string => "DB_PASSWORD={$db['password']}",
            $template
        );

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
            'name' => $this->t('PHP version'),
            'value' => $this->t(':version (requires >= :required)', ['version' => $phpVersion, 'required' => $requiredVersion]),
            'status' => version_compare($phpVersion, $requiredVersion, '>=') ? 'ok' : 'fail',
        ];

        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => $this->t('PHP extension: :ext', ['ext' => $ext]),
                'value' => $loaded ? $this->t('Loaded') : $this->t('Not loaded'),
                'status' => $loaded ? 'ok' : 'fail',
            ];
        }

        $runtimeWritable = is_writable(runtime_path());
        $results[] = [
            'name' => $this->t('runtime/ directory is writable'),
            'value' => $runtimeWritable ? $this->t('Writable') : $this->t('Not writable: :path', ['path' => runtime_path()]),
            'status' => $runtimeWritable ? 'ok' : 'fail',
        ];

        $envDirWritable = is_writable(dirname($this->envPath));
        $envFileWritable = file_exists($this->envPath) ? is_writable($this->envPath) : $envDirWritable;
        $results[] = [
            'name' => $this->t('.env file is writable'),
            'value' => $envFileWritable ? $this->t('Writable') : $this->t('Not writable'),
            'status' => $envFileWritable ? 'ok' : 'fail',
        ];

        $sqlExists = file_exists($this->sqlPath);
        $results[] = [
            'name' => $this->t('install.sql exists'),
            'value' => $sqlExists ? $this->t('Exists') : $this->t('Missing: :path', ['path' => $this->sqlPath]),
            'status' => $sqlExists ? 'ok' : 'fail',
        ];

        return $results;
    }

    /**
     * @param int|null $step 语言选择链接要带上的当前步序号（结果页无步骤传 null）
     */
    private function htmlHeader(string $title, ?int $step = null): string
    {
        $title = htmlspecialchars($title);
        $htmlLang = htmlspecialchars(str_replace('_', '-', $this->locale), ENT_QUOTES);
        $langs = $this->langPicker($step);
        $brandSub = htmlspecialchars($this->t('Open ERP System · Installation Wizard'), ENT_QUOTES);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$htmlLang}">
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
        .langs{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;max-width:720px;margin:-14px 0 22px}
        .lang{font-size:12.5px;color:var(--mut);text-decoration:none;padding:3px 9px;border-radius:999px;border:1px solid var(--line);background:#fff;white-space:nowrap}
        .lang:hover{color:var(--pri);border-color:#c7d2fe;background:var(--pri-l)}
        .lang.active{color:#fff;background:var(--pri);border-color:var(--pri);font-weight:700}
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
        .pw-wrap{position:relative}
        .pw-wrap input{padding-right:44px}
        .pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:15px;line-height:1;padding:4px 6px;color:#64748b}
        .pw-eye:hover{color:#4338ca}
        @media(max-width:720px){body{padding:28px 14px 40px}.card{padding:24px 20px}.form-row{flex-direction:column;gap:0}.step-label{display:none}.step-line{max-width:26px}}
        </style>
        </head>
        <body>
        <div class="brand">
            <div class="brand-mark">E</div>
            <div>
                <div class="brand-name">open-erp</div>
                <div class="brand-sub">{$brandSub}</div>
            </div>
        </div>
        {$langs}
        HTML;
    }

    /**
     * 语言选择器：13 个语种各一个链接。
     *
     * 为什么是链接不是 <select>：CSP 是 `script-src 'none-nonce'` 严格模式，
     * 属性级内联（onchange）不放行，得再配一段脚本才活；链接零脚本、无 JS 也能用。
     * 各语种按**自身文字**书写（中文/한국어/العربية…），语言列表本来就是给看不懂当前
     * 界面的人用的，翻成当前语种等于没写。
     */
    private function langPicker(?int $step): string
    {
        $suffix = $step === null ? '' : '&amp;step=' . $step;
        $out = '<div class="langs">';
        foreach (self::LANGS as $code => $name) {
            $active = $code === $this->locale ? ' active' : '';
            $out .= '<a class="lang' . $active . '" href="/install?lang=' . $code . $suffix . '">'
                . htmlspecialchars($name, ENT_QUOTES) . '</a>';
        }

        return $out . '</div>';
    }

    private function htmlFooter(): string
    {
        // 界面版本水印：用于区分浏览器是否加载到最新代码（升级排查用）
        return self::WIZARD_STATE_SCRIPT
            . '<div class="foot">erik.xyz · ' . date('Y-m-d H:i') . '</div></body></html>';
    }

    /**
     * 向导填写的浏览器侧暂存：后退/刷新后原样回来。
     *
     * 为什么必须做：前进是丢不了的（每一步把前序字段渲成隐藏域带走），丢的是**后退** ——
     * 「← 上一步」是裸 GET，renderStep() 里 $old = $request->post() 拿到空数组，
     * 于是不仅输入框清空，那些隐藏域也一并清空；从后退页再点「下一步」，整条链传下去就是空的。
     *
     * 放 footer 一处，六步全覆盖，日后加字段自动跟上（按 name 遍历，不写死字段表）。
     *
     * 两条刻意的取舍：
     *  1. **隐藏域也存也恢复**。只恢复输入框是不够的 —— 后退页的 host/port/… 隐藏域是空的，
     *     不补就会在「下一步」时把空值传进后面的步骤。
     *  2. **密码字段照样存**。不存的话后退回来数据库口令就空了，正是要修的那个毛病。
     *     代价是明文落在 sessionStorage 里（同源脚本可读）。可接受的理由：本页仅安装前存在
     *     （APP_INSTALLED 守卫），且这些值本来就以明文 hidden input 躺在每一步的 DOM 里；
     *     sessionStorage 按标签页隔离、随标签页关闭而清，安装成功页再显式清一次。
     *
     * 恢复无条件覆盖（storage 胜过服务端渲染值）：本页每次输入都同步写 storage，而各步的
     * 校验失败重渲只把 $old 原样回显、不做任何加工，所以同标签页内 storage 不会比服务端旧。
     */
    private const WIZARD_STATE_SCRIPT = <<<'HTML'
    <script>
    (function () {
      var KEY = 'open_erp_install_wizard';
      var form = document.querySelector('form[action="/install"]');
      if (!form) { return; }                       // 成功页/错误页无表单：不动 storage，由成功页自己清
      var state = {};
      try { state = JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e) { state = {}; }

      var fields = form.querySelectorAll('input[name],select[name],textarea[name]');
      Array.prototype.forEach.call(fields, function (el) {
        if (el.name === 'step') { return; }        // step 是当前页序号，不跨页存

        // 先恢复，再挂监听。恢复完补发 change：各步自己的联动（step3 的搜索服务显隐与
        // required、step4 的密码一致性）都挂在 change/submit 上，不补发就停在初始态。
        if (state[el.name] !== undefined && state[el.name] !== '') {
          var changed = false;
          if (el.type === 'checkbox' || el.type === 'radio') {
            var want = state[el.name] === '1' || state[el.name] === 'on' || state[el.name] === el.value;
            changed = el.checked !== want;
            el.checked = want;
          } else if (el.tagName === 'SELECT') {
            for (var i = 0; i < el.options.length; i++) {
              if (el.options[i].value === state[el.name]) { changed = el.value !== state[el.name]; el.selectedIndex = i; break; }
            }
          } else if (el.value !== state[el.name]) {
            el.value = state[el.name];
            changed = true;
          }
          if (changed) { el.dispatchEvent(new Event('change', { bubbles: true })); }
        }

        var save = function () {
          state[el.name] = (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '') : el.value;
          try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* 隐私模式等写入失败：忽略，退化为不暂存 */ }
        };
        el.addEventListener('input', save);
        el.addEventListener('change', save);
      });
    })();
    </script>
    HTML;
}
