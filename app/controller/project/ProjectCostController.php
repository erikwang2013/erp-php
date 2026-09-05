<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\project;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\ProjectCost;
use app\service\project\ProjectCostService;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 项目成本归集与预算偏差
 * @Apidoc\Tag("项目管理")
 */
class ProjectCostController extends BaseController
{
    /**
     * 成本台账列表（分页）
     * @Apidoc\Title("成本台账列表")
     * @Apidoc\Desc("按项目/类别/来源/日期区间分页查询成本归集行")
     * @Apidoc\Url("/admin/v1/project/cost")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID(hashid)")
     * @Apidoc\Param(name="category", type="int", desc="类别: 1人工 2材料 3其他")
     * @Apidoc\Param(name="source_type", type="string", desc="来源: timesheet/manual")
     * @Apidoc\Param(name="from", type="string", desc="发生日期起 Y-m-d")
     * @Apidoc\Param(name="to", type="string", desc="发生日期止 Y-m-d")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $query = ProjectCost::query();

        $projectId = $request->input('project_id', '');
        if ($projectId !== '') {
            $query->where('project_id', $this->decodeId($projectId));
        }
        $category = $request->input('category');
        if ($category !== null && $category !== '') {
            $query->where('category', (int) $category);
        }
        $sourceType = $request->input('source_type', '');
        if ($sourceType !== '') {
            $query->where('source_type', $sourceType);
        }
        $from = $request->input('from', '');
        $to = $request->input('to', '');
        if ($from !== '') {
            $query->where('work_date', '>=', $from);
        }
        if ($to !== '') {
            $query->where('work_date', '<=', $to);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('work_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 手工录入成本
     * @Apidoc\Title("手工录入成本")
     * @Apidoc\Desc("人工=工时×费率；材料/其他=直接金额，金额列均存 DECIMAL 字符串")
     * @Apidoc\Url("/admin/v1/project/cost")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID(hashid)，必填")
     * @Apidoc\Param(name="work_date", type="string", desc="发生日期 Y-m-d，必填")
     * @Apidoc\Param(name="category", type="int", desc="类别: 1人工 2材料 3其他，必填")
     * @Apidoc\Param(name="hours", type="float", desc="工时，类别=1时必填")
     * @Apidoc\Param(name="rate", type="float", desc="费率(元/小时)，类别=1时选填，默认按费率快照规则取0")
     * @Apidoc\Param(name="cost", type="float", desc="金额，类别=2/3时必填")
     * @Apidoc\Param(name="task_id", type="string", desc="关联任务ID(hashid)，选填")
     * @Apidoc\Param(name="employee_id", type="string", desc="员工ID(hashid)，选填")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'project_id' => 'required|string',
            'work_date' => 'required|date',
            'category' => 'required|integer|in:1,2,3',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $data = $request->all();
        $data['project_id'] = $this->decodeId((string) $data['project_id']);
        if (!empty($data['task_id'])) {
            $data['task_id'] = $this->decodeId((string) $data['task_id']);
        }
        if (!empty($data['employee_id'])) {
            $data['employee_id'] = $this->decodeId((string) $data['employee_id']);
        }

        try {
            $cost = $this->cost()->createManual((int) $data['project_id'], $data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($cost->toArray()), '创建成功');
    }

    /**
     * 删除成本记录
     * @Apidoc\Title("删除成本记录")
     * @Apidoc\Desc("仅手工录入的成本行可删除，需密码确认；工时归集行不可删")
     * @Apidoc\Url("/admin/v1/project/cost/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", desc="成本记录ID(hashid)")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = ProjectCost::query()->find($id);
        if (!$item) {
            return $this->fail('成本记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        try {
            $this->cost()->deleteManual($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    /**
     * 工时归集（自动生成成本）
     * @Apidoc\Title("工时归集生成成本")
     * @Apidoc\Desc("按区间取工时台账×成员费率生成人工成本，幂等可重跑；未配置费率成员整行拒绝并列出")
     * @Apidoc\Url("/admin/v1/project/cost/generate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID(hashid)，必填")
     * @Apidoc\Param(name="from", type="string", desc="起始日期 Y-m-d，必填")
     * @Apidoc\Param(name="to", type="string", desc="截止日期 Y-m-d，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function generate(Request $request): Response
    {
        $validator = validator($request->all(), [
            'project_id' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $result = $this->cost()->generateFromTimesheet(
                $this->decodeId((string) $request->input('project_id')),
                (string) $request->input('from'),
                (string) $request->input('to'),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        foreach ($result['details'] as &$detail) {
            $detail = $this->encodeIds($detail, ['timesheet_id', 'user_id']);
        }
        unset($detail);

        return $this->success($result, '归集完成');
    }

    /**
     * 项目损益（预算 vs 实际成本）
     * @Apidoc\Title("项目损益")
     * @Apidoc\Desc("预算-实际成本偏差；偏差率=偏差/预算×100%，预算为0时偏差率为null；超支不阻断")
     * @Apidoc\Url("/admin/v1/project/cost/pnl")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="project_id", type="string", desc="项目ID(hashid)，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function pnl(Request $request): Response
    {
        $validator = validator($request->all(), ['project_id' => 'required|string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $result = $this->cost()->projectPnl($this->decodeId((string) $request->input('project_id')));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        foreach ($result['labour_details'] as &$line) {
            $line = $this->encodeIds($line, ['task_id', 'employee_id', 'timesheet_id']);
        }
        unset($line);

        return $this->success($result);
    }

    /**
     * 成本服务实例
     */
    private function cost(): ProjectCostService
    {
        return Container::get(ProjectCostService::class);
    }
}
