#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * cleanup-report-datasets.php — 清理修复前生成的敏感报表数据集（默认 dry-run）
 *
 * 用法：
 *   php scripts/cleanup-report-datasets.php           # 只统计
 *   php scripts/cleanup-report-datasets.php --apply   # 实际删除不安全数据集
 */

declare(strict_types=1);

use app\controller\report\ReportController;
use app\model\ReportDataset;

$args = array_slice($argv ?? [], 1);
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, <<<'TXT'
用法: php scripts/cleanup-report-datasets.php [--apply] [--help]

  (无参数)    dry-run：统计 query_sql 含敏感表/SELECT */敏感列的数据集
  --apply     实际删除这些历史数据集（安全报表数据集保留）
  --help, -h  显示本帮助

执行前请备份数据库；删除后报表需重新执行生成。
TXT
    );
    exit(0);
}
$apply = in_array('--apply', $args, true);
foreach ($args as $arg) {
    if (!in_array($arg, ['--apply', '--help', '-h'], true)) {
        fwrite(STDERR, "未知参数: {$arg}\n使用 --help 查看用法。\n");
        exit(1);
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/support/bootstrap.php';

$unsafe = 0;
$safe = 0;
$deleted = 0;

try {
    ReportDataset::query()->chunkById(200, function ($rows) use ($apply, &$unsafe, &$safe, &$deleted): void {
        foreach ($rows as $row) {
            if (!ReportController::isUnsafeDatasetSql((string) $row->query_sql)) {
                $safe++;
                continue;
            }
            $unsafe++;
            if ($apply) {
                $row->delete();
                $deleted++;
            }
        }
    });
} catch (\Throwable $e) {
    fwrite(STDERR, '清理失败: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf(
    "扫描完成：不安全 %d 条，安全保留 %d 条，本次删除 %d 条%s",
    $unsafe,
    $safe,
    $deleted,
    PHP_EOL
));
if (!$apply) {
    fwrite(STDOUT, '当前为 dry-run，未删除；确认后加 --apply 执行。' . PHP_EOL);
}
