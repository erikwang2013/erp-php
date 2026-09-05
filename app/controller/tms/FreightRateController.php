<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsFreightRate;
use app\service\tms\FreightCalculatorService;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("运费费率")]
#[\erikwang2013\apidoc\annotation\Group("运输管理TMS")]

class FreightRateController extends BaseController
{
    /**
     * 运费费率列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("运费费率列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取运费费率列表，支持分页和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/freight-rate")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选（0=禁用,1=启用）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = TmsFreightRate::query();

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建运费费率
     */
#[\erikwang2013\apidoc\annotation\Title("创建运费费率")]
#[\erikwang2013\apidoc\annotation\Desc("创建运费费率，编码必填，其余字段按业务传入")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/freight-rate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"费率编码，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new TmsFreightRate();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 运费费率详情
     */
#[\erikwang2013\apidoc\annotation\Title("运费费率详情")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 获取运费费率详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsFreightRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新运费费率
     */
#[\erikwang2013\apidoc\annotation\Title("更新运费费率")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 更新运费费率信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsFreightRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除运费费率
     */
#[\erikwang2013\apidoc\annotation\Title("删除运费费率")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 删除运费费率，需操作密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"操作密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = TmsFreightRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 运费试算
     */
#[\erikwang2013\apidoc\annotation\Title("运费试算")]
#[\erikwang2013\apidoc\annotation\Desc("按承运商服务/目的国/重量匹配费率卡计算运费")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/freight-rate/calculate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运费费率")]
#[\erikwang2013\apidoc\annotation\Param(name:"carrier_service_id", type:"int", desc:"承运商服务ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"dest_country", type:"string", desc:"目的国")]
#[\erikwang2013\apidoc\annotation\Param(name:"weight_kg", type:"float", desc:"重量(kg)，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"运费结果(charge/currency/rate_id)")]

    public function calculate(Request $request): Response
    {
        $carrierServiceId = (int) $request->input('carrier_service_id', 0);
        $weightKg = (float) $request->input('weight_kg', 0);
        if ($carrierServiceId <= 0 || $weightKg <= 0) {
            return $this->fail('carrier_service_id 与 weight_kg 必须大于0', 422);
        }

        return $this->success((new FreightCalculatorService())->calculate($carrierServiceId, (string) $request->input('dest_country', ''), $weightKg));
    }

    /**
     * 运费比价
     */
#[\erikwang2013\apidoc\annotation\Title("运费比价")]
#[\erikwang2013\apidoc\annotation\Desc("按目的国/重量列出所有可用费率并按价格升序")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/freight-rate/rate-shop")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运费费率")]
#[\erikwang2013\apidoc\annotation\Param(name:"dest_country", type:"string", desc:"目的国")]
#[\erikwang2013\apidoc\annotation\Param(name:"weight_kg", type:"float", desc:"重量(kg)，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"费率列表(list)")]

    public function rateShop(Request $request): Response
    {
        $weightKg = (float) $request->input('weight_kg', 0);
        if ($weightKg <= 0) {
            return $this->fail('weight_kg 必须大于0', 422);
        }

        return $this->success(['list' => (new FreightCalculatorService())->rateShop((string) $request->input('dest_country', ''), $weightKg)]);
    }
}
