<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\model\EamInspectionResult;
use app\model\EamInspectionTask;
use app\service\eam\EamInspectionService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use support\Container;
use Throwable;

/**
 * E1 设备点检执行闭环 集成测试（真库，属 --group=integration）。
 *
 * 表处理双模式（同 M3CapacityScaffold）：自有表 erp_eam_inspection_task/result
 * 缺表时最小化创建并 tearDown 删除（结构随 database/e1p1.sql 最小等价）；
 * 依赖表（install.sql 三张 EAM 主档表）从不创建，缺表整类跳过。
 *
 * 清理纪律：造数一律走本类方法并用雪花合成 ID（绝不触碰真实数据），
 * tearDown 只删登记过的本类 ID——维修单无来源列，按本类设备 ID 归集清理
 * （雪花设备 ID 不可能被他人引用）。日期一律相对本周一推进，日历无关。
 */
#[Group('integration')]
class E1InspectionTest extends IntegrationTestCase
{
    /** 自有表（database/e1p1.sql 新建）— 缺表时最小化创建并登记，tearDown 删除 */
    private const OWN_TABLES = ['erp_eam_inspection_result', 'erp_eam_inspection_task'];
    /** 依赖表（install.sql）— 只读使用，绝不创建 */
    private const DEP_TABLES = [
        'erp_eam_equipment',
        'erp_eam_maintenance_plan',
        'erp_eam_repair_order',
    ];

    private array $createdTables = [];
    private array $equipmentIds = [];
    private array $planIds = [];
    private array $taskIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->equipmentIds = $this->planIds = $this->taskIds = [];
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
                $this->dropTableIfExists($table);
            }
            // 例外行雪花 ID 服务端生成未登记，按本类登记 ID 归集清理（维修单按设备 ID）
            $cleanup = [
                'erp_eam_inspection_result' => ['task_id', $this->taskIds],
                'erp_eam_inspection_task' => ['id', $this->taskIds],
                'erp_eam_repair_order' => ['equipment_id', $this->equipmentIds],
                'erp_eam_maintenance_plan' => ['id', $this->planIds],
                'erp_eam_equipment' => ['id', $this->equipmentIds],
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

    /** 缺表时按 e1p1.sql 列结构最小化创建（全部列可空，服务层显式赋值） */
    private function createOwnTable(string $table): void
    {
        if ($table === 'erp_eam_inspection_task') {
            $build = static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->unsignedBigInteger('equipment_id')->nullable();
                $t->unsignedBigInteger('source_plan_id')->nullable();
                $t->date('task_date')->nullable();
                $t->unsignedBigInteger('assignee_id')->nullable();
                $t->tinyInteger('status')->nullable();
                $t->string('remark', 500)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
            };
        } else {
            $build = static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->unsignedBigInteger('task_id')->nullable();
                $t->string('item_name', 100)->nullable();
                $t->tinyInteger('result')->nullable();
                $t->string('remark', 500)->nullable();
                $t->dateTime('created_at')->nullable();
                $t->dateTime('updated_at')->nullable();
            };
        }
        $this->createTableIfMissing($table, $build);
        $this->createdTables[] = $table;
    }

    // ---------- 造数（全部登记，仅本类 ID） ----------

    protected function nextId(): int
    {
        return SnowflakeService::generate();
    }

    protected function createEquipment(): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_eam_equipment')->insert([
            'id' => $id,
            'code' => 'EQ-' . $id,
            'name' => '点检设备-' . $id,
            'model' => '',
            'serial_number' => '',
            'category' => '',
            'location' => '',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->equipmentIds[] = $id;

        return $id;
    }

    protected function createPlan(int $equipmentId): int
    {
        $id = $this->nextId();
        Capsule::table('erp_eam_maintenance_plan')->insert([
            'id' => $id,
            'equipment_id' => $equipmentId,
            'name' => '保养计划-' . $id,
            'frequency' => 'daily',
            'assignee' => '',
        ]);
        $this->planIds[] = $id;

        return $id;
    }

    protected function inspection(): EamInspectionService
    {
        return Container::get(EamInspectionService::class);
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

    protected function recordTask(int $id): void
    {
        $this->taskIds[] = $id;
    }

    // ---------- 用例 ----------

    #[TestDox('扫码首扫自动建任务并完成；完成日复扫被拒（设备当日点检已完成）')]
    public function testScanAutoCreateAndDoneBlocksRescan(): void
    {
        $equipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();

        $r = $svc->scanExecute($equipmentId, $mon, [
            ['item_name' => '外观检查', 'result' => 0, 'remark' => ''],
        ]);
        $this->recordTask((int) $r['task_id']);
        self::assertTrue($r['task_created'], '无任务时扫码自动建任务');
        self::assertSame(EamInspectionService::STATUS_DONE, $r['task_status']);
        self::assertFalse($r['abnormal']);
        self::assertSame(0, $r['repair_order_id']);
        self::assertSame(1, $r['item_count']);

        $task = EamInspectionTask::query()->find((int) $r['task_id']);
        self::assertSame($equipmentId, (int) $task->equipment_id);
        self::assertSame(0, (int) $task->source_plan_id, '扫码任务无来源计划');
        self::assertSame('扫码自动生成', (string) $task->remark);
        $items = EamInspectionResult::query()->where('task_id', $r['task_id'])->get();
        self::assertCount(1, $items);
        self::assertSame('外观检查', (string) $items[0]->item_name);
        self::assertSame(0, (int) $items[0]->result);

        $this->assertThrowsMessage(
            fn () => $svc->scanExecute($equipmentId, $mon, [['item_name' => '复扫', 'result' => 0]]),
            '设备当日点检已完成',
        );

        $r2 = $svc->scanExecute($equipmentId, $this->addDays($mon, 1), [['item_name' => '次日', 'result' => 0]]);
        $this->recordTask((int) $r2['task_id']);
        self::assertTrue($r2['task_created'], '完成日次日可再扫自动建新任务');
    }

    #[TestDox('异常闭环：0→2 建一次维修单；复扫刷新结果不重单；恢复正常后转完成')]
    public function testScanAbnormalCreatesRepairOrderOnce(): void
    {
        $equipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();

        // 首扫含异常 → 2 + 维修单
        $r = $svc->scanExecute($equipmentId, $mon, [
            ['item_name' => '轴承异响', 'result' => 1, 'remark' => '需更换'],
            ['item_name' => '外观', 'result' => 0],
        ]);
        $this->recordTask((int) $r['task_id']);
        self::assertTrue($r['abnormal']);
        self::assertSame(EamInspectionService::STATUS_ABNORMAL, $r['task_status']);
        self::assertGreaterThan(0, $r['repair_order_id']);
        $order = Capsule::table('erp_eam_repair_order')->where('id', $r['repair_order_id'])->first();
        self::assertNotNull($order);
        self::assertStringStartsWith('RO', (string) $order->code);
        self::assertSame($equipmentId, (int) $order->equipment_id);
        self::assertSame('点检异常项：轴承异响(需更换)', (string) $order->fault_description);
        self::assertSame('corrective', (string) $order->repair_type);
        self::assertSame('open', (string) $order->status);
        self::assertSame('0.00', (string) $order->cost);

        // 复扫仍有异常 → 维持 2、刷新结果行、不二次建单
        $r2 = $svc->scanExecute($equipmentId, $mon, [
            ['item_name' => '轴承异响', 'result' => 1, 'remark' => '需更换'],
            ['item_name' => '润滑', 'result' => 1, 'remark' => '缺油'],
        ]);
        self::assertSame(EamInspectionService::STATUS_ABNORMAL, $r2['task_status']);
        self::assertSame(0, $r2['repair_order_id'], '复扫不二次建单');
        self::assertSame(2, EamInspectionResult::query()->where('task_id', $r['task_id'])->count(), '结果行整删重插刷新');
        self::assertSame(
            1,
            Capsule::table('erp_eam_repair_order')->where('equipment_id', $equipmentId)->count(),
            '全程仅一张维修单',
        );

        // 维修后复扫全部正常 → 置 1（完成），维修单数不变
        $r3 = $svc->scanExecute($equipmentId, $mon, [
            ['item_name' => '轴承异响', 'result' => 0],
            ['item_name' => '润滑', 'result' => 0],
        ]);
        self::assertFalse($r3['abnormal']);
        self::assertSame(EamInspectionService::STATUS_DONE, $r3['task_status']);
        self::assertSame(
            1,
            Capsule::table('erp_eam_repair_order')->where('equipment_id', $equipmentId)->count(),
            '恢复正常不再建单',
        );
        self::assertSame(
            1,
            (int) Capsule::table('erp_eam_inspection_task')->where('id', $r['task_id'])->value('status'),
            '任务落已完成',
        );
    }

    #[TestDox('扫码入参校验：设备不存在/空项/格式/名称/结果/备注超长 全部拒绝且不留行')]
    public function testScanValidationRejects(): void
    {
        $equipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();
        $ghost = $this->nextId();

        $this->assertThrowsMessage(fn () => $svc->scanExecute($ghost, $mon, [['item_name' => 'x', 'result' => 0]]), '设备不存在');
        $this->assertThrowsMessage(fn () => $svc->scanExecute($equipmentId, $mon, []), '点检项不能为空');
        $this->assertThrowsMessage(fn () => $svc->scanExecute($equipmentId, $mon, ['x']), '点检项格式无效');
        $this->assertThrowsMessage(
            fn () => $svc->scanExecute($equipmentId, $mon, [['item_name' => '  ', 'result' => 0]]),
            '点检项名称不能为空',
        );
        $this->assertThrowsMessage(
            fn () => $svc->scanExecute($equipmentId, $mon, [['item_name' => 'x', 'result' => 2]]),
            '点检结果无效',
        );
        $this->assertThrowsMessage(
            fn () => $svc->scanExecute($equipmentId, $mon, [['item_name' => 'x', 'result' => 0, 'remark' => str_repeat('长', 501)]]),
            '点检备注过长',
        );
        self::assertSame(0, EamInspectionTask::query()->where('equipment_id', $equipmentId)->count(), '校验失败不留任务行');
    }

    #[TestDox('建任务规则：同日冲突拒绝、计划绑定设备、幽灵设备/非法日期拒绝')]
    public function testCreateTaskDuplicateAndPlanBinding(): void
    {
        $equipmentId = $this->createEquipment();
        $otherEquipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();

        $plan = $this->createPlan($equipmentId);
        $otherPlan = $this->createPlan($otherEquipmentId);
        $task = $svc->createTask($equipmentId, $mon, $plan);
        $this->recordTask((int) $task->id);
        self::assertSame(EamInspectionService::STATUS_PENDING, (int) $task->status);

        $this->assertThrowsMessage(
            fn () => $svc->createTask($equipmentId, $mon),
            '该设备当日已有未完成的点检任务',
        );
        $this->assertThrowsMessage(
            fn () => $svc->createTask($equipmentId, $mon, $otherPlan),
            '保养计划不存在',
        );
        $this->assertThrowsMessage(
            fn () => $svc->createTask($otherEquipmentId, $mon, $plan),
            '保养计划不存在',
        );
        $this->assertThrowsMessage(fn () => $svc->createTask($this->nextId(), $mon), '设备不存在');
        $this->assertThrowsMessage(fn () => $svc->createTask($equipmentId, '2026-02-30'), '无效的点检日期');
    }

    #[TestDox('取消状态机：待执行可取消、终态不可再取消、已完成/异常待维修不可取消')]
    public function testCancelTaskStateMachine(): void
    {
        $equipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();

        $task = $svc->createTask($equipmentId, $mon);
        $this->recordTask((int) $task->id);
        $svc->cancelTask((int) $task->id);
        self::assertSame(EamInspectionService::STATUS_CANCELLED, (int) $task->fresh()->status);
        $this->assertThrowsMessage(fn () => $svc->cancelTask((int) $task->id), '点检任务已取消');

        // 取消后当日复扫自动重建（3 不在待执行集合内）
        $r = $svc->scanExecute($equipmentId, $mon, [['item_name' => 'x', 'result' => 0]]);
        $this->recordTask((int) $r['task_id']);
        self::assertTrue($r['task_created'], '取消后当日可再扫重建');
        self::assertSame(EamInspectionService::STATUS_DONE, $r['task_status']);
        $this->assertThrowsMessage(fn () => $svc->cancelTask((int) $r['task_id']), '已完成的点检任务不能取消');

        // 异常待维修不可取消
        $equipmentId2 = $this->createEquipment();
        $r2 = $svc->scanExecute($equipmentId2, $mon, [['item_name' => '漏油', 'result' => 1]]);
        $this->recordTask((int) $r2['task_id']);
        $this->assertThrowsMessage(
            fn () => $svc->cancelTask((int) $r2['task_id']),
            '异常待维修的点检任务不能取消，请先维修完成',
        );
    }

    #[TestDox('改期/改派/改备注仅限待执行：状态锁、同日冲突锁、缺单拒绝')]
    public function testUpdateTaskRules(): void
    {
        $equipmentId = $this->createEquipment();
        $mon = $this->monday();
        $svc = $this->inspection();

        $task = $svc->createTask($equipmentId, $mon, assigneeId: $this->nextId(), remark: '初版');
        $this->recordTask((int) $task->id);
        $updated = $svc->updateTask((int) $task->id, ['remark' => '改版', 'assignee_id' => 0]);
        self::assertSame('改版', (string) $updated->remark);
        self::assertSame(0, (int) $updated->assignee_id, 'assignee 传 0 清空');

        // 改期撞上另一未完成任务
        $task2 = $svc->createTask($equipmentId, $this->addDays($mon, 1));
        $this->recordTask((int) $task2->id);
        $this->assertThrowsMessage(
            fn () => $svc->updateTask((int) $task2->id, ['task_date' => $mon]),
            '该设备当日已有未完成的点检任务',
        );

        // 完成后改不动
        $done = $svc->scanExecute($equipmentId, $this->addDays($mon, 1), [['item_name' => 'x', 'result' => 0]]);
        $this->assertThrowsMessage(
            fn () => $svc->updateTask((int) $done['task_id'], ['remark' => 'x']),
            '仅待执行的点检任务可修改',
        );
        $this->assertThrowsMessage(fn () => $svc->updateTask($this->nextId(), ['remark' => 'x']), '点检任务不存在');
    }
}
