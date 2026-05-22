<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
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
     * 任务列表（支持按项目筛选 + 树形）
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建任务
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'project_id' => 'required']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ProjectTask();
        $item->id = $this->generateId();

        // decode project_id from hashid
        $projectIdHash = $request->input('project_id');
        $decoded = $this->decodeIdSafe($projectIdHash);
        $item->project_id = $decoded ?? (int) $projectIdHash;

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'project_id') $item->$k = $v;
        }
        $item->save();

        // 更新上级项目进度
        $this->updateProjectProgress($item->project_id);

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 查看任务详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTask::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $result = $this->encodeIds($item->toArray());

        // 子任务列表
        $result['children'] = ProjectTask::where('parent_id', $item->id)
            ->orderBy('seq')->orderBy('id')
            ->get()->map(fn($child) => $this->encodeIds($child->toArray()));

        return $this->success($result);
    }

    /**
     * 更新任务
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTask::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();

        $this->updateProjectProgress($item->project_id);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除任务（软删除）
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTask::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

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
        $avgProgress = $tasks->isEmpty() ? 0 : (int) round($tasks->avg('progress'));

        \app\model\Project::where('id', $projectId)->update(['progress' => $avgProgress]);
    }
}
