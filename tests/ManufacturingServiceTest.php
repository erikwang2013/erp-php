<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\manufacturing\ManufacturingService;
use PHPUnit\Framework\TestCase;

/**
 * ManufacturingService 纯逻辑单元测试（P2-F2 服务层轻量提取）
 * 覆盖不依赖数据库的逻辑：生产工单/BOM 状态机与流转校验、分页参数归一化。
 */
class ManufacturingServiceTest extends TestCase
{
    /**
     * Service 类存在且可实例化
     */
    public function testManufacturingServiceIsInstantiable(): void
    {
        $class = 'app\\service\\manufacturing\\ManufacturingService';
        $this->assertTrue(class_exists($class));
        $service = new $class();
        $this->assertInstanceOf(ManufacturingService::class, $service);
    }

    /**
     * 生产工单状态流转图：0待生产→1生产中→2已完成，2 为终态
     */
    public function testProductionOrderStatusFlow(): void
    {
        $service = new ManufacturingService();
        $this->assertSame([0 => [1], 1 => [2], 2 => []], $service->productionOrderStatusFlow());
    }

    /**
     * 工单开始/完成校验：仅待生产可开始，仅生产中可完成
     */
    public function testProductionOrderTransitionValidation(): void
    {
        $service = new ManufacturingService();
        // 开始生产
        $this->assertTrue($service->canStartProduction(0));
        $this->assertFalse($service->canStartProduction(1), '生产中不可重复开始');
        $this->assertFalse($service->canStartProduction(2), '已完成不可开始');
        // 完成生产
        $this->assertTrue($service->canCompleteProduction(1));
        $this->assertFalse($service->canCompleteProduction(0), '待生产不可完成');
        $this->assertFalse($service->canCompleteProduction(2), '已完成不可重复完成');
    }

    /**
     * BOM 状态流转图：0草稿→1已生效/2已失效；1已生效→2已失效；2已失效→1可重新生效
     */
    public function testBomStatusFlow(): void
    {
        $service = new ManufacturingService();
        $this->assertSame([0 => [1, 2], 1 => [2], 2 => [1]], $service->bomStatusFlow());
    }

    /**
     * BOM 生效校验：已生效(1)不可重复生效，草稿/失效可生效
     */
    public function testBomActivateValidation(): void
    {
        $service = new ManufacturingService();
        $this->assertTrue($service->canActivateBom(0));
        $this->assertTrue($service->canActivateBom(2));
        $this->assertFalse($service->canActivateBom(1), 'BOM 已生效不可重复生效');
    }

    /**
     * 通用状态流转校验助手（继承自 AbstractCrudService）
     */
    public function testCanTransitionHelper(): void
    {
        $service = new ManufacturingService();
        $flow = [0 => [1], 1 => [2], 2 => []];
        $this->assertTrue($service->canTransition(0, 1, $flow));
        $this->assertFalse($service->canTransition(1, 0, $flow));
        $this->assertFalse($service->canTransition(2, 1, $flow));
    }

    /**
     * 继承的分页参数归一化（跨服务复用验证）
     */
    public function testNormalizePageParams(): void
    {
        $service = new ManufacturingService();
        [$page, $limit] = $service->normalizePageParams(1, 15);
        $this->assertSame([1, 15], [$page, $limit]);
        [$page, $limit] = $service->normalizePageParams(0, -1);
        $this->assertSame([1, 1], [$page, $limit]);
        [$page, $limit] = $service->normalizePageParams(5, 999);
        $this->assertSame([5, 100], [$page, $limit]);
    }
}
