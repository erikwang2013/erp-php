<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrAttendance;
use app\model\HrAttendanceRule;
use app\model\HrDepartment;
use app\model\HrEmployee;
use app\model\HrLeave;
use app\model\HrSalary;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use Throwable;

/**
 * 人力资源模块薄服务层（P2-F2）
 *
 * 承接 HR 模块 5 个控制器的模型查询/写入逻辑：
 *  - 通用 CRUD 由 AbstractCrudService 提供；
 *  - 本类沉淀模块特有业务：上下班打卡（迟到/早退自动判定）、请假审批
 *    （批准后自动生成请假考勤）、薪资试算/发放/批量生成等。
 *
 * 纯逻辑助手（computeClockInStatus / computeClockOutStatus / leaveDays /
 * salaryNetSalary / leaveStatusFlow 等）不依赖数据库，可直接单元测试。
 * 业务规则校验失败抛出 InvalidArgumentException，控制器 catch 后映射为 422。
 */
class HrService extends AbstractCrudService
{
    /**
     * 请假状态流转图：0待审批 1已批准 2已驳回
     * from => [允许流转的 to 列表]
     */
    public const LEAVE_STATUS_FLOW = [
        0 => [1, 2],
        1 => [],
        2 => [],
    ];

    /**
     * 请假状态流转图（纯逻辑，可单测）
     *
     * @return array<int, int[]>
     */
    public function leaveStatusFlow(): array
    {
        return self::LEAVE_STATUS_FLOW;
    }

    /**
     * 请假是否可审批（仅待审批状态，纯逻辑，可单测）
     */
    public function canApproveLeave(int $status): bool
    {
        return $status === 0;
    }

    /**
     * 上班打卡迟到判定（纯逻辑，可单测）
     * 打卡时间晚于规则时间且超出宽限分钟数 → 状态 2（迟到），否则 1（正常）。
     *
     * @return array{status: int, late_minutes: int}
     */
    public function computeClockInStatus(string $clockInTime, string $ruleClockInTime, float $lateGrace): array
    {
        $status = 1;
        $lateMinutes = 0.0;
        if ($clockInTime > $ruleClockInTime) {
            $lateMinutes = max(0.0, (strtotime($clockInTime) - strtotime($ruleClockInTime)) / 60);
            if ($lateMinutes > $lateGrace) {
                $status = 2;
            }
        }

        return ['status' => $status, 'late_minutes' => (int) $lateMinutes];
    }

    /**
     * 下班打卡早退判定（纯逻辑，可单测）
     * 打卡时间早于规则时间、超出宽限分钟数且当前状态为正常(1) → 状态 3（早退）。
     *
     * @return array{status: int, early_minutes: int}
     */
    public function computeClockOutStatus(string $clockOutTime, string $ruleClockOutTime, float $earlyGrace, int $currentStatus): array
    {
        $status = $currentStatus;
        $earlyMinutes = 0.0;
        if ($clockOutTime < $ruleClockOutTime) {
            $earlyMinutes = max(0.0, (strtotime($ruleClockOutTime) - strtotime($clockOutTime)) / 60);
            if ($earlyMinutes > $earlyGrace && $status === 1) {
                $status = 3;
            }
        }

        return ['status' => $status, 'early_minutes' => (int) $earlyMinutes];
    }

    /**
     * 生成请假日期区间内的全部日期（含首尾，纯逻辑，可单测）
     * 与旧控制器按 86400 秒步进生成每日考勤的行为一致。
     *
     * @return array<int, string> Y-m-d 日期列表
     */
    public function leaveDays(string $startDate, string $endDate): array
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false || $end < $start) {
            return [];
        }

        $days = [];
        for ($day = $start; $day <= $end; $day += 86400) {
            $days[] = date('Y-m-d', $day);
        }

        return $days;
    }

    /**
     * 实发金额计算（纯逻辑，可单测）
     * 实发 = 基本工资 + 绩效 + 加班 - 扣款 - 个税。
     *
     * @param array<string, mixed> $data
     */
    public function salaryNetSalary(array $data): float
    {
        return (float) ($data['base_salary'] ?? 0)
            + (float) ($data['performance'] ?? 0)
            + (float) ($data['overtime'] ?? 0)
            - (float) ($data['deduction'] ?? 0)
            - (float) ($data['tax'] ?? 0);
    }

    /**
     * 上班打卡：按考勤规则自动判定迟到，当日已有记录则更新上班时间
     *
     * @return array{status: int, late_minutes: int}
     * @throws InvalidArgumentException 员工不存在 / 今日已打过上班卡时抛出
     */
    public function clockIn(int $employeeId): array
    {
        $employee = HrEmployee::find($employeeId);
        if (!$employee) {
            throw new InvalidArgumentException('员工不存在');
        }

        $workDate = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $existing = HrAttendance::where('employee_id', $employeeId)
            ->where('work_date', $workDate)->first();
        if ($existing && $existing->clock_in) {
            throw new InvalidArgumentException('今天已打过上班卡');
        }

        $rule = HrAttendanceRule::orderBy('id', 'asc')->first();
        $status = 1;
        $lateMinutes = 0;
        if ($rule) {
            $result = $this->computeClockInStatus(
                date('H:i:s'),
                (string) $rule->clock_in_time,
                (float) $rule->late_grace
            );
            $status = $result['status'];
            $lateMinutes = $result['late_minutes'];
        }

        if ($existing) {
            $existing->clock_in = $now;
            $existing->rule_id = $rule ? $rule->id : 0;
            $existing->status = $status;
            $existing->late_minutes = $lateMinutes;
            $existing->save();
        } else {
            $attendance = new HrAttendance();
            $attendance->id = $this->generateId();
            $attendance->employee_id = $employeeId;
            $attendance->rule_id = $rule ? $rule->id : 0;
            $attendance->work_date = $workDate;
            $attendance->clock_in = $now;
            $attendance->status = $status;
            $attendance->late_minutes = $lateMinutes;
            $attendance->created_at = $now;
            $attendance->save();
        }

        return ['status' => $status, 'late_minutes' => $lateMinutes];
    }

    /**
     * 下班打卡：按考勤规则自动判定早退
     *
     * @return array{status: int, early_minutes: int}
     * @throws InvalidArgumentException 员工不存在 / 未打上班卡 / 已打过下班卡时抛出
     */
    public function clockOut(int $employeeId): array
    {
        $employee = HrEmployee::find($employeeId);
        if (!$employee) {
            throw new InvalidArgumentException('员工不存在');
        }

        $workDate = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $attendance = HrAttendance::where('employee_id', $employeeId)
            ->where('work_date', $workDate)->first();
        if (!$attendance || !$attendance->clock_in) {
            throw new InvalidArgumentException('请先打上班卡');
        }
        if ($attendance->clock_out) {
            throw new InvalidArgumentException('今天已打过下班卡');
        }

        $rule = HrAttendanceRule::find($attendance->rule_id);
        $status = (int) $attendance->status;
        $earlyMinutes = 0;
        if ($rule) {
            $result = $this->computeClockOutStatus(
                date('H:i:s'),
                (string) $rule->clock_out_time,
                (float) $rule->early_grace,
                $status
            );
            $status = $result['status'];
            $earlyMinutes = $result['early_minutes'];
        }

        $attendance->clock_out = $now;
        $attendance->status = $status;
        $attendance->early_minutes = $earlyMinutes;
        $attendance->save();

        return ['status' => $status, 'early_minutes' => $earlyMinutes];
    }

    /**
     * 审批请假：批准后自动为请假区间内的每一天生成请假考勤记录(status=5)
     *
     * @return HrLeave|null 请假记录不存在返回 null
     * @throws InvalidArgumentException 请假已审批时抛出
     */
    public function approveLeave(int $leaveId, string $action): ?HrLeave
    {
        $leave = HrLeave::find($leaveId);
        if (!$leave) {
            return null;
        }
        if (!$this->canApproveLeave((int) $leave->status)) {
            throw new InvalidArgumentException('该请假申请已审批');
        }

        DB::beginTransaction();
        try {
            $leave->status = $action === 'reject' ? 2 : 1;
            $leave->save();

            if ($leave->status === 1) {
                $days = $this->leaveDays((string) $leave->start_date, (string) $leave->end_date);

                // 一次取回区间内全部考勤记录，按日期索引
                $existingByDate = [];
                foreach (HrAttendance::where('employee_id', $leave->employee_id)
                    ->whereIn('work_date', $days)->get() as $att) {
                    $existingByDate[$att->work_date] = $att;
                }

                $now = date('Y-m-d H:i:s');
                $attendanceRows = [];
                foreach ($days as $dateStr) {
                    $existing = $existingByDate[$dateStr] ?? null;
                    if (!$existing) {
                        $attendanceRows[] = [
                            'id' => $this->generateId(),
                            'employee_id' => $leave->employee_id,
                            'work_date' => $dateStr,
                            'status' => 5,
                            'created_at' => $now,
                        ];
                    } elseif ($existing->status == 4) {
                        $existing->status = 5;
                        $existing->save();
                    }
                }
                if ($attendanceRows) {
                    HrAttendance::insert($attendanceRows);
                }
            }

            DB::commit();

            return $leave;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 创建薪资记录：校验同员工同期间唯一性后写入，并自动计算实发金额
     *
     * @throws InvalidArgumentException 该员工当月薪资记录已存在时抛出
     */
    public function createSalary(array $data): HrSalary
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $periodYear = (int) ($data['period_year'] ?? 0);
        $periodMonth = (int) ($data['period_month'] ?? 0);
        if ($this->salaryExists($employeeId, $periodYear, $periodMonth)) {
            throw new InvalidArgumentException('该员工当月薪资记录已存在');
        }

        $salary = new HrSalary();
        $salary->id = $this->generateId();
        $salary->fill($this->fillableOnly($salary, $data));
        $salary->net_salary = $this->salaryNetSalary($salary->toArray());
        $salary->save();

        return $salary;
    }

    /**
     * 更新薪资记录：自动重算实发金额；已发放(1)不可修改
     *
     * @return HrSalary|null 记录不存在返回 null
     * @throws InvalidArgumentException 已发放的薪资不可修改时抛出
     */
    public function updateSalary(int $id, array $data): ?HrSalary
    {
        $salary = HrSalary::find($id);
        if (!$salary) {
            return null;
        }
        if ($salary->status === 1) {
            throw new InvalidArgumentException('已发放的薪资不可修改');
        }

        $salary->fill($this->fillableOnly($salary, $data));
        $salary->net_salary = $this->salaryNetSalary($salary->toArray());
        $salary->save();

        return $salary;
    }

    /**
     * 薪资发放确认：状态 0 → 1
     *
     * @return HrSalary|null 记录不存在返回 null
     * @throws InvalidArgumentException 该薪资已发放时抛出
     */
    public function paySalary(int $id): ?HrSalary
    {
        $salary = HrSalary::find($id);
        if (!$salary) {
            return null;
        }
        if ($salary->status === 1) {
            throw new InvalidArgumentException('该薪资已发放');
        }

        $salary->status = 1;
        $salary->save();

        return $salary;
    }

    /**
     * 批量生成薪资：按部门（可选）为在职员工生成初始薪资记录，跳过已存在的期间
     *
     * @return int 新生成的记录数
     */
    public function batchGenerateSalaries(int $periodYear, int $periodMonth, ?int $departmentId = null): int
    {
        $employees = HrEmployee::where('status', 1);
        if ($departmentId) {
            $employees->where('department_id', $departmentId);
        }

        $employees = $employees->get();

        // 一次取回该期间已有薪资的员工集合，缺失的批量写入
        $existing = HrSalary::whereIn('employee_id', $employees->pluck('id'))
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->pluck('employee_id')->flip();

        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($employees as $emp) {
            if (isset($existing[$emp->id])) {
                continue;
            }
            $rows[] = [
                'id' => $this->generateId(),
                'employee_id' => $emp->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'status' => 0,
                'net_salary' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            HrSalary::insert($rows);
        }

        return count($rows);
    }

    /**
     * 指定员工在指定期间是否已有薪资记录
     */
    public function salaryExists(int $employeeId, int $periodYear, int $periodMonth): bool
    {
        return HrSalary::where('employee_id', $employeeId)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->exists();
    }

    /**
     * 部门下是否存在子部门（删除前校验）
     */
    public function hasChildDepartments(int $id): bool
    {
        return HrDepartment::where('parent_id', $id)->exists();
    }
}
