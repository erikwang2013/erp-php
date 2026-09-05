<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("打包管理")
 */
declare(strict_types=1);

namespace app\controller\wms;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\WmsPackTask;
use support\Request;
use support\Response;

class PackController extends BaseController
{
    /**
     * 打包任务列表（分页）
     * @Apidoc\Title("打包任务列表")
     * @Apidoc\Desc("获取打包任务列表，支持分页、编码搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/wms/pack")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（编码）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("打包任务列表")]
#[Apidoc\Desc("获取打包任务列表，支持分页、编码搜索和状态筛选")]
#[Apidoc\Url("/admin/v1/wms/pack")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
#[Apidoc\Param(name:"page", type:"int", default:1, desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（编码）")]
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

        $query = WmsPackTask::query();
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
     * 创建打包任务
     * @Apidoc\Title("创建打包任务")
     * @Apidoc\Desc("创建打包任务，编码必填（缺省自动生成）")
     * @Apidoc\Url("/admin/v1/wms/pack")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="code", type="string", desc="打包任务编码，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建打包任务")]
#[Apidoc\Desc("创建打包任务，编码必填（缺省自动生成）")]
#[Apidoc\Url("/admin/v1/wms/pack")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
#[Apidoc\Param(name:"code", type:"string", desc:"打包任务编码，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new WmsPackTask();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'wms/pack' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 打包任务详情
     * @Apidoc\Title("打包任务详情")
     * @Apidoc\Desc("按 ID 获取打包任务详情")
     * @Apidoc\Url("/admin/v1/wms/pack/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("打包任务详情")]
#[Apidoc\Desc("按 ID 获取打包任务详情")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
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
        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新打包任务
     * @Apidoc\Title("更新打包任务")
     * @Apidoc\Desc("按 ID 更新打包任务信息")
     * @Apidoc\Url("/admin/v1/wms/pack/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("更新打包任务")]
#[Apidoc\Desc("按 ID 更新打包任务信息")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
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
        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除打包任务
     * @Apidoc\Title("删除打包任务")
     * @Apidoc\Desc("按 ID 删除打包任务，需操作密码二次确认")
     * @Apidoc\Url("/admin/v1/wms/pack/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="id", type="string", desc="记录ID(hashid)")
     * @Apidoc\Param(name="password", type="string", desc="操作密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除打包任务")]
#[Apidoc\Desc("按 ID 删除打包任务，需操作密码二次确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
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

        $item = WmsPackTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 创建打包任务（按仓库）
     * @Apidoc\Title("开始打包")
     * @Apidoc\Desc("按仓库创建打包任务，仓库ID必填，其余条件参数按业务传入")
     * @Apidoc\Url("/admin/v1/wms/pack/{id}/start")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="warehouse_id", type="string", desc="仓库ID(hashid)，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据（创建的打包任务）")
     */#[Apidoc\Title("开始打包")]
#[Apidoc\Desc("按仓库创建打包任务，仓库ID必填，其余条件参数按业务传入")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
#[Apidoc\Param(name:"warehouse_id", type:"string", desc:"仓库ID(hashid)，必填")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据（创建的打包任务）")]

    public function start(Request $request): Response
    {
        $warehouseId = $this->decodeIdSafe($request->input('warehouse_id', ''));
        if (!$warehouseId) {
            return $this->fail('请提供仓库ID', 422);
        }
        try {
            $pack = (new \app\service\wms\WmsOutboundService())->startPack($warehouseId, $request->all());

            return $this->success($this->encodeIds($pack->toArray()), '打包任务已创建');
        } catch (\Throwable $e) {
            $this->logError('创建打包任务', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 完成打包
     * @Apidoc\Title("完成打包")
     * @Apidoc\Desc("提交打包结果，完成指定打包任务")
     * @Apidoc\Url("/admin/v1/wms/pack/{id}/complete")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仓储管理(WMS)")
     * @Apidoc\Param(name="id", type="string", desc="打包任务ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("完成打包")]
#[Apidoc\Desc("提交打包结果，完成指定打包任务")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("仓储管理(WMS)")]
#[Apidoc\Param(name:"id", type:"string", desc:"打包任务ID(hashid)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function complete(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        try {
            $pack = (new \app\service\wms\WmsOutboundService())->completePack($id, $request->all());

            return $this->success($this->encodeIds($pack->toArray()), '打包完成');
        } catch (\Throwable $e) {
            $this->logError('完成打包', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
