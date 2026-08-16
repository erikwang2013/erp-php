<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\product\ProductService;
use PHPUnit\Framework\TestCase;
use support\Container;

/**
 * ProductService 纯逻辑单元测试（P2-F2 服务层轻量提取）
 * 覆盖不依赖数据库的逻辑：SKU 数据归一化、分页参数归一化、状态流转校验，
 * 以及容器 class_exists 回退解析（config/dependence.php 为 dead config 时的实际依赖注入路径）。
 */
class ProductServiceTest extends TestCase
{
    /**
     * Service 类存在且可实例化（无参构造）
     */
    public function testProductServiceIsInstantiable(): void
    {
        $class = 'app\\service\\product\\ProductService';
        $this->assertTrue(class_exists($class));
        $service = new $class();
        $this->assertInstanceOf(ProductService::class, $service);
    }

    /**
     * SKU 数据归一化：缺省字段按默认值、spec_attrs JSON 序列化、cost_price 数值化、status 固定 1
     */
    public function testNormalizeSkuDefaults(): void
    {
        $service = new ProductService();
        $sku = $service->normalizeSku([]);
        $this->assertSame('', $sku['sku_code']);
        $this->assertSame('', $sku['barcode']);
        $this->assertSame('[]', $sku['spec_attrs']);
        $this->assertSame(0.0, $sku['cost_price']);
        $this->assertSame(1, $sku['status']);
    }

    /**
     * SKU 数据归一化：完整字段透传，spec_attrs 支持中文/嵌套结构
     */
    public function testNormalizeSkuFullPayload(): void
    {
        $service = new ProductService();
        $sku = $service->normalizeSku([
            'sku_code' => 'SKU-001',
            'barcode' => '6901234567890',
            'spec_attrs' => ['颜色' => '红色', '尺寸' => 'M'],
            'cost_price' => '99.5',
        ]);
        $this->assertSame('SKU-001', $sku['sku_code']);
        $this->assertSame('6901234567890', $sku['barcode']);
        $this->assertSame('{"颜色":"红色","尺寸":"M"}', $sku['spec_attrs']);
        $this->assertSame(99.5, $sku['cost_price']);
        $this->assertSame(1, $sku['status']);
    }

    /**
     * 继承的分页参数归一化
     */
    public function testNormalizePageParams(): void
    {
        $service = new ProductService();
        [$page, $limit] = $service->normalizePageParams(1, 15);
        $this->assertSame([1, 15], [$page, $limit]);
        [$page, $limit] = $service->normalizePageParams(0, 0);
        $this->assertSame([1, 1], [$page, $limit]);
        [$page, $limit] = $service->normalizePageParams(10, 500);
        $this->assertSame([10, 100], [$page, $limit]);
    }

    /**
     * 继承的状态流转校验助手
     */
    public function testCanTransitionHelper(): void
    {
        $service = new ProductService();
        $flow = [0 => [1, 2], 1 => [0]];
        $this->assertTrue($service->canTransition(0, 1, $flow));
        $this->assertTrue($service->canTransition(0, 2, $flow));
        $this->assertFalse($service->canTransition(0, 3, $flow));
        $this->assertFalse($service->canTransition(2, 0, $flow));
    }

    /**
     * 容器 class_exists 回退解析：config/dependence.php 为 dead config（无 addDefinitions），
     * Container::get(XxxService::class) 依赖类存在即 new 的兜底路径，服务必须可经容器解析。
     */
    public function testContainerResolvesProductService(): void
    {
        $this->assertTrue(class_exists(ProductService::class));
        $service = Container::get(ProductService::class);
        $this->assertInstanceOf(ProductService::class, $service);
    }
}
