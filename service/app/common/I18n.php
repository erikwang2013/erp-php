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
    private static ?string $locale = null;

    /**
     * Get current locale from request Accept-Language header, defaults to config locale
     */
    public static function getLocale(Request $request = null): string
    {
        if (self::$locale) {
            return self::$locale;
        }

        if ($request) {
            $header = $request->header('Accept-Language', '');
            if ($header) {
                $locale = strtolower(substr(trim(strtok($header, ',')), 0, 5));
                // Normalize: zh-cn -> zh_CN, en-us -> en
                $locale = str_replace('-', '_', $locale);
                if (str_starts_with($locale, 'zh')) {
                    $locale = 'zh_CN';
                }
                if (str_starts_with($locale, 'en')) {
                    $locale = 'en';
                }
                self::$locale = $locale;
                return self::$locale;
            }
        }

        self::$locale = config('translation.locale', 'zh_CN');
        return self::$locale;
    }

    /**
     * Translate a key. Format: "file.key" or "key" (uses common.php)
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? self::$locale ?? config('translation.locale', 'zh_CN');
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
