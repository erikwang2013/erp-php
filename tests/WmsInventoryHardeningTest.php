<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\common\HashidsService;
use app\common\SnowflakeService;
use app\controller\inventory\InventoryController;
use app\controller\wms\PutawayController;
use app\model\Inventory;
use app\model\WmsAsn;
use app\model\WmsPutawayTask;
use app\model\WmsReceiving;
use app\service\wms\WmsInboundService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * WMS 收货/上架幂等、库存台账只读、SKU 入库锁序的回归测试。
 *
 * 源码断言（无需数据库）锁定「锁先行、校验齐全、写路由已移除」；
 * 标 *WithRealDatabase 的用例在 TEST_DB_* 未配置时优雅跳过，事务内执行并回滚。
 */
class WmsInventoryHardeningTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../' . $relativePath;
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        return $source;
    }

    /** 截取 $signature 起、到下一个方法签名前的源码 */
    private function methodBody(string $source, string $signature, string $nextSignature): string
    {
        $start = strpos($source, $signature);
        $this->assertNotFalse($start, "未找到方法: {$signature}");
        $end = strpos($source, $nextSignature, $start);
        $this->assertNotFalse($end, "未找到方法边界: {$nextSignature}");

        return substr($source, $start, $end - $start);
    }

    private function assertOrder(string $haystack, string $first, string $second, string $message): void
    {
        $firstPos = strpos($haystack, $first);
        $secondPos = strpos($haystack, $second);
        $this->assertNotFalse($firstPos, "未找到: {$first}");
        $this->assertNotFalse($secondPos, "未找到: {$second}");
        $this->assertLessThan($secondPos, $firstPos, $message);
    }

    /** 断言 $needles 在 $haystack 中按给定顺序出现，且都先于 $boundary */
    private function assertOrderedBefore(string $haystack, array $needles, string $boundary, string $message): void
    {
        $lastPos = -1;
        foreach ($needles as $needle) {
            $pos = strpos($haystack, $needle);
            $this->assertNotFalse($pos, "未找到: {$needle}");
            $this->assertGreaterThan($lastPos, $pos, "排序顺序错乱: {$needle}");
            $lastPos = $pos;
        }
        $boundaryPos = strpos($haystack, $boundary);
        $this->assertNotFalse($boundaryPos, "未找到: {$boundary}");
        $this->assertLessThan($boundaryPos, $lastPos, $message);
    }

    public function testStartReceivingLocksAsnBeforeStatusCheck(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/wms/WmsInboundService.php'),
            'public function startReceiving',
            'public function completeReceiving'
        );

        $this->assertStringContainsString(
            "WmsAsn::query()->where('id', \$asnId)->lockForUpdate()",
            $body,
            '开始收货须先对 ASN 加行锁，避免并发创建两个收货任务'
        );
        $this->assertOrder(
            $body,
            'lockForUpdate()',
            '$asn->status',
            '必须先加行锁再检查 ASN 状态'
        );
    }

    public function testCompleteReceivingLocksAndValidatesAgainstAsn(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/wms/WmsInboundService.php'),
            'public function completeReceiving',
            'public function confirmPutaway'
        );

        $this->assertStringContainsString(
            "WmsReceiving::query()->where('id', \$receivingId)->lockForUpdate()",
            $body,
            '完成收货须先对收货单加行锁，双击时后到事务重读状态'
        );
        $this->assertStringContainsString(
            "WmsAsn::query()->where('id', \$receiving->asn_id)->lockForUpdate()",
            $body,
            '完成收货须对 ASN 加行锁'
        );
        $this->assertOrder(
            $body,
            'lockForUpdate()',
            '$receiving->status !== 1',
            '必须先加行锁再检查收货单状态'
        );

        $this->assertStringContainsString('$asnItems', $body, '实收行必须按 ASN 明细索引校验归属');
        $this->assertStringContainsString('expected_quantity', $body, '必须校验 ASN 预期数量');
        $this->assertStringContainsString('累计实收', $body, '超收须返回可读业务异常');
        $this->assertStringContainsString('存在多行实收', $body, '同一商品/SKU 多行实收须拒绝歧义');
        $this->assertStringContainsString('不在ASN明细中', $body, '未知商品/SKU 须拒绝');
        $this->assertStringContainsString('实收数量必须大于0', $body, '实收数量须为正数');
    }

    public function testConfirmPutawayLocksTaskBeforeStatusCheck(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/wms/WmsInboundService.php'),
            'public function confirmPutaway',
            'public function startPutaway'
        );

        $this->assertStringContainsString(
            "WmsPutawayTask::query()->where('id', \$putawayId)->lockForUpdate()",
            $body,
            '确认上架须先对上架任务加行锁，避免重复 stockIn'
        );
        $this->assertOrder(
            $body,
            'lockForUpdate()',
            '$putaway->status !== 1',
            '必须先加行锁再检查上架任务状态'
        );
    }

    public function testReceivingCompleteValidatesItemRows(): void
    {
        $source = $this->source('app/controller/wms/ReceivingController.php');

        $this->assertStringContainsString("'items.*.product_id' => 'required'", $source);
        $this->assertStringContainsString("'items.*.received_quantity' => 'required|numeric|gt:0'", $source);
        $this->assertStringContainsString("'items.*.sku_id' => 'nullable'", $source);
        $this->assertStringContainsString("'items.*.to_location_id' => 'nullable'", $source);
        $this->assertStringContainsString("'items.*.batch_code' => 'nullable|string'", $source);
        $this->assertStringContainsString('decodeItemIds', $source, 'ID 解码仍走 decodeItemIds');
    }

    public function testInventoryFlowRoutesAreReadOnly(): void
    {
        $route = $this->source('config/route.php');

        $this->assertStringContainsString("Route::any('/inventory/flow'", $route, '流水列表接口保留');
        $this->assertStringContainsString("Route::get('/inventory/flow/{id}'", $route, '流水详情接口保留');
        $this->assertStringNotContainsString("Route::put('/inventory/flow/{id}'", $route, '流水更新路由必须移除');
        $this->assertStringNotContainsString("Route::delete('/inventory/flow/{id}'", $route, '流水删除路由必须移除');
        $this->assertStringNotContainsString("FlowController::class, 'update'", $route);
        $this->assertStringNotContainsString("FlowController::class, 'destroy'", $route);
    }

    public function testFlowControllerHasNoWriteMethods(): void
    {
        $source = $this->source('app/controller/inventory/FlowController.php');

        $this->assertStringContainsString('public function index(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringNotContainsString('function store(', $source, '流水不允许创建');
        $this->assertStringNotContainsString('function update(', $source, '流水不允许改写');
        $this->assertStringNotContainsString('function destroy(', $source, '流水不允许删除');
    }

    public function testInventoryDestroyRejectsNonZeroQuantity(): void
    {
        $source = $this->source('app/controller/inventory/InventoryController.php');
        $start = strpos($source, 'public function destroy');
        $this->assertNotFalse($start);
        $body = substr($source, $start);

        $this->assertStringContainsString(
            "bccomp(bc_norm(\$item->quantity), '0', 6)",
            $body,
            '删除前必须校验数量为 0'
        );
        $this->assertStringContainsString("->where('quantity', 0)->delete()", $body, '条件删除防并发入库后误删');
        $this->assertOrder($body, 'bccomp(bc_norm($item->quantity)', 'confirmPassword', '数量校验应先于删除动作');
        $this->assertStringContainsString('stocktake or inventory adjustment', $body, '422 提示应引导走盘点/调整');
    }

    public function testStockInLocksAllSkuRowsInIdOrderBeforeTargetRow(): void
    {
        $source = $this->source('app/service/inventory/InventoryService.php');
        $body = $this->methodBody($source, 'public function stockIn', 'public function stockOut');

        $lockAll = '$skuRows = Inventory::where(\'product_id\', $productId)';
        $this->assertStringContainsString($lockAll, $body, '入库须先锁该 SKU 全部库存行');
        $this->assertStringContainsString("->orderBy('id')", $body, '锁行顺序须固定（id 升序）');
        $this->assertOrder($body, $lockAll, '$inv = new Inventory()', '必须先锁全部行，再做目标行创建判断');
        $this->assertOrder($body, $lockAll, '$inv->quantity = bcadd', '必须先锁全部行，再累加数量');
        $this->assertOrder($body, "->orderBy('id')", 'recalcMovingAverageCost(', '锁全部行之后再做均价重算');

        $this->assertStringContainsString('$this->isDuplicateKey($e)', $body, '保留 1062 唯一键冲突判定');
        $this->assertStringContainsString('->firstOrFail()', $body, '保留 1062 后重读既有行');
    }

    public function testMovingAverageRecalcLocksRowsInIdOrder(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/inventory/InventoryService.php'),
            'private function recalcMovingAverageCost',
            'public function reserveQuantity'
        );

        $this->assertOrder($body, '->orderBy(\'id\')', '->lockForUpdate()', '均价重算的锁序须与 stockIn 一致');
        $this->assertStringContainsString('uk_product_sku_warehouse_location_batch', $body);
    }

    // ============================================================
    // 跨 SKU 死锁与库存行直改（reviewer 发现）
    // ============================================================

    public function testConfirmPutawaySortsItemsByInventoryDimensionsBeforeStockIn(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/wms/WmsInboundService.php'),
            'public function confirmPutaway',
            'public function startPutaway'
        );

        $sortStart = strpos($body, '->sortBy(');
        $this->assertNotFalse($sortStart, '上架明细须按库存维度排序后再逐行入库');
        $sortEnd = strpos($body, '->values()', $sortStart);
        $this->assertNotFalse($sortEnd, '排序后须归一为顺序集合');
        $sortExpr = substr($body, $sortStart, $sortEnd - $sortStart);
        foreach (['product_id', 'sku_id', 'to_location_id', 'batch_code'] as $field) {
            $this->assertStringContainsString($field, $sortExpr, "排序键须包含 {$field}");
        }
        $this->assertOrder(
            $body,
            '->sortBy(',
            '$this->inventory->stockIn(',
            '两单明细顺序相反时须先按统一维度序排列，避免跨 SKU 死锁'
        );
    }

    public function testInventoryUpdateRejectsAccountingAndDimensionChanges(): void
    {
        $body = $this->methodBody(
            $this->source('app/controller/inventory/InventoryController.php'),
            'public function update',
            'public function destroy'
        );

        $this->assertStringContainsString("array_key_exists('quantity', \$payload)", $body, '数量仍须拒绝');
        $this->assertStringContainsString("array_key_exists('cost_price', \$payload)", $body, '成本价须拒绝直改');
        foreach (['product_id', 'sku_id', 'warehouse_id', 'location_id'] as $field) {
            $this->assertStringContainsString("'{$field}'", $body, "库存维度 {$field} 须纳入校验");
        }
        $this->assertStringContainsString("array_key_exists('batch_code', \$payload)", $body, '批次号须纳入校验');
        $this->assertStringContainsString('inventory transfer or stocktake/adjustment', $body, '422 提示应引导走调拨/盘点');
        // 库存行已无可经通用 PUT 写入的字段；不得再 fill/save，否则 hashid 回填会被 integer cast 成 0
        $this->assertStringNotContainsString('fillModelFromRequest', $body);
        $this->assertStringNotContainsString('$item->save()', $body);
    }

    public function testInventoryUpdateRejectsCostPriceChangeWithRealDatabase(): void
    {
        $this->requireTestDatabase();
        Capsule::beginTransaction();
        try {
            [$id] = $this->createInventoryRow();

            $response = (new InventoryController())->update(
                new FakeRequest(['cost_price' => '9.99']),
                HashidsService::encode($id)
            );
            $body = (array) json_decode($response->rawBody(), true);

            $this->assertSame(422, (int) ($body['code'] ?? 0), '直改成本价必须返回 422');
            $this->assertSame(5.5, (float) Inventory::find($id)->cost_price, '成本价不得被改写');
        } finally {
            Capsule::rollBack();
        }
    }

    public function testInventoryUpdateRejectsDimensionChangeWithRealDatabase(): void
    {
        $this->requireTestDatabase();
        Capsule::beginTransaction();
        try {
            [$id, $productId] = $this->createInventoryRow();
            $controller = new InventoryController();
            $hashId = HashidsService::encode($id);

            $cases = [
                'warehouse_id' => HashidsService::encode(2),
                'batch_code' => 'MOVED-BATCH',
                'product_id' => HashidsService::encode($productId + 1),
            ];
            foreach ($cases as $field => $value) {
                $response = $controller->update(new FakeRequest([$field => $value]), $hashId);
                $body = (array) json_decode($response->rawBody(), true);
                $this->assertSame(422, (int) ($body['code'] ?? 0), "直改库存维度 {$field} 必须返回 422");
            }

            $row = Inventory::find($id);
            $this->assertSame($productId, (int) $row->product_id, '商品维度不得被改写');
            $this->assertSame(1, (int) $row->warehouse_id, '仓库维度不得被改写');
            $this->assertSame('VERIFYBATCH', $row->batch_code, '批次号不得被改写');
        } finally {
            Capsule::rollBack();
        }
    }

    public function testInventoryUpdateAllowsUnchangedDimensionEchoWithRealDatabase(): void
    {
        $this->requireTestDatabase();
        Capsule::beginTransaction();
        try {
            [$id, $productId, $skuId, $locationId] = $this->createInventoryRow();

            $response = (new InventoryController())->update(new FakeRequest([
                'product_id' => HashidsService::encode($productId),
                'sku_id' => HashidsService::encode($skuId),
                'warehouse_id' => HashidsService::encode(1),
                'location_id' => HashidsService::encode($locationId),
                'batch_code' => 'VERIFYBATCH',
            ]), HashidsService::encode($id));
            $body = (array) json_decode($response->rawBody(), true);

            $this->assertSame(0, (int) ($body['code'] ?? -1), '未变更的整行回填应放行');
            $this->assertSame($productId, (int) Inventory::find($id)->product_id, 'hashid 回填不得被 integer cast 成 0');
        } finally {
            Capsule::rollBack();
        }
    }

    public function testAllocationReserveSortsItemsBeforeReserving(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/oms/AllocationService.php'),
            'public function reserve',
            'public function release'
        );

        $this->assertStringContainsString(
            'StockLockOrder::sort($items)',
            $body,
            'OMS 多行预占须复用统一库存维度锁序'
        );
        $this->assertOrder(
            $body,
            'StockLockOrder::sort($items)',
            'reserveQuantity(',
            '排序须先于逐条预占，避免与 stockIn/stockOut 反向互等'
        );
    }

    public function testRemainingMultiLineInventoryLoopsUseDeterministicDimensionOrder(): void
    {
        $twoTuple = ["->orderBy('product_id')", "->orderBy('sku_id')"];
        $fiveTuple = [
            "->orderBy('product_id')",
            "->orderBy('sku_id')",
            "->orderBy('warehouse_id')",
            "->orderBy('location_id')",
            "->orderBy('batch_code')",
        ];

        $cases = [
            'MfgCostService::auditIssue' => [
                'app/service/manufacturing/MfgCostService.php',
                'public function auditIssue',
                'public function auditCostEntry',
                $twoTuple,
                'stockOut(',
            ],
            'SubcontractService::auditIssue' => [
                'app/service/manufacturing/SubcontractService.php',
                'public function auditIssue',
                'public function auditReceive',
                $twoTuple,
                'stockOut(',
            ],
            'RmaService::receive' => [
                'app/service/oms/RmaService.php',
                'public function receive',
                'private function getReturnCostBasis',
                $twoTuple,
                'stockIn(',
            ],
            'InventoryService::consumeReservation' => [
                'app/service/inventory/InventoryService.php',
                'public function consumeReservation',
                'public function getAvailableQuantity',
                $fiveTuple,
                'stockOut(',
            ],
            'WmsOutboundService::consumeByPickedQuantity' => [
                'app/service/wms/WmsOutboundService.php',
                'private function consumeByPickedQuantity',
                'private function reservationKey',
                $fiveTuple,
                'stockOut(',
            ],
        ];

        foreach ($cases as $name => [$file, $signature, $next, $order, $boundary]) {
            $body = $this->methodBody($this->source($file), $signature, $next);
            $this->assertOrderedBefore(
                $body,
                $order,
                $boundary,
                "{$name} 须按库存维度固定顺序逐行处理，避免与其它单据反向死锁"
            );
        }
    }

    /** 事务内创建一条库存行，返回 [id, product_id, sku_id, location_id] */
    private function createInventoryRow(): array
    {
        $id = SnowflakeService::generate();
        $productId = SnowflakeService::generate();
        $skuId = SnowflakeService::generate();
        $locationId = SnowflakeService::generate();

        $row = new Inventory();
        $row->id = $id;
        $row->product_id = $productId;
        $row->sku_id = $skuId;
        $row->warehouse_id = 1;
        $row->location_id = $locationId;
        $row->batch_code = 'VERIFYBATCH';
        $row->quantity = 10;
        $row->cost_price = 5.5;
        $row->save();

        return [$id, $productId, $skuId, $locationId];
    }

    // ============================================================
    // 上架状态不可回改（reviewer 发现的重复入库路径）
    // ============================================================

    public function testPutawayUpdateRejectsStatusChange(): void
    {
        // 纯单测：状态拒绝发生在查库之前，无需数据库即可验证
        $response = (new PutawayController())->update(
            new FakeRequest(['status' => 1]),
            HashidsService::encode(987654321)
        );
        $body = (array) json_decode($response->rawBody(), true);

        // 项目约定：业务码在响应体 code 字段（HTTP 状态恒 200），422 即业务拒绝
        $this->assertSame(422, (int) ($body['code'] ?? 0), 'PUT 直接改 status 必须返回 422');
        $this->assertStringContainsString(
            'cannot be modified directly',
            (string) ($body['message'] ?? ''),
            '422 文案应说明状态只能经 start/complete 流转'
        );

        $updateBody = $this->methodBody(
            $this->source('app/controller/wms/PutawayController.php'),
            'public function update',
            'public function destroy'
        );
        $this->assertOrder(
            $updateBody,
            "array_key_exists('status', \$request->all())",
            'WmsPutawayTask::find($id)',
            '状态校验须先于查库'
        );
    }

    public function testPutawayTaskStatusIsNotMassAssignable(): void
    {
        $task = new WmsPutawayTask();

        $this->assertNotContains('status', $task->getFillable(), 'status 不得进入 fillable，通用 PUT 不得回改状态');
        // 服务内部状态流转走直接属性赋值，不受 fillable 影响
        $task->status = 1;
        $this->assertSame(1, (int) $task->status);
    }

    public function testConfirmPutawayVerifiesSourceDocumentStatuses(): void
    {
        $body = $this->methodBody(
            $this->source('app/service/wms/WmsInboundService.php'),
            'public function confirmPutaway',
            'public function startPutaway'
        );

        $this->assertStringContainsString('(int) $receiving->status !== 2', $body, '上架前须复核收货单已完成');
        $this->assertStringContainsString('(int) $asn->status !== 2', $body, '上架前须复核 ASN 已完成');
        $this->assertStringContainsString('收货单或ASN状态不允许上架', $body);
        $this->assertOrder($body, '$putaway->status !== 1', 'stockIn(', '任务状态校验须先于入库');
        $this->assertOrder($body, '(int) $asn->status !== 2', 'stockIn(', '来源单据校验须先于入库');
    }

    public function testConfirmPutawayRejectsCompletedTaskWithRealDatabase(): void
    {
        $this->requireTestDatabase();
        Capsule::beginTransaction();
        try {
            $id = SnowflakeService::generate();
            $task = new WmsPutawayTask();
            $task->id = $id;
            $task->code = 'VERIFYPUT' . $id;
            $task->warehouse_id = 1;
            $task->receiving_id = 0;
            $task->status = 2;
            $task->save();

            $error = null;
            try {
                (new WmsInboundService())->confirmPutaway($id);
            } catch (\RuntimeException $e) {
                $error = $e;
            }

            $this->assertNotNull($error, '已完成的上架任务不得再次确认');
            $this->assertStringContainsString('请先开始上架', $error->getMessage());
        } finally {
            Capsule::rollBack();
        }
    }

    public function testConfirmPutawayRejectsTamperedCompletedReceiptWithRealDatabase(): void
    {
        $this->requireTestDatabase();
        Capsule::beginTransaction();
        try {
            $asnId = SnowflakeService::generate();
            $asn = new WmsAsn();
            $asn->id = $asnId;
            $asn->code = 'VERIFYASN' . $asnId;
            $asn->supplier_id = 1;
            $asn->warehouse_id = 1;
            $asn->status = 3; // 完成上架后 ASN 已置 3
            $asn->save();

            $receivingId = SnowflakeService::generate();
            $receiving = new WmsReceiving();
            $receiving->id = $receivingId;
            $receiving->code = 'VERIFYRCV' . $receivingId;
            $receiving->asn_id = $asnId;
            $receiving->warehouse_id = 1;
            $receiving->status = 2;
            $receiving->save();

            $putawayId = SnowflakeService::generate();
            $putaway = new WmsPutawayTask();
            $putaway->id = $putawayId;
            $putaway->code = 'VERIFYPUT' . $putawayId;
            $putaway->warehouse_id = 1;
            $putaway->receiving_id = $receivingId;
            $putaway->status = 1; // 模拟被通用 PUT 改回「上架中」
            $putaway->save();

            $error = null;
            try {
                (new WmsInboundService())->confirmPutaway($putawayId);
            } catch (\RuntimeException $e) {
                $error = $e;
            }

            $this->assertNotNull($error, '来源单据已完成时不得再次入库');
            $this->assertStringContainsString('收货单或ASN状态不允许上架', $error->getMessage());
            $this->assertSame(1, (int) WmsPutawayTask::find($putawayId)->status, '被拒后任务状态不得推进');
        } finally {
            Capsule::rollBack();
        }
    }

    // ---------- 数据库守卫（契约同 tests/Unit/InventorySkuRaceFixTest） ----------

    private function requireTestDatabase(): void
    {
        if ((string) getenv('TEST_DB_DATABASE') === '') {
            $this->markTestSkipped('未配置 TEST_DB_DATABASE 等 TEST_DB_* 环境变量，跳过数据库断言');
        }
        self::bootCapsule();
        try {
            Capsule::connection()->select('SELECT 1');
        } catch (Throwable $e) {
            $this->markTestSkipped('数据库连接失败: ' . $e->getMessage());
        }
    }

    private static function bootCapsule(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'database' => (string) getenv('TEST_DB_DATABASE'),
            'username' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
            'password' => (string) (getenv('TEST_DB_PASSWORD') ?: ''),
            // 与 app config/database.php 同源：模型声明无前缀表名，靠此前缀拼真实表
            'prefix' => getenv('DB_PREFIX') ?: 'erp_',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => 'InnoDB',
        ], 'default');
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
