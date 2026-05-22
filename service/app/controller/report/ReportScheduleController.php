<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\report;

use app\admin\controller\BaseController;
use app\model\ReportSchedule;
use support\Request;
use support\Response;

/**
 * 报表调度管理
  * @Apidoc\Tag("自定义报表")
 */
class ReportScheduleController extends BaseController
{
    /**
     * 调度列表
     * GET /admin/report/schedule
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $templateId = $request->input('template_id');
        $enabled = $request->input('enabled');

        $query = ReportSchedule::query();
        if ($templateId) {
            $query->where('template_id', (int) $templateId);
        }
        if ($enabled !== null && $enabled !== '') {
            $query->where('enabled', (int) $enabled);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建调度
     * POST /admin/report/schedule
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:200',
            'frequency' => 'required|integer',
            'recipients' => 'required|string',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new ReportSchedule();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }

        // 计算下次执行时间
        $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 调度详情
     * GET /admin/report/schedule/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportSchedule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新调度
     * PUT /admin/report/schedule/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportSchedule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $oldFreq = $item->frequency;
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }

        // 频率变更时重新计算下次执行时间
        if ((int) $item->frequency !== (int) $oldFreq) {
            $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        }

        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除调度
     * DELETE /admin/report/schedule/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = ReportSchedule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 计算下次执行时间
     */
    private function calcNextRun(int $frequency): string
    {
        $now = time();
        return match ($frequency) {
            1 => date('Y-m-d H:i:s', strtotime('+1 day', $now)),     // 每天
            2 => date('Y-m-d H:i:s', strtotime('+1 week', $now)),    // 每周
            3 => date('Y-m-d H:i:s', strtotime('+1 month', $now)),   // 每月
            default => date('Y-m-d H:i:s', strtotime('+1 day', $now)),
        };
    }
}
