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
 * P1-M3 产能负荷集成测试：工作站日历例外 + 粗能力负荷报表。
 *
 * 覆盖口径（与 MfgCapacityService 类注释一致）：
 *  - 日历默认规则 周一~五 8.00 / 周末 0.00，仅存例外；
 *  - 负荷 = 未结工单明细剩余数量 × 工艺路线单件标准工时（落点工作站），
 *    沿计划窗口产能>0 的日均摊，全闭厂窗口退回全窗口均摊；
 *  - 排除：订单 status 2/3、软删订单、明细 status 2、剩余数量<=0；
 *  - 产能=0 且负荷>0 的日输出行负荷率 null（除零保护）。
 * 日期基准 = 本周一~周日，与真实星期几无关。
 */
#[Group('integration')]
class M3CapacityTest extends M3CapacityScaffold
{
    #[TestDox('日历：默认规则 周一~五 8.00/周末 0.00，全 source=default')]
    public function testCalendarDefaultRule(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();

        $rows = $this->capacityService()->calendar($ws, $mon, $this->addDays($mon, 6));
        self::assertCount(7, $rows);
        foreach ($rows as $i => $row) {
            $expectedHours = $i < 5 ? '8.00' : '0.00';   // 周一~五 / 周六日
            self::assertSame($expectedHours, $row['available_hours'], $row['date'] . ' 可用工时');
            self::assertSame('default', $row['source'], $row['date'] . ' 来源');
        }
    }

    #[TestDox('例外：setException 生效/覆盖 upsert/remove 恢复默认，删除幂等')]
    public function testSetAndRemoveException(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $sat = $this->addDays($mon, 5);
        $svc = $this->capacityService();

        $svc->setException($ws, $sat, '4.00', '检修停机');
        $rows = $svc->calendar($ws, $sat, $sat);
        self::assertSame('exception', $rows[0]['source']);
        self::assertSame('4.00', $rows[0]['available_hours']);

        $svc->setException($ws, $sat, '6.00');   // 同日 upsert，不新增行
        self::assertSame(1, Capsule::table('erp_mfg_capacity_calendar')
            ->where('workstation_id', $ws)->where('work_date', $sat)->count());

        $svc->removeException($ws, $sat);
        self::assertSame(0, Capsule::table('erp_mfg_capacity_calendar')
            ->where('workstation_id', $ws)->count());
        self::assertSame('default', $svc->calendar($ws, $sat, $sat)[0]['source']);
        $svc->removeException($ws, $sat);   // 幂等：无记录不报错
        $this->assertTrue(true);
    }

    #[TestDox('日历入参校验：日期格式/存在性、工时数字与 0~24 边界、工作站存在性、区间上限')]
    public function testCalendarValidation(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $svc = $this->capacityService();

        $this->assertThrowsMessage(fn () => $svc->setException($ws, '2026-9-5', '8'), '日期格式须为 YYYY-MM-DD');
        $this->assertThrowsMessage(fn () => $svc->setException($ws, '2026-99-99', '8'), '日期不存在');
        $this->assertThrowsMessage(fn () => $svc->setException($ws, $mon, 'abc'), '可用工时须为 0~24 的数字');
        $this->assertThrowsMessage(fn () => $svc->setException($ws, $mon, '-1'), '可用工时须在 0~24 之间');
        $this->assertThrowsMessage(fn () => $svc->setException($ws, $mon, '25'), '可用工时须在 0~24 之间');
        $this->assertThrowsMessage(fn () => $svc->setException($this->nextId(), $mon, '8'), '工作站不存在');
        $this->assertThrowsMessage(fn () => $svc->removeException($ws, '2026-02-30'), '日期不存在');

        $this->assertThrowsMessage(fn () => $svc->calendar($ws, '2026-09-05', '2026-09-04'), '开始日期不能晚于结束日期');
        $this->assertThrowsMessage(fn () => $svc->calendar($ws, '2026-01-01', '2027-01-02'), '查询区间最多 366 天');
        $this->assertThrowsMessage(fn () => $svc->report(null, $mon, $this->addDays($mon, 366)), '查询区间最多 366 天');
    }

    #[TestDox('报表：整窗口工作日均摊 6×1h/3 天 → 2.00h/日 25.00%，周末不出行')]
    public function testReportEvenSpread(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '6.00', $mon, $this->addDays($mon, 2));

        $rows = $this->reportIndex($ws, $mon, $this->addDays($mon, 6));
        self::assertCount(5, $rows);   // 周一~五（周末无产能无负荷不出行）
        foreach ([0, 1, 2] as $i) {
            $row = $rows[$this->dateKey($ws, $this->addDays($mon, $i))];
            $this->assertBcEquals('2.00', $row['load_hours'], 'D+' . $i . ' 负荷');
            $this->assertBcEquals('25.00', $row['load_rate'], 'D+' . $i . ' 负荷率');
            $this->assertBcEquals('8.00', $row['available_hours'], 'D+' . $i . ' 产能');
        }
        $this->assertBcEquals('0.00', $rows[$this->dateKey($ws, $this->addDays($mon, 3))]['load_hours'], '周四零负荷行');
    }

    #[TestDox('报表：闭厂日(0.00)移除产能摊薄其余日；周末例外日(4.00)照常出行')]
    public function testReportClosedDayAndWeekendException(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $wed = $this->addDays($mon, 2);
        $sat = $this->addDays($mon, 5);
        $svc = $this->capacityService();
        $svc->setException($ws, $wed, '0.00', '闭厂检修');
        $svc->setException($ws, $sat, '4.00');
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '6.00', $mon, $wed);

        $rows = $this->reportIndex($ws, $mon, $this->addDays($mon, 6));
        self::assertArrayNotHasKey($this->dateKey($ws, $wed), $rows, '闭厂日且零负荷不出行');
        $this->assertBcEquals('3.00', $rows[$this->dateKey($ws, $mon)]['load_hours'], '周一 6h/2 天');
        $this->assertBcEquals('37.50', $rows[$this->dateKey($ws, $mon)]['load_rate'], '周一负荷率');
        $this->assertBcEquals('3.00', $rows[$this->dateKey($ws, $this->addDays($mon, 1))]['load_hours'], '周二');
        $this->assertBcEquals('4.00', $rows[$this->dateKey($ws, $sat)]['available_hours'], '周六例外产能');
        $this->assertBcEquals('0.00', $rows[$this->dateKey($ws, $sat)]['load_hours'], '周六零负荷');
    }

    #[TestDox('报表：窗口全闭厂退回全窗口均摊，产能=0 的日负荷率 null')]
    public function testReportZeroCapacityWindowFallback(): void
    {
        $ws = $this->createWorkstation();
        $sun = $this->addDays($this->monday(), 6);
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '5.00', $sun, $sun);   // 仅周日一天的计划窗口

        $rows = $this->reportIndex($ws, $sun, $sun);
        self::assertCount(1, $rows);
        $row = $rows[$this->dateKey($ws, $sun)];
        $this->assertBcEquals('0.00', $row['available_hours'], '周日默认产能');
        $this->assertBcEquals('5.00', $row['load_hours'], '全窗口均摊后全部负荷落周日');
        self::assertNull($row['load_rate'], '产能为 0 时负荷率为 null');
    }

    #[TestDox('报表排除项：完成/取消/软删/明细完结/明细关闭订单均不产生负荷')]
    public function testReportExclusions(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '5.00', $mon, $mon, orderStatus: 2);                       // 已完成
        $this->createOpenOrder($product, '5.00', $mon, $mon, orderStatus: 3);                       // 已取消
        $this->createOpenOrder($product, '5.00', $mon, $mon, deleted: true);                        // 软删
        $this->createOpenOrder($product, '5.00', $mon, $mon, itemStatus: 2);                        // 明细关闭
        $this->createOpenOrder($product, '5.00', $mon, $mon, completedQty: '5.00');                 // 已完成剩余 0

        $row = $this->reportIndex($ws, $mon, $mon)[$this->dateKey($ws, $mon)];
        $this->assertBcEquals('0.00', $row['load_hours'], '排除项不产生负荷');
        $this->assertBcEquals('0.00', $row['load_rate'], '零负荷行负荷率 0.00');
    }

    #[TestDox('报表纳入项：生产中(1)工单计入；部分完工按剩余数量计')]
    public function testReportInclusions(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $svc = $this->capacityService();
        $svc->setException($ws, $this->addDays($mon, 1), '0.00');   // 周二闭厂 → 剩余产能日只剩周一
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '4.00', $mon, $this->addDays($mon, 1), orderStatus: 1, itemStatus: 1);
        $this->createOpenOrder($product, '5.00', $mon, $mon, completedQty: '2.00');   // 剩余 3.00

        $rows = $this->reportIndex($ws, $mon, $mon);
        $row = $rows[$this->dateKey($ws, $mon)];
        $this->assertBcEquals('7.00', $row['load_hours'], '4h(生产中1) + 3h(剩余 5-2)');
        $this->assertBcEquals('87.50', $row['load_rate'], '7/8');
    }

    #[TestDox('报表：3 天均摊除不尽 → 1.67h/日 20.83%（scale4 累计后统一 scale2 舍入）')]
    public function testReportRoundingUnevenSpread(): void
    {
        $ws = $this->createWorkstation();
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $ws, '1.00');
        $this->createOpenOrder($product, '5.00', $mon, $this->addDays($mon, 2));   // 5h/3 天

        $rows = $this->reportIndex($ws, $mon, $this->addDays($mon, 2));
        self::assertCount(3, $rows);
        foreach ([0, 1, 2] as $i) {
            $row = $rows[$this->dateKey($ws, $this->addDays($mon, $i))];
            $this->assertBcEquals('1.67', $row['load_hours'], 'D+' . $i . ' 负荷');
            $this->assertBcEquals('20.83', $row['load_rate'], 'D+' . $i . ' 负荷率');
        }
    }

    #[TestDox('报表范围：缺省仅启用工作站；指定含禁用站；不存在的工作站报错')]
    public function testReportWorkstationScope(): void
    {
        $wsEnabled = $this->createWorkstation();
        $wsDisabled = $this->createWorkstation(0);
        $mon = $this->monday();
        $product = $this->nextId();
        $this->createRouting($product, $wsEnabled, '1.00');
        $this->createOpenOrder($product, '2.00', $mon, $mon);

        $rows = $this->reportIndex(null, $mon, $mon);
        self::assertArrayHasKey($this->dateKey($wsEnabled, $mon), $rows, '启用站计入');
        self::assertArrayNotHasKey($this->dateKey($wsDisabled, $mon), $rows, '禁用站缺省排除');

        $rows = $this->reportIndex($wsDisabled, $mon, $mon);   // 指定 ID 含禁用站（详情查看）
        self::assertCount(1, $rows, '禁用站按默认日历出行(周一 8.00)');
        $this->assertBcEquals('8.00', $rows[$this->dateKey($wsDisabled, $mon)]['available_hours']);
        $this->assertThrowsMessage(
            fn () => $this->capacityService()->report($this->nextId(), $mon, $mon),
            '工作站不存在'
        );
    }

    // ---------- 私有辅助 ----------

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
