<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\TracingId;
use PHPUnit\Framework\TestCase;

class TracingMiddlewareTest extends TestCase
{
    public function testGenerateTraceIdIs32HexChars(): void
    {
        $ref = new \ReflectionClass(TracingId::class);
        $method = $ref->getMethod('generateTraceId');
        $method->setAccessible(true);
        $middleware = new TracingId();
        $traceId = $method->invoke($middleware);
        $this->assertEquals(32, strlen($traceId));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $traceId);
    }

    public function testTraceIdsAreUnique(): void
    {
        $ref = new \ReflectionClass(TracingId::class);
        $method = $ref->getMethod('generateTraceId');
        $method->setAccessible(true);
        $middleware = new TracingId();
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $method->invoke($middleware);
        }
        $this->assertCount(100, array_unique($ids));
    }
}
