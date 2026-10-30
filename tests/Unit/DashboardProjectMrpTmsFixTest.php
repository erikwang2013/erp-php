<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\controller\DashboardController;
use app\controller\project\ProjectController;
use app\service\manufacturing\ManufacturingService;
use app\service\tms\TmsShipmentService;
use app\service\tms\TrackingService;
use PHPUnit\Framework\TestCase;

/**
 * 性能与状态流转修复回归（纯单测，不连 DB/Redis）
 *  - 仪表盘：日期半开区间、账龄 SQL 分桶格式化、Redis 故障 fail-open
 *  - 项目列表：进度批量聚合（无 N+1）
 *  - MRP：期间销售需求 × BOM 用量
 *  - TMS：显式状态流转（异常可恢复）+ 回调事务
 */
class DashboardProjectMrpTmsFixTest extends TestCase
{
    private function methodSource(string $class, string $method): string
    {
        $ref = new \ReflectionMethod($class, $method);
        $lines = file((string) $ref->getFileName()) ?: [];

        return implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    private function classSource(string $class): string
    {
        return (string) file_get_contents((string) (new \ReflectionClass($class))->getFileName());
    }

    /* ============================ 仪表盘 ============================ */

    public function testDayBoundsIsHalfOpenInterval(): void
    {
        $this->assertSame(
            ['2026-10-30 00:00:00', '2026-10-31 00:00:00'],
            DashboardController::dayBounds('2026-10-30')
        );
        // 跨月/跨年边界
        $this->assertSame(
            ['2026-12-31 00:00:00', '2027-01-01 00:00:00'],
            DashboardController::dayBounds('2026-12-31')
        );
    }

    public function testFormatAgingKeepsBucketOrderAndChineseNames(): void
    {
        $result = DashboardController::formatAging([
            '逾期31-60天' => '12.345',
            '未到期' => '100',
            '逾期90+天' => '7',
        ]);

        $this->assertSame(
            ['未到期', '逾期1-30天', '逾期31-60天', '逾期61-90天', '逾期90+天'],
            array_column($result, 'name')
        );
        $this->assertSame(100.0, $result[0]['value']);
        $this->assertSame(0.0, $result[1]['value'], '缺失桶补 0');
        $this->assertSame(12.35, $result[2]['value'], 'BC half-up 保留 2 位');
        $this->assertSame(7.0, $result[4]['value']);
    }

    public function testFormatAgingHalfUpBoundary(): void
    {
        $result = DashboardController::formatAging(['未到期' => '0.005', '逾期1-30天' => '0.004']);
        $this->assertSame(0.01, $result[0]['value'], '0.005 half-up → 0.01');
        $this->assertSame(0.0, $result[1]['value'], '0.004 舍去 → 0');
    }

    public function testDashboardAgingUsesSingleSqlAggregation(): void
    {
        $src = $this->methodSource(DashboardController::class, 'getAging');
        $this->assertStringContainsString('SUM(amount - settled_amount)', $src);
        $this->assertStringContainsString("groupBy('bucket')", $src);
        $this->assertStringNotContainsString('->get()', $src, '账龄不得把全表记录加载进 PHP');
        // 基准日由 PHP 时区取并参数绑定，不用 MySQL CURDATE()（两边时区可能不一致）
        $this->assertStringNotContainsString('CURDATE()', $src);
        $this->assertStringContainsString('DATEDIFF(?, due_date)', $src);
        $this->assertStringContainsString('[$today, $today, $today, $today]', $src);
    }

    public function testDashboardHasNoWhereDateCalls(): void
    {
        $src = $this->classSource(DashboardController::class);
        $this->assertStringNotContainsString('->whereDate(', $src, 'whereDate 会使日期列索引失效');
        $this->assertStringContainsString("where('ordered_at', '>=', \$todayStart)", $src);
        $this->assertStringContainsString("where('received_at', '>=', \$monthStart)", $src);
        $this->assertStringContainsString("where('paid_at', '>=', \$monthStart)", $src);
        $this->assertStringContainsString("where('actual_delivery_at', '>=', \$todayStart)", $src);
    }

    public function testDashboardRedisCallsAreWrappedInFailOpenHelpers(): void
    {
        $src = $this->classSource(DashboardController::class);
        $this->assertSame(1, substr_count($src, 'Redis::get('), 'Redis 读只应出现在 cacheGet 中');
        $this->assertSame(1, substr_count($src, 'Redis::setex('), 'Redis 写只应出现在 cacheSet 中');
        $this->assertSame(2, substr_count($src, 'catch (\\Throwable $e)'), 'cacheGet/cacheSet 均需 fail-open');
        $this->assertSame(4, substr_count($src, 'cacheSet($cacheKey, 300'), '缓存 TTL 保持 300 秒');
    }

    /* ============================ 项目进度 ============================ */

    public function testProgressFromAvg(): void
    {
        $this->assertSame(0, ProjectController::progressFromAvg(null), '无任务项目进度为 0');
        $this->assertSame(35, ProjectController::progressFromAvg('34.5000'), 'half-up 取整');
        $this->assertSame(34, ProjectController::progressFromAvg('34.4999'));
        $this->assertSame(100, ProjectController::progressFromAvg('100.0000'));
    }

    public function testProjectIndexAggregatesProgressInOneQuery(): void
    {
        $src = $this->methodSource(ProjectController::class, 'index');
        $this->assertStringContainsString('AVG(progress)', $src);
        $this->assertStringContainsString("groupBy('project_id')", $src);
        $this->assertStringNotContainsString('calcProgress', $src, 'index 不得逐行查任务');
    }

    /* ============================ MRP 需求 ============================ */

    public function testComputeGrossRequirement(): void
    {
        $service = new ManufacturingService();
        $this->assertSame('1500.00', $service->computeGrossRequirement('1000', '1.5'));
        $this->assertSame('333.00', $service->computeGrossRequirement(1000, 0.333));
        $this->assertSame('3.02', $service->computeGrossRequirement('3', '1.005'), 'BC half-up 保留 2 位');
        $this->assertSame('0.00', $service->computeGrossRequirement('0', '2'));
    }

    public function testMrpDemandComesFromPeriodSalesOrders(): void
    {
        $demandSrc = $this->methodSource(ManufacturingService::class, 'getSalesDemand');
        $this->assertStringContainsString("whereIn('sales_order.status', [1, 2, 3])", $demandSrc);
        $this->assertStringContainsString("whereNull('sales_order.deleted_at')", $demandSrc);
        $this->assertStringContainsString("where('sales_order.ordered_at', '<', \$end)", $demandSrc);

        $generateSrc = $this->methodSource(ManufacturingService::class, 'generateMrpItems');
        $this->assertStringContainsString('getSalesDemand', $generateSrc);
        $this->assertStringContainsString('computeGrossRequirement($demandQty', $generateSrc);
    }

    /* ============================ TMS 状态流转 ============================ */

    public function testTmsStatusFlowAllowsForwardJumpsRecoveryAndReturn(): void
    {
        $service = new TmsShipmentService();
        $this->assertSame([
            0 => [1, 2, 3, 4, 5],
            1 => [2, 3, 4, 5],
            2 => [3, 4, 5],
            3 => [3, 4, 5],
            4 => [1, 2, 3, 4, 5],
            5 => [5],
        ], $service->statusFlow());

        $allowed = [[0, 1], [0, 2], [0, 4], [1, 2], [1, 3], [1, 4], [2, 3], [2, 4],
            [4, 1], [4, 2], [4, 3], [4, 4],
            // 同状态重复上报幂等放行（承运商重发事件）
            [0, 0], [1, 1], [2, 2], [3, 3], [4, 4], [5, 5],
            // 旧前向跳转：首个回调直接送达/退回、送达后异常
            [0, 3], [0, 5], [3, 4],
            // 1/2/3/4 可退回；5 为终态
            [1, 5], [2, 5], [3, 5], [4, 5]];
        foreach ($allowed as [$from, $to]) {
            $this->assertTrue($service->canTransitionStatus($from, $to), "{$from} → {$to} 应允许");
        }

        $rejected = [[1, 0], [2, 0], [2, 1], [3, 0], [3, 1], [3, 2], [4, 0],
            [5, 0], [5, 1], [5, 2], [5, 3], [5, 4], [6, 3], [0, 6]];
        foreach ($rejected as [$from, $to]) {
            $this->assertFalse($service->canTransitionStatus($from, $to), "{$from} → {$to} 应拒绝");
        }
    }

    public function testTmsUpdateStatusUsesFlowAndWebhookIsTransactional(): void
    {
        $this->assertStringContainsString(
            'canTransitionStatus',
            $this->methodSource(TmsShipmentService::class, 'updateStatus')
        );
        $this->assertStringContainsString(
            'DB::connection()->transaction',
            $this->methodSource(TrackingService::class, 'processWebhook'),
            '回调需整批事务，避免部分写入'
        );
    }
}
