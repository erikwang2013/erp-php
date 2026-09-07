<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsShipment;
use app\model\TmsTrackingEvent;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("物流轨迹")]
#[\erikwang2013\apidoc\annotation\Group("运输管理TMS")]

class TrackingController extends BaseController
{
    /**
     * 物流轨迹列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("物流轨迹列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取物流轨迹列表，支持分页（表无 name/code/status 列，状态码为 status_code 字符串）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/tracking")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"shipment_id", type:"string", default:"", desc:"按运单过滤(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'shipment_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $shipmentId = $request->input('shipment_id', '');

        $query = TmsTrackingEvent::query();
        if ($shipmentId !== null && $shipmentId !== '') {
            // 过滤参数接收 hashid：解码失败/非正数一律 422 明确文案
            $decodedShipmentId = $this->decodeIdSafe((string) $shipmentId);
            if ($decodedShipmentId === null || $decodedShipmentId < 1) {
                return $this->fail('无效的 shipment_id', 422);
            }
            $query->where('shipment_id', $decodedShipmentId);
        }

        $total = $query->count();
        $models = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')->get();
        // 行补运单号（轨迹无名称列，运单号为其归属标识）；FK 编码供编辑回填 hashid
        $shipmentCodes = TmsShipment::whereIn('id', $models->pluck('shipment_id')->all())
            ->pluck('code', 'id')->all();
        $list = $models->map(function ($item) use ($shipmentCodes) {
            $row = $this->encodeIds($item->toArray(), ['id', 'shipment_id']);
            $row['shipment_code'] = $shipmentCodes[$item->shipment_id] ?? '';
            return $row;
        });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建物流轨迹
     */
#[\erikwang2013\apidoc\annotation\Title("创建物流轨迹")]
#[\erikwang2013\apidoc\annotation\Desc("创建物流轨迹记录，运单必填，其余字段按业务传入（表无 code 列）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/tms/tracking")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"shipment_id", type:"string", require:true, desc:"运单ID（hashid）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status_code", type:"string", default:"", desc:"状态码: picked_up/in_transit/out_for_delivery/delivered/exception")]
#[\erikwang2013\apidoc\annotation\Param(name:"description", type:"string", default:"", desc:"事件描述")]
#[\erikwang2013\apidoc\annotation\Param(name:"location", type:"string", default:"", desc:"发生地点")]
#[\erikwang2013\apidoc\annotation\Param(name:"event_time", type:"string", default:"", desc:"事件时间")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['shipment_id' => 'required|string', 'status_code' => 'string', 'description' => 'string', 'location' => 'string', 'event_time' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new TmsTrackingEvent();
        $item->id = $this->generateId();
        // 真实列按请求填充（$fillable 白名单），NOT NULL FK 解码覆盖防 hashid 串入库
        $this->fillModelFromRequest($item, $request);
        $shipmentId = $this->decodeFlexibleId((string) $request->input('shipment_id'));
        if ($shipmentId === null || $shipmentId < 1) {
            return $this->fail('运单ID无效', 422);
        }
        $item->shipment_id = $shipmentId;
        if ($request->input('event_time') === '') {
            $item->event_time = null;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'shipment_id']), $this->trans('created'));
    }

    /**
     * 物流轨迹详情
     */
#[\erikwang2013\apidoc\annotation\Title("物流轨迹详情")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 获取物流轨迹详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsTrackingEvent::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新物流轨迹
     */
#[\erikwang2013\apidoc\annotation\Title("更新物流轨迹")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 更新物流轨迹信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsTrackingEvent::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        // FK 仅可改绑合法运单（hashid/原生数字双模，垃圾串 422）
        $rawShipmentId = $request->input('shipment_id');
        if ($rawShipmentId !== null && $rawShipmentId !== '') {
            $shipmentId = $this->decodeFlexibleId((string) $rawShipmentId);
            if ($shipmentId === null || $shipmentId < 1) {
                return $this->fail('运单ID无效', 422);
            }
            $item->shipment_id = $shipmentId;
        }
        // event_time 可空：空白串转 null（datetime cast 直存 '' 会抛异常）
        if ($request->input('event_time') === '') {
            $item->event_time = null;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'shipment_id']), $this->trans('updated'));
    }

    /**
     * 删除物流轨迹
     */
#[\erikwang2013\apidoc\annotation\Title("删除物流轨迹")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 删除物流轨迹，需操作密码二次确认")]
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
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = TmsTrackingEvent::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 承运商轨迹回调
     */
#[\erikwang2013\apidoc\annotation\Title("承运商轨迹回调")]
#[\erikwang2013\apidoc\annotation\Desc("承运商轨迹回传（公开接口，HMAC 签名验证），按运单号写入轨迹事件")]
#[\erikwang2013\apidoc\annotation\Url("/api/tms/tracking/callback")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("运输管理(TMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"tracking_no", type:"string", desc:"运单号，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"events", type:"array", desc:"轨迹事件数组，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function callbackWebhook(Request $request): Response
    {
        $validator = validator($request->all(), [
            'tracking_no' => 'string',
            'events' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $trackingNo = $request->input('tracking_no', '');
        $events = $request->input('events', []);
        if (!$trackingNo || empty($events)) {
            return $this->fail('参数不完整', 422);
        }
        try {
            (new \app\service\tms\TrackingService())->processWebhook($trackingNo, $events);

            return $this->success([], '轨迹已更新');
        } catch (\Throwable $e) {
            $this->logError('处理轨迹回传', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
