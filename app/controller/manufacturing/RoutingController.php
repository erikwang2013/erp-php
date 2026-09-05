<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgRouting;
use app\service\manufacturing\ManufacturingService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 工艺路线管理
 */
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Title("工艺路线")]

class RoutingController extends BaseController
{
    /**
     * 工艺路线列表
     */
#[\erikwang2013\apidoc\annotation\Title("工艺路线列表")]
#[\erikwang2013\apidoc\annotation\Desc("按产品分组查询工艺路线，按seq排序")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/routing")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"product_id", type:"int", desc:"产品ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $productId = $request->input('product_id');

        $list = $this->mfg()->all(MfgRouting::class, [
            'product_id' => $productId,
        ], [
            'truthyFilters' => ['product_id'],
            'orderBy' => [['product_id', 'asc'], ['seq', 'asc']],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 添加工序
     */
#[\erikwang2013\apidoc\annotation\Title("添加工艺工序")]
#[\erikwang2013\apidoc\annotation\Desc("新增工艺路线工序记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/routing")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"product_id", type:"int", desc:"产品ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"工序名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"seq", type:"int", desc:"工序序号，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"workstation_id", type:"int", desc:"工作站ID，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'product_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'seq' => 'required|integer',
            'workstation_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->mfg()->create(MfgRouting::class, $request->all(), ['created_at' => date('Y-m-d H:i:s')]);

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工序详情
     */
#[\erikwang2013\apidoc\annotation\Title("工艺工序详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看工艺路线工序详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工序ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgRouting::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新工序
     */
#[\erikwang2013\apidoc\annotation\Title("更新工艺工序")]
#[\erikwang2013\apidoc\annotation\Desc("修改工艺路线工序信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工序ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->update(MfgRouting::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工序
     */
#[\erikwang2013\apidoc\annotation\Title("删除工艺工序")]
#[\erikwang2013\apidoc\annotation\Desc("删除工艺路线工序记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工序ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgRouting::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->mfg()->delete(MfgRouting::class, $id);

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
