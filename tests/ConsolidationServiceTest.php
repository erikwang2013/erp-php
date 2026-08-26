<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\finance\ConsolidationService;
use PHPUnit\Framework\TestCase;

/**
 * 合并报表服务：多币种合并（当前为预留结构，返回空合并结果）
 */
class ConsolidationServiceTest extends TestCase
{
    public function testDefaultBaseCurrencyIsCny(): void
    {
        $result = (new ConsolidationService())->consolidate([['currency' => 'USD']]);
        $this->assertSame('CNY', $result['base_currency']);
    }

    public function testCustomBaseCurrencyHonored(): void
    {
        $result = (new ConsolidationService())->consolidate([], 'EUR');
        $this->assertSame('EUR', $result['base_currency']);
    }

    public function testExchangeGainLossStartsAtZero(): void
    {
        $result = (new ConsolidationService())->consolidate([]);
        $this->assertSame(0, $result['exchange_gain_loss']);
        $this->assertSame([], $result['consolidated']);
    }

    public function testEmptySubsidiaryReportsAccepted(): void
    {
        $result = (new ConsolidationService())->consolidate([]);
        $this->assertIsArray($result['consolidated']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testResultContainsRequiredKeys(): void
    {
        $result = (new ConsolidationService())->consolidate([['currency' => 'USD']], 'CNY');
        foreach (['base_currency', 'exchange_gain_loss', 'consolidated', 'message'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
