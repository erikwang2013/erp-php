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
