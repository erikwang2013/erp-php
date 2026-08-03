<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\SalesDelivery;
use app\model\SalesDeliveryItem;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\service\finance\FinanceService;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;
use support\Request;
use support\Response;

/**
 * 销售发货管理
 * @Apidoc\Tag("销售管理")
 */
class DeliveryController extends BaseController
{
    /**
     * 发货单列表（分页）
     * @Apidoc\Title("发货单列表")
     * @Apidoc\Desc("获取销售发货单分页列表，支持关键字/状态/订单/客户筛选")
     * @Apidoc\Url("/admin/sales/delivery")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(发货单号)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选:0待发货1已发货")
     * @Apidoc\Param(name="order_id", type="string", default="", desc="销售订单ID(hashid)")
     * @Apidoc\Param(name="customer_id", type="string", default="", desc="客户ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="发货单列表"),
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
        $orderId = $request->input('order_id');
        $customerId = $request->input('customer_id');

        $query = SalesDelivery::with(['items', 'order', 'customer', 'warehouse']);
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($orderId !== null && $orderId !== '') {
            $decoded = $this->decodeIdSafe($orderId);
            if ($decoded !== null && $decoded > 0) {
                $query->where('order_id', $decoded);
            }
        }
        if ($customerId !== null && $customerId !== '') {
            $decoded = $this->decodeIdSafe($customerId);
            if ($decoded !== null && $decoded > 0) {
                $query->where('customer_id', $decoded);
            }
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')->get()->map(function ($delivery) {
                return $this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建发货单并执行出库
     * @Apidoc\Title("创建发货单")
     * @Apidoc\Desc("创建发货单并自动执行出库操作，同时生成应收记录并更新销售订单状态")
     * @Apidoc\Url("/admin/sales/delivery")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="code", type="string", require=true, desc="发货单号")
     * @Apidoc\Param(name="order_id", type="string", require=true, desc="销售订单ID(hashid)")
     * @Apidoc\Param(name="customer_id", type="string", require=true, desc="客户ID(hashid)")
     * @Apidoc\Param(name="warehouse_id", type="string", require=true, desc="仓库ID(hashid)")
     * @Apidoc\Param(name="remark", type="string", default="", desc="备注")
     * @Apidoc\Param(name="items", type="array", require=true, desc="发货明细(含product_id/quantity/price等)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="发货单信息")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'required|string',
            'customer_id' => 'required|string',
            'warehouse_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeId($request->input('order_id'));
        $order = SalesOrder::find($orderId);
        if (!$order) {
            return $this->fail('销售订单不存在', 404);
        }

        DB::beginTransaction();
        try {
            // 1. 创建发货单头
            $delivery = new SalesDelivery();
            $delivery->id = $this->generateId();
            $delivery->code = $request->input('code');
            $delivery->order_id = $orderId;
            $delivery->customer_id = $this->decodeId($request->input('customer_id'));
            $delivery->warehouse_id = $this->decodeId($request->input('warehouse_id'));
            $delivery->status = 0; // 待发货
            $delivery->remark = $request->input('remark', '');
            $delivery->delivered_at = date('Y-m-d H:i:s');
            $delivery->save();

            $inventoryService = new InventoryService();
            $financeService = new FinanceService();
            $totalDeliveryAmount = 0;

            // 2. 创建发货明细 + 执行出库
            foreach ($request->input('items') as $itemData) {
                $productId = ($itemData['product_id'] ?? '') ? $this->decodeId($itemData['product_id']) : 0;
                $skuId = ($itemData['sku_id'] ?? '') ? $this->decodeId($itemData['sku_id']) : 0;
                $locationId = ($itemData['location_id'] ?? '') ? $this->decodeId($itemData['location_id']) : 0;
                $orderItemId = ($itemData['order_item_id'] ?? '') ? $this->decodeId($itemData['order_item_id']) : 0;
                $quantity = (float) $itemData['quantity'];
                $price = (float) $itemData['price'];
                $amount = round($quantity * $price, 2);
                $batchCode = $itemData['batch_code'] ?? '';
                $unit = $itemData['unit'] ?? '';
                $totalDeliveryAmount += $amount;

                // 创建发货明细
                $deliveryItem = new SalesDeliveryItem();
                $deliveryItem->id = $this->generateId();
                $deliveryItem->delivery_id = $delivery->id;
                $deliveryItem->order_item_id = $orderItemId;
                $deliveryItem->product_id = $productId;
                $deliveryItem->sku_id = $skuId;
                $deliveryItem->location_id = $locationId;
                $deliveryItem->batch_code = $batchCode;
                $deliveryItem->quantity = $quantity;
                $deliveryItem->price = $price;
                $deliveryItem->amount = $amount;
                $deliveryItem->unit = $unit;
                $deliveryItem->save();

                // 调用库存服务 — 出库
                $inventoryService->stockOut(
                    $productId,
                    $skuId,
                    $delivery->warehouse_id,
                    $locationId,
                    $batchCode,
                    $quantity,
                    'sales_delivery',
                    $delivery->id
                );
            }

            // 3. 更新发货单状态为已发货
            $delivery->status = 1;
            $delivery->save();

            // 4. 生成应收记录（跨模块：销售 → 财务）
            $financeService->createAr(
                $delivery->customer_id,
                'sales_delivery',
                $delivery->id,
                $totalDeliveryAmount
            );

            // 5. 更新销售订单状态
            $this->updateOrderStatus($order, $delivery->id);

            DB::commit();

            return $this->success(
                $this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']),
                '发货成功，已出库并生成应收记录'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->fail('发货失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新销售订单发货状态
     * 判断该订单所有已发货发货单的总数量，决定订单状态为部分发货或已发货
     */
    private function updateOrderStatus(SalesOrder $order, int $deliveryId): void
    {
        // 统计该订单所有已发货的发货单
        $deliveredCount = SalesDelivery::where('order_id', $order->id)
            ->where('status', 1)
            ->count();

        $orderItems = SalesOrderItem::where('order_id', $order->id)->get();

        if ($orderItems->isEmpty()) {
            $order->status = 3; // 已发货
            $order->save();

            return;
        }

        // 统计所有已发货单的发货明细中本订单明细的累计发货数量
        $allDeliveredIds = SalesDelivery::where('order_id', $order->id)
            ->where('status', 1)
            ->pluck('id');

        $totalOrderedQty = $orderItems->sum('quantity');
        $totalDeliveredQty = SalesDeliveryItem::whereIn('delivery_id', $allDeliveredIds)->sum('quantity');

        if ($totalDeliveredQty >= $totalOrderedQty) {
            $order->status = 3; // 已发货
        } elseif ($deliveredCount > 0) {
            $order->status = 2; // 部分发货
        }
        $order->save();
    }

    /**
     * 发货单详情
     * @Apidoc\Title("发货单详情")
     * @Apidoc\Desc("获取指定发货单的详细信息，包含明细、订单、客户和仓库")
     * @Apidoc\Url("/admin/sales/delivery/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="发货单ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="发货单详情(含关联数据)")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::with(['items', 'order', 'customer', 'warehouse'])->find($id);
        if (!$delivery) {
            return $this->fail('发货单不存在', 404);
        }

        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']));
    }

    /**
     * 更新发货单
     * @Apidoc\Title("更新发货单")
     * @Apidoc\Desc("更新发货单备注等信息，不修改核心数据")
     * @Apidoc\Url("/admin/sales/delivery/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="发货单ID(hashid)")
     * @Apidoc\Param(name="remark", type="string", default="", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的发货单信息")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) {
            return $this->fail('发货单不存在', 404);
        }

        if ($request->has('remark')) {
            $delivery->remark = $request->input('remark');
        }
        $delivery->save();

        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除发货单
     * @Apidoc\Title("删除发货单")
     * @Apidoc\Desc("软删除指定发货单，需要密码二次确认")
     * @Apidoc\Url("/admin/sales/delivery/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("销售管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="发货单ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) {
            return $this->fail('发货单不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $delivery->delete();

        return $this->success([], '删除成功');
    }
}
