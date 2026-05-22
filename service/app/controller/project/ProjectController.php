<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\project;

use app\admin\controller\BaseController;
use app\model\Project;
use app\model\ProjectTask;
use support\Request;
use support\Response;

class ProjectController extends BaseController
{
    /**
     * 项目列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $managerId = $request->input('manager_user_id', '');

        $query = Project::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($managerId) {
            $query->where('manager_user_id', (int) $managerId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(function ($item) {
                $data = $item->toArray();
                $data = $this->encodeIds($data);
                // 计算实际进度
                $data['progress'] = $this->calcProgress($item->id);
                return $data;
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建项目
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'manager_user_id' => 'required|integer|min:1']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new Project();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 查看项目详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Project::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $result = $this->encodeIds($item->toArray());
        $result['progress'] = $this->calcProgress($item->id);

        return $this->success($result);
    }

    /**
     * 更新项目
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Project::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除项目（软删除）
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Project::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 计算项目进度（基于任务完成率）
     */
    protected function calcProgress(int $projectId): int
    {
        $tasks = ProjectTask::where('project_id', $projectId)->get();
        if ($tasks->isEmpty()) {
            return 0;
        }
        return (int) round($tasks->avg('progress'));
    }
}
