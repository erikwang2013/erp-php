<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * F3 完工成本核算主场景集成测试（场景 a/c/d）。
 *
 * 环境变量契约：TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE /
 * TEST_DB_USERNAME / TEST_DB_PASSWORD；TEST_DB_DATABASE 为空则跳过。
 * 依赖表结构（install.sql）与 erp_finance_voucher.ledger_id
 * （p0_f1f2.sql ALTER）缺失时整类跳过（见 F3CostingScaffold）。
 *
 * 造数与清理经 F3CostingScaffold：F3 自有表可最小化创建亦可复用，
 * 真实表仅清理本类写入行。金额断言一律 bccomp，不依赖浮点字面量。
 *
 * - testHappyPath（场景 a）：领料+人工+制费全量归集后完工，
 *   出库均价、标准成本、WIP 台账、完工入库、结转凭证逐项校验；
 * - testZeroCostCompletion（场景 c）：无任何归集即完工，成本单
 *   零值落库、不生成 WIP/凭证，且 generateCostVoucher 拒绝 0 成本；
 * - testRollbackOnInsufficientStock（场景 d）：领料审核遇库存不足
 *   整单回滚，库存/单据/WIP/流水均无残留。
 */
#[Group('integration')]
class F3ProductionCostingTest extends F3CostingScaffold
{
    /**
     * 场景 a — 完整完工结算：材料 5.00 + 人工 2.00 + 制费 1.00。
     *
     * BOM A←(B×2, C×1)；B 单价 2.00 库存 5，C 单价 1.00 库存 3。
     * 领 B2+C1（实际材料 2×2.00+1×1.00=5.00），人工 2.00，制费 1.00。
     * B/C 均已领料 → 标准材料 = BOM B2×2.00 + C1×1.00 = 5.00，差异 0。
     * 完工 3 件 → 单位成本 8.00/3 = 2.67（四舍五入）。
     * 未配置 5(材料差异) 科目 → 贷材料行按实际成本 5.00 记。
     */
    public function testHappyPath(): void
    {
        $a = $this->createProduct(); // 产成品 A
        $b = $this->createProduct();
        $c = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $this->seedStock($c['product_id'], $c['sku_id'], '3', '1.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '2'],
            ['product_id' => $c['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '3');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 2 => 1002, 3 => 1003, 4 => 1004]);

        // 领料单审核：出库 + 单据 + WIP 材料桶
        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '2'],
            ['product_id' => $c['product_id'], 'sku_id' => $c['sku_id'], 'quantity' => '1'],
        ]);
        $this->auditIssue($issueId);

        $issue = Capsule::table('erp_mfg_material_issue')->where('id', $issueId)->first();
        $this->assertSame(1, (int) $issue->status, '领料单应已审核');
        $this->assertBcEquals('5.00', (string) $issue->total_cost, '领料单合计');
        $this->assertBcEquals('3.00', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, 'B 剩余');
        $this->assertBcEquals('2.00', (string) $this->stockRow($c['product_id'], $c['sku_id'])->quantity, 'C 剩余');
        $wip = $this->wipRow($orderId);
        $this->assertNotNull($wip, 'WIP 台账应已创建');
        $this->assertBcEquals('5.00', (string) $wip->material_cost, 'WIP 材料');
        $this->assertBcEquals('5.00', (string) $wip->total_cost, 'WIP 合计');
        $this->assertSame(0, (int) $wip->status, 'WIP 应为在制');

        // 人工 + 制费归集审核
        $laborId = $this->createCostEntry($orderId, 1, '2.00');
        $this->auditCostEntry($laborId);
        $overheadId = $this->createCostEntry($orderId, 2, '1.00');
        $this->auditCostEntry($overheadId);

        $wip = $this->wipRow($orderId);
        $this->assertBcEquals('5.00', (string) $wip->material_cost, 'WIP 材料');
        $this->assertBcEquals('2.00', (string) $wip->labor_cost, 'WIP 人工');
        $this->assertBcEquals('1.00', (string) $wip->overhead_cost, 'WIP 制费');
        $this->assertBcEquals('0.00', (string) $wip->other_cost, 'WIP 其他');
        $this->assertBcEquals('8.00', (string) $wip->total_cost, 'WIP 合计');

        // 完工结算：入库 + 成本单 + WIP 结转 + 凭证
        $this->costService()->completeWithCost($orderId, 3.0, self::WH_ID);

        $order = $this->orderRow($orderId);
        $this->assertSame(2, (int) $order->status, '工单应已完成');
        $this->assertBcEquals('3.00', (string) $order->completed_quantity, '完工数量');

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('3.00', (string) $oc->finished_qty, '成本单完工数');
        $this->assertBcEquals('5.00', (string) $oc->standard_material_cost, '标准材料成本');
        $this->assertBcEquals('5.00', (string) $oc->actual_material_cost, '实际材料成本');
        $this->assertBcEquals('2.00', (string) $oc->labor_cost, '成本单人工');
        $this->assertBcEquals('1.00', (string) $oc->overhead_cost, '成本单制费');
        $this->assertBcEquals('0.00', (string) $oc->other_cost, '成本单其他');
        $this->assertBcEquals('0.00', (string) $oc->material_diff, '材料差异');
        $this->assertBcEquals('8.00', (string) $oc->total_cost, '完工成本合计');
        $this->assertBcEquals('2.67', (string) $oc->unit_cost, '单位成本');
        $this->assertSame(1, (int) $oc->status, '成本单应已结转');
        $this->assertTrue((int) $oc->voucher_id > 0, '应已生成结转凭证');

        $wip = $this->wipRow($orderId);
        $this->assertSame(2, (int) $wip->status, 'WIP 应已生成凭证态');
        $this->assertBcEquals('8.00', (string) $wip->total_cost, 'WIP 结转后合计');

        $inventoryA = $this->stockRow($a['product_id'], $a['sku_id']);
        $this->assertBcEquals('3.00', (string) $inventoryA->quantity, 'A 入库数量');
        $this->assertBcEquals('2.67', (string) $inventoryA->cost_price, 'A 入库单价');

        // WIP 流水：3 笔归集（方向1）+ 1 笔完工转出（方向2）
        $this->assertFlowAmount($orderId, 1, $issueId, '1', '5.00');
        $this->assertFlowAmount($orderId, 2, $laborId, '1', '2.00');
        $this->assertFlowAmount($orderId, 3, $overheadId, '1', '1.00');
        $this->assertFlowAmount($orderId, 5, 0, '2', '8.00');

        // 结转凭证：借 4(存货) 8.00 / 贷 1(材料·实际) 5.00 / 贷 2(人工) 2.00 / 贷 3(制费) 1.00
        $voucher = $this->voucherOfOc((int) $oc->id);
        $this->assertNotNull($voucher, '结转凭证应存在');
        $this->assertStringContainsString('工单完工成本结转-', (string) $voucher->remark);
        $this->assertSame(0, (int) $voucher->status, '凭证应为草稿态');
        $lines = $this->voucherLines((int) $voucher->id);
        $this->assertSame(4, count($lines), '凭证应 4 行');
        $this->assertBcEquals('8.00', (string) $lines[1004]->debit_amount, '借 存货/产成品');
        $this->assertSame('完工结转-存货/产成品(实际成本)', (string) $lines[1004]->summary);
        $this->assertBcEquals('5.00', (string) $lines[1001]->credit_amount, '贷 材料');
        $this->assertSame('完工结转-材料(实际成本)', (string) $lines[1001]->summary);
        $this->assertBcEquals('2.00', (string) $lines[1002]->credit_amount, '贷 人工');
        $this->assertBcEquals('1.00', (string) $lines[1003]->credit_amount, '贷 制费');
        $this->assertRowCount(
            'erp_finance_voucher_source',
            ['voucher_id' => (int) $voucher->id],
            1,
            '凭证来源登记'
        );

        // 收尾守卫：重复生成被拒；完工后新领料单/归集单不可审核
        $this->assertThrowsMessage(
            fn () => $this->costService()->generateCostVoucher((int) $oc->id),
            '该工单结转凭证已生成'
        );
        $lateIssue = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '1'],
        ]);
        $this->assertThrowsMessage(
            fn () => $this->auditIssue($lateIssue),
            '只有生产中的工单可以领料'
        );
        $lateIssueRow = Capsule::table('erp_mfg_material_issue')->where('id', $lateIssue)->first();
        $this->assertSame(0, (int) $lateIssueRow->status, '完工后的新领料单应保持草稿');
        $lateEntry = $this->createCostEntry($orderId, 3, '1.00');
        $this->assertThrowsMessage(
            fn () => $this->auditCostEntry($lateEntry),
            '只有生产中的工单可以归集费用'
        );
    }

    /**
     * 场景 c — 零成本完工：无领料无费用，完工照常入库。
     *
     * BOM A←(B×1)，B 从未领料（标准材料缺 lastCost → 跳过 → 0）。
     * 成本单零值落库（voucher_id=0 / status=0 / unit=0），不建 WIP，
     * 无凭证无来源；generateCostVoucher 拒绝 0 成本。
     */
    public function testZeroCostCompletion(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '2');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 4 => 1004]);

        $this->costService()->completeWithCost($orderId, 2.0, self::WH_ID);

        $order = $this->orderRow($orderId);
        $this->assertSame(2, (int) $order->status, '工单应已完成');
        $this->assertBcEquals('2.00', (string) $order->completed_quantity);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('2.00', (string) $oc->finished_qty, '完工数');
        $this->assertBcEquals('0.00', (string) $oc->standard_material_cost, '标准材料');
        $this->assertBcEquals('0.00', (string) $oc->actual_material_cost, '实际材料');
        $this->assertBcEquals('0.00', (string) $oc->total_cost, '合计');
        $this->assertBcEquals('0', (string) $oc->unit_cost, '单位成本');
        $this->assertSame(0, (int) $oc->voucher_id, '不应有凭证');
        $this->assertSame(0, (int) $oc->status, '成本单应为未结转态');

        $this->assertNull($this->wipRow($orderId), '零成本不应建 WIP');

        $inventoryA = $this->stockRow($a['product_id'], $a['sku_id']);
        $this->assertBcEquals('2.00', (string) $inventoryA->quantity, 'A 入库数量');
        $this->assertBcEquals('0.00', (string) $inventoryA->cost_price, 'A 零单价');

        $this->assertRowCount(
            'erp_finance_voucher_source',
            ['source_type' => 'mfg_order_cost'],
            0,
            '无凭证来源'
        );

        $this->assertThrowsMessage(
            fn () => $this->costService()->generateCostVoucher((int) $oc->id),
            '完工成本为0，无需生成结转凭证'
        );
    }

    /**
     * 场景 d — 领料审核部分库存不足：整单回滚。
     *
     * B 库存 10 可出 8，C 库存 2 不足以出 3 → stockOut 抛库存不足；
     * 事务回滚后 B 恢复 10，单据停留草稿零值，无 WIP 无库存流水。
     */
    public function testRollbackOnInsufficientStock(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $c = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '10', '2.00');
        $this->seedStock($c['product_id'], $c['sku_id'], '2', '1.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '2'],
            ['product_id' => $c['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '4');
        $this->startOrder($orderId);
        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '8'],
            ['product_id' => $c['product_id'], 'sku_id' => $c['sku_id'], 'quantity' => '3'],
        ]);

        $this->assertThrowsMessage(
            fn () => $this->auditIssue($issueId),
            '库存不足'
        );

        // 库存原样
        $this->assertBcEquals('10.00', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, 'B 库存应回滚');
        $this->assertBcEquals('2.00', (string) $this->stockRow($c['product_id'], $c['sku_id'])->quantity, 'C 库存应回滚');

        // 单据仍草稿零值
        $issue = Capsule::table('erp_mfg_material_issue')->where('id', $issueId)->first();
        $this->assertSame(0, (int) $issue->status, '领料单应保持草稿');
        $this->assertBcEquals('0', (string) $issue->total_cost, '领料单合计应回滚');
        $this->assertNull($issue->audit_at, '审核时间应回滚');

        // 明细零值
        foreach (Capsule::table('erp_mfg_material_issue_item')->where('issue_id', $issueId)->get() as $item) {
            $this->assertBcEquals('0', (string) $item->unit_cost, '明细单价应回滚');
            $this->assertBcEquals('0', (string) $item->amount, '明细金额应回滚');
        }

        // 无 WIP、无本单库存流水、工单仍在生产
        $this->assertNull($this->wipRow($orderId), '失败审核不应建 WIP');
        $this->assertRowCount(
            'erp_inventory_flow',
            ['source_type' => 'mfg_material_issue_item', 'source_id' => $issueId],
            0,
            '领料流水应回滚'
        );
        $this->assertSame(1, (int) $this->orderRow($orderId)->status, '工单应仍在生产');
    }

    /** WIP 流水单笔断言：order_id + source_type(+source_id) + direction + amount */
    private function assertFlowAmount(int $orderId, int $sourceType, int $sourceId, string $direction, string $amount): void
    {
        $query = Capsule::table('erp_mfg_wip_flow')
            ->where('order_id', $orderId)
            ->where('source_type', $sourceType)
            ->where('direction', $direction);
        if ($sourceId > 0) {
            $query->where('source_id', $sourceId);
        }
        $rows = $query->get();
        $this->assertCount(1, $rows, "WIP 流水 source_type={$sourceType} 应恰 1 笔");
        $this->assertBcEquals($amount, (string) $rows[0]->amount, "WIP 流水 source_type={$sourceType} 金额");
    }
}
