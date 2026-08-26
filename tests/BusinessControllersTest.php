<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\crm\ContactController;
use app\controller\finance\ArApController;
use app\controller\hr\PositionController;
use app\controller\inventory\CheckTaskController;
use app\controller\manufacturing\ProductionController;
use app\controller\oms\ChannelController;
use app\controller\product\BrandController;
use app\controller\quality\InspectionStandardController;
use app\controller\tms\CarrierController;
use app\controller\wms\LocationController;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 业务模块控制器代表覆盖：每域一个核心控制器的 store 校验失败路径（无 DB 依赖）
 */
class BusinessControllersTest extends TestCase
{
    private function code(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    public function testWmsLocationStoreRejectsMissingCode(): void
    {
        $resp = (new LocationController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testTmsCarrierStoreRejectsMissingName(): void
    {
        $resp = (new CarrierController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testProductBrandStoreRejectsMissingName(): void
    {
        $resp = (new BrandController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testCrmContactStoreRejectsMissingName(): void
    {
        $resp = (new ContactController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testOmsChannelStoreRejectsMissingName(): void
    {
        $resp = (new ChannelController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testFinanceArApStoreRejectsMissingType(): void
    {
        $resp = (new ArApController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testQualityInspectionStandardStoreRejectsMissingName(): void
    {
        $resp = (new InspectionStandardController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testHrPositionStoreRejectsMissingName(): void
    {
        $resp = (new PositionController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testManufacturingProductionStoreRejectsMissingFields(): void
    {
        $resp = (new ProductionController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testInventoryCheckTaskStoreRejectsMissingName(): void
    {
        $resp = (new CheckTaskController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testFinanceArApStoreRejectsNegativeAmount(): void
    {
        $resp = (new ArApController())->store(new FakeRequest([
            'type' => 1,
            'partner_id' => 1,
            'amount' => -5,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    // 校验通过后的落库路径依赖真实 MySQL，属集成测试范畴，单测仅覆盖校验失败分支。
}
