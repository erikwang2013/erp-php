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
     * 调度列表（分页）
     * @Apidoc\Title("报表调度列表")
     * @Apidoc\Desc("分页查询报表调度记录")
     * @Apidoc\Url("/admin/report/schedule")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("自定义报表")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="template_id", type="int", desc="报表模板ID")
     * @Apidoc\Param(name="enabled", type="int", desc="启用状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建调度
     * @Apidoc\Title("创建报表调度")
     * @Apidoc\Desc("新增报表调度记录，自动计算下次执行时间")
     * @Apidoc\Url("/admin/report/schedule")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("自定义报表")
     * @Apidoc\Param(name="template_id", type="int", desc="报表模板ID，必填")
     * @Apidoc\Param(name="name", type="string", desc="调度名称，必填")
     * @Apidoc\Param(name="frequency", type="int", desc="调度频率:1每天2每周3每月，必填")
     * @Apidoc\Param(name="recipients", type="string", desc="接收人列表，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'name' => 'required|string|max:200',
            'frequency' => 'required|integer',
            'recipients' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ReportSchedule();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 调度详情
     * @Apidoc\Title("报表调度详情")
     * @Apidoc\Desc("查看报表调度详细信息")
     * @Apidoc\Url("/admin/report/schedule/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("自定义报表")
     * @Apidoc\Param(name="id", type="string", desc="调度ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportSchedule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新调度
     * @Apidoc\Title("更新报表调度")
     * @Apidoc\Desc("修改报表调度信息，频率变更时重新计算下次执行时间")
     * @Apidoc\Url("/admin/report/schedule/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("自定义报表")
     * @Apidoc\Param(name="id", type="string", desc="调度ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportSchedule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $oldFreq = $item->frequency;
        $this->fillModelFromRequest($item, $request);

        if ((int) $item->frequency !== (int) $oldFreq) {
            $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        }

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除调度
     * @Apidoc\Title("删除报表调度")
     * @Apidoc\Desc("删除报表调度记录，需密码确认")
     * @Apidoc\Url("/admin/report/schedule/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("自定义报表")
     * @Apidoc\Param(name="id", type="string", desc="调度ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail('无效ID', 400);
        }
        $item = ReportSchedule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

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
            1 => date('Y-m-d H:i:s', strtotime('+1 day', $now)),
            2 => date('Y-m-d H:i:s', strtotime('+1 week', $now)),
            3 => date('Y-m-d H:i:s', strtotime('+1 month', $now)),
            default => date('Y-m-d H:i:s', strtotime('+1 day', $now)),
        };
    }
}
