<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\hr\HrService;
use app\service\inventory\InventoryService;
use app\service\manufacturing\ManufacturingService;
use app\service\manufacturing\MfgCostService;
use app\service\manufacturing\PieceWageService;
use app\service\manufacturing\WorkReportService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use support\Container;
use Throwable;

/**
 * P1 M1 工序报工/计件工资 + M2 委外核销 集成测试共享脚手架（抽象基类）。
 *
 * 表处理双模式（同 F3CostingScaffold）：P1 6 张自有表（database/p1_m1m2.sql）
 * 缺表时最小化创建并 tearDown 删除，预存在仅清理本类写入行；依赖表
 * （install.sql）从不创建，缺表或缺 erp_mfg_routing.piece_rate /
 * erp_hr_salary.piece_wage 列（p1_m1m2.sql ALTER）时整类跳过。
 * 金额断言一律 bccomp/bc_norm（raw Capsule 读 PDO DECIMAL→string）。
 * 造数一律走本类方法并登记（只清本类合成 ID，绝不触碰真实数据）；
 * 合成 ID 避开 F3 测试域 9001-9099（WH_ID=9101，其余雪花随机）。
 */
abstract class P1M1M2CostingScaffold extends IntegrationTestCase
{
    /** 共用仓库：领料出库/完工入库/委外收发料同仓（服务端无跨仓约束，测试固定单仓） */
    protected const WH_ID = 9101;
    /** P1 自有表（p1_m1m2.sql）— 缺表时最小化创建 */
    protected const P1_TABLES = [
        'erp_mfg_work_report',
        'erp_mfg_piece_wage',
        'erp_mfg_subcontract',
        'erp_mfg_subcontract_issue',
        'erp_mfg_subcontract_issue_item',
        'erp_mfg_subcontract_receive',
    ];
    /** 依赖表（install.sql）— 只读使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_mfg_bom',
        'erp_mfg_bom_item',
        'erp_mfg_production_order',
        'erp_mfg_routing',
        'erp_mfg_workstation',
        'erp_mfg_wip',
        'erp_mfg_wip_flow',
        'erp_product_sku',
        'erp_inventory',
        'erp_inventory_flow',
        'erp_hr_employee',
        'erp_hr_salary',
        'erp_supplier',
    ];

    protected array $createdTables = [];
    protected array $orderIds = [];
    protected array $bomIds = [];
    protected array $productIds = [];
    protected array $skuIds = [];
    protected array $employeeIds = [];
    protected array $supplierIds = [];
    protected array $routingIds = [];
    protected array $workstationIds = [];
    protected array $workReportIds = [];
    protected array $subcontractIds = [];
    protected array $subcontractIssueIds = [];
    protected array $subcontractReceiveIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->orderIds = $this->bomIds = $this->productIds = $this->skuIds
            = $this->employeeIds = $this->supplierIds = $this->routingIds = $this->workstationIds
            = $this->workReportIds = $this->subcontractIds = $this->subcontractIssueIds
            = $this->subcontractReceiveIds = [];
        // P1 自有表先补齐（模型写入要求列存在），随后依赖表门槛整类跳过
        foreach (self::P1_TABLES as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                $this->createP1Table($table);
            }
        }
        $missing = array_values(array_filter(self::DEP_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missing !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missing) . '（请先导入 install.sql）');
        }
        if (!Capsule::schema()->hasColumn('erp_mfg_routing', 'piece_rate') || !Capsule::schema()->hasColumn('erp_hr_salary', 'piece_wage')) {
            self::markTestSkipped('缺少 P1 ALTER 列（依赖 database/p1_m1m2.sql 重放）');
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
                'erp_mfg_work_report' => ['id', $this->workReportIds],
                'erp_mfg_wip_flow' => ['order_id', $this->orderIds],
                'erp_mfg_wip' => ['order_id', $this->orderIds],
                'erp_mfg_subcontract_issue_item' => ['issue_id', $this->subcontractIssueIds],
                'erp_mfg_subcontract_issue' => ['id', $this->subcontractIssueIds],
                'erp_mfg_subcontract_receive' => ['id', $this->subcontractReceiveIds],
                'erp_mfg_subcontract' => ['id', $this->subcontractIds],
                'erp_mfg_piece_wage' => ['employee_id', $this->employeeIds],
                'erp_hr_salary' => ['employee_id', $this->employeeIds],
                'erp_inventory_flow' => ['product_id', $this->productIds],
                'erp_inventory' => ['product_id', $this->productIds],
                'erp_product_sku' => ['product_id', $this->productIds],
                'erp_mfg_bom_item' => ['bom_id', $this->bomIds],
                'erp_mfg_bom' => ['id', $this->bomIds],
                'erp_mfg_routing' => ['id', $this->routingIds],
                'erp_mfg_workstation' => ['id', $this->workstationIds],
                'erp_hr_employee' => ['id', $this->employeeIds],
                'erp_supplier' => ['id', $this->supplierIds],
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

    /** 缺表时按 p1_m1m2.sql 列结构最小化创建（创建路径全部列可空，服务层显式赋值） */
    private function createP1Table(string $table): void
    {
        $this->createTableIfMissing($table, static function (Blueprint $t) use ($table): void {
            $t->unsignedBigInteger('id')->primary();
            $hasRemark = true;
            if ($table === 'erp_mfg_work_report') {
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('order_id')->nullable();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('routing_id')->nullable();
                $t->unsignedBigInteger('workstation_id')->nullable();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->date('report_date')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('qualified_qty', 12, 2)->nullable();
                $t->decimal('piece_rate', 12, 2)->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $t->tinyInteger('status')->nullable();
                $t->dateTime('audit_at')->nullable();
            } elseif ($table === 'erp_mfg_piece_wage') {
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->integer('period_year')->nullable();
                $t->tinyInteger('period_month')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $hasRemark = false;
            } elseif ($table === 'erp_mfg_subcontract') {
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('supplier_id')->nullable();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('unit_price', 12, 2)->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $t->decimal('issued_amount', 14, 2)->nullable();
                $t->decimal('received_qty', 12, 2)->nullable();
                $t->decimal('consumed_amount', 14, 2)->nullable();
                $t->tinyInteger('status')->nullable();
                $t->dateTime('audit_at')->nullable();
            } elseif ($table === 'erp_mfg_subcontract_issue') {
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('subcontract_id')->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->date('issue_date')->nullable();
                $t->decimal('total_cost', 14, 2)->nullable();
                $t->tinyInteger('status')->nullable();
                $t->dateTime('audit_at')->nullable();
            } elseif ($table === 'erp_mfg_subcontract_issue_item') {
                $t->unsignedBigInteger('issue_id')->nullable();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('sku_id')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('unit_cost', 12, 2)->nullable();
                $t->decimal('amount', 14, 2)->nullable();
                $hasRemark = false;
            } else { // erp_mfg_subcontract_receive
                $t->string('code', 50)->nullable();
                $t->unsignedBigInteger('subcontract_id')->nullable();
                $t->unsignedBigInteger('warehouse_id')->nullable();
                $t->date('receive_date')->nullable();
                $t->decimal('quantity', 12, 2)->nullable();
                $t->decimal('unit_price', 12, 2)->nullable();
                $t->tinyInteger('status')->nullable();
                $t->dateTime('audit_at')->nullable();
            }
            if ($hasRemark) {
                $t->string('remark', 500)->nullable();
            }
            $t->dateTime('created_at')->nullable();
            if ($table !== 'erp_mfg_subcontract_issue_item') {
                $t->dateTime('updated_at')->nullable();
            }
            if (!in_array($table, ['erp_mfg_piece_wage', 'erp_mfg_subcontract_issue_item'], true)) {
                $t->dateTime('deleted_at')->nullable();
            }
        });
        $this->createdTables[] = $table;
    }

    // ---------- 只读查询 ----------

    protected function orderRow(int $orderId): ?object
    {
        return Capsule::table('erp_mfg_production_order')->where('id', $orderId)->first();
    }

    protected function wipRow(int $orderId): ?object
    {
        return Capsule::table('erp_mfg_wip')->where('order_id', $orderId)->first();
    }

    protected function wipFlowRows(int $orderId): array
    {
        return array_values(Capsule::table('erp_mfg_wip_flow')->where('order_id', $orderId)->get()->all());
    }

    protected function workReportRow(int $id): ?object
    {
        return Capsule::table('erp_mfg_work_report')->where('id', $id)->first();
    }

    protected function pieceWageRow(int $employeeId): ?object
    {
        return Capsule::table('erp_mfg_piece_wage')->where('employee_id', $employeeId)->first();
    }

    protected function salaryRows(int $employeeId): array
    {
        return array_values(Capsule::table('erp_hr_salary')->where('employee_id', $employeeId)->get()->all());
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
            'p1_test_seed',
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
            'name' => 'P1测试BOM',
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

    /** 建工作站（erp_mfg_routing.workstation_id NOT NULL，报工依赖） */
    protected function createWorkstation(): int
    {
        $id = $this->nextId();
        Capsule::table('erp_mfg_workstation')->insert([
            'id' => $id,
            'code' => 'WS-' . $id,
            'name' => 'P1测试工作站',
        ]);
        $this->workstationIds[] = $id;

        return $id;
    }

    /** 建工序（piece_rate 计件单价；wsId 缺省自建工作站） */
    protected function createRouting(int $productId, string $rate, ?int $wsId = null): int
    {
        $id = $this->nextId();
        $wsId ??= $this->createWorkstation();
        Capsule::table('erp_mfg_routing')->insert([
            'id' => $id,
            'product_id' => $productId,
            'name' => 'P1测试工序' . $id,
            'seq' => 1,
            'workstation_id' => $wsId,
            'standard_hours' => '0.00',
            'piece_rate' => $rate,
            'description' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->routingIds[] = $id;

        return $id;
    }

    /** 建在职员工（erp_hr_employee code/name NOT NULL，其余走默认值；departmentId>0 时写入部门用于批量薪资按部门隔离） */
    protected function createEmployee(int $departmentId = 0): int
    {
        $id = $this->nextId();
        $row = [
            'id' => $id,
            'code' => 'EMP-' . $id,
            'name' => 'P1测试员工',
            'status' => 1,
        ];
        if ($departmentId > 0) {
            $row['department_id'] = $departmentId;
        }
        Capsule::table('erp_hr_employee')->insert($row);
        $this->employeeIds[] = $id;

        return $id;
    }

    /** 建供应商（erp_supplier code/name NOT NULL） */
    protected function createSupplier(): int
    {
        $id = $this->nextId();
        Capsule::table('erp_supplier')->insert([
            'id' => $id,
            'code' => 'SUP-' . $id,
            'name' => 'P1测试供应商',
            'status' => 1,
        ]);
        $this->supplierIds[] = $id;

        return $id;
    }

    /** 草稿报工单（quantity/qualified 字符串；qualified 缺省=quantity） */
    protected function createWorkReport(int $orderId, int $productId, int $routingId, int $employeeId, string $quantity, ?string $qualified = null, string $reportDate = '2026-08-15'): int
    {
        $id = $this->nextId();
        Capsule::table('erp_mfg_work_report')->insert([
            'id' => $id,
            'code' => 'WR-' . $id,
            'order_id' => $orderId,
            'product_id' => $productId,
            'routing_id' => $routingId,
            'workstation_id' => 0,
            'employee_id' => $employeeId,
            'report_date' => $reportDate,
            'quantity' => $quantity,
            'qualified_qty' => $qualified ?? $quantity,
            'piece_rate' => '0.00',
            'amount' => '0.00',
            'status' => 0,
            'audit_at' => null,
            'remark' => '',
            'deleted_at' => null,
        ]);
        $this->workReportIds[] = $id;

        return $id;
    }

    // ---------- 断言 ----------

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

    protected function workReportService(): WorkReportService
    {
        return Container::get(WorkReportService::class);
    }

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

    protected function wageService(): PieceWageService
    {
        return Container::get(PieceWageService::class);
    }

    protected function hrService(): HrService
    {
        return Container::get(HrService::class);
    }
}
