<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/**
 * H1 招聘 + H2 绩效考核 集成测试基类
 *
 * - setUp 执行 database/h1h2_hr.sql（scratch 建表脚本，全 DROP + CREATE，可重放），
 *   测试库表结构以该文件为唯一来源；缺文件即 markTestSkipped 优雅跳过。
 * - tearDown 清空本批 8 张 erp_hr_* 表，不留测试数据。
 * - nextId() 提供进程内递增主键（与 snowflake 同分布：BIGINT，客户端赋值）。
 * - assertServiceThrows() 断言 InvalidArgumentException 且消息完全一致。
 */
abstract class H1H2Scaffold extends IntegrationTestCase
{
    protected const H1H2_TABLES = [
        'erp_hr_job',
        'erp_hr_candidate',
        'erp_hr_interview',
        'erp_hr_offer',
        'erp_hr_kpi_template',
        'erp_hr_kpi_template_item',
        'erp_hr_perf_plan',
        'erp_hr_perf_score',
    ];

    private static int $idSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->importH1H2Ddl();
    }

    protected function tearDown(): void
    {
        foreach (self::H1H2_TABLES as $table) {
            self::dropTableIfExists($table);
        }
        parent::tearDown();
    }

    /** 执行 scratch 建表脚本（去注释行后按 ';' 拆句）。 */
    protected function importH1H2Ddl(): void
    {
        $path = dirname(__DIR__, 2) . '/database/h1h2_hr.sql';
        if (!is_file($path)) {
            self::markTestSkipped('缺少 database/h1h2_hr.sql（scratch 建表脚本未随批次交付），跳过 H1/H2 集成测试');
        }
        $lines = array_filter(
            explode("\n", (string) file_get_contents($path)),
            static fn (string $line): bool => trim($line) !== '' && !str_starts_with(ltrim($line), '--')
        );
        foreach (explode(';', implode("\n", $lines)) as $statement) {
            if (trim($statement) !== '') {
                Capsule::connection()->unprepared($statement);
            }
        }
    }

    /** 进程内递增主键（从 100000000001 起，避免与 0 默认值/人工小 id 混淆）。 */
    protected static function nextId(): int
    {
        return 100_000_000_001 + (self::$idSeq++);
    }

    /** 断言回调抛出 InvalidArgumentException 且消息与期望完全一致。 */
    protected function assertServiceThrows(callable $fn, string $expectedMessage): void
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());

            return;
        }
        $this->fail('期望抛出 InvalidArgumentException：' . $expectedMessage);
    }
}
