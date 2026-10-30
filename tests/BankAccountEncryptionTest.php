<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\FinanceBankAccount;
use Erikwang2013\Encryptable\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * 银行账号存储加密与输出脱敏（不连库，仅验证 cast 与纯函数）。
 */
class BankAccountEncryptionTest extends TestCase
{
    public function testNewValueEncryptedAndReadable(): void
    {
        $plain = '6222021234567890123';
        $account = new FinanceBankAccount();
        $account->account_number = $plain;

        $raw = $account->getAttributes()['account_number'];
        $this->assertNotSame($plain, $raw, '写入值应为密文');
        $this->assertTrue(Encryption::isEncrypted($raw), '写入值应可识别为密文');
        $this->assertSame($plain, $account->account_number, '读取应自动解密');
    }

    public function testLegacyPlaintextStillReadable(): void
    {
        $account = new FinanceBankAccount();
        $account->setRawAttributes(['account_number' => '6222020000001234']);

        $this->assertSame('6222020000001234', $account->account_number, '历史明文应原样返回');
    }

    public function testMaskKeepsLastFourDigits(): void
    {
        $this->assertSame('***************0123', FinanceBankAccount::maskAccountNumber('6222021234567890123'));
        $this->assertSame('****', FinanceBankAccount::maskAccountNumber('1234'));
        $this->assertSame('***', FinanceBankAccount::maskAccountNumber('123'));
        $this->assertSame('', FinanceBankAccount::maskAccountNumber(''));
        $this->assertSame('', FinanceBankAccount::maskAccountNumber(null));
    }

    public function testMaskedInputDetected(): void
    {
        $this->assertTrue(FinanceBankAccount::isMaskedAccountNumber('***************0123'));
        $this->assertTrue(FinanceBankAccount::isMaskedAccountNumber('***'));
        $this->assertFalse(FinanceBankAccount::isMaskedAccountNumber('6222021234567890123'));
        $this->assertFalse(FinanceBankAccount::isMaskedAccountNumber('6222****0123'));
        $this->assertFalse(FinanceBankAccount::isMaskedAccountNumber(null));
    }

    public function testUpdateRejectsBalanceOverwriteButStoreAllowsOpeningBalance(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/controller/finance/BankAccountController.php');
        $store = $this->methodBody($source, 'store');
        $update = $this->methodBody($source, 'update');

        $this->assertStringNotContainsString("unset(\$data['balance'])", $store, '创建时允许录入期初余额');
        $this->assertStringContainsString('$item->fill($data);', $store);

        $this->assertStringContainsString("unset(\$data['balance']);", $update, '更新不得接受余额覆盖');
        $this->assertStringContainsString('$item->fill($data);', $update);
        $this->assertContains('balance', (new FinanceBankAccount())->getFillable(), 'store 仍可经 fillable 写入期初余额');
    }

    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, "public function {$method}(");
        $this->assertNotFalse($start, "未找到 {$method}()");
        $next = strpos($source, "\n    public function ", $start + 1);
        $end = $next === false ? strlen($source) : $next;

        return substr($source, $start, $end - $start);
    }
}
