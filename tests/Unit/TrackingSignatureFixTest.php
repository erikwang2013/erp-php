<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\TrackingSignature;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/**
 * 物流轨迹回调签名中间件 fail-closed 回归测试
 *
 * 覆盖：TRACKING_WEBHOOK_SECRET 未配置时拒绝请求（503），不得放行；
 * 配置后正常校验签名（缺失签名头返回 401）。
 */
class TrackingSignatureFixTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TRACKING_WEBHOOK_SECRET');
    }

    private function makeRequest(string $body = ''): Request
    {
        $buffer = "POST /api/tms/tracking/callback HTTP/1.1\r\n"
            . 'Host: localhost' . "\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body;

        return new Request($buffer);
    }

    public function testSecretMissingRejectsRequest(): void
    {
        putenv('TRACKING_WEBHOOK_SECRET');
        $middleware = new TrackingSignature();
        $response = $middleware->process($this->makeRequest('{"status":"delivered"}'), fn ($r) => $r);
        $this->assertSame(503, $response->getStatusCode());
        $payload = json_decode($response->rawBody(), true);
        $this->assertSame(503, $payload['code'] ?? null);
    }

    public function testSecretConfiguredStillVerifiesSignature(): void
    {
        putenv('TRACKING_WEBHOOK_SECRET=test-secret-123');
        $middleware = new TrackingSignature();
        // 配置了 secret 但缺少签名头 → 401，证明校验链路仍然生效
        $response = $middleware->process($this->makeRequest('{}'), fn ($r) => $r);
        $this->assertSame(401, $response->getStatusCode());
    }
}
