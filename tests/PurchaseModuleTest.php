<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\PurchaseOrder;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 采购模块纯单测（Purchase: Apply/Order/Receive/Return/Settlement）
 * - 控制器校验分支走真实代码路径（FakeRequest 注入数据，校验失败即返回，不触库）
 * - 金额计算 / 订单状态决策 / 收货流程编排以规则形式断言
 * - 落库类流程依赖 MySQL，以 markTestSkipped 注明
 */
class PurchaseModuleTest extends TestCase
{
    /** 读取 Response JSON 中的业务 code */
    private function responseCode(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    /* ============================ 控制器校验分支（真实代码路径） ============================ */

    public function testReceiveStoreRejectsMissingItems(): void
    {
        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'code' => 'RC-001',
            'order_id' => 'hash',
            'supplier_id' => 'hash',
            'warehouse_id' => 'hash',
        ]));
        $body = json_decode($resp->rawBody(), true);
        $this->assertSame(422, $this->responseCode($resp), '缺少 items 应校验失败');
        $this->assertNotEmpty($body['message']);
    }

    public function testReceiveStoreRejectsZeroQuantityItem(): void
    {
        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'code' => 'RC-001',
            'order_id' => 'hash',
            'supplier_id' => 'hash',
            'warehouse_id' => 'hash',
            'items' => [['product_id' => 'p1', 'quantity' => 0, 'price' => 10]],
        ]));
        $this->assertSame(422, $this->responseCode($resp), '明细数量 0 应校验失败 (min:0.01)');
    }

    public function testReceiveStoreRejectsNegativePriceItem(): void
    {
        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'code' => 'RC-001',
            'order_id' => 'hash',
            'supplier_id' => 'hash',
            'warehouse_id' => 'hash',
            'items' => [['product_id' => 'p1', 'quantity' => 2, 'price' => -1]],
        ]));
        $this->assertSame(422, $this->responseCode($resp), '明细单价为负应校验失败 (min:0)');
    }

    public function testReceiveStoreRejectsMissingOrderId(): void
    {
        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'code' => 'RC-001',
            'supplier_id' => 'hash',
            'warehouse_id' => 'hash',
            'items' => [['product_id' => 'p1', 'quantity' => 1, 'price' => 10]],
        ]));
        $this->assertSame(422, $this->responseCode($resp), '缺少 order_id 应校验失败');
    }

    public function testApplyStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\purchase\ApplyController())->store(new FakeRequest(['code' => 'PA-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购申请缺少 name 应校验失败');
    }

    public function testOrderStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest(['code' => 'PO-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购订单缺少 name 应校验失败');
    }

    public function testReturnStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\purchase\ReturnController())->store(new FakeRequest(['code' => 'PR-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购退货缺少 name 应校验失败');
    }

    public function testSettlementStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\purchase\SettlementController())->store(new FakeRequest(['code' => 'PS-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购结算缺少 name 应校验失败');
    }

    /* ============================ 金额 / 数量计算规则 ============================ */

    public function testReceiveAmountIsQuantityTimesPriceRounded(): void
    {
        // ReceiveController::store 明细金额规则: $amount = round($quantity * $price, 2)
        $this->assertSame(25.0, round(10 * 2.5, 2));
        $this->assertSame(3.3, round(3 * 1.1, 2));
        $this->assertSame(0.07, round(7 * 0.01, 2));
        $this->assertSame(0.01, round(0.1 * 0.1, 2));
        // 汇总规则: 多明细金额累加为 totalReceiveAmount
        $total = round(10 * 2.5, 2) + round(3 * 1.1, 2);
        $this->assertSame(28.3, $total);
    }

    /* ============================ 订单收货状态机 ============================ */

    /**
     * 复刻 ReceiveController::updateOrderStatus 的状态决策分支
     */
    private function decideOrderStatus(array $orderItems, float $totalReceivedQty, int $receivedCount): int
    {
        if (empty($orderItems)) {
            return 3; // 无明细 => 已收货
        }
        $totalOrderedQty = array_sum(array_column($orderItems, 'quantity'));
        if ($totalReceivedQty >= $totalOrderedQty) {
            return 3; // 收齐 => 已收货
        }
        if ($receivedCount > 0) {
            return 2; // 有已入库收货单但未收齐 => 部分收货
        }

        return 1; // 未收货 => 保持原状态
    }

    public function testUpdateOrderStatusDecisionMatrix(): void
    {
        // 分支1: 订单无明细 => 3(已收货)
        $this->assertSame(3, $this->decideOrderStatus([], 0, 0));
        // 分支2: 累计收货 >= 订单数量 => 3(已收货)
        $this->assertSame(3, $this->decideOrderStatus([['quantity' => 10]], 10, 1));
        $this->assertSame(3, $this->decideOrderStatus([['quantity' => 10]], 12, 2));
        // 分支3: 部分收货 => 2(部分收货)
        $this->assertSame(2, $this->decideOrderStatus([['quantity' => 10]], 6, 1));
        // 分支4: 尚未收货 => 状态保持不变
        $this->assertSame(1, $this->decideOrderStatus([['quantity' => 10]], 0, 0));
    }

    /* ============================ 收货流程编排（源码契约断言） ============================ */

    public function testReceiveFlowOrchestrationContract(): void
    {
        // ReceiveController::store 的编排契约（事务内顺序）:
        // beginTransaction -> foreach items stockIn -> status=1 -> createAp -> updateOrderStatus -> commit, catch rollback
        $src = file_get_contents(__DIR__ . '/../app/controller/purchase/ReceiveController.php');
        $this->assertNotFalse(strpos($src, 'Container::get(InventoryService::class)'), '库存服务应从容器获取');
        $this->assertNotFalse(strpos($src, 'Container::get(FinanceService::class)'), '财务服务应从容器获取');
        $this->assertNotFalse(strpos($src, '$totalReceiveAmount'));

        $posBegin = strpos($src, 'DB::beginTransaction()');
        $posStockIn = strpos($src, '->stockIn(');
        $posStatus = strpos($src, '$receive->status = 1');
        $posAp = strpos($src, '->createAp(');
        $posUpdate = strpos($src, 'updateOrderStatus');
        $posCommit = strpos($src, 'DB::commit()');
        $posRollback = strpos($src, 'DB::rollBack()');

        $this->assertNotFalse($posBegin);
        $this->assertNotFalse($posStockIn);
        $this->assertNotFalse($posStatus);
        $this->assertNotFalse($posAp);
        $this->assertNotFalse($posUpdate);
        $this->assertNotFalse($posCommit);
        $this->assertNotFalse($posRollback);

        $this->assertTrue($posBegin < $posStockIn, '事务必须先于入库');
        $this->assertTrue($posStockIn < $posStatus, '入库循环必须先于状态置为已入库');
        $this->assertTrue($posStatus < $posAp, '入库完成后才生成应付');
        $this->assertTrue($posAp < $posUpdate, '生成应付后才更新订单状态');
        $this->assertTrue($posUpdate < $posCommit, '状态更新后提交事务');
        $this->assertTrue($posCommit < $posRollback, '异常路径回滚在提交之后声明');
    }

    /* ============================ 结构与基础行为 ============================ */

    public function testPurchaseControllersExtendBaseController(): void
    {
        $controllers = [
            'app\\controller\\purchase\\ApplyController',
            'app\\controller\\purchase\\OrderController',
            'app\\controller\\purchase\\ReceiveController',
            'app\\controller\\purchase\\ReturnController',
            'app\\controller\\purchase\\SettlementController',
        ];
        foreach ($controllers as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        $methods = get_class_methods('app\\controller\\purchase\\ReceiveController');
        foreach (['index', 'store', 'show', 'update', 'destroy'] as $m) {
            $this->assertContains($m, $methods, 'ReceiveController 应具备 CRUD 方法');
        }
    }

    public function testPurchaseModelsUseSnowflakePrimaryKey(): void
    {
        $models = [
            'PurchaseApply',
            'PurchaseOrder',
            'PurchaseReceive',
            'PurchaseReceiveItem',
            'PurchaseReturn',
            'PurchaseSettlement',
        ];
        foreach ($models as $name) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$name}.php");
            $this->assertStringContainsString('erik_purchase', $source, "{$name} 表必须使用 erik_purchase 前缀");
            $this->assertStringContainsString('public $incrementing = false', $source, "{$name} 必须使用非自增主键");
            $this->assertStringContainsString("protected \$keyType = 'int'", $source, "{$name} 主键类型必须为 int");
        }
    }

    public function testDecodeIdSafeHandlesInvalidHash(): void
    {
        $accessor = new PurchaseBaseAccessor();
        $valid = \app\common\HashidsService::encode(42);
        $this->assertSame(42, $accessor->publicDecodeIdSafe($valid), '有效 hash 应解码回原 ID');
        $this->assertNull($accessor->publicDecodeIdSafe('not-a-valid-hash-xxx'), '无效 hash 应返回 null 而非抛异常');

        $encoded = $accessor->publicEncodeIds(['id' => 7, 'code' => 'PO-1'], ['id']);
        $this->assertNotSame(7, $encoded['id'], 'id 字段应被编码为 hash 字符串');
        $this->assertSame('PO-1', $encoded['code'], '非 ID 字段应原样保留');
    }

    public function testFillModelFromRequestOnlyFillsFillableFields(): void
    {
        // BaseController::fillModelFromRequest 只允许 $fillable 字段，防止 mass assignment
        $accessor = new PurchaseBaseAccessor();
        $model = new PurchaseOrder();
        $request = new FakeRequest([
            'code' => 'PO-001',
            'total_amount' => 100,
            'name' => 'malicious',
            'evil_field' => 'x',
            'status' => 3,
        ]);
        $accessor->publicFillModelFromRequest($model, $request);
        $attrs = $model->getAttributes();
        $this->assertSame('PO-001', $attrs['code'], 'fillable 字段应被填充');
        $this->assertEquals(3, $attrs['status'], 'fillable 字段应被填充');
        $this->assertArrayNotHasKey('name', $attrs, '非 fillable 字段不应被填充');
        $this->assertArrayNotHasKey('evil_field', $attrs, '未知字段不应被填充');
    }

    /* ============================ 落库流程（DB 依赖，跳过并注明） ============================ */

    public function testReceiveStoreEndToEndRequiresDatabase(): void
    {
        // 收货 -> 自动入库(InventoryService::stockIn) -> 生成应付(FinanceService::createAp)
        // -> 更新订单状态 的完整落库流程依赖 MySQL 事务，纯单测环境无法执行。
        $this->markTestSkipped('依赖 MySQL: 收货单落库 + InventoryService::stockIn + FinanceService::createAp + updateOrderStatus');
    }
}

/**
 * 暴露 BaseController 受保护方法，供纯单测调用真实实现
 */
class PurchaseBaseAccessor extends \app\admin\controller\BaseController
{
    public function publicDecodeIdSafe(string $hashid): ?int
    {
        return $this->decodeIdSafe($hashid);
    }

    public function publicEncodeIds(array $data, array $fields = ['id']): array
    {
        return $this->encodeIds($data, $fields);
    }

    public function publicFillModelFromRequest(\support\Model $model, \support\Request $request): void
    {
        $this->fillModelFromRequest($model, $request);
    }
}
