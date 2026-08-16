<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\crm\CrmService;
use PHPUnit\Framework\TestCase;

/**
 * CrmService 纯逻辑单元测试（P2-F2 服务层轻量提取）
 * 仅覆盖不依赖数据库的逻辑：状态流转校验、模拟报表数据构建、分页参数归一化。
 */
class CrmServiceTest extends TestCase
{
    /**
     * Service 类存在且可实例化（无参构造，容器 class_exists 回退依赖此前提）
     */
    public function testCrmServiceIsInstantiable(): void
    {
        $class = 'app\\service\\crm\\CrmService';
        $this->assertTrue(class_exists($class));
        $service = new $class();
        $this->assertInstanceOf(CrmService::class, $service);
    }

    /**
     * 合同状态流转图结构：0草稿→1待审批；1待审批→2已审批/0退回；
     * 2已审批→3执行中；3执行中→4已完成/5已终止；4/5 为终态
     */
    public function testContractStatusFlowStructure(): void
    {
        $service = new CrmService();
        $this->assertSame(CrmService::CONTRACT_STATUS_FLOW, $service->contractStatusFlow());
        $this->assertSame([1], CrmService::CONTRACT_STATUS_FLOW[0]);
        $this->assertSame([2, 0], CrmService::CONTRACT_STATUS_FLOW[1]);
        $this->assertSame([3], CrmService::CONTRACT_STATUS_FLOW[2]);
        $this->assertSame([4, 5], CrmService::CONTRACT_STATUS_FLOW[3]);
        $this->assertSame([], CrmService::CONTRACT_STATUS_FLOW[4]);
        $this->assertSame([], CrmService::CONTRACT_STATUS_FLOW[5]);
    }

    /**
     * 合同状态流转校验：合法流转放行、非法流转拒绝
     */
    public function testContractTransitionValidation(): void
    {
        $service = new CrmService();
        // 合法流转
        $this->assertTrue($service->canTransitionContract(0, 1));
        $this->assertTrue($service->canTransitionContract(1, 2));
        $this->assertTrue($service->canTransitionContract(1, 0));
        $this->assertTrue($service->canTransitionContract(3, 5));
        // 非法流转
        $this->assertFalse($service->canTransitionContract(0, 3), '草稿不能直接到执行中');
        $this->assertFalse($service->canTransitionContract(4, 0), '已完成是终态');
        $this->assertFalse($service->canTransitionContract(2, 0), '已审批不能退回草稿');
        $this->assertFalse($service->canTransitionContract(0, -1), '未知目标状态拒绝');
    }

    /**
     * 分析报表模拟数据：customer/order/revenue/activity/retention 各类型键结构完整
     */
    public function testBuildReportDataStructurePerType(): void
    {
        $service = new CrmService();

        $customer = $service->buildReportData('customer', 2026, 3, 1);
        foreach (['new_customers', 'active_customers', 'churn_customers', 'retention_rate', 'period'] as $key) {
            $this->assertArrayHasKey($key, $customer, "customer 报表缺少键 {$key}");
        }
        $this->assertGreaterThanOrEqual(0, $customer['retention_rate']);
        $this->assertLessThanOrEqual(1, $customer['retention_rate']);

        $order = $service->buildReportData('order', 2026, 3, 1);
        foreach (['total_orders', 'total_amount', 'avg_order_value', 'period'] as $key) {
            $this->assertArrayHasKey($key, $order, "order 报表缺少键 {$key}");
        }

        $revenue = $service->buildReportData('revenue', 2026, 3, 1);
        $this->assertSame(0, $revenue['gross_profit']);
        $this->assertSame(0, $revenue['gross_margin']);

        $activity = $service->buildReportData('activity', 2026, 3, 1);
        $this->assertArrayHasKey('conversion_rate', $activity);

        $retention = $service->buildReportData('retention', 2026, 3, 1);
        foreach (['cohort_size', 'month1_retention', 'month3_retention', 'month6_retention'] as $key) {
            $this->assertArrayHasKey($key, $retention, "retention 报表缺少键 {$key}");
        }
    }

    /**
     * 分析报表期间标签：月/季/年三种期间类型文案正确
     */
    public function testBuildReportDataPeriodLabel(): void
    {
        $service = new CrmService();
        $this->assertSame('2026年3月', $service->buildReportData('customer', 2026, 3, 1)['period']);
        $this->assertSame('2026年Q2', $service->buildReportData('customer', 2026, 2, 2)['period']);
        $this->assertSame('2026年度', $service->buildReportData('customer', 2026, 1, 3)['period']);
        $this->assertSame('2026年1月', $service->buildReportData('unknown', 2026, 1, 1)['period']);
    }

    /**
     * 继承的分页参数归一化：页码最小 1，每页条数限制 [1,100]
     */
    public function testNormalizePageParams(): void
    {
        $service = new CrmService();
        [$page, $limit] = $service->normalizePageParams(0, 15);
        $this->assertSame(1, $page);
        [$page, $limit] = $service->normalizePageParams(3, 0);
        $this->assertSame(1, $limit);
        [$page, $limit] = $service->normalizePageParams(2, 500);
        $this->assertSame(100, $limit);
        [$page, $limit] = $service->normalizePageParams(2, 15);
        $this->assertSame([2, 15], [$page, $limit]);
    }

    /**
     * 通用状态流转校验助手：非法流转拒绝、未知状态拒绝
     */
    public function testCanTransitionHelper(): void
    {
        $service = new CrmService();
        $flow = [0 => [1], 1 => [2], 2 => []];
        $this->assertTrue($service->canTransition(0, 1, $flow));
        $this->assertFalse($service->canTransition(0, 2, $flow));
        $this->assertFalse($service->canTransition(2, 1, $flow));
        $this->assertFalse($service->canTransition(9, 1, $flow));
    }
}
