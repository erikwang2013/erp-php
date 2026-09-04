<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\service\inventory\InventoryService;
use app\service\inventory\TraceService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use support\Container;
use Throwable;

/**
 * M6 追溯链报表集成测试（真库，--group=integration）。
 *
 * 依赖表（install.sql 域，只读使用，绝不创建）：
 *   erp_inventory / erp_inventory_batch / erp_inventory_serial
 *   / erp_inventory_flow / erp_cost_record / erp_product_sku
 *
 * 造数原则：每用例独立 product/sku（snowflake id），批次码唯一前缀 + 序号，
 * 避免移动加权均价跨测试串算；断言一律 bccomp（数量以字符串返回）。
 * 清理原则：tearDown 仅删除本类写入行（flow/serial/batch/inventory/cost_record
 * /product_sku），不触碰其他测试数据。
 */
#[Group('integration')]
#[TestDox('M6 追溯链报表')]
class M6TraceTest extends IntegrationTestCase
{
    private const WH_ID = 9002;
    private const LOCATION_ID = 0;
    private const BATCH_PREFIX = 'M6-B-';

    /** install.sql 域依赖表：缺表整类跳过 */
    private const DEP_TABLES = [
        'erp_inventory',
        'erp_inventory_batch',
        'erp_inventory_serial',
        'erp_inventory_flow',
        'erp_cost_record',
    ];

    private array $productIds = [];
    private array $skuIds = [];
    private array $flowIds = [];
    private array $serialIds = [];
    private array $batchCodes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $missing = array_values(array_filter(
            self::DEP_TABLES,
            fn (string $t): bool => !Capsule::schema()->hasTable($t)
        ));
        if ($missing !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missing) . '（请先导入 install.sql）');
        }
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            if ($this->productIds !== []) {
                $this->safeDelete('erp_inventory', ['product_id', $this->productIds]);
                $this->safeDelete('erp_product_sku', ['product_id', $this->productIds]);
            }
            if ($this->flowIds !== []) {
                $this->safeDelete('erp_inventory_flow', ['id', $this->flowIds]);
                $this->safeDelete('erp_cost_record', ['flow_id', $this->flowIds]);
                $this->safeDelete('erp_inventory_serial', ['in_flow_id', $this->flowIds]);
                $this->safeDelete('erp_inventory_serial', ['out_flow_id', $this->flowIds]);
            }
            if ($this->serialIds !== []) {
                $this->safeDelete('erp_inventory_serial', ['id', $this->serialIds]);
            }
            if ($this->batchCodes !== []) {
                $this->safeDelete('erp_inventory_batch', ['batch_code', $this->batchCodes]);
            }
        }
        parent::tearDown();
    }

    // ---------- 正向追溯 ----------

    #[TestDox('批次正向追溯：出入分组 + 总量 + 出库去向展开 + source_label')]
    public function testForward(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();

        // 入库 ×2（采购收货 / 上架）；出库 ×1（销售发货）
        $this->stockIn($p, $batch, '10', '5.00', 'purchase_receive');
        $this->stockIn($p, $batch, '5', '6.00', 'wms_putaway');
        $this->stockOut($p, $batch, '3', 'sales_delivery');

        $result = $this->trace()->forward($batch);

        $this->assertSame($batch, $result['batch_code']);
        $this->assertSame($p['product_id'], $result['product_id']);
        $this->assertSame($p['sku_id'], $result['sku_id']);
        $this->assertBcEquals('12', $result['on_hand']);
        $this->assertBcEquals('15', $result['in_total']);
        $this->assertBcEquals('3', $result['out_total']);

        // 入库分组按 id 升序：2 条
        $this->assertCount(2, $result['in_flows']);
        $this->assertSame('purchase_receive', $result['in_flows'][0]['source_type']);
        $this->assertSame('采购收货单', $result['in_flows'][0]['source_label']);
        $this->assertBcEquals('10', $result['in_flows'][0]['quantity']);
        $this->assertSame('wms_putaway', $result['in_flows'][1]['source_type']);
        $this->assertSame('上架单', $result['in_flows'][1]['source_label']);

        // 出库分组：1 条，去向展开
        $this->assertCount(1, $result['out_flows']);
        $this->assertSame(2, $result['out_flows'][0]['direction']);
        $this->assertSame('sales_delivery', $result['out_flows'][0]['source_type']);
        $this->assertSame('销售发货单', $result['out_flows'][0]['source_label']);
        $this->assertBcEquals('3', $result['out_flows'][0]['quantity']);
    }

    // ---------- 反向追溯 ----------

    #[TestDox('批次反向追溯：仅入库来源链，出库不在 sources')]
    public function testBackward(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();

        $this->stockIn($p, $batch, '10', '5.00', 'mfg_production_finish');
        $this->stockOut($p, $batch, '4', 'sales_delivery');

        $result = $this->trace()->backward($batch);

        $this->assertSame($batch, $result['batch_code']);
        $this->assertSame($p['product_id'], $result['product_id']);
        $this->assertBcEquals('6', $result['on_hand']);
        $this->assertCount(1, $result['sources']);
        $this->assertSame('mfg_production_finish', $result['sources'][0]['source_type']);
        $this->assertSame('生产完工单', $result['sources'][0]['source_label']);
        $this->assertBcEquals('10', $result['sources'][0]['quantity']);
    }

    // ---------- 序列号追溯 ----------

    #[TestDox('序列号追溯：入库/出库两端流水 + 状态标签')]
    public function testSerial(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();
        $serialCode = 'SN-' . $this->nextId();

        $inFlowId = $this->stockIn($p, $batch, '2', '5.00', 'wms_putaway', [$serialCode]);
        $outFlowId = $this->stockOut($p, $batch, '1', 'sales_delivery', [$serialCode]);

        $result = $this->trace()->serial($serialCode);

        $this->assertSame($serialCode, $result['serial_code']);
        $this->assertSame($p['product_id'], $result['product_id']);
        $this->assertSame(1, $result['status']);
        $this->assertSame('已出库', $result['status_label']);
        $this->assertSame($inFlowId, $result['in_flow']['id']);
        $this->assertSame('wms_putaway', $result['in_flow']['source_type']);
        $this->assertSame('上架单', $result['in_flow']['source_label']);
        $this->assertSame($outFlowId, $result['out_flow']['id']);
        $this->assertSame('sales_delivery', $result['out_flow']['source_type']);
        $this->assertSame('销售发货单', $result['out_flow']['source_label']);
    }

    #[TestDox('序列号不存在返回空数组')]
    public function testSerialUnknown(): void
    {
        $this->assertSame([], $this->trace()->serial('SN-NOT-EXIST'));
    }

    // ---------- 近效期预警 ----------

    #[TestDox('近效期预警：窗口过滤 + 仅在库批次 + remaining_days')]
    public function testExpiryAlert(): void
    {
        $p = $this->createProduct();
        $today = date('Y-m-d');
        $inWindow = 'M6-EXP-IN';
        $past = 'M6-EXP-PAST';
        $zeroStock = 'M6-EXP-ZERO';
        $beyond = 'M6-EXP-BEYOND';
        $noExpiry = 'M6-EXP-NULL';

        // 窗口内（今天+10）、已过期、窗口内但零库存、超窗口、无有效期
        $this->stockIn($p, $inWindow, '8', '5.00', 'purchase_receive', [],
            date('Y-m-d', strtotime('-30 day', strtotime($today))),
            date('Y-m-d', strtotime('+10 day', strtotime($today))));
        $this->stockIn($p, $past, '5', '5.00', 'purchase_receive', [],
            date('Y-m-d', strtotime('-60 day', strtotime($today))),
            date('Y-m-d', strtotime('-3 day', strtotime($today))));
        $this->stockIn($p, $zeroStock, '2', '5.00', 'purchase_receive', [],
            date('Y-m-d', strtotime('-40 day', strtotime($today))),
            date('Y-m-d', strtotime('+5 day', strtotime($today))));
        $this->stockOut($p, $zeroStock, '2', 'sales_delivery');
        $this->stockIn($p, $beyond, '3', '5.00', 'purchase_receive', [],
            date('Y-m-d', strtotime('-10 day', strtotime($today))),
            date('Y-m-d', strtotime('+120 day', strtotime($today))));
        $this->stockIn($p, $noExpiry, '3', '5.00', 'purchase_receive');

        $rows = $this->trace()->expiryAlert(10);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['batch_code']] = $row;
        }

        $this->assertArrayHasKey($inWindow, $map);
        $this->assertArrayHasKey($past, $map);
        $this->assertArrayNotHasKey($zeroStock, $map);
        $this->assertArrayNotHasKey($beyond, $map);
        $this->assertArrayNotHasKey($noExpiry, $map);

        $this->assertSame(10, $map[$inWindow]['remaining_days']);
        $this->assertSame(-3, $map[$past]['remaining_days']);
        $this->assertBcEquals('8', $map[$inWindow]['on_hand']);
        $this->assertSame(date('Y-m-d', strtotime('+10 day', strtotime($today))), $map[$inWindow]['expiry_date']);
    }

    #[TestDox('近效期预警：天数不能为负')]
    public function testExpiryAlertNegativeDays(): void
    {
        $this->assertThrowsMessage(fn () => $this->trace()->expiryAlert(-1), '预警天数不能为负数');
    }

    // ---------- stockIn 生产/效期日期参数（P1-M6 附加，纯增量） ----------

    #[TestDox('stockIn 新批次写入生产/效期日期')]
    public function testStockInWritesBatchDates(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();
        $production = date('Y-m-d', strtotime('-10 day'));
        $expiry = date('Y-m-d', strtotime('+90 day'));

        $this->stockIn($p, $batch, '10', '5.00', 'purchase_receive', [], $production, $expiry);

        $batchRow = Capsule::table('erp_inventory_batch')
            ->where('product_id', $p['product_id'])
            ->where('sku_id', $p['sku_id'])
            ->where('batch_code', $batch)
            ->first();
        $this->assertNotNull($batchRow);
        $this->assertSame($production, $batchRow->production_date);
        $this->assertSame($expiry, $batchRow->expiry_date);
    }

    #[TestDox('stockIn 缺省日期仍可入库（向后兼容）')]
    public function testStockInWithoutDatesBackwardCompatible(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();

        $this->stockIn($p, $batch, '10', '5.00', 'purchase_receive');

        $batchRow = Capsule::table('erp_inventory_batch')
            ->where('product_id', $p['product_id'])
            ->where('sku_id', $p['sku_id'])
            ->where('batch_code', $batch)
            ->first();
        $this->assertNotNull($batchRow);
        $this->assertNull($batchRow->production_date);
        $this->assertNull($batchRow->expiry_date);
    }

    #[TestDox('stockIn 批次已存在时仅补写空值，不覆盖既有效期')]
    public function testStockInBackfillsEmptyOnly(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();
        $production = date('Y-m-d', strtotime('-20 day'));
        $firstExpiry = date('Y-m-d', strtotime('+30 day'));
        $secondExpiry = date('Y-m-d', strtotime('+200 day'));

        // 首次入库仅带效期
        $this->stockIn($p, $batch, '5', '5.00', 'purchase_receive', [], null, $firstExpiry);
        // 二次入库带生产日期 + 不同效期：效期不应被覆盖，生产日期应补写
        $this->stockIn($p, $batch, '5', '5.00', 'wms_putaway', [], $production, $secondExpiry);

        $batchRow = Capsule::table('erp_inventory_batch')
            ->where('product_id', $p['product_id'])
            ->where('sku_id', $p['sku_id'])
            ->where('batch_code', $batch)
            ->first();
        $this->assertSame($production, $batchRow->production_date);
        $this->assertSame($firstExpiry, $batchRow->expiry_date);
    }

    #[TestDox('stockIn 非法日期抛参数异常')]
    public function testStockInRejectsInvalidDate(): void
    {
        $p = $this->createProduct();
        $batch = $this->uniqueBatch();

        $this->assertThrowsMessage(
            fn () => $this->inventory()->stockIn(
                $p['product_id'], $p['sku_id'], self::WH_ID, self::LOCATION_ID, $batch,
                10.0, 5.0, 'purchase_receive', $this->nextId(), [], '2026/01/01'
            ),
            'production_date 须为合法日期 YYYY-MM-DD'
        );
    }

    // ---------- 输入校验 ----------

    #[TestDox('批次/序列号参数非空校验')]
    public function testEmptyInputRejected(): void
    {
        $this->assertThrowsMessage(fn () => $this->trace()->forward(''), '批次号不能为空');
        $this->assertThrowsMessage(fn () => $this->trace()->backward(''), '批次号不能为空');
        $this->assertThrowsMessage(fn () => $this->trace()->serial(''), '序列号不能为空');
    }

    // ---------- 造数辅助 ----------

    /** 建唯一产品+SKU（erp_product_sku 仅保证 recalc 查询域独立，主档可缺省） */
    private function createProduct(): array
    {
        $productId = $this->nextId();
        $skuId = $this->nextId();
        $now = date('Y-m-d H:i:s');
        Capsule::table('erp_product_sku')->insert([
            'id' => $skuId,
            'product_id' => $productId,
            'sku_code' => 'SKU-' . $skuId,
            'barcode' => '',
            'cost_price' => '0.00',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->productIds[] = $productId;
        $this->skuIds[] = $skuId;

        return ['product_id' => $productId, 'sku_id' => $skuId];
    }

    /** 唯一批次码：常量前缀 + snowflake 尾号（短于 50 列宽，并保证全局唯一） */
    private function uniqueBatch(): string
    {
        return self::BATCH_PREFIX . $this->nextId();
    }

    private function nextId(): int
    {
        return (int) SnowflakeService::generate();
    }

    /** 入库 + 登记清理（flow id / serial id）；productionDate/expiryDate 可空 */
    private function stockIn(
        array $p,
        string $batch,
        string $qty,
        string $cost,
        string $sourceType,
        array $serials = [],
        ?string $productionDate = null,
        ?string $expiryDate = null
    ): int {
        $this->batchCodes[] = $batch;
        $flowId = $this->inventory()->stockIn(
            $p['product_id'], $p['sku_id'], self::WH_ID, self::LOCATION_ID, $batch,
            (float) $qty, (float) $cost, $sourceType, $this->nextId(), $serials,
            $productionDate, $expiryDate
        );
        $this->flowIds[] = $flowId;
        foreach ($serials as $code) {
            $serial = Capsule::table('erp_inventory_serial')->where('serial_code', $code)->first();
            if ($serial) {
                $this->serialIds[] = (int) $serial->id;
            }
        }

        return $flowId;
    }

    /** 出库 + 登记清理 */
    private function stockOut(array $p, string $batch, string $qty, string $sourceType, array $serials = []): int
    {
        $this->batchCodes[] = $batch;
        $flowId = $this->inventory()->stockOut(
            $p['product_id'], $p['sku_id'], self::WH_ID, self::LOCATION_ID, $batch,
            (float) $qty, $sourceType, $this->nextId(), $serials
        );
        $this->flowIds[] = $flowId;

        return $flowId;
    }

    // ---------- 断言/清理工具 ----------

    protected function assertThrowsMessage(callable $fn, string $needle): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }
        self::fail('预期异常未抛出，期望消息包含: ' . $needle);
    }

    protected function assertBcEquals(string $expected, string $actual, string $label = ''): void
    {
        $this->assertSame(0, bccomp(bc_norm($expected), bc_norm($actual), 6), $label
            . sprintf(' 期望=%s 实际=%s', bc_norm($expected), bc_norm($actual)));
    }

    /** 清理失败仅记录，不改变测试结论（同 IntegrationTestCase::dropTableIfExists 约定） */
    private function safeDelete(string $table, array $where): void
    {
        try {
            Capsule::table($table)->whereIn($where[0], $where[1])->delete();
        } catch (Throwable) {
        }
    }

    // ---------- 服务入口（Container 解析，与控制器同路径） ----------

    private function trace(): TraceService
    {
        return Container::get(TraceService::class);
    }

    private function inventory(): InventoryService
    {
        return Container::get(InventoryService::class);
    }
}
