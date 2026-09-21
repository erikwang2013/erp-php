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
use app\controller\wms\AsnController;
use app\controller\wms\LocationController;
use app\controller\wms\PackController;
use app\controller\wms\PickController;
use app\controller\wms\PutawayController;
use app\controller\wms\ReceivingController;
use app\controller\wms\WaveController;
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

    public function testWmsLocationStoreRejectsMissingLocationId(): void
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

    /**
     * 原用例断言 name 必填——该列在 erp_check_task 不存在（幻列），规则已删，
     * 空请求会直接落库、单测无 DB 必炸；改断言本端点仍真实存在的校验分支。
     */
    public function testInventoryCheckTaskStoreRejectsNonIntegerStatus(): void
    {
        $resp = (new CheckTaskController())->store(new FakeRequest(['status' => 'abc']));
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

    /**
     * WMS 六张单据表（asn/receiving/putaway/pick/pack/wave）的 warehouse_id 是 NOT NULL 无默认列
     * （asn 另有同款的 supplier_id）：请求体缺省时原先直插 → MySQL 1364 → 500。
     * 现要求缺省与垃圾串都在边界拦成 422。
     */
    public function testWmsStoresRequireWarehouseId(): void
    {
        $controllers = [
            new AsnController(),
            new ReceivingController(),
            new PutawayController(),
            new PickController(),
            new PackController(),
            new WaveController(),
        ];
        foreach ($controllers as $controller) {
            $class = $controller::class;
            $missing = $controller->store(new FakeRequest(['code' => 'PROBE-1']));
            $this->assertSame(422, $this->code($missing), "{$class} 缺 warehouse_id 应 422");

            $garbage = $controller->store(new FakeRequest([
                'code' => 'PROBE-1',
                'warehouse_id' => 'not-a-hashid',
            ]));
            $this->assertSame(422, $this->code($garbage), "{$class} 垃圾 warehouse_id 应 422");
        }
    }

    // 校验通过后的落库路径依赖真实 MySQL，属集成测试范畴，单测仅覆盖校验失败分支。
}
