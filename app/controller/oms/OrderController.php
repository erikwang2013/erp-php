<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("OMS订单")
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsOrder;
use app\service\oms\OmsOrderService;
use support\Request;
use support\Response;

class OrderController extends BaseController
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

        $query = OmsOrder::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('channel_order_no', 'like', "%{$keyword}%");
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
     * 创建
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsOrder();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 详情
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsOrder::find($id);
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
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = OmsOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 库存分配
     */
    public function allocate(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $items = $request->input('items', []);
        if (empty($items)) {
            return $this->fail('请提供分配明细', 422);
        }

        try {
            $service = new OmsOrderService();
            $service->allocateOrder($id, $items);

            return $this->success([], '库存分配成功');
        } catch (\Throwable $e) {
            $this->logError('库存分配', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 创建履约
     */
    public function fulfill(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $warehouseId = $this->decodeIdSafe($request->input('warehouse_id', ''));
        if (!$warehouseId) {
            return $this->fail('请提供发货仓库', 422);
        }

        try {
            $service = new OmsOrderService();
            $fulfillment = $service->createFulfillment($id, $warehouseId);

            return $this->success($this->encodeIds($fulfillment->toArray()), '履约创建成功');
        } catch (\Throwable $e) {
            $this->logError('创建履约', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * 取消订单
     */
    public function cancel(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        try {
            $service = new OmsOrderService();
            $service->cancelOrder($id);

            return $this->success([], '订单已取消');
        } catch (\Throwable $e) {
            $this->logError('取消订单', $e);

            return $this->fail($e->getMessage(), 500);
        }
    }
}
