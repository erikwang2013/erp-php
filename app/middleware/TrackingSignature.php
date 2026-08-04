<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 物流轨迹回调 HMAC 签名验证
 *
 * 承运商回调时携带 X-Tracking-Signature 头，
 * 签名算法: HMAC-SHA256(timestamp.body, secret)
 * 容忍 5 分钟时钟偏差
 */
class TrackingSignature implements MiddlewareInterface
{
    private const MAX_AGE = 300;

    public function process(Request $request, callable $next): Response
    {
        $secret = getenv('TRACKING_WEBHOOK_SECRET') ?: '';
        if ($secret === '') {
            return $next($request);
        }

        $timestamp = (int) $request->header('X-Tracking-Timestamp', '0');
        $receivedSig = $request->header('X-Tracking-Signature', '');

        if (!$timestamp || !$receivedSig) {
            return json(['code' => 401, 'message' => 'Missing signature headers', 'data' => []])->withStatus(401);
        }

        if (abs(time() - $timestamp) > self::MAX_AGE) {
            return json(['code' => 401, 'message' => 'Request expired', 'data' => []])->withStatus(401);
        }

        $body = $request->rawBody();
        $expected = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        if (!hash_equals($expected, $receivedSig)) {
            return json(['code' => 403, 'message' => 'Invalid signature', 'data' => []])->withStatus(403);
        }

        return $next($request);
    }
}
