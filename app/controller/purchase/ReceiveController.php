<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\model\PurchaseReceive;
use app\model\PurchaseReceiveItem;
use app\service\finance\FinanceService;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;
use support\Container;
use support\Request;
use support\Response;

/**
 * 采购收货管理
 * @Apidoc\Tag("采购管理")
 */
class ReceiveController extends BaseController
{
    /**
     * 收货单列表（分页）
     * @Apidoc\Title("收货单列表")
     * @Apidoc\Desc("获取采购收货单分页列表，支持关键字/状态/订单/供应商筛选")
     * @Apidoc\Url("/admin/purchase/receive")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词(收货单号)")
     * @Apidoc\Param(name="status", type="int", default="", desc="状态筛选:0待入库1已入库")
     * @Apidoc\Param(name="order_id", type="string", default="", desc="采购订单ID(hashid)")
     * @Apidoc\Param(name="supplier_id", type="string", default="", desc="供应商ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("list", type="array", desc="收货单列表"),
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
        $supplierId = $request->input('supplier_id');

        $query = PurchaseReceive::with(['items', 'order', 'supplier', 'warehouse']);
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
        if ($supplierId !== null && $supplierId !== '') {
            $decoded = $this->decodeIdSafe($supplierId);
            if ($decoded !== null && $decoded > 0) {
                $query->where('supplier_id', $decoded);
            }
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')->get()->map(function ($receive) {
                return $this->encodeIds($receive->toArray(), ['id', 'order_id', 'supplier_id', 'warehouse_id']);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建收货单并执行入库
     * @Apidoc\Title("创建收货单")
     * @Apidoc\Desc("创建收货单并自动执行入库操作，同时生成应付记录并更新采购订单状态")
     * @Apidoc\Url("/admin/purchase/receive")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="code", type="string", require=true, desc="收货单号")
     * @Apidoc\Param(name="order_id", type="string", require=true, desc="采购订单ID(hashid)")
     * @Apidoc\Param(name="supplier_id", type="string", require=true, desc="供应商ID(hashid)")
     * @Apidoc\Param(name="warehouse_id", type="string", require=true, desc="仓库ID(hashid)")
     * @Apidoc\Param(name="remark", type="string", default="", desc="备注")
     * @Apidoc\Param(name="items", type="array", require=true, desc="收货明细(含product_id/quantity/price等)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="收货单信息")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'required|string',
            'supplier_id' => 'required|string',
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
        $order = PurchaseOrder::find($orderId);
        if (!$order) {
            return $this->fail('采购订单不存在', 404);
        }

        DB::beginTransaction();
        try {
            // 1. 创建收货单头
            $receive = new PurchaseReceive();
            $receive->id = $this->generateId();
            $receive->code = $request->input('code');
            $receive->order_id = $orderId;
            $receive->supplier_id = $this->decodeId($request->input('supplier_id'));
            $receive->warehouse_id = $this->decodeId($request->input('warehouse_id'));
            $receive->status = 0; // 待入库
            $receive->remark = $request->input('remark', '');
            $receive->received_at = date('Y-m-d H:i:s');
            $receive->save();

            // 从容器获取服务实例（便于测试时替换/注入 mock）
            $inventoryService = Container::get(InventoryService::class);
            $financeService = Container::get(FinanceService::class);
            $totalReceiveAmount = 0;

            // 2. 创建收货明细 + 执行入库
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
                $totalReceiveAmount += $amount;

                // 创建收货明细
                $receiveItem = new PurchaseReceiveItem();
                $receiveItem->id = $this->generateId();
                $receiveItem->receive_id = $receive->id;
                $receiveItem->order_item_id = $orderItemId;
                $receiveItem->product_id = $productId;
                $receiveItem->sku_id = $skuId;
                $receiveItem->location_id = $locationId;
                $receiveItem->batch_code = $batchCode;
                $receiveItem->quantity = $quantity;
                $receiveItem->price = $price;
                $receiveItem->amount = $amount;
                $receiveItem->unit = $unit;
                $receiveItem->save();

                // 调用库存服务 — 入库
                $inventoryService->stockIn(
                    $productId,
                    $skuId,
                    $receive->warehouse_id,
                    $locationId,
                    $batchCode,
                    $quantity,
                    $price,
                    'purchase_receive',
                    $receive->id
                );
            }

            // 3. 更新收货单状态为已入库
            $receive->status = 1;
            $receive->save();

            // 4. 生成应付记录（跨模块：采购 → 财务）
            $financeService->createAp(
                $receive->supplier_id,
                'purchase_receive',
                $receive->id,
                $totalReceiveAmount
            );

            // 5. 更新采购订单状态
            $this->updateOrderStatus($order, $receive->id);

            DB::commit();

            return $this->success(
                $this->encodeIds($receive->toArray(), ['id', 'order_id', 'supplier_id', 'warehouse_id']),
                '收货成功，已入库并生成应付记录'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logError('执行收货', $e);

            return $this->fail('收货失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新采购订单收货状态
     * 判断该订单所有已入库收货单的总数量，决定订单状态为部分收货或已收货
     */
    private function updateOrderStatus(PurchaseOrder $order, int $receiveId): void
    {
        // 统计该订单所有已入库的收货单
        $receivedCount = PurchaseReceive::where('order_id', $order->id)
            ->where('status', 1)
            ->count();

        $orderItems = PurchaseOrderItem::where('order_id', $order->id)->get();

        if ($orderItems->isEmpty()) {
            $order->status = 3; // 已收货
            $order->save();

            return;
        }

        // 统计所有已收货单的收货明细中本订单明细的累计收货数量
        $allReceivedItemIds = PurchaseReceive::where('order_id', $order->id)
            ->where('status', 1)
            ->pluck('id');

        $totalOrderedQty = $orderItems->sum('quantity');
        $totalReceivedQty = PurchaseReceiveItem::whereIn('receive_id', $allReceivedItemIds)->sum('quantity');

        if ($totalReceivedQty >= $totalOrderedQty) {
            $order->status = 3; // 已收货
        } elseif ($receivedCount > 0) {
            $order->status = 2; // 部分收货
        }
        $order->save();
    }

    /**
     * 收货单详情
     * @Apidoc\Title("收货单详情")
     * @Apidoc\Desc("获取指定收货单的详细信息，包含明细、订单、供应商和仓库")
     * @Apidoc\Url("/admin/purchase/receive/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="收货单ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="收货单详情(含关联数据)")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $receive = PurchaseReceive::with(['items', 'order', 'supplier', 'warehouse'])->find($id);
        if (!$receive) {
            return $this->fail('收货单不存在', 404);
        }

        return $this->success($this->encodeIds($receive->toArray(), ['id', 'order_id', 'supplier_id', 'warehouse_id']));
    }

    /**
     * 更新收货单
     * @Apidoc\Title("更新收货单")
     * @Apidoc\Desc("更新收货单备注等信息，不修改核心数据")
     * @Apidoc\Url("/admin/purchase/receive/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="收货单ID(hashid)")
     * @Apidoc\Param(name="remark", type="string", default="", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的收货单信息")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $receive = PurchaseReceive::find($id);
        if (!$receive) {
            return $this->fail('收货单不存在', 404);
        }

        if ($request->input('remark') !== null) {
            $receive->remark = $request->input('remark');
        }
        $receive->save();

        return $this->success($this->encodeIds($receive->toArray(), ['id', 'order_id', 'supplier_id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除收货单
     * @Apidoc\Title("删除收货单")
     * @Apidoc\Desc("软删除指定收货单，需要密码二次确认")
     * @Apidoc\Url("/admin/purchase/receive/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("采购管理")
     * @Apidoc\Param(name="id", type="string", require=true, desc="收货单ID(hashid)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="当前管理员密码(二次确认)")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $receive = PurchaseReceive::find($id);
        if (!$receive) {
            return $this->fail('收货单不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $receive->delete();

        return $this->success([], '删除成功');
    }
}
