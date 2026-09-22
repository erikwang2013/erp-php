<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsCarrier;
use app\model\TmsFreightInvoice;
use app\model\TmsShipment;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('运费发票')]
#[\erikwang2013\apidoc\annotation\Group('运输管理TMS')]

class FreightInvoiceController extends BaseController
{
    /**
     * 运费发票列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('运费发票列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取运费发票列表，支持分页、编码搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/freight-invoice')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（编码）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
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
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = TmsFreightInvoice::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->toArray();
        // 行补承运商名与运单号（表只有 carrier_id/shipment_id）；运单号键沿用 tms/TrackingController:68
        // 的 shipment_code（运单无 name 列，两端别名表也把 shipment_id 登记到该键）
        $carrierNames = TmsCarrier::query()
            ->whereIn('id', array_column($rows, 'carrier_id'))
            ->pluck('name', 'id')->all();
        $shipmentCodes = TmsShipment::query()
            ->whereIn('id', array_column($rows, 'shipment_id'))
            ->pluck('code', 'id')->all();
        $list = array_map(function (array $item) use ($carrierNames, $shipmentCodes) {
            // 名称按裸 ID 查（encodeIds 之后这两个键是 hashid）
            $item['carrier_name'] = $carrierNames[$item['carrier_id']] ?? '';
            $item['shipment_code'] = $shipmentCodes[$item['shipment_id']] ?? '';

            return $this->encodeIds($item, ['id', 'carrier_id', 'shipment_id']);
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建运费发票
     */
    #[\erikwang2013\apidoc\annotation\Title('创建运费发票')]
    #[\erikwang2013\apidoc\annotation\Desc('创建运费发票，编码留空由后端自生成')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/freight-invoice')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'carrier_id', type:'string', desc:'承运商ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'shipment_id', type:'string', desc:'运单ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'发票编码，留空由后端自生成')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // code 原为 required|string|max:200，而下面又有 empty() 自生成兜底 —— 必填使兜底永不生效，
        // 用户不填单号就 422。改可选让兜底生效；列宽 VARCHAR(50)，原 max:200 也偏松
        $validator = validator($request->all(), ['code' => 'nullable|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 表里 NOT NULL 无默认的非 id 列是 code/carrier_id/shipment_id：两个 FK 请求里传的是
        // hashid，直填会写坏 bigint，故双模解码，非法一律 422（不留 MySQL 1366 500）
        $carrierId = $this->decodeFlexibleId($request->input('carrier_id'));
        if ($carrierId === null || $carrierId < 1) {
            return $this->fail($this->trans('Invalid carrier'), 422);
        }
        $shipmentId = $this->decodeFlexibleId($request->input('shipment_id'));
        if ($shipmentId === null || $shipmentId < 1) {
            return $this->fail($this->trans('Invalid shipment'), 422);
        }

        $item = new TmsFreightInvoice();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->fill(['carrier_id' => $carrierId, 'shipment_id' => $shipmentId]);
        if (empty($item->code)) {
            $item->code = 'tms/freight-invoice' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 运费发票详情
     */
    #[\erikwang2013\apidoc\annotation\Title('运费发票详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取运费发票详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新运费发票
     */
    #[\erikwang2013\apidoc\annotation\Title('更新运费发票')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新运费发票信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除运费发票
     */
    #[\erikwang2013\apidoc\annotation\Title('删除运费发票')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除运费发票，需操作密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'记录ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'操作密码（二次确认）')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 确认运费发票
     */
    #[\erikwang2013\apidoc\annotation\Title('确认运费发票')]
    #[\erikwang2013\apidoc\annotation\Desc('确认运费发票，状态置为已确认(1)')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发票ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function confirm(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = \app\model\TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->status = 1;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Freight invoice confirmed'));
    }

    /**
     * 支付运费发票
     */
    #[\erikwang2013\apidoc\annotation\Title('支付运费发票')]
    #[\erikwang2013\apidoc\annotation\Desc('支付运费发票，需先确认(状态1)，支付后状态置为已支付(2)')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'发票ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function pay(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = \app\model\TmsFreightInvoice::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ($item->status !== 1) {
            return $this->fail($this->trans('Please confirm the freight invoice first'), 400);
        }
        $item->status = 2;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Freight invoice paid'));
    }
}
