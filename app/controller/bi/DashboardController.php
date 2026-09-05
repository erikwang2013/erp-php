<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\bi;

use app\admin\controller\BaseController;
use app\model\BiDashboard;
use app\model\BiWidget;
use support\Request;
use support\Response;

/**
 * BI 数据看板管理
 * @Apidoc\Tag("BI看板")
 */
class DashboardController extends BaseController
{
    /**
     * BI 看板列表
     * @Apidoc\Title("BI 看板列表")
     * @Apidoc\Desc("分页查询数据看板，支持名称关键字与状态筛选")
     * @Apidoc\Url("/admin/v1/bi/dashboard")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="page", type="int", default="1", desc="页码")
     * @Apidoc\Param(name="limit", type="int", default="15", desc="每页数量")
     * @Apidoc\Param(name="keyword", type="string", desc="看板名称关键字")
     * @Apidoc\Param(name="status", type="int", desc="状态,0=停用,1=启用")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="分页列表(list/total/page/limit)")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = BiDashboard::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
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
     * 创建 BI 看板
     * @Apidoc\Title("创建 BI 看板")
     * @Apidoc\Desc("新建数据看板，可携带布局配置字段")
     * @Apidoc\Url("/admin/v1/bi/dashboard")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="name", type="string", require=true, desc="看板名称(≤200字符)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="看板详情(hashid)")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new BiDashboard();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * BI 看板详情
     * @Apidoc\Title("BI 看板详情")
     * @Apidoc\Desc("查看看板详情及其下图表组件列表")
     * @Apidoc\Url("/admin/v1/bi/dashboard/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="看板ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="看板详情,含widgets组件数组")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiDashboard::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $widgets = BiWidget::where('dashboard_id', $id)->orderBy('position_y', 'asc')->orderBy('position_x', 'asc')->get()->map(fn ($w) => $this->encodeIds($w->toArray()));
        $data = $this->encodeIds($item->toArray());
        $data['widgets'] = $widgets;

        return $this->success($data);
    }

    /**
     * 更新 BI 看板
     * @Apidoc\Title("更新 BI 看板")
     * @Apidoc\Desc("更新看板名称或布局配置")
     * @Apidoc\Url("/admin/v1/bi/dashboard/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="看板ID(hashid)")
     * @Apidoc\Param(name="name", type="string", desc="看板名称")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后看板详情(hashid)")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiDashboard::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除 BI 看板
     * @Apidoc\Title("删除 BI 看板")
     * @Apidoc\Desc("删除看板并级联删除其下全部图表组件，需二次密码确认")
     * @Apidoc\Url("/admin/v1/bi/dashboard/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="看板ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="操作密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiDashboard::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        BiWidget::where('dashboard_id', $id)->delete();
        $item->delete();

        return $this->success([], '删除成功');
    }
}
