<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("收货管理")
 */
declare(strict_types=1);

namespace app\controller\wms;

use app\admin\controller\BaseController;
use app\model\WmsReceiving;
use app\service\wms\WmsInboundService;
use support\Request;
use support\Response;

class ReceivingController extends BaseController
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

        $query = WmsReceiving::query();
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

        $item = new WmsReceiving();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'wms/receiving' . $this->generateId();
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
        $item = WmsReceiving::find($id);
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
        $item = WmsReceiving::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $this->fillModelFromRequest($item, $request);

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

        $item = WmsReceiving::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /** 完成收货并生成上架任务 */
    public function complete(Request $request, string $hashid): Response
    {
        $id = $this->decodeIdSafe($hashid);
        if (!$id) return $this->fail($this->trans('invalid_id'), 400);

        $actuals = $request->input('items', []);
        if (empty($actuals)) return $this->fail('请提供收货明细', 422);

        try {
            $service = new WmsInboundService();
            $putaway = $service->completeReceiving($id, $actuals);
            return $this->success($this->encodeIds($putaway->toArray()), '收货完成，已生成上架任务');
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }
}
