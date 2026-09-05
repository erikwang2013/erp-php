<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\eam;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\EamEquipment;
use support\Request;
use support\Response;

/**
 * 设备台账管理
 * @Apidoc\Tag("设备管理")
 */
class EquipmentController extends BaseController
{
    /**
     * 设备列表（分页）
     * @Apidoc\Title("设备列表")
     * @Apidoc\Desc("获取设备列表，支持分页、名称/编码/序列号关键词搜索及状态/分类筛选")
     * @Apidoc\Url("/admin/v1/eam/equipment")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（名称/编码/序列号）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Param(name="category", type="string", default="", desc="设备分类筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="设备列表数据")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = EamEquipment::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%")
                  ->orWhere('serial_number', 'like', "%{$keyword}%");
            });
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        $category = $request->input('category');
        if ($category) {
            $query->where('category', $category);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建设备
     * @Apidoc\Title("创建设备")
     * @Apidoc\Desc("新增设备档案，设备编码/名称必填")
     * @Apidoc\Url("/admin/v1/eam/equipment")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="code", type="string", default="", desc="设备编码（必填）")
     * @Apidoc\Param(name="name", type="string", default="", desc="设备名称（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="创建设备记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new EamEquipment();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 设备详情
     * @Apidoc\Title("设备详情")
     * @Apidoc\Desc("根据ID获取设备详细信息")
     * @Apidoc\Url("/admin/v1/eam/equipment/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="设备hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="设备详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamEquipment::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新设备
     * @Apidoc\Title("更新设备")
     * @Apidoc\Desc("根据ID更新设备档案信息")
     * @Apidoc\Url("/admin/v1/eam/equipment/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="设备hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的设备记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamEquipment::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除设备（软删除）
     * @Apidoc\Title("删除设备")
     * @Apidoc\Desc("根据ID软删除设备档案，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/eam/equipment/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="设备hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamEquipment::find($id);
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
