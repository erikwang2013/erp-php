<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\manufacturing\SubcontractService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * M2 委外订单核销 集成测试（P1-M2）
 *
 * 依赖真实 MySQL（TEST_DB_*）：p1_m1m2.sql 表已应用。
 * 覆盖：发料审核移动加权快照出库与委外单联动（0→1 快照加工费）、
 * 库存不足整单回滚、分批收料（2 已收货）→ 二次发料 → 收满自动核销
 * （3 已核销，consumed_amount=核销时 issued_amount）、超收/未发料/
 * 已核销后收发的守卫、委外产品无启用 SKU 拒绝入库。
 * 造数走本类方法并登记合成 ID（scaffold tearDown 清理）。
 */
#[Group('integration')]
class P1M2SubcontractTest extends P1M1M2CostingScaffold
{
    /**
     * 发料审核：逐行按移动加权均价快照出库；委外单 0→1 并快照加工费 amount。
     */
    public function testIssueAuditStocksOutByAverageCostAndAdvancesSubcontract(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '100', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '20', '3.5');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);

        $this->subcontractService()->auditIssue($issueId);

        $issue = $this->subcontractIssueRow($issueId);
        $this->assertSame(1, (int) $issue->status, '发料单已审核');
        $this->assertBcEquals('25.00', (string) $issue->total_cost, '发料总额 10×2.50');
        $item = $this->subcontractIssueItemRows($issueId)[0];
        $this->assertBcEquals('2.50', (string) $item->unit_cost, '行成本快照=移动加权均价');
        $this->assertBcEquals('25.00', (string) $item->amount, '行金额');
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(1, (int) $sub->status, '委外单 0→1');
        $this->assertBcEquals('70.00', (string) $sub->amount, '首审快照加工费 20×3.50');
        $this->assertBcEquals('25.00', (string) $sub->issued_amount, '发料成本累计');
        $inv = $this->inventoryRow($mat['product_id'], $mat['sku_id']);
        $this->assertBcEquals('90', (string) $inv->quantity, '材料出库 100−10');
        $this->assertBcEquals('2.50', (string) $inv->cost_price, '出库不改移动加权均价');

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($issueId),
            '只有草稿状态的发料单可以审核'
        );
    }

    /**
     * 任一行库存不足：整单回滚拒绝（无部分出库，快照列全部还原）。
     */
    public function testIssueAuditInsufficientStockRollsBackWholeDocument(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '5', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '20', '3.5');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($issueId),
            '库存不足'
        );

        $issue = $this->subcontractIssueRow($issueId);
        $this->assertSame(0, (int) $issue->status, '发料单仍是草稿');
        $this->assertBcEquals('0', (string) $issue->total_cost, '总额未落库');
        $item = $this->subcontractIssueItemRows($issueId)[0];
        $this->assertBcEquals('0', (string) $item->unit_cost, '行成本快照还原');
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(0, (int) $sub->status, '委外单仍是草稿');
        $this->assertBcEquals('0', (string) $sub->amount, '加工费快照未写');
        $this->assertBcEquals('0', (string) $sub->issued_amount, '发料成本未累计');
        $inv = $this->inventoryRow($mat['product_id'], $mat['sku_id']);
        $this->assertBcEquals('5', (string) $inv->quantity, '库存原样');
    }

    /**
     * 分批收料：60 收 → 委外单 2 已收货；已收货状态仍可二次发料（补料）；
     * 再收 40 收满 → 3 已核销，consumed_amount = 核销时累计 issued_amount（两单合计）。
     */
    public function testPartialReceiveThenExtraIssueThenFullWriteOff(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '100', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '100', '3.2');

        $issue1 = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '40',
        ]]);
        $this->subcontractService()->auditIssue($issue1);
        $receive1 = $this->createSubcontractReceive($subcontractId, '60');
        $this->subcontractService()->auditReceive($receive1);

        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(2, (int) $sub->status, '未收满 → 2 已收货');
        $this->assertBcEquals('60.00', (string) $sub->received_qty, '收料累计');
        $this->assertBcEquals('0', (string) $sub->consumed_amount, '未核销无冲抵');
        $recv1 = $this->subcontractReceiveRow($receive1);
        $this->assertSame(1, (int) $recv1->status, '收料单已审核');
        $this->assertBcEquals('3.20', (string) $recv1->unit_price, '入库单价=加工单价快照');

        $issue2 = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '30',
        ]]);
        $this->subcontractService()->auditIssue($issue2);
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(2, (int) $sub->status, '已收货状态允许补料');
        $this->assertBcEquals('175.00', (string) $sub->issued_amount, '跨单累计 100+75');

        $receive2 = $this->createSubcontractReceive($subcontractId, '40');
        $this->subcontractService()->auditReceive($receive2);
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(3, (int) $sub->status, '收满自动核销');
        $this->assertBcEquals('100.00', (string) $sub->received_qty, '收料收满');
        $this->assertBcEquals('175.00', (string) $sub->consumed_amount, '核销冲抵=核销时 issued_amount');
        $inv = $this->inventoryRow($out['product_id'], $out['sku_id']);
        $this->assertBcEquals('100', (string) $inv->quantity, '委外件累计入库');
        $this->assertBcEquals('3.20', (string) $inv->cost_price, '入库加权=加工单价');

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($receive1),
            '只有草稿状态的收料单可以审核'
        );
    }

    /**
     * 超收拒绝：收料数量 > 剩余（quantity−received_qty）时整单拒绝，库存不动。
     */
    public function testOverReceiveRejectedBeforeStockMovement(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '10', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '10', '3');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);
        $this->subcontractService()->auditIssue($issueId);
        $receiveId = $this->createSubcontractReceive($subcontractId, '20');

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($receiveId),
            '收料数量超过委外单剩余数量'
        );

        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(1, (int) $sub->status, '委外单状态不变');
        $this->assertBcEquals('0', (string) $sub->received_qty, '未收料');
        $recv = $this->subcontractReceiveRow($receiveId);
        $this->assertSame(0, (int) $recv->status, '收料单仍草稿');
        $this->assertRowCount('erp_inventory', ['product_id' => $out['product_id'], 'sku_id' => $out['sku_id'], 'warehouse_id' => self::WH_ID], 0, '无委外件入库行');
    }

    /**
     * 状态机守卫：未发料不能收料；已核销后禁止再发料/再收料。
     */
    public function testStateGuardsAroundIssueAndReceive(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '100', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();

        // 草稿委外单收料被拒
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '10', '3');
        $draftReceive = $this->createSubcontractReceive($subcontractId, '5');
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($draftReceive),
            '委外订单尚未发料，不能收料'
        );

        // 收满核销后：再发料/再收料均被拒
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);
        $this->subcontractService()->auditIssue($issueId);
        $receiveId = $this->createSubcontractReceive($subcontractId, '10');
        $this->subcontractService()->auditReceive($receiveId);
        $this->assertSame(3, (int) $this->subcontractRow($subcontractId)->status, '已核销');

        $lateIssue = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '1',
        ]]);
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($lateIssue),
            '委外订单已核销，禁止发料'
        );
        $lateReceive = $this->createSubcontractReceive($subcontractId, '1');
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($lateReceive),
            '委外订单已核销，禁止收料'
        );
    }

    /**
     * 委外产品无启用 SKU：收料审核拒绝入库（金额/库存均不动）。
     */
    public function testReceiveRejectedWhenSubcontractProductHasNoEnabledSku(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '10', '2.5');
        // 委外产品仅建停用 SKU（status=0），不入库域
        $productId = $this->nextId();
        $disabledSkuId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_product_sku')->insert([
            'id' => $disabledSkuId,
            'product_id' => $productId,
            'sku_code' => 'SKU-D-' . $disabledSkuId,
            'barcode' => '',
            'cost_price' => '0',
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->productIds[] = $productId;
        $this->skuIds[] = $disabledSkuId;

        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $productId, '10', '3');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);
        $this->subcontractService()->auditIssue($issueId);
        $receiveId = $this->createSubcontractReceive($subcontractId, '10');

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($receiveId),
            '委外产品无启用SKU'
        );

        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(1, (int) $sub->status, '委外单停在已发料');
        $this->assertBcEquals('0', (string) $sub->received_qty, '未收料');
        $this->assertRowCount('erp_inventory', ['product_id' => $productId], 0, '无入库行');
    }

    // ---------- 造数与只读 ----------

    /** 草稿委外单（amount/issued_amount/received_qty/consumed_amount 零快照） */
    protected function createSubcontract(int $supplierId, int $productId, string $qty, string $unitPrice): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_subcontract')->insert([
            'id' => $id,
            'code' => 'SUB-' . $id,
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'warehouse_id' => self::WH_ID,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'amount' => '0.00',
            'issued_amount' => '0.00',
            'received_qty' => '0.00',
            'consumed_amount' => '0.00',
            'status' => 0,
            'audit_at' => null,
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->subcontractIds[] = $id;

        return $id;
    }

    /** 草稿发料单（明细 unit_cost/amount 零快照）；items: [{product_id, sku_id, quantity}] */
    protected function createSubcontractIssue(int $subcontractId, array $items): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_subcontract_issue')->insert([
            'id' => $id,
            'code' => 'SCI-' . $id,
            'subcontract_id' => $subcontractId,
            'warehouse_id' => self::WH_ID,
            'issue_date' => '2026-08-20',
            'total_cost' => '0.00',
            'status' => 0,
            'audit_at' => null,
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        foreach ($items as $row) {
            Capsule::table('erp_mfg_subcontract_issue_item')->insert([
                'id' => $this->nextId(),
                'issue_id' => $id,
                'product_id' => $row['product_id'],
                'sku_id' => $row['sku_id'],
                'quantity' => $row['quantity'],
                'unit_cost' => '0.00',
                'amount' => '0.00',
                'created_at' => $now,
            ]);
        }
        $this->subcontractIssueIds[] = $id;

        return $id;
    }

    /** 草稿收料单（unit_price 零快照，审核时落加工单价） */
    protected function createSubcontractReceive(int $subcontractId, string $qty): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_subcontract_receive')->insert([
            'id' => $id,
            'code' => 'SCR-' . $id,
            'subcontract_id' => $subcontractId,
            'warehouse_id' => self::WH_ID,
            'receive_date' => '2026-08-25',
            'quantity' => $qty,
            'unit_price' => '0.00',
            'status' => 0,
            'audit_at' => null,
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->subcontractReceiveIds[] = $id;

        return $id;
    }

    protected function subcontractRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract')->where('id', $id)->first();
    }

    protected function subcontractIssueRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract_issue')->where('id', $id)->first();
    }

    protected function subcontractIssueItemRows(int $issueId): array
    {
        return array_values(Capsule::table('erp_mfg_subcontract_issue_item')->where('issue_id', $issueId)->orderBy('id')->get()->all());
    }

    protected function subcontractReceiveRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract_receive')->where('id', $id)->first();
    }

    protected function inventoryRow(int $productId, int $skuId): ?object
    {
        return Capsule::table('erp_inventory')
            ->where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->where('warehouse_id', self::WH_ID)
            ->where('location_id', 0)
            ->where('batch_code', '')
            ->first();
    }

    protected function subcontractService(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
