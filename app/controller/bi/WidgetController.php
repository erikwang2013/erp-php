<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\bi;

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

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

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

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = BiWidget::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = BiWidget::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
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
