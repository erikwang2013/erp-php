<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\OperationLog;
use PHPUnit\Framework\TestCase;

/**
 * 操作日志脱敏：凭据与个人/财务敏感字段不得明文落库（含嵌套数组与 camelCase 键）。
 */
class OperationLogRedactionTest extends TestCase
{
    public function testNestedPiiIsRedactedAndNormalFieldsKept(): void
    {
        $input = [
            'id' => 123,
            'name' => '张三',
            'phone' => '13812345678',
            'email' => 'zhang@example.com',
            'id_card' => '110101199001011234',
            'bank_account' => '6222021234567890123',
            'tax_number' => '91310000TEST',
            'account_number' => '6222020000001234',
            'password' => 'secret-pass',
            'app_secret' => 'app-secret',
            'nested' => [
                'contact_phone' => '13900001111',
                'address' => '上海市某路 1 号',
                'address_line1' => '某路 1 号',
                'contact_name' => '李四',
                'contact_person' => '李四',
                'phone_number' => '13700002222',
                'birthday' => '1990-01-01',
                'emergency_contact' => '王五',
                'nickname' => '小张',
                'items' => [
                    ['sku' => 'A-1', 'bankAccount' => '6222029999999999'],
                ],
            ],
        ];

        $out = OperationLog::redactPayload($input);

        foreach ([
            'phone', 'email', 'id_card', 'bank_account', 'tax_number', 'account_number',
            'password', 'app_secret',
        ] as $key) {
            $this->assertSame('***', $out[$key], "{$key} 应被脱敏");
        }
        $this->assertSame('***', $out['nested']['contact_phone']);
        $this->assertSame('***', $out['nested']['address']);
        foreach (['address_line1', 'contact_name', 'contact_person', 'phone_number', 'birthday', 'emergency_contact'] as $key) {
            $this->assertSame('***', $out['nested'][$key], "{$key} 应被脱敏");
        }
        $this->assertSame('***', $out['nested']['items'][0]['bankAccount'], 'camelCase 键也应脱敏');

        $this->assertSame(123, $out['id'], '普通 id 不应误伤');
        $this->assertSame('张三', $out['name']);
        $this->assertSame('小张', $out['nested']['nickname']);
        $this->assertSame('A-1', $out['nested']['items'][0]['sku']);
    }

    public function testCredentialsRedactedRegardlessOfCaseAndUnderscore(): void
    {
        $out = OperationLog::redactPayload([
            'Password' => 'p',
            'oldPassword' => 'p',
            'new_password_confirmation' => 'p',
            'accessToken' => 't',
            'refresh_token' => 't',
        ]);

        $this->assertSame(['***', '***', '***', '***', '***'], array_values($out));
    }
}
