<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\PurchaseOrder;
use Illuminate\Database\Capsule\Manager as DB;
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

    /**
     * 读取 Response JSON 中的业务 message。
     * 用途是把「422 是被测规则打回的」钉死：同一请求可能有别的规则也在拦（缺 ID、hashid 非法），
     * 只看 code 无法区分，断言会变成恒真的空转。字段名（status/unit…）在中英两版文案里都保留，
     * 故对字段名做子串断言是 locale 无关的。
     */
    private function responseMessage(Response $resp): string
    {
        $body = json_decode($resp->rawBody(), true);

        return (string) ($body['message'] ?? '');
    }

    /** 落库类用例的守卫：无 DB 连接即跳过（本机/CI 未配 TEST_DB_* 时整批跳过而非报错） */
    private function skipIfNoDb(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('依赖 MySQL: ' . $e->getMessage());
        }
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

    /**
     * 小数数量/单价必须能过校验（收货 1.5、单价 12.34 是常规输入）。
     *
     * JSON 体里的数字经 json_decode 是 int/float；gt/gte/lt/lte/between/size 在 Laravel 内核里
     * 都走 Brick\BigNumber::of()，而 brick/math ≥0.14 对 float 入参发 E_DEPRECATED。webman
     * （support/App::run 的 error_reporting(E_ALL) + support/bootstrap.php 里抛异常的
     * set_error_handler）把它升级成 ErrorException → 未捕获 → 500「服务器内部错误」+TraceId。
     * validator() 已在入口按 bc_norm() 口径把 float 规范成十进制串，本用例装同款处理器复验。
     */
    public function testFractionalQuantityAndPricePassRuleComparison(): void
    {
        set_error_handler(static function (int $level, string $message, string $file = '', int $line = 0): bool {
            if (error_reporting() & $level) {
                throw new \ErrorException($message, 0, $level, $file, $line);
            }

            return false;
        });
        try {
            $validator = validator(
                ['items' => [['quantity' => 1.5, 'price' => 12.34]], 'total_amount' => 12.5],
                [
                    'items.*.quantity' => 'required|numeric|min:0.01',
                    'items.*.price' => 'required|numeric|min:0',
                    'total_amount' => 'nullable|numeric|between:0,99999.99',
                ]
            );
            $this->assertTrue($validator->passes(), '小数入参不应因 brick/math 弃用告警中断校验');
            $normalized = $validator->validated();
            $this->assertSame('1.5', $normalized['items'][0]['quantity'], 'float 应按 bc_norm() 口径规范成十进制串');
            $this->assertSame('12.34', $normalized['items'][0]['price']);
            $this->assertSame('12.5', $normalized['total_amount']);
        } finally {
            restore_error_handler();
        }
    }

    public function testApplyStoreRequiresApplicant(): void
    {
        // 表无 name 列（erp_purchase_apply 仅 code/apply_user_id 等，见 install.sql）：
        // 真实守卫是申请人——apply_user_id 缺省取当前登录管理员（AdminAuth 注入 adminId），
        // 两者都没有（无登录态）时落 422「Invalid apply_user_id」
        $resp = (new \app\controller\purchase\ApplyController())->store(new FakeRequest(['code' => 'PA-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购申请无可解析的申请人应校验失败');
    }

    public function testOrderStoreRejectsMissingRequiredFields(): void
    {
        // erp_purchase_order 无 name 列（见 install.sql）；code 由后端 doc_code() 生成（nullable），
        // 故只校验 supplier_id 缺失：hashid 解码失败同样落 422「Invalid supplier_id」
        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest(['code' => 'PO-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购订单缺少 supplier_id 应校验失败');

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest(['supplier_id' => 'hash']));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 supplier_id 非合法 hashid 应被拒');
    }

    public function testReturnStoreRequiresReceiveSupplierWarehouse(): void
    {
        // 表无 name 列：三个 NOT NULL 无默认 FK（receive_id/supplier_id/warehouse_id）
        // 缺失或非法 hashid 一律 422，守卫在 decodeFlexibleId 处
        $resp = (new \app\controller\purchase\ReturnController())->store(new FakeRequest(['code' => 'PR-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购退货缺三个必填 FK 应校验失败');
    }

    public function testSettlementStoreRequiresReceiveAndAmount(): void
    {
        // 表无 name 列：守卫是 receive_id/receipt_payment_id/amount 三条 required
        $resp = (new \app\controller\purchase\SettlementController())->store(new FakeRequest(['code' => 'PS-1']));
        $this->assertSame(422, $this->responseCode($resp), '采购结算缺 receive_id 等必填项应校验失败');
    }

    /**
     * 2026-09 采购写接口的越界输入：文本超列宽（varchar 50/500）落库报 1406、
     * 非日期串落 datetime 列报 1292 —— 两者原先都以 500「服务器内部错误」返回。
     * 校验分支在触库之前失败，故无需 DB。
     */
    public function testPurchaseStoresRejectOverlongTextBeyondColumnWidth(): void
    {
        // 除被测字段外其余入参一律给合法值：否则 422 可能来自别的规则（如缺 ID、hashid 非法），
        // 断言就成了恒真的空转
        $hash = \app\common\HashidsService::encode(1);
        $long = str_repeat('注', 600);

        $resp = (new \app\controller\purchase\ApplyController())->store(new FakeRequest([
            'code' => 'PA-BOUND', 'department' => str_repeat('部', 60),
        ], ['adminId' => 1]));
        $this->assertSame(422, $this->responseCode($resp), '采购申请 department 超 varchar(50) 应 422');

        $resp = (new \app\controller\purchase\ApplyController())->store(new FakeRequest([
            'code' => 'PA-BOUND', 'department' => '采购部', 'remark' => $long,
        ], ['adminId' => 1]));
        $this->assertSame(422, $this->responseCode($resp), '采购申请 remark 超 varchar(500) 应 422');

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'remark' => $long,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 remark 超 varchar(500) 应 422');

        $resp = (new \app\controller\purchase\ReturnController())->store(new FakeRequest([
            'receive_id' => $hash, 'supplier_id' => $hash, 'warehouse_id' => $hash, 'remark' => $long,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购退货 remark 超 varchar(500) 应 422');

        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'order_id' => $hash, 'supplier_id' => $hash, 'warehouse_id' => $hash,
            'items' => [['product_id' => $hash, 'order_item_id' => $hash, 'quantity' => 1, 'price' => 1]],
            'remark' => $long,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购收货 remark 超 varchar(500) 应 422');
    }

    public function testSupplierAssessmentStoreRejectsWrongColumnTypes(): void
    {
        $hash = \app\common\HashidsService::encode(1);
        $base = ['supplier_id' => $hash, 'total_score' => 88];

        $resp = (new \app\controller\purchase\SupplierAssessmentController())->store(
            new FakeRequest($base + ['assessed_at' => 'not-a-date'])
        );
        $this->assertSame(422, $this->responseCode($resp), '评估 assessed_at 非日期应 422');

        $resp = (new \app\controller\purchase\SupplierAssessmentController())->store(
            new FakeRequest($base + ['remark' => str_repeat('注', 600)])
        );
        $this->assertSame(422, $this->responseCode($resp), '评估 remark 超 varchar(500) 应 422');

        // dimensions 是 json 列：非数组入参落库报 3140 Invalid JSON text
        $resp = (new \app\controller\purchase\SupplierAssessmentController())->store(
            new FakeRequest($base + ['dimensions' => 'not-an-array'])
        );
        $this->assertSame(422, $this->responseCode($resp), '评估 dimensions 非数组应 422');
    }

    public function testPurchaseStoresRejectNonDateValues(): void
    {
        $hash = \app\common\HashidsService::encode(1);

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'ordered_at' => 'not-a-date',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 ordered_at 非日期应 422');

        $resp = (new \app\controller\purchase\ReturnController())->store(new FakeRequest([
            'receive_id' => $hash, 'supplier_id' => $hash, 'warehouse_id' => $hash,
            'returned_at' => 'not-a-date',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购退货 returned_at 非日期应 422');
    }

    /**
     * 数值越界：status 落 TINYINT UNSIGNED（1264 Out of range）、金额落 DECIMAL(12,2)
     * （非数值 1265 / 超量程 1264）、明细文本超列宽（1406）——原先都以 500 返回。
     * 全部在触库前被 validator 拦下，无需 DB。
     */
    public function testPurchaseStoresRejectOutOfRangeStatusAndAmount(): void
    {
        $hash = \app\common\HashidsService::encode(1);

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'status' => 5,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 status=5 超语义域(0..4) 应 422');
        $this->assertStringContainsString('status', $this->responseMessage($resp), '应由 status 规则打回');

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'status' => -1,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 status=-1 落 TINYINT UNSIGNED 应 422');

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'total_amount' => 'abc',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 total_amount 非数值应 422');

        $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
            'supplier_id' => $hash, 'total_amount' => '99999999999999',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购订单 total_amount 超 DECIMAL(12,2) 量程应 422');
        $this->assertStringContainsString('total amount', $this->responseMessage($resp), '应由 total_amount 规则打回');

        $resp = (new \app\controller\purchase\ApplyController())->store(new FakeRequest([
            'department' => '采购部', 'status' => 9,
        ], ['adminId' => 1]));
        $this->assertSame(422, $this->responseCode($resp), '采购申请 status=9 超语义域(0..3) 应 422');
        $this->assertStringContainsString('status', $this->responseMessage($resp), '应由 status 规则打回');

        $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest([
            'order_id' => $hash, 'supplier_id' => $hash, 'warehouse_id' => $hash,
            'items' => [[
                'product_id' => $hash, 'order_item_id' => $hash, 'quantity' => 1, 'price' => 1,
                'unit' => str_repeat('单', 21),
            ]],
        ]));
        $this->assertSame(422, $this->responseCode($resp), '采购收货明细 unit 超 varchar(20) 应 422');
        $this->assertStringContainsString('unit', $this->responseMessage($resp), '应由 items.*.unit 规则打回');
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
            'PurchaseApply' => 'purchase_apply',
            'PurchaseOrder' => 'purchase_order',
            'PurchaseReceive' => 'purchase_receive',
            'PurchaseReceiveItem' => 'purchase_receive_item',
            'PurchaseReturn' => 'purchase_return',
            'PurchaseSettlement' => 'purchase_settlement',
        ];
        foreach ($models as $name => $table) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$name}.php");
            $this->assertStringContainsString("protected \$table = '{$table}'", $source, "{$name} 表名应为 {$table}（erp_ 前缀由连接层 config/database.php 施加）");
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

    /**
     * 采购退货 destroy：已出库守卫 + 待出库软删除。
     * 守卫排在口令二次确认之前，故空口令即可区分「被守卫拦下」与「停在口令校验」两支，
     * 无需构造管理员口令。全程在事务内自造行、结束回滚，不依赖演示数据。
     */
    public function testReturnDestroyBlocksIssuedOutAndSoftDeletesPending(): void
    {
        $this->skipIfNoDb();

        // 测试段主键：避开演示数据(4.1e17)与 snowflake(3.77e17)，防止与真实行相互污染
        $id = 900000000000000000 + random_int(1, 999999);
        $hash = \app\common\HashidsService::encode($id);
        $controller = new \app\controller\purchase\ReturnController();
        $request = new FakeRequest([], ['adminId' => 0]);

        DB::beginTransaction();
        try {
            DB::table('purchase_return')->insert([
                'id' => $id, 'code' => 'TEST-PRN-DEL', 'receive_id' => 1,
                'supplier_id' => 1, 'warehouse_id' => 1, 'total_amount' => 0,
                'status' => 1, 'remark' => 'unit-test', 'deleted_at' => null,
            ]);

            // 支线 1：已出库（status=1）是库存/财务凭证 —— 拒绝删除
            $resp = $controller->destroy($request, $hash);
            $guardMessage = (string) (json_decode($resp->rawBody(), true)['message'] ?? '');
            $this->assertSame(422, $this->responseCode($resp), '已出库退货单应拒绝删除');
            $this->assertNotNull(
                DB::table('purchase_return')->where('id', $id)->whereNull('deleted_at')->first(),
                '被守卫拒绝时不应落软删除'
            );

            // 支线 2：待出库（status=0）应放行守卫、停在口令二次确认
            DB::table('purchase_return')->where('id', $id)->update(['status' => 0]);
            $resp = $controller->destroy($request, $hash);
            $passwordMessage = (string) (json_decode($resp->rawBody(), true)['message'] ?? '');
            $this->assertSame(422, $this->responseCode($resp), '空口令应被二次确认拦下');
            $this->assertNotSame($guardMessage, $passwordMessage, '待出库不应命中已出库守卫');

            // 支线 3：软删除真实生效（模型层，无需口令）
            $model = \app\model\PurchaseReturn::find($id);
            $this->assertNotNull($model, 'SoftDeletes 不应过滤未删除行');
            $model->delete();
            $this->assertNotNull(DB::table('purchase_return')->where('id', $id)->first(), '软删除后物理行应保留');
            $this->assertNotNull(DB::table('purchase_return')->where('id', $id)->value('deleted_at'), '软删除后 deleted_at 应有值');
            $this->assertNull(\app\model\PurchaseReturn::find($id), '软删除后模型查询不应返回');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 供应商评分落库：dimensions（json 列）缺 array 强转时，Eloquent 把 PHP 数组直接交给 PDO，
     * 字符串 "Array" 写进 json 列报 3140 —— 建评分接口原先无论传什么入参都 500。
     * 真库 + 事务回滚，按 DB 不可用优雅跳过。
     */
    public function testSupplierAssessmentStorePersistsDimensionsAsJson(): void
    {
        $this->skipIfNoDb();

        $dimensions = ['quality' => 90, 'delivery' => 80];
        DB::beginTransaction();
        try {
            $resp = (new \app\controller\purchase\SupplierAssessmentController())->store(new FakeRequest([
                'supplier_id' => \app\common\HashidsService::encode(1),
                'total_score' => 88,
                'dimensions' => $dimensions,
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, $this->responseCode($resp), '合法入参建评分应成功，实际：' . json_encode($body['message'] ?? ''));

            // 建单用的 snowflake id 由响应回传（hashid），解码后回查
            $id = \app\common\HashidsService::decode((string) ($body['data']['id'] ?? ''));
            $stored = DB::table('supplier_assessment')->where('id', $id)->value('dimensions');
            $this->assertIsString($stored, 'dimensions 应落成 json 文本');
            $this->assertSame($dimensions, json_decode((string) $stored, true), 'dimensions 应原样落库（未强转时落的是 "Array"）');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 采购订单的 FK 出参契约：create 与 show 都必须下发 hashid。
     * 漏编码时下发的是 4.1e17 级雪花整数，前端按数字回传即丢精度（>2^53）→ 回写 422。
     * 真库 + 事务回滚（purchase_order 对其 FK 无外键约束，故可直接用任意 id）。
     */
    public function testOrderResponsesEncodeForeignKeyIdsAsHashids(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $resp = (new \app\controller\purchase\OrderController())->store(new FakeRequest([
                'supplier_id' => \app\common\HashidsService::encode(11),
                'apply_id' => \app\common\HashidsService::encode(22),
                'warehouse_id' => \app\common\HashidsService::encode(33),
            ]));
            $created = json_decode($resp->rawBody(), true)['data'] ?? [];
            $this->assertSame(0, $this->responseCode($resp), '建单应成功');
            foreach (['supplier_id' => 11, 'apply_id' => 22, 'warehouse_id' => 33] as $field => $expected) {
                $this->assertNotSame((string) $expected, (string) ($created[$field] ?? ''), "create 返回的 {$field} 不应是裸数字");
                $this->assertSame($expected, \app\common\HashidsService::decode((string) ($created[$field] ?? '')), "create 返回的 {$field} 应是可解码的 hashid");
            }

            $resp = (new \app\controller\purchase\OrderController())->show(new FakeRequest(), (string) $created['id']);
            $shown = json_decode($resp->rawBody(), true)['data'] ?? [];
            foreach (['supplier_id' => 11, 'apply_id' => 22, 'warehouse_id' => 33] as $field => $expected) {
                $this->assertSame($expected, \app\common\HashidsService::decode((string) ($shown[$field] ?? '')), "show 返回的 {$field} 应是可解码的 hashid");
            }
        } finally {
            DB::rollBack();
        }
    }

    public function testReceiveStoreEndToEndRequiresDatabase(): void
    {
        // 收货 -> 自动入库(InventoryService::stockIn) -> 生成应付(FinanceService::createAp)
        // -> 更新订单状态 的完整落库流程依赖 MySQL 事务，纯单测环境无法执行。
        $this->markTestSkipped('依赖 MySQL: 收货单落库 + InventoryService::stockIn + FinanceService::createAp + updateOrderStatus');
    }

    /**
     * 收货明细的 order_item_id 可缺省：通用前端表单只能选到商品（明细行 hashid 无界面可查），
     * 缺省时后端按 product_id 在本单内反查；本单该商品不唯一（0 行/多行）必须拒绝而非猜测。
     * 全程事务内跑，结束回滚。
     */
    public function testReceiveStoreResolvesOrderItemIdFromProductWhenUnique(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $supplierId = (int) (DB::table('supplier')->orderBy('id')->value('id') ?? 0);
            $warehouseId = (int) (DB::table('warehouse')->orderBy('id')->value('id') ?? 0);
            $productA = (int) (DB::table('product')->orderBy('id')->value('id') ?? 0);
            $productB = (int) (DB::table('product')->orderBy('id')->where('id', '<>', $productA)->value('id') ?? 0);
            if ($supplierId === 0 || $warehouseId === 0 || $productA === 0 || $productB === 0) {
                $this->markTestSkipped('依赖示例数据: supplier/warehouse/product 至少各一条');
            }

            // 自造订单：商品 A 两行（不唯一）、商品 B 一行（唯一）
            $orderId = 900000000000500000 + random_int(1, 999);
            DB::table('purchase_order')->insert([
                'id' => $orderId, 'code' => 'PO-UNIT-' . $orderId, 'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId, 'total_amount' => 0, 'status' => 1,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $lineA = 900000000000600000 + random_int(1, 249);
            $lineA2 = 900000000000600250 + random_int(1, 249);
            $lineB = 900000000000600500 + random_int(1, 249);
            foreach ([[$lineA, $productA], [$lineA2, $productA], [$lineB, $productB]] as [$lid, $pid]) {
                DB::table('purchase_order_item')->insert([
                    'id' => $lid, 'order_id' => $orderId, 'product_id' => $pid, 'quantity' => '10.00',
                    'price' => '5.00', 'amount' => '50.00',
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $payload = [
                'order_id' => \app\common\HashidsService::encode($orderId),
                'supplier_id' => \app\common\HashidsService::encode($supplierId),
                'warehouse_id' => \app\common\HashidsService::encode($warehouseId),
            ];

            // 商品 B 在本单唯一 → 反查成功
            $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest($payload + ['items' => [[
                'product_id' => \app\common\HashidsService::encode($productB), 'quantity' => 2, 'price' => 5,
            ]]]));
            $this->assertSame(0, $this->responseCode($resp), '商品唯一时应能反查明细行并收货成功');
            $receiveId = (int) DB::table('purchase_receive')->where('order_id', $orderId)->orderByDesc('id')->value('id');
            $this->assertSame($lineB, (int) DB::table('purchase_receive_item')->where('receive_id', $receiveId)->value('order_item_id'), '应落到本单该商品的明细行');

            // 商品 A 在本单两行 → 无法判定，422（业务拒绝不再是 500）
            $resp = (new \app\controller\purchase\ReceiveController())->store(new FakeRequest($payload + ['items' => [[
                'product_id' => \app\common\HashidsService::encode($productA), 'quantity' => 1, 'price' => 5,
            ]]]));
            $this->assertSame(422, $this->responseCode($resp), '同商品多行不可猜测，应 422');
            $this->assertStringContainsString('无法确定', $this->responseMessage($resp), '应点明无法确定明细行');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 询价单头字段原先只校验 items：supplier_range/remark 超 varchar(500) 报 1406、
     * require_date 非日期串报 1292、明细 unit 超 varchar(20) / target_price 非数值报 1265，
     * 全被 catch 成 422 并把原始 SQL 文案抛给用户。逐字段断言消息里点的是哪个字段——
     * 只看 422 无法区分是这条规则拦下的还是别的规则。
     */
    public function testRfqStoreAndUpdateBoundHeaderFieldsAgainstColumnWidths(): void
    {
        $hash = \app\common\HashidsService::encode(1);
        $controller = new \app\controller\purchase\RfqController();
        $cases = [
            ['supplier_range' => str_repeat('范', 501), 'field' => 'supplier range'],
            ['remark' => str_repeat('R', 501), 'field' => 'remark'],
            ['require_date' => 'not-a-date', 'field' => 'require date'],
        ];
        foreach ($cases as $case) {
            $field = $case['field'];
            unset($case['field']);
            $resp = $controller->store(new FakeRequest($case + ['items' => [['product_id' => $hash, 'quantity' => 1]]]));
            $this->assertSame(422, $this->responseCode($resp), "{$field} 越界应 422");
            $this->assertStringContainsString($field, $this->responseMessage($resp), "422 应点名字段 {$field}（而非原始 SQL 文案）");
        }

        $items = [['product_id' => $hash, 'quantity' => 1, 'unit' => str_repeat('件', 21), 'target_price' => 'abc']];
        $resp = $controller->store(new FakeRequest(['items' => $items]));
        $this->assertSame(422, $this->responseCode($resp), '明细 unit 超 20 字应 422');
        $this->assertStringContainsString('unit', $this->responseMessage($resp), '422 应点名 unit');

        $resp = $controller->store(new FakeRequest(['items' => [['product_id' => $hash, 'quantity' => 1, 'target_price' => 'abc']]]));
        $this->assertSame(422, $this->responseCode($resp), '明细 target_price 非数值应 422');
        $this->assertStringContainsString('target_price', $this->responseMessage($resp), '422 应点名 target_price');
    }

    /**
     * 更新询价单的 buyer_id 由采购员下拉下发 hashid，而 show() 出参恰是 hashid（编辑表单回显即是它）：
     * 原写法把它当字符串直写 buyer_id BIGINT UNSIGNED → 1366，即「改采购员」每次必失败。
     */
    public function testRfqUpdatePersistsHashidBuyerId(): void
    {
        $this->skipIfNoDb();

        $rfqId = 900000000000820000 + random_int(1, 999);
        $hash = \app\common\HashidsService::encode($rfqId);
        DB::beginTransaction();
        try {
            DB::table('purchase_rfq')->insert([
                'id' => $rfqId, 'rfq_no' => 'RFQ-UNIT-' . $rfqId, 'buyer_id' => 1,
                'supplier_range' => '', 'require_date' => null, 'status' => 0, 'remark' => '', 'deleted_at' => null,
            ]);

            $resp = (new \app\controller\purchase\RfqController())->update(
                new FakeRequest(['buyer_id' => \app\common\HashidsService::encode(7)]),
                $hash
            );
            $this->assertSame(0, $this->responseCode($resp), '改采购员应成功，实际：' . $this->responseMessage($resp));
            $this->assertSame(7, (int) DB::table('purchase_rfq')->where('id', $rfqId)->value('buyer_id'), 'buyer_id 应落解码后的整数');

            // 垃圾串不得静默落回登录管理员（原 store 的 (int)'xx' = 0 → adminId 兜底）
            $resp = (new \app\controller\purchase\RfqController())->update(
                new FakeRequest(['buyer_id' => 'not-a-hashid']),
                $hash
            );
            $this->assertSame(422, $this->responseCode($resp), '无效 buyer_id 应 422');
            $this->assertSame(7, (int) DB::table('purchase_rfq')->where('id', $rfqId)->value('buyer_id'), '422 时不得改写原有采购员');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 报价行金额落 DECIMAL(14,2)、采购订单明细金额只有 DECIMAL(12,2)：单价 × 询价数量
     * 越界原先报 1264 Out of range（用户看到的是原始 SQL）。两处都应在写库前给出可读 422，
     * 且中标被拦下时不得留下「报价已中标 / 询价单已中标」的半成品状态。
     */
    public function testRfqQuoteAndAwardRejectAmountsBeyondColumnWidth(): void
    {
        $this->skipIfNoDb();

        $bigRfq = 900000000000830000 + random_int(1, 499);   // 数量 1000（行金额超报价行列宽）
        $okRfq = 900000000000830500 + random_int(1, 499);    // 数量 100（报价可成，转单超订单列宽）
        $bigItem = $bigRfq + 1000000;
        $okItem = $okRfq + 1000000;
        $encode = fn (int $n): string => \app\common\HashidsService::encode($n);
        DB::beginTransaction();
        try {
            foreach ([[$bigRfq, 1000], [$okRfq, 100]] as [$id, $qty]) {
                DB::table('purchase_rfq')->insert([
                    'id' => $id, 'rfq_no' => 'RFQ-UNIT-' . $id, 'buyer_id' => 1,
                    'supplier_range' => '', 'require_date' => null, 'status' => 1, 'remark' => '', 'deleted_at' => null,
                ]);
                DB::table('purchase_rfq_item')->insert([
                    'id' => $id + 1000000, 'rfq_id' => $id, 'product_id' => 1,
                    'quantity' => (string) $qty, 'unit' => '件', 'target_price' => '1.00',
                ]);
            }

            $quoteStore = new \app\controller\purchase\RfqQuoteController();
            $resp = $quoteStore->store(new FakeRequest([
                'rfq_id' => $encode($bigRfq), 'supplier_id' => $encode(9),
                'items' => [['rfq_item_id' => $encode($bigItem), 'unit_price' => '9999999999.99']],
            ]));
            $this->assertSame(422, $this->responseCode($resp), '行金额超 DECIMAL(14,2) 应 422');
            $this->assertStringContainsString('超出上限', $this->responseMessage($resp), '应给出可读上限说明');

            // 恰好列宽内应放行（规则不得误杀）
            $resp = $quoteStore->store(new FakeRequest([
                'rfq_id' => $encode($okRfq), 'supplier_id' => $encode(9),
                'items' => [['rfq_item_id' => $encode($okItem), 'unit_price' => '9999999999.99']],
            ]));
            $this->assertSame(0, $this->responseCode($resp), '列宽内的报价应放行，实际：' . $this->responseMessage($resp));
            $quoteId = (int) \app\common\HashidsService::decode((string) (json_decode($resp->rawBody(), true)['data']['id'] ?? ''));

            // 中标转单：DECIMAL(12,2) 装不下 → 可读 422，且无半成品状态
            $resp = (new \app\controller\purchase\RfqController())->award(
                new FakeRequest(['quote_id' => $encode($quoteId)], ['adminId' => 1]),
                $encode($okRfq)
            );
            $this->assertSame(422, $this->responseCode($resp), '转单金额超订单明细列宽应 422');
            $this->assertStringContainsString('采购订单', $this->responseMessage($resp), '应点明是采购订单列宽受限');
            $this->assertSame(1, (int) DB::table('purchase_rfq')->where('id', $okRfq)->value('status'), '中标失败不应改写询价单状态');
            $this->assertSame(0, (int) DB::table('purchase_rfq_quote')->where('id', $quoteId)->value('awarded'), '中标失败不应标记报价中标');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 报价明细行的 rfq_item_id 同收货的 order_item_id：没有任何界面能查到（选中询价单也不带出明细），
     * 缺省时应按 product_id 在本询价单内反查；本单该商品多行即拒绝而非猜测。
     */
    public function testRfqQuoteResolvesRfqItemIdFromProductWhenUnique(): void
    {
        $this->skipIfNoDb();

        $uniqueRfq = 900000000000840000 + random_int(1, 499);   // 1 条明细，商品唯一
        $dupRfq = 900000000000840500 + random_int(1, 499);      // 同商品 2 条明细，无法判定
        $encode = fn (int $n): string => \app\common\HashidsService::encode($n);
        DB::beginTransaction();
        try {
            foreach ([$uniqueRfq, $dupRfq] as $id) {
                DB::table('purchase_rfq')->insert([
                    'id' => $id, 'rfq_no' => 'RFQ-UNIT-' . $id, 'buyer_id' => 1,
                    'supplier_range' => '', 'require_date' => null, 'status' => 1, 'remark' => '', 'deleted_at' => null,
                ]);
            }
            $uniqueLine = $uniqueRfq + 1000000;
            DB::table('purchase_rfq_item')->insert([
                ['id' => $uniqueLine, 'rfq_id' => $uniqueRfq, 'product_id' => 1, 'quantity' => '10.00', 'unit' => '件', 'target_price' => '1.00'],
                ['id' => $dupRfq + 1000001, 'rfq_id' => $dupRfq, 'product_id' => 1, 'quantity' => '10.00', 'unit' => '件', 'target_price' => '1.00'],
                ['id' => $dupRfq + 1000002, 'rfq_id' => $dupRfq, 'product_id' => 1, 'quantity' => '20.00', 'unit' => '件', 'target_price' => '1.00'],
            ]);
            $controller = new \app\controller\purchase\RfqQuoteController();

            // 唯一 → 反查成功，且落库的 rfq_item_id 指向本单该商品那一行
            $resp = $controller->store(new FakeRequest([
                'rfq_id' => $encode($uniqueRfq), 'supplier_id' => $encode(9),
                'items' => [['product_id' => $encode(1), 'unit_price' => '5.00']],
            ]));
            $this->assertSame(0, $this->responseCode($resp), '商品唯一时应能反查询价明细行并登记报价，实际：' . $this->responseMessage($resp));
            $quoteId = (int) DB::table('purchase_rfq_quote')->where('rfq_id', $uniqueRfq)->value('id');
            $this->assertSame($uniqueLine, (int) DB::table('purchase_rfq_quote_item')->where('quote_id', $quoteId)->value('rfq_item_id'), '报价行应落在本单该商品的询价明细行');

            // 同商品多行 → 无法判定，422
            $resp = $controller->store(new FakeRequest([
                'rfq_id' => $encode($dupRfq), 'supplier_id' => $encode(9),
                'items' => [['product_id' => $encode(1), 'unit_price' => '5.00']],
            ]));
            $this->assertSame(422, $this->responseCode($resp), '同商品多行不可猜测，应 422');
            $this->assertStringContainsString('无法确定', $this->responseMessage($resp), '应点明无法确定询价明细行');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 采购模块每条资源路由都要有对应权限种子（erp_admin_permission）：
     * 路由在、种子缺时，非超管角色拿到的是 403 而页面照常显示——「功能在、权限缺」的静默不可用。
     * 询价单 / 供应商报价 / 供应商评估三个资源整块缺种子的情形发生过，故钉成用例。
     * AdminPermission：`{verb}.admin/purchase/x` 精确命中，或 `any.admin/purchase/x` 覆盖任意方法。
     */
    public function testEveryPurchaseResourceRouteHasPermissionSeed(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../config/route.php');
        preg_match_all("#Route::resource\('(/purchase/[a-z-]+)'#", $routes, $m);
        $paths = array_values(array_unique($m[1]));
        $this->assertNotEmpty($paths, '未解析到采购资源路由：route.php 写法变了，正则需同步');

        $sql = (string) file_get_contents(__DIR__ . '/../database/install.sql');
        preg_match_all("/'((?:get|post|put|delete|any)\.admin\/purchase\/[a-z-]+)'/", $sql, $sm);
        $seeded = array_unique($sm[1]);

        foreach ($paths as $path) {
            foreach (['get', 'post', 'put', 'delete'] as $verb) {
                $slug = "{$verb}.admin{$path}";
                $this->assertTrue(
                    in_array($slug, $seeded, true) || in_array("any.admin{$path}", $seeded, true),
                    "采购路由 {$path} 的 {$verb} 缺少权限种子（{$slug}）——非超管角色调用会 403"
                );
            }
        }
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
