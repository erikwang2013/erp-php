<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\model\ProjectCost;
use app\service\project\ProjectCostService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use support\Container;
use Throwable;

/**
 * P1 项目成本归集与预算偏差 集成测试（真库，属 --group=integration）。
 *
 * 表处理双模式（同 P1M1M2CostingScaffold）：自有表 erp_project_cost 缺表时
 * 最小化创建并 tearDown 删除；依赖表（install.sql 项目主档 + 本次
 * database/e1p1.sql 的两处 ALTER 列）从不创建——budget_amount/hourly_rate
 * 列缺失即整类跳过并提示先导入 e1p1.sql。
 *
 * 清理纪律：造数一律雪花合成 ID（绝不触碰真实数据），tearDown 按登记的
 * 本类项目 ID 级联清成本/成员/工时行——雪花项目 ID 不可能被他人引用。
 * 日期一律相对本周一推进，日历无关。金额断言全为 DECIMAL 读出的字符串。
 */
#[Group('integration')]
class P1ProjectCostTest extends IntegrationTestCase
{
    /** 自有表（database/e1p1.sql 新建）— 缺表时最小化创建并登记，tearDown 删除 */
    private const OWN_TABLES = ['erp_project_cost'];
    /** 依赖表（install.sql）— 只读使用，绝不创建 */
    private const DEP_TABLES = [
        'erp_project',
        'erp_project_member',
        'erp_project_timesheet',
    ];
    /** e1p1.sql ALTER 追加列 — 缺失说明库未导本次 DDL，整类跳过 */
    private const ALTER_COLUMNS = [
        'erp_project' => ['budget_amount'],
        'erp_project_member' => ['hourly_rate'],
    ];

    private array $createdTables = [];
    private array $projectIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = $this->projectIds = [];
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
        $this->createTableIfMissing($table, static function (Blueprint $t): void {
            $t->unsignedBigInteger('id')->primary();
            $t->unsignedBigInteger('project_id')->nullable();
            $t->unsignedBigInteger('task_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->date('work_date')->nullable();
            $t->string('source_type', 20)->nullable();
            $t->unsignedBigInteger('timesheet_id')->nullable();
            $t->tinyInteger('category')->nullable();
            $t->decimal('hours', 12, 2)->nullable();
            $t->decimal('rate', 12, 2)->nullable();
            $t->decimal('cost', 14, 2)->nullable();
            $t->string('remark', 500)->nullable();
            $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable();
        });
        $this->createdTables[] = $table;
    }

    // ---------- 造数（全部登记，仅本类 ID） ----------

    protected function nextId(): int
    {
        return SnowflakeService::generate();
    }

    protected function createProject(string $budget = '0.00'): int
    {
        $id = $this->nextId();
        Capsule::table('erp_project')->insert([
            'id' => $id,
            'code' => 'PRJ-' . $id,
            'name' => '成本项目-' . $id,
            'manager_user_id' => 0,
            'status' => 0,
            'budget_amount' => $budget,
        ]);
        $this->projectIds[] = $id;

        return $id;
    }

    /** 项目成员（hourly_rate 缺省 0.00 → 未配置费率拒绝路径） */
    protected function addMember(int $projectId, int $userId, string $hourlyRate = '0.00'): void
    {
        Capsule::table('erp_project_member')->insert([
            'id' => $this->nextId(),
            'project_id' => $projectId,
            'user_id' => $userId,
            'hourly_rate' => $hourlyRate,
        ]);
    }

    protected function addTimesheet(int $projectId, int $userId, string $workDate, string $hours): int
    {
        $id = $this->nextId();
        Capsule::table('erp_project_timesheet')->insert([
            'id' => $id,
            'project_id' => $projectId,
            'user_id' => $userId,
            'hours' => $hours,
            'work_date' => $workDate,
            'description' => '',
        ]);

        return $id;
    }

    protected function cost(): ProjectCostService
    {
        return Container::get(ProjectCostService::class);
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

    // ---------- 用例 ----------

    #[TestDox('手工人工成本精度：133.74×8.5 → 1136.79 字符串，费率工时快照落库')]
    public function testManualLaborPrecision(): void
    {
        $projectId = $this->createProject('5000.00');
        $row = $this->cost()->createManual($projectId, [
            'work_date' => $this->monday(),
            'category' => ProjectCostService::CATEGORY_LABOR,
            'hours' => '8.5',
            'rate' => '133.74',
            'remark' => ' 加班检修 ',
        ]);

        self::assertSame('1136.79', (string) $row->cost, 'bc_round(133.74×8.5) half-up');
        self::assertSame('8.50', (string) $row->hours, '工时归一 2 位小数');
        self::assertSame('133.74', (string) $row->rate);
        self::assertSame(ProjectCostService::SOURCE_MANUAL, (string) $row->source_type);
        self::assertSame('加班检修', (string) $row->remark, '备注去首尾空白');
        self::assertSame($projectId, (int) $row->project_id);
        $db = Capsule::table('erp_project_cost')->where('id', $row->id)->value('cost');
        self::assertSame('1136.79', (string) $db, 'DECIMAL 落库为精确字符串');
    }

    #[TestDox('手工录入校验：非法日期/类别/工时/费率/金额全拒绝，零残留行（无半截行）')]
    public function testManualValidationNoOrphanRow(): void
    {
        $projectId = $this->createProject('5000.00');
        $svc = $this->cost();
        $cases = [
            [['work_date' => '2026-02-30', 'category' => 1, 'hours' => '1', 'rate' => '1'], '无效的发生日期'],
            [['work_date' => $this->monday(), 'category' => 9, 'hours' => '1'], '无效的成本类别'],
            [['work_date' => $this->monday(), 'category' => 1, 'hours' => '', 'rate' => '1'], '工时格式无效'],
            [['work_date' => $this->monday(), 'category' => 1, 'hours' => '0', 'rate' => '1'], '工时必须大于0'],
            [['work_date' => $this->monday(), 'category' => 1, 'hours' => '1.5.2', 'rate' => '1'], '工时格式无效'],
            [['work_date' => $this->monday(), 'category' => 1, 'hours' => '1', 'rate' => '-1'], '费率格式无效'],
            [['work_date' => $this->monday(), 'category' => 2, 'cost' => ''], '成本金额格式无效'],
            [['work_date' => $this->monday(), 'category' => 3, 'cost' => '0'], '成本金额必须大于0'],
        ];
        foreach ($cases as [$data, $message]) {
            $this->assertThrowsMessage(fn () => $svc->createManual($projectId, $data), $message);
        }
        self::assertSame(0, ProjectCost::query()->where('project_id', $projectId)->count(), '校验失败不留半截行');
        $this->assertThrowsMessage(
            fn () => $svc->createManual($this->nextId(), ['work_date' => $this->monday(), 'category' => 2, 'cost' => '1']),
            '项目不存在',
        );
    }

    #[TestDox('材料/其他为直接金额列，金额列一律 DECIMAL 字符串且不入工时费率')]
    public function testManualMaterialAndOther(): void
    {
        $projectId = $this->createProject('0.00');
        $svc = $this->cost();
        $mon = $this->monday();

        $material = $svc->createManual($projectId, [
            'work_date' => $mon, 'category' => ProjectCostService::CATEGORY_MATERIAL, 'cost' => '599.99', 'remark' => '备件',
        ]);
        self::assertSame('599.99', (string) $material->cost);
        self::assertSame('0.00', (string) $material->hours, '材料行不写工时');
        self::assertSame('0.00', (string) $material->rate);

        $other = $svc->createManual($projectId, [
            'work_date' => $this->addDays($mon, 1), 'category' => ProjectCostService::CATEGORY_OTHER, 'cost' => '150.5',
        ]);
        self::assertSame('150.50', (string) $other->cost, '金额归一 2 位小数');
    }

    #[TestDox('工时归集：幂等重跑零新增；零工时跳过；未入队/零费率成员逐行拒绝并给原因')]
    public function testGenerateFromTimesheetIdempotentWithRefusals(): void
    {
        $projectId = $this->createProject('0.00');
        $mon = $this->monday();
        $memberA = $this->nextId();
        $memberB = $this->nextId();          // 成员但费率 0
        $outsider = $this->nextId();         // 有工时但非成员
        $this->addMember($projectId, $memberA, '50.00');
        $this->addMember($projectId, $memberB);
        $svc = $this->cost();

        $s1 = $this->addTimesheet($projectId, $memberA, $mon, '3.25');                 // → created 162.50
        $s2 = $this->addTimesheet($projectId, $memberA, $this->addDays($mon, 1), '0.00'); // 工时为0 跳过
        $s3 = $this->addTimesheet($projectId, $memberB, $this->addDays($mon, 2), '4.00'); // 费率0 拒绝
        $s4 = $this->addTimesheet($projectId, $outsider, $this->addDays($mon, 2), '2.00'); // 非成员 拒绝
        $s5 = $this->addTimesheet($projectId, $memberA, $this->addDays($mon, 2), '2.00');  // → created 100.00

        $r = $svc->generateFromTimesheet($projectId, $mon, $this->addDays($mon, 2));
        self::assertSame(2, $r['created']);
        self::assertSame(1, $r['skipped']);
        self::assertSame(2, $r['refused']);
        self::assertCount(5, $r['details']);
        self::assertSame('created', $r['details'][0]['status']);
        self::assertSame('162.50', (string) $r['details'][0]['cost']);
        self::assertSame('50.00', (string) $r['details'][0]['rate']);
        self::assertSame('skipped', $r['details'][1]['status']);
        self::assertSame('工时为0', $r['details'][1]['message']);
        self::assertSame('refused', $r['details'][2]['status']);
        self::assertSame("成员 {$memberB} 未配置费率", $r['details'][2]['message']);
        self::assertSame('refused', $r['details'][3]['status']);
        self::assertSame("成员 {$outsider} 未加入项目", $r['details'][3]['message']);
        self::assertSame('created', $r['details'][4]['status']);
        self::assertSame('100.00', (string) $r['details'][4]['cost']);

        // 重跑幂等：两行已归集 + 零工时行 + 两个拒绝行，均不再新增成本
        $r2 = $svc->generateFromTimesheet($projectId, $mon, $this->addDays($mon, 2));
        self::assertSame(0, $r2['created'], '重跑零新增');
        self::assertSame(3, $r2['skipped']);
        self::assertSame(2, $r2['refused']);
        self::assertSame('已归集', $r2['details'][0]['message']);
        self::assertSame(
            2,
            ProjectCost::query()->where('project_id', $projectId)->where('source_type', ProjectCostService::SOURCE_TIMESHEET)->count(),
            '成本行仍为 2 条',
        );
        self::assertNotNull(Capsule::table('erp_project_cost')->where('timesheet_id', $s1)->first());
        self::assertNotNull(Capsule::table('erp_project_cost')->where('timesheet_id', $s5)->first());

        // 区间颠倒与幽灵项目
        $this->assertThrowsMessage(
            fn () => $svc->generateFromTimesheet($projectId, $this->addDays($mon, 3), $mon),
            '起始日期不能晚于截止日期',
        );
        $this->assertThrowsMessage(
            fn () => $svc->generateFromTimesheet($projectId, '2026-02-30', '2026-02-30'),
            '无效的日期区间',
        );
        $this->assertThrowsMessage(
            fn () => $svc->generateFromTimesheet($this->nextId(), $mon, $mon),
            '项目不存在',
        );
    }

    #[TestDox('删除仅限手工行：归集行/幽灵行拒绝，手工行删除即消失')]
    public function testDeleteManualOnly(): void
    {
        $projectId = $this->createProject('0.00');
        $mon = $this->monday();
        $userId = $this->nextId();
        $this->addMember($projectId, $userId, '50.00');
        $svc = $this->cost();

        $manual = $svc->createManual($projectId, [
            'work_date' => $mon, 'category' => ProjectCostService::CATEGORY_MATERIAL, 'cost' => '88.00',
        ]);
        $this->addTimesheet($projectId, $userId, $mon, '2.00');
        $svc->generateFromTimesheet($projectId, $mon, $mon);
        $generated = ProjectCost::query()
            ->where('project_id', $projectId)->where('source_type', ProjectCostService::SOURCE_TIMESHEET)->first();

        $this->assertThrowsMessage(fn () => $svc->deleteManual((int) $generated->id), '工时归集生成的成本记录不可删除');
        $this->assertThrowsMessage(fn () => $svc->deleteManual($this->nextId()), '成本记录不存在');

        $svc->deleteManual((int) $manual->id);
        self::assertNull(ProjectCost::query()->find((int) $manual->id), '手工行删除成功');
    }

    #[TestDox('损益：分类汇总/预算偏差率（预算0→null）/超支标记/人工明细含来源')]
    public function testPnlBudgetVarianceRollup(): void
    {
        $projectId = $this->createProject('2000.00');
        $mon = $this->monday();
        $userId = $this->nextId();
        $this->addMember($projectId, $userId, '50.00');
        $svc = $this->cost();

        // 人工：手工 850.00 + 归集 162.50 + 100.00 = 1112.50
        $svc->createManual($projectId, [
            'work_date' => $mon, 'category' => ProjectCostService::CATEGORY_LABOR, 'hours' => '8.5', 'rate' => '100.00',
        ]);
        $this->addTimesheet($projectId, $userId, $mon, '3.25');
        $this->addTimesheet($projectId, $userId, $this->addDays($mon, 1), '2.00');
        $svc->generateFromTimesheet($projectId, $mon, $this->addDays($mon, 2));
        // 材料 500.00 + 其他 250.50
        $svc->createManual($projectId, [
            'work_date' => $this->addDays($mon, 1), 'category' => ProjectCostService::CATEGORY_MATERIAL, 'cost' => '500.00',
        ]);
        $svc->createManual($projectId, [
            'work_date' => $this->addDays($mon, 2), 'category' => ProjectCostService::CATEGORY_OTHER, 'cost' => '250.50',
        ]);

        $pnl = $svc->projectPnl($projectId);
        self::assertSame('2000.00', $pnl['budget_amount']);
        self::assertSame('1863.00', $pnl['total_cost'], '850+162.5+100+500+250.5');
        self::assertSame('137.00', $pnl['variance']);
        self::assertSame('6.85', $pnl['variance_rate'], '137/2000×100 精确');
        self::assertFalse($pnl['over_budget']);
        $expectCats = [
            ['category' => 1, 'category_name' => '人工', 'cost' => '1112.50'],
            ['category' => 2, 'category_name' => '材料', 'cost' => '500.00'],
            ['category' => 3, 'category_name' => '其他', 'cost' => '250.50'],
        ];
        self::assertSame($expectCats, $pnl['cost_by_category']);
        self::assertCount(3, $pnl['labour_details'], '手工+归集共 3 条人工明细');
        self::assertSame('manual', $pnl['labour_details'][0]['source_type']);
        self::assertSame('timesheet', $pnl['labour_details'][1]['source_type']);

        // 预算 0：偏差率 null、超支标记 true、负偏差
        $zeroBudget = $this->createProject('0.00');
        $zeroUser = $this->nextId();
        $this->addMember($zeroBudget, $zeroUser, '50.00');
        $this->addTimesheet($zeroBudget, $zeroUser, $mon, '10.00');
        $svc->generateFromTimesheet($zeroBudget, $mon, $mon);
        $pnl0 = $svc->projectPnl($zeroBudget);
        self::assertSame('0.00', $pnl0['budget_amount']);
        self::assertSame('500.00', $pnl0['total_cost']);
        self::assertSame('-500.00', $pnl0['variance']);
        self::assertNull($pnl0['variance_rate'], '预算为 0 时偏差率为 null');
        self::assertTrue($pnl0['over_budget'], '超支不阻断，仅标记');

        $this->assertThrowsMessage(fn () => $svc->projectPnl($this->nextId()), '项目不存在');
    }
}
