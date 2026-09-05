<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgProductionOrder;
use app\service\manufacturing\ManufacturingService;
use app\service\manufacturing\MfgCostService;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 生产工单管理 — CRUD + 状态流转
  * @Apidoc\Tag("生产制造")
 */#[\erikwang2013\apidoc\annotation\Tag("生产制造")]

class ProductionController extends BaseController
{
    /**
     * 生产工单列表（分页）
     * @Apidoc\Title("生产工单列表")
     * @Apidoc\Desc("分页查询生产工单记录")
     * @Apidoc\Url("/admin/v1/mfg/production")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="bom_id", type="int", desc="BOM ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("生产工单列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询生产工单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/production")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Param(name:"bom_id", type:"int", desc:"BOM ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $bomId = $request->input('bom_id');

        $result = $this->mfg()->list(MfgProductionOrder::class, [
            'keyword' => $keyword,
            'status' => $status,
            'bom_id' => $bomId,
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['bom_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建生产工单
     * @Apidoc\Title("创建生产工单")
     * @Apidoc\Desc("新增生产工单记录")
     * @Apidoc\Url("/admin/v1/mfg/production")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="工单编码，必填")
     * @Apidoc\Param(name="bom_id", type="int", desc="BOM ID，必填")
     * @Apidoc\Param(name="planned_quantity", type="float", desc="计划数量，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("创建生产工单")]
#[\erikwang2013\apidoc\annotation\Desc("新增生产工单记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/production")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"工单编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"bom_id", type:"int", desc:"BOM ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"planned_quantity", type:"float", desc:"计划数量，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'bom_id' => 'required|integer',
            'planned_quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->mfg()->create(MfgProductionOrder::class, $request->all(), [
            'status' => 0,
            'completed_quantity' => 0,
        ]);

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工单详情
     * @Apidoc\Title("生产工单详情")
     * @Apidoc\Desc("查看生产工单详细信息，含明细和BOM")
     * @Apidoc\Url("/admin/v1/mfg/production/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("生产工单详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看生产工单详细信息，含明细和BOM")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgProductionOrder::class, $id, ['items', 'bom']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }
        if ($item->relationLoaded('bom') && $item->bom) {
            $data['bom'] = $this->encodeIds($item->bom->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新工单
     * @Apidoc\Title("更新生产工单")
     * @Apidoc\Desc("修改生产工单，仅待生产状态可修改")
     * @Apidoc\Url("/admin/v1/mfg/production/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("更新生产工单")]
#[\erikwang2013\apidoc\annotation\Desc("修改生产工单，仅待生产状态可修改")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgProductionOrder::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status !== 0) {
            return $this->fail('只能修改待生产状态的工单', 422);
        }

        $item = $this->mfg()->update(MfgProductionOrder::class, $id, $request->all(), ['status', 'completed_quantity']);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工单
     * @Apidoc\Title("删除生产工单")
     * @Apidoc\Desc("删除生产工单，生产中或已完成不可删除，需密码确认")
     * @Apidoc\Url("/admin/v1/mfg/production/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除生产工单")]
#[\erikwang2013\apidoc\annotation\Desc("删除生产工单，生产中或已完成不可删除，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgProductionOrder::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if (in_array($item->status, [1, 2])) {
            return $this->fail('生产中或已完成的工单不可删除', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->mfg()->deleteProductionOrderWithItems($id);

        return $this->success([], '删除成功');
    }

    /**
     * 开始生产
     * @Apidoc\Title("开始生产")
     * @Apidoc\Desc("将工单状态变更为生产中")
     * @Apidoc\Url("/admin/v1/mfg/production/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("开始生产")]
#[\erikwang2013\apidoc\annotation\Desc("将工单状态变更为生产中")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function start(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);

        try {
            $item = $this->mfg()->startProduction($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '生产已开始');
    }

    /**
     * 完成生产（完工结算：入库 + 成本结转凭证，同事务）
     * @Apidoc\Title("完成生产")
     * @Apidoc\Desc("完工结算并入库产成品，归集成本结转为财务凭证")
     * @Apidoc\Url("/admin/v1/mfg/production/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="工单ID")
     * @Apidoc\Param(name="completed_quantity", type="float", desc="完成数量，缺省取计划数量")
     * @Apidoc\Param(name="warehouse_id", type="int", desc="完工入库仓库ID，缺省取工单仓库")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("完成生产")]
#[\erikwang2013\apidoc\annotation\Desc("完工结算并入库产成品，归集成本结转为财务凭证")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"工单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"completed_quantity", type:"float", desc:"完成数量，缺省取计划数量")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"int", desc:"完工入库仓库ID，缺省取工单仓库")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function complete(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $warehouseId = (int) ($request->input('warehouse_id') ?? 0);

        try {
            $item = $this->cost()->completeWithCost($id, $request->input('completed_quantity') !== null ? (float) $request->input('completed_quantity') : null, $warehouseId);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '生产已完成');
    }

    /**
     * 生产制造薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function mfg(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
    }

    /** 成本核算服务（完工结算走成本口径） */
    private function cost(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }
}
