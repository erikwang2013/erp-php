<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class TracingId implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $traceId = $request->header('X-Trace-Id', '') ?: $this->generateTraceId();
        $request->traceId = $traceId;

        /** @var Response $response */
        $response = $handler($request);

        return $response->withHeader('X-Trace-Id', $traceId);
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
