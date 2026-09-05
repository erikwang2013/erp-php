<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\HrEmployee;
use app\service\hr\HrService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 员工管理
 * @Apidoc\Tag("人力资源")
 */
class EmployeeController extends BaseController
{
    /**
     * 员工列表（分页）
     * @Apidoc\Title("员工列表")
     * @Apidoc\Desc("获取员工分页列表，支持关键字/状态/部门筛选")
     * @Apidoc\Url("/admin/v1/hr/employee")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(姓名/编码)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Param(name="department_id", type="int", default="", desc="部门ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="员工列表(含部门/职位)"),
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
        $departmentId = $request->input('department_id');

        $result = $this->hr()->list(HrEmployee::class, [
            'keyword' => $keyword,
            'status' => $status,
            'department_id' => $departmentId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['department_id'],
            'with' => ['department', 'position'],
        ]);
        $list = array_map(function ($data) {
            $data['department'] = !empty($data['department']) ? $this->encodeIds($data['department']) : null;
            $data['position'] = !empty($data['position']) ? $this->encodeIds($data['position']) : null;

            return $this->encodeIds($data);
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建员工
     * @Apidoc\Title("创建员工")
     * @Apidoc\Desc("创建一名新员工")
     * @Apidoc\Url("/admin/v1/hr/employee")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="code", type="string", require=true, desc="员工编码")
     * @Apidoc\Param(name="name", type="string", require=true, desc="员工姓名")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="员工信息")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->hr()->create(HrEmployee::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 员工详情
     * @Apidoc\Title("员工详情")
     * @Apidoc\Desc("获取指定员工的详细信息，包含部门和职位")
     * @Apidoc\Url("/admin/v1/hr/employee/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", require=true, desc="员工ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="员工详情(含部门/职位)")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrEmployee::class, $id, ['department', 'position']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        $data['department'] = $item->relationLoaded('department') && $item->department
            ? $this->encodeIds($item->department->toArray()) : null;
        $data['position'] = $item->relationLoaded('position') && $item->position
            ? $this->encodeIds($item->position->toArray()) : null;

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新员工
     * @Apidoc\Title("更新员工")
     * @Apidoc\Desc("更新指定员工的信息")
     * @Apidoc\Url("/admin/v1/hr/employee/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", require=true, desc="员工ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的员工信息")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->update(HrEmployee::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除员工
     * @Apidoc\Title("删除员工")
     * @Apidoc\Desc("软删除指定员工，需要密码二次确认")
     * @Apidoc\Url("/admin/v1/hr/employee/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", require=true, desc="员工ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrEmployee::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrEmployee::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * HR 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function hr(): HrService
    {
        return Container::get(HrService::class);
    }
}
