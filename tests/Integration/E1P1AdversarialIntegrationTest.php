<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\eam\EamInspectionService;
use app\service\project\ProjectCostService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use support\Container;
use Throwable;

/**
 * E1 扫码闭环 + P1 成本归集 · 对抗性集成测试（真库，--group=integration）。
 *
 * 与 E1InspectionTest / P1ProjectCostTest 互补，专攻其未覆盖路径：状态 2 复扫不重单
 * 且摘要冻结、异常转正常关单后复扫拒绝、取消态重建三行并存、updateTask 白名单走私、
 * 同日 done+pending 脏数据守门、校验失败零半写入；费率快照精确串 133.74×8.50 与
 * 0.50×100.00、bcmath half-up 对抗（1.005×200→202.00，浮点必错）、拒绝行补配费率
 * 后重跑补齐、区间边界含/不含、预算偏差符号与零分母、类别分组一致。
 *
 * 数值断言一律 assertSame 字符串（DECIMAL 列 PDO 直读字符串）。清理只删本类登记 ID
 * （维修单无来源任务列，按本类设备 ID 归集）；缺自有表最小化自建，缺依赖表/ALTER 列
 * 整类跳过（见 database/e1p1.sql）。
 */
#[Group('integration')]
class E1P1AdversarialIntegrationTest extends IntegrationTestCase
{
    private const OWN_TABLES = ['erp_eam_inspection_result', 'erp_eam_inspection_task', 'erp_project_cost'];
    private const DEP_TABLES = [
        'erp_eam_equipment', 'erp_eam_repair_order',
        'erp_project', 'erp_project_member', 'erp_project_timesheet',
    ];
    private const ALTER_COLUMNS = [
        'erp_project' => ['budget_amount'],
        'erp_project_member' => ['hourly_rate'],
    ];
    private const BASE_DATE = '2026-01-05'; // 周一，测试日期一律相对推进，日历无关

    private array $createdTables = [];
    private array $equipmentIds = [];
    private array $taskIds = [];
    private array $projectIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->equipmentIds = $this->taskIds = $this->projectIds = [];
        foreach (self::OWN_TABLES as $table) {
            if (!Capsule::schema()->hasTable($table)) {
                $this->createOwnTable($table);
            }
        }
        foreach (self::ALTER_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (!Capsule::schema()->hasColumn($table, $column)) {
                    self::markTestSkipped("依赖列缺失: {$table}.{$column}（请先导入 database/e1p1.sql）");
                }
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
            $cleanup = [
                'erp_eam_inspection_result' => ['task_id', $this->taskIds],
                'erp_eam_inspection_task' => ['id', $this->taskIds],
                'erp_eam_repair_order' => ['equipment_id', $this->equipmentIds],
                'erp_eam_equipment' => ['id', $this->equipmentIds],
                'erp_project_cost' => ['project_id', $this->projectIds],
                'erp_project_timesheet' => ['project_id', $this->projectIds],
                'erp_project_member' => ['project_id', $this->projectIds],
                'erp_project' => ['id', $this->projectIds],
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
        $cols = [
            'erp_eam_inspection_task' => ['equipment_id' => 'uint', 'source_plan_id' => 'uint', 'task_date' => 'date', 'assignee_id' => 'uint', 'status' => 'tiny', 'remark' => 's500'],
            'erp_eam_inspection_result' => ['task_id' => 'uint', 'item_name' => 's100', 'result' => 'tiny', 'remark' => 's500'],
            'erp_project_cost' => ['project_id' => 'uint', 'task_id' => 'uint', 'employee_id' => 'uint', 'work_date' => 'date', 'source_type' => 's20', 'timesheet_id' => 'uint', 'category' => 'tiny', 'hours' => 'd12', 'rate' => 'd12', 'cost' => 'd14', 'remark' => 's500'],
        ];
        $build = static function (Blueprint $t) use ($table, $cols): void {
            $t->unsignedBigInteger('id')->primary();
            foreach ($cols[$table] as $name => $type) {
                $column = match ($type) {
                    'uint' => $t->unsignedBigInteger($name),
                    'tiny' => $t->tinyInteger($name),
                    'date' => $t->date($name),
                    's20' => $t->string($name, 20),
                    's100' => $t->string($name, 100),
                    's500' => $t->string($name, 500),
                    'd12' => $t->decimal($name, 12, 2),
                    'd14' => $t->decimal($name, 14, 2),
                };
                $column->nullable();
            }
            $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable();
        };
        $this->createTableIfMissing($table, $build);
        $this->createdTables[] = $table;
    }

    // ---------- 造数（雪花 ID 全部登记，仅本类可清） ----------

    protected function nextId(): int
    {
        return SnowflakeService::generate();
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

    /** E1 造数/工具 */
    protected function makeEquipment(): int
    {
        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_eam_equipment')->insert([
            'id' => $id, 'code' => 'EQ-' . $id, 'name' => '对抗点检设备-' . $id,
            'model' => '', 'serial_number' => '', 'category' => '', 'location' => '',
            'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->equipmentIds[] = $id;

        return $id;
    }

    /** 扫码包装：执行后登记任务 ID（含自动生成）并返回原始结果 */
    protected function scan(int $equipmentId, string $taskDate, array $items): array
    {
        $result = $this->inspection()->scanExecute($equipmentId, $taskDate, $items);
        $this->taskIds[] = (int) $result['task_id'];

        return $result;
    }

    protected function countRows(string $table, string $column, int $id): int
    {
        return (int) Capsule::table($table)->where($column, $id)->count();
    }

    protected function inspection(): EamInspectionService
    {
        return Container::get(EamInspectionService::class);
    }

    /** P1 造数/工具 */
    protected function makeProject(string $budget = '0.00'): int
    {
        $id = $this->nextId();
        Capsule::table('erp_project')->insert([
            'id' => $id, 'code' => 'PRJ-' . $id, 'name' => '对抗成本项目-' . $id,
            'manager_user_id' => 0, 'status' => 0, 'budget_amount' => $budget,
        ]);
        $this->projectIds[] = $id;

        return $id;
    }

    protected function addMember(int $projectId, int $userId, string $hourlyRate = '0.00'): void
    {
        Capsule::table('erp_project_member')->insert([
            'id' => $this->nextId(), 'project_id' => $projectId, 'user_id' => $userId, 'hourly_rate' => $hourlyRate,
        ]);
    }

    protected function setRate(int $projectId, int $userId, string $hourlyRate): void
    {
        Capsule::table('erp_project_member')
            ->where('project_id', $projectId)->where('user_id', $userId)
            ->update(['hourly_rate' => $hourlyRate]);
    }

    protected function addTimesheet(int $projectId, int $userId, string $workDate, string $hours): int
    {
        $id = $this->nextId();
        Capsule::table('erp_project_timesheet')->insert([
            'id' => $id, 'project_id' => $projectId, 'user_id' => $userId,
            'hours' => $hours, 'work_date' => $workDate, 'description' => '',
        ]);

        return $id;
    }

    protected function cost(): ProjectCostService
    {
        return Container::get(ProjectCostService::class);
    }

    // ================= E1 扫码闭环对抗 =================

    public function testE1AbnormalRescanSingleOrderFrozenSummaryRowsRefreshed(): void
    {
        $eq = $this->makeEquipment();
        $day = self::BASE_DATE;
        $first = $this->scan($eq, $day, [
            ['item_name' => '润滑系统', 'result' => 0],
            ['item_name' => '制动油管', 'result' => 1, 'remark' => '漏油'],
        ]);
        self::assertTrue($first['task_created'], '无任务自动生成');
        self::assertSame(2, $first['task_status']);
        self::assertTrue($first['abnormal']);
        self::assertSame(2, $first['item_count']);
        self::assertGreaterThan(0, $first['repair_order_id']);
        // 维修单关联字段逐项核对
        $ro = Capsule::table('erp_eam_repair_order')->where('equipment_id', $eq)->get()->all();
        self::assertCount(1, $ro, '0→2 首次异常仅建 1 张维修单');
        self::assertSame('点检异常项：制动油管(漏油)', $ro[0]->fault_description, '异常摘要=项名(备注)');
        self::assertSame('corrective', $ro[0]->repair_type);
        self::assertSame('open', $ro[0]->status);
        self::assertSame('0.00', $ro[0]->cost, '维修单成本初始 0.00 字符串');
        self::assertSame($day, (string) $ro[0]->start_date);
        self::assertSame($eq, (int) $ro[0]->equipment_id);
        // 状态 2 复扫（仍异常）：不重复建单、摘要冻结首扫快照、结果行整删重插刷新
        $again = $this->scan($eq, $day, [
            ['item_name' => '制动油管', 'result' => 0],
            ['item_name' => '传动皮带', 'result' => 1, 'remark' => '异响'],
            ['item_name' => '液位开关', 'result' => 1],
        ]);
        self::assertFalse($again['task_created'], '复扫复用原任务');
        self::assertSame(2, $again['task_status'], '仍异常维持待维修');
        self::assertSame(0, $again['repair_order_id'], '状态 2 复扫绝不二次建单');
        self::assertSame(1, $this->countRows('erp_eam_repair_order', 'equipment_id', $eq), '维修单仍唯一');
        self::assertSame('点检异常项：制动油管(漏油)', (string) Capsule::table('erp_eam_repair_order')
            ->where('equipment_id', $eq)->value('fault_description'), '维修单摘要保持首扫快照');
        self::assertSame(3, $this->countRows('erp_eam_inspection_result', 'task_id', (int) $first['task_id']), '旧结果行已清除重建');
        self::assertSame(1, (int) Capsule::table('erp_eam_inspection_result')
            ->where('task_id', $first['task_id'])->where('result', 0)->count(), '转正常项已落库');
    }

    public function testE1AbnormalThenAllNormalClosesOrderPersistsRescanBlocked(): void
    {
        $eq = $this->makeEquipment();
        $day = self::BASE_DATE;
        $bad = $this->scan($eq, $day, [['item_name' => 'A', 'result' => 1, 'remark' => 'R']]);
        self::assertSame(2, $bad['task_status']);
        self::assertGreaterThan(0, $bad['repair_order_id']);
        $ok = $this->scan($eq, $day, [['item_name' => 'A', 'result' => 0]]);
        self::assertSame(1, $ok['task_status'], '异常复扫全正常 → 转已完成');
        self::assertSame(0, $ok['repair_order_id'], '恢复正常不建新单');
        self::assertSame(1, $this->countRows('erp_eam_repair_order', 'equipment_id', $eq), '维修单随闭环保留');
        $this->assertThrowsMessage(
            fn () => $this->inspection()->scanExecute($eq, $day, [['item_name' => 'A', 'result' => 0]]),
            '设备当日点检已完成'
        );
        $this->assertThrowsMessage(fn () => $this->inspection()->cancelTask((int) $bad['task_id']), '已完成的点检任务不能取消');
        $this->assertThrowsMessage(fn () => $this->inspection()->updateTask((int) $bad['task_id'], ['remark' => '改']), '仅待执行的点检任务可修改');
    }

    public function testE1CancelledRescanRebuildsOldTaskUntouched(): void
    {
        $eq = $this->makeEquipment();
        $day = self::BASE_DATE;
        $created = $this->inspection()->createTask($eq, $day, 0, 7, '计划单');
        self::assertSame(0, (int) $created->status);
        $cancelled = (int) $created->id;
        $this->taskIds[] = $cancelled;
        $this->inspection()->cancelTask($cancelled);
        // 取消（终态 3）后：createTask 允许同日重建
        $rebuilt = $this->inspection()->createTask($eq, $day, 0, 8, '重建');
        self::assertNotSame($cancelled, (int) $rebuilt->id);
        $this->taskIds[] = (int) $rebuilt->id;
        $this->inspection()->cancelTask((int) $rebuilt->id);
        $this->assertThrowsMessage(fn () => $this->inspection()->cancelTask((int) $rebuilt->id), '点检任务已取消');
        // 取消态任务再扫 → 自动生成全新任务并执行，旧任务结果为零
        $res = $this->scan($eq, $day, [
            ['item_name' => 'X', 'result' => 0],
            ['item_name' => 'Y', 'result' => 1],
        ]);
        self::assertTrue($res['task_created'], '取消后扫码自动重建');
        self::assertSame(2, $res['task_status']);
        self::assertNotSame($cancelled, $res['task_id'], '重建任务 ID 不与取消任务相同');
        self::assertSame(3, (int) Capsule::table('erp_eam_inspection_task')->where('equipment_id', $eq)->count(), '原取消+重建取消+扫码重建共存三行');
        self::assertSame(0, $this->countRows('erp_eam_inspection_result', 'task_id', $cancelled), '取消任务不沾新结果');
        self::assertSame(2, $this->countRows('erp_eam_inspection_result', 'task_id', (int) $res['task_id']));
        self::assertSame(0, (int) Capsule::table('erp_eam_inspection_task')->where('id', $res['task_id'])->value('source_plan_id'), '扫码生成任务 source_plan_id=0');
    }

    public function testE1UpdateWhitelistDirtyDonePriorityAndNoPartialRows(): void
    {
        $eq = $this->makeEquipment();
        $day = self::BASE_DATE;
        $task = $this->inspection()->createTask($eq, $day);
        $this->taskIds[] = (int) $task->id;
        // updateTask 白名单走私：status/equipment_id 字段被忽略，状态机不可绕过
        $this->inspection()->updateTask((int) $task->id, [
            'task_date' => $this->addDays($day, 1), 'assignee_id' => 9,
            'remark' => '只许白名单', 'status' => 1, 'equipment_id' => 999999,
        ]);
        $after = Capsule::table('erp_eam_inspection_task')->where('id', $task->id)->first();
        self::assertSame(0, (int) $after->status, '走私 status=1 无效，仍待执行');
        self::assertSame($eq, (int) $after->equipment_id, '走私 equipment_id 无效');
        self::assertSame($this->addDays($day, 1), (string) $after->task_date, '改期生效');
        self::assertSame('只许白名单', (string) $after->remark);
        // 脏数据同日 done+pending 并存 → done 优先拒绝，不静默改写已完成记录
        $done = $this->scan($eq, $this->addDays($day, 1), [['item_name' => 'A', 'result' => 0]]);
        self::assertSame(1, $done['task_status']);
        $dirty = $this->nextId();
        $this->taskIds[] = $dirty;
        Capsule::table('erp_eam_inspection_task')->insert([
            'id' => $dirty, 'equipment_id' => $eq, 'source_plan_id' => 0,
            'task_date' => $this->addDays($day, 1), 'assignee_id' => 0, 'status' => 0, 'remark' => '脏数据',
        ]);
        $this->assertThrowsMessage(
            fn () => $this->inspection()->scanExecute($eq, $this->addDays($day, 1), [['item_name' => 'A', 'result' => 0]]),
            '设备当日点检已完成'
        );
        // 校验失败零半写入（无效日期/空项/超长备注均在事务前拒绝）
        $this->assertThrowsMessage(fn () => $this->scan($eq, '2026-02-30', [['item_name' => 'A', 'result' => 0]]), '无效的点检日期');
        $this->assertThrowsMessage(fn () => $this->scan($eq, '2026-13-01', [['item_name' => 'A', 'result' => 0]]), '无效的点检日期');
        $this->assertThrowsMessage(fn () => $this->scan($eq, $day, []), '点检项不能为空');
        $this->assertThrowsMessage(fn () => $this->scan($eq, $day, ['x']), '点检项格式无效');
        $this->assertThrowsMessage(fn () => $this->scan($eq, $day, [['item_name' => '  ', 'result' => 0]]), '点检项名称不能为空');
        $this->assertThrowsMessage(fn () => $this->scan($eq, $day, [['item_name' => 'A', 'result' => 9]]), '点检结果无效');
        $this->assertThrowsMessage(
            fn () => $this->scan($eq, $day, [['item_name' => 'A', 'result' => 0, 'remark' => str_repeat('r', 501)]]),
            '点检备注过长'
        );
        self::assertSame(0, $this->countRows('erp_eam_inspection_result', 'task_id', $dirty), '拒绝路径不触碰脏数据行');
        self::assertSame(0, (int) Capsule::table('erp_eam_inspection_task')
            ->where('equipment_id', $eq)->where('task_date', $day)->count(), '校验失败不自动建当日任务');
        self::assertSame(2, (int) Capsule::table('erp_eam_inspection_task')->where('equipment_id', $eq)->count(), '任务总数不变');
        $this->assertThrowsMessage(fn () => $this->inspection()->createTask(99999999, $day), '设备不存在');
        $this->assertThrowsMessage(fn () => $this->inspection()->scanExecute(99999999, $day, [['item_name' => 'A', 'result' => 0]]), '设备不存在');
    }

    // ================= P1 成本归集对抗 =================

    public function testP1TimesheetPrecisionSnapshotAndRawString(): void
    {
        $p = $this->makeProject();
        $u1 = 1001;
        $u2 = 1002;
        $this->addMember($p, $u1, '8.50');
        $this->addMember($p, $u2, '100.00');
        $d1 = self::BASE_DATE;
        $ts1 = $this->addTimesheet($p, $u1, $d1, '133.74'); // 133.74×8.50 = 1136.79 精确串
        $this->addTimesheet($p, $u2, $this->addDays($d1, 1), '0.50'); // 0.50×100.00 = 50.00
        $r1 = $this->cost()->generateFromTimesheet($p, $d1, $this->addDays($d1, 1));
        self::assertSame(2, $r1['created']);
        self::assertSame(0, $r1['skipped']);
        self::assertSame(0, $r1['refused']);
        $row1 = Capsule::table('erp_project_cost')->where('timesheet_id', $ts1)->first();
        self::assertSame('1136.79', (string) $row1->cost, '133.74×8.50 精确串，无浮点');
        self::assertSame('133.74', (string) $row1->hours);
        self::assertSame('8.50', (string) $row1->rate, '费率快照原样落库');
        self::assertSame('timesheet', (string) $row1->source_type);
        self::assertSame($d1, (string) $row1->work_date);
        self::assertSame(1, (int) $row1->category);
        self::assertSame($u1, (int) $row1->employee_id);
        $all = Capsule::table('erp_project_cost')->where('project_id', $p)->orderBy('id')->get(['rate', 'cost', 'hours'])->all();
        self::assertSame('50.00', (string) $all[1]->cost, '0.5×100 → 50.00');
        self::assertSame('100.00', (string) $all[1]->rate);
        self::assertSame('0.50', (string) $all[1]->hours);
        $pnl = $this->cost()->projectPnl($p);
        self::assertSame('1186.79', $pnl['total_cost'], '1136.79+50.00 bcadd');
        self::assertCount(2, $pnl['labour_details'], '人工行全部进 labour_details');
        self::assertSame('1136.79', $pnl['labour_details'][0]['cost']);
    }

    public function testP1ManualHalfUpAdversarialAndRejectsNoOrphan(): void
    {
        $p = $this->makeProject();
        $svc = $this->cost();
        // 金额字符串直通：材料直接金额原样往返
        $m = $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '133.74']);
        self::assertSame('133.74', (string) Capsule::table('erp_project_cost')->where('id', $m->id)->value('cost'));
        self::assertSame('manual', (string) $m->source_type);
        self::assertSame(0, (int) $m->timesheet_id);
        self::assertSame('0.00', (string) $m->hours);
        // half-up 对抗：1.005 在 bcmath 精确域恰半进位 1.01 → 202.00；float(1.005)=1.004999… 必错
        $l = $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 1, 'hours' => '1.005', 'rate' => '200.00']);
        self::assertSame('202.00', (string) $l->cost, 'bc_round(1.005,2)=1.01 后再乘');
        self::assertSame('1.01', (string) $l->hours, '工时先 half-up 再存');
        self::assertSame('200.00', (string) $l->rate);
        // 校验拒绝且零半写入（拒绝前后行数不变）
        $before = (int) Capsule::table('erp_project_cost')->where('project_id', $p)->count();
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => '2026-02-30', 'category' => 2, 'cost' => '1.00']), '无效的发生日期');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 9, 'cost' => '1.00']), '无效的成本类别');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 1, 'hours' => '0', 'rate' => '100']), '工时必须大于0');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 1, 'hours' => '-1', 'rate' => '100']), '工时格式无效');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 1, 'hours' => '1.5', 'rate' => 'abc']), '费率格式无效');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '0']), '成本金额必须大于0');
        $this->assertThrowsMessage(fn () => $svc->createManual($p, ['work_date' => self::BASE_DATE, 'category' => 2]), '成本金额格式无效');
        self::assertSame($before, (int) Capsule::table('erp_project_cost')->where('project_id', $p)->count(), '校验失败零半写入');
        $this->assertThrowsMessage(fn () => $svc->createManual(99999999, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '1.00']), '项目不存在');
    }

    public function testP1RefusalRetryAfterRateFixAndManualCoexist(): void
    {
        $p = $this->makeProject();
        $svc = $this->cost();
        $u1 = 2001; // 无 erp_project_member 行
        $u2 = 2002; // 成员行费率 0（未配置）
        $u3 = 2003; // 正常费率
        $this->addMember($p, $u2, '0.00');
        $this->addMember($p, $u3, '50.00');
        $d = self::BASE_DATE;
        $this->addTimesheet($p, $u1, $d, '2.00');
        $this->addTimesheet($p, $u2, $d, '3.00');
        $this->addTimesheet($p, $u3, $d, '4.00');
        $r1 = $svc->generateFromTimesheet($p, $d, $d);
        self::assertSame(1, $r1['created'], '仅正常费率成员归集');
        self::assertSame(0, $r1['skipped']);
        self::assertSame(2, $r1['refused'], '无成员行+费率 0 各拒一行');
        self::assertSame(1, $this->countRows('erp_project_cost', 'project_id', $p), '拒绝行零半写入');
        $messages = array_column($r1['details'], 'message');
        self::assertContains("成员 {$u1} 未加入项目", $messages, '拒绝消息逐字明确');
        self::assertContains("成员 {$u2} 未配置费率", $messages, '费率 0 消息逐字明确');
        $r2 = $svc->generateFromTimesheet($p, $d, $d);
        self::assertSame(0, $r2['created']);
        self::assertSame(1, $r2['skipped'], '已归集行二次全量 skipped');
        self::assertSame(2, $r2['refused']);
        self::assertSame(1, $this->countRows('erp_project_cost', 'project_id', $p), '重跑不重复行');
        // 补配后重跑补齐（幂等补漏），不产生重复
        $this->addMember($p, $u1, '100.00');
        $this->setRate($p, $u2, '200.00');
        $r3 = $svc->generateFromTimesheet($p, $d, $d);
        self::assertSame(2, $r3['created'], '补配费率后重跑补齐 2 行');
        self::assertSame(1, $r3['skipped']);
        self::assertSame(0, $r3['refused']);
        self::assertSame(3, $this->countRows('erp_project_cost', 'project_id', $p));
        self::assertSame('200.00', (string) Capsule::table('erp_project_cost')->where('project_id', $p)->where('employee_id', $u1)->value('cost'), '2.00h×100.00');
        self::assertSame('600.00', (string) Capsule::table('erp_project_cost')->where('project_id', $p)->where('employee_id', $u2)->value('cost'), '3.00h×200.00');
        // 手工与归集行共存（同一项目），总计 bcadd
        $svc->createManual($p, ['work_date' => $d, 'category' => 1, 'hours' => '8.00', 'rate' => '100.00']);
        $pnl = $svc->projectPnl($p);
        self::assertSame('1800.00', $pnl['total_cost'], '1000.00 归集 + 800.00 手工');
        self::assertCount(4, $pnl['labour_details'], '手工人工行同入 labour_details');
    }

    public function testP1PnlBudgetVarianceSymbolZeroDenominatorCategoryConsistency(): void
    {
        $svc = $this->cost();
        // 预算内：10000 - 8000 → 偏差 2000.00 / 率 20.00 / 不超支
        $a = $this->makeProject('10000.00');
        $svc->createManual($a, ['work_date' => self::BASE_DATE, 'category' => 1, 'hours' => '70.00', 'rate' => '100.00']);
        $svc->createManual($a, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '800.00']);
        $svc->createManual($a, ['work_date' => self::BASE_DATE, 'category' => 3, 'cost' => '200.00']);
        $pa = $svc->projectPnl($a);
        self::assertSame('10000.00', $pa['budget_amount']);
        self::assertSame('8000.00', $pa['total_cost']);
        self::assertSame('2000.00', $pa['variance'], 'bcsub 字符串');
        self::assertSame('20.00', $pa['variance_rate']);
        self::assertFalse($pa['over_budget']);
        self::assertCount(1, $pa['labour_details'], 'labour_details 只含人工行');
        $sum = '0.00';
        foreach ($pa['cost_by_category'] as $cat) {
            $sum = bcadd($sum, (string) $cat['cost'], 2);
        }
        self::assertSame('8000.00', $sum, 'category 分组合计与总计一致');
        // 超支：10000 - 12000 → -2000.00 / -20.00 / over_budget true
        $b = $this->makeProject('10000.00');
        $svc->createManual($b, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '12000.00']);
        $pb = $svc->projectPnl($b);
        self::assertSame('-2000.00', $pb['variance']);
        self::assertSame('-20.00', $pb['variance_rate']);
        self::assertTrue($pb['over_budget']);
        // 零分母：预算 0 实际 100 → 偏差率 null 不除零崩溃
        $c = $this->makeProject('0.00');
        $svc->createManual($c, ['work_date' => self::BASE_DATE, 'category' => 3, 'cost' => '100.00']);
        $pc = $svc->projectPnl($c);
        self::assertSame('-100.00', $pc['variance']);
        self::assertNull($pc['variance_rate'], '预算 0 偏差率 null');
        self::assertTrue($pc['over_budget']);
        // 偏差率 half-up：预算 3 成本 1 → 2/3×100=66.666…→66.67
        $d = $this->makeProject('3.00');
        $svc->createManual($d, ['work_date' => self::BASE_DATE, 'category' => 2, 'cost' => '1.00']);
        self::assertSame('66.67', $svc->projectPnl($d)['variance_rate']);
    }

    public function testP1DateBoundsNonexistentAndDeleteManualRules(): void
    {
        $p = $this->makeProject();
        $svc = $this->cost();
        $u1 = 3001;
        $this->addMember($p, $u1, '100.00');
        $d = self::BASE_DATE;
        $this->addTimesheet($p, $u1, $this->addDays($d, -1), '1.00'); // 区间外
        $tsIn = $this->addTimesheet($p, $u1, $d, '1.00'); // 区间内（下界含）
        $this->addTimesheet($p, $u1, $this->addDays($d, 1), '1.00'); // 区间内（上界含）
        $this->addTimesheet($p, $u1, $this->addDays($d, 2), '1.00'); // 区间外
        // 区间过滤：from/to 边界含、区间外不取；from==to 单日可跑
        $r1 = $svc->generateFromTimesheet($p, $d, $this->addDays($d, 1));
        self::assertSame(2, $r1['created'], '只取 [d, d+1] 两行');
        self::assertSame(2, $this->countRows('erp_project_cost', 'project_id', $p));
        self::assertSame(1, $svc->generateFromTimesheet($p, $d, $d)['skipped'], 'd 日行已归集');
        $before = $this->countRows('erp_project_cost', 'project_id', $p);
        $this->assertThrowsMessage(fn () => $svc->generateFromTimesheet($p, $this->addDays($d, 1), $d), '起始日期不能晚于截止日期');
        $this->assertThrowsMessage(fn () => $svc->generateFromTimesheet($p, '2026-02-30', $d), '无效的日期区间');
        $this->assertThrowsMessage(fn () => $svc->generateFromTimesheet(99999999, $d, $d), '项目不存在');
        self::assertSame($before, $this->countRows('erp_project_cost', 'project_id', $p), '非法区间零写入');
        // deleteManual：归集行拒删、手工行可删、重复删报不存在
        $tsRow = (int) Capsule::table('erp_project_cost')->where('timesheet_id', $tsIn)->value('id');
        $this->assertThrowsMessage(fn () => $svc->deleteManual($tsRow), '工时归集生成的成本记录不可删除');
        $manual = $svc->createManual($p, ['work_date' => $d, 'category' => 2, 'cost' => '9.90']);
        $svc->deleteManual((int) $manual->id);
        self::assertSame(0, $this->countRows('erp_project_cost', 'id', (int) $manual->id), '手工行删除成功');
        $this->assertThrowsMessage(fn () => $svc->deleteManual((int) $manual->id), '成本记录不存在');
        $this->assertThrowsMessage(fn () => $svc->projectPnl(99999999), '项目不存在');
    }
}
