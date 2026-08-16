<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrDepartment;
use app\service\hr\HrService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 部门管理 — 树形CRUD
  * @Apidoc\Tag("人力资源")
 */
class DepartmentController extends BaseController
{
    /**
     * 部门树形列表
     * @Apidoc\Title("部门列表")
     * @Apidoc\Desc("查询部门列表，支持关键词和状态筛选")
     * @Apidoc\Url("/admin/hr/department")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $list = $this->hr()->all(HrDepartment::class, [
            'keyword' => $keyword,
            'status' => $status,
        ], [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
            'orderBy' => 'id',
            'orderDir' => 'asc',
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 创建部门
     * @Apidoc\Title("创建部门")
     * @Apidoc\Desc("新增部门记录")
     * @Apidoc\Url("/admin/hr/department")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="code", type="string", desc="部门编码，必填")
     * @Apidoc\Param(name="name", type="string", desc="部门名称，必填")
     * @Apidoc\Param(name="parent_id", type="int", desc="上级部门ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->hr()->create(HrDepartment::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 部门详情
     * @Apidoc\Title("部门详情")
     * @Apidoc\Desc("查看部门详细信息")
     * @Apidoc\Url("/admin/hr/department/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="部门ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrDepartment::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新部门
     * @Apidoc\Title("更新部门")
     * @Apidoc\Desc("修改部门信息")
     * @Apidoc\Url("/admin/hr/department/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="部门ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->update(HrDepartment::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除部门
     * @Apidoc\Title("删除部门")
     * @Apidoc\Desc("删除部门记录，需先删除子部门，需密码确认")
     * @Apidoc\Url("/admin/hr/department/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="部门ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrDepartment::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($this->hr()->hasChildDepartments($id)) {
            return $this->fail('存在子部门，请先删除子部门', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrDepartment::class, $id);

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
