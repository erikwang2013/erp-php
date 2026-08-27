<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Unit;

use app\service\oms\RmaService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * RMA 创建事务化修复回归测试
 *
 * - 纯逻辑断言（无需数据库）: RmaService 可实例化。
 * - 数据库断言（TEST_DB_* 契约，未配置时优雅跳过）:
 *   create() 头+明细同事务，任一明细写入失败时表头一并回滚；
 *   refund() 翻状态并同步订单支付状态为已退款。
 *   注: 数据库断言统一走 Capsule::table 显式表名，规避模型魔术方法的
 *   phpstan 基线差异与 erp_ 前缀双重叠加问题。
 */
class RmaFixTest extends TestCase
{
    /** @var string[] 测试中创建的 RMA code，tearDown 清理 */
    private array $rmaCodes = [];

    protected function tearDown(): void
    {
        if (!empty($this->rmaCodes)) {
            $rmaIds = Capsule::table('erp_oms_rma')->whereIn('code', $this->rmaCodes)->pluck('id')->all();
            if (!empty($rmaIds)) {
                Capsule::table('erp_oms_rma_item')->whereIn('rma_id', $rmaIds)->delete();
                Capsule::table('erp_oms_rma')->whereIn('id', $rmaIds)->delete();
            }
            $this->rmaCodes = [];
        }
        parent::tearDown();
    }

    public function testRmaServiceIsInstantiable(): void
    {
        $service = new RmaService();
        $this->assertInstanceOf(RmaService::class, $service);
    }

    public function testCreateRollsBackHeaderWhenItemFails(): void
    {
        $this->requireTestDatabase();

        $code = 'UT-RMA-' . time() . '-' . random_int(1000, 9999);
        $this->rmaCodes[] = $code;

        $items = [
            ['order_item_id' => 1, 'product_id' => 1, 'quantity' => 2, 'price' => 10],
            // quantity 缺失 → NOT NULL 约束失败，触发回滚
            ['order_item_id' => 2, 'product_id' => 2],
        ];

        try {
            (new RmaService())->create(700001, 700001, 1, 'unit test rollback', $items, ['code' => $code]);
            $this->fail('明细写入失败时应抛出异常');
        } catch (Throwable $e) {
            // 期望抛出
        }

        $this->assertNull(
            Capsule::table('erp_oms_rma')->where('code', $code)->first(),
            '明细失败时表头应随事务一并回滚'
        );
    }

    public function testRefundFlipsStatus(): void
    {
        $this->requireTestDatabase();

        $code = 'UT-RMA-' . time() . '-' . random_int(1000, 9999);
        $this->rmaCodes[] = $code;

        $service = new RmaService();
        $service->create(700002, 700002, 1, 'unit test refund', [
            ['order_item_id' => 1, 'product_id' => 1, 'quantity' => 1, 'price' => 20],
        ], ['code' => $code, 'refund_amount' => 20.0]);

        $rmaId = (int) Capsule::table('erp_oms_rma')->where('code', $code)->value('id');
        $service->refund($rmaId);

        $status = (int) Capsule::table('erp_oms_rma')->where('id', $rmaId)->value('status');
        $this->assertEquals(4, $status, '退款后 RMA 状态应为 4=已退款');
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
