<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgMrpPlan;
use app\service\manufacturing\ManufacturingService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * MRP计划管理 — 计划生成 + 列表
  * @Apidoc\Tag("生产制造")
 */#[\erikwang2013\apidoc\annotation\Tag("生产制造")]

class MrpController extends BaseController
{
    /**
     * MRP计划列表（分页）
     * @Apidoc\Title("MRP计划列表")
     * @Apidoc\Desc("分页查询MRP计划记录")
     * @Apidoc\Url("/admin/v1/mfg/mrp")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="period_year", type="int", desc="计划年度")
     * @Apidoc\Param(name="period_month", type="int", desc="计划月份")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("MRP计划列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询MRP计划记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/mrp")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"计划年度")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"计划月份")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $periodYear = $request->input('period_year');
        $periodMonth = $request->input('period_month');
        $status = $request->input('status');

        $result = $this->mfg()->list(MfgMrpPlan::class, [
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'status' => $status,
        ], $page, $limit, [
            'eqFilters' => ['status'],
            'truthyFilters' => ['period_year', 'period_month'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建MRP计划头
     * @Apidoc\Title("创建MRP计划")
     * @Apidoc\Desc("新增MRP计划头记录")
     * @Apidoc\Url("/admin/v1/mfg/mrp")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="计划编码，必填")
     * @Apidoc\Param(name="period_year", type="int", desc="计划年度，必填")
     * @Apidoc\Param(name="period_month", type="int", desc="计划月份，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("创建MRP计划")]
#[\erikwang2013\apidoc\annotation\Desc("新增MRP计划头记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/mrp")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"计划编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"计划年度，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"计划月份，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'period_year' => 'required|integer',
            'period_month' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->mfg()->create(MfgMrpPlan::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * MRP计划详情
     * @Apidoc\Title("MRP计划详情")
     * @Apidoc\Desc("查看MRP计划详细信息，含明细")
     * @Apidoc\Url("/admin/v1/mfg/mrp/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("MRP计划详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看MRP计划详细信息，含明细")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"计划ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgMrpPlan::class, $id, ['items']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新MRP计划
     * @Apidoc\Title("更新MRP计划")
     * @Apidoc\Desc("修改MRP计划，已确认不可修改")
     * @Apidoc\Url("/admin/v1/mfg/mrp/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("更新MRP计划")]
#[\erikwang2013\apidoc\annotation\Desc("修改MRP计划，已确认不可修改")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"计划ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgMrpPlan::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 2) {
            return $this->fail('已确认的计划不可修改', 422);
        }

        $item = $this->mfg()->update(MfgMrpPlan::class, $id, $request->all(), ['status']);

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除MRP计划
     * @Apidoc\Title("删除MRP计划")
     * @Apidoc\Desc("删除MRP计划，连明细一起删除，需密码确认")
     * @Apidoc\Url("/admin/v1/mfg/mrp/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("删除MRP计划")]
#[\erikwang2013\apidoc\annotation\Desc("删除MRP计划，连明细一起删除，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"计划ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->mfg()->find(MfgMrpPlan::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->mfg()->deleteMrpPlanWithItems($id);

        return $this->success([], '删除成功');
    }

    /**
     * 生成MRP计划明细
     * @Apidoc\Title("生成MRP明细")
     * @Apidoc\Desc("基于各产品BOM与库存计算净需求，生成MRP计划明细")
     * @Apidoc\Url("/admin/v1/mfg/mrp/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[\erikwang2013\apidoc\annotation\Title("生成MRP明细")]
#[\erikwang2013\apidoc\annotation\Desc("基于各产品BOM与库存计算净需求，生成MRP计划明细")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"计划ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function generate(Request $request, string $id): Response
    {
        $planId = $this->decodeId($id);

        try {
            $itemCount = $this->mfg()->generateMrpItems($planId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if ($itemCount === null) {
            return $this->fail('计划不存在', 404);
        }

        return $this->success(['items_count' => $itemCount], "MRP计划生成完成，共 {$itemCount} 条明细");
    }

    /**
     * 生产制造薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function mfg(): ManufacturingService
    {
        return Container::get(ManufacturingService::class);
    }
}
