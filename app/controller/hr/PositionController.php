<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrDepartment;
use app\model\HrPosition;
use app\service\hr\HrService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 职位管理
 */
#[\erikwang2013\apidoc\annotation\Tag('人力资源')]
#[\erikwang2013\apidoc\annotation\Title('职位')]
#[\erikwang2013\apidoc\annotation\Group('人力资源')]

class PositionController extends BaseController
{
    /**
     * 职位列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('职位列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询职位记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/position')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'department_id', type:'int', desc:'部门ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
            'department_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        // 同上：职位列表按部门筛选（下拉值为 hashid）
        $departmentId = $request->input('department_id');
        if ($departmentId !== null && $departmentId !== '') {
            $departmentId = $this->decodeFlexibleId($departmentId);
            if ($departmentId === null) {
                return $this->fail('部门ID' . $this->trans('Invalid'), 422);
            }
        }

        $result = $this->hr()->list(HrPosition::class, [
            'keyword' => $keyword,
            'status' => $status,
            'department_id' => $departmentId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['department_id'],
        ]);
        $list = $this->appendDepartmentName($result['list']);
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'department_id']), $list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建职位
     */
    #[\erikwang2013\apidoc\annotation\Title('创建职位')]
    #[\erikwang2013\apidoc\annotation\Desc('新增职位记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/position')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'职位编码，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'职位名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'department_id', type:'int', desc:'所属部门ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'department_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $item = $this->hr()->create(HrPosition::class, $data);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 职位详情
     */
    #[\erikwang2013\apidoc\annotation\Title('职位详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看职位详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'职位ID')]
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
        $item = $this->hr()->find(HrPosition::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $rows = $this->appendDepartmentName([$item->toArray()]);

        return $this->success($this->encodeIds($rows[0]));
    }

    /**
     * 更新职位
     */
    #[\erikwang2013\apidoc\annotation\Title('更新职位')]
    #[\erikwang2013\apidoc\annotation\Desc('修改职位信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'职位ID')]
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

        $item = $this->hr()->update(HrPosition::class, $id, $data);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除职位
     */
    #[\erikwang2013\apidoc\annotation\Title('删除职位')]
    #[\erikwang2013\apidoc\annotation\Desc('删除职位记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('人力资源')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'职位ID')]
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
        $item = $this->hr()->find(HrPosition::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        if ($this->hr()->hasEmployeesInPosition($id)) {
            return $this->fail($this->trans('Employees exist under this position, please reassign them first'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrPosition::class, $id);

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
        foreach (['department_id' => '部门ID'] as $field => $label) {
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
     * 行级补 department_name（所属部门名，含已软删部门），一次 pluck 成映射、行内查表，无 N+1。
     * 须在 encodeIds 之前调用（此后外键已变 hashid）。
     */
    private function appendDepartmentName(array $rows): array
    {
        $ids = array_values(array_unique(array_map(static fn ($r) => (int) ($r['department_id'] ?? 0), $rows)));
        $names = HrDepartment::withTrashed()->whereIn('id', $ids)->pluck('name', 'id');

        return array_map(static function (array $row) use ($names): array {
            $row['department_name'] = (string) ($names[(int) ($row['department_id'] ?? 0)] ?? '');

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
