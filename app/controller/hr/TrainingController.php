<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\service\hr\TrainingService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * H3 课程体系：课程 CRUD + 报名/完成/取消 + 员工学分
 * 本批次不注册路由（controller 仅写 Apidoc），路由归口由批次负责人统一注册。
 */
#[\erikwang2013\apidoc\annotation\Title("课程")]
#[\erikwang2013\apidoc\annotation\Group("人力资源")]
class TrainingController extends BaseController
{
    /**
     * 课程列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("课程列表")]
#[\erikwang2013\apidoc\annotation\Desc("按状态/类型/最低学分/关键词分页查询课程")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/course")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1上架2下架")]
#[\erikwang2013\apidoc\annotation\Param(name:"course_type", type:"string", desc:"类型:internal内训/external外训/online线上")]
#[\erikwang2013\apidoc\annotation\Param(name:"min_credits", type:"int", desc:"最低学分")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"标题/讲师关键词")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function listCourses(Request $request): Response
    {
        $result = $this->training()->listCourses(
            $request->only(['status', 'course_type', 'min_credits', 'keyword']),
            (int) $request->input('page', 1),
            (int) $request->input('limit', 15)
        );
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->successPage($list, (int) $result['total'], (int) $result['page'], (int) $result['limit']);
    }

    /**
     * 创建课程
     */
#[\erikwang2013\apidoc\annotation\Title("创建课程")]
#[\erikwang2013\apidoc\annotation\Desc("title/course_type 必填；未传 status 落草稿(0)")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/course")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"title", type:"string", desc:"课程标题，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"course_type", type:"string", desc:"类型:internal内训/external外训/online线上，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"lecturer", type:"string", desc:"讲师姓名")]
#[\erikwang2013\apidoc\annotation\Param(name:"credits", type:"int", desc:"学分，非负整数")]
#[\erikwang2013\apidoc\annotation\Param(name:"duration_hours", type:"float", desc:"课时时长，最多两位小数")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1上架2下架，缺省0")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function createCourse(Request $request): Response
    {
        try {
            $course = $this->training()->createCourse($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($course), '创建成功');
    }

    /**
     * 更新课程
     */
#[\erikwang2013\apidoc\annotation\Title("更新课程")]
#[\erikwang2013\apidoc\annotation\Desc("可部分更新；下架仅拦截新报名，不影响已有选课")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"课程ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function updateCourse(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $course = $this->training()->updateCourse($id, $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($course), '更新成功');
    }

    /**
     * 删除课程（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除课程")]
#[\erikwang2013\apidoc\annotation\Desc("软删除课程；学分与选课历史保留")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"课程ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroyCourse(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $this->training()->destroyCourse($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 员工报名课程
     */
#[\erikwang2013\apidoc\annotation\Title("员工报名课程")]
#[\erikwang2013\apidoc\annotation\Desc("仅上架课程可报名；同员工同课程仅一条选课，重复报名拒绝")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"课程ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function enroll(Request $request, string $id): Response
    {
        $courseId = $this->decodeId($id);
        $employeeId = (int) $request->input('employee_id', 0);
        $operatorId = (int) ($request->adminId ?? 0);
        try {
            $enrollment = $this->training()->enroll($courseId, $employeeId, $operatorId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($enrollment), '报名成功');
    }

    /**
     * 取消课程报名
     */
#[\erikwang2013\apidoc\annotation\Title("取消课程报名")]
#[\erikwang2013\apidoc\annotation\Desc("已报名(0)→已取消(2)；已完成(1)不可取消")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"课程ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function cancel(Request $request, string $id): Response
    {
        $courseId = $this->decodeId($id);
        $employeeId = (int) $request->input('employee_id', 0);
        $operatorId = (int) ($request->adminId ?? 0);
        try {
            $enrollment = $this->training()->cancel($courseId, $employeeId, $operatorId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($enrollment), '已取消报名');
    }

    /**
     * 标记课程完成
     */
#[\erikwang2013\apidoc\annotation\Title("标记课程完成")]
#[\erikwang2013\apidoc\annotation\Desc("已报名(0)→已完成(1)并记 completed_at；学分计入员工学分")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"课程ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function complete(Request $request, string $id): Response
    {
        $courseId = $this->decodeId($id);
        $employeeId = (int) $request->input('employee_id', 0);
        $operatorId = (int) ($request->adminId ?? 0);
        try {
            $enrollment = $this->training()->complete($courseId, $employeeId, $operatorId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($enrollment), '已完成');
    }

    /**
     * 员工学分统计
     */
#[\erikwang2013\apidoc\annotation\Title("员工学分统计")]
#[\erikwang2013\apidoc\annotation\Desc("已完成选课学分合计与选课历史（课程软删除后仍计入）")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"员工ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function employeeCredits(Request $request, string $id): Response
    {
        $employeeId = $this->decodeId($id);
        try {
            $result = $this->training()->employeeCredits($employeeId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result);
    }

    /**
     * 课程薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function training(): TrainingService
    {
        return Container::get(TrainingService::class);
    }
}
