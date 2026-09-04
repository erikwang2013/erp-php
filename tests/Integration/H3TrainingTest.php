<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\HrCourse;
use app\service\hr\TrainingService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use support\Container;

/**
 * H3 课程体系 集成测试（TrainingService 全方法）
 *
 * 覆盖：createCourse 校验（草稿默认值/部分更新/软删除）、enroll/cancel/complete
 * 状态机守卫、employeeCredits 学分统计（含课程软删除后仍计入）、listCourses 筛选。
 * 断言一律走服务层返回数组的精确值/精确中文消息。
 */
#[Group('integration')]
class H3TrainingTest extends H3H4Scaffold
{
    /** 服务层实例（无参构造，容器 class_exists 回退，与控制器同源）。 */
    private function training(): TrainingService
    {
        return Container::get(TrainingService::class);
    }

    /** 走服务层建课（校验同生产路径；credits/duration_hours 为必填，status 缺省草稿）。 */
    private function seedCourse(array $overrides = []): array
    {
        return $this->training()->createCourse(array_merge([
            'title' => '新员工入职培训',
            'course_type' => 'internal',
            'credits' => '1',
            'duration_hours' => '1.00',
        ], $overrides));
    }

    public function testCreateCourseDefaultsToDraft(): void
    {
        // 最小合法入参：credits/duration_hours 必填，status 缺省落草稿(0)、讲师缺省空串
        $course = $this->seedCourse();
        $this->assertSame('新员工入职培训', $course['title']);
        $this->assertSame('internal', $course['course_type']);
        $this->assertSame(1, $course['credits']);
        $this->assertSame(0, $course['status'], '未传 status 应落草稿(0)');

        // create 返回未 refresh，其余默认列查库断言
        $row = (array) Capsule::table('erp_hr_course')->where('id', (int) $course['id'])->first();
        $this->assertSame(0, (int) $row['status']);
        $this->assertSame('', $row['lecturer']);

        // 必填缺失直抛（credits 校验先于 duration_hours）
        $this->assertServiceThrows(
            fn () => $this->training()->createCourse([
                'title' => '缺学分课程', 'course_type' => 'internal', 'duration_hours' => '1.00',
            ]),
            '学分须为非负整数'
        );
        $this->assertServiceThrows(
            fn () => $this->training()->createCourse([
                'title' => '缺课时课程', 'course_type' => 'internal', 'credits' => '1',
            ]),
            '课时时长格式应为数字（最多两位小数）'
        );
    }

    public function testCreateCourseExplicitStatusAndValues(): void
    {
        $course = $this->seedCourse([
            'status' => 1,
            'credits' => '5',
            'duration_hours' => '4.50',
            'lecturer' => '张老师',
        ]);
        $this->assertSame(1, $course['status'], '显式传 status 应直建上架课程');
        $this->assertSame(5, $course['credits']);
        $this->assertSame(4.5, $course['duration_hours'], 'duration_hours 模型 cast float');
        $this->assertSame('张老师', $course['lecturer']);
    }

    public function testCreateCourseValidationMessages(): void
    {
        $cases = [
            [['title' => ''], '课程标题不能为空'],
            [['title' => str_repeat('课', 101)], '课程标题不能超过 100 字'],
            [['course_type' => 'other'], '课程类型不合法（internal内训/external外训/online线上）'],
            [['lecturer' => str_repeat('师', 101)], '讲师姓名不能超过 100 字'],
            [['credits' => '-1'], '学分须为非负整数'],
            [['credits' => '1.5'], '学分须为非负整数'],
            [['duration_hours' => '1.234'], '课时时长格式应为数字（最多两位小数）'],
            [['duration_hours' => 'abc'], '课时时长格式应为数字（最多两位小数）'],
            [['duration_hours' => '10000'], '课时时长不能超过 9999.99 小时'],
            [['status' => '3'], '课程状态不合法（0草稿/1上架/2下架）'],
        ];
        foreach ($cases as [$overrides, $message]) {
            // 基底补齐必填字段（credits/duration_hours），使每个用例只命中目标字段校验
            $this->assertServiceThrows(fn () => $this->training()->createCourse(array_merge([
                'title' => '合规标题',
                'course_type' => 'internal',
                'credits' => '1',
                'duration_hours' => '1.00',
            ], $overrides)), $message);
        }
    }

    public function testUpdateCoursePartialAndMissing(): void
    {
        $course = $this->seedCourse(['status' => 1, 'credits' => '2', 'lecturer' => '张老师']);
        $updated = $this->training()->updateCourse((int) $course['id'], ['credits' => '8', 'lecturer' => '李讲师']);

        $this->assertSame('新员工入职培训', $updated['title'], '未传字段应保持不变');
        $this->assertSame(8, $updated['credits']);
        $this->assertSame('李讲师', $updated['lecturer']);
        $this->assertSame(1, $updated['status']);

        $this->assertServiceThrows(
            fn () => $this->training()->updateCourse((int) $course['id'], ['status' => '9']),
            '课程状态不合法（0草稿/1上架/2下架）'
        );
        $this->assertServiceThrows(
            fn () => $this->training()->updateCourse(self::nextId(), ['credits' => '1']),
            '课程不存在'
        );
    }

    public function testDestroyCourseSoftDeletes(): void
    {
        $course = $this->seedCourse();
        $this->training()->destroyCourse((int) $course['id']);

        // 模型默认作用域不可见软删行（Capsule::table 裸查询走不到 Eloquent 全局作用域）
        $this->assertSame(0, HrCourse::where('id', (int) $course['id'])->count());
        $this->assertSame(1, Capsule::table('erp_hr_course')
            ->where('id', (int) $course['id'])
            ->whereNotNull('deleted_at')->count(), '应软删除（deleted_at 落值）');
        $this->assertServiceThrows(
            fn () => $this->training()->destroyCourse(self::nextId()),
            '课程不存在'
        );
    }

    public function testEnrollFlowAndGuards(): void
    {
        $employeeId = $this->createEmployee();
        $course = $this->seedCourse(['status' => 1]);

        $enrollment = $this->training()->enroll((int) $course['id'], $employeeId, 20001);
        $this->assertSame((int) $course['id'], $enrollment['course_id']);
        $this->assertSame($employeeId, $enrollment['employee_id']);
        $this->assertSame(0, $enrollment['status'], '报名默认状态 0');
        $this->assertSame(20001, $enrollment['created_by'], '报名记录操作人');

        $this->assertServiceThrows(
            fn () => $this->training()->enroll((int) $course['id'], $employeeId, 20001),
            '该员工已报名该课程（含已完成），请勿重复报名'
        );
    }

    public function testEnrollGuardsEmployeeCourseAndStatus(): void
    {
        $employeeId = $this->createEmployee();
        $published = $this->seedCourse(['status' => 1]);
        $draft = $this->seedCourse(['status' => 0]);
        $offShelf = $this->seedCourse(['status' => 2]);

        // 员工不存在（校验先于课程状态）
        $this->assertServiceThrows(
            fn () => $this->training()->enroll((int) $published['id'], self::nextId(), 0),
            '员工不存在'
        );
        // 课程不存在
        $this->assertServiceThrows(
            fn () => $this->training()->enroll(self::nextId(), $employeeId, 0),
            '课程不存在'
        );
        // 草稿/下架均不可报名
        $this->assertServiceThrows(
            fn () => $this->training()->enroll((int) $draft['id'], $employeeId, 0),
            '该课程未上架，不可报名'
        );
        $this->assertServiceThrows(
            fn () => $this->training()->enroll((int) $offShelf['id'], $employeeId, 0),
            '该课程未上架，不可报名'
        );
    }

    public function testCancelStateMachine(): void
    {
        $employeeId = $this->createEmployee();
        $course = $this->seedCourse(['status' => 1]);

        // 未报名先取消 → 选课记录不存在
        $this->assertServiceThrows(
            fn () => $this->training()->cancel((int) $course['id'], $employeeId, 0),
            '选课记录不存在'
        );

        $this->training()->enroll((int) $course['id'], $employeeId, 20001);
        $canceled = $this->training()->cancel((int) $course['id'], $employeeId, 20001);
        $this->assertSame(2, $canceled['status'], '已报名(0)→已取消(2)');

        // 重复取消
        $this->assertServiceThrows(
            fn () => $this->training()->cancel((int) $course['id'], $employeeId, 0),
            '该选课已取消，请勿重复操作'
        );
        // 已取消的选课不可标记完成
        $this->assertServiceThrows(
            fn () => $this->training()->complete((int) $course['id'], $employeeId, 0),
            '已取消的选课不可标记完成'
        );
    }

    public function testCompleteStateMachine(): void
    {
        $employeeId = $this->createEmployee();
        $course = $this->seedCourse(['status' => 1, 'credits' => '5']);

        $this->training()->enroll((int) $course['id'], $employeeId, 20001);
        $completed = $this->training()->complete((int) $course['id'], $employeeId, 20002);
        $this->assertSame(1, $completed['status']);
        $this->assertNotNull($completed['completed_at'], '完成应写 completed_at');
        $this->assertSame(20002, $completed['created_by'], '完成应覆盖操作人');

        // 重复完成
        $this->assertServiceThrows(
            fn () => $this->training()->complete((int) $course['id'], $employeeId, 0),
            '该选课已完成，请勿重复操作'
        );
        // 已完成不可取消（学分已计入）
        $this->assertServiceThrows(
            fn () => $this->training()->cancel((int) $course['id'], $employeeId, 0),
            '已完成课程不可取消（学分已计入员工学分）'
        );
    }

    public function testEmployeeCreditsAggregation(): void
    {
        $employeeId = $this->createEmployee();
        $courseA = $this->seedCourse(['status' => 1, 'credits' => '5']);
        $courseB = $this->seedCourse(['status' => 1, 'credits' => '3']);
        $courseC = $this->seedCourse(['status' => 1, 'credits' => '2']);
        $courseD = $this->seedCourse(['status' => 1, 'credits' => '1']);

        $this->training()->enroll((int) $courseA['id'], $employeeId, 20001);
        $this->training()->complete((int) $courseA['id'], $employeeId, 20002);
        $this->training()->enroll((int) $courseB['id'], $employeeId, 20001);
        $this->training()->complete((int) $courseB['id'], $employeeId, 20002);
        $this->training()->enroll((int) $courseC['id'], $employeeId, 20001); // 进行中
        $this->training()->enroll((int) $courseD['id'], $employeeId, 20001);
        $this->training()->cancel((int) $courseD['id'], $employeeId, 20003); // 已取消

        $credits = $this->training()->employeeCredits($employeeId);
        $this->assertSame(8, $credits['total_credits'], '只计已完成学分 5+3');
        $this->assertSame(2, $credits['completed_courses']);
        $this->assertSame(1, $credits['in_progress']);
        $this->assertCount(4, $credits['history'], '全部选课入历史（含取消）');

        $completedRows = array_values(array_filter(
            $credits['history'],
            static fn (array $row): bool => $row['status'] === 1
        ));
        $this->assertSame(5, $completedRows[0]['credits']);
        $this->assertSame('已完成', $completedRows[0]['status_text']);
        $this->assertNotNull($completedRows[0]['completed_at']);

        $cancelled = array_values(array_filter(
            $credits['history'],
            static fn (array $row): bool => $row['status'] === 2
        ));
        $this->assertSame(1, $cancelled[0]['credits'], '课程快照随历史保留');
        $this->assertSame('已取消', $cancelled[0]['status_text']);
        $this->assertNull($cancelled[0]['completed_at']);

        $this->assertServiceThrows(
            fn () => $this->training()->employeeCredits(self::nextId()),
            '员工不存在'
        );
    }

    public function testDestroyedCourseCreditsSurvive(): void
    {
        $employeeId = $this->createEmployee();
        $course = $this->seedCourse(['status' => 1, 'credits' => '7', 'title' => '信息安全专项培训']);
        $this->training()->enroll((int) $course['id'], $employeeId, 20001);
        $this->training()->complete((int) $course['id'], $employeeId, 20002);
        $this->training()->destroyCourse((int) $course['id']);

        // 软删除课程：学分仍计入，历史保留课程快照（withTrashed 加载）
        $credits = $this->training()->employeeCredits($employeeId);
        $this->assertSame(7, $credits['total_credits']);
        $this->assertSame('信息安全专项培训', $credits['history'][0]['course_title']);
        $this->assertSame(1, $credits['history'][0]['status']);
        $this->assertSame('已完成', $credits['history'][0]['status_text']);
    }

    public function testListCoursesFilters(): void
    {
        $this->seedCourse(['title' => '新员工入职引导', 'course_type' => 'internal', 'status' => 1, 'credits' => '2', 'lecturer' => '张老师']);
        $this->seedCourse(['title' => '渠道外训交流', 'course_type' => 'external', 'status' => 0, 'credits' => '5', 'lecturer' => '王老师']);
        $this->seedCourse(['title' => '信息安全培训', 'course_type' => 'online', 'status' => 1, 'credits' => '1', 'lecturer' => '李强']);

        // 无筛选全量
        $all = $this->training()->listCourses([]);
        $this->assertSame(3, $all['total']);

        // 状态过滤
        $published = $this->training()->listCourses(['status' => 1]);
        $this->assertSame(2, $published['total']);

        // 类型过滤
        $external = $this->training()->listCourses(['course_type' => 'external']);
        $this->assertSame(1, $external['total']);

        // 最低学分（含等值）
        $high = $this->training()->listCourses(['min_credits' => 5]);
        $this->assertSame(1, $high['total']);
        $this->assertSame('渠道外训交流', $high['list'][0]['title']);

        // 关键词命中标题
        $byTitle = $this->training()->listCourses(['keyword' => '安全']);
        $this->assertSame(1, $byTitle['total']);
        $this->assertSame('信息安全培训', $byTitle['list'][0]['title']);

        // 关键词命中讲师
        $byLecturer = $this->training()->listCourses(['keyword' => '李强']);
        $this->assertSame(1, $byLecturer['total']);

        // 组合筛选 + id 倒序
        $combo = $this->training()->listCourses(['status' => 1, 'course_type' => 'internal']);
        $this->assertSame(1, $combo['total']);
        $this->assertSame('新员工入职引导', $combo['list'][0]['title']);

        // 软删除课程不进列表
        $deleted = $this->training()->listCourses(['status' => 1]);
        $this->training()->destroyCourse((int) $deleted['list'][0]['id']);
        $this->assertSame(1, $this->training()->listCourses(['status' => 1])['total']);
    }
}
