<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmFollowRecord;
use support\Request;
use support\Response;

/**
 * 跟进记录管理
 * @Apidoc\Tag("CRM")
 */
class FollowRecordController extends BaseController
{
    /**
     * 跟进记录列表（分页）
     * @Apidoc\Title("跟进记录列表")
     * @Apidoc\Desc("获取跟进记录分页列表，支持关键字搜索和状态筛选")
     * @Apidoc\Url("/admin/crm/follow")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(名称/编码)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="跟进记录列表"),
     *     @Apidoc\Returned("total", type="int", desc="总条数"),
     *     @Apidoc\Returned("page", type="int", desc="当前页码"),
     *     @Apidoc\Returned("limit", type="int", desc="每页条数"),
     * })
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = CrmFollowRecord::query();
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

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建跟进记录
     * @Apidoc\Title("创建跟进记录")
     * @Apidoc\Desc("创建一条新的跟进记录")
     * @Apidoc\Url("/admin/crm/follow")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="name", type="string", require=true, desc="跟进标题")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="跟进记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new CrmFollowRecord();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 跟进记录详情
     * @Apidoc\Title("跟进记录详情")
     * @Apidoc\Desc("获取指定跟进记录的详细信息")
     * @Apidoc\Url("/admin/crm/follow/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", require=true, desc="跟进记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="跟进详情")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmFollowRecord::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新跟进记录
     * @Apidoc\Title("更新跟进记录")
     * @Apidoc\Desc("更新指定跟进记录的信息")
     * @Apidoc\Url("/admin/crm/follow/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", require=true, desc="跟进记录ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的跟进记录")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmFollowRecord::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除跟进记录
     * @Apidoc\Title("删除跟进记录")
     * @Apidoc\Desc("软删除指定跟进记录，需要密码二次确认")
     * @Apidoc\Url("/admin/crm/follow/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", require=true, desc="跟进记录ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmFollowRecord::find($id);
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
