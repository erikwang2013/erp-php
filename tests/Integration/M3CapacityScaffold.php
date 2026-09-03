<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\manufacturing\MfgCapacityService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use support\Container;
use Throwable;

/**
 * P1 M3 产能负荷 集成测试共享脚手架（抽象基类）。
 *
 * 表处理双模式（同 P1M1M2CostingScaffold）：自有表 erp_mfg_capacity_calendar
 * 缺表时最小化创建并 tearDown 删除；依赖表（install.sql 四张制造主数据表）
 * 从不创建，缺表整类跳过。造数一律走本类方法并登记（只清本类合成 ID）；
 * 合成 ID 全走雪花随机，绝不触碰真实数据。
 *
 * 日历断言基准：本周一~周日（strtotime('monday this week') 起算，周日相对 +6 天），
 * 使默认规则（周一~五 8 小时）断言与真实星期几无关。
 */
abstract class M3CapacityScaffold extends IntegrationTestCase
{
    /** 自有表（database/m3_capacity.sql）— 缺表时最小化创建 */
    protected const OWN_TABLES = ['erp_mfg_capacity_calendar'];
    /** 依赖表（install.sql）— 只读使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_mfg_workstation',
        'erp_mfg_routing',
        'erp_mfg_production_order',
        'erp_mfg_production_item',
    ];

    protected array $createdTables = [];
    protected array $workstationIds = [];
    protected array $routingIds = [];
    protected array $orderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->workstationIds = $this->routingIds
            = $this->orderIds = [];
        // 自有表先补齐（服务写入要求列存在），随后依赖表门槛整类跳过
        foreach (self::OWN_TABLES as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                $this->createOwnTable($table);
            }
        }
        $missing = array_values(array_filter(self::DEP_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missing !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missing) . '（请先导入 install.sql）');
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
            // 例外行雪花 ID 服务端生成未登记，按 workstation_id 归集清理
            $cleanup = [
                'erp_mfg_capacity_calendar' => ['workstation_id', $this->workstationIds],
                'erp_mfg_production_item' => ['order_id', $this->orderIds],
                'erp_mfg_production_order' => ['id', $this->orderIds],
                'erp_mfg_routing' => ['id', $this->routingIds],
                'erp_mfg_workstation' => ['id', $this->workstationIds],
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

    /** 缺表时按 m3_capacity.sql 列结构最小化创建（创建路径全部列可空，服务层显式赋值） */
    private function createOwnTable(string $table): void
    {
        $this->createTableIfMissing($table, static function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();
            $t->unsignedBigInteger('workstation_id')->nullable();
            $t->date('work_date')->nullable();
            $t->decimal('available_hours', 5, 2)->nullable();
            $t->string('remark', 200)->nullable();
            $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable();
            $t->unique(['workstation_id', 'work_date']);
        });
        $this->createdTables[] = $table;
    }

    // ---------- 造数（全部登记，仅本类 ID） ----------

    protected function nextId(): int
    {
        return SnowflakeService::generate();
    }

    protected function createWorkstation(int $status = 1): int
    {
        $id = $this->nextId();
        Capsule::table('erp_mfg_workstation')->insert([
            'id' => $id,
            'code' => 'WS-' . $id,
            'name' => '工作站-' . $id,
            'status' => $status,
        ]);
        $this->workstationIds[] = $id;

        return $id;
    }

    /** 产品工艺路线：落点工作站 + 单件标准工时 */
    protected function createRouting(int $productId, int $wsId, string $hours): int
    {
        $id = $this->nextId();
        Capsule::table('erp_mfg_routing')->insert([
            'id' => $id,
            'product_id' => $productId,
            'name' => '工序-' . $id,
            'seq' => 1,
            'workstation_id' => $wsId,
            'standard_hours' => $hours,
            'piece_rate' => '0.00',
            'description' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->routingIds[] = $id;

        return $id;
    }

    /**
     * 未结工单（订单 + 单明细）。orderStatus/itemStatus 默认 0=待生产；
     * deleted=true 模拟软删。计划窗口 start/end 必填（报表口径仅统计有计划窗口的工单）。
     */
    protected function createOpenOrder(
        int $productId,
        string $plannedQty,
        string $plannedStart,
        string $plannedEnd,
        int $orderStatus = 0,
        int $itemStatus = 0,
        string $completedQty = '0.00',
        bool $deleted = false
    ): int {
        $orderId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_mfg_production_order')->insert([
            'id' => $orderId,
            'code' => 'PO-' . $orderId,
            'bom_id' => 0,   // 报表口径不触及 BOM，bom_id 仅列约束占位
            'warehouse_id' => 0,
            'planned_quantity' => $plannedQty,
            'completed_quantity' => '0.00',
            'status' => $orderStatus,
            'planned_start' => $plannedStart,
            'planned_end' => $plannedEnd,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deleted ? $now : null,
        ]);
        Capsule::table('erp_mfg_production_item')->insert([
            'id' => $this->nextId(),
            'order_id' => $orderId,
            'product_id' => $productId,
            'planned_quantity' => $plannedQty,
            'completed_quantity' => $completedQty,
            'status' => $itemStatus,
            'created_at' => $now,
        ]);
        $this->orderIds[] = $orderId;

        return $orderId;
    }

    // ---------- 基准日/断言工具 ----------

    /** 本周一（若今天是周一则今天）；测试内日期一律相对该周推进 */
    protected function monday(): string
    {
        return date('Y-m-d', strtotime('monday this week'));
    }

    protected function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . ' ' . $days . ' day'));
    }

    protected function assertThrowsMessage(callable $fn, string $needle): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            self::assertStringContainsString($needle, $e->getMessage());

            return;
        }
        self::fail('预期抛出含「' . $needle . '」的异常，实际未抛出');
    }

    protected function assertBcEquals(string $expected, string $actual, string $label = ''): void
    {
        self::assertTrue(
            bccomp(bc_norm($expected), bc_norm($actual), 4) === 0,
            $label . ' 期望 ' . $expected . ' 实际 ' . $actual
        );
    }

    protected function capacityService(): MfgCapacityService
    {
        return Container::get(MfgCapacityService::class);
    }
}
