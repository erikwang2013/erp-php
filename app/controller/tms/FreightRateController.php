<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("运费费率")
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsFreightRate;
use app\service\tms\FreightCalculatorService;
use support\Request;
use support\Response;

class FreightRateController extends BaseController
{
    /**
     * 列表（分页）
     */
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
     * 创建
     */
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
     * 详情
     */
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
     * 更新
     */
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
     * 删除
     */
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
     * @Apidoc\Title("运费试算")
     * @Apidoc\Desc("按承运商服务/目的国/重量匹配费率卡计算运费")
     * @Apidoc\Url("/admin/tms/freight-rate/calculate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运费费率")
     * @Apidoc\Param(name="carrier_service_id", type="int", desc="承运商服务ID，必填")
     * @Apidoc\Param(name="dest_country", type="string", desc="目的国")
     * @Apidoc\Param(name="weight_kg", type="float", desc="重量(kg)，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="运费结果(charge/currency/rate_id)")
     */
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
     * @Apidoc\Title("运费比价")
     * @Apidoc\Desc("按目的国/重量列出所有可用费率并按价格升序")
     * @Apidoc\Url("/admin/tms/freight-rate/rate-shop")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运费费率")
     * @Apidoc\Param(name="dest_country", type="string", desc="目的国")
     * @Apidoc\Param(name="weight_kg", type="float", desc="重量(kg)，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="费率列表(list)")
     */
    public function rateShop(Request $request): Response
    {
        $weightKg = (float) $request->input('weight_kg', 0);
        if ($weightKg <= 0) {
            return $this->fail('weight_kg 必须大于0', 422);
        }

        return $this->success(['list' => (new FreightCalculatorService())->rateShop((string) $request->input('dest_country', ''), $weightKg)]);
    }
}
