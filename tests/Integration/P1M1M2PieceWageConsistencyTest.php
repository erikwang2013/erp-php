<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * M1 计件工资归集一致性测试（P1-M1b 独立验证，与 coder 用例互补）
 *
 * 覆盖 coder 套件之外的完整性面：金额链在报工单/WIP/计件台账三处逐分一致
 * （无二次取整漂移）、重复审核被拒且不重复累计计件、批处理中途失败时外层
 * 事务回滚同时撤销计件与 WIP 副作用（同事务原子性）、同一员工跨工单同月
 * 归并到同一计件行。
 */
#[Group('integration')]
class P1M1M2PieceWageConsistencyTest extends P1M1M2CostingScaffold
{
    /**
     * 金额链全等：exact-half 进位（1.01×2.50=2.525→2.53）后，报工单金额、
     * 计件台账金额、WIP 人工成本与归集流水必须同为 2.53——任一处二次取整
     * 都会破坏链上等值。
     */
    public function testAmountChainIdenticalAcrossReportWipAndWage(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '1.01', '1.01', '2026-08-15');
        $this->workReportService()->audit($reportId);

        $report = $this->workReportRow($reportId);
        $this->assertBcEquals('2.53', (string) $report->amount, '报工单金额 2.525→2.53');
        $this->assertSame(1, (int) $report->status, '已审核');

        $wage = $this->pieceWageRow($fixture['employee_id']);
        $this->assertSame(2026, (int) $wage->period_year, '期间年=报工年');
        $this->assertSame(8, (int) $wage->period_month, '期间月=报工月');
        $this->assertBcEquals('1.01', (string) $wage->quantity, '计件数量=报工合格数');
        $this->assertBcEquals('2.53', (string) $wage->amount, '计件台账金额=报工单金额');

        $wip = $this->wipRow($fixture['order_id']);
        $this->assertBcEquals('2.53', (string) $wip->labor_cost, 'WIP 人工成本=报工单金额');
        $this->assertBcEquals('2.53', (string) $wip->total_cost, 'WIP 总成本同步');

        $flows = $this->wipFlowRows($fixture['order_id']);
        $this->assertCount(1, $flows, '恰一条归集流水');
        $this->assertBcEquals('2.53', (string) $flows[0]->amount, '流水金额=报工单金额');
        $this->assertSame(2, (int) $flows[0]->source_type, '人工桶');
        $this->assertSame($reportId, (int) $flows[0]->source_id, '流水挂报工单');
    }

    /**
     * 重复审核被拒：已审单据再次审核抛错，计件台账保持单行且金额不变。
     */
    public function testReauditRejectedLeavesWageRowUntouched(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $reportId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10', '10', '2026-08-15');
        $this->workReportService()->audit($reportId);

        $this->assertThrowsMessage(
            fn () => $this->workReportService()->audit($reportId),
            '只有草稿状态的报工单可以审核'
        );

        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $fixture['employee_id']], 1, '计件行不因重复审核翻倍');
        $this->assertBcEquals('25.00', (string) $this->pieceWageRow($fixture['employee_id'])->amount, '金额保持首次审核值');
    }

    /**
     * 同事务原子性：外层事务中首张报工审核成功（计件+WIP 已写），随后第二张
     * 报工因工序缺失失败并抛出——外层回滚必须把首张的计件行、WIP、流水与
     * 审核状态一并撤销，不留半套副作用。
     */
    public function testOuterRollbackDiscardsWageAndWipSideEffects(): void
    {
        $fixture = $this->makeInProductionOrder('2.50');
        $firstId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '10', '10', '2026-08-15');

        $secondId = 0;
        $this->assertThrowsMessage(function () use ($fixture, $firstId, &$secondId): void {
            Capsule::transaction(function () use ($fixture, $firstId, &$secondId): void {
                $this->workReportService()->audit($firstId);
                // 删除工序使第二张报工在事务内失败（DELETE 随外层回滚复原）
                Capsule::table('erp_mfg_routing')->where('id', $fixture['routing_id'])->delete();
                $secondId = $this->createWorkReport($fixture['order_id'], $fixture['product_id'], $fixture['routing_id'], $fixture['employee_id'], '5', '5', '2026-08-15');
                $this->workReportService()->audit($secondId);
            });
        }, '工序不存在');

        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $fixture['employee_id']], 0, '计件行整体撤销');
        $this->assertNull($this->wipRow($fixture['order_id']), 'WIP 台账整体撤销');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $fixture['order_id']], 0, '归集流水整体撤销');

        $first = $this->workReportRow($firstId);
        $this->assertSame(0, (int) $first->status, '首张单据回滚为草稿');
        $this->assertBcEquals('0.00', (string) $first->amount, '首张金额未冻结');
        $this->assertNull($this->workReportRow($secondId), '事务内创建的失败单据已整体回滚');
    }

    /**
     * 跨工单归并：同一员工当月先后在两个不同工单报工，计件台账合并为一行
     * （按员工+期间，与工单无关），各工单 WIP 各自独立累计。
     */
    public function testTwoOrdersSameMonthMergeIntoOneWageRow(): void
    {
        $f1 = $this->makeInProductionOrder('2.50');
        $f2 = $this->makeInProductionOrder('3.00');
        $employeeId = $f1['employee_id'];

        $r1 = $this->createWorkReport($f1['order_id'], $f1['product_id'], $f1['routing_id'], $employeeId, '10', '10', '2026-08-15');
        $this->workReportService()->audit($r1);
        $r2 = $this->createWorkReport($f2['order_id'], $f2['product_id'], $f2['routing_id'], $employeeId, '5', '5', '2026-08-20');
        $this->workReportService()->audit($r2);

        $this->assertRowCount('erp_mfg_piece_wage', ['employee_id' => $employeeId], 1, '两单归并为一行');
        $wage = $this->pieceWageRow($employeeId);
        $this->assertBcEquals('15', (string) $wage->quantity, '数量 10+5');
        $this->assertBcEquals('40.00', (string) $wage->amount, '金额 25.00+15.00（不同单价）');

        $this->assertBcEquals('25.00', (string) $this->wipRow($f1['order_id'])->labor_cost, '工单一 WIP 独立');
        $this->assertBcEquals('15.00', (string) $this->wipRow($f2['order_id'])->labor_cost, '工单二 WIP 独立');
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
