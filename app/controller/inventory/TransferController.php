<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\inventory;

use app\admin\controller\BaseController;
use app\model\Transfer;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("库存调拨")]
#[\erikwang2013\apidoc\annotation\Group("库存管理")]

class TransferController extends BaseController
{
    /**
     * 库存调拨列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("库存调拨列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取库存调拨列表，支持分页、关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/inventory/transfer")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("库存管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（名称/编码）")]
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

        $query = Transfer::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
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
     * 创建库存调拨
     */
#[\erikwang2013\apidoc\annotation\Title("创建库存调拨")]
#[\erikwang2013\apidoc\annotation\Desc("新增一个库存调拨记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/inventory/transfer")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("库存管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"调拨名称（必填）")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"调拨单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:1, desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"库存调拨记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new Transfer();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 库存调拨详情
     */
#[\erikwang2013\apidoc\annotation\Title("库存调拨详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取库存调拨详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("库存管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库存调拨hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"库存调拨详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Transfer::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新库存调拨
     */
#[\erikwang2013\apidoc\annotation\Title("更新库存调拨")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新库存调拨信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("库存管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库存调拨hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", default:"", desc:"调拨名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"调拨单号")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的库存调拨记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Transfer::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除库存调拨（软删除）
     */
#[\erikwang2013\apidoc\annotation\Title("删除库存调拨")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除库存调拨，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("库存管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"库存调拨hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = Transfer::find($id);
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
