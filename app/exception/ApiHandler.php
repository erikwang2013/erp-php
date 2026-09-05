<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\exception;

use support\exception\Handler;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * API JSON 异常处理器
 *
 * 将未捕获异常转换为统一 JSON 格式，提升客户端体验。
 */
class ApiHandler extends Handler
{
    public function render(Request $request, Throwable $exception): Response
    {
        $path = $request->path();

        if (!str_starts_with($path, '/api') && !str_starts_with($path, '/admin')) {
            return parent::render($request, $exception);
        }

        // getCode() 某些异常实现返回 numeric-string（strict_types 下不会强转），
        // 统一归 int 再判 HTTP 状态区间，否则 withStatus(int) 抛 TypeError
        $code = $exception->getCode();
        $code = is_numeric($code) ? (int) $code : 0;
        $statusCode = ($code >= 400 && $code < 600) ? $code : 500;

        $debug = method_exists($this, 'debug') && !$this->debug;
        $message = $debug && $statusCode === 500
            ? '服务器内部错误'
            : ($exception->getMessage() ?: get_class($exception));

        return json([
            'code' => $statusCode,
            'message' => $message,
            'data' => [],
        ])->withStatus($statusCode);
    }
}
