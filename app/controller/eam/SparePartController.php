<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\eam;

use app\admin\controller\BaseController;
use app\model\EamSparePart;
use support\Request;
use support\Response;

/**
 * 备品备件管理
 * @Apidoc\Tag("设备管理")
 */
class SparePartController extends BaseController
{
    /**
     * 备品备件列表（分页）
     * @Apidoc\Title("备品备件列表")
     * @Apidoc\Desc("获取备品备件列表，支持分页、名称/编码/存放位置关键词搜索及状态/设备筛选")
     * @Apidoc\Url("/admin/v1/eam/spare-part")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（名称/编码/存放位置）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Param(name="equipment_id", type="int", default="", desc="设备ID筛选（整数）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="备品备件列表数据")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = EamSparePart::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%")
                  ->orWhere('location', 'like', "%{$keyword}%");
            });
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int)$status);
        }
        $equipmentId = $request->input('equipment_id');
        if ($equipmentId) {
            $query->where('equipment_id', (int)$equipmentId);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建备品备件
     * @Apidoc\Title("创建备品备件")
     * @Apidoc\Desc("新增备品备件档案，编码/名称必填")
     * @Apidoc\Url("/admin/v1/eam/spare-part")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="code", type="string", default="", desc="备件编码（必填）")
     * @Apidoc\Param(name="name", type="string", default="", desc="备件名称（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="创建的备品备件记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:100',
            'name' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new EamSparePart();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 备品备件详情
     * @Apidoc\Title("备品备件详情")
     * @Apidoc\Desc("根据ID获取备品备件详细信息")
     * @Apidoc\Url("/admin/v1/eam/spare-part/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="备品备件hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="备品备件详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamSparePart::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新备品备件
     * @Apidoc\Title("更新备品备件")
     * @Apidoc\Desc("根据ID更新备品备件信息")
     * @Apidoc\Url("/admin/v1/eam/spare-part/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="备品备件hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的备品备件记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamSparePart::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除备品备件（软删除）
     * @Apidoc\Title("删除备品备件")
     * @Apidoc\Desc("根据ID软删除备品备件档案，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/eam/spare-part/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="备品备件hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamSparePart::find($id);
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
