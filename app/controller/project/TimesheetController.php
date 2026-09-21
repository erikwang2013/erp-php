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

#[\erikwang2013\apidoc\annotation\Title('工时记录')]
#[\erikwang2013\apidoc\annotation\Group('项目管理')]

class TimesheetController extends BaseController
{
    /**
     * 工时记录列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('工时记录列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询工时记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/project/timesheet')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('项目管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'project_id', type:'int', desc:'项目ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'task_id', type:'int', desc:'任务ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'user_id', type:'int', desc:'用户ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'work_date', type:'string', desc:'工作日期')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
        [$page, $limit] = $this->pageParams($request);
        $projectId = $request->input('project_id', '');
        $taskId = $request->input('task_id', '');
        $userId = $request->input('user_id', '');
        $workDate = $request->input('work_date', '');

        $query = ProjectTimesheet::query();

        // 筛选值非法一律 422：旧写法 (int) 兜底会把垃圾串变成 where 0，静默回空列表
        foreach (['project_id' => $projectId, 'task_id' => $taskId, 'user_id' => $userId] as $field => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $decoded = $this->decodeFlexibleId($raw);
            if ($decoded === null) {
                return $this->fail($this->trans('Invalid :field', ['field' => $field]), 422);
            }
            $query->where($field, $decoded);
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
    #[\erikwang2013\apidoc\annotation\Title('记录工时')]
    #[\erikwang2013\apidoc\annotation\Desc('新增工时记录，自动聚合更新任务实际工时')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/project/timesheet')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('项目管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'project_id', type:'string', desc:'项目ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'user_id', type:'string', desc:'用户ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'hours', type:'float', desc:'工时数，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'work_date', type:'string', desc:'工作日期，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
        $fkError = $this->decodeFkIntoRequest($request);
        if ($fkError !== null) {
            return $this->fail($fkError, 422);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']), $this->trans('Work hours recorded successfully'));
    }

    /**
     * 工时详情
     */
    #[\erikwang2013\apidoc\annotation\Title('工时详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看工时记录详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('项目管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工时ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']));
    }

    /**
     * hashid 兼容解码：store/update 前把表单下发的 hashid FK（来自 /admin/v1/project、
     * /admin/v1/user 等列表行）解码为 int 合并回请求，fill 落库即为 int。
     * 解不出（垃圾串 / 数组）返回错误文案由调用方 422 —— 旧写法 (int) 兜底会静默写 0。
     */
    protected function decodeFkIntoRequest(Request $request, ?ProjectTimesheet $existing = null): ?string
    {
        foreach (['project_id', 'task_id', 'user_id'] as $key) {
            $value = $request->input($key, '');
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                // 空串=不改动：留着会被 fill 把 '' 写进 NOT NULL BIGINT 列（严格模式 1366 → 500）
                if ($existing !== null) {
                    $request->setGet($key, $existing->getAttribute($key));
                }
                continue;
            }
            $decoded = $this->decodeFlexibleId($value);
            if ($decoded === null) {
                return $this->trans('Invalid :field', ['field' => $key]);
            }
            $request->setGet($key, $decoded);
        }

        return null;
    }

    /**
     * 更新工时
     */
    #[\erikwang2013\apidoc\annotation\Title('更新工时')]
    #[\erikwang2013\apidoc\annotation\Desc('修改工时记录，自动更新任务实际工时')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('项目管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工时ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
            return $this->fail($this->trans('Record not found'), 404);
        }

        $fkError = $this->decodeFkIntoRequest($request, $item);
        if ($fkError !== null) {
            return $this->fail($fkError, 422);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'project_id', 'task_id', 'user_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除工时
     */
    #[\erikwang2013\apidoc\annotation\Title('删除工时')]
    #[\erikwang2013\apidoc\annotation\Desc('删除工时记录，自动更新任务实际工时，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('项目管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'工时ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $taskId = $item->task_id;
        $item->delete();
        $this->updateTaskActualHours($taskId);

        return $this->success([], $this->trans('Deleted successfully'));
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
