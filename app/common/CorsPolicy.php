<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Webman\Http\Request;

/**
 * CORS 策略：从 CORS_ALLOWED_ORIGIN 环境变量读取来源白名单（逗号分隔），
 * 命中请求 Origin 时回显该来源；未配置或未命中则不返回 CORS 头。
 */
class CorsPolicy
{
    private const ALLOWED_METHODS = 'GET,POST,PUT,DELETE,OPTIONS';
    private const ALLOWED_HEADERS = 'Authorization,Content-Type,API-Version';

    public static function allowedOrigin(Request $request): ?string
    {
        $origin = $request->header('Origin');
        if (!$origin) {
            return null;
        }

        $list = array_filter(array_map('trim', explode(',', (string) getenv('CORS_ALLOWED_ORIGIN'))));
        if (in_array($origin, $list, true)) {
            return $origin;
        }

        return null;
    }

    /** 非预检响应的跨域头（未命中白名单时为空数组） */
    public static function responseHeaders(Request $request): array
    {
        $origin = self::allowedOrigin($request);
        if ($origin === null) {
            return [];
        }

        return ['Access-Control-Allow-Origin' => $origin];
    }

    /** 预检响应的跨域头（未命中白名单时仅返回方法/头声明，不带 Allow-Origin） */
    public static function preflightHeaders(Request $request): array
    {
        $headers = [
            'Access-Control-Allow-Methods' => self::ALLOWED_METHODS,
            'Access-Control-Allow-Headers' => self::ALLOWED_HEADERS,
            'Access-Control-Max-Age' => '86400',
        ];

        $origin = self::allowedOrigin($request);
        if ($origin !== null) {
            $headers['Access-Control-Allow-Origin'] = $origin;
        }

        return $headers;
    }
}
