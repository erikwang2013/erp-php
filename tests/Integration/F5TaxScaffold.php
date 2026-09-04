<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\model\TaxInputInvoice;
use app\service\tax\EInvoiceService;
use app\service\tax\TaxInvoicePoolService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * P2-2 F5（进项发票池 + 数电票开票出口）共用脚手架：真库集成（TEST_DB_* 契约）。
 *
 * 表门槛：erp_tax_input_invoice / erp_tax_issue_log 来自 database/f5_tax.sql，
 * erp_finance_invoice 的 electronic_no / issue_status 两列也由该脚本守卫 ALTER 添加，
 * 任一缺失即整类跳过；依赖表来自 install.sql。
 * 数据隔离：种子行带 'T-F5-' 标记 + snowflake id，tearDown 按跟踪 id 清理
 * （先删开票日志，再删发票/池行/客户，顺序即引用方向）。
 */
abstract class F5TaxScaffold extends IntegrationTestCase
{
    /** F5 自有表（f5_tax.sql）——缺失即跳过 */
    protected const F5_TABLES = [
        'erp_tax_input_invoice',
        'erp_tax_issue_log',
    ];
    /** 依赖表（install.sql）——只读/种子使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_finance_invoice',
        'erp_customer',
    ];

    /** 测试数据行标记前缀（发票号/客户编码共用） */
    protected const MARKER = 'T-F5-';

    /** @var int[] */
    protected array $poolIds = [];
    /** @var int[] */
    protected array $invoiceIds = [];
    /** @var int[] */
    protected array $customerIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $missingF5 = array_values(array_filter(self::F5_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missingF5 !== []) {
            self::markTestSkipped('缺少 F5 表: ' . implode(', ', $missingF5) . '（请先执行 mysql < database/f5_tax.sql 建表）');
        }
        $missingDep = array_values(array_filter(self::DEP_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missingDep !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missingDep) . '（请先导入 install.sql）');
        }
        if (!Capsule::schema()->hasColumns('erp_finance_invoice', ['electronic_no', 'issue_status'])) {
            self::markTestSkipped('erp_finance_invoice 缺 electronic_no/issue_status 列（请先执行 mysql < database/f5_tax.sql 建表）');
        }
        $this->poolIds = $this->invoiceIds = $this->customerIds = [];
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            try {
                // 开票日志先于发票行清理（日志引用发票 id）
                $this->deleteIn('erp_tax_issue_log', 'invoice_id', $this->invoiceIds);
                $this->deleteIn('erp_tax_input_invoice', 'id', $this->poolIds);
                $this->deleteIn('erp_finance_invoice', 'id', $this->invoiceIds);
                $this->deleteIn('erp_customer', 'id', $this->customerIds);
            } catch (Throwable) {
            }
        }
        parent::tearDown();
    }

    /** snowflake 自增键（非自增主键约定） */
    protected function nextId(): int
    {
        return (int) SnowflakeService::generate();
    }

    /** 种子客户（code 唯一），返回客户 id 并登记清理 */
    protected function seedCustomer(string $tag): int
    {
        $id = $this->nextId();
        Capsule::table('erp_customer')->insert([
            'id' => $id,
            'code' => self::MARKER . 'C' . $id,
            'name' => '测试客户' . $tag,
            'level_id' => 0,
            'status' => 1,
            'remark' => self::MARKER . 'customer-fixture',
        ]);
        $this->customerIds[] = $id;

        return $id;
    }

    /**
     * 种子已审核应收(ar)发票（开票/红冲目标），金额三栏字符串直写。
     * invoice_no 唯一（snowflake 拼接），返回 id 并登记清理。
     */
    protected function seedAuditedArInvoice(int $customerId, string $amount, string $untaxed = '', string $tax = ''): int
    {
        $id = $this->nextId();
        Capsule::table('erp_finance_invoice')->insert([
            'id' => $id,
            'invoice_no' => self::MARKER . 'I' . $id,
            'type' => 'ar',
            'customer_id' => $customerId,
            'supplier_id' => 0,
            'biz_type' => 'manual',
            'source_id' => 0,
            'invoice_date' => date('Y-m-d'),
            'untaxed_amount' => $untaxed !== '' ? $untaxed : bc_norm(bcsub($amount, $tax === '' ? '0' : $tax, 2)),
            'tax_amount' => $tax !== '' ? $tax : '0.00',
            'amount' => $amount,
            'currency' => 'CNY',
            'status' => 'audited',
            'remark' => self::MARKER . 'invoice-fixture',
        ]);
        $this->invoiceIds[] = $id;

        return $id;
    }

    /** 种子任意状态/类型发票（guards 负路径），返回 id 并登记清理 */
    protected function seedInvoice(array $overrides = []): int
    {
        $id = $this->nextId();
        Capsule::table('erp_finance_invoice')->insert(array_merge([
            'id' => $id,
            'invoice_no' => self::MARKER . 'I' . $id,
            'type' => 'ar',
            'customer_id' => 0,
            'supplier_id' => 0,
            'biz_type' => 'manual',
            'source_id' => 0,
            'invoice_date' => date('Y-m-d'),
            'untaxed_amount' => '1000.00',
            'tax_amount' => '130.00',
            'amount' => '1130.00',
            'currency' => 'CNY',
            'status' => 'draft',
            'remark' => self::MARKER . 'invoice-fixture',
        ], $overrides));
        $this->invoiceIds[] = $id;

        return $id;
    }

    /** 池登记默认参数（码/号均唯一） */
    protected function poolData(array $overrides = []): array
    {
        return array_merge([
            'invoice_code' => '044031900111',
            'invoice_no' => self::MARKER . 'N' . $this->nextId(),
            'issue_date' => date('Y-m-d'),
            'seller_name' => '测试销售方',
            'seller_tax_no' => '81330100TEST1', // 非 9 开头 → Mock 验真通过
            'buyer_name' => '测试购买方',
            'buyer_tax_no' => '5001',
            'untaxed_amount' => '1000.00',
            'tax_amount' => '130.00',
            'amount' => '1130.00',
            'source' => 'manual',
            'remark' => '',
        ], $overrides);
    }

    /** 经服务登记一张池发票，追踪 id 并返回模型 */
    protected function registerPool(array $overrides = []): TaxInputInvoice
    {
        [$row, $err] = $this->poolService()->registerOne($this->poolData($overrides));
        $this->assertNull($err, '登记应成功: ' . (string) $err);
        $this->assertNotNull($row);
        $this->poolIds[] = (int) $row->id;

        return $row;
    }

    protected function assertBcEquals(string $expected, string $actual, string $label = ''): void
    {
        $this->assertSame(
            0,
            bccomp(bc_norm($expected), bc_norm($actual), 6),
            $label . sprintf(' 期望=%s 实际=%s', bc_norm($expected), bc_norm($actual))
        );
    }

    protected function assertRowCount(string $table, array $where, int $expected, string $label = ''): void
    {
        $this->assertSame($expected, Capsule::table($table)->where($where)->count(), $label);
    }

    /** 通用删除（tearDown 内部；空 id 列表直接跳过） */
    private function deleteIn(string $table, string $column, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        Capsule::table($table)->whereIn($column, $ids)->delete();
    }

    /** @var TaxInvoicePoolService|null 惰性单例 */
    private static ?TaxInvoicePoolService $pool = null;

    protected function poolService(): TaxInvoicePoolService
    {
        return self::$pool ??= new TaxInvoicePoolService();
    }

    /** @var EInvoiceService|null 惰性单例（默认 Mock 适配器） */
    private static ?EInvoiceService $einvoice = null;

    protected function einvoiceService(): EInvoiceService
    {
        return self::$einvoice ??= new EInvoiceService();
    }
}
