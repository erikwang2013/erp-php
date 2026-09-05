<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\eam;

use app\admin\controller\BaseController;
use app\model\EamMaintenancePlan;
use support\Request;
use support\Response;

/**
 * 保养计划管理
 */#[\erikwang2013\apidoc\annotation\Tag("设备管理")]

class MaintenancePlanController extends BaseController
{
    /**
     * 保养计划列表（分页）
     */#[\erikwang2013\apidoc\annotation\Title("保养计划列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取保养计划列表，支持分页、计划名称关键词搜索及设备/状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/eam/maintenance")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("设备管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（计划名称）")]
#[\erikwang2013\apidoc\annotation\Param(name:"equipment_id", type:"string", default:"", desc:"设备hashid筛选")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"保养计划列表数据")]

    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = EamMaintenancePlan::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $equipmentId = $request->input('equipment_id');
        if ($equipmentId) {
            $query->where('equipment_id', $this->decodeId($equipmentId));
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建保养计划
     */#[\erikwang2013\apidoc\annotation\Title("创建保养计划")]
#[\erikwang2013\apidoc\annotation\Desc("新增一条设备保养计划，设备ID/计划名称/保养频率必填")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/eam/maintenance")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("设备管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"equipment_id", type:"int", default:"", desc:"设备ID（必填）")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"计划名称（必填）")]
#[\erikwang2013\apidoc\annotation\Param(name:"frequency", type:"string", default:"", desc:"保养频率，如 monthly（必填）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"创建的保养计划记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'equipment_id' => 'required|integer',
            'name' => 'required|string|max:200',
            'frequency' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new EamMaintenancePlan();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 保养计划详情
     */#[\erikwang2013\apidoc\annotation\Title("保养计划详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取保养计划详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("设备管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"保养计划hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"保养计划详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamMaintenancePlan::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新保养计划
     */#[\erikwang2013\apidoc\annotation\Title("更新保养计划")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新保养计划信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("设备管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"保养计划hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的保养计划记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamMaintenancePlan::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除保养计划（软删除）
     */#[\erikwang2013\apidoc\annotation\Title("删除保养计划")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除保养计划，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("设备管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"保养计划hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamMaintenancePlan::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }
}
