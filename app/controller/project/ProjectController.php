<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\project;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\Project;
use app\model\ProjectTask;
use support\Request;
use support\Response;

/**
 * 项目管理
 */
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Title("项目")]
#[\erikwang2013\apidoc\annotation\Group("项目管理")]

class ProjectController extends BaseController
{
    /**
     * 项目列表（分页）
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("项目列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取项目分页列表，支持关键字/状态/负责人筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/project")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词(名称/编码)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Param(name:"manager_user_id", type:"string", default:"", desc:"负责人ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("list", type:"array", desc:"项目列表(含进度)")]
#[\erikwang2013\apidoc\annotation\Returned("total", type:"int", desc:"总条数")]
#[\erikwang2013\apidoc\annotation\Returned("page", type:"int", desc:"当前页码")]
#[\erikwang2013\apidoc\annotation\Returned("limit", type:"int", desc:"每页条数")]

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
        if ($managerId !== null && $managerId !== '') {
            $query->where('manager_user_id', $this->decodeIdSafe((string) $managerId) ?? (int) $managerId);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $item->toArray())->all();

        // 负责人展示名（行级同查询无 N+1）；manager_user_id 一并编码，编辑弹窗回填同源选项
        $managerIds = array_values(array_unique(array_map(fn ($r) => (int) ($r['manager_user_id'] ?? 0), $rows)));
        $managerNames = AdminUser::whereIn('id', $managerIds)->pluck('real_name', 'id');
        $list = array_map(function ($row) use ($managerNames) {
            $row['manager_name'] = (string) ($managerNames[(int) ($row['manager_user_id'] ?? 0)] ?? '');
            // 计算实际进度
            $row['progress'] = $this->calcProgress($row['id']);

            return $this->encodeIds($row, ['id', 'manager_user_id']);
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建项目
     */
#[\erikwang2013\apidoc\annotation\Title("创建项目")]
#[\erikwang2013\apidoc\annotation\Desc("创建一个新项目")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/project")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", require:true, desc:"项目名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", require:true, desc:"项目编号(唯一)")]
#[\erikwang2013\apidoc\annotation\Param(name:"manager_user_id", type:"string", require:true, desc:"负责人用户ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"项目信息")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'code' => 'required|string|max:50', 'manager_user_id' => 'required|string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new Project();
        $item->id = $this->generateId();
        // manager_user_id 接受 /admin/v1/user 列表下发的 hashid，先解码回 int 再 fill
        $request->merge(['manager_user_id' => $this->decodeIdSafe((string) $request->input('manager_user_id')) ?? 0]);
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'manager_user_id']), '创建成功');
    }

    /**
     * 项目详情
     */
#[\erikwang2013\apidoc\annotation\Title("项目详情")]
#[\erikwang2013\apidoc\annotation\Desc("获取指定项目的详细信息，包含计算后的进度")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"项目ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"项目详情(含进度)")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Project::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $result = $this->encodeIds($item->toArray(), ['id', 'manager_user_id']);
        $result['progress'] = $this->calcProgress($item->id);

        return $this->success($result);
    }

    /**
     * 更新项目
     */
#[\erikwang2013\apidoc\annotation\Title("更新项目")]
#[\erikwang2013\apidoc\annotation\Desc("更新指定项目的信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"项目ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的项目信息")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Project::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($request->input('manager_user_id', '') !== '') {
            $request->merge(['manager_user_id' => $this->decodeIdSafe((string) $request->input('manager_user_id')) ?? 0]);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'manager_user_id']), '更新成功');
    }

    /**
     * 删除项目
     */
#[\erikwang2013\apidoc\annotation\Title("删除项目")]
#[\erikwang2013\apidoc\annotation\Desc("软删除指定项目，需要密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("项目管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"项目ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"当前管理员密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Project::find($id);
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
     * 计算项目进度（基于任务完成率）
     */
    protected function calcProgress(int $projectId): int
    {
        $tasks = ProjectTask::where('project_id', $projectId)->get();
        if ($tasks->isEmpty()) {
            return 0;
        }

        $sum = '0';
        foreach ($tasks as $task) {
            $sum = bcadd($sum, bc_norm($task->progress), 6);
        }

        return (int) bc_round(bcdiv($sum, (string) count($tasks), 6), 0);
    }
}
