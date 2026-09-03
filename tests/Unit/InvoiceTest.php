<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use app\service\finance\InvoiceService;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * 发票管理 P0 单测：开票申请状态流 + 三单匹配（全部 bcmath，禁 float）
 *
 * - 纯逻辑断言（无需数据库）: calcLineAmounts 税额半值进位（half-up away-from-zero）、
 *   100.005 边界不经 float 的字符串精确性。
 * - 数据库断言（TEST_DB_* 契约，未配置时优雅跳过）: 应付发票 ⇐ 采购收货单的
 *   正常开票/超开拦截/恰好尾单/作废回补；金额断言走 Capsule::table 显式表名。
 *   测试行税率取 0 使 含税合计=不含税金额，三单匹配金额可直接精确比对；
 *   税额计算正确性由纯逻辑用例独立覆盖（应收路径与应付共用同一 SOURCE_MAP 机制）。
 */
class InvoiceTest extends TestCase
{
    /** 测试收货单头 id 段（930001~930004），tearDown 无条件清理 */
    private const RC_IDS = [930001, 930002, 930003, 930004];

    private static bool $booted = false;

    /** @var int[] 测试创建的发票头 id */
    private array $invoiceIds = [];

    private InvoiceService $service;

    protected function setUp(): void
    {
        $this->service = new InvoiceService();
    }

    protected function tearDown(): void
    {
        if (self::$booted) {
            if (!empty($this->invoiceIds)) {
                Capsule::table('erp_finance_invoice_item')->whereIn('invoice_id', $this->invoiceIds)->delete();
                Capsule::table('erp_finance_invoice_match_log')->whereIn('invoice_id', $this->invoiceIds)->delete();
                Capsule::table('erp_finance_invoice')->whereIn('id', $this->invoiceIds)->delete();
                $this->invoiceIds = [];
            }
            // 被拦截尝试的日志 invoice_id=0，按来源单清理；再清来源单及其明细
            Capsule::table('erp_finance_invoice_match_log')
                ->where('source_type', 'purchase_receive')->whereIn('source_id', self::RC_IDS)->delete();
            Capsule::table('erp_purchase_receive_item')->whereIn('receive_id', self::RC_IDS)->delete();
            Capsule::table('erp_purchase_receive')->whereIn('id', self::RC_IDS)->delete();
        }
        parent::tearDown();
    }

    public function testNormalApInvoiceFlow(): void
    {
        $this->requireTestDatabase();
        $this->seedReceive(930001, [
            [930101, '2.00', '300.00', '600.00'],
            [930102, '4.00', '100.00', '400.00'],
        ]);

        [$invoice, $error] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-N1-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930001,
            'invoice_date' => '2026-09-04',
            'currency' => 'CNY',
            'remark' => '正常开票流程',
            'items' => [['quantity' => '2', 'price' => '300', 'tax_rate' => '0.00', 'product_id' => 930101]],
        ]);
        $this->assertNull($error, '正常开票申请应通过余额校验');
        $this->assertNotNull($invoice);
        $this->invoiceIds[] = (int) $invoice->id;
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('600.00', $invoice->amount, '含税合计应等于行金额(税率0)');
        $this->assertSame('600.00', $invoice->untaxed_amount);

        $this->assertNull($this->service->submit((int) $invoice->id));
        $invoice->refresh();
        $this->assertSame('submitted', $invoice->status);

        $this->assertNull($this->service->audit((int) $invoice->id, 8801));
        $invoice->refresh();
        $this->assertSame('audited', $invoice->status);
        $this->assertSame(8801, (int) $invoice->audited_by);
        $this->assertNotNull($invoice->audited_at);

        $info = $this->service->balanceInfo('purchase_receive', 930001);
        $this->assertSame('1000.00', $info['source_total']);
        $this->assertSame('600.00', $info['invoiced_total'], '已审核发票应占余额');
        $this->assertSame('400.00', $info['balance']);

        $log = Capsule::table('erp_finance_invoice_match_log')
            ->where('invoice_id', (int) $invoice->id)->first();
        $this->assertNotNull($log, '审核应写三单匹配日志');
        $this->assertSame('under', $log->result, '600<1000 应记 under');
    }

    public function testOverInvoiceBlocked(): void
    {
        $this->requireTestDatabase();
        $this->seedReceive(930002, [[930201, '2.00', '300.00', '600.00'], [930202, '4.00', '100.00', '400.00']]);

        $no = uniqid('P0T-O1-');
        [$invoice, $error] = $this->service->storeDraft([
            'invoice_no' => $no,
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930002,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '7', 'price' => '200', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($invoice, '超开不应落发票');
        $this->assertNotNull($error);
        $this->assertStringContainsString('超出未开票余额', $error);
        $this->assertSame(0, Capsule::table('erp_finance_invoice')->where('invoice_no', $no)->count());

        $blocked = Capsule::table('erp_finance_invoice_match_log')
            ->where('invoice_id', 0)->where('source_id', 930002)->first();
        $this->assertNotNull($blocked, '拦截尝试应记 result=over 日志(invoice_id=0)');
        $this->assertSame('over', $blocked->result);
        $info = $this->service->balanceInfo('purchase_receive', 930002);
        $this->assertSame('1000.00', $info['balance'], '拦截后余额应分毫未动');
    }

    public function testTailInvoiceExactlyAtBalanceAllowed(): void
    {
        $this->requireTestDatabase();
        $this->seedReceive(930003, [[930301, '2.00', '300.00', '600.00'], [930302, '4.00', '100.00', '400.00']]);

        [$first, $error] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-T1-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930003,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '2', 'price' => '300', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($error);
        $this->invoiceIds[] = (int) $first->id;
        $this->assertNull($this->service->submit((int) $first->id));
        $this->assertNull($this->service->audit((int) $first->id, 8801));

        // 尾单恰好 = 未开票余额(1000-600=400)，应允许
        [$tail, $tailError] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-T2-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930003,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '4', 'price' => '100', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($tailError, '恰好等于余额的尾单应允许');
        $this->assertNotNull($tail);
        $this->invoiceIds[] = (int) $tail->id;
        $this->assertSame('400.00', $tail->amount);
        $this->assertNull($this->service->submit((int) $tail->id));
        $this->assertNull($this->service->audit((int) $tail->id, 8801));

        $info = $this->service->balanceInfo('purchase_receive', 930003);
        $this->assertSame('1000.00', $info['invoiced_total'], '两张发票恰好开满');
        $this->assertSame('0.00', $info['balance']);

        $last = Capsule::table('erp_finance_invoice_match_log')
            ->where('invoice_id', (int) $tail->id)->first();
        $this->assertSame('ok', $last->result, '恰好在余额上应记 ok');
    }

    public function testVoidRestoresBalance(): void
    {
        $this->requireTestDatabase();
        $this->seedReceive(930004, [[930401, '10.00', '100.00', '1000.00']]);

        [$invoice, $error] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-V1-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930004,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '10', 'price' => '100', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($error);
        $this->invoiceIds[] = (int) $invoice->id;
        $this->assertNull($this->service->submit((int) $invoice->id));
        $this->assertNull($this->service->audit((int) $invoice->id, 8801));
        $this->assertSame('0.00', $this->service->balanceInfo('purchase_receive', 930004)['balance']);

        $this->assertNull($this->service->void((int) $invoice->id, '测试作废'));
        $invoice->refresh();
        $this->assertSame('voided', $invoice->status);
        $this->assertSame('测试作废', $invoice->void_reason);
        $this->assertSame('1000.00', $this->service->balanceInfo('purchase_receive', 930004)['balance'], '作废后余额应回补');

        // 再次作废应报错；余额回补后可重新开满
        $this->assertNotNull($this->service->void((int) $invoice->id, '重复作废'));
        [$again, $againError] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-V2-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9001,
            'biz_type' => 'purchase_receive',
            'source_id' => 930004,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '10', 'price' => '100', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($againError, '回补后重新开票应通过');
        $this->assertNotNull($again);
        $this->invoiceIds[] = (int) $again->id;
    }

    public function testSourceSupplierMismatchBlocked(): void
    {
        $this->requireTestDatabase();
        $this->seedReceive(930001, [[930101, '2.00', '300.00', '600.00']]);

        [$invoice, $error] = $this->service->storeDraft([
            'invoice_no' => uniqid('P0T-M1-'),
            'type' => 'ap',
            'customer_id' => 0,
            'supplier_id' => 9002, // 与收货单 supplier_id=9001 不一致
            'biz_type' => 'purchase_receive',
            'source_id' => 930001,
            'invoice_date' => '',
            'currency' => 'CNY',
            'items' => [['quantity' => '1', 'price' => '100', 'tax_rate' => '0.00']],
        ]);
        $this->assertNull($invoice);
        $this->assertStringContainsString('供应商与收货单不一致', (string) $error);
    }

    public function testCalcLineAmountsHalfUpBoundary(): void
    {
        // 半值进位：0.50 × 0.13 = 0.0650 → half-up(2位) = 0.07
        $line = $this->service->calcLineAmounts('1', '0.50', '0.13');
        $this->assertSame('0.50', $line['amount']);
        $this->assertSame('0.07', $line['tax_amount']);
        $this->assertSame('0.57', $line['line_total']);

        // 100.005 类边界：1.0050 → 1.01（字符串 bc，不经 float 的 1.0049... 尾噪）
        $bound = $this->service->calcLineAmounts('100.50', '0.01', '0.50');
        $this->assertSame('1.01', $bound['amount']);
        $this->assertSame('0.51', $bound['tax_amount'], '0.5050 半值应进位 0.51');
        $this->assertSame('1.52', $bound['line_total']);

        $line2 = $this->service->calcLineAmounts('100.50', '0.01', '0.13');
        $this->assertSame('1.01', $line2['amount']);
        $this->assertSame('0.13', $line2['tax_amount']);
        $this->assertSame('1.14', $line2['line_total']);
    }

    /** 播种采购收货单头+明细（amount 已含金额，来源总额 = Σ明细 amount） */
    private function seedReceive(int $receiveId, array $items): void
    {
        Capsule::table('erp_purchase_receive')->insert([
            'id' => $receiveId,
            'code' => 'P0T-RC-' . $receiveId,
            'order_id' => 0,
            'supplier_id' => 9001,
            'warehouse_id' => 9001,
        ]);
        foreach ($items as [$itemId, $qty, $price, $amount]) {
            Capsule::table('erp_purchase_receive_item')->insert([
                'id' => $itemId,
                'receive_id' => $receiveId,
                'product_id' => 900101,
                'quantity' => $qty,
                'price' => $price,
                'amount' => $amount,
            ]);
        }
    }

    private function requireTestDatabase(): void
    {
        if (getenv('TEST_DB_DATABASE') === false || getenv('TEST_DB_DATABASE') === '') {
            $this->markTestSkipped('未配置 TEST_DB_DATABASE 等 TEST_DB_* 环境变量，跳过数据库断言');
        }
        self::bootCapsule();
        try {
            Capsule::connection()->select('SELECT 1');
            self::$booted = true;
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
