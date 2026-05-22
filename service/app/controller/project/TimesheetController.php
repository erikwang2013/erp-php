<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
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
     */
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 记录工时
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'project_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
            'hours' => 'required|numeric|min:0.01',
            'work_date' => 'required|date',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ProjectTimesheet();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();

        // 聚合更新任务实际工时
        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray()), '工时记录成功');
    }

    /**
     * 工时详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTimesheet::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新工时
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTimesheet::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();

        $this->updateTaskActualHours($item->task_id);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工时
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ProjectTimesheet::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

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
