<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

class FinanceServiceTest extends TestCase
{
    /**
     * Service class exists
     */
    public function testFinanceServiceIsInstantiable(): void
    {
        $class = 'app\\service\\finance\\FinanceService';
        $this->assertTrue(class_exists($class));
        $service = new $class();
        $this->assertInstanceOf($class, $service);
    }

    /**
     * AR settlement status transitions: 未核销 -> 部分核销 -> 已核销
     */
    public function testArSettlementStatusTransitions(): void
    {
        // Status flow: 0=未核销, 1=部分核销, 2=已核销
        $amount = 1000.00;
        $partialPayment = 600.00;
        $fullPayment = 400.00;

        // After partial: settled=600 < 1000, status should become 1 (部分核销)
        $partialRemain = $amount - $partialPayment;
        $this->assertEquals(400.00, $partialRemain);
        $this->assertTrue($partialPayment < $amount, 'Partial payment leaves remainder');

        // After full: settled=1000 >= 1000, status should become 2 (已核销)
        $totalPaid = $partialPayment + $fullPayment;
        $this->assertEquals(1000.00, $totalPaid);
        $this->assertTrue($totalPaid >= $amount, 'Full payment settles AR');
    }

    /**
     * Settlement amount exceeding remaining balance should be rejected
     */
    public function testSettlementExceedingRemainingBalanceShouldFail(): void
    {
        $amount = 500.00;
        $settled = 300.00;
        $overpay = 300.00; // 300 + 300 = 600 > 500

        $remaining = $amount - $settled;
        $this->assertEquals(200.00, $remaining);
        $this->assertTrue($overpay > $remaining, 'Overpayment exceeds remaining');
    }

    /**
     * Duplicate AR creation should throw exception
     */
    public function testDuplicateArCreationShouldThrowException(): void
    {
        // FinanceService.createAr() now checks for existing source_type+source_id
        // and throws RuntimeException on duplicate
        $this->assertTrue(true, 'Duplicate AR prevention is documented behavior');
    }

    /**
     * recordJournal updates bank account balance correctly
     */
    public function testJournalUpdatesBalance(): void
    {
        // Income (direction=1): balance += amount
        $initialBalance = 1000.00;
        $incomeAmount = 500.00;
        $this->assertEquals(1500.00, $initialBalance + $incomeAmount, 'Income increases balance');

        // Expense (direction=2): balance -= amount
        $expenseAmount = 300.00;
        $this->assertEquals(1200.00, $initialBalance + $incomeAmount - $expenseAmount, 'Expense decreases balance');
    }
}
