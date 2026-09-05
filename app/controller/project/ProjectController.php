<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\project;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\Project;
use app\model\ProjectTask;
use support\Request;
use support\Response;

/**
 * 项目管理
 * @Apidoc\Tag("项目管理")
 */
class ProjectController extends BaseController
{
    /**
     * 项目列表（分页）
     * @Apidoc\Title("项目列表")
     * @Apidoc\Desc("获取项目分页列表，支持关键字/状态/负责人筛选")
     * @Apidoc\Url("/admin/v1/project")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(名称/编码)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Param(name="manager_user_id", type="string", default="", desc="负责人ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="项目列表(含进度)"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
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

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建项目
     * @Apidoc\Title("创建项目")
     * @Apidoc\Desc("创建一个新项目")
     * @Apidoc\Url("/admin/v1/project")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="name", type="string", require=true, desc="项目名称")
     * @Apidoc\Param(name="manager_user_id", type="int", require=true, desc="负责人用户ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="项目信息")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'manager_user_id' => 'required|integer|min:1']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new Project();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 项目详情
     * @Apidoc\Title("项目详情")
     * @Apidoc\Desc("获取指定项目的详细信息，包含计算后的进度")
     * @Apidoc\Url("/admin/v1/project/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="项目ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="项目详情(含进度)")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Project::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $result = $this->encodeIds($item->toArray());
        $result['progress'] = $this->calcProgress($item->id);

        return $this->success($result);
    }

    /**
     * 更新项目
     * @Apidoc\Title("更新项目")
     * @Apidoc\Desc("更新指定项目的信息")
     * @Apidoc\Url("/admin/v1/project/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="项目ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的项目信息")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Project::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除项目
     * @Apidoc\Title("删除项目")
     * @Apidoc\Desc("软删除指定项目，需要密码二次确认")
     * @Apidoc\Url("/admin/v1/project/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("项目管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="项目ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
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
