<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("项目管理")
 */
declare(strict_types=1);

namespace app\controller\project;

use app\admin\controller\BaseController;
use app\model\ProjectTask;
use support\Request;
use support\Response;

class TaskController extends BaseController
{
    /**
     * 项目任务列表（分页）
     * @Apidoc\Title("项目任务列表")
     * @Apidoc\Desc("分页查询项目任务，支持按项目筛选")
     * @Apidoc\Url("/admin/v1/project/task")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID")
     * @Apidoc\Param(name="parent_id", type="int", desc="父任务ID")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="assignee_user_id", type="int", desc="负责人ID")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $projectId = $request->input('project_id');
        $parentId = $request->input('parent_id');
        $status = $request->input('status');
        $assigneeId = $request->input('assignee_user_id', '');
        $keyword = $request->input('keyword', '');

        $query = ProjectTask::query();

        if ($projectId) {
            $query->where('project_id', $this->decodeId($projectId));
        }
        if ($parentId !== null && $parentId !== '') {
            $query->where('parent_id', (int) $parentId);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($assigneeId) {
            $query->where('assignee_user_id', (int) $assigneeId);
        }
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('seq')->orderBy('id', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建项目任务
     * @Apidoc\Title("创建项目任务")
     * @Apidoc\Desc("新增项目任务记录，自动更新上级项目进度")
     * @Apidoc\Url("/admin/v1/project/task")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="name", type="string", desc="任务名称，必填")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'project_id' => 'required']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ProjectTask();
        $item->id = $this->generateId();

        $projectIdHash = $request->input('project_id');
        $decoded = $this->decodeIdSafe($projectIdHash);
        $item->project_id = $decoded ?? (int) $projectIdHash;

        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateProjectProgress($item->project_id);

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 任务详情
     * @Apidoc\Title("项目任务详情")
     * @Apidoc\Desc("查看项目任务详细信息，含子任务列表")
     * @Apidoc\Url("/admin/v1/project/task/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTask::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $result = $this->encodeIds($item->toArray());

        $result['children'] = ProjectTask::where('parent_id', $item->id)
            ->orderBy('seq')->orderBy('id')
            ->get()->map(fn ($child) => $this->encodeIds($child->toArray()));

        return $this->success($result);
    }

    /**
     * 更新任务
     * @Apidoc\Title("更新项目任务")
     * @Apidoc\Desc("修改项目任务信息，自动更新上级项目进度")
     * @Apidoc\Url("/admin/v1/project/task/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTask::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        $this->updateProjectProgress($item->project_id);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除任务
     * @Apidoc\Title("删除项目任务")
     * @Apidoc\Desc("删除项目任务，自动更新上级项目进度，需密码确认")
     * @Apidoc\Url("/admin/v1/project/task/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectTask::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $projectId = $item->project_id;
        $item->delete();
        $this->updateProjectProgress($projectId);

        return $this->success([], '删除成功');
    }

    /**
     * 聚合更新上级项目进度
     */
    protected function updateProjectProgress(int $projectId): void
    {
        $tasks = ProjectTask::where('project_id', $projectId)->get();
        $avgProgress = 0;
        if (!$tasks->isEmpty()) {
            $sum = '0';
            foreach ($tasks as $task) {
                $sum = bcadd($sum, bc_norm($task->progress), 6);
            }
            $avgProgress = (int) bc_round(bcdiv($sum, (string) count($tasks), 6), 0);
        }

        \app\model\Project::where('id', $projectId)->update(['progress' => $avgProgress]);
    }
}
