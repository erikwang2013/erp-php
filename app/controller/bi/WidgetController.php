<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\bi;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\BiWidget;
use support\Request;
use support\Response;

/**
 * BI 看板组件管理
 * @Apidoc\Tag("BI看板")
 */
class WidgetController extends BaseController
{
    /**
     * 图表组件列表
     * @Apidoc\Title("图表组件列表")
     * @Apidoc\Desc("分页查询图表组件，支持按看板与名称关键字筛选")
     * @Apidoc\Url("/admin/v1/bi/widget")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="page", type="int", default="1", desc="页码")
     * @Apidoc\Param(name="limit", type="int", default="15", desc="每页数量")
     * @Apidoc\Param(name="dashboard_id", type="string", desc="所属看板ID(hashid)")
     * @Apidoc\Param(name="keyword", type="string", desc="组件名称关键字")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="分页列表(list/total/page/limit)")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = BiWidget::query();
        $dashboardId = $request->input('dashboard_id');
        if ($dashboardId) {
            $query->where('dashboard_id', $this->decodeId($dashboardId));
        }
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建图表组件
     * @Apidoc\Title("创建图表组件")
     * @Apidoc\Desc("在指定看板下新建图表组件")
     * @Apidoc\Url("/admin/v1/bi/widget")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="dashboard_id", type="string", require=true, desc="所属看板ID(hashid)")
     * @Apidoc\Param(name="name", type="string", require=true, desc="组件名称(≤200字符)")
     * @Apidoc\Param(name="type", type="string", require=true, desc="组件类型(≤50字符)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="组件详情(hashid)")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'dashboard_id' => 'required|integer',
            'name' => 'required|string|max:200',
            'type' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new BiWidget();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 图表组件详情
     * @Apidoc\Title("图表组件详情")
     * @Apidoc\Desc("查看单个图表组件配置")
     * @Apidoc\Url("/admin/v1/bi/widget/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="组件ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="组件详情(hashid)")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiWidget::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新图表组件
     * @Apidoc\Title("更新图表组件")
     * @Apidoc\Desc("更新组件名称、类型或配置")
     * @Apidoc\Url("/admin/v1/bi/widget/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="组件ID(hashid)")
     * @Apidoc\Param(name="name", type="string", desc="组件名称")
     * @Apidoc\Param(name="type", type="string", desc="组件类型")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后组件详情(hashid)")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiWidget::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除图表组件
     * @Apidoc\Title("删除图表组件")
     * @Apidoc\Desc("删除图表组件，需二次密码确认")
     * @Apidoc\Url("/admin/v1/bi/widget/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("BI看板")
     * @Apidoc\Param(name="id", type="string", require=true, desc="组件ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="操作密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = BiWidget::find($id);
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
