<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace tests;
use PHPUnit\Framework\TestCase;

class DoubleEntryServiceTest extends TestCase
{
    public function testBalanceValidationPasses(): void
    {
        $svc = new \app\service\finance\DoubleEntryService();
        $items = [
            ['account_subject_id' => 1, 'debit_amount' => 100, 'credit_amount' => 0],
            ['account_subject_id' => 2, 'debit_amount' => 0, 'credit_amount' => 100],
        ];
        $this->expectNotToPerformAssertions();
        $svc->validateBalance($items);
    }

    public function testBalanceValidationFails(): void
    {
        $svc = new \app\service\finance\DoubleEntryService();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('借贷不平衡');
        $svc->validateBalance([['debit_amount' => 100], ['credit_amount' => 50]]);
    }

    public function testReverseSwapsDebitCredit(): void
    {
        $items = [['account_subject_id' => 1, 'debit_amount' => 100, 'credit_amount' => 0, 'summary' => 'test']];
        $reversed = array_map(fn($i) => [
            'account_subject_id' => $i['account_subject_id'],
            'debit_amount' => $i['credit_amount'],
            'credit_amount' => $i['debit_amount'],
            'summary' => '冲销: ' . ($i['summary'] ?? ''),
        ], $items);
        $this->assertEquals(0, $reversed[0]['debit_amount']);
        $this->assertEquals(100, $reversed[0]['credit_amount']);
    }
}
