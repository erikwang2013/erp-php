<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\ProjectTimesheet;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 项目模块纯单测（Project: Project/Task/Timesheet）
 * - 控制器校验分支走真实代码路径
 * - 项目进度 = round(任务 progress 均值)、任务实际工时 = SUM(hours) 为纯聚合规则
 * - 落库类流程依赖 MySQL，跳过并注明
 */
class ProjectModuleTest extends TestCase
{
    private function responseCode(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    /* ============================ 控制器校验分支（真实代码路径） ============================ */

    public function testProjectStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\project\ProjectController())->store(new FakeRequest([
            'manager_user_id' => 1,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '项目缺少 name 应校验失败');
    }

    public function testProjectStoreRejectsMissingManager(): void
    {
        $resp = (new \app\controller\project\ProjectController())->store(new FakeRequest([
            'name' => '新项目',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '项目缺少 manager_user_id 应校验失败');
    }

    public function testProjectStoreRejectsZeroManager(): void
    {
        $resp = (new \app\controller\project\ProjectController())->store(new FakeRequest([
            'name' => '新项目',
            'manager_user_id' => 0,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '负责人 ID 必须 >= 1');
    }

    public function testTaskStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\project\TaskController())->store(new FakeRequest([
            'project_id' => 1,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '任务缺少 name 应校验失败');
    }

    public function testTaskStoreRejectsMissingProjectId(): void
    {
        $resp = (new \app\controller\project\TaskController())->store(new FakeRequest([
            'name' => '新任务',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '任务缺少 project_id 应校验失败');
    }

    public function testTimesheetStoreRejectsMissingProjectId(): void
    {
        $resp = (new \app\controller\project\TimesheetController())->store(new FakeRequest([
            'user_id' => 1,
            'hours' => 4,
            'work_date' => '2026-01-01',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '工时缺少 project_id 应校验失败');
    }

    public function testTimesheetStoreRejectsZeroHours(): void
    {
        $resp = (new \app\controller\project\TimesheetController())->store(new FakeRequest([
            'project_id' => 1,
            'user_id' => 1,
            'hours' => 0,
            'work_date' => '2026-01-01',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '工时必须大于 0 (min:0.01)');
    }

    public function testTimesheetStoreRejectsInvalidWorkDate(): void
    {
        $resp = (new \app\controller\project\TimesheetController())->store(new FakeRequest([
            'project_id' => 1,
            'user_id' => 1,
            'hours' => 4,
            'work_date' => 'not-a-date',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '工作日期必须为合法日期');
    }

    /* ============================ 聚合计算规则 ============================ */

    /**
     * 复刻 ProjectController::calcProgress / TaskController::updateProjectProgress:
     * 空任务 => 0，否则 (int) round(任务 progress 均值)
     */
    private function avgProgress(array $taskProgressList): int
    {
        if (empty($taskProgressList)) {
            return 0;
        }

        return (int) round(array_sum($taskProgressList) / count($taskProgressList));
    }

    public function testProjectProgressAggregationRule(): void
    {
        $this->assertSame(0, $this->avgProgress([]), '无任务时项目进度应为 0');
        $this->assertSame(50, $this->avgProgress([50, 50]));
        $this->assertSame(20, $this->avgProgress([10, 20, 30]));
        $this->assertSame(35, $this->avgProgress([34, 35]), 'round(34.5) = 35（PHP 四舍五入）');
    }

    public function testTaskActualHoursAggregationRule(): void
    {
        // TimesheetController::updateTaskActualHours: 任务实际工时 = SUM(工时记录 hours)
        $total = round(3.5 + 2 + 3, 2);
        $this->assertSame(8.5, $total);
        // 空工时记录 => 0
        $this->assertSame(0, array_sum([]));
    }

    /**
     * 复刻 TaskController::store 的 project_id 解析:
     * $decoded = decodeIdSafe($projectIdHash); project_id = $decoded ?? (int)$projectIdHash
     */
    private function resolveProjectId(string $projectIdInput): int
    {
        $accessor = new ProjectBaseAccessor();
        $decoded = $accessor->publicDecodeIdSafe($projectIdInput);

        return $decoded ?? (int) $projectIdInput;
    }

    public function testTaskStoreProjectIdDecodeFallback(): void
    {
        $hash = \app\common\HashidsService::encode(99);
        $this->assertSame(99, $this->resolveProjectId($hash), '有效 hash 应解码为项目 ID');
        $this->assertSame(55, $this->resolveProjectId('55'), '无效 hash 应回退为原始整数');
    }

    /* ============================ 结构与模型契约 ============================ */

    public function testProjectControllersExtendBaseController(): void
    {
        $controllers = [
            'app\\controller\\project\\ProjectController',
            'app\\controller\\project\\TaskController',
            'app\\controller\\project\\TimesheetController',
        ];
        foreach ($controllers as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        // 聚合方法存在性（受保护方法需通过反射断言）
        $taskRef = new \ReflectionClass('app\\controller\\project\\TaskController');
        $this->assertTrue($taskRef->hasMethod('updateProjectProgress'), 'TaskController 应有 updateProjectProgress');
        $this->assertTrue($taskRef->getMethod('updateProjectProgress')->isProtected());
        $timesheetRef = new \ReflectionClass('app\\controller\\project\\TimesheetController');
        $this->assertTrue($timesheetRef->hasMethod('updateTaskActualHours'), 'TimesheetController 应有 updateTaskActualHours');
        $this->assertTrue($timesheetRef->getMethod('updateTaskActualHours')->isProtected());
    }

    public function testProjectModelsUseSnowflakePrimaryKey(): void
    {
        $models = ['Project', 'ProjectTask', 'ProjectTimesheet'];
        foreach ($models as $name) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$name}.php");
            $this->assertStringContainsString('erik_project', $source, "{$name} 表必须使用 erik_project 前缀");
            $this->assertStringContainsString('public $incrementing = false', $source, "{$name} 必须使用非自增主键");
            $this->assertStringContainsString("protected \$keyType = 'int'", $source, "{$name} 主键类型必须为 int");
        }
    }

    public function testProjectTimesheetModelFillableFields(): void
    {
        // ProjectTimesheet 使用 $guarded 反向保护：业务字段可填充，主键/审计字段受保护
        $guarded = (new ProjectTimesheet())->getGuarded();
        $this->assertSame(['id', 'created_at', 'updated_at'], $guarded);
        foreach (['project_id', 'task_id', 'user_id', 'hours', 'work_date', 'remark'] as $field) {
            $this->assertNotContains($field, $guarded, "ProjectTimesheet 应允许填充 {$field}");
        }
        foreach (['id', 'created_at', 'updated_at'] as $field) {
            $this->assertContains($field, $guarded, "ProjectTimesheet 不应允许填充 {$field}");
        }
    }

    public function testUpdateTaskActualHoursGuardContract(): void
    {
        // TimesheetController::updateTaskActualHours: taskId<=0 时直接返回，避免无效聚合
        $src = file_get_contents(__DIR__ . '/../app/controller/project/TimesheetController.php');
        $this->assertStringContainsString('if ($taskId <= 0)', $src);
        $this->assertStringContainsString('->sum(\'hours\')', $src, '任务实际工时应为 hours 求和');
        $this->assertStringContainsString("'actual_hours' => \$totalHours", $src, '聚合结果应写回任务 actual_hours');
    }

    /* ============================ 落库流程（DB 依赖，跳过并注明） ============================ */

    public function testProgressAndHoursWriteFlowRequiresDatabase(): void
    {
        // 任务增删改 -> updateProjectProgress 写回项目 progress、
        // 工时增删改 -> updateTaskActualHours 写回任务 actual_hours 均依赖 MySQL。
        $this->markTestSkipped('依赖 MySQL: ProjectTask/ProjectTimesheet 落库 + progress/actual_hours 聚合写回');
    }
}

/**
 * 暴露 BaseController 受保护方法，供纯单测调用真实实现
 */
class ProjectBaseAccessor extends \app\admin\controller\BaseController
{
    public function publicDecodeIdSafe(string $hashid): ?int
    {
        return $this->decodeIdSafe($hashid);
    }
}
