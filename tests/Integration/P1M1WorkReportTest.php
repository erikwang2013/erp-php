<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * M1 工序报工集成测试（P1-M1a）
 *
 * 依赖真实 MySQL（TEST_DB_*）：p1_m1m2.sql 6 表 + ALTER 已应用，
 * 缺表/缺列整类跳过。覆盖审核快照（piece_rate/amount/audit_at）、
 * WIP 人工成本归集（labor_cost/流水 source_type=2）、重复审核拒绝、
 * 非生产中工单拒绝、工序无计件单价拒绝、合格超报工回滚、零合格免归集。
 */
#[Group('integration')]
class P1M1WorkReportTest extends P1M1M2CostingScaffold
{
    /**
     * 审核成功：快照计件单价/金额/审核时间，WIP 归集人工成本并写流水。
     */
    public function testAuditSnapshotsPieceRateAndAccumulatesWipLabor(): void
    {
        $fixture = $this->makeInProductionOrder();
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');

        $audited = $this->workReportService()->audit($reportId);
        $this->assertSame(1, (int) $audited->status);

        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('2.50', (string) $row->piece_rate, '计件单价快照');
        $this->assertBcEquals('25.00', (string) $row->amount, '金额=合格数量×单价');
        $this->assertNotNull($row->audit_at, '审核时间快照');

        $wip = $this->wipRow($fixture['order_id']);
        $this->assertNotNull($wip, 'WIP 应已创建');
        $this->assertBcEquals('25.00', (string) $wip->labor_cost, 'WIP 人工成本');
        $this->assertBcEquals('25.00', (string) $wip->total_cost, 'WIP 总成本');

        $flows = array_values(array_filter(
            $this->wipFlowRows($fixture['order_id']),
            fn ($f): bool => (int) $f->source_id === $reportId
        ));
        $this->assertCount(1, $flows, '应恰有一条归集流水');
        $this->assertSame(2, (int) $flows[0]->source_type, '人工成本桶');
        $this->assertSame(1, (int) $flows[0]->direction, '方向=归集');
        $this->assertBcEquals('25.00', (string) $flows[0]->amount, '流水金额');
        $this->assertSame('2026-08-15', substr((string) $flows[0]->flow_date, 0, 10), '流水日期=报工日期');
    }

    /**
     * 金额精度：合格数量带小数 × 单价 → bcmath 截断到分。
     */
    public function testAuditRoundsAmountToCents(): void
    {
        $fixture = $this->makeInProductionOrder('0.35');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10', '7.33');

        $this->workReportService()->audit($reportId);

        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('2.57', (string) $row->amount, '7.33×0.35=2.5655 → 2.57');
        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('2.57', (string) $wip->labor_cost, 'WIP 与单据同额');
    }

    /**
     * 重复审核拒绝：非草稿状态不可再次审核，WIP 不重复归集。
     */
    public function testAuditTwiceRejected(): void
    {
        $fixture = $this->makeInProductionOrder();
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');
        $this->workReportService()->audit($reportId);

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '只有草稿状态的报工单可以审核'
        );
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 1, '流水不得重复');
    }

    /**
     * 非生产中工单（status=0 未开工）审核拒绝。
     */
    public function testAuditRejectsOrderNotInProduction(): void
    {
        $fixture = $this->makeInProductionOrder();
        // 第二张草稿工单不开工
        $draftOrderId = $this->createOrder($fixture['bom_id'], '3');
        $reportId = $this->createWorkReport($draftOrderId, $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '只有生产中的工单可以报工'
        );
        $this->assertSame(0, (int) $this->workReportRow($reportId)->status, '单据保持草稿');
    }

    /**
     * 工序未配置计件单价（piece_rate=0）审核拒绝。
     */
    public function testAuditRejectsRoutingWithoutPieceRate(): void
    {
        $fixture = $this->makeInProductionOrder('0.00');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '工序未配置计件单价'
        );
        $this->assertSame(0, (int) $this->workReportRow($reportId)->status, '单据保持草稿');
    }

    /**
     * 合格数量大于报工数量：整体回滚，单据保持草稿且不产生 WIP。
     */
    public function testAuditRollsBackWhenQualifiedExceedsQuantity(): void
    {
        $fixture = $this->makeInProductionOrder();
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '5', '8');

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '合格数量不能大于报工数量'
        );
        $this->assertSame(0, (int) $this->workReportRow($reportId)->status, '事务回滚后单据保持草稿');
        $this->assertNull($this->wipRow($fixture['order_id']), '不得产生 WIP 行');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 0, '不得产生流水');
    }

    /**
     * 零合格：审核通过但金额为 0，且不产生 WIP 归集（成本服务零额静默约定）。
     */
    public function testAuditZeroQualifiedAccumulatesNothing(): void
    {
        $fixture = $this->makeInProductionOrder();
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10', '0');

        $audited = $this->workReportService()->audit($reportId);
        $this->assertSame(1, (int) $audited->status, '零合格仍可审核（计废品场景）');

        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('0.00', (string) $row->amount, '金额为零');
        $this->assertNull($this->wipRow($fixture['order_id']), '零额不建 WIP');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 0, '零额不写流水');
    }

    // ---------- 夹具 ----------

    /** 生产中工单（已开工）+ 计件工序 + 员工；返回各主档 id */
    private function makeInProductionOrder(string $rate = '2.50'): array
    {
        $product = $this->createProduct();
        $bomId = $this->createBom($product['product_id'], [['product_id' => $product['product_id'], 'quantity' => '2']]);
        $orderId = $this->createOrder($bomId, '3');
        $this->startOrder($orderId);

        return [
            'product_id' => $product['product_id'],
            'bom_id' => $bomId,
            'order_id' => $orderId,
            'routing_id' => $this->createRouting($product['product_id'], $rate),
            'employee_id' => $this->createEmployee(),
        ];
    }
}
