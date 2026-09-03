<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * F3 成本核算守卫与口径边界独立测试（tester 独立验证，与 coder 场景互补）。
 *
 * 复用 F3CostingScaffold 造数与清理；金额一律 bccomp 断言。
 * 与 coder 已覆盖（正路/零成本/库存不足回滚/超用·节约差异/未开工完工/
 * 科目缺失回滚/完工后补审被拒）不重叠，本类聚焦：
 *
 * - testCompletionBlendsMovingAverage：完工入库与既有库存移动加权融合，
 *   库存行均价按 InventoryService 口径重算（非完工单价直落）；
 * - testHalfCentRoundingOnUnitCost：完工单位成本半分位进位（bc_round
 *   半值远离零：50.02/4 → 12.505 → 12.51）；
 * - testAuditSideEffectsHappenOnlyOnce：重复审核同单被拒，库存/WIP/
 *   流水均只发生一次；不存在单据/工单的明确拒绝；
 * - testInvalidInputsRejected：负/零领料数量、空明细、零/负费用金额、
 *   非法费用类型、领料/归集挂不存在工单；
 * - testCompletionQuantityAndReentryGuard：完工数量 0 被拒不动库，
 *   完工后二次完工被拒，库存/成本单/凭证来源无第二次副作用。
 */
#[Group('integration')]
class F3CostingGuardrailsTest extends F3CostingScaffold
{
    /**
     * 完工入库移动加权融合。
     *
     * 产成品 A 预置库存 10 × 8.00（80.00）；材料 B 10 × 1.00，
     * BOM A←B×2 领 B2 → 材料 2.00，人工归集 1.00 → 完工总成本 3.00。
     * 完工 2 件 × 1.50 入库 → 移动加权 = (80.00 + 2×1.50)/12
     * = 83.00/12 = 6.9166… → 库存均价 6.92（非 1.50），口径随
     * InventoryService::recalcMovingAverageCost 半值进位舍入。
     */
    public function testCompletionBlendsMovingAverage(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($a['product_id'], $a['sku_id'], '10', '8.00');
        $this->seedStock($b['product_id'], $b['sku_id'], '10', '1.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '2'],
        ]);
        $orderId = $this->createOrder($bomId, '2');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 2 => 1002, 4 => 1004]);

        // 预置库存均价确认
        $this->assertBcEquals('10.00', (string) $this->stockRow($a['product_id'], $a['sku_id'])->quantity, 'A 预置数量');
        $this->assertBcEquals('8.00', (string) $this->stockRow($a['product_id'], $a['sku_id'])->cost_price, 'A 预置均价');

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '2'],
        ]);
        $this->auditIssue($issueId);
        $this->assertBcEquals('2.00', (string) $this->wipRow($orderId)->material_cost, 'WIP 材料');

        $laborId = $this->createCostEntry($orderId, 1, '1.00');
        $this->auditCostEntry($laborId);
        $this->assertBcEquals('3.00', (string) $this->wipRow($orderId)->total_cost, 'WIP 合计');

        $this->costService()->completeWithCost($orderId, 2.0, self::WH_ID);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('2.00', (string) $oc->finished_qty, '完工数');
        $this->assertBcEquals('2.00', (string) $oc->standard_material_cost, '标准材料');
        $this->assertBcEquals('2.00', (string) $oc->actual_material_cost, '实际材料');
        $this->assertBcEquals('1.00', (string) $oc->labor_cost, '人工');
        $this->assertBcEquals('0.00', (string) $oc->material_diff, '差异');
        $this->assertBcEquals('3.00', (string) $oc->total_cost, '总成本');
        $this->assertBcEquals('1.50', (string) $oc->unit_cost, '单位成本');
        $this->assertSame(1, (int) $oc->status, '成本单已结转');

        // 口径核心：库存行均价 = (80.00+3.00)/12 = 6.92，不是完工单价 1.50
        $invA = $this->stockRow($a['product_id'], $a['sku_id']);
        $this->assertBcEquals('12.00', (string) $invA->quantity, 'A 融合后数量');
        $this->assertBcEquals('6.92', (string) $invA->cost_price, 'A 融合后移动加权均价');

        // 完工入库流水恰好一笔（source mfg_production_finish）
        $this->assertRowCount(
            'erp_inventory_flow',
            ['product_id' => $a['product_id'], 'source_type' => 'mfg_production_finish'],
            1,
            '完工入库流水'
        );

        // WIP 转出流水（type5/方向2）一笔
        $this->assertRowCount(
            'erp_mfg_wip_flow',
            ['order_id' => $orderId, 'source_type' => 5, 'direction' => 2],
            1,
            '完工转出流水'
        );

        // 凭证方向与借贷平衡：借 1004=3.00 / 贷 1001=2.00 + 1002=1.00
        $this->assertNotNull($this->voucherOfOc((int) $oc->id), '结转凭证存在');
        $lines = $this->voucherLines((int) $this->voucherOfOc((int) $oc->id)->id);
        $this->assertCount(3, $lines, '凭证 3 行');
        $this->assertBcEquals('3.00', (string) $lines[1004]->debit_amount, '借 存货/产成品');
        $this->assertBcEquals('2.00', (string) $lines[1001]->credit_amount, '贷 材料(实际)');
        $this->assertBcEquals('1.00', (string) $lines[1002]->credit_amount, '贷 人工');
        $this->assertVoucherBalanced((int) $this->voucherOfOc((int) $oc->id)->id);
    }

    /**
     * 单位成本半分位舍入：50.02 / 4 = 12.505 → bc_round 半值进位 → 12.51。
     *
     * 材料单价 25.01（2 位小数额定），领 B2 = 50.02，完工 4 件；
     * 未配置差异科目(5) → 贷材料按实际 50.02，无差异行。
     */
    public function testHalfCentRoundingOnUnitCost(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '10', '25.01');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '2'],
        ]);
        $orderId = $this->createOrder($bomId, '4');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 4 => 1004]);

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '2'],
        ]);
        $this->auditIssue($issueId);
        $this->assertBcEquals('50.02', (string) Capsule::table('erp_mfg_material_issue')->where('id', $issueId)->first()->total_cost, '领料单合计');

        $this->costService()->completeWithCost($orderId, 4.0, self::WH_ID);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('50.02', (string) $oc->standard_material_cost, '标准材料');
        $this->assertBcEquals('50.02', (string) $oc->actual_material_cost, '实际材料');
        $this->assertBcEquals('50.02', (string) $oc->total_cost, '总成本');
        $this->assertBcEquals('12.51', (string) $oc->unit_cost, '单位成本半值进位 12.505→12.51');

        // 材料剩余 10-2=8，成本价不变；产成品零初始库存直落完工单价
        $this->assertBcEquals('8.00', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, 'B 剩余');
        $invA = $this->stockRow($a['product_id'], $a['sku_id']);
        $this->assertBcEquals('4.00', (string) $invA->quantity, 'A 入库数量');
        $this->assertBcEquals('12.51', (string) $invA->cost_price, 'A 入库均价=完工单价');

        $this->assertNotNull($this->voucherOfOc((int) $oc->id), '结转凭证存在');
        $lines = $this->voucherLines((int) $this->voucherOfOc((int) $oc->id)->id);
        $this->assertCount(2, $lines, '凭证 2 行（无差异行）');
        $this->assertBcEquals('50.02', (string) $lines[1004]->debit_amount, '借 存货');
        $this->assertBcEquals('50.02', (string) $lines[1001]->credit_amount, '贷 材料(实际)');
        $this->assertVoucherBalanced((int) $this->voucherOfOc((int) $oc->id)->id);
    }

    /**
     * 防重入：同一领料单/费用单二次审核被拒，库存与 WIP 只发生一次；
     * 单据不存在、工单不存在给出明确拒绝。
     */
    public function testAuditSideEffectsHappenOnlyOnce(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '2');
        $this->startOrder($orderId);

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '1'],
        ]);
        $this->auditIssue($issueId);
        $this->assertBcEquals('4.00', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, 'B 出库后剩余');

        // 二次审核同单被拒，且无第二次副作用
        $this->assertThrowsMessage(fn () => $this->auditIssue($issueId), '只有草稿状态的领料单可以审核');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $orderId, 'source_type' => 1, 'source_id' => $issueId], 1, '领料流水仍 1 笔');
        $this->assertBcEquals('4.00', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, '库存未被二次扣减');
        $this->assertBcEquals('2.00', (string) $this->wipRow($orderId)->material_cost, 'WIP 材料未被二次累加');

        $laborId = $this->createCostEntry($orderId, 1, '3.00');
        $this->auditCostEntry($laborId);
        $this->assertBcEquals('3.00', (string) $this->wipRow($orderId)->labor_cost, 'WIP 人工');

        $this->assertThrowsMessage(fn () => $this->auditCostEntry($laborId), '只有草稿状态的费用归集单可以审核');
        $this->assertRowCount('erp_mfg_wip_flow', ['order_id' => $orderId, 'source_type' => 2, 'source_id' => $laborId], 1, '费用流水仍 1 笔');
        $this->assertBcEquals('3.00', (string) $this->wipRow($orderId)->labor_cost, 'WIP 人工未被二次累加');

        // 单据不存在
        $this->assertThrowsMessage(fn () => $this->costService()->auditIssue($this->nextId()), '领料单不存在');
        $this->assertThrowsMessage(fn () => $this->costService()->auditCostEntry($this->nextId()), '费用归集单不存在');

        // 领料/归集挂不存在的工单
        $ghostOrder = $this->nextId();
        $ghostIssue = $this->createIssue($ghostOrder, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '1'],
        ]);
        $this->assertThrowsMessage(fn () => $this->auditIssue($ghostIssue), '生产工单不存在');
        $this->assertThrowsMessage(
            fn () => $this->auditCostEntry($this->createCostEntry($ghostOrder, 1, '5.00')),
            '生产工单不存在'
        );
    }

    /**
     * 非法输入守卫：负/零领料数量、空明细、零/负费用金额、非法费用类型。
     * 全部拒绝且单据停留草稿、库存分毫未动。
     */
    public function testInvalidInputsRejected(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '2');
        $this->startOrder($orderId);
        $sku = $b['sku_id'];

        // 负数量 / 零数量
        foreach (['-2', '0'] as $qty) {
            $issueId = $this->createIssue($orderId, [
                ['product_id' => $b['product_id'], 'sku_id' => $sku, 'quantity' => $qty],
            ]);
            $this->assertThrowsMessage(fn () => $this->auditIssue($issueId), '领料数量必须大于0');
            $this->assertSame(0, (int) Capsule::table('erp_mfg_material_issue')->where('id', $issueId)->first()->status, '拒绝后仍草稿');
        }
        $this->assertBcEquals('5.00', (string) $this->stockRow($b['product_id'], $sku)->quantity, '库存未动');

        // 空明细
        $emptyIssue = $this->createIssue($orderId, []);
        $this->assertThrowsMessage(fn () => $this->auditIssue($emptyIssue), '领料单明细不能为空，无法审核');

        // 零金额 / 负金额
        foreach (['0.00', '-5.00'] as $amount) {
            $entryId = $this->createCostEntry($orderId, 1, $amount);
            $this->assertThrowsMessage(fn () => $this->auditCostEntry($entryId), '费用金额必须大于0');
            $this->assertSame(0, (int) Capsule::table('erp_mfg_cost_entry')->where('id', $entryId)->first()->status, '拒绝后仍草稿');
        }

        // 非法费用类型
        $typeEntry = $this->createCostEntry($orderId, 4, '5.00');
        $this->assertThrowsMessage(fn () => $this->auditCostEntry($typeEntry), '费用类型非法: 1=人工 2=制费 3=其他');

        // 全程无 WIP（所有审核均被拒）
        $this->assertNull($this->wipRow($orderId), '非法输入不应建 WIP');
    }

    /**
     * 完工参数与重复完工守卫：数量 0 被拒不动库；完工成功后再次
     * 完工被拒，库存/成本单/凭证来源无第二次副作用。
     */
    public function testCompletionQuantityAndReentryGuard(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '2');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 4 => 1004]);

        // 完工数量 0 → 拒绝，工单仍在生产、无成本单、无入库
        $this->assertThrowsMessage(fn () => $this->costService()->completeWithCost($orderId, 0.0, self::WH_ID), '完工数量必须大于0');
        $this->assertSame(1, (int) $this->orderRow($orderId)->status, '工单仍在生产');
        $this->assertNull($this->ocRow($orderId), '无成本单');
        $this->assertNull($this->stockRow($a['product_id'], $a['sku_id']), '无入库');

        // 正常完工一次
        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '1'],
        ]);
        $this->auditIssue($issueId);
        $this->costService()->completeWithCost($orderId, 2.0, self::WH_ID);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('2.00', (string) $oc->total_cost, '完工总成本');
        $this->assertBcEquals('1.00', (string) $oc->unit_cost, '单位成本');
        $this->assertBcEquals('2.00', (string) $this->stockRow($a['product_id'], $a['sku_id'])->quantity, 'A 入库数量');
        $this->assertSame(1, (int) $this->ocRow($orderId)->status, '成本单已结转');

        // 登记凭证供 tearDown 清理（voucher_source 仅按 voucher_id 清理）
        $this->assertNotNull($this->voucherOfOc((int) $oc->id), '结转凭证存在');

        // 二次完工被拒：库存不再加、成本单不新增、凭证来源不翻倍
        $this->assertThrowsMessage(
            fn () => $this->costService()->completeWithCost($orderId, 1.0, self::WH_ID),
            '只有生产中的工单可以完工'
        );
        $this->assertSame(2, (int) $this->orderRow($orderId)->status, '工单保持已完成');
        $this->assertBcEquals('2.00', (string) $this->stockRow($a['product_id'], $a['sku_id'])->quantity, '入库未翻倍');
        $this->assertRowCount('erp_mfg_order_cost', ['order_id' => $orderId], 1, '成本单仍 1 张');
        $this->assertRowCount('erp_inventory_flow', ['product_id' => $a['product_id'], 'source_type' => 'mfg_production_finish'], 1, '完工入库流水仍 1 笔');
        $this->assertRowCount('erp_finance_voucher_source', ['source_type' => 'mfg_order_cost'], 1, '凭证来源仍 1 行');
    }

    /** 借贷平衡断言：借方合计 == 贷方合计（bcmath 精确） */
    private function assertVoucherBalanced(int $voucherId): void
    {
        $dr = '0';
        $cr = '0';
        foreach (Capsule::table('erp_finance_voucher_item')->where('voucher_id', $voucherId)->get() as $row) {
            $dr = bcadd($dr, bc_norm($row->debit_amount), 6);
            $cr = bcadd($cr, bc_norm($row->credit_amount), 6);
        }
        $this->assertSame(0, bccomp($dr, $cr, 6), "凭证借贷不平衡: 借={$dr} 贷={$cr}");
    }
}
