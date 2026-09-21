<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\HrDepartment;
use app\service\hr\HrService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 部门管理 — 树形CRUD
 */
#[\erikwang2013\apidoc\annotation\Tag('人力资源')]
#[\erikwang2013\apidoc\annotation\Title('部门')]
#[\erikwang2013\apidoc\annotation\Group('人力资源')]

class DepartmentController extends BaseController
{
    /**
     * 部门树形列表
     */
    #[\erikwang2013\apidoc\annotation\Title('部门列表')]
    #[\erikwang2013\apidoc\annotation\Desc('查询部门列表，支持关键词和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/department')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
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
        $list = $this->appendNames($list);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'parent_id', 'manager_user_id']), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 创建部门
     */
    #[\erikwang2013\apidoc\annotation\Title('创建部门')]
    #[\erikwang2013\apidoc\annotation\Desc('新增部门记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/department')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'部门编码，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'部门名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'parent_id', type:'int', desc:'上级部门ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'parent_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $item = $this->hr()->create(HrDepartment::class, $data);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 部门详情
     */
    #[\erikwang2013\apidoc\annotation\Title('部门详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看部门详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'部门ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrDepartment::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $rows = $this->appendNames([$item->toArray()]);

        return $this->success($this->encodeIds($rows[0]));
    }

    /**
     * 更新部门
     */
    #[\erikwang2013\apidoc\annotation\Title('更新部门')]
    #[\erikwang2013\apidoc\annotation\Desc('修改部门信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'部门ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        try {
            $data = $this->decodeForeignKeys($request);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $item = $this->hr()->update(HrDepartment::class, $id, $data);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除部门
     */
    #[\erikwang2013\apidoc\annotation\Title('删除部门')]
    #[\erikwang2013\apidoc\annotation\Desc('删除部门记录，需先删除子部门，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'部门ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrDepartment::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        if ($this->hr()->hasChildDepartments($id)) {
            return $this->fail($this->trans('Child departments exist, please delete them first'), 422);
        }

        if ($this->hr()->hasEmployeesInDepartment($id)) {
            return $this->fail($this->trans('Employees exist under this department, please reassign them first'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrDepartment::class, $id);

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 可选外键双模解码（与 EmployeeController 同口径）：
     * 未传 / null / '' / '0' → 视为不改动，从写入数据中剔除；
     * 非空但解不出（含 (int) 会静默变 0 的垃圾串）→ 422，防孤儿行/1366 落库报 500。
     */
    private function decodeForeignKeys(Request $request): array
    {
        $data = $request->all();
        foreach (['parent_id' => '上级部门ID', 'manager_user_id' => '负责人ID'] as $field => $label) {
            $raw = $request->input($field);
            $rawStr = $raw === null ? '' : (string) $raw;
            if ($rawStr === '' || $rawStr === '0') {
                unset($data[$field]);
                continue;
            }
            $decoded = $this->decodeFlexibleId($rawStr);
            if ($decoded === null || $decoded < 1) {
                throw new InvalidArgumentException($label . $this->trans('Invalid'));
            }
            $data[$field] = $decoded;
        }

        return $data;
    }

    /**
     * 行级补关联名：parent_name（上级部门名，含已软删部门）/ manager_name（负责人 real_name）。
     * 名称一次 pluck 成映射、行内查表，无 N+1。须在 encodeIds 之前调用（此后外键已变 hashid）。
     */
    private function appendNames(array $rows): array
    {
        $parentIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['parent_id'] ?? 0), $rows)));
        $parentNames = HrDepartment::withTrashed()->whereIn('id', $parentIds)->pluck('name', 'id');
        $managerIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['manager_user_id'] ?? 0), $rows)));
        $managerNames = AdminUser::query()->whereIn('id', $managerIds)->pluck('real_name', 'id');

        return array_map(static function (array $row) use ($parentNames, $managerNames): array {
            $row['parent_name'] = (string) ($parentNames[(int) ($row['parent_id'] ?? 0)] ?? '');
            $row['manager_name'] = (string) ($managerNames[(int) ($row['manager_user_id'] ?? 0)] ?? '');

            return $row;
        }, $rows);
    }

    /**
     * HR 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function hr(): HrService
    {
        return Container::get(HrService::class);
    }
}
