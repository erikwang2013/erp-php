<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\common\SnowflakeService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * F6（票据台账 + 银企对账）共用脚手架：真库集成（TEST_DB_* 契约）。
 *
 * 表门槛：F6 三张自有表来自 database/f6_fund.sql（scratch 脚本，未入 install.sql），
 * 缺失即整类跳过（先执行 mysql < database/f6_fund.sql 建表）；依赖表来自 install.sql。
 * 数据隔离：全部种子行带 'T-F6-' 标记 + 独立 snowflake 账户/单据，tearDown 按跟踪
 * id/批次清理；对账测试各用例独立开账户，杜绝跨用例候选串扰。
 */
abstract class F6FundScaffold extends IntegrationTestCase
{
    /** F6 自有表（f6_fund.sql）——缺失即跳过 */
    protected const F6_TABLES = [
        'erp_finance_bill',
        'erp_finance_bank_statement',
        'erp_finance_bank_recon_match',
    ];
    /** 依赖表（install.sql）——只读/种子使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_finance_bank_account',
        'erp_finance_receipt',
        'erp_finance_cash_journal',
    ];

    /** 测试数据行标记前缀（票号/批次/摘要/账户名共用） */
    protected const MARKER = 'T-F6-';

    /** @var int[] */
    protected array $accountIds = [];
    /** @var int[] */
    protected array $billIds = [];
    /** @var int[] */
    protected array $receiptIds = [];
    /** @var int[] */
    protected array $journalIds = [];
    /** @var string[] 已导入的对账批次号（按批次清理流水行） */
    protected array $statementBatches = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $missingF6 = array_values(array_filter(self::F6_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missingF6 !== []) {
            self::markTestSkipped('缺少 F6 表: ' . implode(', ', $missingF6) . '（请先执行 mysql < database/f6_fund.sql 建表）');
        }
        $missingDep = array_values(array_filter(self::DEP_TABLES, fn (string $t): bool => !Capsule::schema()->hasTable($t)));
        if ($missingDep !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missingDep) . '（请先导入 install.sql）');
        }
        $this->accountIds = $this->billIds = $this->receiptIds = $this->journalIds = [];
        $this->statementBatches = [];
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            try {
                // 核销轨两侧都源于本脚手架数据：按跟踪日记账/批次流水清理
                $stmtIds = [];
                if ($this->statementBatches !== []) {
                    $stmtIds = Capsule::table('erp_finance_bank_statement')
                        ->whereIn('import_batch', $this->statementBatches)->pluck('id')->all();
                }
                $this->deleteIn('erp_finance_bank_recon_match', 'cash_journal_id', $this->journalIds);
                $this->deleteIn('erp_finance_bank_recon_match', 'statement_id', $stmtIds);
                $this->deleteIn('erp_finance_bank_statement', 'id', $stmtIds);
                $this->deleteIn('erp_finance_cash_journal', 'id', $this->journalIds);
                $this->deleteIn('erp_finance_receipt', 'id', $this->receiptIds);
                $this->deleteIn('erp_finance_bill', 'id', $this->billIds);
                $this->deleteIn('erp_finance_bank_account', 'id', $this->accountIds);
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

    /** 种子银行账户（启用，余额 0），返回账户 id 并登记清理 */
    protected function seedBankAccount(string $tag): int
    {
        $id = $this->nextId();
        Capsule::table('erp_finance_bank_account')->insert([
            'id' => $id,
            'name' => self::MARKER . $tag,
            'account_number' => self::MARKER . $tag,
            'bank_name' => '测试银行',
            'balance' => '0.00',
            'status' => 1,
        ]);
        $this->accountIds[] = $id;

        return $id;
    }

    /**
     * 种子已审核收款单（receipt 来源票据登记依赖），返回 id。
     * code 唯一：票号式拼接 snowflake id。
     */
    protected function seedReceipt(int $bankAccountId, string $amount, int $status = 1): int
    {
        $id = $this->nextId();
        Capsule::table('erp_finance_receipt')->insert([
            'id' => $id,
            'code' => self::MARKER . 'R' . $id,
            'customer_id' => $this->nextId(),
            'bank_account_id' => $bankAccountId,
            'amount' => $amount,
            'method' => 'bank',
            'status' => $status,
            'remark' => self::MARKER . 'receipt-fixture',
        ]);
        $this->receiptIds[] = $id;

        return $id;
    }

    /** 种子现金日记账行（对账目标），返回 id 并登记清理 */
    protected function seedJournal(int $accountId, string $date, int $direction, string $amount, string $summary = ''): int
    {
        $id = $this->nextId();
        Capsule::table('erp_finance_cash_journal')->insert([
            'id' => $id,
            'bank_account_id' => $accountId,
            'direction' => $direction,
            'amount' => $amount,
            'balance' => '0.00',
            'source_type' => 'payment',
            'source_id' => 0,
            'summary' => $summary !== '' ? $summary : self::MARKER . 'journal-fixture',
            'journal_date' => $date,
        ]);
        $this->journalIds[] = $id;

        return $id;
    }

    /** 一次导入批次号（同批唯一；跨用例也不会碰撞） */
    protected function newBatch(): string
    {
        return self::MARKER . 'B' . $this->nextId();
    }

    /** 调用 importStatement 并把批次登记进清理清单 */
    protected function importBatch(int $accountId, string $batch, array $rows): array
    {
        $this->statementBatches[] = $batch;

        return $this->reconService()->importStatement($accountId, $batch, $rows);
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

    /** @var \app\service\finance\BankReconService|null 惰性单例 */
    private static ?\app\service\finance\BankReconService $recon = null;

    protected function reconService(): \app\service\finance\BankReconService
    {
        return self::$recon ??= new \app\service\finance\BankReconService();
    }

    /** @var \app\service\finance\FinanceBillService|null 惰性单例 */
    private static ?\app\service\finance\FinanceBillService $bills = null;

    protected function billService(): \app\service\finance\FinanceBillService
    {
        return self::$bills ??= new \app\service\finance\FinanceBillService();
    }
}
