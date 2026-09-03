<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * P1-M3 产能负荷 独立对抗验证（tester，对照 M3CapacityTest 补盲）。
 *
 * 覆盖（schema 实际语义为准，见 database/install.sql）：
 *  - 多工序多工作站展开：同产品多条工艺路线各落点工作站，负荷按站拆分；
 *    禁用站缺省排除、指定 ID 时计入（详情查看语义）；
 *  - 负荷率边界：100.00% / 150.00% / 250.00% 精确字符串（无 float 泄漏）；
 *  - 报表期间过滤与跨日聚合：重叠工单按日累计，区间外日期不落账；
 *  - 空结果与无日历记录：周末零产能零负荷区间返回 [] 不崩；工作日零负荷行
 *    产能 8.00/负荷 0.00/负荷率 0.00；
 *  - 工单状态口径锁定：schema 无「未下达」前态（0=待生产 1=生产中 2=已完成
 *    3=已取消），0/1 均计入、部分完工按剩余量、2 排除 —— 合计恰好 100.00%；
 *  - 异常数据防护：计划窗口 NULL/起>止/超 366 天的工单静默跳过不崩；
 *  - 例外工时边界：24.00 合法、24.01 拒绝、9.999 四舍五入为 10.00。
 * 金额/工时断言一律字符串（bc 域，对照 app/functions.php 约定）。
 * 日期基准 = 本周一~周日，与真实星期几无关。
 */
#[Group('integration')]
class M3CapacityIntegrationTest extends M3CapacityScaffold
{
    #[TestDox('负荷展开：同产品双路由双站按站拆分；禁用站缺省排除、指定计入')]
    public function testMultiRoutingMultiWorkstationSplit(): void
    {
        $wsA = $this->createWorkstation();
        $wsB = $this->createWorkstation();
        $wsC = $this->createWorkstation(0);   // 禁用站
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $wsA, '1.00');
        $this->createRouting($product, $wsB, '2.00');
        $this->createRouting($product, $wsC, '3.00');
        $this->createOpenOrder($product, '4.00', $mon, $this->addDays($mon, 1));   // 4件×各站单件工时

        $rows = $this->reportIndex(null, $mon, $this->addDays($mon, 1));
        // 缺省范围：仅启用站出行，每站按各自单件工时×数量÷2天
        self::assertSame(4, count($rows), 'A/B 两站 × 2 天，C 禁用不出行');
        $a = $rows[$this->dateKey($wsA, $mon)];
        $b = $rows[$this->dateKey($wsB, $mon)];
        self::assertSame('2.00', $a['load_hours'], 'A 站 4×1h÷2 天');
        self::assertSame('25.00', $a['load_rate'], 'A 站 2/8');
        self::assertSame('4.00', $b['load_hours'], 'B 站 4×2h÷2 天');
        self::assertSame('50.00', $b['load_rate'], 'B 站 4/8');
        self::assertArrayNotHasKey($this->dateKey($wsC, $mon), $rows, '禁用站缺省不占行');

        // 指定禁用站（详情查看）：其 4×3h÷2 天 独立展开
        $cRows = $this->reportIndex($wsC, $mon, $this->addDays($mon, 1));
        self::assertSame(2, count($cRows), 'C 站 2 天各 6.00');
        self::assertSame('6.00', $cRows[$this->dateKey($wsC, $mon)]['load_hours']);
        self::assertSame('75.00', $cRows[$this->dateKey($wsC, $mon)]['load_rate']);
        self::assertSame('6.00', $cRows[$this->dateKey($wsC, $this->addDays($mon, 1))]['load_hours']);
    }

    #[TestDox('负荷率边界：150.00% 与 250.00%（超100% 精确字符串，无 float 泄漏）')]
    public function testRateOverOneHundredBoundary(): void
    {
        $mon = $this->monday();
        $svc = $this->capacityService();

        $ws1 = $this->createWorkstation();
        $p1 = $this->nextId();
        $this->createRouting($p1, $ws1, '1.00');
        $this->createOpenOrder($p1, '12.00', $mon, $mon);   // 12h / 8h 产能

        $row = $this->reportIndex($ws1, $mon, $mon)[$this->dateKey($ws1, $mon)];
        self::assertSame('12.00', $row['load_hours']);
        self::assertSame('150.00', $row['load_rate'], '12/8=150% 必须字符串两位小数');
        self::assertIsString($row['load_rate']);

        $ws2 = $this->createWorkstation();
        $p2 = $this->nextId();
        $this->createRouting($p2, $ws2, '1.00');
        $this->createOpenOrder($p2, '8.00', $mon, $mon);
        $this->createOpenOrder($p2, '12.00', $mon, $mon);   // 同日两单 → 20h

        $row2 = $this->reportIndex($ws2, $mon, $mon)[$this->dateKey($ws2, $mon)];
        self::assertSame('20.00', $row2['load_hours'], '8+12 跨单同日累计');
        self::assertSame('250.00', $row2['load_rate'], '20/8=250%');
        self::assertSame('8.00', $row2['available_hours']);
    }

    #[TestDox('报表：重叠工单跨日聚合 scale4 后统一舍入；区间外日期不落账')]
    public function testOverlapAggregationAndPeriodFilter(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '8.00', $mon, $this->addDays($mon, 2));    // 8h÷3 天 = 2.6667/日
        $this->createOpenOrder($product, '4.00', $mon, $this->addDays($mon, 1));    // 4h÷2 天 = 2.0000/日

        $rows = $this->reportIndex($ws, $mon, $this->addDays($mon, 1));   // 只查周一~周二
        self::assertSame(2, count($rows), '仅区间内日期占行');
        foreach ([0, 1] as $i) {
            $row = $rows[$this->dateKey($ws, $this->addDays($mon, $i))];
            self::assertSame('4.67', $row['load_hours'], 'D+' . $i . ' 2.6667+2.0000 累计');
            self::assertSame('58.33', $row['load_rate'], 'D+' . $i . ' 4.6667/8');
        }
        // 周三在窗口内但区间外 → 不落账（期间过滤）；周末零产能零负荷不出行
        self::assertArrayNotHasKey($this->dateKey($ws, $this->addDays($mon, 2)), $rows, '区间外周三无行');
        self::assertArrayNotHasKey($this->dateKey($ws, $this->addDays($mon, 5)), $rows, '周末无行');
    }

    #[TestDox('报表：空结果不崩（周末零产能零负荷区间）；无日历记录走默认规则')]
    public function testEmptyResultAndNoCalendarRecord(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $sat = $this->addDays($mon, 5);
        $svc = $this->capacityService();

        self::assertSame(0, Capsule::table('erp_mfg_capacity_calendar')->where('workstation_id', $ws)->count(), '前置：无日历记录');
        self::assertSame([], $svc->report(null, $sat, $this->addDays($sat, 1)), '无工单周末区间 → 空结果');
        self::assertSame([], $svc->report($ws, $sat, $this->addDays($sat, 1)), '指定站同样空');

        // 无日历记录：默认规则周一 8.00 出行，零负荷行负荷率 0.00（非 null）
        $rows = $this->reportIndex(null, $mon, $mon);
        $row = $rows[$this->dateKey($ws, $mon)] ?? null;
        self::assertNotNull($row, '默认规则下行存在');
        self::assertSame('8.00', $row['available_hours']);
        self::assertSame('0.00', $row['load_hours']);
        self::assertSame('0.00', $row['load_rate'], '产能>0 时零负荷率 0.00 非 null');
    }

    #[TestDox('状态口径锁定：0/1 均计（schema 无未下达前态）、部分完工按剩余、已完成排除')]
    public function testOrderStateSemanticsLocked(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '2.00', $mon, $mon, orderStatus: 0);                         // 待生产计
        $this->createOpenOrder($product, '3.00', $mon, $mon, orderStatus: 1);                         // 生产中计
        $this->createOpenOrder($product, '5.00', $mon, $mon, itemStatus: 1, completedQty: '2.00');    // 剩 3
        $this->createOpenOrder($product, '99.00', $mon, $mon, orderStatus: 2);                        // 已完成不计

        $row = $this->reportIndex($ws, $mon, $mon)[$this->dateKey($ws, $mon)];
        self::assertSame('8.00', $row['load_hours'], '2+3+3 完成单排除');
        self::assertSame('100.00', $row['load_rate'], '恰好 100% 边界字符串');
    }

    #[TestDox('数据异常防护：计划窗口 NULL/起>止/超366天 工单静默跳过，不崩不污染')]
    public function testDataAnomalyGuard(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->insertAnomalyOrder($product, null, null);                       // (a) 无计划窗口
        $this->insertAnomalyOrder($product, '2020-01-01', '2020-01-02');       // (b) 合法窗口但区间外
        $this->insertAnomalyOrder($product, '2026-09-20', '2026-09-19');       // (c) 起>止
        $this->insertAnomalyOrder($product, '2020-01-01', '2026-01-01');       // (d) 跨度超 366 天
        $this->createOpenOrder($product, '2.00', $mon, $mon);                  // 正常单对照

        $rows = $this->reportIndex($ws, $mon, $mon);
        self::assertSame(1, count($rows), '异常单全部静默跳过');
        $row = $rows[$this->dateKey($ws, $mon)];
        self::assertSame('2.00', $row['load_hours'], '仅正常单计入');
        self::assertSame('25.00', $row['load_rate']);
    }

    #[TestDox('例外工时边界：24.00 合法/24.01 拒绝/9.999 舍入 10.00/0.00 闭厂')]
    public function testExceptionHoursBoundary(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $svc = $this->capacityService();

        $svc->setException($ws, $mon, '24.00');
        self::assertSame('24.00', $svc->calendar($ws, $mon, $mon)[0]['available_hours'], '24.00 上限合法');

        $svc->setException($ws, $mon, '9.999');   // half-up 入 10.00
        self::assertSame('10.00', $svc->calendar($ws, $mon, $mon)[0]['available_hours'], '9.999→10.00');
        $this->assertThrowsMessage(fn () => $svc->setException($ws, $mon, '24.01'), '可用工时须在 0~24 之间');

        $svc->setException($ws, $mon, '0.00', '闭厂检修');   // 闭厂日
        $row = $svc->calendar($ws, $mon, $mon)[0];
        self::assertSame('0.00', $row['available_hours']);
        self::assertSame('exception', $row['source']);
        $svc->removeException($ws, $mon);
        self::assertSame('8.00', $svc->calendar($ws, $mon, $mon)[0]['available_hours'], '删除后回默认周一 8.00');
    }

    // ---------- 私有辅助 ----------

    /** 直接插入「计划窗口异常」工单（主档 NOT NULL 列照 schema 全量赋值，code 雪花唯一） */
    private function insertAnomalyOrder(int $productId, ?string $start, ?string $end): void
    {
        $orderId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_production_order')->insert([
            'id' => $orderId,
            'code' => 'POX-' . $orderId,
            'bom_id' => 0,
            'warehouse_id' => 0,
            'planned_quantity' => '5.00',
            'completed_quantity' => '0.00',
            'status' => 0,
            'planned_start' => $start,
            'planned_end' => $end,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        Capsule::table('erp_mfg_production_item')->insert([
            'id' => $this->nextId(),
            'order_id' => $orderId,
            'product_id' => $productId,
            'planned_quantity' => '5.00',
            'completed_quantity' => '0.00',
            'status' => 0,
            'created_at' => $now,
        ]);
        $this->orderIds[] = $orderId;
    }

    /** 执行报表并按 "wsId:date" 索引返回（键即断言锚点） */
    private function reportIndex(?int $ws, string $from, string $to): array
    {
        $map = [];
        foreach ($this->capacityService()->report($ws, $from, $to) as $row) {
            $map[$this->dateKey($row['workstation_id'], $row['date'])] = $row;
        }

        return $map;
    }

    private function dateKey(int $ws, string $date): string
    {
        return $ws . ':' . $date;
    }
}
