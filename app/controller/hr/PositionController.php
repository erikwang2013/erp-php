<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrPosition;
use app\service\hr\HrService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 职位管理
 */
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Title("职位")]
#[\erikwang2013\apidoc\annotation\Group("人力资源")]

class PositionController extends BaseController
{
    /**
     * 职位列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("职位列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询职位记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/position")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Param(name:"department_id", type:"int", desc:"部门ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');

        $result = $this->hr()->list(HrPosition::class, [
            'keyword' => $keyword,
            'status' => $status,
            'department_id' => $departmentId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['department_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建职位
     */
#[\erikwang2013\apidoc\annotation\Title("创建职位")]
#[\erikwang2013\apidoc\annotation\Desc("新增职位记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/position")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"职位编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"职位名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"department_id", type:"int", desc:"所属部门ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->hr()->create(HrPosition::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 职位详情
     */
#[\erikwang2013\apidoc\annotation\Title("职位详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看职位详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"职位ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrPosition::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新职位
     */
#[\erikwang2013\apidoc\annotation\Title("更新职位")]
#[\erikwang2013\apidoc\annotation\Desc("修改职位信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"职位ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->update(HrPosition::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除职位
     */
#[\erikwang2013\apidoc\annotation\Title("删除职位")]
#[\erikwang2013\apidoc\annotation\Desc("删除职位记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"职位ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrPosition::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrPosition::class, $id);

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
