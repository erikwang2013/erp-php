<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\project;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\Project;
use app\model\ProjectTimesheet;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("工时记录")]
#[\erikwang2013\apidoc\annotation\Group("项目管理")]

class TimesheetController extends BaseController
{
    /**
     * 工时记录列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("工时记录列表")]
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
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'project_id' => 'string',
            'task_id' => 'string',
            'user_id' => 'string',
            'work_date' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $projectId = $request->input('project_id', '');
        $taskId = $request->input('task_id', '');
        $userId = $request->input('user_id', '');
        $workDate = $request->input('work_date', '');

        $query = ProjectTimesheet::query();

        if ($projectId !== null && $projectId !== '') {
            $query->where('project_id', $this->decodeIdSafe((string) $projectId) ?? (int) $projectId);
        }
        if ($taskId !== null && $taskId !== '') {
            $query->where('task_id', $this->decodeIdSafe((string) $taskId) ?? (int) $taskId);
        }
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', $this->decodeIdSafe((string) $userId) ?? (int) $userId);
        }
        if ($workDate !== null && $workDate !== '') {
            $query->where('work_date', $workDate);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('work_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $item->toArray())->all();

        // 行级展示名（项目/用户），FK 一并编码供编辑弹窗回填同源选项
        $projectIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['project_id'] ?? 0), $rows)));
        $userIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['user_id'] ?? 0), $rows)));
        $projectNames = Project::whereIn('id', $projectIds)->pluck('name', 'id');
        $userNames = AdminUser::whereIn('id', $userIds)->pluck('real_name', 'id');
        $list = array_map(function ($row) use ($projectNames, $userNames) {
            $row['project_name'] = (string) ($projectNames[(int) ($row['project_id'] ?? 0)] ?? '');
            $row['user_name'] = (string) ($userNames[(int) ($row['user_id'] ?? 0)] ?? '');

            return $this->encodeIds($row, ['id', 'project_id', 'task_id', 'user_id']);
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 记录工时
     */
#[\erikwang2013\apidoc\annotation\Title("记录工时")]
#[\erikwang2013\apidoc\annotation\Desc("新增工时记录，自动聚合更新任务实际工时")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/project/timesheet")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"project_id", type:"string", desc:"项目ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"user_id", type:"string", desc:"用户ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"hours", type:"float", desc:"工时数，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"work_date", type:"string", desc:"工作日期，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'project_id' => 'required|string',
            'user_id' => 'required|string',
            'hours' => 'required|numeric|min:0.01',
            'work_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ProjectTimesheet();
        $item->id = $this->generateId();
        $this->decodeFkIntoRequest($request);
        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']), '工时记录成功');
    }

    /**
     * 工时详情
     */
#[\erikwang2013\apidoc\annotation\Title("工时详情")]
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
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = ProjectTimesheet::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']));
    }

    /**
     * hashid 兼容解码：store/update 前把表单下发的 hashid FK（来自 /admin/v1/project、
     * /admin/v1/user 等列表行）解码为 int 合并回请求，fill 落库即为 int。
     */
    protected function decodeFkIntoRequest(Request $request): void
    {
        foreach (['project_id', 'task_id', 'user_id'] as $key) {
            $value = $request->input($key, '');
            if ($value !== null && $value !== '') {
                $request->setGet($key, $this->decodeIdSafe((string) $value) ?? (int) $value);
            }
        }
    }

    /**
     * 更新工时
     */
#[\erikwang2013\apidoc\annotation\Title("更新工时")]
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
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = ProjectTimesheet::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->decodeFkIntoRequest($request);
        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']), '更新成功');
    }

    /**
     * 删除工时
     */
#[\erikwang2013\apidoc\annotation\Title("删除工时")]
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
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
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
