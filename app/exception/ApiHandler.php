<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\exception;

use support\exception\Handler;
use support\Log;
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

        // 框架 Handler::$debug 是**属性**（构造时由 config('app.debug') 注入），不是方法。
        // 原实现写的是 method_exists($this, 'debug') —— 恒为 false，于是「非 debug 时回
        // 通用文案」整段成了死代码：任何 500 都把原始异常原样回给客户端（实测登录时
        // 库不存在，SQLSTATE、库名与整条 SQL 一并吐出，既泄露内部结构又不是用户能行动的提示）。
        $debug = (bool) $this->debug;
        if (!$debug && $statusCode === 500) {
            $traceId = trace_id();
            // report() 已把完整异常（含堆栈）写进日志，这里再补一条带 TraceId 的摘要：
            // 500 走框架异常出口、不过 TracingId 中间件，响应拿不到 X-Trace-Id 头，
            // TraceId 只能随报文回给客户端，否则日志里成堆的异常无法与用户报的那次对上。
            Log::error("未捕获异常 TraceId={$traceId}：" . $exception->getMessage());

            return json([
                'code' => 500,
                'message' => "服务器内部错误，请稍后重试（TraceId: {$traceId}）",
                'data' => [],
            ])->withStatus(500);
        }

        $message = $exception->getMessage() ?: get_class($exception);

        return json([
            'code' => $statusCode,
            'message' => $message,
            'data' => [],
        ])->withStatus($statusCode);
    }
}
