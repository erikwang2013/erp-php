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
use app\service\sales\CreditControlException;
use app\service\sales\CreditControlService;
use Illuminate\Database\Capsule\Manager as DB;
use support\Container;
use support\Request;
use support\Response;

/**
 * 销售发货管理
 */
#[\erikwang2013\apidoc\annotation\Tag('销售管理')]
#[\erikwang2013\apidoc\annotation\Title('发货单')]
#[\erikwang2013\apidoc\annotation\Group('销售管理')]

class DeliveryController extends BaseController
{
    /**
     * 发货单列表（分页）
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('发货单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取销售发货单分页列表，支持关键字/状态/订单/客户筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/sales/delivery')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词(发货单号)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选:0待发货1已发货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'string', default:'', desc:'销售订单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'string', default:'', desc:'客户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('list', type:'array', desc:'发货单列表')]
    #[\erikwang2013\apidoc\annotation\Returned('total', type:'int', desc:'总条数')]
    #[\erikwang2013\apidoc\annotation\Returned('page', type:'int', desc:'当前页码')]
    #[\erikwang2013\apidoc\annotation\Returned('limit', type:'int', desc:'每页条数')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
            'order_id' => 'string',
            'customer_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $orderId = $request->input('order_id');
        $customerId = $request->input('customer_id');

        // order_id → erp_sales_order 的外键（install.sql 列注释「销售订单ID」）：
        // 只下发 hashid 的话「销售订单」列无处可读，leftJoin 把单号以 order_code 带出
        // （同 OrderController::index 的 apply_code 范式）。筛选/排序须带表名——
        // sales_order 同有 code/status/id/customer_id/warehouse_id，裸列名会 1052 ambiguous
        $query = SalesDelivery::with(['items', 'order', 'customer', 'warehouse'])
            ->leftJoin('sales_order', 'sales_order.id', '=', 'sales_delivery.order_id')
            ->select('sales_delivery.*', 'sales_order.code as order_code');
        if ($keyword) {
            $query->where('sales_delivery.code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('sales_delivery.status', (int) $status);
        }
        if ($orderId !== null && $orderId !== '') {
            $decoded = $this->decodeIdSafe($orderId);
            if ($decoded !== null && $decoded > 0) {
                $query->where('sales_delivery.order_id', $decoded);
            }
        }
        if ($customerId !== null && $customerId !== '') {
            $decoded = $this->decodeIdSafe($customerId);
            if ($decoded !== null && $decoded > 0) {
                $query->where('sales_delivery.customer_id', $decoded);
            }
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('sales_delivery.id', 'desc')->get()->map(function ($delivery) {
                return $this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']);
            });

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建发货单并执行出库
     */
    #[\erikwang2013\apidoc\annotation\Title('创建发货单')]
    #[\erikwang2013\apidoc\annotation\Desc('创建发货单并自动执行出库操作，同时生成应收记录并更新销售订单状态')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/sales/delivery')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', require:true, desc:'发货单号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'string', require:true, desc:'销售订单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'string', require:true, desc:'客户ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'warehouse_id', type:'string', require:true, desc:'仓库ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', require:true, desc:'发货明细(含product_id/quantity/price等)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'发货单信息')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'nullable|string|max:50',
            'order_id' => 'required|string',
            'customer_id' => 'required|string',
            'warehouse_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            // order_item_id 可缺省：订单明细行的 hashid 没有界面能查到（通用表单只能选到商品），
            // 缺省时在下方按 product_id 在本单内反查（同采购收货契约），归属校验照旧
            'items.*.order_item_id' => 'nullable',
            'items.*.quantity' => 'required|numeric|min:0.01|max:9999999999.99',
            'items.*.price' => 'required|numeric|min:0|max:9999999999.99',
            // 上限对齐列宽（varchar 50 / 20）：超长落库报 1406，会以 500 返回
            'items.*.batch_code' => 'nullable|string|max:50',
            'items.*.unit' => 'nullable|string|max:20',
            'remark' => 'string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $orderId = $this->decodeFlexibleId($request->input('order_id'));
        if ($orderId === null || $orderId < 1) {
            return $this->fail($this->trans('Invalid order_id'), 422);
        }
        $order = SalesOrder::find($orderId);
        if (!$order) {
            return $this->fail($this->trans('Sales order not found'), 404);
        }

        // 预取订单明细（按 id 索引），供归属校验与超发校验使用
        $orderItems = SalesOrderItem::query()->where('order_id', $orderId)->get()->keyBy('id');

        // 信用控制前置拦截（事务外）：冻结/额度/账期超期任一不过即 422 业务拒绝
        // 发货为在途占用→应收 1:1 平移，额度占用已含本次实发，无需额外累加；
        // 通过时返回客户账期到期日（credit_days>0），用于写入应收 due_date
        $creditService = Container::get(CreditControlService::class);
        try {
            $dueDate = $creditService->assertDeliveryCreate((int) $order->customer_id);
        } catch (CreditControlException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        DB::beginTransaction();
        try {
            // 1. 创建发货单头
            $delivery = new SalesDelivery();
            $delivery->id = $this->generateId();
            $delivery->code = doc_code($request->input('code'), 'SD');
            $delivery->order_id = $orderId;
            // 两个外键同套双模解码（hashid 或原生数字）：垃圾串 422 而不是落 0 成孤儿单
            $customerId = $this->decodeFlexibleId($request->input('customer_id'));
            $warehouseId = $this->decodeFlexibleId($request->input('warehouse_id'));
            if ($customerId === null || $customerId < 1 || $warehouseId === null || $warehouseId < 1) {
                throw new \RuntimeException('客户或仓库（customer_id/warehouse_id）无效');
            }
            $delivery->customer_id = $customerId;
            $delivery->warehouse_id = $warehouseId;
            $delivery->status = 0; // 待发货
            $delivery->remark = $request->input('remark', '');
            $delivery->delivered_at = date('Y-m-d H:i:s');
            $delivery->save();

            // 从容器获取服务实例（便于测试时替换/注入 mock）
            $inventoryService = Container::get(InventoryService::class);
            $financeService = Container::get(FinanceService::class);
            $totalDeliveryAmount = '0';

            // 2. 创建发货明细 + 执行出库
            foreach ($request->input('items') as $itemData) {
                // 明细外键同采购收货一套双模解码（hashid 或原生数字）：product_id 缺失/无效必须拒绝
                // （落 0 会出库到无商品的行），其余三个是可选归属列，无法解析即按「未提供」落 0
                $productId = $this->decodeFlexibleId($itemData['product_id'] ?? '');
                if ($productId === null || $productId < 1) {
                    throw new \RuntimeException('发货明细 product_id 无效');
                }
                $skuId = $this->decodeFlexibleId($itemData['sku_id'] ?? '') ?? 0;
                $locationId = $this->decodeFlexibleId($itemData['location_id'] ?? '') ?? 0;
                $orderItemId = $this->decodeFlexibleId($itemData['order_item_id'] ?? '') ?? 0;
                $quantity = bc_norm($itemData['quantity']);
                $price = bc_norm($itemData['price']);
                $amount = bc_round(bcmul($quantity, $price, 6), 2);
                $batchCode = $itemData['batch_code'] ?? '';
                $unit = $itemData['unit'] ?? '';
                $totalDeliveryAmount = bcadd($totalDeliveryAmount, $amount, 6);

                // order_item_id 缺省时按商品反查本单明细行：本单该商品恰好一行才可判定，
                // 0 行（商品不在本单）或多行（同商品多行，价/批不同）都必须让调用方显式指定
                if ($orderItemId <= 0) {
                    $candidates = $orderItems->where('product_id', $productId);
                    if ($candidates->count() !== 1) {
                        throw new \RuntimeException(
                            "发货明细未指定订单明细行，且本单商品({$productId})对应 {$candidates->count()} 行，无法确定，请显式传入 order_item_id"
                        );
                    }
                    $orderItemId = (int) $candidates->keys()->first();
                }

                // 归属校验：order_item_id 必须属于该销售单
                if ($orderItemId <= 0 || !isset($orderItems[$orderItemId])) {
                    throw new \RuntimeException("发货明细 order_item_id 缺失或不属于该销售订单: order_item_id={$orderItemId}");
                }

                // 超发校验（严格拒绝）：该行累计实发（历史已出库发货单 + 本次）不得超过订购数量
                // 行锁串行化并发：锁住销售明细行后再做累计校验，防并发两单同时通过
                $orderItem = SalesOrderItem::query()->whereKey($orderItemId)->lockForUpdate()->first();
                if (!$orderItem) {
                    throw new \RuntimeException("销售明细不存在: order_item_id={$orderItemId}");
                }
                $deliveredSoFar = bc_norm(SalesDeliveryItem::query()->join('sales_delivery', 'sales_delivery.id', '=', 'sales_delivery_item.delivery_id')
                    ->where('sales_delivery.order_id', $orderId)
                    ->where('sales_delivery.status', 1)
                    ->whereNull('sales_delivery.deleted_at')
                    ->where('sales_delivery_item.order_item_id', $orderItemId)
                    ->sum('sales_delivery_item.quantity'));
                $orderedQty = bc_norm($orderItem->quantity);
                if (bccomp(bcadd($deliveredSoFar, $quantity, 4), $orderedQty, 4) > 0) {
                    throw new \RuntimeException(
                        "超发拒绝: 行{$orderItemId} 订购{$orderedQty}, 累计实发" . bc_round(bcadd($deliveredSoFar, $quantity, 6), 2)
                    );
                }

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
                    (float) $quantity,
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
                (float) $totalDeliveryAmount,
                $dueDate
            );

            // 5. 更新销售订单状态
            $this->updateOrderStatus($order, $delivery->id);

            DB::commit();

            return $this->success(
                $this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']),
                $this->trans('Shipment succeeded; goods issued and a receivable record was created')
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logError('执行发货', $e);

            // 同采购收货：业务规则拒绝（超发/明细不属本单/商品对应多行/库存不足）是调用方可纠正的
            // 输入问题 → 422；PDOException 也是 RuntimeException（SQL/连接故障属服务端），须排除后再判 500
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($this->trans('Shipment failed: ') . $e->getMessage(), 422) : $this->failServer();
        }
    }

    /**
     * 更新销售订单发货状态
     * 逐明细行比较：每行累计实发 ≥ 该行订购数量才算该行完成；
     * 全部行完成 → 已发货，有任意行完成 → 部分发货（修复跨明细总量比较的误判）。
     */
    private function updateOrderStatus(SalesOrder $order, int $deliveryId): void
    {
        $orderItems = SalesOrderItem::query()->where('order_id', $order->id)->get();

        if ($orderItems->isEmpty()) {
            $order->status = 3; // 已发货
            $order->save();

            return;
        }

        // 各订单明细行的累计实发（跨全部已出库发货单）
        $deliveredByItem = SalesDeliveryItem::query()->join('sales_delivery', 'sales_delivery.id', '=', 'sales_delivery_item.delivery_id')
            ->where('sales_delivery.order_id', $order->id)
            ->where('sales_delivery.status', 1)
            ->whereNull('sales_delivery.deleted_at')
            ->groupBy('sales_delivery_item.order_item_id')
            ->selectRaw(db_prefix().'sales_delivery_item.order_item_id')
            ->selectRaw('SUM(' . db_prefix() . 'sales_delivery_item.quantity) as total_delivered')
            ->get()
            ->pluck('total_delivered', 'order_item_id');

        $allComplete = true;
        $anyDelivered = false;
        foreach ($orderItems as $item) {
            $delivered = bc_norm($deliveredByItem[$item->id] ?? 0);
            if (bccomp($delivered, '0', 4) <= 0) {
                $allComplete = false;
                continue;
            }
            $anyDelivered = true;
            if (bccomp($delivered, bc_norm($item->quantity), 4) < 0) {
                $allComplete = false;
            }
        }

        if ($allComplete) {
            $order->status = 3; // 每行实发均达订购量 → 已发货
        } elseif ($anyDelivered) {
            $order->status = 2; // 部分发货
        }
        $order->save();
    }

    /**
     * 发货单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('发货单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('获取指定发货单的详细信息，包含明细、订单、客户和仓库')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'发货单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'发货单详情(含关联数据)')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $delivery = SalesDelivery::with(['items', 'order', 'customer', 'warehouse'])->find($id);
        if (!$delivery) {
            return $this->fail($this->trans('Shipment not found'), 404);
        }

        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']));
    }

    /**
     * 更新发货单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新发货单')]
    #[\erikwang2013\apidoc\annotation\Desc('更新发货单备注等信息，不修改核心数据')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'发货单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', default:'', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的发货单信息')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'remark' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) {
            return $this->fail($this->trans('Shipment not found'), 404);
        }

        if ($request->input('remark') !== null) {
            $delivery->remark = $request->input('remark');
        }
        $delivery->save();

        return $this->success($this->encodeIds($delivery->toArray(), ['id', 'order_id', 'customer_id', 'warehouse_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除发货单
     */
    #[\erikwang2013\apidoc\annotation\Title('删除发货单')]
    #[\erikwang2013\apidoc\annotation\Desc('软删除指定发货单，需要密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', require:true, desc:'发货单ID(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', require:true, desc:'当前管理员密码(二次确认)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'password' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $delivery = SalesDelivery::find($id);
        if (!$delivery) {
            return $this->fail($this->trans('Shipment not found'), 404);
        }
        if ($delivery->status === 1) {
            return $this->fail($this->trans('Shipped delivery notes cannot be deleted'), 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $delivery->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
