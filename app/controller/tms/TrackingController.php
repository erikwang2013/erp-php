<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("物流轨迹")
 */
declare(strict_types=1);

namespace app\controller\tms;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\TmsTrackingEvent;
use support\Request;
use support\Response;

class TrackingController extends BaseController
{
    /**
     * 物流轨迹列表（分页）
     * @Apidoc\Title("物流轨迹列表")
     * @Apidoc\Desc("获取物流轨迹列表，支持分页和状态筛选")
     * @Apidoc\Url("/admin/v1/tms/tracking")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("物流轨迹列表")]
#[Apidoc\Desc("获取物流轨迹列表，支持分页和状态筛选")]
#[Apidoc\Url("/admin/v1/tms/tracking")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"page", type:"int", default:1, desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[Apidoc\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = TmsTrackingEvent::query();

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
     * 创建物流轨迹
     * @Apidoc\Title("创建物流轨迹")
     * @Apidoc\Desc("创建物流轨迹记录，编码必填，其余字段按业务传入")
     * @Apidoc\Url("/admin/v1/tms/tracking")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="code", type="string", desc="轨迹编码，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建物流轨迹")]
#[Apidoc\Desc("创建物流轨迹记录，编码必填，其余字段按业务传入")]
#[Apidoc\Url("/admin/v1/tms/tracking")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"code", type:"string", desc:"轨迹编码，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new TmsTrackingEvent();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 物流轨迹详情
     * @Apidoc\Title("物流轨迹详情")
     * @Apidoc\Desc("按 ID 获取物流轨迹详情")
     * @Apidoc\Url("/admin/v1/tms/tracking/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("物流轨迹详情")]
#[Apidoc\Desc("按 ID 获取物流轨迹详情")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
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
     * @Apidoc\Title("更新物流轨迹")
     * @Apidoc\Desc("按 ID 更新物流轨迹信息")
     * @Apidoc\Url("/admin/v1/tms/tracking/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("更新物流轨迹")]
#[Apidoc\Desc("按 ID 更新物流轨迹信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = TmsTrackingEvent::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除物流轨迹
     * @Apidoc\Title("删除物流轨迹")
     * @Apidoc\Desc("按 ID 删除物流轨迹，需操作密码二次确认")
     * @Apidoc\Url("/admin/v1/tms/tracking/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Param(name="password", type="string", desc="操作密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除物流轨迹")]
#[Apidoc\Desc("按 ID 删除物流轨迹，需操作密码二次确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"id", type:"string", desc:"记录ID(hashid)")]
#[Apidoc\Param(name:"password", type:"string", desc:"操作密码（二次确认）")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

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

        $item = TmsTrackingEvent::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 承运商轨迹回调
     * @Apidoc\Title("承运商轨迹回调")
     * @Apidoc\Desc("承运商轨迹回传（公开接口，HMAC 签名验证），按运单号写入轨迹事件")
     * @Apidoc\Url("/api/tms/tracking/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("运输管理(TMS)")
     * @Apidoc\Param(name="tracking_no", type="string", desc="运单号，必填")
     * @Apidoc\Param(name="events", type="array", desc="轨迹事件数组，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("承运商轨迹回调")]
#[Apidoc\Desc("承运商轨迹回传（公开接口，HMAC 签名验证），按运单号写入轨迹事件")]
#[Apidoc\Url("/api/tms/tracking/callback")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("运输管理(TMS)")]
#[Apidoc\Param(name:"tracking_no", type:"string", desc:"运单号，必填")]
#[Apidoc\Param(name:"events", type:"array", desc:"轨迹事件数组，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function callbackWebhook(Request $request): Response
    {
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
