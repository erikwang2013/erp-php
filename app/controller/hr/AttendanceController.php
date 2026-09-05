<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\HrAttendance;
use app\model\HrEmployee;
use app\model\HrLeave;
use app\service\hr\HrService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 考勤与请假管理
  * @Apidoc\Tag("人力资源")
 */
class AttendanceController extends BaseController
{
    // 考勤记录

    /**
     * 考勤记录列表（分页）
     * @Apidoc\Title("考勤记录列表")
     * @Apidoc\Desc("分页查询考勤记录")
     * @Apidoc\Url("/admin/v1/hr/attendance")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID")
     * @Apidoc\Param(name="work_date", type="string", desc="工作日期")
     * @Apidoc\Param(name="status", type="int", desc="状态:1正常2迟到3早退4旷工5请假")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $workDate = $request->input('work_date');
        $status = $request->input('status');

        $result = $this->hr()->list(HrAttendance::class, [
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'status' => $status,
        ], $page, $limit, [
            'eqFilters' => ['status'],
            'truthyFilters' => ['employee_id'],
            'stringTruthyFilters' => ['work_date'],
            'with' => ['employee'],
        ]);
        $list = array_map(function ($data) {
            $data['employee'] = !empty($data['employee']) ? $this->encodeIds($data['employee']) : null;

            return $this->encodeIds($data);
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 上班打卡
     * @Apidoc\Title("上班打卡")
     * @Apidoc\Desc("员工上班打卡，根据考勤规则自动判定迟到")
     * @Apidoc\Url("/admin/v1/hr/attendance/clock-in")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="打卡结果")
     */
    public function clockIn(Request $request): Response
    {
        $employeeId = (int) $request->input('employee_id');
        if (!$this->hr()->find(HrEmployee::class, $employeeId)) {
            return $this->fail('员工不存在', 404);
        }

        try {
            $result = $this->hr()->clockIn($employeeId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result, '打卡成功');
    }

    /**
     * 下班打卡
     * @Apidoc\Title("下班打卡")
     * @Apidoc\Desc("员工下班打卡，根据考勤规则自动判定早退")
     * @Apidoc\Url("/admin/v1/hr/attendance/clock-out")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="打卡结果")
     */
    public function clockOut(Request $request): Response
    {
        $employeeId = (int) $request->input('employee_id');
        if (!$this->hr()->find(HrEmployee::class, $employeeId)) {
            return $this->fail('员工不存在', 404);
        }

        try {
            $result = $this->hr()->clockOut($employeeId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result, '打卡成功');
    }

    // 请假管理

    /**
     * 请假列表（分页）
     * @Apidoc\Title("请假列表")
     * @Apidoc\Desc("分页查询请假记录")
     * @Apidoc\Url("/admin/v1/hr/attendance")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID")
     * @Apidoc\Param(name="type", type="int", desc="请假类型")
     * @Apidoc\Param(name="status", type="int", desc="状态:0待审批1已批准2已驳回")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function leaveIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $type = $request->input('type');
        $status = $request->input('status');

        $result = $this->hr()->list(HrLeave::class, [
            'employee_id' => $employeeId,
            'type' => $type,
            'status' => $status,
        ], $page, $limit, [
            'eqFilters' => ['type', 'status'],
            'truthyFilters' => ['employee_id'],
            'with' => ['employee'],
        ]);
        $list = array_map(function ($data) {
            $data['employee'] = !empty($data['employee']) ? $this->encodeIds($data['employee']) : null;

            return $this->encodeIds($data);
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建请假
     * @Apidoc\Title("创建请假")
     * @Apidoc\Desc("提交请假申请")
     * @Apidoc\Url("/admin/v1/hr/attendance")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID，必填")
     * @Apidoc\Param(name="type", type="int", desc="请假类型，必填")
     * @Apidoc\Param(name="start_date", type="string", desc="开始日期，必填")
     * @Apidoc\Param(name="end_date", type="string", desc="结束日期，必填")
     * @Apidoc\Param(name="days", type="float", desc="请假天数，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->hr()->create(HrLeave::class, $request->all(), ['status' => 0]);

        return $this->success($this->encodeIds($item->toArray()), '请假申请已提交');
    }

    /**
     * 请假详情
     * @Apidoc\Title("请假详情")
     * @Apidoc\Desc("查看请假记录详细信息")
     * @Apidoc\Url("/admin/v1/hr/attendance/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="请假ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function leaveShow(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrLeave::class, $id, ['employee']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if ($item->relationLoaded('employee') && $item->employee) {
            $data['employee'] = $this->encodeIds($item->employee->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新请假
     * @Apidoc\Title("更新请假")
     * @Apidoc\Desc("修改请假申请，仅待审批状态可修改")
     * @Apidoc\Url("/admin/v1/hr/attendance/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="请假ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function leaveUpdate(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrLeave::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status !== 0) {
            return $this->fail('只能修改待审批的请假申请', 422);
        }

        $item = $this->hr()->update(HrLeave::class, $id, $request->all(), ['status']);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除请假
     * @Apidoc\Title("删除请假")
     * @Apidoc\Desc("删除请假记录，需密码确认")
     * @Apidoc\Url("/admin/v1/hr/attendance/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="请假ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function leaveDestroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrLeave::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrLeave::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 审批请假
     * @Apidoc\Title("审批请假")
     * @Apidoc\Desc("批准或驳回请假申请，批准后自动标记考勤为请假状态")
     * @Apidoc\Url("/admin/v1/hr/attendance/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="请假ID")
     * @Apidoc\Param(name="action", type="string", desc="审批动作:approve批准/reject驳回")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function approveLeave(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $action = (string) $request->input('action', 'approve');

        try {
            $item = $this->hr()->approveLeave($id, $action);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), $item->status === 1 ? '已批准' : '已驳回');
    }

    /**
     * HR 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function hr(): HrService
    {
        return Container::get(HrService::class);
    }
}
