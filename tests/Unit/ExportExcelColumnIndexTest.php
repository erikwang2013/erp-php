<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use app\admin\controller\ExportController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use tests\FakeRequest;

/**
 * PHP 8.5 对非数字字符串自增（$col = 'A'; $col++）抛 E_DEPRECATED，
 * webman 的 set_error_handler 会升级为 ErrorException，导致 Excel 导出 500。
 * 列号必须走整数 + Coordinate::stringFromColumnIndex()。
 */
class ExportExcelColumnIndexTest extends TestCase
{
    #[Test]
    public function excel_export_does_not_increment_string_column_index(): void
    {
        $deprecations = [];
        set_error_handler(function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity === E_DEPRECATED && str_contains($message, 'Increment on non-numeric string')) {
                $deprecations[] = $message;

                return true;
            }

            return false;
        });

        try {
            // 未登记的表不查库；显式 columns 仍会走表头列号逻辑，无需 DB
            $request = new FakeRequest([
                'table' => 'not_a_table',
                'columns' => ['id', 'username', 'email'],
                'title' => 'T',
            ]);
            $response = (new ExportController())->excel($request);
        } finally {
            restore_error_handler();
        }

        $file = $response->file['file'] ?? null;
        if ($file) {
            @unlink($file);
        }

        $this->assertInstanceOf(\support\Response::class, $response);
        $this->assertSame([], $deprecations);

        // 静态兜底：数据行循环里的同类写法也一并防回归
        $source = file_get_contents(__DIR__ . '/../../app/admin/controller/ExportController.php');
        $this->assertStringNotContainsString("\$colIndex = 'A'", $source);
    }
}
