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

    if ($factory === null) {
        $loader = new \Illuminate\Translation\ArrayLoader();
        $factory = new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator($loader, 'zh_CN')
        );
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
