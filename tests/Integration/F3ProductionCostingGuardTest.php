<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * F3 完工成本核算守卫场景集成测试（场景 e-X/e-Y/f1/f2）。
 *
 * 场景 e 系列验证材料差异双方向（配置 5=材料差异科目后走标准成本口径）：
 * - 贷方结构不变，借 4 记实际合计；差异>0 贷 5 超用 / <0 借 5 节约；
 * - 标准材料 = Σ BOM 用量 × 已领料组件 lastCost（未领组件跳过）。
 * 场景 f 系列验证完工前置守卫与科目映射缺失回滚：
 * - 未开工工单完工被拒且零副作用；
 * - 科目映射缺失时完工结算整单回滚（WIP 保留、其余零残留）。
 */
#[Group('integration')]
class F3ProductionCostingGuardTest extends F3CostingScaffold
{
    /**
     * 场景 e-X — 超领差异（超用，贷 5 正差异）。
     *
     * BOM A←(B×1, C×3)。领 B2@2.00 + C1@1.00 + D1@2.00（D 非 BOM 件，
     * 验证非 BOM 领料计入实际但不参与标准）。
     * 实际 7.00；标准 = B1×2.00 + C3×1.00 = 5.00（C 已领→lastCost 1.00，
     * 按 BOM 全量 3 计）；差异 +2.00 → 贷 5。
     */
    public function testVoucherWithMaterialOveruseDiff(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $c = $this->createProduct();
        $d = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $this->seedStock($c['product_id'], $c['sku_id'], '5', '1.00');
        $this->seedStock($d['product_id'], $d['sku_id'], '5', '2.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
            ['product_id' => $c['product_id'], 'quantity' => '3'],
        ]);
        $orderId = $this->createOrder($bomId, '1');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 4 => 1004, 5 => 1005]);

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '2'],
            ['product_id' => $c['product_id'], 'sku_id' => $c['sku_id'], 'quantity' => '1'],
            ['product_id' => $d['product_id'], 'sku_id' => $d['sku_id'], 'quantity' => '1'],
        ]);
        $this->auditIssue($issueId);

        $wip = $this->wipRow($orderId);
        $this->assertNotNull($wip, 'WIP 台账应已创建');
        $this->assertBcEquals('7.00', (string) $wip->material_cost, 'WIP 材料=实际领料');
        $this->assertBcEquals('7.00', (string) $wip->total_cost, 'WIP 合计');

        $this->costService()->completeWithCost($orderId, 1.0, self::WH_ID);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('7.00', (string) $oc->actual_material_cost, '实际材料');
        $this->assertBcEquals('5.00', (string) $oc->standard_material_cost, '标准材料');
        $this->assertBcEquals('2.00', (string) $oc->material_diff, '材料差异=+2.00');
        $this->assertBcEquals('7.00', (string) $oc->total_cost, '完工合计');
        $this->assertBcEquals('7.00', (string) $oc->unit_cost, '单位成本');

        $voucher = $this->voucherOfOc((int) $oc->id);
        $this->assertNotNull($voucher, '结转凭证应存在');
        $lines = $this->voucherLines((int) $voucher->id);
        $this->assertSame(3, count($lines), '凭证应 3 行');
        $this->assertBcEquals('7.00', (string) $lines[1004]->debit_amount, '借 存货=实际合计');
        $this->assertSame('完工结转-存货/产成品(实际成本)', (string) $lines[1004]->summary);
        $this->assertBcEquals('5.00', (string) $lines[1001]->credit_amount, '贷 材料=标准');
        $this->assertSame('完工结转-材料(标准成本)', (string) $lines[1001]->summary);
        $this->assertBcEquals('2.00', (string) $lines[1005]->credit_amount, '贷 材料超用差异');
        $this->assertSame('完工结转-材料超用差异', (string) $lines[1005]->summary);
        $this->assertBcEquals('0', (string) ($lines[1005]->debit_amount ?? 0), '差异行应纯贷方');
    }

    /**
     * 场景 e-Y — 少领差异（节约，借 5 负差异绝对值）。
     *
     * BOM A←(B×1, C×3)。只领 B0.5@3.00，C 未领（不计标准）。
     * 实际 1.50；标准 = B1×3.00 = 3.00；差异 -1.50 → 借 5 1.50。
     */
    public function testVoucherWithMaterialSavingDiff(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $c = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '1', '3.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
            ['product_id' => $c['product_id'], 'quantity' => '3'],
        ]);
        $orderId = $this->createOrder($bomId, '1');
        $this->startOrder($orderId);
        $this->configureAccounts([1 => 1001, 4 => 1004, 5 => 1005]);

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '0.5'],
        ]);
        $this->auditIssue($issueId);
        $this->assertBcEquals('0.50', (string) $this->stockRow($b['product_id'], $b['sku_id'])->quantity, 'B 剩余');

        $this->costService()->completeWithCost($orderId, 1.0, self::WH_ID);

        $oc = $this->ocRow($orderId);
        $this->assertBcEquals('1.50', (string) $oc->actual_material_cost, '实际材料');
        $this->assertBcEquals('3.00', (string) $oc->standard_material_cost, '标准材料');
        $this->assertBcEquals('-1.50', (string) $oc->material_diff, '材料差异=-1.50');
        $this->assertBcEquals('1.50', (string) $oc->total_cost, '完工合计');

        $voucher = $this->voucherOfOc((int) $oc->id);
        $this->assertNotNull($voucher, '结转凭证应存在');
        $lines = $this->voucherLines((int) $voucher->id);
        $this->assertSame(3, count($lines), '凭证应 3 行');
        $this->assertBcEquals('1.50', (string) $lines[1004]->debit_amount, '借 存货=实际合计');
        $this->assertBcEquals('3.00', (string) $lines[1001]->credit_amount, '贷 材料=标准');
        $this->assertSame('完工结转-材料(标准成本)', (string) $lines[1001]->summary);
        $this->assertBcEquals('1.50', (string) $lines[1005]->debit_amount, '借 材料节约差异');
        $this->assertSame('完工结转-材料节约差异', (string) $lines[1005]->summary);
        $this->assertBcEquals('0', (string) ($lines[1005]->credit_amount ?? 0), '差异行应纯借方');
    }

    /**
     * 场景 f1 — 未开工（status=0）工单完工被拒，零副作用。
     *
     * 前置门槛全部通过（计划>0、仓库齐、无草稿单据、BOM 与启用 SKU
     * 均在、无 WIP），唯一不满足项为工单未在生产 → 完工结算拒绝。
     */
    public function testCompleteUnstartedOrderRejected(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->seedStock($b['product_id'], $b['sku_id'], '5', '2.00');
        $bomId = $this->createBom($a['product_id'], [
            ['product_id' => $b['product_id'], 'quantity' => '1'],
        ]);
        $orderId = $this->createOrder($bomId, '3');
        // 注意：不 startOrder，工单停留草稿

        $this->assertThrowsMessage(
            fn () => $this->costService()->completeWithCost($orderId, 3.0, self::WH_ID),
            '只有生产中的工单可以完工结算'
        );

        $order = $this->orderRow($orderId);
        $this->assertSame(0, (int) $order->status, '工单应保持待生产');
        $this->assertBcEquals('0.00', (string) $order->completed_quantity, '完工数应为 0');
        $this->assertRowCount('erp_mfg_order_cost', ['order_id' => $orderId], 0, '无成本单');
        $this->assertRowCount('erp_mfg_wip', ['order_id' => $orderId], 0, '无 WIP');
        $this->assertRowCount('erp_inventory_flow', ['product_id' => $a['product_id']], 0, '无入库流水');
        $this->assertRowCount(
            'erp_finance_voucher_source',
            ['source_type' => 'mfg_order_cost'],
            0,
            '无凭证来源'
        );
    }

    /**
     * 场景 f2 — 科目映射缺失：完工结算整单回滚。
     *
     * 已开工工单：领 B1@2.00 审核 + 人工 1.50 审核（WIP 3.50），
     * 科目映射（1001..1005）全部删除 → buildLines 抛缺少映射，
     * 入库/成本单/工单状态随事务回滚，WIP 台账完整保留。
     */
    public function testCompleteRollsBackWhenAccountConfigMissing(): void
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

        $issueId = $this->createIssue($orderId, [
            ['product_id' => $b['product_id'], 'sku_id' => $b['sku_id'], 'quantity' => '1'],
        ]);
        $this->auditIssue($issueId);
        $entryId = $this->createCostEntry($orderId, 1, '1.50');
        $this->auditCostEntry($entryId);

        // 清空测试域科目映射 → 规则抛缺少映射
        Capsule::table('erp_finance_cost_account_config')
            ->whereIn('account_id', array_values(self::TEST_ACCOUNT_IDS))
            ->delete();

        $this->assertThrowsMessage(
            fn () => $this->costService()->completeWithCost($orderId, 1.0, self::WH_ID),
            '缺少成本结转科目映射'
        );

        $order = $this->orderRow($orderId);
        $this->assertSame(1, (int) $order->status, '工单应回滚为生产中');
        $this->assertBcEquals('0.00', (string) $order->completed_quantity, '完工数应回滚为 0');
        $this->assertRowCount('erp_mfg_order_cost', ['order_id' => $orderId], 0, '成本单应回滚');
        $this->assertRowCount('erp_inventory_flow', ['product_id' => $a['product_id']], 0, '入库流水应回滚');
        $this->assertRowCount('erp_inventory', ['product_id' => $a['product_id']], 0, '库存行应回滚');
        $this->assertRowCount(
            'erp_finance_voucher_source',
            ['source_type' => 'mfg_order_cost'],
            0,
            '凭证来源应回滚'
        );

        // WIP 台账不受影响（归集已完成并保留）
        $wip = $this->wipRow($orderId);
        $this->assertNotNull($wip, 'WIP 应保留');
        $this->assertBcEquals('2.00', (string) $wip->material_cost, 'WIP 材料保留');
        $this->assertBcEquals('1.50', (string) $wip->labor_cost, 'WIP 人工保留');
        $this->assertBcEquals('3.50', (string) $wip->total_cost, 'WIP 合计保留');
        $this->assertSame(0, (int) $wip->status, 'WIP 应为在制');
    }
}
