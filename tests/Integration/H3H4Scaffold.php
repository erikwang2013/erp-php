<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/**
 * H3 课程体系 + H4 社保基数规则 集成测试基类
 *
 * - setUp 执行 database/h34_hr.sql（scratch 建表脚本，全 DROP + CREATE，可重放），
 *   测试库表结构以该文件为唯一来源；缺文件即 markTestSkipped 优雅跳过。
 * - 本批 5 张 erp_hr_* 新表全量 DROP；erp_hr_employee 为存量表绝不 DROP，
 *   测试员工由 createEmployee() 种子（沿用 P1M1M2CostingScaffold 同款裸插 4 列写法），
 *   tearDown 仅按 id 删除本批种子的员工行。
 * - nextId() 提供进程内递增主键（与 snowflake 同分布：BIGINT，客户端赋值）。
 * - assertServiceThrows() 断言 InvalidArgumentException 且消息完全一致。
 */
abstract class H3H4Scaffold extends IntegrationTestCase
{
    protected const H34_TABLES = [
        'erp_hr_course',
        'erp_hr_course_enrollment',
        'erp_hr_social_rule',
        'erp_hr_social_rate',
        'erp_hr_employee_social',
    ];

    private static int $idSeq = 0;

    /** 本测试种子过的员工主键（tearDown 只清这些行，绝不 DROP 员工表）。 */
    private array $seededEmployeeIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->importH34Ddl();
    }

    protected function tearDown(): void
    {
        foreach (self::H34_TABLES as $table) {
            self::dropTableIfExists($table);
        }
        if ($this->seededEmployeeIds !== []) {
            Capsule::table('erp_hr_employee')->whereIn('id', $this->seededEmployeeIds)->delete();
            $this->seededEmployeeIds = [];
        }
        parent::tearDown();
    }

    /** 执行 scratch 建表脚本（去注释行后按 ';' 拆句）。 */
    protected function importH34Ddl(): void
    {
        $path = dirname(__DIR__, 2) . '/database/h34_hr.sql';
        if (!is_file($path)) {
            self::markTestSkipped('缺少 database/h34_hr.sql（scratch 建表脚本未随批次交付），跳过 H3/H4 集成测试');
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

    /** 种子在职员工（erp_hr_employee 仅 code/name/status NOT NULL，其余走默认值/null）。 */
    protected function createEmployee(): int
    {
        $id = self::nextId();
        Capsule::table('erp_hr_employee')->insert([
            'id' => $id,
            'code' => 'EMP-' . $id,
            'name' => 'H3H4测试员工',
            'status' => 1,
        ]);
        $this->seededEmployeeIds[] = $id;

        return $id;
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
