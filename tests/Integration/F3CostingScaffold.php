<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\inventory\InventoryService;
use app\service\manufacturing\ManufacturingService;
use app\service\manufacturing\MfgCostService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use support\Container;
use Throwable;

/**
 * F3 成本核算集成测试共享脚手架（抽象基类，本身不执行）。
 *
 * 表处理双模式（同 FinanceTransactionIntegrationTest）：F3 8 张自有表
 * （p0_f3.sql）缺表时最小化创建并 tearDown 删除，预存在仅清理本类写入行；
 * 依赖表（install.sql）从不创建，缺表或缺 erp_finance_voucher.ledger_id
 * 列（p0_f1f2.sql ALTER）时整类跳过。
 * 金额断言一律 bccomp/bc_norm（raw Capsule 读 PDO DECIMAL→string）。
 */
abstract class F3CostingScaffold extends IntegrationTestCase
{
    /** 共用仓库：领料出库与完工入库同仓（服务端无跨仓约束，测试固定单仓） */
    protected const WH_ID = 9001;
    /** 成本结转科目测试映射：1材料 2人工 3制费 4存货/产成品 5材料差异 */
    protected const TEST_ACCOUNT_IDS = [1 => 1001, 2 => 1002, 3 => 1003, 4 => 1004, 5 => 1005];
    /** F3 自有表（p0_f3.sql）— 缺表时最小化创建 */
    protected const F3_TABLES = [
        'erp_mfg_material_issue',
        'erp_mfg_material_issue_item',
        'erp_mfg_cost_entry',
        'erp_mfg_wip',
        'erp_mfg_wip_flow',
        'erp_mfg_order_cost',
        'erp_finance_voucher_source',
        'erp_finance_cost_account_config',
    ];
    /** 依赖表（install.sql / p0_f1f2.sql 域）— 只读使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_mfg_bom',
        'erp_mfg_bom_item',
        'erp_mfg_production_order',
        'erp_product_sku',
        'erp_inventory',
        'erp_inventory_flow',
        'erp_cost_record',
        'erp_finance_voucher',
        'erp_finance_voucher_item',
    ];

    protected array $createdTables = [];
    protected array $orderIds = [];
    protected array $issueIds = [];
    protected array $entryIds = [];
    protected array $bomIds = [];
    protected array $productIds = [];
    protected array $skuIds = [];
    protected array $voucherIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->orderIds = $this->issueIds = $this->entryIds = $this->bomIds = $this->productIds = $this->skuIds = $this->voucherIds = [];
        // F3 自有表先补齐（模型写入要求列存在），随后依赖表门槛整类跳过
        foreach (self::F3_TABLES as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                $this->createF3Table($table);
            }
        }
        $missing = array_values(array_filter(self::DEP_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missing !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missing) . '（请先导入 install.sql）');
        }
        if (!Capsule::schema()->hasColumn('erp_finance_voucher', 'ledger_id')) {
            self::markTestSkipped('erp_finance_voucher 缺少 ledger_id 列（依赖 database/p0_f1f2.sql 重放）');
        }
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            foreach (array_reverse($this->createdTables) as $table) {
                try {
                    $this->dropTableIfExists($table);
                } catch (Throwable) {
                }
            }
            $cleanup = [
                'erp_mfg_wip_flow' => ['order_id', $this->orderIds],
                'erp_mfg_wip' => ['order_id', $this->orderIds],
                'erp_mfg_order_cost' => ['order_id', $this->orderIds],
                'erp_mfg_material_issue_item' => ['issue_id', $this->issueIds],
                'erp_mfg_material_issue' => ['id', $this->issueIds],
                'erp_mfg_cost_entry' => ['id', $this->entryIds],
                'erp_finance_voucher_item' => ['voucher_id', $this->voucherIds],
                'erp_finance_voucher_source' => ['voucher_id', $this->voucherIds],
                'erp_finance_voucher' => ['id', $this->voucherIds],
                'erp_inventory_flow' => ['product_id', $this->productIds],
                'erp_cost_record' => ['product_id', $this->productIds],
                'erp_inventory' => ['product_id', $this->productIds],
                'erp_product_sku' => ['product_id', $this->productIds],
                'erp_mfg_bom_item' => ['bom_id', $this->bomIds],
                'erp_mfg_bom' => ['id', $this->bomIds],
                'erp_finance_cost_account_config' => ['account_id', array_values(self::TEST_ACCOUNT_IDS)],
            ];
            foreach ($cleanup as $table => [$column, $ids]) {
                if ($ids === []) {
                    continue;
                }
                try {
                    Capsule::table($table)->whereIn($column, $ids)->delete();
                } catch (Throwable) {
                }
            }
        }
        parent::tearDown();
    }

    /** 缺表时按 p0_f3.sql 列结构最小化创建（创建路径全部列可空，服务层显式赋值） */
    private function createF3Table(string $table): void
    {
        $this->createTableIfMissing($table, static function (Blueprint $t) use ($table): void {
            $t->unsignedBigInteger('id')->primary();
            if ($table === 'erp_mfg_material_issue') {
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->date('issue_date')->nullable();
                $t->tinyInteger('status')->nullable();
                $t->decimal('total_cost', 14, 2)->nullable();
                $t->dateTime('audit_at')->nullable();
                $t->string('remark', 500)->nullable();
            } elseif ($table === 'erp_mfg_material_issue_item') {
                $t->unsignedBigInteger('issue_id')->nullable();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('sku_id')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('unit_cost', 12, 2)->nullable();
                $t->decimal('amount', 14, 2)->nullable();
            } elseif ($table === 'erp_mfg_cost_entry') {
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->tinyInteger('entry_type')->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $t->date('entry_date')->nullable();
                $t->tinyInteger('status')->nullable();
                $t->dateTime('audit_at')->nullable();
                $t->string('summary', 500)->nullable();
            } elseif ($table === 'erp_mfg_wip') {
                $t->unsignedBigInteger('order_id')->nullable();
                $t->decimal('material_cost', 14, 2)->nullable();
                $t->decimal('labor_cost', 14, 2)->nullable();
                $t->decimal('overhead_cost', 14, 2)->nullable();
                $t->decimal('other_cost', 14, 2)->nullable();
                $t->decimal('total_cost', 14, 2)->nullable();
                $t->tinyInteger('status')->nullable();
            } elseif ($table === 'erp_mfg_wip_flow') {
                $t->unsignedBigInteger('wip_id')->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->tinyInteger('source_type')->nullable();
                $t->bigInteger('source_id')->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $t->tinyInteger('direction')->nullable();
                $t->date('flow_date')->nullable();
            } elseif ($table === 'erp_mfg_order_cost') {
                $t->unsignedBigInteger('order_id')->nullable();
                $t->decimal('finished_qty', 12, 2)->nullable();
                $t->decimal('standard_material_cost', 14, 2)->nullable();
                $t->decimal('actual_material_cost', 14, 2)->nullable();
                $t->decimal('labor_cost', 14, 2)->nullable();
                $t->decimal('overhead_cost', 14, 2)->nullable();
                $t->decimal('other_cost', 14, 2)->nullable();
                $t->decimal('material_diff', 14, 2)->nullable();
                $t->decimal('total_cost', 14, 2)->nullable();
                $t->decimal('unit_cost', 14, 2)->nullable();
                $t->unsignedBigInteger('voucher_id')->nullable();
                $t->tinyInteger('status')->nullable();
            } elseif ($table === 'erp_finance_voucher_source') {
                $t->unsignedBigInteger('voucher_id')->nullable();
                $t->string('source_type', 30)->nullable();
                $t->bigInteger('source_id')->nullable();
            } else { // erp_finance_cost_account_config
                $t->tinyInteger('cost_type')->nullable();
                $t->unsignedBigInteger('account_id')->nullable();
                $t->tinyInteger('status')->nullable();
            }
            $t->dateTime('created_at')->nullable();
            if (!in_array($table, ['erp_mfg_material_issue_item', 'erp_mfg_wip_flow', 'erp_finance_voucher_source'], true)) {
                $t->dateTime('updated_at')->nullable();
            }
            if ($table === 'erp_mfg_material_issue' || $table === 'erp_mfg_cost_entry') {
                $t->dateTime('deleted_at')->nullable();
            }
        });
        $this->createdTables[] = $table;
    }

    // ---------- 只读查询 ----------

    protected function stockRow(int $productId, int $skuId): ?object
    {
        return Capsule::table('erp_inventory')
            ->where('product_id', $productId)->where('sku_id', $skuId)
            ->where('warehouse_id', self::WH_ID)->where('location_id', 0)
            ->where('batch_code', '')->first();
    }

    protected function orderRow(int $orderId): ?object
    {
        return Capsule::table('erp_mfg_production_order')->where('id', $orderId)->first();
    }

    protected function wipRow(int $orderId): ?object
    {
        return Capsule::table('erp_mfg_wip')->where('order_id', $orderId)->first();
    }

    protected function ocRow(int $orderId): ?object
    {
        return Capsule::table('erp_mfg_order_cost')->where('order_id', $orderId)->first();
    }

    /** 完工结转凭证（含行），并登记 voucherIds 供 tearDown 清理 */
    protected function voucherOfOc(int $ocId): ?object
    {
        $oc = Capsule::table('erp_mfg_order_cost')->where('id', $ocId)->first();
        if (!$oc || (int) $oc->voucher_id <= 0) {
            return null;
        }
        $vid = (int) $oc->voucher_id;
        $this->voucherIds[] = $vid;

        return Capsule::table('erp_finance_voucher')->where('id', $vid)->first();
    }

    /** 凭证行按 account_id 建索引（顺序无关断言）：account_id => [row] */
    protected function voucherLines(int $voucherId): array
    {
        $this->voucherIds[] = $voucherId;
        $map = [];
        foreach (Capsule::table('erp_finance_voucher_item')->where('voucher_id', $voucherId)->get() as $row) {
            $map[(int) $row->account_id] = $row;
        }

        return $map;
    }

    // ---------- 造数 ----------

    protected function nextId(): int
    {
        return (int) SnowflakeService::generate();
    }

    /** 建产品 + 唯一启用 SKU（erp_product_sku 无 product 主档要求） */
    protected function createProduct(): array
    {
        $productId = $this->nextId();
        $skuId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_product_sku')->insert([
            'id' => $skuId,
            'product_id' => $productId,
            'sku_code' => 'SKU-' . $skuId,
            'barcode' => '',
            'cost_price' => '0',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->productIds[] = $productId;
        $this->skuIds[] = $skuId;

        return ['product_id' => $productId, 'sku_id' => $skuId];
    }

    /** 初始库存：走 InventoryService::stockIn（同口径移动加权），source 本类专用 */
    protected function seedStock(int $productId, int $skuId, string $qty, string $unitCost): void
    {
        $this->inventoryService()->stockIn(
            $productId,
            $skuId,
            self::WH_ID,
            0,
            '',
            (float) $qty,
            (float) $unitCost,
            'f3_test_seed',
            $this->nextId()
        );
    }

    /** BOM（status=1）+ 明细 [(product_id, quantity), ...] */
    protected function createBom(int $productId, array $components): int
    {
        $bomId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_bom')->insert([
            'id' => $bomId,
            'product_id' => $productId,
            'code' => 'BOM-' . $bomId,
            'name' => 'F3测试BOM',
            'version' => '1.0',
            'status' => 1,
            'effective_date' => date('Y-m-d'),
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        foreach ($components as $row) {
            Capsule::table('erp_mfg_bom_item')->insert([
                'id' => $this->nextId(),
                'bom_id' => $bomId,
                'component_product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'unit' => '',
                'scrap_rate' => '0.00',
                'seq' => 0,
                'created_at' => $now,
            ]);
        }
        $this->bomIds[] = $bomId;

        return $bomId;
    }

    /** 生产工单（status=0 草稿态），planned 为计划数量 */
    protected function createOrder(int $bomId, string $planned = '3'): int
    {
        $orderId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_production_order')->insert([
            'id' => $orderId,
            'code' => 'PO-' . $orderId,
            'bom_id' => $bomId,
            'warehouse_id' => self::WH_ID,
            'planned_quantity' => $planned,
            'completed_quantity' => '0.00',
            'status' => 0,
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->orderIds[] = $orderId;

        return $orderId;
    }

    protected function startOrder(int $orderId): void
    {
        $order = $this->mfgService()->startProduction($orderId);
        if (!$order) {
            self::fail('工单不存在，无法开始生产: ' . $orderId);
        }
    }

    /** 草稿领料单（行：[product_id, sku_id, quantity]） */
    protected function createIssue(int $orderId, array $lines): int
    {
        $issueId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_material_issue')->insert([
            'id' => $issueId,
            'code' => 'MI-' . $issueId,
            'order_id' => $orderId,
            'warehouse_id' => self::WH_ID,
            'issue_date' => date('Y-m-d'),
            'status' => 0,
            'total_cost' => '0',
            'remark' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        foreach ($lines as $row) {
            Capsule::table('erp_mfg_material_issue_item')->insert([
                'id' => $this->nextId(),
                'issue_id' => $issueId,
                'product_id' => $row['product_id'],
                'sku_id' => $row['sku_id'],
                'quantity' => $row['quantity'],
                'unit_cost' => '0',
                'amount' => '0',
                'created_at' => $now,
            ]);
        }
        $this->issueIds[] = $issueId;

        return $issueId;
    }

    protected function auditIssue(int $issueId): void
    {
        $this->costService()->auditIssue($issueId);
    }

    /** 草稿费用归集单；type 1人工/2制费/3其他 */
    protected function createCostEntry(int $orderId, int $type, string $amount): int
    {
        $entryId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_cost_entry')->insert([
            'id' => $entryId,
            'code' => 'CE-' . $entryId,
            'order_id' => $orderId,
            'entry_type' => $type,
            'amount' => $amount,
            'entry_date' => date('Y-m-d'),
            'status' => 0,
            'audit_at' => null,
            'summary' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $this->entryIds[] = $entryId;

        return $entryId;
    }

    protected function auditCostEntry(int $entryId): void
    {
        $this->costService()->auditCostEntry($entryId);
    }

    /** 科目映射整表测试域重设（cost_type → account_id，status=1） */
    protected function configureAccounts(array $map): void
    {
        Capsule::table('erp_finance_cost_account_config')
            ->whereIn('account_id', array_values(self::TEST_ACCOUNT_IDS))
            ->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($map as $costType => $accountId) {
            Capsule::table('erp_finance_cost_account_config')->insert([
                'id' => $this->nextId(),
                'cost_type' => $costType,
                'account_id' => $accountId,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** 断言闭包抛异常且消息包含指定片段（异常类不做耦合断言） */
    protected function assertThrowsMessage(callable $fn, string $needle): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }
        self::fail('预期异常未抛出，期望消息包含: ' . $needle);
    }

    protected function assertBcEquals(string $expected, string $actual, string $label = ''): void
    {
        $this->assertSame(0, bccomp(bc_norm($expected), bc_norm($actual), 6), $label
            . sprintf(' 期望=%s 实际=%s', bc_norm($expected), bc_norm($actual)));
    }

    protected function assertRowCount(string $table, array $where, int $expected, string $label = ''): void
    {
        $count = Capsule::table($table)->where($where)->count();
        $this->assertSame($expected, $count, $label . sprintf(' 期望行数=%d 实际=%d', $expected, $count));
    }

    // ---------- 服务入口（Container 解析，与控制器同路径） ----------

    protected function costService(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }

    protected function inventoryService(): InventoryService
    {
        return Container::get(InventoryService::class);
    }

    protected function mfgService(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
    }
}
