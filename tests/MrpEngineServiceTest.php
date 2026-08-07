<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\manufacturing\MrpEngineService;
use PHPUnit\Framework\TestCase;

class MrpEngineServiceTest extends TestCase
{
    public function testNetRequirement(): void
    {
        $svc = new MrpEngineService();
        $this->assertEquals(70, $svc->calculateNetRequirement(100, 30));
        $this->assertEquals(90, $svc->calculateNetRequirement(100, 30, 0, 0, 20));
        $this->assertEquals(0, $svc->calculateNetRequirement(50, 100, 20));
    }

    public function testBomSingleLevel(): void
    {
        $r = (new MrpEngineService())->explodeBom([
            ['item_id' => 101, 'quantity' => 2, 'loss_rate' => 5, 'children' => []],
            ['item_id' => 102, 'quantity' => 3, 'loss_rate' => 0, 'children' => []],
        ], 10);
        $this->assertEquals(21, $r[101]);
        $this->assertEquals(30, $r[102]);
    }

    public function testBomMultiLevel(): void
    {
        $r = (new MrpEngineService())->explodeBom([[
            'item_id' => 1, 'quantity' => 1, 'loss_rate' => 0, 'children' => [
                ['item_id' => 2, 'quantity' => 3, 'loss_rate' => 10, 'children' => [
                    ['item_id' => 3, 'quantity' => 2, 'loss_rate' => 0, 'children' => []],
                ]],
            ],
        ]], 1);
        $this->assertEquals(1.0, $r[1]);
        $this->assertEquals(3.3, $r[2]);
        $this->assertEquals(6.6, $r[3]);
    }

    public function testOrderSuggestion(): void
    {
        $svc = new MrpEngineService();
        $this->assertEquals(100, $svc->generateOrderSuggestion(85, 7, 50)['quantity']);
        $this->assertEquals(100, $svc->generateOrderSuggestion(10, 3, 0, 100)['quantity']);
    }
}
