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
