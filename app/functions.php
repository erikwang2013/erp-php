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
 * 读取必填环境变量，缺失时立即报错（fail-fast），避免静默降级为弱密钥。
 */
function env_required(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new \RuntimeException("缺少必需环境变量: {$key}，请参照 .env.example 配置后重试");
    }

    return $value;
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
