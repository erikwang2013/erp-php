<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\middleware;

use app\common\I18n;
use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Locale implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        I18n::getLocale($request);
        return $next($request);
    }
}
