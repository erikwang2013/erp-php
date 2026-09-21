<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\exception;

use app\common\I18n;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
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

        // 唯一键冲突是**客户端可纠正的输入问题**，不是服务端故障：各模块 store() 直落
        // uk_code（采购申请/采购订单/收货单等 code 唯一键），手填重复单号、或前端自造
        // 秒级时间戳单号同秒两次提交，都会抛 1062。原样走下面的 500 分支用户只拿到一句
        // 「服务器内部错误」+ TraceId，无从下手；这里回到 422 并点名单号。
        // 必须排在 debug 分支之前：唯一键冲突的 getCode() 也是 500，放后面会被吞掉。
        if ($exception instanceof UniqueConstraintViolationException) {
            // 异常原文形如 Duplicate entry 'PA2026…' for key 'erp_purchase_apply.uk_code'
            $dup = preg_match("/Duplicate entry '([^']*)'/", $exception->getMessage(), $m) ? $m[1] : '';

            return json([
                'code' => 422,
                'message' => I18n::trans(
                    $dup === ''
                        ? 'Record already exists'
                        : 'Number ":code" already exists; please refresh the page and use another one',
                    ['code' => $dup]
                ),
                'data' => [],
            ])->withStatus(422);
        }

        // 数据超长（SQLSTATE 22001 / MySQL 1406）同属「客户端可纠正的输入问题」：部分控制器的
        // max 校验宽度比真实列宽大（例：BrandController 的 name 写 max:200，而 erp_brand.name
        // 是 VARCHAR(100)），超长输入过得了校验、到 MySQL 才炸，原本用户只看到 500 + TraceId。
        // 这里兜底回 422，复用既有「参数验证失败」文案（不新增词典键）；报文只给通用提示，
        // 含表名/列名的 MySQL 原文进日志。各站点仍应按真实列宽收紧校验（本轮另做清扫）。
        if ($exception instanceof QueryException && ($exception->errorInfo[0] ?? '') === '22001') {
            Log::warning('输入超出列宽，已按 422 拒绝 TraceId=' . trace_id() . '：' . $exception->getMessage());

            return json([
                'code' => 422,
                'message' => I18n::trans('Validation failed'),
                'data' => [],
            ])->withStatus(422);
        }

        // 无效入参类异常本质是客户端输入问题，不是服务端故障。最典型的是 hashid 解码失败
        // （HashidsService::decode 抛 InvalidArgumentException「无效的加密ID」，各控制器
        // 的 decodeId() 直接透传）：路径/查询里的 ID 不是合法 hashid（旧书签、被截断的串、
        // 扫描器批量探路径）时，未捕获就是 500 + TraceId，用户看不出「这条链接的 ID 不对」。
        // 服务层（product/eam/project 等）抛的同类异常消息本就面向用户，故原样回 422。
        if ($exception instanceof InvalidArgumentException) {
            return json([
                'code' => 422,
                'message' => I18n::trans($exception->getMessage()),
                'data' => [],
            ])->withStatus(422);
        }

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
