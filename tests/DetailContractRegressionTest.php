<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\controller\OpenApiController;
use app\admin\controller\PermissionController;
use app\admin\controller\WebhookController;
use app\common\HashidsService;
use app\common\SnowflakeService;
use app\controller\eam\EamInspectionController;
use app\controller\eam\MaintenancePlanController;
use app\controller\eam\RepairOrderController;
use app\controller\finance\ExpenseController;
use app\controller\finance\FinanceBillController;
use app\controller\finance\InvoiceController;
use app\controller\finance\PaymentController;
use app\controller\hr\PerformanceController;
use app\controller\inventory\TransferController;
use app\controller\manufacturing\BomController;
use app\controller\oms\FulfillmentController as OmsFulfillmentController;
use app\controller\oms\OrderController as OmsOrderController;
use app\controller\oms\RmaController;
use app\controller\purchase\OrderController as PurchaseOrderController;
use app\controller\sales\OrderController as SalesOrderController;
use app\controller\tms\FreightInvoiceController;
use app\controller\wms\PackController;
use app\controller\wms\PickController;
use app\controller\wms\PutawayController;
use app\controller\workflow\ApprovalController;
use app\model\AdminPermission;
use app\model\AdminUser;
use app\model\ApprovalInstance;
use app\model\ApprovalNode;
use app\model\ApprovalRecord;
use app\model\ApprovalWorkflow;
use app\model\Customer;
use app\model\EamEquipment;
use app\model\EamInspectionResult;
use app\model\EamInspectionTask;
use app\model\EamMaintenancePlan;
use app\model\EamRepairOrder;
use app\model\FinanceBankAccount;
use app\model\FinanceBill;
use app\model\FinanceExpense;
use app\model\FinanceInvoice;
use app\model\FinancePayment;
use app\model\HrPerfPlan;
use app\model\Inventory;
use app\model\MfgBom;
use app\model\OmsFulfillment;
use app\model\OmsFulfillmentItem;
use app\model\OmsOrder;
use app\model\OmsRma;
use app\model\OmsRmaItem;
use app\model\OpenApiApp;
use app\model\Product;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\model\Supplier;
use app\model\TmsCarrier;
use app\model\TmsFreightInvoice;
use app\model\TmsShipment;
use app\model\Transfer;
use app\model\Warehouse;
use app\model\WebhookSubscription;
use app\model\WmsPackTask;
use app\model\WmsPickTask;
use app\model\WmsPutawayTask;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use support\Request;
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
            // 对外 ID 一律 hashid（docs/FEATURE_DESIGN.md「所有ID…hashids 加密传输」）：
            // 写响应与 list/show 同形，supplier_id 出解码落库后重编码的 hashid；
            // 「fill 未用 hash 串覆写」由下面读库断言独立保证
            $this->assertSame($this->encodeId($supplierId), $data['supplier_id'] ?? null);

            $row = PurchaseOrder::find($orderId);
            $this->assertSame($supplierId, (int) $row->supplier_id, 'store 落库 supplier_id 应为解码后的 int（fill 不得用 hash 串覆写）');
        } finally {
            if ($orderId !== null) {
                PurchaseOrder::where('id', $orderId)->forceDelete();
            }
        }
    }

    /**
     * 回归（批2）：store 的 supplier_id 原写 `string`（is_string() 把数字形态的供应商 ID 判 422）——
     * 与 update 侧同族的写路径缺口，只修 update 等于洞留一半。现只留 required（列 NOT NULL 无默认值，
     * api 文档亦为 require:true），双模判定收口 decodeFlexibleId（口径同 bi/DatasetController::store）。
     *
     * 负控（改回缺陷即红）：规则恢复 `string` → 第 1 段断言 422 ≠ 0。
     * 数组/缺省两段只锁「422 而非 500」的外部契约（phpunit 下没有 webman 的 set_error_handler，
     * 对 (string) 强转不敏感），见 eam 同族注释。
     */
    public function testPurchaseOrderStoreAcceptsNumericAndHashidSupplierId(): void
    {
        $suffix = $this->randSuffix();
        $supplierId = SnowflakeService::generate();
        $orderIds = [];
        try {
            // 1. 数字形态（修复前被 string 规则判 422）
            $resp = (new PurchaseOrderController())->store(new FakeRequest([
                'code' => 'B2POSN-' . $suffix,
                'supplier_id' => $supplierId,
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 supplier_id 不得 422');
            $orderIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', end($orderIds))->value('supplier_id'),
                '数字 supplier_id 应原样落库为裸 BIGINT'
            );

            // 2. hashid 仍能过（不得为放行数字而丢双模）
            $resp = (new PurchaseOrderController())->store(new FakeRequest([
                'code' => 'B2POSH-' . $suffix,
                'supplier_id' => $this->encodeId($supplierId),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? 'hashid supplier_id 不得 422');
            $orderIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', end($orderIds))->value('supplier_id'),
                'hashid 应解码落库'
            );

            // 3. 垃圾串 422：不得退化成 (int)'abc'=0 建无主单
            $bad = (new PurchaseOrderController())->store(new FakeRequest([
                'code' => 'B2POSB-' . $suffix,
                'supplier_id' => 'not-a-hashid',
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 supplier_id 应 422');

            // 4. 缺省 422（必填外键，required 或解码失败都算，不锁文案）
            $bad = (new PurchaseOrderController())->store(new FakeRequest(['code' => 'B2POSM-' . $suffix]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '缺省 supplier_id 应 422');

            // 5. 数组 422 而非 500
            $bad = (new PurchaseOrderController())->store(new FakeRequest([
                'code' => 'B2POSA-' . $suffix,
                'supplier_id' => ['oops'],
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 supplier_id 应 422 而非 500');
        } finally {
            foreach ($orderIds as $id) {
                PurchaseOrder::where('id', $id)->forceDelete();
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
            $this->assertSame($this->encodeId($supplierB), ($body['data']['supplier_id'] ?? null));

            $row = PurchaseOrder::find($orderId);
            $this->assertSame($supplierB, (int) $row->supplier_id, 'update 落库 supplier_id 应为解码后的 int');
        } finally {
            PurchaseOrder::where('id', $orderId)->forceDelete();
        }
    }

    /* ======================== sales order store/update 解码（批3 mini-fix） ======================== */

    public function testSalesOrderStorePersistsDecodedCustomerId(): void
    {
        $suffix = $this->randSuffix();
        $customerId = SnowflakeService::generate();
        $orderId = null;
        try {
            $resp = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B3SOS-' . $suffix,
                'customer_id' => $this->encodeId($customerId),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $orderId = HashidsService::decode((string) ($data['id'] ?? ''));
            // 对外 ID 一律 hashid（同 purchase store）：写响应与 list/show 同形
            $this->assertSame($this->encodeId($customerId), $data['customer_id'] ?? null);

            $row = SalesOrder::find($orderId);
            $this->assertSame($customerId, (int) $row->customer_id, 'store 落库 customer_id 应为解码后的 int（fill 不得用 hash 串覆写）');
        } finally {
            if ($orderId !== null) {
                SalesOrder::where('id', $orderId)->forceDelete();
            }
        }
    }

    public function testSalesOrderUpdatePersistsDecodedCustomerId(): void
    {
        $suffix = $this->randSuffix();
        $customerA = SnowflakeService::generate();
        $customerB = SnowflakeService::generate();
        $orderId = SnowflakeService::generate();
        try {
            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B3SOU-' . $suffix;
            $order->customer_id = $customerA;
            $order->save();

            $resp = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => $this->encodeId($customerB)]),
                $this->encodeId($orderId)
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $this->assertSame($this->encodeId($customerB), $body['data']['customer_id'] ?? null);

            $row = SalesOrder::find($orderId);
            $this->assertSame($customerB, (int) $row->customer_id, 'update 落库 customer_id 应为解码后的 int');
        } finally {
            SalesOrder::where('id', $orderId)->forceDelete();
        }
    }

    /**
     * 回归：warehouse_id 也在 $fillable 内，但 store/update 原先既不校验也不解码 ——
     * 下拉下发的 hashid 串被 fill 直填 BIGINT UNSIGNED NOT NULL DEFAULT 0 列，
     * MySQL 严格模式报 1366 → 未捕获 QueryException → 真 HTTP 500。
     * 编辑回填重提交（回填值就是 hashid）必然踩到，故 store/update 都要解码。
     */
    public function testSalesOrderStorePersistsDecodedWarehouseId(): void
    {
        $suffix = $this->randSuffix();
        $customerId = SnowflakeService::generate();
        $warehouseId = SnowflakeService::generate();
        $orderId = null;
        try {
            $resp = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B3SOW-' . $suffix,
                'customer_id' => $this->encodeId($customerId),
                'warehouse_id' => $this->encodeId($warehouseId),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $orderId = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame($this->encodeId($warehouseId), $body['data']['warehouse_id'] ?? null);

            $row = SalesOrder::find($orderId);
            $this->assertSame($warehouseId, (int) $row->warehouse_id, 'store 落库 warehouse_id 应为解码后的 int（hash 串直填会 1366）');

            // 缺省/空串 = 不指定 → 0（列 NOT NULL DEFAULT 0），不得把 '' 写进 BIGINT 列
            $resp = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B3SOW0-' . $suffix,
                'customer_id' => $this->encodeId($customerId),
                'warehouse_id' => '',
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $blankId = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(0, (int) SalesOrder::where('id', $blankId)->value('warehouse_id'), '空 warehouse_id 应落 0');
            SalesOrder::where('id', $blankId)->forceDelete();
        } finally {
            if ($orderId !== null) {
                SalesOrder::where('id', $orderId)->forceDelete();
            }
        }
    }

    public function testSalesOrderUpdatePersistsDecodedWarehouseId(): void
    {
        $suffix = $this->randSuffix();
        $customerId = SnowflakeService::generate();
        $warehouseA = SnowflakeService::generate();
        $warehouseB = SnowflakeService::generate();
        $orderId = SnowflakeService::generate();
        try {
            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B3SOWU-' . $suffix;
            $order->customer_id = $customerId;
            $order->warehouse_id = $warehouseA;
            $order->save();

            $resp = (new SalesOrderController())->update(
                new FakeRequest(['warehouse_id' => $this->encodeId($warehouseB)]),
                $this->encodeId($orderId)
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $this->assertSame(
                $warehouseB,
                (int) SalesOrder::where('id', $orderId)->value('warehouse_id'),
                'update 落库 warehouse_id 应为解码后的 int'
            );

            // 部分更新：不带 warehouse_id 的请求不得把已存值抹成 0
            (new SalesOrderController())->update(new FakeRequest(['code' => 'B3SOWU-' . $suffix]), $this->encodeId($orderId));
            $this->assertSame(
                $warehouseB,
                (int) SalesOrder::where('id', $orderId)->value('warehouse_id'),
                '未传 warehouse_id 时不得覆写既有外键'
            );
        } finally {
            SalesOrder::where('id', $orderId)->forceDelete();
        }
    }

    /**
     * 回归（批2）：store 的 customer_id 原写 `string`（is_string() 把数字形态的客户 ID 判 422），
     * 且解码走 decodeIdSafe（纯 hashid：数字串抛异常 → 422，且 hashids 对某些纯数字串会解出垃圾大数
     * ——见 decodeFlexibleId 注释）。现只留 required（列 NOT NULL 无默认值、api 文档 require:true），
     * 双模判定收口 decodeFlexibleId（口径同 purchase/OrderController::store 的 supplier_id）。
     *
     * 负控（改回缺陷即红）：规则恢复 `string` 或解码退回 decodeIdSafe → 第 1 段数字分支 422 ≠ 0。
     */
    public function testSalesOrderStoreAcceptsNumericAndHashidCustomerId(): void
    {
        $suffix = $this->randSuffix();
        $customerId = SnowflakeService::generate();
        $orderIds = [];
        try {
            // 1. 数字形态（修复前被 string 规则 + decodeIdSafe 双重判 422）
            $resp = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B2SOSN-' . $suffix,
                'customer_id' => $customerId,
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 customer_id 不得 422');
            $orderIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $customerId,
                (int) SalesOrder::where('id', end($orderIds))->value('customer_id'),
                '数字 customer_id 应原样落库为裸 BIGINT'
            );

            // 2. hashid 仍能过（不得为放行数字而丢双模）
            $resp = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B2SOSH-' . $suffix,
                'customer_id' => $this->encodeId($customerId),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? 'hashid customer_id 不得 422');
            $orderIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $customerId,
                (int) SalesOrder::where('id', end($orderIds))->value('customer_id'),
                'hashid 应解码落库'
            );

            // 3. 垃圾串 422：不得退化成 (int)'abc'=0 建无主单
            $bad = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B2SOSB-' . $suffix,
                'customer_id' => 'not-a-hashid',
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 customer_id 应 422');

            // 4. 缺省 422（必填外键；required 或解码失败都算，不锁文案）
            $bad = (new SalesOrderController())->store(new FakeRequest(['code' => 'B2SOSM-' . $suffix]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '缺省 customer_id 应 422');

            // 5. 数组 422 而非 500
            $bad = (new SalesOrderController())->store(new FakeRequest([
                'code' => 'B2SOSA-' . $suffix,
                'customer_id' => ['oops'],
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 customer_id 应 422 而非 500');
        } finally {
            foreach ($orderIds as $id) {
                SalesOrder::where('id', $id)->forceDelete();
            }
        }
    }

    /**
     * 回归（批2）：update 的 customer_id 原先只对「带值」覆写（`!== null && !== ''` 守卫），空串既不
     * 覆写也不解码 —— fill 留在模型上的 '' 一路 save 到 BIGINT NOT NULL 列（erp_sales_order.customer_id
     * 无默认值），MySQL 严格模式报 1366 → 未捕获 QueryException → 真 HTTP 500。与 purchase update 同型。
     * 现口径同 purchase/OrderController::update：未传/显式 null → 不动；空串 → 回填原值（必填外键清空
     * 无意义，但必须抹掉 fill 带进来的 ''/null）；传值 → 双模解码覆写；垃圾串 422 且不动既有值。
     *
     * 负控（改回缺陷即红）：空串分支恢复 `!== null && !== ''` 守卫，第 1 段断言即以 500 失败；
     * 第 3 段（数字）对应移除的 `string` 规则。
     */
    public function testSalesOrderUpdateHandlesEmptyAndAbsentCustomerId(): void
    {
        $suffix = $this->randSuffix();
        $customerA = SnowflakeService::generate();
        $customerB = SnowflakeService::generate();
        $customerC = SnowflakeService::generate();
        $orderId = SnowflakeService::generate();
        try {
            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B2SOU-' . $suffix;
            $order->customer_id = $customerA;
            $order->save();

            // 1. 空串：不得 500，既有客户保持（修复前 '' 直落 BIGINT → 1366）
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => '']),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '空 customer_id 不得 500');
            $this->assertSame(
                $customerA,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                '空 customer_id 应维持原值'
            );

            // 2. hashid：解码成裸 BIGINT 落库
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => $this->encodeId($customerB)]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), $this->jsonBody($resp)['message'] ?? '');
            $this->assertSame(
                $customerB,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                'hashid 应解码成裸 BIGINT 落库'
            );

            // 3. 数字形态：update 侧 validator 原也挂 `string`（数字 ID 判 422）
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => $customerC]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '数字 customer_id 不得 422');
            $this->assertSame(
                $customerC,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                '数字 customer_id 应原样落库'
            );

            // 4. 不传：既有外键原样保持（局部更新）
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['code' => 'B2SOU-' . $suffix]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1));
            $this->assertSame(
                $customerC,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                '未传 customer_id 不得改动既有值'
            );

            // 5. 显式 null：同样按「未传」处理，不得以 null 落 NOT NULL 列（1048 → 500）
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => null]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '显式 null customer_id 不得 500');
            $this->assertSame(
                $customerC,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                '显式 null customer_id 不得改动既有值'
            );

            // 6. 垃圾串 422，且不动既有值
            $bad = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => 'not-a-hashid']),
                $this->encodeId($orderId)
            );
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 customer_id 应 422');
            $this->assertSame(
                $customerC,
                (int) SalesOrder::where('id', $orderId)->value('customer_id'),
                '422 分支不得改动既有值'
            );

            // 7. 数组 422 而非 500
            $bad = (new SalesOrderController())->update(
                new FakeRequest(['customer_id' => ['oops']]),
                $this->encodeId($orderId)
            );
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 customer_id 应 422 而非 500');

            // 8. 探针（批2 观察项）：同方法内的 warehouse_id 也走 fill，显式 null 是否落 NOT NULL 列
            $warehouseId = SnowflakeService::generate();
            SalesOrder::where('id', $orderId)->update(['warehouse_id' => $warehouseId]);
            $resp = (new SalesOrderController())->update(
                new FakeRequest(['warehouse_id' => null]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '显式 null warehouse_id 不得 500');
            $this->assertSame(
                $warehouseId,
                (int) SalesOrder::where('id', $orderId)->value('warehouse_id'),
                '显式 null warehouse_id 不得改动既有值'
            );
        } finally {
            SalesOrder::where('id', $orderId)->forceDelete();
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
            $this->assertSame('批2审批人' . $ids['suffix'], $data['submitter_name'] ?? null, 'submitter_name 应 join admin_user.real_name');
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
        // 随机 id：uk_target=(target_type,target_id)，硬编码值在多条车道并发跑本文件时会撞 1062
        $targetId = SnowflakeService::generate();
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

    /* ======================== oms order code join（批7 收口） ======================== */

    /** OMS 订单种子：销售订单（code 单号）+ OMS 扩展行，返回 id 供清理 */
    private function seedOmsOrderWithSalesCode(string $suffix): array
    {
        $orderId = SnowflakeService::generate();
        $customerId = SnowflakeService::generate();
        $omsId = SnowflakeService::generate();

        $customer = new Customer();
        $customer->id = $customerId;
        $customer->code = 'B7C-' . $suffix;
        $customer->name = '批7客户' . $suffix;
        $customer->save();

        $order = new SalesOrder();
        $order->id = $orderId;
        $order->code = 'B7SO-' . $suffix;
        $order->customer_id = $customerId;
        $order->save();

        $oms = new OmsOrder();
        $oms->id = $omsId;
        $oms->order_id = $orderId;
        $oms->save();

        return ['order_id' => $orderId, 'customer_id' => $customerId, 'oms_id' => $omsId, 'suffix' => $suffix];
    }

    private function cleanupOmsOrderSeed(array $ids): void
    {
        if (!$ids) {
            return;
        }
        OmsOrder::where('id', $ids['oms_id'])->delete();
        SalesOrder::where('id', $ids['order_id'])->forceDelete();
        Customer::where('id', $ids['customer_id'])->forceDelete();
    }

    public function testOmsOrderIndexKeywordSearchesSalesCodeWithAlias(): void
    {
        $suffix = $this->randSuffix();
        $ids = [];
        try {
            $ids = $this->seedOmsOrderWithSalesCode($suffix);

            // keyword 搜销售单号命中且不报 SQL 错（旧实现按幻列 code where → 崩溃）
            $resp = (new OmsOrderController())->index(new FakeRequest(['page' => 1, 'limit' => 50, 'keyword' => 'B7SO-' . $suffix]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = null;
            foreach ((array) ($body['data']['list'] ?? []) as $item) {
                if (($item['id'] ?? null) === $this->encodeId($ids['oms_id'])) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '按销售单号搜索应命中 OMS 扩展行');
            $this->assertSame('B7SO-' . $suffix, $row['code'] ?? null, 'code 应为 leftJoin 带出的销售单号别名');
        } finally {
            $this->cleanupOmsOrderSeed($ids);
        }
    }

    public function testOmsOrderIndexReturnsSalesCodeAliasWithoutKeyword(): void
    {
        $suffix = $this->randSuffix();
        $ids = [];
        try {
            $ids = $this->seedOmsOrderWithSalesCode($suffix);

            $resp = (new OmsOrderController())->index(new FakeRequest(['page' => 1, 'limit' => 50]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = null;
            foreach ((array) ($body['data']['list'] ?? []) as $item) {
                if (($item['id'] ?? null) === $this->encodeId($ids['oms_id'])) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '新插入 OMS 行应出现在列表');
            $this->assertSame('B7SO-' . $suffix, $row['code'] ?? null, '列表行 code 应为销售单号别名');
        } finally {
            $this->cleanupOmsOrderSeed($ids);
        }
    }

    public function testOmsOrderShowCarriesSalesCodeAlias(): void
    {
        $suffix = $this->randSuffix();
        $ids = [];
        try {
            $ids = $this->seedOmsOrderWithSalesCode($suffix);

            $resp = (new OmsOrderController())->show(new FakeRequest(), $this->encodeId($ids['oms_id']));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $data = $body['data'] ?? [];
            $this->assertSame($this->encodeId($ids['oms_id']), $data['id'] ?? null);
            $this->assertSame('B7SO-' . $suffix, $data['code'] ?? null, '详情 code 应为销售单号别名');
        } finally {
            $this->cleanupOmsOrderSeed($ids);
        }
    }

    public function testOmsOrderStoreRequiresRealOrderIdNotPhantomCode(): void
    {
        $suffix = $this->randSuffix();
        $orderId = SnowflakeService::generate();
        $customerId = SnowflakeService::generate();
        $omsId = null;
        try {
            // 只带 code（旧幻校验必填项）不带 order_id → 422
            $resp = (new OmsOrderController())->store(new FakeRequest(['code' => 'B7SO-' . $suffix]));
            $this->assertSame(422, (int) ($this->jsonBody($resp)['code'] ?? -1), '缺 order_id 应 422');

            // 带 order_id 不带 code → 成功（code 非列，提交即丢弃）
            $customer = new Customer();
            $customer->id = $customerId;
            $customer->code = 'B7C-' . $suffix;
            $customer->name = '批7客户' . $suffix;
            $customer->save();
            $order = new SalesOrder();
            $order->id = $orderId;
            $order->code = 'B7SO-' . $suffix;
            $order->customer_id = $customerId;
            $order->save();

            $resp = (new OmsOrderController())->store(new FakeRequest(['order_id' => $orderId, 'channel' => 'manual']));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $omsId = isset($body['data']['id']) ? HashidsService::decode((string) $body['data']['id']) : null;
            $this->assertNotNull($omsId, '应返回新建行 id（hashid）');
            $row = OmsOrder::find($omsId);
            $this->assertNotNull($row, '新建 OMS 行应落库');
            $this->assertSame($orderId, (int) $row->order_id);

            // 同 order_id 重复 → 422（uk_order_id 友好化）
            $resp = (new OmsOrderController())->store(new FakeRequest(['order_id' => $orderId]));
            $this->assertSame(422, (int) ($this->jsonBody($resp)['code'] ?? -1), '重复 order_id 应 422');
        } finally {
            if ($omsId) {
                OmsOrder::where('id', $omsId)->delete();
            }
            SalesOrder::where('id', $orderId)->forceDelete();
            Customer::where('id', $customerId)->forceDelete();
        }
    }

    /**
     * 回归：PUT /oms/order/{id} 的 order_id 是下拉下发的 hashid 串。Eloquent 的 integer cast 只在
     * 读取时生效，写库走原值 → MySQL 严格模式报 1366（Incorrect integer value）→ 未捕获
     * QueryException → 真 HTTP 500（无 body.code，走全局异常处理器）。
     */
    public function testOmsOrderUpdateDecodesOrderIdForeignKey(): void
    {
        $suffix = $this->randSuffix();
        $ids = [];
        $otherOrderId = SnowflakeService::generate();
        try {
            $ids = $this->seedOmsOrderWithSalesCode($suffix);

            $other = new SalesOrder();
            $other->id = $otherOrderId;
            $other->code = 'B7SO2-' . $suffix;
            $other->customer_id = $ids['customer_id'];
            $other->save();

            // 合法 hashid：修复前 1366 → 500；修复后边界解码，裸 BIGINT 落库
            $resp = (new OmsOrderController())->update(
                new FakeRequest(['order_id' => $this->encodeId($otherOrderId)]),
                $this->encodeId($ids['oms_id'])
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $this->assertSame(
                $otherOrderId,
                (int) OmsOrder::where('id', $ids['oms_id'])->value('order_id'),
                'order_id 必须以裸 BIGINT 落库，不得把 hashid 串写进列'
            );

            // 垃圾串：边界 422，不得落到 MySQL 1366
            $bad = (new OmsOrderController())->update(
                new FakeRequest(['order_id' => 'not-a-hashid']),
                $this->encodeId($ids['oms_id'])
            );
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 order_id 应 422');
        } finally {
            SalesOrder::where('id', $otherOrderId)->forceDelete();
            $this->cleanupOmsOrderSeed($ids);
        }
    }

    /**
     * 回归：POST /oms/order 的 order_id 同样是下拉下发的 hashid 串。原 validator 写
     * `required|integer|min:1`，hashid 必被 422 挡回（该入口用下拉后 100% 不可用）；
     * 放宽成 required 后必须自己解码 + 覆写（fill 直填 hashid 会 1366 → 500）。
     */
    public function testOmsOrderStoreDecodesOrderIdForeignKey(): void
    {
        $suffix = $this->randSuffix();
        $ids = [];
        $omsId = null;
        $otherOrderId = null;
        try {
            $ids = $this->seedOmsOrderWithSalesCode($suffix);
            // 另建一张销售订单：种子里的那张已被 OMS 行占用（uk_order_id）
            $otherOrderId = SnowflakeService::generate();
            $other = new SalesOrder();
            $other->id = $otherOrderId;
            $other->code = 'B7SO3-' . $suffix;
            $other->customer_id = $ids['customer_id'];
            $other->save();

            $resp = (new OmsOrderController())->store(new FakeRequest([
                'order_id' => $this->encodeId($otherOrderId),
                'channel' => 'manual',
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $omsId = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $otherOrderId,
                (int) OmsOrder::where('id', $omsId)->value('order_id'),
                'order_id 必须以裸 BIGINT 落库，不得把 hashid 串写进列'
            );

            // 垃圾串：边界 422，不得落到 MySQL 1366
            $bad = (new OmsOrderController())->store(new FakeRequest(['order_id' => 'not-a-hashid']));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 order_id 应 422');
        } finally {
            if ($omsId) {
                OmsOrder::where('id', $omsId)->delete();
            }
            if ($otherOrderId !== null) {
                SalesOrder::where('id', $otherOrderId)->forceDelete();
            }
            $this->cleanupOmsOrderSeed($ids);
        }
    }

    /* ======================== mfg bom ======================== */

    /**
     * BOM 列表带出产品名：index 需 with product（前端按关系对象列出名称，而非裸 product_id hashid），
     * 且 encodeIds 递归，嵌套 product.id 也必须是 hashid。
     */
    public function testBomIndexCarriesProductNameAndEncodesNestedId(): void
    {
        $suffix = $this->randSuffix();
        $bomId = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        try {
            $product = new Product();
            $product->id = $productId;
            $product->code = 'B3PD-' . $suffix;
            $product->name = '批3BOM产品' . $suffix;
            $product->save();

            $bom = new MfgBom();
            $bom->id = $bomId;
            $bom->product_id = $productId;
            $bom->code = 'B3BOM-' . $suffix;
            $bom->name = '批3BOM' . $suffix;
            $bom->version = '1.0';
            $bom->status = 0;
            $bom->save();

            $resp = (new BomController())->index(new FakeRequest([
                'page' => 1,
                'limit' => 50,
                'keyword' => 'B3BOM-' . $suffix,
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = null;
            foreach ((array) ($body['data']['list'] ?? []) as $item) {
                if (($item['id'] ?? null) === $this->encodeId($bomId)) {
                    $row = $item;
                    break;
                }
            }
            $this->assertNotNull($row, '新插入的 BOM 应出现在列表');
            $this->assertSame($this->encodeId($productId), $row['product_id'] ?? null);
            $this->assertSame('批3BOM产品' . $suffix, $row['product']['name'] ?? null, 'index 需 with product 带出产品名');
            $this->assertSame($this->encodeId($productId), $row['product']['id'] ?? null, '嵌套 product.id 也要编码');
        } finally {
            MfgBom::where('id', $bomId)->forceDelete();
            Product::where('id', $productId)->forceDelete();
        }
    }

    /* ======================== purchase order update 外键空串 ======================== */

    /**
     * 回归：update 的三个 FK 都在 $fillable 内，fillModelFromRequest 会把请求里的空串/hash 串
     * 直填 BIGINT 列。原先只对「带值」的字段解码覆写，空串既不被覆写也不解码 —— fill 留在模型上的
     * '' 一路 save 到列，MySQL 严格模式报 1366 → 未捕获 QueryException → 真 HTTP 500。
     *
     * 口径同 sales/OrderController::update（同族已修实现）：可选外键（apply_id/warehouse_id，
     * 列 NOT NULL DEFAULT 0）传了即覆写、空串=清空→0；必填外键 supplier_id（列 NOT NULL 无默认值，
     * store 侧 required）空串=不改动，但同样必须回填原值抹掉 fill 带进来的 ''；未传（含显式 null）
     * 一律不动 —— 局部更新不得清空既有外键。
     *
     * 负控（改回缺陷即红）：把空串分支恢复成 `$raw !== null && $raw !== ''` 守卫，第 1 段断言
     * 即以 500（1366 Incorrect integer value）失败；第 8 段（数字 supplier_id）对应批2 去掉的
     * update 侧 `string` 规则，规则恢复即 422 ≠ 0。
     */
    public function testPurchaseOrderUpdateHandlesEmptyAndAbsentForeignKeys(): void
    {
        $suffix = $this->randSuffix();
        $supplierId = SnowflakeService::generate();
        $supplierB = SnowflakeService::generate();
        $warehouseA = SnowflakeService::generate();
        $warehouseB = SnowflakeService::generate();
        $orderId = SnowflakeService::generate();
        try {
            $order = new PurchaseOrder();
            $order->id = $orderId;
            $order->code = 'B9POU-' . $suffix;
            $order->supplier_id = $supplierId;
            $order->apply_id = 0;
            $order->warehouse_id = $warehouseA;
            $order->save();

            // 1. 空串：可选外键清空 → 0（修复前 '' 直落 BIGINT → 1366 → 500）
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['apply_id' => '', 'warehouse_id' => '']),
                $this->encodeId($orderId)
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '空串外键不得 500');
            $this->assertSame(
                0,
                (int) PurchaseOrder::where('id', $orderId)->value('warehouse_id'),
                '空 warehouse_id 应清空为 0（口径同 sales/OrderController::update）'
            );
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '未传的 supplier_id 不得被清零'
            );

            // 2. hashid：解码成裸 BIGINT 落库
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['warehouse_id' => $this->encodeId($warehouseB)]),
                $this->encodeId($orderId)
            );
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $this->assertSame(
                $warehouseB,
                (int) PurchaseOrder::where('id', $orderId)->value('warehouse_id'),
                'hashid 应解码成裸 BIGINT 落库'
            );

            // 3. 不传：既有外键原样保持（局部更新）
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['code' => 'B9POU-' . $suffix]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1));
            $this->assertSame(
                $warehouseB,
                (int) PurchaseOrder::where('id', $orderId)->value('warehouse_id'),
                '未传 warehouse_id 不得改动既有值'
            );

            // 4. 显式 null：同样按「未传」处理，不得以 null 落 NOT NULL 列（1048 → 500）
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['warehouse_id' => null]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '显式 null 不得 500');
            $this->assertSame(
                $warehouseB,
                (int) PurchaseOrder::where('id', $orderId)->value('warehouse_id'),
                '显式 null 不得改动既有值'
            );

            // 5. 必填外键空串 = 不改动（列 NOT NULL 无默认值，清空无意义），同样不得 500
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => '']),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '空 supplier_id 不得 500');
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '空 supplier_id 应维持原值'
            );

            // 6. 必填外键垃圾串：边界 422（与 store 同口径），不得落 0 建无主单
            $bad = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => 'not-a-hashid']),
                $this->encodeId($orderId)
            );
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 supplier_id 应 422');
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '422 分支不得改动既有值'
            );

            // 7. 必填外键显式 null：按「未传」处理，不得以 null 落 NOT NULL 列（1048 → 500）
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => null]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '显式 null supplier_id 不得 500');
            $this->assertSame(
                $supplierId,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '显式 null supplier_id 不得改动既有值'
            );

            // 8. 数字形态的 supplier_id：update 侧 validator 原也挂 `string`（数字 ID 判 422，同族缺口）
            $resp = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => $supplierB]),
                $this->encodeId($orderId)
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '数字 supplier_id 不得 422');
            $this->assertSame(
                $supplierB,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '数字 supplier_id 应原样落库'
            );

            // 9. 数组 422 而非 500（去 string 规则后数组不再被校验器挡下，由 decodeFlexibleId 收口）
            $bad = (new PurchaseOrderController())->update(
                new FakeRequest(['supplier_id' => ['oops']]),
                $this->encodeId($orderId)
            );
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 supplier_id 应 422 而非 500');
            $this->assertSame(
                $supplierB,
                (int) PurchaseOrder::where('id', $orderId)->value('supplier_id'),
                '422 分支不得改动既有值'
            );
        } finally {
            PurchaseOrder::where('id', $orderId)->forceDelete();
        }
    }

    /* ======================== eam 外键双模（数字 ID 不再被 string 挡回） ======================== */

    /**
     * 回归：eam 三个控制器的 ID 校验原写 `required|string`，而 Laravel 的 string 规则即
     * is_string() —— 数字形态的设备/负责人 ID 一律被 422 挡回，与全仓同族（quality 5 个、bi 3 个、
     * sales/oms）的 `required` + decodeFlexibleId 双模不一致。现只留 required，双模判定收口到
     * decodeFlexibleId：数字与 hashid 均落库为裸 BIGINT，垃圾串仍 422（不落 0 建无主行）。
     *
     * 负控（改回缺陷即红）：任一处恢复 `required|string`，对应断言即以 422 失败。
     */
    public function testEamWritePathsAcceptNumericAndHashidEquipmentId(): void
    {
        $suffix = $this->randSuffix();
        $equipmentId = SnowflakeService::generate();
        $assigneeId = SnowflakeService::generate();
        $planIds = [];
        $repairIds = [];
        $taskIds = [];
        try {
            $equipment = new EamEquipment();
            $equipment->id = $equipmentId;
            $equipment->code = 'B9EQ-' . $suffix;
            $equipment->name = '批9设备' . $suffix;
            $equipment->save();

            // 1. 保养计划 store：数字设备 ID（修复前 is_string(900…) 判 422）
            $resp = (new MaintenancePlanController())->store(new FakeRequest([
                'equipment_id' => $equipmentId,
                'name' => '批9保养计划' . $suffix,
                'frequency' => 'monthly',
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 equipment_id 不得 422');
            $planIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamMaintenancePlan::where('id', end($planIds))->value('equipment_id'),
                '数字 equipment_id 应原样落库'
            );

            // 2. 保养计划 store：hashid 仍能过
            $resp = (new MaintenancePlanController())->store(new FakeRequest([
                'equipment_id' => $this->encodeId($equipmentId),
                'name' => '批9保养计划H' . $suffix,
                'frequency' => 'monthly',
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? 'hashid equipment_id 不得 422');
            $planIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamMaintenancePlan::where('id', end($planIds))->value('equipment_id'),
                'hashid 应解码落库'
            );

            // 3. 保养计划 store：垃圾串仍 422（不得退化成 (int)'abc'=0 建无主计划）
            $bad = (new MaintenancePlanController())->store(new FakeRequest([
                'equipment_id' => 'not-a-hashid',
                'name' => '批9保养计划bad' . $suffix,
                'frequency' => 'monthly',
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 equipment_id 应 422');

            // 4. 维修工单 store：数字设备 ID 通过、垃圾串 422
            $resp = (new RepairOrderController())->store(new FakeRequest([
                'code' => 'B9RO-' . $suffix,
                'equipment_id' => $equipmentId,
                'fault_description' => '批9故障' . $suffix,
                'repair_type' => 'corrective',
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 equipment_id 不得 422');
            $repairIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamRepairOrder::where('id', end($repairIds))->value('equipment_id'),
                '数字 equipment_id 应原样落库'
            );
            $bad = (new RepairOrderController())->store(new FakeRequest([
                'code' => 'B9ROb-' . $suffix,
                'equipment_id' => 'not-a-hashid',
                'fault_description' => '批9故障' . $suffix,
                'repair_type' => 'corrective',
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 equipment_id 应 422');

            // 5. 点检任务 store：数字设备/负责人 ID（服务层校验设备存在，故先种设备行）
            $resp = (new EamInspectionController())->store(new FakeRequest([
                'equipment_id' => $equipmentId,
                'task_date' => date('Y-m-d', strtotime('-1 day')),
                'assignee_id' => $assigneeId,
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 equipment_id 不得 422');
            $taskIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamInspectionTask::where('id', end($taskIds))->value('equipment_id')
            );
            $this->assertSame(
                $assigneeId,
                (int) EamInspectionTask::where('id', end($taskIds))->value('assignee_id'),
                '数字 assignee_id 应原样落库'
            );

            // 5b. 点检任务 update：数字 assignee_id 换人（改派）
            $resp = (new EamInspectionController())->update(
                new FakeRequest(['assignee_id' => $equipmentId]),
                $this->encodeId((int) end($taskIds))
            );
            $this->assertSame(0, (int) ($this->jsonBody($resp)['code'] ?? -1), '数字 assignee_id 不得 422');
            $this->assertSame(
                $equipmentId,
                (int) EamInspectionTask::where('id', end($taskIds))->value('assignee_id'),
                '改派应以数字 ID 落库'
            );

            // 6. 点检任务 store：hashid 仍能过、垃圾串 422（换日期避开同日重复校验）
            $resp = (new EamInspectionController())->store(new FakeRequest([
                'equipment_id' => $this->encodeId($equipmentId),
                'task_date' => date('Y-m-d', strtotime('-2 days')),
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? 'hashid equipment_id 不得 422');
            $taskIds[] = HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamInspectionTask::where('id', end($taskIds))->value('equipment_id'),
                'hashid 应解码落库'
            );
            $bad = (new EamInspectionController())->store(new FakeRequest([
                'equipment_id' => 'not-a-hashid',
                'task_date' => date('Y-m-d', strtotime('-3 days')),
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '垃圾 equipment_id 应 422');

            // 7. 扫码点检：数字设备 ID 同样收（原 required|string 会把扫码入口判 422）
            $resp = (new EamInspectionController())->scanExecute(new FakeRequest([
                'equipment_id' => $equipmentId,
                'task_date' => date('Y-m-d', strtotime('-4 days')),
                'items' => [['item_name' => '批9点检项', 'result' => 0]],
            ]));
            $body = $this->jsonBody($resp);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数字 equipment_id 不得 422');
            $taskIds[] = HashidsService::decode((string) ($body['data']['task_id'] ?? ''));
            $this->assertSame(
                $equipmentId,
                (int) EamInspectionTask::where('id', end($taskIds))->value('equipment_id'),
                '扫码生成的临时任务应以数字 ID 落库'
            );
            // 8. 数组入参仍是 422 而非 500：去掉 string 规则后数组不再被校验器挡下，
            //    由 decodeFlexibleId 对非标量返回 null → 422；生产侧不套 (string) 强转，
            //    否则 webman 的 set_error_handler 会把「数组转字符串」警告升级成 ErrorException → 500。
            //    注：phpunit 下没有该 handler，强转只会变 PHP warning（实测 Warnings: 1 而断言仍绿），
            //    故这两条只锁「422」这一外部契约，不构成对强转的负控
            $bad = (new MaintenancePlanController())->store(new FakeRequest([
                'equipment_id' => ['oops'],
                'name' => '批9保养计划arr' . $suffix,
                'frequency' => 'monthly',
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 equipment_id 应 422 而非 500');
            $bad = (new EamInspectionController())->store(new FakeRequest([
                'equipment_id' => $equipmentId,
                'task_date' => date('Y-m-d', strtotime('-5 days')),
                'assignee_id' => ['oops'],
            ]));
            $this->assertSame(422, (int) ($this->jsonBody($bad)['code'] ?? -1), '数组 assignee_id 应 422 而非 500');
        } finally {
            foreach ($taskIds as $id) {
                EamInspectionResult::where('task_id', $id)->delete();
                EamInspectionTask::where('id', $id)->delete();
            }
            foreach ($planIds as $id) {
                EamMaintenancePlan::where('id', $id)->delete();
            }
            foreach ($repairIds as $id) {
                EamRepairOrder::where('id', $id)->delete();
            }
            EamEquipment::where('id', $equipmentId)->delete();
        }
    }

    /* ======================== 关联名收尾批（v1.19.4） ======================== */

    /** 列表里按裸 ID 找行（列表按 id desc 排，新行通常在最前，但不依赖顺序） */
    private function rowById(array $list, int $id): ?array
    {
        foreach ($list as $item) {
            if (($item['id'] ?? null) === $this->encodeId($id)) {
                return $item;
            }
        }

        return null;
    }

    /** 权限树是嵌套结构：按裸 ID 深度优先找节点 */
    private function treeNodeById(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $this->encodeId($id)) {
                return $node;
            }
            $hit = $this->treeNodeById((array) ($node['children'] ?? []), $id);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * 列表行关联名收尾：/finance/bill、/finance/payment 的 bank_account_name，
     * /inventory/transfer 的 from_/to_warehouse_name（两键不得互换），
     * /tms/freight-invoice 的 carrier_name + shipment_code，
     * /system/permission 的 parent_name（顶级留空）。
     * 缺任一键，两端详情抽屉就只剩「<标题> -」。
     */
    public function testListRowsCarryRemainingForeignKeyNames(): void
    {
        $suffix = $this->randSuffix();
        $accountId = SnowflakeService::generate();
        $fromWarehouseId = SnowflakeService::generate();
        $toWarehouseId = SnowflakeService::generate();
        $supplierId = SnowflakeService::generate();
        $carrierId = SnowflakeService::generate();
        $shipmentId = SnowflakeService::generate();
        $billId = SnowflakeService::generate();
        $paymentId = SnowflakeService::generate();
        $transferId = SnowflakeService::generate();
        $invoiceId = SnowflakeService::generate();
        $permParentId = SnowflakeService::generate();
        $permChildId = SnowflakeService::generate();
        try {
            $account = new FinanceBankAccount();
            $account->id = $accountId;
            $account->name = '批4账户' . $suffix;
            $account->save();

            $fromWarehouse = new Warehouse();
            $fromWarehouse->id = $fromWarehouseId;
            $fromWarehouse->code = 'B4WFA' . $suffix;
            $fromWarehouse->name = '批4调出仓' . $suffix;
            $fromWarehouse->save();
            $toWarehouse = new Warehouse();
            $toWarehouse->id = $toWarehouseId;
            $toWarehouse->code = 'B4WTB' . $suffix;
            $toWarehouse->name = '批4调入仓' . $suffix;
            $toWarehouse->save();

            $supplier = new Supplier();
            $supplier->id = $supplierId;
            $supplier->code = 'B4SI' . $suffix;
            $supplier->name = '批4供应商' . $suffix;
            $supplier->save();

            $carrier = new TmsCarrier();
            $carrier->id = $carrierId;
            $carrier->code = 'B4C' . $suffix;
            $carrier->name = '批4承运商' . $suffix;
            $carrier->save();
            $shipment = new TmsShipment();
            $shipment->id = $shipmentId;
            $shipment->code = 'B4S' . $suffix;
            $shipment->save();

            // 1. 票据台账：bank_account_id 的表单字段没有 source（裸 hashid 输入框），抽屉只能靠这个兄弟键
            $bill = new FinanceBill();
            $bill->id = $billId;
            $bill->bill_no = 'B4B' . $suffix;
            $bill->due_date = date('Y-m-d', strtotime('+30 days'));
            $bill->bank_account_id = $accountId;
            $bill->save();
            $body = $this->jsonBody((new FinanceBillController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $billId);
            $this->assertNotNull($row, '新插入的票据应出现在列表');
            $this->assertSame('批4账户' . $suffix, $row['bank_account_name'] ?? null, '票据台账缺 bank_account_name');
            $this->assertSame($this->encodeId($accountId), $row['bank_account_id'] ?? null, 'bank_account_id 仍应是 hashid');

            // 2. 付款单：与既有的 supplier_name 并列产出
            $payment = new FinancePayment();
            $payment->id = $paymentId;
            $payment->code = 'B4P' . $suffix;
            $payment->supplier_id = $supplierId;
            $payment->bank_account_id = $accountId;
            $payment->save();
            $body = $this->jsonBody((new PaymentController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $paymentId);
            $this->assertNotNull($row, '新插入的付款单应出现在列表');
            $this->assertSame('批4账户' . $suffix, $row['bank_account_name'] ?? null, '付款单缺 bank_account_name');
            $this->assertSame('批4供应商' . $suffix, $row['supplier_name'] ?? null, '既有 supplier_name 不得回退');

            // 3. 库存调拨：两个仓库名各归各键（互换即红）
            $transfer = new Transfer();
            $transfer->id = $transferId;
            $transfer->code = 'B4T' . $suffix;
            $transfer->from_warehouse_id = $fromWarehouseId;
            $transfer->to_warehouse_id = $toWarehouseId;
            $transfer->save();
            $body = $this->jsonBody((new TransferController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $transferId);
            $this->assertNotNull($row, '新插入的调拨单应出现在列表');
            $this->assertSame('批4调出仓' . $suffix, $row['from_warehouse_name'] ?? null, '调拨单缺 from_warehouse_name');
            $this->assertSame('批4调入仓' . $suffix, $row['to_warehouse_name'] ?? null, '调拨单缺 to_warehouse_name');

            // 4. 运费发票：承运商名 + 运单号（运单号键是 shipment_code，两端别名表也登记到该键）
            $invoice = new TmsFreightInvoice();
            $invoice->id = $invoiceId;
            $invoice->code = 'B4F' . $suffix;
            $invoice->carrier_id = $carrierId;
            $invoice->shipment_id = $shipmentId;
            $invoice->save();
            $body = $this->jsonBody((new FreightInvoiceController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $invoiceId);
            $this->assertNotNull($row, '新插入的运费发票应出现在列表');
            $this->assertSame('批4承运商' . $suffix, $row['carrier_name'] ?? null, '运费发票缺 carrier_name');
            $this->assertSame('B4S' . $suffix, $row['shipment_code'] ?? null, '运费发票缺 shipment_code');

            // 5. 权限树：父级名（父节点就在同一结果集里，零查询解出）；顶级留空串
            $parent = new AdminPermission();
            $parent->id = $permParentId;
            $parent->name = '批4父权限' . $suffix;
            $parent->slug = 'b4.parent.' . $suffix;
            $parent->type = 1;
            $parent->save();
            $child = new AdminPermission();
            $child->id = $permChildId;
            $child->parent_id = $permParentId;
            $child->name = '批4子权限' . $suffix;
            $child->slug = 'b4.child.' . $suffix;
            $child->type = 2;
            $child->save();
            $body = $this->jsonBody((new PermissionController())->index(new FakeRequest()));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $tree = (array) ($body['data'] ?? []);
            $childNode = $this->treeNodeById($tree, $permChildId);
            $this->assertNotNull($childNode, '新插入的子权限应出现在权限树');
            $this->assertSame('批4父权限' . $suffix, $childNode['parent_name'] ?? null, '权限节点缺 parent_name');
            $this->assertSame($this->encodeId($permParentId), $childNode['parent_id'] ?? null, 'parent_id 仍应是 hashid');
            $parentNode = $this->treeNodeById($tree, $permParentId);
            $this->assertNotNull($parentNode, '新插入的父权限应出现在权限树');
            $this->assertSame('', $parentNode['parent_name'] ?? null, '顶级节点 parent_name 应为空串');
        } finally {
            FinanceBill::where('id', $billId)->forceDelete();
            FinancePayment::where('id', $paymentId)->delete();
            Transfer::where('id', $transferId)->delete();
            TmsFreightInvoice::where('id', $invoiceId)->delete();
            TmsShipment::where('id', $shipmentId)->delete();
            TmsCarrier::where('id', $carrierId)->delete();
            AdminPermission::whereIn('id', [$permChildId, $permParentId])->delete();
            Supplier::where('id', $supplierId)->forceDelete();
            FinanceBankAccount::where('id', $accountId)->delete();
            Warehouse::whereIn('id', [$fromWarehouseId, $toWarehouseId])->forceDelete();
        }
    }

    /* ======================== `_by` 类外键名称产出方（v1.19.6） ======================== */

    /**
     * 真实报文的 Request（不是 FakeRequest）：OpenApi/Webhook 两个 index 读 `$request->get()`，
     * 而 FakeRequest 只实现了 `input()/all()`，`get()` 会走父类未初始化的 $buffer 抛 Error。
     */
    private function queryRequest(string $path, array $query = []): Request
    {
        $qs = $query === [] ? '' : '?' . http_build_query($query);

        return new Request("GET {$path}{$qs} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /**
     * 9 个外键的行内名称兄弟键（`<列名>_name`）—— 9 列 / 9 端点（B7 的 8 列 `_by`/`assigned_to` + B7b 的 `erp_hr_perf_plan.created_by`）；名称键 4 个（approved_name/audited_name/assigned_name/created_name）在 9 个端点上逐个断言。
     * 缺这个兄弟键，两端详情抽屉里这些字段就只剩「审批人ID -」这类占位（裸外键不跳过）。
     *
     * 名称源逐列核过，都是 `erp_admin_user.real_name`（**不是** `erp_hr_employee`）：写入方各写
     * `request->adminId` —— ExpenseController:239、RmaController:286、InvoiceService 的 $adminId、
     * WmsOutboundService::startPick/startPack、WmsInboundService::startPutaway、
     * OpenApiController:153、WebhookController:172；真库探针同向（非 0 值只在 erp_admin_user 命中、
     * 在 erp_hr_employee 命中 0）。本批只补名称兄弟键，裸外键的值形状一律不动（移动端
     * HarmonyOS 按 `Number(row['assigned_to'])` 回填该键）。
     */
    public function testListRowsCarryActorForeignKeyNames(): void
    {
        $suffix = $this->randSuffix();
        $adminId = SnowflakeService::generate();
        $warehouseId = SnowflakeService::generate();
        $expenseId = SnowflakeService::generate();
        $zeroExpenseId = SnowflakeService::generate();
        $rmaId = SnowflakeService::generate();
        $invoiceId = SnowflakeService::generate();
        $pickId = SnowflakeService::generate();
        $packId = SnowflakeService::generate();
        $putawayId = SnowflakeService::generate();
        $appId = SnowflakeService::generate();
        $subId = SnowflakeService::generate();
        $planId = SnowflakeService::generate();
        $zeroPlanId = SnowflakeService::generate();
        $actorName = '批7办理人' . $suffix;
        try {
            $admin = new AdminUser();
            $admin->id = $adminId;
            $admin->username = 'b7admin' . $suffix;
            $admin->password = 'x';
            $admin->real_name = $actorName;
            $admin->save();

            $warehouse = new Warehouse();
            $warehouse->id = $warehouseId;
            $warehouse->code = 'B7W' . $suffix;
            $warehouse->name = '批7仓' . $suffix;
            $warehouse->save();

            // 1. /finance/expense 的 approved_by（该键已在 ID_FIELDS 里，值仍是 hashid）
            $expense = new FinanceExpense();
            $expense->id = $expenseId;
            $expense->code = 'B7E' . $suffix;
            $expense->apply_user_id = $adminId;
            $expense->account_id = $adminId;
            $expense->approved_by = $adminId;
            $expense->save();
            // 外键 0（未审批）：名称落空串，不是 '-'，也不能崩
            $zeroExpense = new FinanceExpense();
            $zeroExpense->id = $zeroExpenseId;
            $zeroExpense->code = 'B7Z' . $suffix;
            $zeroExpense->apply_user_id = 0;
            $zeroExpense->account_id = 0;
            $zeroExpense->approved_by = 0;
            $zeroExpense->save();
            $body = $this->jsonBody((new ExpenseController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $expenseId);
            $this->assertNotNull($row, '新插入的费用单应出现在列表');
            $this->assertSame($actorName, $row['approved_name'] ?? null, '费用单缺 approved_name');
            $this->assertSame($this->encodeId($adminId), (string) ($row['approved_by'] ?? ''), 'approved_by 仍应是 hashid');
            $zeroRow = $this->rowById((array) ($body['data']['list'] ?? []), $zeroExpenseId);
            $this->assertNotNull($zeroRow, '外键为 0 的费用单应出现在列表');
            $this->assertSame('', $zeroRow['approved_name'] ?? null, '外键 0 时 approved_name 应为空串');

            // 2. /oms/rma 的 approved_by —— 该键不在 ID_FIELDS 里，值仍是裸雪花ID
            $rma = new OmsRma();
            $rma->id = $rmaId;
            $rma->code = 'B7R' . $suffix;
            $rma->order_id = $adminId;
            $rma->customer_id = $adminId;
            $rma->approved_by = $adminId;
            $rma->save();
            $body = $this->jsonBody((new RmaController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $rmaId);
            $this->assertNotNull($row, '新插入的退换货单应出现在列表');
            $this->assertSame($actorName, $row['approved_name'] ?? null, '退换货单缺 approved_name');
            $this->assertSame((string) $adminId, (string) ($row['approved_by'] ?? ''), 'approved_by 应仍是裸雪花ID（本批不改值形状）');

            // 3. /finance/invoice 的 audited_by（在 HEADER_ID_FIELDS 里，值仍是 hashid）
            $invoice = new FinanceInvoice();
            $invoice->id = $invoiceId;
            $invoice->invoice_no = 'B7I' . $suffix;
            $invoice->audited_by = $adminId;
            $invoice->save();
            $body = $this->jsonBody((new InvoiceController())->index(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $invoiceId);
            $this->assertNotNull($row, '新插入的发票应出现在列表');
            $this->assertSame($actorName, $row['audited_name'] ?? null, '发票缺 audited_name');
            $this->assertSame($this->encodeId($adminId), (string) ($row['audited_by'] ?? ''), 'audited_by 仍应是 hashid');

            // 4/5/6. 三个 WMS 作业单的 assigned_to（都不在 encodeIds 探测范围内，值仍是裸雪花ID）
            $tasks = [
                ['/admin/v1/wms/pick-task', new WmsPickTask(), $pickId, 'B7PK'],
                ['/admin/v1/wms/pack-task', new WmsPackTask(), $packId, 'B7PA'],
                ['/admin/v1/wms/putaway-task', new WmsPutawayTask(), $putawayId, 'B7PT'],
            ];
            $controllers = [new PickController(), new PackController(), new PutawayController()];
            foreach ($tasks as $i => [$endpoint, $task, $taskId, $codePrefix]) {
                $task->id = $taskId;
                $task->code = $codePrefix . $suffix;
                $task->warehouse_id = $warehouseId;
                $task->assigned_to = $adminId;
                $task->save();
                $body = $this->jsonBody($controllers[$i]->index(new FakeRequest(['page' => 1, 'limit' => 50])));
                $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
                $row = $this->rowById((array) ($body['data']['list'] ?? []), $taskId);
                $this->assertNotNull($row, $endpoint . ' 新插入的作业单应出现在列表');
                $this->assertSame($actorName, $row['assigned_name'] ?? null, $endpoint . ' 缺 assigned_name');
                $this->assertSame((string) $adminId, (string) ($row['assigned_to'] ?? ''), $endpoint . ' assigned_to 应仍是裸雪花ID（移动端 Number() 回填依赖它）');
            }

            // 7. /openapi/app 的 created_by —— 两个 index 走 $request->get()，故用真实报文
            $app = new OpenApiApp();
            $app->id = $appId;
            $app->app_name = '批7应用' . $suffix;
            $app->app_key = 'ak_b7' . $suffix;
            $app->created_by = $adminId;
            $app->save();
            $body = $this->jsonBody((new OpenApiController())->index($this->queryRequest('/admin/v1/openapi/app', ['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $appId);
            $this->assertNotNull($row, '新插入的开放平台应用应出现在列表');
            $this->assertSame($actorName, $row['created_name'] ?? null, '开放平台应用缺 created_name');
            $this->assertSame((string) $adminId, (string) ($row['created_by'] ?? ''), 'created_by 应仍是裸雪花ID');

            // 8. /webhook/subscription 的 created_by（行上还有 with('app') 带出的 app_name）
            $sub = new WebhookSubscription();
            $sub->id = $subId;
            $sub->app_id = $appId;
            $sub->event = ['order.created'];
            $sub->target_url = 'https://example.test/b7/' . $suffix;
            $sub->created_by = $adminId;
            $sub->save();
            $body = $this->jsonBody((new WebhookController())->index($this->queryRequest('/admin/v1/webhook/subscription', ['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $subId);
            $this->assertNotNull($row, '新插入的 Webhook 订阅应出现在列表');
            $this->assertSame($actorName, $row['created_name'] ?? null, 'Webhook 订阅缺 created_name');
            $this->assertSame((string) $adminId, (string) ($row['created_by'] ?? ''), 'created_by 应仍是裸雪花ID');
            $this->assertSame('批7应用' . $suffix, $row['app_name'] ?? null, '预加载 app 关系仍应带出 app_name');
            $this->assertArrayNotHasKey('app', $row, '预加载的 app 关系不应随行下发');

            // 9. /hr/perf/plan 的 created_by（B7b）—— 该键在 encodeIds 白名单里，值仍是 hashid。
            // 名称源取 erp_admin_user.real_name：planStore 落的是 $request->adminId，
            // DDL 注释本批已改齐（install.sql:2463 现为 erp_admin_user.id），二者一致。
            $plan = new HrPerfPlan();
            $plan->id = $planId;
            $plan->template_id = $adminId; // 表无外键约束，模板名落空即可（本例只验创建人名）
            $plan->period_start = '2026-01-01';
            $plan->period_end = '2026-03-31';
            $plan->created_by = $adminId;
            $plan->save();
            $zeroPlan = new HrPerfPlan();
            $zeroPlan->id = $zeroPlanId;
            $zeroPlan->template_id = 0;
            $zeroPlan->period_start = '2026-01-01';
            $zeroPlan->period_end = '2026-03-31';
            $zeroPlan->created_by = 0;
            $zeroPlan->save();
            $body = $this->jsonBody((new PerformanceController())->planIndex(new FakeRequest(['page' => 1, 'limit' => 50])));
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '');
            $row = $this->rowById((array) ($body['data']['list'] ?? []), $planId);
            $this->assertNotNull($row, '新插入的考核批次应出现在列表');
            $this->assertSame($actorName, $row['created_name'] ?? null, '考核批次缺 created_name');
            $this->assertSame($this->encodeId($adminId), (string) ($row['created_by'] ?? ''), 'created_by 仍应是 hashid');
            $zeroRow = $this->rowById((array) ($body['data']['list'] ?? []), $zeroPlanId);
            $this->assertNotNull($zeroRow, '创建人为 0 的考核批次应出现在列表');
            $this->assertSame('', $zeroRow['created_name'] ?? null, '创建人 0 时 created_name 应为空串');
        } finally {
            HrPerfPlan::whereIn('id', [$planId, $zeroPlanId])->delete();
            // 用 SoftDeletes 的模型必须 forceDelete：delete() 只置 deleted_at，行仍在表里（本文件其余
            // 清理块同款，见 :179/:274 等）；withTrashed() 只影响查询、不改变 delete() 的软删语义
            FinanceExpense::whereIn('id', [$expenseId, $zeroExpenseId])->forceDelete();
            OmsRma::where('id', $rmaId)->delete();
            FinanceInvoice::where('id', $invoiceId)->forceDelete();
            WmsPickTask::where('id', $pickId)->delete();
            WmsPackTask::where('id', $packId)->delete();
            WmsPutawayTask::where('id', $putawayId)->delete();
            WebhookSubscription::where('id', $subId)->delete();
            OpenApiApp::where('id', $appId)->forceDelete();
            Warehouse::where('id', $warehouseId)->forceDelete();
            AdminUser::where('id', $adminId)->forceDelete();
        }
    }
}
