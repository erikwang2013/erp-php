<?php

declare(strict_types=1);

namespace support;

use Composer\Console\Application as ComposerApplication;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Terminal;

/**
 * create-project setup wizard: interactive locale, timezone and optional components selection, then runs composer require.
 */
class ExceptionHandle
{
    // --- Optional component package names ---
    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof \hg\apidoc\exception\HttpException) {
            return response(json_encode([
                "code" => $exception->getCode(),
                "message" => $exception->getMessage(),
            ],JSON_UNESCAPED_UNICODE), $exception->getStatusCode());
        }
        
        return parent::render($request, $exception);
    }
}