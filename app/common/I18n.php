<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\common;

use Webman\Http\Request;

class I18n
{
    private static array $loaded = [];

    /**
     * Resolve current locale from request Accept-Language header.
     * No static caching — webman workers are persistent, each request must be evaluated independently.
     */
    public static function getLocale(?Request $request = null): string
    {
        if ($request) {
            $header = $request->header('Accept-Language', '');
            if ($header) {
                // 浏览器按偏好降序排列语言标签（q 值无需解析），取首个即可；
                // 只保留主语言子标签：翻译目录除 zh_CN 外均为 2 字母（de/fr/ja…），
                // 带地区的完整标签（de-DE => de_de）没有对应目录，会白白落回 zh_CN。
                $tag = str_replace('_', '-', strtolower(trim((string) strtok($header, ','))));
                $primary = explode('-', $tag)[0];
                if ($primary === 'zh') {
                    return 'zh_CN';
                }
                if ($primary !== '') {
                    return $primary;
                }
            }
        }

        return config('translation.locale', 'zh_CN');
    }

    /**
     * Translate a key. Format: "file.key" or "key" (uses common.php)
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        // 未显式指定时取当前请求的 Accept-Language；无请求上下文（CLI/队列/测试）时
        // request() 返回 null，getLocale() 落回 translation.locale 配置，行为与改造前一致。
        $locale = $locale ?? self::getLocale(request());
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
        // 英文即 key 语义：词典将以**英文原文**为键，故请求 en 时 key 本身就是英文渲染结果。
        // 故 en 不参与 fallback（不落 zh_CN，否则英文用户会看到中文）：
        //   先查 en 词典（兼容既有的语义 key 条目），查不到即返回 key（= 英文原文）。
        $localesToTry = str_starts_with(strtolower($locale), 'en')
            ? [$locale]
            : array_merge([$locale], (array) $fallbackLocales);

        foreach ($localesToTry as $loc) {
            $cacheKey = "{$loc}.{$file}";
            if (!isset(self::$loaded[$cacheKey])) {
                $f = "{$path}/{$loc}/{$file}.php";
                self::$loaded[$cacheKey] = is_file($f) ? require $f : [];
            }
            if (isset(self::$loaded[$cacheKey][$key])) {
                return self::applyReplace((string) self::$loaded[$cacheKey][$key], $replace);
            }
        }

        return self::applyReplace($key, $replace); // 找不到条目时返回 key 本身（英文即 key 语义下即英文原文）
    }

    /** 占位符替换：`:name` → 值 */
    private static function applyReplace(string $value, array $replace): string
    {
        foreach ($replace as $k => $v) {
            $value = str_replace(":{$k}", (string) $v, $value);
        }

        return $value;
    }
}
