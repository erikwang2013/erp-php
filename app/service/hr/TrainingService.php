<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrCourse;
use app\model\HrCourseEnrollment;
use app\model\HrEmployee;
use app\service\AbstractCrudService;
use InvalidArgumentException;

/**
 * H3 课程体系：课程 CRUD + 报名/完成/取消 + 员工学分统计
 *
 * 状态机：
 *   课程 status：0 草稿 / 1 上架 / 2 下架
 *     - 报名仅允许 1 上架课程；草稿/下架课程只拦「新报名」；
 *     - 已完成的学分与进行中选课不受下架影响（下架不回收，也不回滚完成状态）。
 *   选课 enrollment.status：0 已报名 / 1 已完成 / 2 已取消
 *     - 0→1 完成（写 completed_at）、0→2 取消；不存在其他流转；
 *     - 已完成(1)不可取消（学分已计入员工学分）；已取消(2)不可完成；
 *     - UNIQUE(course_id, employee_id)：同员工同课程仅一条选课，已取消亦占名额，
 *       重复报名一律拒绝（如需重报请走取消→再报名的产品流程扩展）。
 *
 * 课程软删除（HrCourse SoftDeletes）：删除课程不抹除学分历史
 *   —— 学分统计与选课历史查询均带 withTrashed 关联课程。
 * 金额/学分数值一律字符串校验 + 库内整数/小数存储，运算不做 float 比较。
 */
class TrainingService extends AbstractCrudService
{
    /** @var array<int, string> */
    public const COURSE_STATUS_TEXT = [0 => '草稿', 1 => '上架', 2 => '下架'];

    /** @var array<int, string> */
    public const ENROLL_STATUS_TEXT = [0 => '已报名', 1 => '已完成', 2 => '已取消'];

    /** @var array<string, string> */
    private const COURSE_TYPE_TEXT = ['internal' => '内训', 'external' => '外训', 'online' => '线上'];

    private const COURSE_TYPE_ALLOWED = 'internal内训/external外训/online线上';

    /** 数量正则：非负整数（学分）。 */
    private const INT_REGEX = '/^(0|[1-9]\d*)$/';

    /** 金额/时长正则：非负小数，最多两位小数。 */
    private const DEC2_REGEX = '/^\d+(\.\d{1,2})?$/';

    public function __construct()
    {
        // 保持无参构造（AbstractCrudService 约定，容器 class_exists 回退实例化）。
    }

    /**
     * 课程分页列表（支持 status / course_type / min_credits / keyword 筛选）
     *
     * @param array<string, mixed> $filters 过滤条件：status / course_type / min_credits / keyword
     * @return array{list: array, total: int, page: int, limit: int}
     */
    public function listCourses(array $filters, int $page = 1, int $limit = 15): array
    {
        [$page, $limit] = $this->normalizePageParams($page, $limit);
        $query = HrCourse::query();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")->orWhere('lecturer', 'like', "%{$keyword}%");
            });
        }
        $status = $filters['status'] ?? '';
        if ($status !== '') {
            $query->where('status', (int) $status);
        }
        $courseType = (string) ($filters['course_type'] ?? '');
        if ($courseType !== '') {
            $query->where('course_type', $courseType);
        }
        $minCredits = (int) ($filters['min_credits'] ?? 0);
        if ($minCredits > 0) {
            $query->where('credits', '>=', $minCredits);
        }
        $total = (int) $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get()->toArray();

        return ['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    /**
     * 新建课程
     *
     * @param array<string, mixed> $data title/course_type/lecturer/credits/duration_hours/status
     * @return array 课程（含 id）
     */
    public function createCourse(array $data): array
    {
        $this->validateCourseData($data, false);
        // defaultsOverride=false：未传 status 时落草稿(0)，显式传 status 则允许直建上架课程。
        $item = $this->create(HrCourse::class, $data, ['status' => 0], false);

        return $item->toArray();
    }

    /**
     * 更新课程（可部分更新；不校验报名状态，下架仅拦截新报名见 enroll()）
     */
    public function updateCourse(int $id, array $data): array
    {
        $this->validateCourseData($data, true);
        $item = $this->update(HrCourse::class, $id, $data);
        if (!$item) {
            throw new InvalidArgumentException('课程不存在');
        }

        return $item->toArray();
    }

    /** 软删除课程（学分/选课历史保留，见类 docblock）。 */
    public function destroyCourse(int $id): void
    {
        if (!$this->delete(HrCourse::class, $id)) {
            throw new InvalidArgumentException('课程不存在');
        }
    }

    /**
     * 员工报名课程（0→创建）
     *
     * @param int $courseId 课程ID
     * @param int $employeeId 员工ID
     * @param int $operatorId 操作人ID（admin 或员工本人）
     */
    public function enroll(int $courseId, int $employeeId, int $operatorId): array
    {
        $this->assertEmployeeExists($employeeId);
        $course = HrCourse::find($courseId);
        if (!$course) {
            throw new InvalidArgumentException('课程不存在');
        }
        if ((int) $course->status !== 1) {
            throw new InvalidArgumentException('该课程未上架，不可报名');
        }
        $exists = HrCourseEnrollment::where('course_id', $courseId)
            ->where('employee_id', $employeeId)->exists();
        if ($exists) {
            throw new InvalidArgumentException('该员工已报名该课程（含已完成），请勿重复报名');
        }

        $enrollment = $this->create(
            HrCourseEnrollment::class,
            ['course_id' => $courseId, 'employee_id' => $employeeId, 'created_by' => $operatorId],
            ['status' => 0]
        );

        return $enrollment->toArray();
    }

    /**
     * 取消报名（0→2）。已完成(1)的选课不可取消（学分已计入）。
     */
    public function cancel(int $courseId, int $employeeId, int $operatorId): array
    {
        $enrollment = $this->findEnrollment($courseId, $employeeId);
        $status = (int) $enrollment->status;
        if ($status === 1) {
            throw new InvalidArgumentException('已完成课程不可取消（学分已计入员工学分）');
        }
        if ($status === 2) {
            throw new InvalidArgumentException('该选课已取消，请勿重复操作');
        }
        $enrollment->status = 2;
        $enrollment->save();

        return $enrollment->toArray();
    }

    /**
     * 标记完成（0→1，写 completed_at）。已取消(2)的选课不可标记完成。
     */
    public function complete(int $courseId, int $employeeId, int $operatorId): array
    {
        $enrollment = $this->findEnrollment($courseId, $employeeId);
        $status = (int) $enrollment->status;
        if ($status === 1) {
            throw new InvalidArgumentException('该选课已完成，请勿重复操作');
        }
        if ($status === 2) {
            throw new InvalidArgumentException('已取消的选课不可标记完成');
        }
        $enrollment->status = 1;
        $enrollment->completed_at = date('Y-m-d H:i:s');
        $enrollment->created_by = $operatorId;
        $enrollment->save();

        return $enrollment->toArray();
    }

    /**
     * 员工学分统计（含软删除课程的学分历史）
     *
     * @return array{total_credits:int, completed_courses:int, in_progress:int, history:array}
     *   - total_credits: 已完成选课学分合计（课程软删除后仍计入）
     *   - completed_courses / in_progress: 选课状态计数
     *   - history: 全部选课（按选课创建先后），含状态文案与课程快照
     */
    public function employeeCredits(int $employeeId): array
    {
        $this->assertEmployeeExists($employeeId);
        $enrollments = HrCourseEnrollment::where('employee_id', $employeeId)
            ->orderBy('id', 'asc')->get();
        $courseIds = $enrollments->pluck('course_id')->unique()->all();
        $courses = [];
        if ($courseIds !== []) {
            $courses = HrCourse::withTrashed()->whereIn('id', $courseIds)->get()->keyBy('id')->all();
        }

        $totalCredits = 0;
        $completed = 0;
        $inProgress = 0;
        $history = [];
        foreach ($enrollments as $enrollment) {
            $status = (int) $enrollment->status;
            /** @var HrCourse|null $course */
            $course = $courses[$enrollment->course_id] ?? null;
            if ($status === 1) {
                $completed++;
                $totalCredits += $course ? (int) $course->credits : 0;
            } elseif ($status === 0) {
                $inProgress++;
            }
            $history[] = [
                'enrollment_id' => (int) $enrollment->id,
                'course_id' => (int) $enrollment->course_id,
                'course_title' => $course ? (string) $course->title : '',
                'course_type' => $course ? (string) $course->course_type : '',
                'credits' => $course ? (int) $course->credits : 0,
                'status' => $status,
                'status_text' => self::ENROLL_STATUS_TEXT[$status],
                'completed_at' => $enrollment->completed_at,
            ];
        }

        return [
            'total_credits' => $totalCredits,
            'completed_courses' => $completed,
            'in_progress' => $inProgress,
            'history' => $history,
        ];
    }

    /**
     * 课程校验（新建全量 / 更新仅校验传入字段）
     *
     * @param array<string, mixed> $data
     */
    private function validateCourseData(array $data, bool $partial): void
    {
        $check = static function (string $field) use ($data, $partial): bool {
            return !$partial || array_key_exists($field, $data);
        };

        if ($check('title')) {
            $title = trim((string) ($data['title'] ?? ''));
            if ($title === '') {
                throw new InvalidArgumentException('课程标题不能为空');
            }
            if (mb_strlen($title) > 100) {
                throw new InvalidArgumentException('课程标题不能超过 100 字');
            }
        }
        if ($check('course_type')) {
            $courseType = (string) ($data['course_type'] ?? '');
            if (!isset(self::COURSE_TYPE_TEXT[$courseType])) {
                throw new InvalidArgumentException('课程类型不合法（' . self::COURSE_TYPE_ALLOWED . '）');
            }
        }
        if ($check('lecturer')) {
            $lecturer = trim((string) ($data['lecturer'] ?? ''));
            if (mb_strlen($lecturer) > 100) {
                throw new InvalidArgumentException('讲师姓名不能超过 100 字');
            }
        }
        if ($check('credits')) {
            $credits = $data['credits'] ?? null;
            if (!is_numeric($credits) || !preg_match(self::INT_REGEX, (string) $credits)) {
                throw new InvalidArgumentException('学分须为非负整数');
            }
        }
        if ($check('duration_hours')) {
            $duration = (string) ($data['duration_hours'] ?? '');
            if (!preg_match(self::DEC2_REGEX, $duration)) {
                throw new InvalidArgumentException('课时时长格式应为数字（最多两位小数）');
            }
            if (bccomp($duration, '9999.99') > 0) {
                throw new InvalidArgumentException('课时时长不能超过 9999.99 小时');
            }
        }
        if ($check('status')) {
            // 新建未传 status 落草稿(0)：?? 0 与 create() defaultsOverride=false 语义一致
            $status = (int) ($data['status'] ?? 0);
            if (!isset(self::COURSE_STATUS_TEXT[$status])) {
                throw new InvalidArgumentException('课程状态不合法（0草稿/1上架/2下架）');
            }
        }
    }

    /** 员工须存在（未软删除）。 */
    private function assertEmployeeExists(int $employeeId): void
    {
        if (!HrEmployee::find($employeeId)) {
            throw new InvalidArgumentException('员工不存在');
        }
    }

    /** 取选课记录（任状态），不存在抛错。 */
    private function findEnrollment(int $courseId, int $employeeId): HrCourseEnrollment
    {
        $enrollment = HrCourseEnrollment::where('course_id', $courseId)
            ->where('employee_id', $employeeId)->first();
        if (!$enrollment) {
            throw new InvalidArgumentException('选课记录不存在');
        }

        return $enrollment;
    }
}
