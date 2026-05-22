<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrAttendance;
use app\model\HrAttendanceRule;
use app\model\HrEmployee;
use app\model\HrLeave;
use support\Request;
use support\Response;

/**
 * 考勤与请假管理
 */
class AttendanceController extends BaseController
{
    // ============================================================
    // 考勤记录
    // ============================================================

    /**
     * 考勤记录列表（分页）
     * GET /admin/hr/attendance
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $workDate = $request->input('work_date');
        $status = $request->input('status');

        $query = HrAttendance::with(['employee']);
        if ($employeeId) {
            $query->where('employee_id', (int) $employeeId);
        }
        if ($workDate) {
            $query->where('work_date', $workDate);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(function ($item) {
                $data = $item->toArray();
                if ($item->relationLoaded('employee') && $item->employee) {
                    $data['employee'] = $this->encodeIds($item->employee->toArray());
                }
                return $this->encodeIds($data);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 上班打卡
     * POST /admin/hr/attendance/clock-in
     */
    public function clockIn(Request $request): Response
    {
        $employeeId = (int) $request->input('employee_id');
        $employee = HrEmployee::find($employeeId);
        if (!$employee) return $this->fail('员工不存在', 404);

        $workDate = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // 检查今天是否已打卡
        $existing = HrAttendance::where('employee_id', $employeeId)
            ->where('work_date', $workDate)->first();
        if ($existing && $existing->clock_in) {
            return $this->fail('今天已打过上班卡', 422);
        }

        // 获取考勤规则
        $rule = HrAttendanceRule::orderBy('id', 'asc')->first();
        $status = 1;
        $lateMinutes = 0;

        if ($rule) {
            $clockInTime = date('H:i:s');
            if ($clockInTime > $rule->clock_in_time) {
                $lateMinutes = max(0, (strtotime($clockInTime) - strtotime($rule->clock_in_time)) / 60);
                if ($lateMinutes > $rule->late_grace) {
                    $status = 2; // 迟到
                }
            }
        }

        if ($existing) {
            $existing->clock_in = $now;
            $existing->rule_id = $rule ? $rule->id : 0;
            $existing->status = $status;
            $existing->late_minutes = (int) $lateMinutes;
            $existing->save();
        } else {
            $attendance = new HrAttendance();
            $attendance->id = $this->generateId();
            $attendance->employee_id = $employeeId;
            $attendance->rule_id = $rule ? $rule->id : 0;
            $attendance->work_date = $workDate;
            $attendance->clock_in = $now;
            $attendance->status = $status;
            $attendance->late_minutes = (int) $lateMinutes;
            $attendance->created_at = $now;
            $attendance->save();
        }

        return $this->success(['status' => $status, 'late_minutes' => (int) $lateMinutes], '打卡成功');
    }

    /**
     * 下班打卡
     * POST /admin/hr/attendance/clock-out
     */
    public function clockOut(Request $request): Response
    {
        $employeeId = (int) $request->input('employee_id');
        $employee = HrEmployee::find($employeeId);
        if (!$employee) return $this->fail('员工不存在', 404);

        $workDate = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $attendance = HrAttendance::where('employee_id', $employeeId)
            ->where('work_date', $workDate)->first();
        if (!$attendance || !$attendance->clock_in) {
            return $this->fail('请先打上班卡', 422);
        }
        if ($attendance->clock_out) {
            return $this->fail('今天已打过下班卡', 422);
        }

        // 获取考勤规则
        $rule = HrAttendanceRule::find($attendance->rule_id);
        $status = $attendance->status;
        $earlyMinutes = 0;

        if ($rule) {
            $clockOutTime = date('H:i:s');
            if ($clockOutTime < $rule->clock_out_time) {
                $earlyMinutes = max(0, (strtotime($rule->clock_out_time) - strtotime($clockOutTime)) / 60);
                if ($earlyMinutes > $rule->early_grace && $status == 1) {
                    $status = 3; // 早退（如果之前不是迟到）
                }
            }
        }

        $attendance->clock_out = $now;
        $attendance->status = $status;
        $attendance->early_minutes = (int) $earlyMinutes;
        $attendance->save();

        return $this->success(['status' => $status, 'early_minutes' => (int) $earlyMinutes], '打卡成功');
    }

    // ============================================================
    // 请假管理
    // ============================================================

    /**
     * 请假列表（分页）
     * GET /admin/hr/leave
     */
    public function leaveIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $type = $request->input('type');
        $status = $request->input('status');

        $query = HrLeave::with(['employee']);
        if ($employeeId) {
            $query->where('employee_id', (int) $employeeId);
        }
        if ($type !== null && $type !== '') {
            $query->where('type', (int) $type);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(function ($item) {
                $data = $item->toArray();
                if ($item->relationLoaded('employee') && $item->employee) {
                    $data['employee'] = $this->encodeIds($item->employee->toArray());
                }
                return $this->encodeIds($data);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建请假
     * POST /admin/hr/leave
     */
    public function leaveStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'employee_id' => 'required|integer',
            'type' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'days' => 'required|numeric',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new HrLeave();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = 0; // 待审批
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '请假申请已提交');
    }

    /**
     * 请假详情
     * GET /admin/hr/leave/{id}
     */
    public function leaveShow(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrLeave::with(['employee'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if ($item->relationLoaded('employee') && $item->employee) {
            $data['employee'] = $this->encodeIds($item->employee->toArray());
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新请假
     * PUT /admin/hr/leave/{id}
     */
    public function leaveUpdate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrLeave::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('只能修改待审批的请假申请', 422);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除请假（软删除）
     * DELETE /admin/hr/leave/{id}
     */
    public function leaveDestroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrLeave::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 审批请假
     * POST /admin/hr/leave/{id}/approve
     */
    public function approveLeave(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrLeave::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('该请假申请已审批', 422);

        $action = $request->input('action', 'approve'); // approve 或 reject
        $item->status = $action === 'reject' ? 2 : 1;
        $item->save();

        // 批准后自动标记考勤为请假状态
        if ($item->status === 1) {
            $start = strtotime($item->start_date);
            $end = strtotime($item->end_date);
            for ($d = $start; $d <= $end; $d += 86400) {
                $dateStr = date('Y-m-d', $d);
                $existing = HrAttendance::where('employee_id', $item->employee_id)
                    ->where('work_date', $dateStr)->first();
                if (!$existing) {
                    $att = new HrAttendance();
                    $att->id = $this->generateId();
                    $att->employee_id = $item->employee_id;
                    $att->work_date = $dateStr;
                    $att->status = 5; // 请假
                    $att->created_at = date('Y-m-d H:i:s');
                    $att->save();
                } elseif ($existing->status == 4) {
                    $existing->status = 5;
                    $existing->save();
                }
            }
        }

        return $this->success($this->encodeIds($item->toArray()), $item->status === 1 ? '已批准' : '已驳回');
    }
}
