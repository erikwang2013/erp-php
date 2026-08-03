<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\common;

use support\Request;

class I18n
{
    private static array $loaded = [];

    /**
     * Resolve current locale from request Accept-Language header.
     * No static caching — webman workers are persistent, each request must be evaluated independently.
     */
    public static function getLocale(Request $request = null): string
    {
        if ($request) {
            $header = $request->header('Accept-Language', '');
            if ($header) {
                $locale = strtolower(substr(trim(strtok($header, ',')), 0, 5));
                $locale = str_replace('-', '_', $locale);
                if (str_starts_with($locale, 'zh')) {
                    return 'zh_CN';
                }
                if (str_starts_with($locale, 'en')) {
                    return 'en';
                }

                return $locale;
            }
        }

        return config('translation.locale', 'zh_CN');
    }

    /**
     * Translate a key. Format: "file.key" or "key" (uses common.php)
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? config('translation.locale', 'zh_CN');
        $fallback = config('translation.fallback_locale', ['zh_CN', 'en']);
        $path = config('translation.path', base_path() . '/resource/translations');

        // Parse "file.key" format
        if (str_contains($key, '.')) {
            [$file, $k] = explode('.', $key, 2);
        } else {
            $file = 'common';
            $k = $key;
        }

        return self::getTranslated($path, $locale, $file, $k, $replace, $fallback);
    }

    private static function getTranslated(string $path, string $locale, string $file, string $key, array $replace, array $fallbackLocales): string
    {
        $localesToTry = array_merge([$locale], (array) $fallbackLocales);

        foreach ($localesToTry as $loc) {
            $cacheKey = "{$loc}.{$file}";
            if (!isset(self::$loaded[$cacheKey])) {
                $f = "{$path}/{$loc}/{$file}.php";
                self::$loaded[$cacheKey] = is_file($f) ? require $f : [];
            }
            if (isset(self::$loaded[$cacheKey][$key])) {
                $value = self::$loaded[$cacheKey][$key];
                foreach ($replace as $k => $v) {
                    $value = str_replace(":{$k}", (string) $v, $value);
                }

                return $value;
            }
        }

        return $key; // Return key if not found
    }
}
