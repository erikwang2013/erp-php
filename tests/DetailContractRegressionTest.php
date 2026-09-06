<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\HashidsService;
use app\common\SnowflakeService;
use app\controller\oms\FulfillmentController as OmsFulfillmentController;
use app\controller\oms\RmaController;
use app\controller\purchase\OrderController as PurchaseOrderController;
use app\controller\sales\OrderController as SalesOrderController;
use app\controller\workflow\ApprovalController;
use app\model\ApprovalInstance;
use app\model\ApprovalNode;
use app\model\ApprovalRecord;
use app\model\ApprovalWorkflow;
use app\model\Customer;
use app\model\Inventory;
use app\model\OmsFulfillment;
use app\model\OmsFulfillmentItem;
use app\model\OmsRma;
use app\model\OmsRmaItem;
use app\model\Product;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\model\Supplier;
use app\model\Warehouse;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 批2「后端详情与引用补充」契约回归：
 * - sales/purchase order：列表行 customer_name/supplier_name + FK hashid；show 嵌 items（行级 id/order_id/
 *   product_id hashid + product_name/product_code join，缺商品 null 兜底不丢行）
 * - oms fulfillment：index 可选 oms_order_id(hashid) 过滤（解码失败 422）+ warehouse_name + FK hashid；
 *   show 嵌 items + pick/pack_task/shipment_id hashid（0 → null）
 * - oms rma show 嵌 items
 * - approval/{id} 新详情端点：平字段 + workflow_name/current_node_name + records 时间线(operator_name) +
 *   target_ref（registry 内类型编码 / 外类型 null）
 *
 * 需 .env 真库（Eloquent erp_ 前缀连接）；DB 不可用自动跳过（保持无库环境绿）。
 * 批准类写操作（rma approve 等）走业务表内字段，非 approval_instance，本批不触碰。
 */
class DetailContractRegressionTest extends TestCase
{
    /** DB 不可用守卫：真实 erp 库缺失/连接失败时整类跳过 */
    protected function setUp(): void
    {
        try {
            Inventory::query()->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('数据库不可用（需 .env 真实 erp 库），跳过: ' . $e->getMessage());
        }
    }

    private function jsonBody(Response $resp): array
    {
        return (array) json_decode($resp->rawBody(), true);
    }

    private function encodeId(int $id): string
    {
        return HashidsService::encode($id);
    }

    private function randSuffix(): string
    {
        return (string) mt_rand(100000, 999999);
    }

    /* ======================== sales order ======================== */

    public function testSalesOrderShowEmbedsItemsAndCustomerName(): void
    {
        $suffix = $this->randSuffix();
        $orderId = SnowflakeService::generate();
        $customerId = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        $itemIds = [SnowflakeService::generate(), SnowflakeService::generate()];
        try {
            $customer = new Customer();
            $customer->id = $customerId;
            $customer->code = 'B2C-' . $suffix;
            $customer->name = '批2客户' . $suffix;
            $customer->save();

            $product = new Product();
            $product->id = $productId;
            $product->code = 'B2P-' . $suffix;
            $product->name = '批2商品' . $suffix;
            $product->save();

            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B2SO-' . $suffix;
            $order->customer_id = $customerId;
            $order->save();

            foreach ($itemIds as $i => $iid) {
                $item = new SalesOrderItem();
                $item->id = $iid;
                $item->order_id = $orderId;
                $item->product_id = $productId;
                $item->quantity = 2 + $i;
                $item->save();
            }

            $resp = (new SalesOrderController())->show(new FakeRequest(), $this->encodeId($orderId));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($orderId), $data['id'] ?? null);
            $this->assertSame($this->encodeId($customerId), $data['customer_id'] ?? null, 'customer_id 应输出 hashid');
            $this->assertSame('批2客户' . $suffix, $data['customer_name'] ?? null);
            $this->assertSame('B2SO-' . $suffix, $data['code'] ?? null, '既有平字段不得改变');

            $items = (array) ($data['items'] ?? []);
            $this->assertCount(2, $items, 'show 应嵌 2 条 items');
            foreach ($items as $row) {
                $this->assertContains($row['id'], array_map(fn ($iid) => $this->encodeId($iid), $itemIds), '行级 id 应为 hashid');
                $this->assertSame($this->encodeId($orderId), $row['order_id'] ?? null, '行级 order_id 应为 hashid');
                $this->assertSame($this->encodeId($productId), $row['product_id'] ?? null, '行级 product_id 应为 hashid');
                $this->assertSame('批2商品' . $suffix, $row['product_name'] ?? null);
                $this->assertSame('B2P-' . $suffix, $row['product_code'] ?? null);
            }
        } finally {
            SalesOrderItem::whereIn('id', $itemIds)->delete();
            SalesOrder::where('id', $orderId)->forceDelete();
            Product::where('id', $productId)->forceDelete();
            Customer::where('id', $customerId)->forceDelete();
        }
    }

    public function testSalesOrderIndexCarriesCustomerNameAndHashidCustomerId(): void
    {
        $suffix = $this->randSuffix();
        $orderId = SnowflakeService::generate();
        $customerId = SnowflakeService::generate();
        try {
            $customer = new Customer();
            $customer->id = $customerId;
            $customer->code = 'B2I-' . $suffix;
            $customer->name = '批2列表客户' . $suffix;
            $customer->save();

            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B2SOI-' . $suffix;
            $order->customer_id = $customerId;
            $order->save();

            $resp = (new SalesOrderController())->index(new FakeRequest(['page' => 1, 'limit' => 50]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = null;
            foreach ((array) ($body['data']['list'] ?? []) as $item) {
                if (($item['id'] ?? null) === $this->encodeId($orderId)) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '新插入的销售订单应出现在列表');
            $this->assertSame('批2列表客户' . $suffix, $row['customer_name'] ?? null);
            $this->assertSame($this->encodeId($customerId), $row['customer_id'] ?? null);
        } finally {
            SalesOrder::where('id', $orderId)->forceDelete();
            Customer::where('id', $customerId)->forceDelete();
        }
    }

    /* ======================== purchase order ======================== */

    public function testPurchaseOrderShowEmbedsItemsAndSupplierName(): void
    {
        $suffix = $this->randSuffix();
        $orderId = SnowflakeService::generate();
        $supplierId = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        $itemId = SnowflakeService::generate();
        try {
            $supplier = new Supplier();
            $supplier->id = $supplierId;
            $supplier->code = 'B2S-' . $suffix;
            $supplier->name = '批2供应商' . $suffix;
            $supplier->save();

            $product = new Product();
            $product->id = $productId;
            $product->code = 'B2PP-' . $suffix;
            $product->name = '批2采购商品' . $suffix;
            $product->save();

            $order = new PurchaseOrder();
            $order->id = $orderId;
            $order->code = 'B2PO-' . $suffix;
            $order->supplier_id = $supplierId;
            $order->save();

            $item = new PurchaseOrderItem();
            $item->id = $itemId;
            $item->order_id = $orderId;
            $item->product_id = $productId;
            $item->quantity = 5;
            $item->save();

            $resp = (new PurchaseOrderController())->show(new FakeRequest(), $this->encodeId($orderId));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($supplierId), $data['supplier_id'] ?? null);
            $this->assertSame('批2供应商' . $suffix, $data['supplier_name'] ?? null);
            $this->assertSame('B2PO-' . $suffix, $data['code'] ?? null, 'code 平字段原样保持');

            $items = (array) ($data['items'] ?? []);
            $this->assertCount(1, $items);
            $this->assertSame($this->encodeId($itemId), $items[0]['id'] ?? null);
            $this->assertSame($this->encodeId($orderId), $items[0]['order_id'] ?? null);
            $this->assertSame($this->encodeId($productId), $items[0]['product_id'] ?? null);
            $this->assertSame('批2采购商品' . $suffix, $items[0]['product_name'] ?? null);
            $this->assertSame('B2PP-' . $suffix, $items[0]['product_code'] ?? null);
        } finally {
            PurchaseOrderItem::where('id', $itemId)->delete();
            PurchaseOrder::where('id', $orderId)->forceDelete();
            Product::where('id', $productId)->forceDelete();
            Supplier::where('id', $supplierId)->forceDelete();
        }
    }

    public function testPurchaseOrderIndexCarriesSupplierName(): void
    {
        $suffix = $this->randSuffix();
        $orderId = SnowflakeService::generate();
        $supplierId = SnowflakeService::generate();
        try {
            $supplier = new Supplier();
            $supplier->id = $supplierId;
            $supplier->code = 'B2SI-' . $suffix;
            $supplier->name = '批2列表供应商' . $suffix;
            $supplier->save();

            $order = new PurchaseOrder();
            $order->id = $orderId;
            $order->code = 'B2POI-' . $suffix;
            $order->supplier_id = $supplierId;
            $order->save();

            $resp = (new PurchaseOrderController())->index(new FakeRequest(['page' => 1, 'limit' => 50]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = null;
            foreach ((array) ($body['data']['list'] ?? []) as $item) {
                if (($item['id'] ?? null) === $this->encodeId($orderId)) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '新插入的采购订单应出现在列表');
            $this->assertSame('批2列表供应商' . $suffix, $row['supplier_name'] ?? null);
            $this->assertSame($this->encodeId($supplierId), $row['supplier_id'] ?? null);
        } finally {
            PurchaseOrder::where('id', $orderId)->forceDelete();
            Supplier::where('id', $supplierId)->forceDelete();
        }
    }

    public function testPurchaseOrderStorePersistsDecodedSupplierId(): void
    {
        $suffix = $this->randSuffix();
        $supplierId = SnowflakeService::generate();
        $orderId = null;
        try {
            $resp = (new PurchaseOrderController())->store(new FakeRequest([
                'code' => 'B2POS-' . $suffix,
                'supplier_id' => $this->encodeId($supplierId),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $orderId = HashidsService::decode((string) ($data['id'] ?? ''));
            // store 响应与历史形状一致仅编码 id，supplier_id 出 raw int（解码后的值）
            $this->assertSame($supplierId, $data['supplier_id'] ?? null);

            $row = PurchaseOrder::find($orderId);
            $this->assertSame($supplierId, (int) $row->supplier_id, 'store 落库 supplier_id 应为解码后的 int（fill 不得用 hash 串覆写）');
        } finally {
            if ($orderId !== null) {
                PurchaseOrder::where('id', $orderId)->forceDelete();
            }
        }
    }

    public function testPurchaseOrderUpdatePersistsDecodedSupplierId(): void
    {
        $suffix = $this->randSuffix();
        $supplierA = SnowflakeService::generate();
        $supplierB = SnowflakeService::generate();
        $orderId = SnowflakeService::generate();
        try {
            $order = new PurchaseOrder();
            $order->id = $orderId;
            $order->code = 'B2POU-' . $suffix;
            $order->supplier_id = $supplierA;
            $order->save();

            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => $this->encodeId($supplierB)]),
                $this->encodeId($orderId)
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $this->assertSame($supplierB, ($body['data']['supplier_id'] ?? null));

            $row = PurchaseOrder::find($orderId);
            $this->assertSame($supplierB, (int) $row->supplier_id, 'update 落库 supplier_id 应为解码后的 int');
        } finally {
            PurchaseOrder::where('id', $orderId)->forceDelete();
        }
    }

    /* ======================== oms fulfillment ======================== */

    public function testFulfillmentIndexFiltersByOmsOrderIdWithWarehouseName(): void
    {
        $suffix = $this->randSuffix();
        $warehouseId = SnowflakeService::generate();
        $omsOrderA = SnowflakeService::generate();
        $omsOrderB = SnowflakeService::generate();
        $fulfillA = SnowflakeService::generate();
        $fulfillB = SnowflakeService::generate();
        try {
            $warehouse = new Warehouse();
            $warehouse->id = $warehouseId;
            $warehouse->code = 'B2W-' . $suffix;
            $warehouse->name = '批2仓库' . $suffix;
            $warehouse->save();

            foreach ([[$fulfillA, $omsOrderA], [$fulfillB, $omsOrderB]] as [$fid, $oid]) {
                $f = new OmsFulfillment();
                $f->id = $fid;
                $f->oms_order_id = $oid;
                $f->warehouse_id = $warehouseId;
                $f->save();
            }

            // 按 OMS 订单过滤（hashid 入参）只回命中行
            $resp = (new OmsFulfillmentController())->index(new FakeRequest([
                'page' => 1, 'limit' => 50, 'oms_order_id' => $this->encodeId($omsOrderA),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $list = (array) ($body['data']['list'] ?? []);
            $this->assertCount(1, $list);
            $this->assertSame($this->encodeId($fulfillA), $list[0]['id'] ?? null);
            $this->assertSame($this->encodeId($omsOrderA), $list[0]['oms_order_id'] ?? null);
            $this->assertSame($this->encodeId($warehouseId), $list[0]['warehouse_id'] ?? null);
            $this->assertSame('批2仓库' . $suffix, $list[0]['warehouse_name'] ?? null);

            // 无效 hashid → 422 明确文案（raw 数字/乱串均拒）
            foreach (['12345', 'not-a-hashid'] as $bad) {
                $resp = (new OmsFulfillmentController())->index(new FakeRequest(['oms_order_id' => $bad]));
                $this->assertSame(422, (int) ($this->jsonBody($resp)['code'] ?? -1), "oms_order_id={$bad} 应 422");
            }
        } finally {
            OmsFulfillment::whereIn('id', [$fulfillA, $fulfillB])->delete();
            Warehouse::where('id', $warehouseId)->forceDelete();
        }
    }

    public function testFulfillmentShowEmbedsItemsAndEncodesTaskRefs(): void
    {
        $suffix = $this->randSuffix();
        $fulfillId = SnowflakeService::generate();
        $omsOrderId = SnowflakeService::generate();
        $warehouseId = SnowflakeService::generate();
        $pickTaskId = SnowflakeService::generate();
        $packTaskId = SnowflakeService::generate();
        $shipmentId = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        $itemId = SnowflakeService::generate();
        $bareFulfillId = SnowflakeService::generate();
        try {
            $warehouse = new Warehouse();
            $warehouse->id = $warehouseId;
            $warehouse->code = 'B2WF-' . $suffix;
            $warehouse->name = '批2履约仓' . $suffix;
            $warehouse->save();

            $product = new Product();
            $product->id = $productId;
            $product->code = 'B2FP-' . $suffix;
            $product->name = '批2履约商品' . $suffix;
            $product->save();

            foreach ([[$fulfillId, $omsOrderId, $pickTaskId, $packTaskId, $shipmentId], [$bareFulfillId, $omsOrderId, 0, 0, 0]] as [$fid, $oid, $pick, $pack, $ship]) {
                $f = new OmsFulfillment();
                $f->id = $fid;
                $f->oms_order_id = $oid;
                $f->warehouse_id = $warehouseId;
                $f->pick_task_id = $pick;
                $f->pack_task_id = $pack;
                $f->shipment_id = $ship;
                $f->save();
            }

            $item = new OmsFulfillmentItem();
            $item->id = $itemId;
            $item->fulfillment_id = $fulfillId;
            $item->order_item_id = SnowflakeService::generate();
            $item->product_id = $productId;
            $item->allocated_quantity = 3;
            $item->save();

            $resp = (new OmsFulfillmentController())->show(new FakeRequest(), $this->encodeId($fulfillId));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($omsOrderId), $data['oms_order_id'] ?? null);
            $this->assertSame($this->encodeId($warehouseId), $data['warehouse_id'] ?? null);
            $this->assertSame('批2履约仓' . $suffix, $data['warehouse_name'] ?? null);
            $this->assertSame($this->encodeId($pickTaskId), $data['pick_task_id'] ?? null);
            $this->assertSame($this->encodeId($packTaskId), $data['pack_task_id'] ?? null);
            $this->assertSame($this->encodeId($shipmentId), $data['shipment_id'] ?? null);

            $items = (array) ($data['items'] ?? []);
            $this->assertCount(1, $items);
            $this->assertSame($this->encodeId($itemId), $items[0]['id'] ?? null);
            $this->assertSame($this->encodeId($productId), $items[0]['product_id'] ?? null);
            $this->assertSame('批2履约商品' . $suffix, $items[0]['product_name'] ?? null);
            $this->assertSame('B2FP-' . $suffix, $items[0]['product_code'] ?? null);

            // 未生成任务（0）→ null 而非 hashid(0)
            $resp = (new OmsFulfillmentController())->show(new FakeRequest(), $this->encodeId($bareFulfillId));
            $data = $this->jsonBody($resp)['data'] ?? [];
            foreach (['pick_task_id', 'pack_task_id', 'shipment_id'] as $fk) {
                $this->assertArrayHasKey($fk, $data);
                $this->assertNull($data[$fk], "{$fk}=0 应为 null");
            }
            $this->assertSame([], $data['items'] ?? null, '无明细时 items 应为空数组');
        } finally {
            OmsFulfillmentItem::where('id', $itemId)->delete();
            OmsFulfillment::whereIn('id', [$fulfillId, $bareFulfillId])->delete();
            Product::where('id', $productId)->forceDelete();
            Warehouse::where('id', $warehouseId)->forceDelete();
        }
    }

    /* ======================== oms rma ======================== */

    public function testRmaShowEmbedsItems(): void
    {
        $suffix = $this->randSuffix();
        $rmaId = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        $itemId = SnowflakeService::generate();
        try {
            $product = new Product();
            $product->id = $productId;
            $product->code = 'B2RP-' . $suffix;
            $product->name = '批2退货商品' . $suffix;
            $product->save();

            $rma = new OmsRma();
            $rma->id = $rmaId;
            $rma->code = 'B2RMA-' . $suffix;
            $rma->order_id = SnowflakeService::generate();
            $rma->customer_id = SnowflakeService::generate();
            $rma->save();

            $item = new OmsRmaItem();
            $item->id = $itemId;
            $item->rma_id = $rmaId;
            $item->order_item_id = SnowflakeService::generate();
            $item->product_id = $productId;
            $item->quantity = 1;
            $item->save();

            $resp = (new RmaController())->show(new FakeRequest(), $this->encodeId($rmaId));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($rmaId), $data['id'] ?? null);
            $this->assertSame('B2RMA-' . $suffix, $data['code'] ?? null, '既有平字段不得改变');
            $items = (array) ($data['items'] ?? []);
            $this->assertCount(1, $items);
            $this->assertSame($this->encodeId($itemId), $items[0]['id'] ?? null);
            $this->assertSame($this->encodeId($productId), $items[0]['product_id'] ?? null);
            $this->assertSame('批2退货商品' . $suffix, $items[0]['product_name'] ?? null);
            $this->assertSame('B2RP-' . $suffix, $items[0]['product_code'] ?? null);
        } finally {
            OmsRmaItem::where('id', $itemId)->delete();
            OmsRma::where('id', $rmaId)->delete();
            Product::where('id', $productId)->forceDelete();
        }
    }

    /* ======================== approval detail ======================== */

    /** 审批种子：workflow + node + instance + record + 操作人，返回各 id 供清理 */
    private function seedApproval(string $targetType, int $targetId): array
    {
        $suffix = $this->randSuffix();
        $workflowId = SnowflakeService::generate();
        $nodeId = SnowflakeService::generate();
        $instanceId = SnowflakeService::generate();
        $recordId = SnowflakeService::generate();
        $userId = SnowflakeService::generate();

        $wf = new ApprovalWorkflow();
        $wf->id = $workflowId;
        $wf->code = 'B2WF-' . $suffix;
        $wf->name = '批2审批流' . $suffix;
        $wf->target_type = $targetType;
        $wf->save();

        $node = new ApprovalNode();
        $node->id = $nodeId;
        $node->workflow_id = $workflowId;
        $node->name = '批2节点' . $suffix;
        $node->save();

        $instance = new ApprovalInstance();
        $instance->id = $instanceId;
        $instance->workflow_id = $workflowId;
        $instance->target_type = $targetType;
        $instance->target_id = $targetId;
        $instance->submitter_id = $userId;
        $instance->current_node_id = $nodeId;
        $instance->status = 0;
        $instance->submitted_at = date('Y-m-d H:i:s');
        $instance->save();

        $record = new ApprovalRecord();
        $record->id = $recordId;
        $record->instance_id = $instanceId;
        $record->node_id = $nodeId;
        $record->approver_id = $userId;
        $record->action = 1;
        $record->comment = '批2审批通过';
        $record->save();

        Capsule::connection()->table('admin_user')->insert([
            'id' => $userId, 'username' => 'b2user' . $suffix, 'password' => 'unused',
            'real_name' => '批2审批人' . $suffix,
        ]);

        return [
            'workflow_id' => $workflowId, 'node_id' => $nodeId, 'instance_id' => $instanceId,
            'record_id' => $recordId, 'user_id' => $userId, 'suffix' => $suffix,
        ];
    }

    public function testApprovalShowStructureAndTargetRef(): void
    {
        $targetId = SnowflakeService::generate();
        try {
            $ids = $this->seedApproval('purchase_order', $targetId);

            $resp = (new ApprovalController())->show(new FakeRequest(), $this->encodeId($ids['instance_id']));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($ids['instance_id']), $data['id'] ?? null);
            $this->assertSame($this->encodeId($ids['workflow_id']), $data['workflow_id'] ?? null);
            $this->assertSame($this->encodeId($ids['user_id']), $data['submitter_id'] ?? null, 'submitter_id 应 hashid');
            $this->assertSame('purchase_order', $data['target_type'] ?? null);
            $this->assertSame($targetId, $data['target_id'] ?? null, 'target_id 保持 raw int');
            $this->assertSame($this->encodeId($targetId), $data['target_ref'] ?? null, 'registry 内类型 target_ref 应编码');
            $this->assertSame('批2审批流' . $ids['suffix'], $data['workflow_name'] ?? null);
            $this->assertSame('批2节点' . $ids['suffix'], $data['current_node_name'] ?? null);
            $this->assertSame(0, $data['status'] ?? -1, '0=审批中 语义保持');

            $records = (array) ($data['records'] ?? []);
            $this->assertCount(1, $records, '时间线应含 1 条审批记录');
            $this->assertSame($this->encodeId($ids['record_id']), $records[0]['id'] ?? null);
            $this->assertSame($this->encodeId($ids['instance_id']), $records[0]['instance_id'] ?? null);
            $this->assertSame($this->encodeId($ids['node_id']), $records[0]['node_id'] ?? null);
            $this->assertSame($this->encodeId($ids['user_id']), $records[0]['approver_id'] ?? null);
            $this->assertSame('批2审批人' . $ids['suffix'], $records[0]['operator_name'] ?? null, 'operator_name 应 join admin_user.real_name');
            $this->assertSame(1, $records[0]['action'] ?? -1, '1=通过');
            $this->assertSame('批2审批通过', $records[0]['comment'] ?? null);
        } finally {
            $this->cleanupApproval($ids ?? []);
        }
    }

    public function testApprovalShowTargetRefNullForNonRegistryType(): void
    {
        // 'leave' 为 install.sql 列注释遗留取值、无独立单据资源 → registry 外：target_ref=null、target_id raw
        $targetId = 7777;
        try {
            $ids = $this->seedApproval('leave', $targetId);
            $resp = (new ApprovalController())->show(new FakeRequest(), $this->encodeId($ids['instance_id']));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame('leave', $data['target_type'] ?? null);
            $this->assertSame($targetId, $data['target_id'] ?? null);
            $this->assertArrayHasKey('target_ref', $data);
            $this->assertNull($data['target_ref'], 'registry 外类型 target_ref 应为 null');
            $this->assertCount(1, $data['records'] ?? [], 'registry 不影响 records 时间线');
        } finally {
            $this->cleanupApproval($ids ?? []);
        }
    }

    public function testApprovalShowRejectsInvalidHashid(): void
    {
        $resp = (new ApprovalController())->show(new FakeRequest(), 'not-a-hashid');
        $this->assertSame(400, (int) ($this->jsonBody($resp)['code'] ?? -1));
    }

    private function cleanupApproval(array $ids): void
    {
        if (!$ids) {
            return;
        }
        Capsule::connection()->table('admin_user')->where('id', $ids['user_id'])->delete();
        ApprovalRecord::where('id', $ids['record_id'])->delete();
        ApprovalInstance::where('id', $ids['instance_id'])->delete();
        ApprovalNode::where('id', $ids['node_id'])->delete();
        ApprovalWorkflow::where('id', $ids['workflow_id'])->forceDelete();
    }
}
