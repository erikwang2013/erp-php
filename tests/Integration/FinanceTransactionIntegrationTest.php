<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\FinanceArAp;
use app\model\FinanceBankAccount;
use app\model\FinanceCashJournal;
use app\model\FinanceReceipt;
use app\model\FinanceSettlement;
use app\service\finance\FinanceService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * 财务事务集成测试：FinanceService 真实 DB 事务路径（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 背景：AccountBalanceService / ConsolidationService / PeriodCloseService 为
 * 结构占位（无 DB 访问），真正的 DB 事务与行锁（lockForUpdate）在
 * FinanceService::settleReceipt / recordJournal。本类直接覆盖真实事务路径：
 * 1. 核销超余额 → 异常 → 事务回滚（应收未变、无核销记录）；
 * 2. 日记账余额不足 → 异常 → 事务回滚（余额未变、无日记账）；
 * 3. commit 持久化（对照回滚，证明事务语义真实生效）；
 * 4. createAr 同源单据重复 → 业务异常拒绝；
 * 5. 并发核销同一应收（pcntl_fork 双进程）：行锁下恰好一方成功，总额不超。
 *
 * 表处理双模式：测试库缺表时按 install.sql 结构创建并在 tearDown 删除；
 * 已有真实表（CI 已导入 install.sql）则直接使用，仅清理本类写入的行。
 */
#[Group('integration')]
class FinanceTransactionIntegrationTest extends IntegrationTestCase
{
    private const TABLES = [
        'erp_finance_ar_ap',
        'erp_finance_receipt',
        'erp_finance_settlement',
        'erp_finance_bank_account',
        'erp_finance_cash_journal',
    ];

    /** 本类创建的表（tearDown 需删除）；预存在表只清理测试行 */
    private array $createdTables = [];

    /** 测试写入行的主键（预存在表模式下按 id 清理） */
    private array $testIds = [];

    private int $idCursor = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->ensureFinanceTables();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            foreach (self::TABLES as $table) {
                if (in_array($table, $this->createdTables, true)) {
                    self::dropTableIfExists($table);
                    continue;
                }
                try {
                    if (Capsule::schema()->hasTable($table) && !empty($this->testIds)) {
                        Capsule::table($table)->whereIn('id', $this->testIds)->delete();
                    }
                } catch (Throwable) {
                    // 清理失败仅记录，不改变测试结论
                }
            }
            // 服务内部经 Snowflake 生成的行 id 未纳入 testIds，按外键列兜底清理
            foreach ([
                'erp_finance_settlement' => 'ar_ap_id',
                'erp_finance_cash_journal' => 'bank_account_id',
            ] as $table => $column) {
                if (in_array($table, $this->createdTables, true)) {
                    continue;
                }
                try {
                    if (Capsule::schema()->hasTable($table) && !empty($this->testIds)) {
                        Capsule::table($table)->whereIn($column, $this->testIds)->delete();
                    }
                } catch (Throwable) {
                    // 清理失败仅记录，不改变测试结论
                }
            }
        }
        parent::tearDown();
    }

    private function ensureFinanceTables(): void
    {
        $schema = Capsule::schema();
        $defs = [
            'erp_finance_ar_ap' => static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->tinyInteger('type');
                $t->unsignedBigInteger('partner_id');
                $t->string('source_type', 30)->default('');
                $t->unsignedBigInteger('source_id')->default(0);
                $t->decimal('amount', 12, 2)->default(0);
                $t->decimal('settled_amount', 12, 2)->default(0);
                $t->tinyInteger('status')->default(0);
                $t->date('due_date')->nullable();
                $t->timestamps();
                $t->unique(['source_type', 'source_id'], 'uk_source');
            },
            'erp_finance_receipt' => static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->string('code', 50);
                $t->unsignedBigInteger('customer_id');
                $t->unsignedBigInteger('bank_account_id');
                $t->decimal('amount', 12, 2)->default(0);
                $t->string('method', 20)->default('bank');
                $t->tinyInteger('status')->default(0);
                $t->string('remark', 500)->default('');
                $t->dateTime('received_at')->nullable();
                $t->timestamps();
                $t->unique('code', 'uk_code');
            },
            'erp_finance_settlement' => static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->unsignedBigInteger('ar_ap_id');
                $t->unsignedBigInteger('receipt_payment_id');
                $t->tinyInteger('type');
                $t->decimal('amount', 12, 2)->default(0);
                $t->dateTime('settled_at')->nullable();
                $t->timestamps();
            },
            'erp_finance_bank_account' => static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->string('name', 100);
                $t->string('account_number', 500)->default('');
                $t->string('bank_name', 200)->default('');
                $t->decimal('balance', 12, 2)->default(0);
                $t->tinyInteger('status')->default(1);
                $t->timestamps();
            },
            'erp_finance_cash_journal' => static function (Blueprint $t): void {
                $t->unsignedBigInteger('id')->primary();
                $t->unsignedBigInteger('bank_account_id');
                $t->tinyInteger('direction');
                $t->decimal('amount', 12, 2)->default(0);
                $t->decimal('balance', 12, 2)->default(0);
                $t->string('source_type', 30)->default('');
                $t->unsignedBigInteger('source_id')->default(0);
                $t->string('summary', 500)->default('');
                $t->date('journal_date');
                $t->timestamp('created_at')->nullable();
            },
        ];

        foreach ($defs as $table => $blueprint) {
            if ($schema->hasTable($table)) {
                continue;
            }
            $schema->create($table, $blueprint);
            $this->createdTables[] = $table;
        }
    }

    private function nextId(): int
    {
        $base = random_int(1, PHP_INT_MAX - 1000);
        $id = $base + $this->idCursor++;
        $this->testIds[] = $id;
        return $id;
    }

    private function createAr(float $amount, int $partnerId, string $sourceType = 'it_order'): int
    {
        $ar = new FinanceArAp();
        $ar->id = $this->nextId();
        $ar->type = 1;
        $ar->partner_id = $partnerId;
        $ar->source_type = $sourceType;
        $ar->source_id = $this->nextId();
        $ar->amount = $amount;
        $ar->settled_amount = 0;
        $ar->status = 0;
        $ar->save();
        return (int) $ar->id;
    }

    private function createReceipt(float $amount, int $customerId): int
    {
        $receipt = new FinanceReceipt();
        $receipt->id = $this->nextId();
        $receipt->code = 'IT-' . uniqid();
        $receipt->customer_id = $customerId;
        $receipt->bank_account_id = 0;
        $receipt->amount = $amount;
        $receipt->status = 1;
        $receipt->save();
        return (int) $receipt->id;
    }

    private function createBankAccount(float $balance): int
    {
        $account = new FinanceBankAccount();
        $account->id = $this->nextId();
        $account->name = 'IT测试账户';
        $account->balance = $balance;
        $account->save();
        return (int) $account->id;
    }

    public function testSettleReceiptOverSettleRollsBack(): void
    {
        $partner = $this->nextId();
        $arId = $this->createAr(100.0, $partner);
        $receiptId = $this->createReceipt(100.0, $partner);

        try {
            (new FinanceService())->settleReceipt($receiptId, $arId, 150.0);
            $this->fail('核销金额超出未核销余额时应抛出异常');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('超出未核销余额', $e->getMessage());
        }

        // 异常后事务应已回滚：应收未动、无核销记录
        $ar = FinanceArAp::find($arId);
        $this->assertEqualsWithDelta(0.0, (float) $ar->settled_amount, 0.001);
        $this->assertSame(0, (int) $ar->status);
        $this->assertSame(0, (int) FinanceSettlement::where('ar_ap_id', $arId)->count());
    }

    public function testSettleReceiptCommitPersists(): void
    {
        $partner = $this->nextId();
        $arId = $this->createAr(100.0, $partner);
        $receiptId = $this->createReceipt(100.0, $partner);

        (new FinanceService())->settleReceipt($receiptId, $arId, 60.0);

        $ar = FinanceArAp::find($arId);
        $this->assertEqualsWithDelta(60.0, (float) $ar->settled_amount, 0.001);
        $this->assertSame(1, (int) $ar->status, '部分核销状态应为 1');
        $this->assertSame(1, (int) FinanceSettlement::where('ar_ap_id', $arId)->count(), '应生成 1 条核销记录');
    }

    public function testRecordJournalInsufficientBalanceRollsBack(): void
    {
        $accountId = $this->createBankAccount(100.0);

        try {
            (new FinanceService())->recordJournal($accountId, 2, 200.0, 'it_expense', 1, '测试支出');
            $this->fail('余额不足时应抛出异常');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('账户余额不足', $e->getMessage());
        }

        // 回滚后：余额未变、无日记账
        $account = FinanceBankAccount::find($accountId);
        $this->assertEqualsWithDelta(100.0, (float) $account->balance, 0.001);
        $this->assertSame(0, (int) FinanceCashJournal::where('bank_account_id', $accountId)->count());
    }

    public function testRecordJournalCommitPersists(): void
    {
        $accountId = $this->createBankAccount(100.0);

        (new FinanceService())->recordJournal($accountId, 1, 50.0, 'it_income', 1, '测试收入');

        $account = FinanceBankAccount::find($accountId);
        $this->assertEqualsWithDelta(150.0, (float) $account->balance, 0.001, '收入后余额应增加');
        $journal = FinanceCashJournal::where('bank_account_id', $accountId)->first();
        $this->assertNotNull($journal);
        $this->assertEqualsWithDelta(150.0, (float) $journal->balance, 0.001, '日记账应记录交易后余额');
    }

    public function testCreateArDuplicateSourceRejected(): void
    {
        $partner = $this->nextId();
        $sourceId = $this->nextId();

        $arId = (new FinanceService())->createAr($partner, 'it_sale', $sourceId, 88.0);
        $this->assertGreaterThan(0, $arId);

        try {
            (new FinanceService())->createAr($partner, 'it_sale', $sourceId, 88.0);
            $this->fail('同源单据重复生成应收时应抛出异常');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('应收记录已存在', $e->getMessage());
        }

        $this->assertSame(1, (int) FinanceArAp::where('source_type', 'it_sale')->where('source_id', $sourceId)->count());
    }

    /**
     * 并发核销同一应收：100 元应收，两笔 100 元收款单各核销 60。
     * 行锁（lockForUpdate）下两进程串行执行，先到者成功后到者因余额不足失败，
     * 恰好一方成功；若无行锁则双方都读到 0 已核销、双双成功（丢失更新）。
     */
    public function testConcurrentSettleReceiptAllowsOnlyOneWinner(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('缺少 pcntl 扩展，无法执行进程级并发用例');
        }
        if (ini_get('pcov.enabled')) {
            $this->markTestSkipped('pcov 采集期间 fork 子进程会竞争覆盖率缓冲，跳过并发用例');
        }

        $partner = $this->nextId();
        $arId = $this->createAr(100.0, $partner);
        $receipt1 = $this->createReceipt(100.0, $partner);
        $receipt2 = $this->createReceipt(100.0, $partner);

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->markTestSkipped('pcntl_fork 失败，无法执行并发用例');
        }

        if ($pid === 0) {
            // 子进程：断连重建独立 PDO 连接，避免继承父进程连接
            Capsule::connection()->disconnect();
            try {
                (new FinanceService())->settleReceipt($receipt1, $arId, 60.0);
                exit(0);
            } catch (Throwable) {
                exit(1);
            }
        }

        Capsule::connection()->disconnect();
        // 让子进程先拿到行锁，保证顺序确定（顺序不影响结论，仅让用例稳定可复现）
        usleep(300000);
        $parentOk = true;
        try {
            (new FinanceService())->settleReceipt($receipt2, $arId, 60.0);
        } catch (Throwable) {
            $parentOk = false;
        }
        pcntl_waitpid($pid, $status);
        $childOk = pcntl_wexitstatus($status) === 0;

        $this->assertTrue($parentOk xor $childOk, '并发核销同一应收应恰好一方成功（行锁防止重复核销）');
        $ar = FinanceArAp::find($arId);
        $this->assertEqualsWithDelta(60.0, (float) $ar->settled_amount, 0.001, '核销总额应为 60，不得重复核销');
        $this->assertCount(1, FinanceSettlement::where('ar_ap_id', $arId)->get(), '应仅生成 1 条核销记录');
    }
}
