<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\MfgWorkstation;
use app\service\manufacturing\ManufacturingService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 工作站管理
  * @Apidoc\Tag("生产制造")
 */#[Apidoc\Tag("生产制造")]

class WorkstationController extends BaseController
{
    /**
     * 工作站列表
     * @Apidoc\Title("工作站列表")
     * @Apidoc\Desc("查询工作站记录")
     * @Apidoc\Url("/admin/v1/mfg/workstation")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("工作站列表")]
#[Apidoc\Desc("查询工作站记录")]
#[Apidoc\Url("/admin/v1/mfg/workstation")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("生产制造")]
#[Apidoc\Param(name:"keyword", type:"string", desc:"关键词")]
#[Apidoc\Param(name:"status", type:"int", desc:"状态")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $list = $this->mfg()->all(MfgWorkstation::class, [
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
     * 创建工作站
     * @Apidoc\Title("创建工作站")
     * @Apidoc\Desc("新增工作站记录")
     * @Apidoc\Url("/admin/v1/mfg/workstation")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="工作站编码，必填")
     * @Apidoc\Param(name="name", type="string", desc="工作站名称，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建工作站")]
#[Apidoc\Desc("新增工作站记录")]
#[Apidoc\Url("/admin/v1/mfg/workstation")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("生产制造")]
#[Apidoc\Param(name:"code", type:"string", desc:"工作站编码，必填")]
#[Apidoc\Param(name:"name", type:"string", desc:"工作站名称，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->mfg()->create(MfgWorkstation::class, $request->all(), ['created_at' => date('Y-m-d H:i:s')]);

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工作站详情
     * @Apidoc\Title("工作站详情")
     * @Apidoc\Desc("查看工作站详细信息")
     * @Apidoc\Url("/admin/v1/mfg/workstation/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工作站ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("工作站详情")]
#[Apidoc\Desc("查看工作站详细信息")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("生产制造")]
#[Apidoc\Param(name:"id", type:"string", desc:"工作站ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgWorkstation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新工作站
     * @Apidoc\Title("更新工作站")
     * @Apidoc\Desc("修改工作站信息")
     * @Apidoc\Url("/admin/v1/mfg/workstation/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工作站ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("更新工作站")]
#[Apidoc\Desc("修改工作站信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("生产制造")]
#[Apidoc\Param(name:"id", type:"string", desc:"工作站ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->update(MfgWorkstation::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工作站
     * @Apidoc\Title("删除工作站")
     * @Apidoc\Desc("删除工作站记录，需密码确认")
     * @Apidoc\Url("/admin/v1/mfg/workstation/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工作站ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除工作站")]
#[Apidoc\Desc("删除工作站记录，需密码确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("生产制造")]
#[Apidoc\Param(name:"id", type:"string", desc:"工作站ID")]
#[Apidoc\Param(name:"password", type:"string", desc:"管理员密码")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgWorkstation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->mfg()->delete(MfgWorkstation::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 生产制造薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function mfg(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
    }
}
