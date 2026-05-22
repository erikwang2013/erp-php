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
use app\service\inventory\InventoryService;
use app\service\finance\FinanceService;
use support\Request;
use support\Response;
use Illuminate\Database\Capsule\Manager as DB;

class DeliveryController extends BaseController
{
    /**
     * 发货单列表（分页）
     * GET /sales/delivery
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
            $query->where('order_id', $this->decodeId($orderId));
        }
        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', $this->decodeId($customerId));
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')->get()->map(function ($delivery) {
                return $this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建发货单并执行出库 + 生成应收 + 更新订单状态
     * POST /sales/delivery
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
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $orderId = $this->decodeId($request->input('order_id'));
        $order = SalesOrder::find($orderId);
        if (!$order) return $this->fail('销售订单不存在', 404);

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
     * GET /sales/delivery/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::with(['items', 'order', 'customer', 'warehouse'])->find($id);
        if (!$delivery) return $this->fail('发货单不存在', 404);
        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']));
    }

    /**
     * 更新发货单（仅修改备注等字段，不修改核心数据）
     * PUT /sales/delivery/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) return $this->fail('发货单不存在', 404);

        if ($request->has('remark')) {
            $delivery->remark = $request->input('remark');
        }
        $delivery->save();
        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除发货单（软删除，需密码确认）
     * DELETE /sales/delivery/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) return $this->fail('发货单不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $delivery->delete();
        return $this->success([], '删除成功');
    }
}
