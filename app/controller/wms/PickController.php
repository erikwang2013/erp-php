<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsPickTask;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("拣货任务")]
#[\erikwang2013\apidoc\annotation\Group("仓储管理WMS")]

class PickController extends BaseController
{
    /**
     * 拣货任务列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("拣货任务列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取拣货任务列表，支持分页、编码搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/wms/pick")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（编码）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = WmsPickTask::query();
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建拣货任务
     */
#[\erikwang2013\apidoc\annotation\Title("创建拣货任务")]
#[\erikwang2013\apidoc\annotation\Desc("创建拣货任务，编码必填（缺省自动生成）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/wms/pick")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"拣货任务编码，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new WmsPickTask();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'wms/pick' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 拣货任务详情
     */
#[\erikwang2013\apidoc\annotation\Title("拣货任务详情")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 获取拣货任务详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
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
        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新拣货任务
     */
#[\erikwang2013\apidoc\annotation\Title("更新拣货任务")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 更新拣货任务信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
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
        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除拣货任务
     */
#[\erikwang2013\apidoc\annotation\Title("删除拣货任务")]
#[\erikwang2013\apidoc\annotation\Desc("按 ID 删除拣货任务，需操作密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
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

        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 开始拣货
     */
#[\erikwang2013\apidoc\annotation\Title("开始拣货")]
#[\erikwang2013\apidoc\annotation\Desc("开始执行指定拣货任务")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"拣货任务ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function start(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        try {
            (new \app\service\wms\WmsOutboundService())->startPick($id, $request->adminId ?? 0);

            return $this->success([], '拣货任务已开始');
        } catch (\Throwable $e) {
            $this->logError('开始拣货', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 确认拣货
     */
#[\erikwang2013\apidoc\annotation\Title("确认拣货")]
#[\erikwang2013\apidoc\annotation\Desc("提交实际拣货明细，确认拣货完成")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仓储管理(WMS)")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"拣货任务ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"items", type:"array", desc:"拣货确认明细（实际数量等），必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function confirm(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $actuals = $request->input('items', []);
        if (empty($actuals)) {
            return $this->fail('请提供拣货确认明细', 422);
        }
        try {
            (new \app\service\wms\WmsOutboundService())->confirmPick($id, $actuals);

            return $this->success([], '拣货确认完成');
        } catch (\Throwable $e) {
            $this->logError('确认拣货', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
