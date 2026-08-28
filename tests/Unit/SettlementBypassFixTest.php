<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Unit;

use app\service\finance\FinanceService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * 核销接口绕过服务层修复回归测试
 *
 * - 纯逻辑断言（无需数据库）: SettlementController 不再暴露 update/destroy，
 *   已核销记录不可被随意修改/删除。
 * - 数据库断言（TEST_DB_* 契约，与 tests/Integration 一致，未配置时优雅跳过）:
 *   核销经服务层后 erp_finance_ar_ap.settled_amount/status 同步、超余额拒绝。
 *   注: 数据库断言统一走 Capsule::table 显式表名，规避模型魔术方法的
 *   phpstan 基线差异与 erp_ 前缀双重叠加问题。
 */
class SettlementBypassFixTest extends TestCase
{
    /** @var int[] 测试中创建的 ar_ap id，tearDown 清理 */
    private array $arApIds = [];

    /** @var int[] 测试中创建的核销记录 id，tearDown 清理 */
    private array $settlementIds = [];

    /** @var int[] 测试中创建的收款单 id，tearDown 清理 */
    private array $receiptIds = [];

    protected function tearDown(): void
    {
        if (!empty($this->settlementIds)) {
            Capsule::table('erp_finance_settlement')->whereIn('id', $this->settlementIds)->delete();
            $this->settlementIds = [];
        }
        if (!empty($this->arApIds)) {
            Capsule::table('erp_finance_ar_ap')->whereIn('id', $this->arApIds)->delete();
            $this->arApIds = [];
        }
        if (!empty($this->receiptIds)) {
            Capsule::table('erp_finance_receipt')->whereIn('id', $this->receiptIds)->delete();
            $this->receiptIds = [];
        }
        parent::tearDown();
    }

    public function testSettlementControllerNoLongerExposesUpdateDestroy(): void
    {
        $class = 'app\\controller\\finance\\SettlementController';
        $this->assertTrue(class_exists($class));
        foreach (['index', 'show', 'store'] as $kept) {
            $this->assertTrue(method_exists($class, $kept), "{$class}::{$kept} 应保留");
        }
        foreach (['update', 'destroy'] as $removed) {
            $this->assertFalse(method_exists($class, $removed), "{$class}::{$removed} 应已删除（核销记录不可随意改删）");
        }
    }

    public function testSettleReceiptSyncsArApAmount(): void
    {
        $this->requireTestDatabase();

        $service = new FinanceService();
        $arApId = $service->createAr(999001, 'unit_test', 900001, 1000.00);
        $this->arApIds[] = $arApId;

        // 收款单须先存在（settleReceipt 单据侧守卫：不存在/未审核/归属不一致均先行拒绝）
        Capsule::table('erp_finance_receipt')->insert([
            'id' => 888001,
            'code' => 'UNIT-888001',
            'customer_id' => 999001,
            'bank_account_id' => 0,
            'amount' => 1000.00,
            'status' => 1,
        ]);
        $this->receiptIds[] = 888001;

        $service->settleReceipt(888001, $arApId, 600.00);

        $arAp = Capsule::table('erp_finance_ar_ap')->where('id', $arApId)->first();
        $this->assertNotNull($arAp);
        $this->assertEquals(600.00, (float) $arAp->settled_amount, 'settled_amount 应同步为已核销金额');
        $this->assertEquals(1, (int) $arAp->status, '部分核销状态应为 1');

        $settlement = Capsule::table('erp_finance_settlement')
            ->where('ar_ap_id', $arApId)->where('type', 1)->first();
        $this->assertNotNull($settlement, '服务层应生成核销记录');
        $this->assertEquals(600.00, (float) $settlement->amount);
        $this->settlementIds[] = (int) $settlement->id;
    }

    public function testSettleRejectsOverRemainingBalance(): void
    {
        $this->requireTestDatabase();

        $service = new FinanceService();
        $arApId = $service->createAr(999002, 'unit_test', 900002, 500.00);
        $this->arApIds[] = $arApId;

        // 收款单须存在且余额充足（700≥600），否则单据侧守卫先行抛出
        // "收款单不存在/核销金额超出收款单剩余可核销额"，无法命中应收侧守卫
        Capsule::table('erp_finance_receipt')->insert([
            'id' => 888002,
            'code' => 'UNIT-888002',
            'customer_id' => 999002,
            'bank_account_id' => 0,
            'amount' => 700.00,
            'status' => 1,
        ]);
        $this->receiptIds[] = 888002;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('超出未核销余额');
        $service->settleReceipt(888002, $arApId, 600.00);
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
