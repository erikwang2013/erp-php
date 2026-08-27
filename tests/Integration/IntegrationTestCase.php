<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use support\Redis;
use Throwable;

/**
 * 集成测试基类（真库/真 Redis，属于 --group=integration 组，默认不随主套件执行）
 *
 * 设计约定：
 * 1. 数据库连接完全由 TEST_DB_* 环境变量驱动，不读取 config/database.php：
 *    - TEST_DB_HOST     测试库主机（默认 127.0.0.1）
 *    - TEST_DB_PORT     测试库端口（默认 3306）
 *    - TEST_DB_DATABASE 测试库名（必填；为空即跳过全部数据库类测试）
 *    - TEST_DB_USERNAME 测试库用户（默认 root）
 *    - TEST_DB_PASSWORD 测试库口令（默认空）
 *    未配置 TEST_DB_DATABASE 时 markTestSkipped 优雅跳过；已配置但连接失败时
 *    同样跳过并给出原因（兼容本环境 MySQL 凭据受限等场景）。
 *
 * 2. Redis 队列测试由 TEST_REDIS_* 环境变量开关控制（为与 TEST_DB_* 契约对称的扩展）：
 *    - TEST_REDIS_HOST 启用开关 + 一致性校验（默认 127.0.0.1）
 *    实际连接仍走 config/redis.php（REDIS_HOST 等），因此启用开关必须与
 *    REDIS_HOST 指向同一实例，避免误投递到其他 Redis。
 *
 * 3. 本基类自行引导 Eloquent Capsule（prefix 置空）：项目 config/database.php
 *    配置了 'prefix' => 'erp_'，而各模型已显式声明 $table = 'erp_xxx'，
 *    Eloquent grammar 会二次加前缀产生 erp_erp_xxx 双重前缀（既有配置问题，
 *    不在本任务修复范围）；集成测试统一使用显式全表名 + 空前缀，保证查询
 *    精确命中真实表 erp_it_crud / erp_product 等。
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Eloquent Capsule 单例（同一 PHPUnit 进程内所有集成测试共享一个连接） */
    protected static ?Capsule $capsule = null;

    // ---------- 数据库环境变量契约 ----------

    protected static function testDbHost(): string
    {
        return (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1');
    }

    protected static function testDbPort(): int
    {
        return (int) (getenv('TEST_DB_PORT') ?: 3306);
    }

    protected static function testDbDatabase(): string
    {
        return (string) (getenv('TEST_DB_DATABASE') ?: '');
    }

    protected static function testDbUsername(): string
    {
        return (string) (getenv('TEST_DB_USERNAME') ?: 'root');
    }

    protected static function testDbPassword(): string
    {
        return (string) (getenv('TEST_DB_PASSWORD') ?: '');
    }

    /**
     * 数据库可用性守卫：无 TEST_DB_DATABASE 环境变量或连接不可用时优雅跳过。
     * 应在 setUp() 中调用。
     */
    protected function requireTestDatabase(): void
    {
        if (self::testDbDatabase() === '') {
            self::markTestSkipped(
                '未配置 TEST_DB_DATABASE 等 TEST_DB_* 环境变量，跳过数据库集成测试'
                . '（契约见 tests/Integration/IntegrationTestCase.php 类头说明）'
            );
        }

        self::bootCapsule();

        try {
            Capsule::connection()->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped(
                '数据库连接失败（TEST_DB_* 已配置但不可用）: ' . $e->getMessage()
            );
        }
    }

    /**
     * 引导 Eloquent Capsule（每个 PHPUnit 进程仅引导一次）。
     */
    protected static function bootCapsule(): void
    {
        if (self::$capsule !== null) {
            return;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => self::testDbHost(),
            'port' => self::testDbPort(),
            'database' => self::testDbDatabase(),
            'username' => self::testDbUsername(),
            'password' => self::testDbPassword(),
            // 模型表名已显式包含 erp_ 前缀，此处必须置空，避免双重前缀
            'prefix' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict' => true,
            'engine' => 'InnoDB',
        ], 'default');
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;
    }

    // ---------- Redis 环境变量契约 ----------

    protected static function testRedisHost(): string
    {
        return (string) (getenv('TEST_REDIS_HOST') ?: '');
    }

    protected static function testRedisPort(): int
    {
        return (int) (getenv('TEST_REDIS_PORT') ?: 6379);
    }

    protected static function testRedisPassword(): string
    {
        return (string) (getenv('TEST_REDIS_PASSWORD') ?: '');
    }

    protected static function testRedisDatabase(): int
    {
        return (int) (getenv('TEST_REDIS_DATABASE') ?: 0);
    }

    /**
     * Redis 可用性守卫：无 TEST_REDIS_HOST 环境变量或连接不可用时优雅跳过。
     * 应在 setUp() 中调用。
     */
    protected function requireTestRedis(): void
    {
        if (self::testRedisHost() === '') {
            self::markTestSkipped(
                '未配置 TEST_REDIS_HOST 等 TEST_REDIS_* 环境变量，跳过队列集成测试'
                . '（契约见 tests/Integration/IntegrationTestCase.php 类头说明）'
            );
        }

        // 队列经 support\Redis（config/redis.php 即 REDIS_* 环境变量）连接，
        // 启用开关必须与实际连接指向同一实例，防止误投递到其他 Redis。
        $actualHost = (string) config('redis.default.host', '127.0.0.1');
        if ($actualHost !== self::testRedisHost()) {
            self::markTestSkipped(
                'TEST_REDIS_HOST（' . self::testRedisHost() . "）与 config('redis.default.host')（{$actualHost}）不一致，"
                . '请统一设置 REDIS_HOST 与 TEST_REDIS_HOST 指向同一测试 Redis 后重试'
            );
        }

        try {
            Redis::connection()->ping();
        } catch (Throwable $e) {
            self::markTestSkipped(
                'Redis 连接失败（TEST_REDIS_* 已配置但不可用）: ' . $e->getMessage()
            );
        }
    }

    // ---------- 表结构辅助 ----------

    /**
     * 测试表不存在时按蓝图创建；已存在则跳过（支持 CI 多轮运行幂等）。
     */
    protected static function createTableIfMissing(string $table, callable $blueprint): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable($table)) {
            return;
        }
        $schema->create($table, $blueprint);
    }

    /**
     * 测试表存在则删除（清理失败不掩盖测试结果）。
     */
    protected static function dropTableIfExists(string $table): void
    {
        try {
            $schema = Capsule::schema();
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        } catch (Throwable) {
            // 清理失败仅记录，不改变测试结论
        }
    }
}
