<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use app\model\TaxInputInvoice;
use app\service\tax\TaxInvoicePoolService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * P2-2 F5 进项发票池共享脚手架：真库集成（TEST_DB_* 契约）。
 *
 * 表门槛：erp_tax_input_invoice 来自 database/f5_tax.sql，缺失即整类跳过。
 * 数据隔离：种子行带 'T-F5-' 标记 + snowflake id，tearDown 按跟踪 id 清理。
 */
abstract class F5TaxScaffold extends IntegrationTestCase
{
    /** F5 自有表（f5_tax.sql）——缺失即跳过 */
    protected const F5_TABLES = [
        'tax_input_invoice',
    ];

    /** 测试数据行标记前缀（发票号/客户编码共用） */
    protected const MARKER = 'T-F5-';

    /** @var int[] */
    protected array $poolIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $missingF5 = array_values(array_filter(self::F5_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missingF5 !== []) {
            self::markTestSkipped('缺少 F5 表: ' . implode(', ', $missingF5) . '（请先执行 mysql < database/f5_tax.sql 建表）');
        }
        $this->poolIds = [];
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            try {
                $this->deleteIn('tax_input_invoice', 'id', $this->poolIds);
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

}
