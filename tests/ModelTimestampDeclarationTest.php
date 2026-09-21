<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * 模型声明与真实表结构一致性回归（P4 模型层缺陷）
 *
 * 【断言的是判别子本身，不是文件名清单】
 * 本用例不抄一份"要检查哪些模型"的白名单，而是把两条**通用判别子**跑遍
 * app/model/** 的全部模型：
 *   P1  表无 `updated_at` 列 → 模型必须关闭其自动维护：
 *          public $timestamps = false;  或  public const UPDATED_AT = null;
 *   P2  表无 AUTO_INCREMENT → 模型必须 public $incrementing = false;
 * 这样将来新增的模型（哪怕是全新模块）一样被覆盖，同类缺陷不会再漏。
 *
 * 【P1 根因】表仅有 created_at（DEFAULT CURRENT_TIMESTAMP），模型按 Eloquent 默认
 * $timestamps=true 会带上不存在的 updated_at 列 → save()/update() 一律
 * SQLSTATE[42S22] Unknown column 'updated_at' in 'field list'（已实测复现）。
 * 两种写法都合法：$timestamps=false 关掉 created_at+updated_at 两者（created_at 由
 * DB 默认值兜底）；UPDATED_AT=null 只关 updated_at，保留 Eloquent 写 created_at。
 *
 * 【P2 根因】全库主键是 Snowflake 大整数、**零个 AUTO_INCREMENT 列**。缺
 * $incrementing=false 时 Eloquent 走 insertAndSetId()，把 MySQL
 * LAST_INSERT_ID()（非自增表恒为 0）回写覆盖手工设定的主键，创建响应 id 恒为 0，
 * 且后续 save() 退化成 update ... where id = 0。
 *
 * 【关于 insertGetId 的口径】修复不改变 Model::insertGetId() 的返回值：它是
 * query-builder 语义，直取 MySQL lastInsertId()，非 AUTO_INCREMENT 表恒返 0，属
 * MySQL 固有行为，与 $incrementing 无关。因此验收口径是 **save() 后实例主键保留、
 * 库内行主键正确**（已实测：修复前实例 id 被覆盖为 0，修复后与 Snowflake 值一致）。
 * 另经全仓扫描确认 app/ 下 **零处调用 insertGetId**，故该语义无生产影响。
 *
 * 【schema 事实来源】database/install.sql 而非 information_schema —— 本用例因此
 * 不依赖真实数据库，随默认单元套件在任何环境都能跑，不会在无库环境误红。
 * 该文件在 updated_at 事实上与线上库经 227 张表逐表比对**零差异**（详见提交说明）；
 * 若日后有人 ALTER 库却不更新 install.sql，此处事实会滞后 —— 届时把同一判别子
 * 在 tests/Integration/ 下按 TEST_DB_* 契约再跑一遍即可（本用例判别子可直接复用）。
 */
class ModelTimestampDeclarationTest extends TestCase
{
    /** @var array<string, array{hasUpdatedAt: bool, hasAutoIncrement: bool}>|null */
    private static ?array $tableFacts = null;

    /**
     * 解析 install.sql，得到 {无前缀表名: {有无 updated_at, 有无 AUTO_INCREMENT}}。
     *
     * @return array<string, array{hasUpdatedAt: bool, hasAutoIncrement: bool}>
     */
    private function tableFacts(): array
    {
        if (self::$tableFacts !== null) {
            return self::$tableFacts;
        }

        $sql = (string) file_get_contents(__DIR__ . '/../database/install.sql');
        $facts = [];

        // 块形态：CREATE TABLE IF NOT EXISTS `erp_xxx` ( ... \n) ENGINE=InnoDB ...;
        if (preg_match_all('/CREATE TABLE IF NOT EXISTS `erp_(\w+)`\s*\((.*?)\n\)\s*ENGINE/s', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $block) {
                $facts[$block[1]] = [
                    // 只认列定义行：行首（可有缩进）反引号包起来的列名后跟类型
                    'hasUpdatedAt' => (bool) preg_match('/^\s*`updated_at`\s+\w+/m', $block[2]),
                    'hasAutoIncrement' => stripos($block[2], 'AUTO_INCREMENT') !== false,
                ];
            }
        }

        return self::$tableFacts = $facts;
    }

    /**
     * 反射 app/model/** 全部具体模型，取表名与两条关键声明的**默认值**
     * （不实例化、不 boot，避免 Searchable / TenantScope 的启动副作用）。
     *
     * @return array<string, array{file: string, table: string|null, timestamps: mixed, incrementing: mixed, updatedAt: mixed}>
     */
    private function modelDeclarations(): array
    {
        $models = [];
        foreach (glob(__DIR__ . '/../app/model/*.php') ?: [] as $file) {
            $class = 'app\\model\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $defaults = $reflection->getDefaultProperties();
            $models[$class] = [
                'file' => $file,
                'table' => $defaults['table'] ?? null,
                // 未声明时由 Illuminate\Database\Eloquent\Model 继承：true / true
                'timestamps' => $defaults['timestamps'] ?? null,
                'incrementing' => $defaults['incrementing'] ?? null,
                // 常量同样会被继承，未覆写时为字符串 'updated_at'
                'updatedAt' => defined($class . '::UPDATED_AT') ? constant($class . '::UPDATED_AT') : 'updated_at',
            ];
        }

        return $models;
    }

    public function test_install_sql_parse_yields_tables(): void
    {
        $facts = $this->tableFacts();
        $this->assertGreaterThan(200, count($facts), 'install.sql 应解析出全部 erp_ 表（当前 227 张）');
    }

    public function test_every_model_table_exists_in_install_sql(): void
    {
        $facts = $this->tableFacts();
        $unknown = [];
        foreach ($this->modelDeclarations() as $class => $decl) {
            if ($decl['table'] === null) {
                $unknown[] = "{$class} 未声明 \$table";
                continue;
            }
            if (!isset($facts[$decl['table']])) {
                $unknown[] = "{$class}::\$table='{$decl['table']}' 在 install.sql 中不存在";
            }
        }
        $this->assertSame([], $unknown, "模型表名与 install.sql 对不上：\n" . implode("\n", $unknown));
    }

    /**
     * P1：表无 updated_at → 模型必须声明 $timestamps=false 或 UPDATED_AT=null。
     */
    public function test_models_for_tables_without_updated_at_disable_timestamp_maintenance(): void
    {
        $facts = $this->tableFacts();
        $violations = [];

        foreach ($this->modelDeclarations() as $class => $decl) {
            $table = $decl['table'];
            if ($table === null || !isset($facts[$table])) {
                continue;
            }
            if ($facts[$table]['hasUpdatedAt']) {
                continue;
            }
            if (!$this->disablesUpdatedAt($decl)) {
                $violations[] = sprintf(
                    '%s (erp_%s) 表无 updated_at，但 $timestamps=%s 且 UPDATED_AT=%s —— '
                    . '需加 public $timestamps = false; 或 public const UPDATED_AT = null;',
                    $class,
                    $table,
                    var_export($decl['timestamps'], true),
                    var_export($decl['updatedAt'], true)
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "以下模型会因 updated_at 未知列在 save()/update() 时报 SQLSTATE[42S22]：\n" . implode("\n", $violations)
        );
    }

    /**
     * P2：表无 AUTO_INCREMENT → 模型必须 $incrementing = false（否则主键被回写为 0）。
     */
    public function test_models_for_non_auto_increment_tables_declare_incrementing_false(): void
    {
        $facts = $this->tableFacts();
        $violations = [];

        foreach ($this->modelDeclarations() as $class => $decl) {
            $table = $decl['table'];
            if ($table === null || !isset($facts[$table])) {
                continue;
            }
            if ($facts[$table]['hasAutoIncrement']) {
                continue;
            }
            if ($decl['incrementing'] !== false) {
                $violations[] = sprintf(
                    '%s (erp_%s) 表无 AUTO_INCREMENT，但 $incrementing=%s —— '
                    . '需加 public $incrementing = false; 否则 save() 后主键被 LAST_INSERT_ID()=0 覆盖',
                    $class,
                    $table,
                    var_export($decl['incrementing'], true)
                );
            }
        }

        $this->assertSame(
            [],
            $violations,
            "以下模型的创建响应主键会恒为 0：\n" . implode("\n", $violations)
        );
    }

    /**
     * 判别子自检：直接调用上面用例所用的**同一个** disablesUpdatedAt()，
     * 证明它既认 $timestamps=false 也认 UPDATED_AT=null，且对违例真的返回 false
     * —— 防止判别子写窄（只认一种写法导致误报）或写宽（永远为真导致漏报）。
     */
    public function test_timestamp_predicate_accepts_both_idioms_and_rejects_violation(): void
    {
        $this->assertTrue($this->disablesUpdatedAt(['timestamps' => false, 'updatedAt' => 'updated_at']), '应接受 $timestamps=false');
        $this->assertTrue($this->disablesUpdatedAt(['timestamps' => true, 'updatedAt' => null]), '应接受 UPDATED_AT=null');
        $this->assertFalse($this->disablesUpdatedAt(['timestamps' => true, 'updatedAt' => 'updated_at']), '应拒绝两者皆未声明');
    }

    /**
     * P1 判别子：模型是否已关闭 updated_at 的自动维护（两种合法写法任一即可）。
     *
     * 单独抽成方法是为了让自检用例与主用例跑**同一份**逻辑，
     * 而不是各写一份副本 —— 副本会让自检变成自欺。
     *
     * @param array{timestamps: mixed, updatedAt: mixed} $decl
     */
    private function disablesUpdatedAt(array $decl): bool
    {
        return $decl['timestamps'] === false || $decl['updatedAt'] === null;
    }
}
