<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\common\CorsPolicy;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $nonce = base64_encode(random_bytes(16));

        if ($request->method() === 'OPTIONS') {
            return response('', 204, CorsPolicy::preflightHeaders($request));
        }

        $response = $handler($request);
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: blob:; connect-src 'self';",
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        return $response->withHeaders(array_merge($headers, CorsPolicy::responseHeaders($request)));
    }
}
