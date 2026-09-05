<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrKpiTemplate;
use app\model\HrPerfPlan;
use app\model\HrPerfScore;
use app\service\hr\PerformanceService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 绩效考核（H2：KPI 模板 + 评分流程）
 * 模板/考核批次/评分三组接口。状态机唯一入口为 PerformanceService：
 * 模板 0草稿→1启用（启用前须指标≥1 且权重合计=100.00，启用后指标项冻结）；
 * 批次 0草稿→1进行中→2已归档（仅可引用已启用模板；归档须≥1 条评分）。
 * 行主键 id 经 hashid 出入；跨表外键（template_id/employee_id/plan_id 等）为原始整数。
 * 统一返回 {code,message,data}；Tag 见类注解。
 */
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]

class PerformanceController extends BaseController
{
    // ---------- KPI 模板（erp_hr_kpi_template） ----------

    #[\erikwang2013\apidoc\annotation\Title("模板列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/template")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1启用")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"模板名称（等值）")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateIndex(Request $request): Response
    {
        $result = $this->perf()->list(HrKpiTemplate::class, [
            'status' => $request->input('status'),
            'name' => $request->input('name'),
        ], (int) $request->input('page', 1), (int) $request->input('limit', 15), [
            'eqFilters' => ['status'],
            'stringEqFilters' => ['name'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("新建模板")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/template")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"模板名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_type", type:"string", desc:"周期类型:monthly/quarterly/yearly，默认monthly")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateStore(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:100']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $template = $this->perf()->templateStore($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($template), '创建成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("模板详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateShow(Request $request, string $id): Response
    {
        try {
            $template = $this->perf()->templateShow($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 404);
        }

        return $this->success($this->encodeTemplate($template));
    }

    #[\erikwang2013\apidoc\annotation\Title("更新模板")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"模板名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_type", type:"string", desc:"周期类型:monthly/quarterly/yearly")]
#[\erikwang2013\apidoc\annotation\Param(name:"items", type:"array", desc:"指标项（仅草稿模板可改）")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateUpdate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), ['name' => 'sometimes|string|max:100']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $template = $this->perf()->templateUpdate($this->decodeId($id), $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeTemplate($template), '更新成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("启用模板")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateEnable(Request $request, string $id): Response
    {
        try {
            $template = $this->perf()->templateEnable($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeTemplate($template), '模板已启用');
    }

    #[\erikwang2013\apidoc\annotation\Title("删除模板")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function templateDestroy(Request $request, string $id): Response
    {
        $templateId = $this->decodeId($id);
        if (!$this->perf()->find(HrKpiTemplate::class, $templateId)) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        try {
            $this->perf()->templateDestroy($templateId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    // ---------- 考核批次（erp_hr_perf_plan） ----------

    #[\erikwang2013\apidoc\annotation\Title("考核批次列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/plan")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1进行中2已归档")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", desc:"模板ID")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function planIndex(Request $request): Response
    {
        $result = $this->perf()->list(HrPerfPlan::class, [
            'status' => $request->input('status'),
            'template_id' => $request->input('template_id'),
        ], (int) $request->input('page', 1), (int) $request->input('limit', 15), [
            'eqFilters' => ['status', 'template_id'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("新建考核批次")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/plan")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"int", desc:"模板ID（须已启用），必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_start", type:"string", desc:"周期开始 Y-m-d，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_end", type:"string", desc:"周期结束 Y-m-d，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"created_by", type:"int", desc:"创建人ID，默认当前管理员")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function planStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'template_id' => 'required|integer',
            'period_start' => 'required|date_format:Y-m-d',
            'period_end' => 'required|date_format:Y-m-d',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $data = $request->all();
        $data['created_by'] = (int) ($data['created_by'] ?? ($request->adminId ?? 0));
        try {
            $plan = $this->perf()->createPlan($data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($plan), '创建成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("启动考核批次")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function planStart(Request $request, string $id): Response
    {
        try {
            $plan = $this->perf()->startPlan($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($plan), '批次已启动');
    }

    #[\erikwang2013\apidoc\annotation\Title("归档考核批次")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function planArchive(Request $request, string $id): Response
    {
        try {
            $plan = $this->perf()->archivePlan($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($plan), '批次已归档');
    }

    // ---------- 评分（erp_hr_perf_score） ----------

    #[\erikwang2013\apidoc\annotation\Title("提交评分")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/score")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"plan_id", type:"int", desc:"考核批次ID（进行中），必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"被考核员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"rater_type", type:"int", desc:"评分人类型:1自评2上级3同事360，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"rater_id", type:"int", desc:"评分人ID，默认当前管理员")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function scoreSubmit(Request $request): Response
    {
        $validator = validator($request->all(), [
            'plan_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'rater_type' => 'required|integer|between:1,3',
            'scores' => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $count = $this->perf()->submitScore(
                (int) $request->input('plan_id'),
                (int) $request->input('employee_id'),
                (int) $request->input('rater_id', $request->adminId ?? 0),
                (int) $request->input('rater_type'),
                (array) $request->input('scores', [])
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success(['count' => $count], '评分已提交');
    }

    #[\erikwang2013\apidoc\annotation\Title("评分记录列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/score")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"plan_id", type:"int", desc:"考核批次ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"被考核员工ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"rater_id", type:"int", desc:"评分人ID")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function scoreIndex(Request $request): Response
    {
        $result = $this->perf()->list(HrPerfScore::class, [
            'plan_id' => $request->input('plan_id'),
            'employee_id' => $request->input('employee_id'),
            'rater_id' => $request->input('rater_id'),
        ], (int) $request->input('page', 1), (int) $request->input('limit', 15), [
            'eqFilters' => ['plan_id', 'employee_id', 'rater_id'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("员工考核汇总")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/perf/score/summary")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"plan_id", type:"int", desc:"考核批次ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"被考核员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据（无评分记录时 data 为 null）")]

    public function summary(Request $request): Response
    {
        try {
            $summary = $this->perf()->summary(
                (int) $request->input('plan_id', 0),
                (int) $request->input('employee_id', 0)
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 404);
        }

        return $this->success($summary ?? null, $summary === null ? '该员工暂无评分记录' : '请求成功');
    }

    /** 模板 payload：顶层 id 与 items[].id 均 hashid 化。 */
    private function encodeTemplate(array $template): array
    {
        $template['items'] = array_map(fn ($item) => $this->encodeIds($item), $template['items']);

        return $this->encodeIds($template);
    }

    private function perf(): PerformanceService
    {
        return Container::get(PerformanceService::class);
    }
}
