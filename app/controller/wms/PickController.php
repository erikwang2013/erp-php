<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("拣货管理")
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsPickTask;
use app\service\wms\WmsOutboundService;
use support\Request;
use support\Response;

class PickController extends BaseController
{
    /**
     * 列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = WmsPickTask::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $data = $request->all();
        unset($data['id']);
        if (empty($data['code'])) {
            $data['code'] = 'wms/pick' . date('YmdHis') . rand(100, 999);
        }

        $item = new WmsPickTask();
        $item->id = $this->generateId();
        foreach ($data as $k => $v) {
            if ($v !== null) $item->$k = $v;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $data = $request->all();
        unset($data['id']);
        foreach ($data as $k => $v) {
            if ($v !== null) $item->$k = $v;
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = WmsPickTask::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /** 开始拣货 */
    public function start(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) return $this->fail($this->trans('invalid_id'), 400);
        try {
            (new \app\service\wms\WmsOutboundService())->startPick($id, $request->adminId ?? 0);
            return $this->success([], '拣货任务已开始');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    /** 确认拣货 */
    public function confirm(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) return $this->fail($this->trans('invalid_id'), 400);
        $actuals = $request->input('items', []);
        if (empty($actuals)) return $this->fail('请提供拣货确认明细', 422);
        try {
            (new \app\service\wms\WmsOutboundService())->confirmPick($id, $actuals);
            return $this->success([], '拣货确认完成');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }
}
