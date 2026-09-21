<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * Here is your custom functions.
 */

/**
 * Translate the given message.
 */
function __(string $key, array $replace = [], ?string $locale = null): string
{
    return \app\common\I18n::trans($key, $replace, $locale);
}

/**
 * Translate a module name.
 */
function __m(string $key): string
{
    return \app\common\I18n::trans("modules.{$key}");
}

/**
 * Create a validator instance (Laravel-compatible helper).
 */
function validator(array $data = [], array $rules = [], array $messages = [], array $attributes = []): \Illuminate\Validation\Validator
{
    static $factory = null;

    // JSON 体里的数字由 json_decode 还原成 int/float。gt/gte/lt/lte/between/size 这些规则在
    // Laravel 内一律经 Brick\BigNumber::of() 比较，而 brick/math ≥0.14 对 float 入参发
    // E_DEPRECATED；webman（support/App::run 的 error_reporting(E_ALL) + support/bootstrap.php
    // 里抛异常的 set_error_handler）会把它升级成 ErrorException → 未捕获 → 500「服务器内部错误」
    // +TraceId（实测：quantity=1.5 走 numeric|gt:0 即 500，quantity=2 或 "1.5" 正常）。
    // 小数数量/单价是常规输入（收货 1.5、单价 12.34），故在校验入口按 bc_norm() 同口径把 float
    // 规范成十进制串——bcmath 与规则两侧本就只吃十进制串；整数与字符串原样不动。
    // 只规范本次校验的副本，$request->input() 取到的原始入参不变。
    array_walk_recursive($data, static function (&$value): void {
        if (is_float($value)) {
            $value = bc_norm($value);
        }
    });

    if ($factory === null) {
        $loader = new \Illuminate\Translation\ArrayLoader();
        foreach (['zh_CN', 'en'] as $locale) {
            $file = config('translation.path', base_path() . '/resource/translations') . "/{$locale}/validation.php";
            if (is_file($file)) {
                $loader->addMessages($locale, 'validation', require $file);
            }
        }
        $translator = new \Illuminate\Translation\Translator($loader, 'zh_CN');
        $translator->setFallback('en');
        $factory = new \Illuminate\Validation\Factory($translator);
    }

    return $factory->make($data, $rules, $messages, $attributes);
}

/**
 * 读取必填环境变量，缺失/为空/为弱占位值时立即报错（fail-fast），
 * 避免缺失或弱值被静默用于生产（如 JWT/加密密钥被降级为弱密钥）。
 */
function env_required(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new \RuntimeException("缺少必需环境变量: {$key}，请参照 .env.example 配置后重试");
    }

    assert_env_not_placeholder($key, $value);

    return $value;
}

/**
 * 读取加解密主密钥并**按算法校验长度**（AES-256 → 32 字节；AES-128 / SM4 → 16 字节）。
 *
 * 两个插件都只在**首次使用**时才校验长度（encryptable 的 Encrypter.php:71、
 * encryption 的 EncryptionManagerFactory.php:29 都是 strlen 硬校验），而 env_required()
 * 只看非空与占位值 —— 于是长度不对的站点能正常启动，直到某个用户点开一个会解密字段的
 * 页面才 500（实测：安装向导生成的密钥是 bin2hex(random_bytes(24)) = 48 字符，
 * 装完后 GET /admin/v1/supplier 读 phone/email 直接 500，用户只看到一个 TraceId）。
 * 启动即拒绝并给出修复命令，比运行期 500 好归因。
 *
 * @param string $key    环境变量名
 * @param string $cipher 生效的加密算法名
 */
function env_crypto_key(string $key, string $cipher): string
{
    $value = env_required($key);
    $need = str_contains(strtolower($cipher), '256') ? 32 : 16;
    if (strlen($value) !== $need) {
        throw new \RuntimeException(
            "环境变量 {$key} 长度必须为 {$need} 字节（当前 " . strlen($value) . '）：'
            . "算法 {$cipher} 在首次加解密时硬校验该长度，长度不符会让页面报 500。"
            . '修复：bash scripts/gen-env-keys.sh .env'
        );
    }

    return $value;
}

/**
 * 读取密码类环境变量（数据库/ES/RabbitMQ 等口令）。
 *
 * 与 env_required() 的区别：可通过 $allowEmpty 显式放行空口令
 * （仅限本地开发等允许空口令连接的场景，如 root 空密码的本地 MySQL）；
 * 但弱占位值（change-me/CHANGE_ME/xxx）无论何种场景一律拒绝启动。
 *
 * @param string $key        环境变量名
 * @param string $label      用途中文描述（用于报错信息，如“数据库”）
 * @param bool   $allowEmpty 是否允许空口令，默认 false（为空时同样拒绝）
 */
function env_secret(string $key, string $label, bool $allowEmpty = false): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        if ($allowEmpty) {
            return '';
        }
        throw new \RuntimeException("缺少{$label}口令: 环境变量 {$key} 未配置或为空，请显式配置强随机口令后重试");
    }

    assert_env_not_placeholder($key, $value);

    return $value;
}

/**
 * 占位值检测：环境变量值包含 change-me / change_me / CHANGE_ME / xxx 等
 * 弱占位特征时抛异常，防止占位密钥/口令被静默用于生产。
 */
function assert_env_not_placeholder(string $key, string $value): void
{
    if (preg_match('/(change[-_]me|xxx)/i', $value)) {
        throw new \RuntimeException(
            "环境变量 {$key} 的值仍为弱占位值（change-me/CHANGE_ME/xxx），部署前必须替换为强随机密钥/口令，请参照 .env.example 重新配置后重试"
        );
    }
}

/**
 * 判断当前是否为生产环境（APP_ENV=production）。
 *
 * 用于数据库等口令的强校验门控：生产环境禁止空口令（fail-fast），
 * 开发环境（未设置或非 production）允许空口令连接。
 */
function env_is_production(): bool
{
    return (getenv('APP_ENV') ?: '') === 'production';
}

/**
 * 获取当前请求的 TraceId（由 TracingId 中间件注入）。
 *
 * 用于 fail-closed 审计日志：同一请求内的所有日志可凭 TraceId 串联排查。
 * 无请求上下文时（如队列消费、WebSocket、CLI）返回 '-'。
 */
function trace_id(): string
{
    $request = request();

    return $request ? (string)($request->traceId ?? '-') : '-';
}

/**
 * 共享 JWT 实例（全项目唯一创建点，密钥校验只在此处）。
 */
function jwt_instance(): \Erikwang2013\Jwt\JWT
{
    static $jwt = null;

    if ($jwt === null) {
        $config = config('plugin.erikwang2013.jwt.jwt', []);
        $jwt = \Erikwang2013\Jwt\JWTFactory::createFromConfig($config);
    }

    return $jwt;
}

/**
 * 规范化数值为 bcmath 可用的十进制字符串。
 *
 * bcmath 只接受十进制串（科学计数法如 "1.0E-5" 会抛 ValueError）；
 * float 经 10 位小数展开再掐尾零，避免二进制尾噪与科学计数法进入 bc 运算
 * （0.105→"0.105"、1e-5→"0.00001"）；字符串原样返回。
 *
 * 命名用 bc_ 前缀：PHP 8.4 起 bcmath 自带 bcround()/bcceil()/bcfloor()，
 * symfony/polyfill-php84 亦已定义同名函数（composer autoload_files），不可重名。
 */
function bc_norm(string|int|float $v): string
{
    if (is_float($v)) {
        $v = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
    }
    $v = (string) $v;

    return $v === '-0' ? '0' : $v;
}

/**
 * 四舍五入（half-up，远离零——与 PHP round() 默认及中文财会一致）。
 *
 * 原理：末位加 0.5×10^-scale 后按 scale 截断——恰半值必在 scale 位产生 10 进位，
 * 余下低位置截断安全。负数对称（bcsub），-1.005→"-1.01"。
 */
function bc_round(string|int|float $value, int $scale = 2): string
{
    $n = bc_norm($value);
    $half = '0.' . str_repeat('0', $scale) . '5';

    return str_starts_with($n, '-') ? bcsub($n, $half, $scale) : bcadd($n, $half, $scale);
}

/**
 * 绝对值（bc 域内取符号位，避免 abs() 把金额串转回 float）。
 *
 * 负值经 bcsub 固定 scale=6 会产生尾零（-1.2→"1.200000"），与正数直通
 * （"1.2"）不对称，出口前掐掉尾零统一为规范十进制串。
 */
function bc_abs(string|int|float $value): string
{
    $n = bc_norm($value);
    if (!str_starts_with($n, '-')) {
        return $n;
    }

    return rtrim(rtrim(bcsub('0', $n, 6), '0'), '.');
}

/**
 * 数据库表前缀（与 config/database.php 生效值同源）。
 * Grammar 只对表参自动加前缀；限定列引用/原生 SQL 需物理表名时
 * 用本函数拼接，避免二次硬编码 erp_。
 */
function db_prefix(): string
{
    $prefix = config('database.connections.mysql.prefix', '');

    return $prefix !== '' ? (string) $prefix : (string) (getenv('DB_PREFIX') ?: '');
}

/**
 * 单据号：调用方给了就原样用（Flutter 端自生成 PREFIX+年月日时分秒下发），
 * 留空（缺省/空串）用雪花号兜底，避免各端「新增」因 uk_code 必填而 422。
 *
 * 兜底不用「前缀+时间戳」：各表 code 有 uk_code 唯一索引，同秒并发即撞（500），
 * 而雪花号跨进程单调唯一——与 wms/tms 服务生成单号的既有写法一致
 * （WmsInboundService::'RCV'.SnowflakeService::generate() 等）。
 */
function doc_code(mixed $given, string $prefix): string
{
    $code = trim((string) $given);
    if ($code !== '') {
        return $code;
    }

    return $prefix . \app\common\SnowflakeService::generate();
}

// poster-php 配置挂载：PosterConfig 默认只读包内 vendor config（driver 硬编码 auto），
// 项目 config/poster.php 需在此显式加载才生效（生产与测试共用此引导路径）。
// PosterConfig::load(null) 会按包内默认路径重载并因 mtime 不等而覆盖已挂载配置
// （findProjectConfig 定位偏差，详见 vendor/erikwang2013/poster-php/src/PosterConfig.php），
// 故同步包配置 mtime 使缓存命中，保证项目配置（driver=gd）恒生效。
$posterPkgConfig = dirname(__DIR__) . '/vendor/erikwang2013/poster-php/config/poster.php';
$posterAppConfig = dirname(__DIR__) . '/config/poster.php';
if (is_file($posterPkgConfig) && is_file($posterAppConfig)) {
    @touch($posterPkgConfig, (int) filemtime($posterAppConfig));
}
\Erikwang2013\Poster\PosterConfig::load($posterAppConfig);

if (!function_exists('captcha_pass_store')) {
    /**
     * 人机验证「放行凭证」存储：必须与验证码挑战同一驱动（config/poster.php captcha.storage，
     * 可经 .env POSTER_CAPTCHA_STORAGE 覆盖；auto = Redis > Session > File 自动探测）。
     *
     * 写入方 CaptchaController::verify、读取方 AuthController::consumeCaptchaPass 都走这里。
     * 曾经写入方硬编码 Redis、而挑战按配置存文件：Redis 不可用时**正确答案也返回 500**，
     * 且消息误报成「验证码校验失败，请重试」（用户会以为是自己点歪了而反复重试）。
     */
    function captcha_pass_store(): \Erikwang2013\Poster\Storage\StorageInterface
    {
        return \Erikwang2013\Poster\Storage\StorageFactory::create(
            \Erikwang2013\Poster\PosterConfig::get('captcha.storage')
        );
    }
}
