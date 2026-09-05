<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("项目管理")
 */
declare(strict_types=1);

namespace app\controller\project;

use app\admin\controller\BaseController;
use app\model\ProjectTimesheet;
use support\Request;
use support\Response;

class TimesheetController extends BaseController
{
    /**
     * 工时记录列表（分页）
     * @Apidoc\Title("工时记录列表")
     * @Apidoc\Desc("分页查询工时记录")
     * @Apidoc\Url("/admin/v1/project/timesheet")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="project_id", type="int", desc="项目ID")
     * @Apidoc\Param(name="task_id", type="int", desc="任务ID")
     * @Apidoc\Param(name="user_id", type="int", desc="用户ID")
     * @Apidoc\Param(name="work_date", type="string", desc="工作日期")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("工时记录列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询工时记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/project/timesheet")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"project_id", type:"int", desc:"项目ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"task_id", type:"int", desc:"任务ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"user_id", type:"int", desc:"用户ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"work_date", type:"string", desc:"工作日期")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $projectId = $request->input('project_id', '');
        $taskId = $request->input('task_id', '');
        $userId = $request->input('user_id', '');
        $workDate = $request->input('work_date', '');

        $query = ProjectTimesheet::query();

        if ($projectId) {
            $query->where('project_id', (int) $projectId);
        }
        if ($taskId) {
            $query->where('task_id', (int) $taskId);
        }
        if ($userId) {
            $query->where('user_id', (int) $userId);
        }
        if ($workDate) {
            $query->where('work_date', $workDate);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('work_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 记录工时
     * @Apidoc\Title("记录工时")
     * @Apidoc\Desc("新增工时记录，自动聚合更新任务实际工时")
     * @Apidoc\Url("/admin/v1/project/timesheet")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="project_id", type="int", desc="项目ID，必填")
     * @Apidoc\Param(name="user_id", type="int", desc="用户ID，必填")
     * @Apidoc\Param(name="hours", type="float", desc="工时数，必填")
     * @Apidoc\Param(name="work_date", type="string", desc="工作日期，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("记录工时")]
#[\erikwang2013\apidoc\annotation\Desc("新增工时记录，自动聚合更新任务实际工时")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/project/timesheet")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"project_id", type:"int", desc:"项目ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"user_id", type:"int", desc:"用户ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"hours", type:"float", desc:"工时数，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"work_date", type:"string", desc:"工作日期，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'project_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
            'hours' => 'required|numeric|min:0.01',
            'work_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ProjectTimesheet();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray()), '工时记录成功');
    }

    /**
     * 工时详情
     * @Apidoc\Title("工时详情")
     * @Apidoc\Desc("查看工时记录详细信息")
     * @Apidoc\Url("/admin/v1/project/timesheet/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="工时ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("工时详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看工时记录详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工时ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTimesheet::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新工时
     * @Apidoc\Title("更新工时")
     * @Apidoc\Desc("修改工时记录，自动更新任务实际工时")
     * @Apidoc\Url("/admin/v1/project/timesheet/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="工时ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("更新工时")]
#[\erikwang2013\apidoc\annotation\Desc("修改工时记录，自动更新任务实际工时")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工时ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTimesheet::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工时
     * @Apidoc\Title("删除工时")
     * @Apidoc\Desc("删除工时记录，自动更新任务实际工时，需密码确认")
     * @Apidoc\Url("/admin/v1/project/timesheet/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="工时ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除工时")]
#[\erikwang2013\apidoc\annotation\Desc("删除工时记录，自动更新任务实际工时，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工时ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTimesheet::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $taskId = $item->task_id;
        $item->delete();
        $this->updateTaskActualHours($taskId);

        return $this->success([], '删除成功');
    }

    /**
     * 聚合更新任务实际工时
     */
    protected function updateTaskActualHours(int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }

        $totalHours = ProjectTimesheet::where('task_id', $taskId)->sum('hours');

        \app\model\ProjectTask::where('id', $taskId)->update(['actual_hours' => $totalHours]);
    }
}
