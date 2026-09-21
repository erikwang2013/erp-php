<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsShipment;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('运单')]
#[\erikwang2013\apidoc\annotation\Group('运输管理TMS')]

class ShipmentController extends BaseController
{
    /**
     * 运单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('运单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取运单列表，支持分页、编码搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/shipment')]
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

        $query = TmsShipment::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'carrier_service_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建运单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建运单')]
    #[\erikwang2013\apidoc\annotation\Desc('创建运单，编码必填（缺省自动生成）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/shipment')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'运单编码，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // code 真实列宽 VARCHAR(50)：原 max:200 会放过超长串去撞 MySQL 1406/500
        $validator = validator($request->all(), ['code' => 'required|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new TmsShipment();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'tms/shipment' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 运单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('运单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取运单详情')]
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
        $item = TmsShipment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新运单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新运单')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新运单信息')]
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
        $item = TmsShipment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除运单
     */
    #[\erikwang2013\apidoc\annotation\Title('删除运单')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除运单，需操作密码二次确认')]
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

        $item = TmsShipment::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 确认发货
     */
    #[\erikwang2013\apidoc\annotation\Title('确认发货')]
    #[\erikwang2013\apidoc\annotation\Desc('提交发货确认，关联发货单与OMS订单')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'运单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'fulfillment_id', type:'int', default:0, desc:'发货单ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'oms_order_id', type:'int', default:0, desc:'OMS订单ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function ship(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'fulfillment_id' => 'string',
            'oms_order_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        // 两个外键由前端下拉下发（hashid 串），原样进 confirmShip(int ...) 直接 TypeError → 500；
        // 双模解码（hashid 或原生数字），缺失/非法一律 422
        $fulfillmentId = $this->decodeFlexibleId($request->input('fulfillment_id'));
        $omsOrderId = $this->decodeFlexibleId($request->input('oms_order_id'));
        if ($fulfillmentId === null || $fulfillmentId < 1 || $omsOrderId === null || $omsOrderId < 1) {
            return $this->fail($this->trans('Invalid fulfillment_id or oms_order_id'), 422);
        }
        try {
            $svc = new \app\service\tms\TmsShipmentService();
            $svc->confirmShip($id, $fulfillmentId, $omsOrderId);

            return $this->success([], $this->trans('Shipment confirmation completed'));
        } catch (\Throwable $e) {
            $this->logError('确认发货', $e);

            // 业务规则拒绝（运单不存在/状态不允许发货/履约单已发货）是调用方可纠正的输入问题 → 422；
            // PDOException 也是 RuntimeException（SQL/连接故障属服务端），须排除后再判 500
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }
    }

    /**
     * 获取面单
     */
    #[\erikwang2013\apidoc\annotation\Title('获取面单')]
    #[\erikwang2013\apidoc\annotation\Desc('按运单获取面单下载地址，面单生成请求已提交')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'运单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据（label_url 面单下载地址）')]

    public function getLabel(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 校验 hashid 合法性，同时保留原始 hash 用于面单下载地址
        $decodedId = $this->decodeIdSafe($id);
        if (!$decodedId) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        return $this->success(['label_url' => '/api/shipping-label/' . $id], $this->trans('Waybill generation request submitted'));
    }
}
