<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/**
 * F1/F2 多组织与账套 + 集团合并报表集成测试共享脚手架（抽象基类，本身不执行）。
 *
 * 表处理双模式（同 F3CostingScaffold）：
 *  · 5 张 F1/F2 自有表（database/p0_f1f2.sql，scratch 不回仓）缺表时按原 DDL 全量创建
 *    （CREATE TABLE IF NOT EXISTS 可重放），由本类创建的 tearDown 删除；
 *  · 存量表（database/install.sql）从不创建，仅做条件守卫增量 ALTER：voucher +
 *    ledger_id 列，三张单体快照表 +company_id/+ledger_id 且期间唯一键换成账套维度
 *    （uk_ledger_period / uk_ledger_report），保证 CI 上多账套关闭同一期间不冲突；
 *    依赖表缺失则整类跳过（提示先导入 install.sql）。
 * 清理只按固定 code/id 删除本套件写入行，绝不 TRUNCATE（测试库多套件共享）；
 * 金额一律字符串等值断言（服务返回 bcmath 字符串，raw 读 DECIMAL 亦为字符串）。
 */
abstract class F12MultiCompanyScaffold extends IntegrationTestCase
{
    // ---- 固定 fixture id（清理与断言共用；install.sql 无主数据种子，id 段安全）----
    /** 1002 银行存款（资产） */
    protected const ACC_BANK_ID = 100001;
    /** 3001 实收资本（权益） */
    protected const ACC_CAPITAL_ID = 100002;
    /** 4001 主营业务收入 */
    protected const ACC_REVENUE_ID = 100003;
    /** 5001 管理费用 */
    protected const ACC_EXPENSE_ID = 100004;
    /** @var list<int> */
    protected const FIXED_ACCOUNT_IDS = [
        self::ACC_BANK_ID, self::ACC_CAPITAL_ID, self::ACC_REVENUE_ID, self::ACC_EXPENSE_ID,
    ];
    protected const CUR_CNY_ID = 200001;
    protected const CUR_USD_ID = 200002;
    protected const RATE_USD_CNY_ID = 300001;
    /** 本套件组织 code（清理/幂等复用共用） */
    protected const COMPANY_CODES = ['MAIN', 'SUBUS'];

    /** 依赖表（install.sql 域）— 只读使用，绝不创建 */
    protected const DEP_TABLES = [
        'erp_finance_voucher',
        'erp_finance_voucher_item',
        'erp_finance_account',
        'erp_finance_currency',
        'erp_finance_exchange_rate',
        'erp_finance_balance_sheet',
        'erp_finance_profit',
        'erp_finance_cash_flow',
    ];

    /** 存量快照表：补列 + 唯一键换账套维度（[表, 旧唯一键, 新唯一键, 年列, 月列]） */
    private const SNAPSHOT_KEY_SWAPS = [
        ['erp_finance_profit', 'uk_year_month', 'uk_ledger_period', 'year', 'month'],
        ['erp_finance_balance_sheet', 'uk_report_period', 'uk_ledger_report', 'report_year', 'report_month'],
        ['erp_finance_cash_flow', 'uk_report_period', 'uk_ledger_report', 'report_year', 'report_month'],
    ];

    /** 本类 setUp 中缺表创建的表（tearDown 删除） */
    protected array $createdTables = [];

    /** 运行时解析的币种 id（code => id；install.sql 已种子 CNY/USD 时复用种子行 id） */
    protected array $currencyIdByCode = [];

    /** 本套件实际插入的币种 fallback id（仅这些在 tearDown 删除；种子行永不动） */
    protected array $insertedCurrencyIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->createdTables = [];
        $this->ensureSchema();
        $missing = array_values(array_filter(
            self::DEP_TABLES,
            fn (string $t): bool => !Capsule::schema()->hasTable($t)
        ));
        if ($missing !== []) {
            self::markTestSkipped('缺少依赖表: ' . implode(', ', $missing) . '（请先导入 database/install.sql）');
        }
        $this->resetFixtures();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            $this->resetFixtures();
            $this->currencyIdByCode = [];
            $this->insertedCurrencyIds = [];
            foreach (array_reverse($this->createdTables) as $table) {
                $this->dropTableIfExists($table);
            }
            $this->createdTables = [];
        }
        parent::tearDown();
    }

    // ---- 模式建立：自有表全量创建 + 存量表条件 ALTER（幂等） ----

    /** @return array<string, string> 表名 => 原 DDL（镜像 p0_f1f2.sql，去掉注释行） */
    private static function ownTableDdl(): array
    {
        return [
            'erp_company' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `erp_company` (
  `id`            BIGINT UNSIGNED NOT NULL,
  `code`          VARCHAR(50)     NOT NULL,
  `name`          VARCHAR(200)    NOT NULL,
  `parent_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `base_currency` VARCHAR(10)     NOT NULL DEFAULT 'CNY',
  `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `remark`        VARCHAR(500)    NOT NULL DEFAULT '',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            'erp_finance_ledger' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `erp_finance_ledger` (
  `id`            BIGINT UNSIGNED NOT NULL,
  `company_id`    BIGINT UNSIGNED NOT NULL,
  `code`          VARCHAR(50)     NOT NULL,
  `name`          VARCHAR(200)    NOT NULL,
  `currency`      VARCHAR(10)     NOT NULL DEFAULT 'CNY',
  `is_default`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `remark`        VARCHAR(500)    NOT NULL DEFAULT '',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_code` (`company_id`, `code`),
  KEY `idx_company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            'erp_finance_period' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `erp_finance_period` (
  `id`         BIGINT UNSIGNED NOT NULL,
  `ledger_id`  BIGINT UNSIGNED NOT NULL,
  `period`     VARCHAR(7)      NOT NULL,
  `status`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `opened_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at`  DATETIME        DEFAULT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ledger_period` (`ledger_id`, `period`),
  KEY `idx_ledger_id` (`ledger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            'erp_finance_consolidation_report' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `erp_finance_consolidation_report` (
  `id`               BIGINT UNSIGNED NOT NULL,
  `company_id`       BIGINT UNSIGNED NOT NULL,
  `report_year`      SMALLINT UNSIGNED NOT NULL,
  `report_month`     TINYINT UNSIGNED NOT NULL,
  `base_currency`    VARCHAR(10)     NOT NULL DEFAULT 'CNY',
  `status`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_assets`     DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `total_liabilities` DECIMAL(14,2)  NOT NULL DEFAULT 0,
  `total_equity`     DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `revenue`          DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `net_profit`       DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `report_data`      JSON            DEFAULT NULL,
  `issued_at`        DATETIME        DEFAULT NULL,
  `remark`           VARCHAR(500)    NOT NULL DEFAULT '',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_period` (`company_id`, `report_year`, `report_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            'erp_finance_elimination_item' => <<<'SQL'
CREATE TABLE IF NOT EXISTS `erp_finance_elimination_item` (
  `id`           BIGINT UNSIGNED NOT NULL,
  `report_id`    BIGINT UNSIGNED NOT NULL,
  `account_code` VARCHAR(50)     NOT NULL,
  `summary`      VARCHAR(500)    NOT NULL DEFAULT '',
  `debit_amount` DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `credit_amount` DECIMAL(14,2)  NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];
    }

    /** 缺表建表（原 DDL）；存量表按 INFORMATION_SCHEMA 条件守卫增量 ALTER（镜像 p0_f1f2.sql 过程体） */
    private function ensureSchema(): void
    {
        $schema = Capsule::schema();
        foreach (self::ownTableDdl() as $table => $ddl) {
            if (!$schema->hasTable($table)) {
                self::runDdl($ddl);
                $this->createdTables[] = $table;
            }
        }
        if ($schema->hasTable('erp_finance_voucher') && !self::hasColumn('erp_finance_voucher', 'ledger_id')) {
            self::runDdl('ALTER TABLE `erp_finance_voucher` ADD COLUMN `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `code`, ADD KEY `idx_ledger_id` (`ledger_id`)');
        }
        foreach (self::SNAPSHOT_KEY_SWAPS as [$table, $oldIndex, $newIndex, $yearCol, $monthCol]) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            if (!self::hasColumn($table, 'ledger_id')) {
                self::runDdl("ALTER TABLE `$table` ADD COLUMN `company_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`, ADD COLUMN `ledger_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `company_id`");
            }
            if (self::hasIndex($table, $oldIndex) && !self::hasIndex($table, $newIndex)) {
                self::runDdl("ALTER TABLE `$table` DROP INDEX `$oldIndex`, ADD UNIQUE KEY `$newIndex` (`ledger_id`, `$yearCol`, `$monthCol`)");
            }
        }
    }

    private static function runDdl(string $sql): void
    {
        Capsule::connection()->getPdo()->exec($sql);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $rows = Capsule::connection()->select(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, $column]
        );

        return $rows !== [];
    }

    private static function hasIndex(string $table, string $index): bool
    {
        $rows = Capsule::connection()->select(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $index]
        );

        return $rows !== [];
    }

    // ---- 状态清理（只动本套件写入行；按固定 code/id 级联删除，幂等） ----

    protected function resetFixtures(): void
    {
        $db = Capsule::connection();
        $companyIds = array_map('intval', $db->table('erp_company')
            ->whereIn('code', self::COMPANY_CODES)->pluck('id')->all());
        $reportIds = $companyIds === [] ? [] : array_map('intval', $db->table('erp_finance_consolidation_report')
            ->whereIn('company_id', $companyIds)->pluck('id')->all());
        $ledgerIds = $companyIds === [] ? [] : array_map('intval', $db->table('erp_finance_ledger')
            ->whereIn('company_id', $companyIds)->pluck('id')->all());
        $voucherIds = $ledgerIds === [] ? [] : array_map('intval', $db->table('erp_finance_voucher')
            ->whereIn('ledger_id', $ledgerIds)->pluck('id')->all());

        $tryDelete = function (string $table, string $column, array $ids): void {
            if ($ids === []) {
                return;
            }
            try {
                Capsule::table($table)->whereIn($column, $ids)->delete();
            } catch (Throwable) {
                // 清理失败仅记录，不掩盖测试结论
            }
        };
        $tryDelete('erp_finance_elimination_item', 'report_id', $reportIds);
        $tryDelete('erp_finance_consolidation_report', 'id', $reportIds);
        $tryDelete('erp_finance_voucher_item', 'voucher_id', $voucherIds);
        $tryDelete('erp_finance_voucher', 'id', $voucherIds);
        $tryDelete('erp_finance_period', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_balance_sheet', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_profit', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_cash_flow', 'ledger_id', $ledgerIds);
        $tryDelete('erp_finance_ledger', 'id', $ledgerIds);
        $tryDelete('erp_company', 'id', $companyIds);
        $tryDelete('erp_finance_account', 'id', self::FIXED_ACCOUNT_IDS);
        $tryDelete('erp_finance_exchange_rate', 'id', [self::RATE_USD_CNY_ID]);
        if ($this->insertedCurrencyIds !== []) {
            $tryDelete('erp_finance_currency', 'id', $this->insertedCurrencyIds);
        }
    }

    // ---- fixture 写入（镜像 /tmp/f1f2_smoke.php 的原始 INSERT 契约） ----

    protected function insertAccount(int $id, string $code, string $name, int $type): void
    {
        Capsule::table('erp_finance_account')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'direction' => $type === 1 ? 1 : 2,
            'status' => 1,
        ]);
    }

    /**
     * 保证 code 币种行存在：已存在（install.sql 种子 CNY/USD，id 为 61e16…）则复用其 id；
     * 否则以 fallbackId 插入并记录待清理。返回实际 id（汇率写入必须用返回值，勿用常量）。
     */
    protected function insertCurrency(int $fallbackId, string $code): int
    {
        $existing = Capsule::table('erp_finance_currency')->where('code', $code)->first();
        if ($existing !== null) {
            $id = (int) $existing->id;
            $this->currencyIdByCode[$code] = $id;

            return $id;
        }
        Capsule::table('erp_finance_currency')->insert([
            'id' => $fallbackId,
            'code' => $code,
            'name' => $code,
            'is_base' => 0,
        ]);
        $this->currencyIdByCode[$code] = $fallbackId;
        $this->insertedCurrencyIds[] = $fallbackId;

        return $fallbackId;
    }

    /** 期末汇率 USD→CNY 7.1（2026-08-31，固定 id；币种侧为 insertCurrency 解析出的实际 id） */
    protected function insertRateUsdToCny(): void
    {
        $fromId = $this->currencyIdByCode['USD'] ?? null;
        $toId = $this->currencyIdByCode['CNY'] ?? null;
        if ($fromId === null || $toId === null) {
            throw new \RuntimeException('insertRateUsdToCny 前须先 insertCurrency(USD/CNY)');
        }
        Capsule::table('erp_finance_exchange_rate')->insert([
            'id' => self::RATE_USD_CNY_ID,
            'from_currency_id' => $fromId,
            'to_currency_id' => $toId,
            'rate' => '7.100000',
            'effective_date' => '2026-08-31',
        ]);
    }

    /**
     * 建账套维度凭证并审核（写 erp_finance_voucher.ledger_id）。
     *
     * @param list<array{account_id:int, debit_amount?:string, credit_amount?:string, summary?:string}> $entries
     */
    protected function makeAuditedVoucher(int $ledgerId, string $date, string $remark, array $entries): void
    {
        $de = new \app\service\finance\DoubleEntryService();
        $voucher = $de->createVoucher(['voucher_date' => $date, 'remark' => $remark], $entries, $ledgerId);
        $de->audit((int) $voucher->id);
    }
}
