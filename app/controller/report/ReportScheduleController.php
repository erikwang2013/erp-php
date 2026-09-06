<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\report;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\ReportSchedule;
use app\model\ReportTemplate;
use support\Request;
use support\Response;

/**
 * 报表调度管理
 */
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Title("报表调度")]
#[\erikwang2013\apidoc\annotation\Group("自定义报表")]

class ReportScheduleController extends BaseController
{
    /**
     * 调度列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("报表调度列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询报表调度记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report/schedule")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", desc:"报表模板ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"enabled", type:"int", desc:"启用状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $templateId = $request->input('template_id');
        $enabled = $request->input('enabled');

        $query = ReportSchedule::query();
        if ($templateId !== null && $templateId !== '') {
            $query->where('template_id', $this->decodeIdSafe((string) $templateId) ?? (int) $templateId);
        }
        if ($enabled !== null && $enabled !== '') {
            $query->where('enabled', (int) $enabled);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $item->toArray())->all();

        // 行级展示名 + FK 编码：template_id 供编辑弹窗回填同源选项；recipients 为 int 列表
        $templateIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['template_id'] ?? 0), $rows)));
        $templateNames = ReportTemplate::whereIn('id', $templateIds)->pluck('name', 'id');
        $userIds = [];
        foreach ($rows as $row) {
            foreach ($this->recipientTokens((string) ($row['recipients'] ?? '')) as $uid) {
                $userIds[] = $uid;
            }
        }
        $userNames = AdminUser::whereIn('id', array_values(array_unique($userIds)))->pluck('real_name', 'id');
        $list = array_map(function ($row) use ($templateNames, $userNames) {
            $row['template_name'] = (string) ($templateNames[(int) ($row['template_id'] ?? 0)] ?? '');
            $row['recipients_names'] = implode(', ', array_map(
                fn ($uid) => (string) ($userNames[$uid] ?? $uid),
                $this->recipientTokens((string) ($row['recipients'] ?? ''))
            ));
            $row = $this->encodeIds($row, ['id', 'template_id']);
            $row['recipients'] = $this->encodeRecipients((string) $row['recipients']);

            return $row;
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建调度
     */
#[\erikwang2013\apidoc\annotation\Title("创建报表调度")]
#[\erikwang2013\apidoc\annotation\Desc("新增报表调度记录，自动计算下次执行时间")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/report/schedule")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", desc:"报表模板ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"调度名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"frequency", type:"int", desc:"调度频率:1每天2每周3每月，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"recipients", type:"string", desc:"接收人列表，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|string',
            'name' => 'required|string|max:200',
            'frequency' => 'required|integer|in:1,2,3',
            'recipients' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new ReportSchedule();
        $item->id = $this->generateId();
        $this->decodeFkIntoRequest($request);
        $this->fillModelFromRequest($item, $request);

        $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        $item->save();

        return $this->success($this->encodeScheduleItem($item->toArray()), '创建成功');
    }

    /**
     * 调度详情
     */
#[\erikwang2013\apidoc\annotation\Title("报表调度详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看报表调度详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"调度ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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

        return $this->success($this->encodeScheduleItem($item->toArray()));
    }

    /**
     * 更新调度
     */
#[\erikwang2013\apidoc\annotation\Title("更新报表调度")]
#[\erikwang2013\apidoc\annotation\Desc("修改报表调度信息，频率变更时重新计算下次执行时间")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"调度ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
        $this->decodeFkIntoRequest($request);
        $this->fillModelFromRequest($item, $request);

        if ((int) $item->frequency !== (int) $oldFreq) {
            $item->next_run_at = $this->calcNextRun((int) $item->frequency);
        }

        $item->save();

        return $this->success($this->encodeScheduleItem($item->toArray()), '更新成功');
    }

    /**
     * 删除调度
     */
#[\erikwang2013\apidoc\annotation\Title("删除报表调度")]
#[\erikwang2013\apidoc\annotation\Desc("删除报表调度记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("自定义报表")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"调度ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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

    /**
     * hashid 兼容解码：template_id/recipients 为表单下发的 hashid（/admin/v1/report、
     * /admin/v1/user 列表行），解码为 int 合并回请求，fill 落库即为 int（recipients 逗号分隔列表）。
     */
    private function decodeFkIntoRequest(Request $request): void
    {
        $templateId = $request->input('template_id', '');
        if ($templateId !== null && $templateId !== '') {
            $request->merge(['template_id' => $this->decodeIdSafe((string) $templateId) ?? (int) $templateId]);
        }
        $recipients = $request->input('recipients', '');
        if ($recipients !== null && $recipients !== '') {
            $tokens = array_map(
                fn ($t) => (string) ($this->decodeIdSafe(trim((string) $t)) ?? (int) trim((string) $t)),
                explode(',', (string) $recipients)
            );
            $request->merge(['recipients' => implode(',', $tokens)]);
        }
    }

    /**
     * 出参编码：id/template_id → hashid，recipients int 列表逐项 → hashid（与下拉选项同源）。
     */
    private function encodeScheduleItem(array $item): array
    {
        $data = $this->encodeIds($item, ['id', 'template_id']);
        $data['recipients'] = $this->encodeRecipients((string) ($data['recipients'] ?? ''));

        return $data;
    }

    /**
     * recipients 原值解析为 int 列表（兼容历史 raw int 与新写入的 hashid 两种形态）。
     *
     * @return int[]
     */
    private function recipientTokens(string $raw): array
    {
        $out = [];
        foreach (explode(',', $raw) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $out[] = ctype_digit($token) ? (int) $token : ($this->decodeIdSafe($token) ?? 0);
        }

        return array_filter($out, fn ($id) => $id > 0);
    }

    /**
     * recipients int 列表逐项编码为 hashid（非数字 token 原样保留）。
     */
    private function encodeRecipients(string $raw): string
    {
        $out = [];
        foreach (explode(',', $raw) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }
            $out[] = ctype_digit($token) && (int) $token > 0 ? $this->encodeId((int) $token) : $token;
        }

        return implode(',', $out);
    }
}
