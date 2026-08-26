<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Unit;

use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Throwable;

/**
 * SKU 首次并发入库均价竞态修复回归测试
 *
 * - 纯逻辑断言（无需数据库）: isDuplicateKey 仅对 MySQL 1062 唯一键冲突返回 true，
 *   死锁等其他错误原样抛出，避免吞掉非冲突异常。
 * - 数据库断言（TEST_DB_* 契约，与 tests/Integration 一致，未配置时优雅跳过）:
 *   同一 (product_id, sku_id, warehouse_id, location_id, batch_code) 连续两次入库
 *   只保留一条库存行（唯一索引不变量）、数量累加、加权均价正确。
 * - 真实并发竞态（两事务同刻首单，select 都查不到行）无法在单进程单测中确定性复现，
 *   该路径的正确性由唯一索引 uk_product_sku_warehouse_location_batch 兜底 +
 *   stockIn 创建路径捕获 1062 后重读重算保证，需集成环境（两个并发连接）验证。
 */
class InventorySkuRaceFixTest extends TestCase
{
    /** @var array<int, array<string, int|string>> 测试创建的库存行唯一键，tearDown 清理 */
    private array $invKeys = [];

    /** @var int[] 测试创建的库存流水 id，tearDown 清理 */
    private array $flowIds = [];

    /** @var string[] 测试创建的批次号，tearDown 清理 */
    private array $batchCodes = [];

    protected function tearDown(): void
    {
        foreach ($this->invKeys as $key) {
            Capsule::table('erik_inventory')->where($key)->delete();
        }
        if (!empty($this->flowIds)) {
            Capsule::table('erik_inventory_flow')->whereIn('id', $this->flowIds)->delete();
            Capsule::table('erik_cost_record')->whereIn('flow_id', $this->flowIds)->delete();
        }
        foreach ($this->batchCodes as $code) {
            Capsule::table('erik_inventory_batch')->where('batch_code', $code)->delete();
        }
        $this->invKeys = [];
        $this->flowIds = [];
        $this->batchCodes = [];
        parent::tearDown();
    }

    public function testDuplicateKeyPredicateOnlyMatches1062(): void
    {
        $method = new ReflectionMethod(InventoryService::class, 'isDuplicateKey');
        $method->setAccessible(true);
        $service = new InventoryService();

        $dup = new PDOException('Duplicate entry', 1062);
        $dup->errorInfo = ['23000', 1062, "Duplicate entry 'x' for key 'uk_product_sku_warehouse_location_batch'"];
        $e1062 = new QueryException('default', 'insert into erik_inventory ...', [], $dup);
        $this->assertTrue($method->invoke($service, $e1062), '1062 唯一键冲突应判定为重复');

        $deadlock = new PDOException('Deadlock found', 1213);
        $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
        $e1213 = new QueryException('default', 'insert into erik_inventory ...', [], $deadlock);
        $this->assertFalse($method->invoke($service, $e1213), '死锁等非 1062 错误不应判定为重复');

        $ePlain = new QueryException('default', 'insert into erik_inventory ...', [], new \RuntimeException('boom'));
        $this->assertFalse($method->invoke($service, $ePlain), '无 PDO errorInfo 的错误不应判定为重复');
    }

    public function testStockInTwiceKeepsSingleRowWithCorrectAvg(): void
    {
        $this->requireTestDatabase();

        $key = ['product_id' => 970001, 'sku_id' => 970002, 'warehouse_id' => 970003, 'location_id' => 970004, 'batch_code' => 'race-batch-01'];
        $this->invKeys[] = $key;
        $this->batchCodes[] = 'race-batch-01';

        $service = new InventoryService();
        $this->flowIds[] = $service->stockIn(970001, 970002, 970003, 970004, 'race-batch-01', 10, 10.00, 'unit_test', 910001);
        $this->flowIds[] = $service->stockIn(970001, 970002, 970003, 970004, 'race-batch-01', 10, 20.00, 'unit_test', 910002);

        $this->assertSame(1, Capsule::table('erik_inventory')->where($key)->count(), '同一唯一键只应有一条库存行');
        $row = Capsule::table('erik_inventory')->where($key)->first();
        $this->assertNotNull($row);
        $this->assertEquals(20.0, (float) $row->quantity, '数量应累加');
        $this->assertEquals(15.0, (float) $row->cost_price, '加权均价 = (10*10 + 10*20) / 20 = 15');
    }

    // ---------- 数据库守卫（契约同 tests/Integration/IntegrationTestCase） ----------

    private function requireTestDatabase(): void
    {
        if (getenv('TEST_DB_DATABASE') === false || getenv('TEST_DB_DATABASE') === '') {
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
            // 查询统一走显式表名，此处置空前缀
            'prefix' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => 'InnoDB',
        ], 'default');
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
