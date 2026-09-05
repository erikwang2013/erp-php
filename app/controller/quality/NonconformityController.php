<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\quality;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\QualityNonconformity;
use support\Request;
use support\Response;

/**
 * 不合格品管理
 * @Apidoc\Tag("质量管理")
 */
class NonconformityController extends BaseController
{
    /**
     * 不合格品单列表（分页）
     * @Apidoc\Title("不合格品单列表")
     * @Apidoc\Desc("获取不合格品单列表，支持分页、单号/缺陷类型关键词搜索和状态筛选")
     * @Apidoc\Url("/admin/v1/quality/nonconformity")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（单号/缺陷类型）")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="不合格品单列表数据")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = QualityNonconformity::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%")->orWhere('defect_type', 'like', "%{$keyword}%");
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
     * 创建不合格品单
     * @Apidoc\Title("创建不合格品单")
     * @Apidoc\Desc("新增不合格品单，单号/缺陷类型/缺陷数量必填")
     * @Apidoc\Url("/admin/v1/quality/nonconformity")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="code", type="string", default="", desc="不合格品单号（必填）")
     * @Apidoc\Param(name="defect_type", type="string", default="", desc="缺陷类型（必填）")
     * @Apidoc\Param(name="defect_qty", type="int", default="", desc="缺陷数量（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="创建的不合格品单记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'defect_type' => 'required|string|max:100',
            'defect_qty' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new QualityNonconformity();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 不合格品单详情
     * @Apidoc\Title("不合格品单详情")
     * @Apidoc\Desc("根据ID获取不合格品单详细信息")
     * @Apidoc\Url("/admin/v1/quality/nonconformity/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="不合格品单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="不合格品单详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityNonconformity::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新不合格品单
     * @Apidoc\Title("更新不合格品单")
     * @Apidoc\Desc("根据ID更新不合格品单信息")
     * @Apidoc\Url("/admin/v1/quality/nonconformity/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="不合格品单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的不合格品单记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityNonconformity::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除不合格品单（软删除）
     * @Apidoc\Title("删除不合格品单")
     * @Apidoc\Desc("根据ID软删除不合格品单，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/quality/nonconformity/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="不合格品单hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = QualityNonconformity::find($id);
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
