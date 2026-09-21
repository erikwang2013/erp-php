<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\tms;

use app\admin\controller\BaseController;
use app\model\TmsCarrierService;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('承运商服务')]
#[\erikwang2013\apidoc\annotation\Group('运输管理TMS')]

class ServiceController extends BaseController
{
    /**
     * 承运商服务列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('承运商服务列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取承运商服务列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/service')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（名称）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选（0=禁用,1=启用）')]
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
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = TmsCarrierService::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'carrier_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建承运商服务
     */
    #[\erikwang2013\apidoc\annotation\Title('创建承运商服务')]
    #[\erikwang2013\apidoc\annotation\Desc('创建承运商服务，名称必填，其余字段按业务传入')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/tms/service')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('运输管理(TMS)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'carrier_id', type:'string', desc:'承运商ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'服务名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'服务编码，留空由后端自生成')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // name 是真列，规则保留（列宽 VARCHAR(100)，原 max:200 偏松，收紧避免 1406）
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // carrier_id 是 NOT NULL 无默认列，原规则完全没盯它 → 每次新增必 500；
        // 请求里传的是 hashid，直填会写坏 bigint，故双模解码，非法一律 422
        $carrierId = $this->decodeFlexibleId($request->input('carrier_id'));
        if ($carrierId === null || $carrierId < 1) {
            return $this->fail($this->trans('Invalid carrier'), 422);
        }

        $item = new TmsCarrierService();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // code 列 NOT NULL 无默认：Angular 端该字段是可选的，留空即 1364，走 doc_code 兜底
        $item->fill([
            'carrier_id' => $carrierId,
            'code' => doc_code($request->input('code'), 'CS'),
        ]);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 承运商服务详情
     */
    #[\erikwang2013\apidoc\annotation\Title('承运商服务详情')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 获取承运商服务详情')]
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
        $item = TmsCarrierService::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新承运商服务
     */
    #[\erikwang2013\apidoc\annotation\Title('更新承运商服务')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 更新承运商服务信息')]
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
        $item = TmsCarrierService::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除承运商服务
     */
    #[\erikwang2013\apidoc\annotation\Title('删除承运商服务')]
    #[\erikwang2013\apidoc\annotation\Desc('按 ID 删除承运商服务，需操作密码二次确认')]
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

        $item = TmsCarrierService::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
