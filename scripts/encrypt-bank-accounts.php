#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * encrypt-bank-accounts.php — 历史明文银行账号加密迁移（默认 dry-run）
 *
 * 用法：
 *   php scripts/encrypt-bank-accounts.php           # 只统计待转换数量，不写库
 *   php scripts/encrypt-bank-accounts.php --apply   # 实际写库（先备份，建议停写）
 */

declare(strict_types=1);

use app\model\FinanceBankAccount;
use Erikwang2013\Encryptable\Encryption;

$args = array_slice($argv ?? [], 1);
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, <<<'TXT'
用法: php scripts/encrypt-bank-accounts.php [--apply] [--help]

  (无参数)    dry-run：仅统计 erp_finance_bank_account 中待转换的明文账号
  --apply     实际写库：逐条经 EncryptableOrLegacy cast 加密 account_number
  --help, -h  显示本帮助

已加密或空值的记录自动跳过；可用 --apply 重复执行（幂等）。
执行前请备份数据库，并尽量停止银行账户写入。
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

$pending = 0;
$skipped = 0;
$converted = 0;
$failed = 0;

try {
    FinanceBankAccount::query()->chunkById(200, function ($rows) use (
        $apply,
        &$pending,
        &$skipped,
        &$converted,
        &$failed
    ): void {
        foreach ($rows as $row) {
            $raw = $row->getRawOriginal('account_number');
            if (!is_string($raw) || $raw === '' || Encryption::isEncrypted($raw)) {
                $skipped++;
                continue;
            }
            $pending++;
            if (!$apply) {
                continue;
            }
            try {
                $row->account_number = $raw; // cast 负责加密，读取时自动解密
                $row->save();
                $converted++;
            } catch (\Throwable $e) {
                $failed++;
                fwrite(STDERR, 'ID ' . $row->getKey() . ' 转换失败: ' . $e->getMessage() . PHP_EOL);
            }
        }
    });
} catch (\Throwable $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf(
    "扫描完成：待转换 %d 条，已加密/空值跳过 %d 条，本次转换 %d 条，失败 %d 条%s",
    $pending,
    $skipped,
    $converted,
    $failed,
    PHP_EOL
));
if (!$apply) {
    fwrite(STDOUT, '当前为 dry-run，未写库；确认后加 --apply 执行。' . PHP_EOL);
}
exit($failed > 0 ? 1 : 0);
