<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\hr\HrService;
use PHPUnit\Framework\TestCase;

/**
 * HrService 纯逻辑单元测试（P2-F2 服务层轻量提取）
 * 覆盖不依赖数据库的逻辑：迟到/早退判定、请假日期展开、实发金额计算、请假审批状态机。
 */
class HrServiceTest extends TestCase
{
    /**
     * Service 类存在且可实例化
     */
    public function testHrServiceIsInstantiable(): void
    {
        $class = 'app\\service\\hr\\HrService';
        $this->assertTrue(class_exists($class));
        $service = new $class();
        $this->assertInstanceOf(HrService::class, $service);
    }

    /**
     * 上班打卡判定：准点/宽限内为正常(1)，超出宽限为迟到(2)，迟到分钟数精确
     */
    public function testComputeClockInStatus(): void
    {
        $service = new HrService();

        // 准点打卡：09:00:00 未超过规则时间 09:00:00
        $result = $service->computeClockInStatus('09:00:00', '09:00:00', 10.0);
        $this->assertSame(1, $result['status']);
        $this->assertSame(0, $result['late_minutes']);

        // 宽限内迟到：迟到 5 分钟 < 宽限 10 分钟 → 正常
        $result = $service->computeClockInStatus('09:05:00', '09:00:00', 10.0);
        $this->assertSame(1, $result['status']);
        $this->assertSame(5, $result['late_minutes']);

        // 超出宽限：迟到 15 分钟 > 宽限 10 分钟 → 迟到(2)
        $result = $service->computeClockInStatus('09:15:00', '09:00:00', 10.0);
        $this->assertSame(2, $result['status']);
        $this->assertSame(15, $result['late_minutes']);

        // 宽限为 0：任何迟到都判定为迟到
        $result = $service->computeClockInStatus('09:00:30', '09:00:00', 0.0);
        $this->assertSame(2, $result['status']);
    }

    /**
     * 下班打卡判定：早退超出宽限且原状态正常(1) → 早退(3)；宽限内/非正常状态保持不变
     */
    public function testComputeClockOutStatus(): void
    {
        $service = new HrService();

        // 早退 20 分钟 > 宽限 10 分钟，原状态正常 → 早退(3)
        $result = $service->computeClockOutStatus('17:40:00', '18:00:00', 10.0, 1);
        $this->assertSame(3, $result['status']);
        $this->assertSame(20, $result['early_minutes']);

        // 宽限内早退 5 分钟 → 状态保持正常(1)
        $result = $service->computeClockOutStatus('17:55:00', '18:00:00', 10.0, 1);
        $this->assertSame(1, $result['status']);

        // 原状态已是迟到(2)，早退不覆盖迟到状态
        $result = $service->computeClockOutStatus('17:30:00', '18:00:00', 10.0, 2);
        $this->assertSame(2, $result['status']);

        // 准点下班：不早退
        $result = $service->computeClockOutStatus('18:00:00', '18:00:00', 10.0, 1);
        $this->assertSame(0, $result['early_minutes']);
    }

    /**
     * 请假日期展开：含首尾日、跨月正确、非法区间返回空数组
     */
    public function testLeaveDays(): void
    {
        $service = new HrService();
        $this->assertSame(['2026-08-01', '2026-08-02', '2026-08-03'], $service->leaveDays('2026-08-01', '2026-08-03'));
        // 单日请假
        $this->assertSame(['2026-08-15'], $service->leaveDays('2026-08-15', '2026-08-15'));
        // 跨月
        $this->assertSame(['2026-08-31', '2026-09-01'], $service->leaveDays('2026-08-31', '2026-09-01'));
        // 非法区间（结束早于开始）
        $this->assertSame([], $service->leaveDays('2026-08-05', '2026-08-01'));
        // 非法日期
        $this->assertSame([], $service->leaveDays('not-a-date', '2026-08-01'));
    }

    /**
     * 实发金额计算：实发 = 基本 + 绩效 + 加班 - 扣款 - 个税，缺省字段按 0 计
     */
    public function testSalaryNetSalary(): void
    {
        $service = new HrService();
        $this->assertSame(8000.0, $service->salaryNetSalary([
            'base_salary' => 8000,
        ]));
        $this->assertSame(9900.0, $service->salaryNetSalary([
            'base_salary' => 8000,
            'performance' => 2000,
            'overtime' => 500,
            'deduction' => 300,
            'tax' => 300,
        ]));
        $this->assertSame(0.0, $service->salaryNetSalary([]));
        // 空值字段按 0 处理（与旧控制器 ?? 0 语义一致）
        $this->assertSame(8000.0, $service->salaryNetSalary(['base_salary' => 8000, 'deduction' => null]));
    }

    /**
     * 请假审批状态机：仅待审批(0)可审批；已批准/已驳回为终态
     */
    public function testLeaveApprovalStatusFlow(): void
    {
        $service = new HrService();
        $this->assertSame([0 => [1, 2], 1 => [], 2 => []], $service->leaveStatusFlow());
        $this->assertTrue($service->canApproveLeave(0));
        $this->assertFalse($service->canApproveLeave(1), '已批准不可再审批');
        $this->assertFalse($service->canApproveLeave(2), '已驳回不可再审批');
    }

    /**
     * 继承的分页参数归一化（跨服务复用验证）
     */
    public function testNormalizePageParams(): void
    {
        $service = new HrService();
        [$page, $limit] = $service->normalizePageParams(0, 0);
        $this->assertSame([1, 1], [$page, $limit]);
        [$page, $limit] = $service->normalizePageParams(-5, 1000);
        $this->assertSame([1, 100], [$page, $limit]);
    }
}
