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
 * M2 委外订单核销 独立验证（P1-M2，与 coder P1M2SubcontractTest 互补）
 *
 * 覆盖 coder 套件之外的守卫面与边界：DB 直写负/零数量行绕过控制器时的
 * 服务层拒绝与整单回滚（多行单中首行已出库、次行非法）、发料/收料/委外单
 * 悬空引用（单据不存在）、从未入库的材料行（无库存行）整单回滚、加工费
 * amount 快照仅首审一次（补料不重写）、分位金额快照（数量×单价进位到分）、
 * 收料按加工单价快照入库与收满自动核销 consumed_amount 快照。
 *
 * 造数方法为本类私有（子类化 scaffold，自建 M2 单据并登记合成 ID），
 * 与 coder 用例类各自独立、互不依赖。
 */
#[Group('integration')]
class P1M1M2SubcontractStateTest extends P1M1M2CostingScaffold
{
    /**
     * 服务层数量守卫：多行发料单首行合法已出库、次行数量为负 →
     * 整单回滚拒绝，首行出库/快照全部还原（无部分出库）。
     */
    public function testIssueAuditRejectsNegativeItemQuantityWithWholeRollback(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '10', '2.5');
        // uk_issue_sku 唯一键：单张发料单同一 SKU 只允许一行，第二行须为另一材料
        $mat2 = $this->createProduct();
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '10', '3');
        $issueId = $this->createSubcontractIssue($subcontractId, [
            ['product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10'],
            ['product_id' => $mat2['product_id'], 'sku_id' => $mat2['sku_id'], 'quantity' => '-5'],
        ]);

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($issueId),
            '发料数量必须大于0'
        );

        $issue = $this->subcontractIssueRow($issueId);
        $this->assertSame(0, (int) $issue->status, '发料单整单回滚为草稿');
        $this->assertBcEquals('0', (string) $issue->total_cost, '总额未落库');
        foreach ($this->subcontractIssueItemRows($issueId) as $item) {
            $this->assertBcEquals('0', (string) $item->unit_cost, '行成本快照还原');
            $this->assertBcEquals('0', (string) $item->amount, '行金额还原');
        }
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(0, (int) $sub->status, '委外单未推进');
        $this->assertBcEquals('0', (string) $sub->issued_amount, '发料成本未累计');
        $inv = $this->inventoryRow($mat['product_id'], $mat['sku_id']);
        $this->assertBcEquals('10', (string) $inv->quantity, '首行出库被回滚，库存原样');
        // seedStock 本身写 1 条入库流水 → 基线=1，多出即为未回滚的出库流水
        $this->assertRowCount('erp_inventory_flow', ['product_id' => $mat['product_id']], 1, '无残留出库流水');
    }

    /**
     * 服务层数量守卫：收料单数量为负（DB 直写绕过控制器）→ 拒绝，单据保持草稿，
     * 委外件无任何入库痕迹。
     */
    public function testReceiveAuditRejectsNonPositiveQuantity(): void
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
        $receiveId = $this->createSubcontractReceive($subcontractId, '-2');

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($receiveId),
            '收料数量必须大于0'
        );

        $recv = $this->subcontractReceiveRow($receiveId);
        $this->assertSame(0, (int) $recv->status, '收料单保持草稿');
        $this->assertBcEquals('0', (string) $recv->unit_price, '入库单价未快照');
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(1, (int) $sub->status, '委外单停在已发料');
        $this->assertBcEquals('0', (string) $sub->received_qty, '未收料');
        $this->assertRowCount('erp_inventory', ['product_id' => $out['product_id'], 'sku_id' => $out['sku_id']], 0, '无委外件入库行');
    }

    /**
     * 悬空引用拒绝：单据本身不存在 / 明细挂不存在的委外单 → 服务层明确报错。
     */
    public function testMissingDocumentReferencesRejected(): void
    {
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue(99110011),
            '发料单不存在'
        );
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive(99110012),
            '收料单不存在'
        );

        // 发料单存在但其 subcontract_id 悬空
        $danglingIssue = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_subcontract_issue')->insert([
            'id' => $danglingIssue,
            'code' => 'SCI-D' . $danglingIssue,
            'subcontract_id' => 99110013,
            'warehouse_id' => self::WH_ID,
            'issue_date' => '2026-08-20',
            'total_cost' => '0.00',
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->subcontractIssueIds[] = $danglingIssue;
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($danglingIssue),
            '委外订单不存在'
        );

        // 收料单存在但其 subcontract_id 悬空
        $danglingReceive = $this->nextId();
        Capsule::table('erp_mfg_subcontract_receive')->insert([
            'id' => $danglingReceive,
            'code' => 'SCR-D' . $danglingReceive,
            'subcontract_id' => 99110014,
            'warehouse_id' => self::WH_ID,
            'receive_date' => '2026-08-25',
            'quantity' => '5',
            'unit_price' => '0.00',
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->subcontractReceiveIds[] = $danglingReceive;
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditReceive($danglingReceive),
            '委外订单不存在'
        );
    }

    /**
     * 从未入库（无库存行）的材料引用：发料审核拒绝，单据与快照列全部还原。
     */
    public function testIssueOnNeverStockedMaterialRejectedWithRollback(): void
    {
        $mat = $this->createProduct(); // 建产品但不 seedStock → 无 erp_inventory 行
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '10', '3');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);

        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($issueId),
            '库存不足'
        );

        $issue = $this->subcontractIssueRow($issueId);
        $this->assertSame(0, (int) $issue->status, '发料单仍草稿');
        $item = $this->subcontractIssueItemRows($issueId)[0];
        $this->assertBcEquals('0', (string) $item->unit_cost, '行成本快照还原');
        $this->assertBcEquals('0', (string) $item->amount, '行金额还原');
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(0, (int) $sub->status, '委外单未推进');
        $this->assertRowCount('erp_inventory_flow', ['product_id' => $mat['product_id']], 0, '无出库流水');
    }

    /**
     * 加工费 amount 快照仅首审一次：已发料状态（status=1）二次发料（补料）只累计
     * issued_amount，不重写 amount；委外单状态保持 1（未收料不跳 2）。
     */
    public function testAmountSnapshotFrozenAcrossReplenishIssues(): void
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

        $sub = $this->subcontractRow($subcontractId);
        $this->assertBcEquals('320.00', (string) $sub->amount, '首审快照加工费 100×3.20');
        $this->assertSame(1, (int) $sub->status, '0→1');

        // 已发料状态直接补料（未收料不跳 2），amount 不得重写
        $issue2 = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '30',
        ]]);
        $this->subcontractService()->auditIssue($issue2);

        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(1, (int) $sub->status, '未收料前补料不推进状态');
        $this->assertBcEquals('320.00', (string) $sub->amount, 'amount 快照不重写');
        $this->assertBcEquals('175.00', (string) $sub->issued_amount, '跨单累计发料成本 100+75');
    }

    /**
     * 分位快照：数量×单价进位到分（33.33×3.33=110.9889→110.99）；收料单按加工
     * 单价快照入库；一次收满自动核销 status 3 + consumed_amount=issued_amount。
     */
    public function testFractionalAmountSnapshotAndFullReceiveWriteOff(): void
    {
        $mat = $this->createProduct();
        $this->seedStock($mat['product_id'], $mat['sku_id'], '10', '2.5');
        $out = $this->createProduct();
        $supplierId = $this->createSupplier();
        $subcontractId = $this->createSubcontract($supplierId, $out['product_id'], '33.33', '3.33');
        $issueId = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '10',
        ]]);
        $this->subcontractService()->auditIssue($issueId);
        $sub = $this->subcontractRow($subcontractId);
        $this->assertBcEquals('110.99', (string) $sub->amount, '33.33×3.33=110.9889 → 110.99');
        $this->assertBcEquals('25.00', (string) $sub->issued_amount, '发料成本 10×2.50');

        $receiveId = $this->createSubcontractReceive($subcontractId, '33.33');
        $this->subcontractService()->auditReceive($receiveId);

        $recv = $this->subcontractReceiveRow($receiveId);
        $this->assertSame(1, (int) $recv->status, '收料单已审核');
        $this->assertBcEquals('3.33', (string) $recv->unit_price, '入库单价快照=加工单价');
        $sub = $this->subcontractRow($subcontractId);
        $this->assertSame(3, (int) $sub->status, '一次收满自动核销');
        $this->assertBcEquals('33.33', (string) $sub->received_qty, '收料收满');
        $this->assertBcEquals('25.00', (string) $sub->consumed_amount, '核销冲抵=核销时 issued_amount');
        $inv = $this->inventoryRow($out['product_id'], $out['sku_id']);
        $this->assertBcEquals('33.33', (string) $inv->quantity, '委外件入库数量');
        $this->assertBcEquals('3.33', (string) $inv->cost_price, '入库加权成本=加工单价');

        // 核销后补发料行依然被拒（已核销不可逆，consumed_amount 为终态）
        $lateIssue = $this->createSubcontractIssue($subcontractId, [[
            'product_id' => $mat['product_id'], 'sku_id' => $mat['sku_id'], 'quantity' => '1',
        ]]);
        $this->assertThrowsMessage(
            fn () => $this->subcontractService()->auditIssue($lateIssue),
            '委外订单已核销，禁止发料'
        );
        $this->assertBcEquals('25.00', (string) $this->subcontractRow($subcontractId)->consumed_amount, '核销快照不变');
    }

    // ---------- 造数与只读（自包含：子类化 scaffold 但 M2 单据 helper 不入 scaffold） ----------

    /** 草稿委外单（amount/issued_amount/received_qty/consumed_amount 零快照） */
    private function createSubcontract(int $supplierId, int $productId, string $qty, string $unitPrice): int
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
    private function createSubcontractIssue(int $subcontractId, array $items): int
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
    private function createSubcontractReceive(int $subcontractId, string $qty): int
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

    private function subcontractRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract')->where('id', $id)->first();
    }

    private function subcontractIssueRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract_issue')->where('id', $id)->first();
    }

    private function subcontractIssueItemRows(int $issueId): array
    {
        return array_values(Capsule::table('erp_mfg_subcontract_issue_item')->where('issue_id', $issueId)->orderBy('id')->get()->all());
    }

    private function subcontractReceiveRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_subcontract_receive')->where('id', $id)->first();
    }

    private function inventoryRow(int $productId, int $skuId): ?object
    {
        return Capsule::table('erp_inventory')
            ->where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->where('warehouse_id', self::WH_ID)
            ->where('location_id', 0)
            ->where('batch_code', '')
            ->first();
    }

    private function subcontractService(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
