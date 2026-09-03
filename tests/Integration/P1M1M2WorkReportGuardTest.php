<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * M1 工序报工守卫测试（P1-M1a 独立验证，与 coder 用例互补）
 *
 * 覆盖 coder 套件之外的守卫面：审核后改路由单价不影响已审金额（快照隔离）、
 * 草稿审前改数生效（金额不冻结）、报工单不存在/工序不存在错误路径、
 * 同单多笔报工累计归集、exact-half 分位进位、已关账 WIP（工单已完工结转）
 * 拒绝并回滚。
 */
#[Group('integration')]
class P1M1M2WorkReportGuardTest extends P1M1M2CostingScaffold
{
    /**
     * 快照隔离：审核后再调高路由计件单价，已审单据金额/单价快照不受影响，
     * WIP 归集额也不变。
     */
    public function testAuditedAmountIsolatedFromRoutingRateChange(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');
        $this->workReportService()->audit($reportId);

        // 模拟工艺路线后续调价（草稿报工才受影响；已审单据必须保持审核时点快照）
        Capsule::table('erp_mfg_routing')->where('id', $fixture['routing_id'])->update(['piece_rate' => '99.00']);

        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('2.50', (string) $row->piece_rate, '单价快照不随路由调价漂移');
        $this->assertBcEquals('25.00', (string) $row->amount, '金额快照不随路由调价漂移');
        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('25.00', (string) $wip->labor_cost, 'WIP 归集额不受后续调价影响');
        $this->assertSame(1, (int) $row->status, '单据保持已审核');
    }

    /**
     * 草稿金额不冻结：审核前改报工数量（等价控制器 update 路径），审核反映最新值。
     */
    public function testDraftEditsApplyBeforeAudit(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');
        $this->assertSame(0, (int) $this->workReportRow($reportId)->amount, '草稿金额初始为 0');

        // 草稿可改：数量与合格数 10 → 20（等价控制器对 status=0 单据的 update 语义）
        Capsule::table('erp_mfg_work_report')->where('id', $reportId)->update(['quantity' => '20', 'qualified_qty' => '20']);

        $this->workReportService()->audit($reportId);
        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('50.00', (string) $row->amount, '审前改数量后金额=20×2.50');
        $this->assertBcEquals('2.50', (string) $row->piece_rate, '单价快照');
        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('50.00', (string) $wip->labor_cost, 'WIP 与最终审核值一致');
    }

    /**
     * 不存在的报工单审核：报工单不存在。
     */
    public function testAuditRejectsMissingReport(): void
    {
        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit(99110099),
            '报工单不存在'
        );
    }

    /**
     * 工序被删后审核草稿报工：工序不存在，单据保持草稿且无任何归集痕迹。
     */
    public function testAuditRejectsMissingRouting(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');

        // 工序删除后残留草稿报工（routing 行删除由 tearDown 二次清理兜底）
        Capsule::table('erp_mfg_routing')->where('id', $fixture['routing_id'])->delete();

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '工序不存在'
        );
        $this->assertSame(0, (int) $this->workReportRow($reportId)->status, '单据保持草稿');
        $this->assertNull($this->wipRow($fixture['order_id']), '不得产生 WIP');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 0, '不得产生流水');
    }

    /**
     * 同单多笔报工：人工成本逐笔累计入同一 WIP 台账，流水各自成行。
     */
    public function testMultipleReportsAccumulateOnSameOrder(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportA = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');
        $reportB = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '5');
        $this->workReportService()->audit($reportA);
        $this->workReportService()->audit($reportB);

        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('37.50', (string) $wip->labor_cost, '两笔合计 25.00+12.50');
        $this->assertBcEquals('37.50', (string) $wip->total_cost, '总成本同步');

        $flows = array_values(array_filter(
            $this->wipFlowRows($fixture['order_id']),
            fn ($f): bool => in_array((int) $f->source_id, [$reportA, $reportB], true)
        ));
        $this->assertCount(2, $flows, '每笔报工各自一条流水');
        $this->assertBcEquals('37.50', (string) bcadd((string) $flows[0]->amount, (string) $flows[1]->amount, 4), '流水金额合计');
        foreach ($flows as $flow) {
            $this->assertSame(2, (int) $flow->source_type, '全部归集人工桶');
            $this->assertSame(1, (int) $flow->direction, '全部为归集方向');
        }
    }

    /**
     * exact-half 分位：数量×单价恰落在 .xx5 → 四舍五入远离零进位到分（2.525→2.53）。
     */
    public function testAuditRoundsExactHalfCentAwayFromZero(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '1.01');

        $this->workReportService()->audit($reportId);

        $row = $this->workReportRow($reportId);
        $this->assertBcEquals('2.53', (string) $row->amount, '1.01×2.50=2.525 → 2.53');
        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('2.53', (string) $wip->labor_cost, 'WIP 同额');
    }

    /**
     * 已完工结转 WIP（status=2）拒绝继续归集：整单回滚，草稿保持，无流水。
     */
    public function testAuditRejectsClosedWip(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        // 手工构造已生成结转凭证的关账 WIP（status=2，成本列走默认 0）
        Capsule::table('erp_mfg_wip')->insert([
            'id' => $this->nextId(),
            'order_id' => $fixture['order_id'],
            'status' => 2,
        ]);
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10');

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '工单已完工结转，禁止继续归集成本'
        );
        $row = $this->workReportRow($reportId);
        $this->assertSame(0, (int) $row->status, '事务回滚，单据保持草稿');
        $this->assertBcEquals('0.00', (string) $row->amount, '金额未冻结');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 0, '不得追加流水');
    }

    /**
     * 缺陷 #1 回归（806937f 修复）：DB 直写非正报工数量绕过控制器 → 服务层拒绝
     * '报工数量必须大于0'；quantity=0 与 -5 同守。单据保持草稿、金额不冻结、
     * 无工资行、无 WIP。
     */
    public function testAuditRejectsNonPositiveQuantity(): void
    {
        foreach (['0', '-5'] as $qty) {
            $fixture = $this->makeInProductionOrder('2.50');
            $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], $qty);

            $this->assertThrowsMessage(
                fn () => $this->workReportService()->audit($reportId),
                '报工数量必须大于0'
            );

            $row = $this->workReportRow($reportId);
            $this->assertSame(0, (int) $row->status, "qty={$qty} 单据保持草稿");
            $this->assertBcEquals('0.00', (string) $row->amount, '金额未冻结');
            $this->assertNull($this->pieceWageRow($fixture['employee_id']), '不得产生计件工资行');
            $this->assertNull($this->wipRow($fixture['order_id']), '不得产生 WIP');
        }
    }

    /**
     * 缺陷 #1 回归（806937f 修复）：DB 直写合格数量为负（绕过控制器）→ 服务层
     * 拒绝 '合格数量不能为负数'。单据保持草稿、无工资行、无 WIP。
     */
    public function testAuditRejectsNegativeQualifiedQuantity(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '5', '-1');

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '合格数量不能为负数'
        );

        $row = $this->workReportRow($reportId);
        $this->assertSame(0, (int) $row->status, '单据保持草稿');
        $this->assertNull($this->pieceWageRow($fixture['employee_id']), '不得产生计件工资行');
        $this->assertNull($this->wipRow($fixture['order_id']), '不得产生 WIP');
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
