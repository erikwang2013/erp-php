<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\middleware\TenantScope as TenantScopeMiddleware;
use app\model\concerns\TenantScope;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use tests\Integration\Fixtures\TenantAwareModel;
use Webman\Http\Request;

/**
 * 多租户作用域集成测试（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 覆盖点：
 * 1. TenantScope trait 的 setCurrentTenantId / getCurrentTenantId 行为契约；
 * 2. 【已知缺陷 · P0 记录】trait 的静态属性是"每个使用类一份拷贝"，经 trait 名
 *    setCurrentTenantId() 写入的值，使用该 trait 的模型类读不到（PHP 8.3 实测，
 *    app/middleware/TenantScope.php 与 app/model/concerns/TenantScope.php 注释均已
 *    记录）——本类如实断言当前（缺陷）行为，不强行修复；
 * 3. TenantScope 中间件契约：X-Tenant-Id 请求头 → request->tenantId 注入 + trait 写入；
 * 4. 数据隔离：经【使用类】设置租户时全局作用域应生效（租户1/租户2互不可见）；
 *    经【trait 名】设置租户时（缺陷路径）当前行为为无隔离——如实断言。
 *
 * 说明：trait 契约与中间件契约均为纯逻辑测试（无需建表），但为保持集成组
 * "无环境变量即整类跳过"的行为一致，统一由 requireTestDatabase() 门控；
 * 隔离类用例在 CI（mysql service + TEST_DB_*）中执行。
 *
 * 关于 #[IgnoreDeprecations]：经 trait 名直接调用静态方法
 * （TenantScope::setCurrentTenantId / getCurrentTenantId）在 PHP 8.3 触发
 * "should only be called on a class using the trait" 弃用警告——生产中间件
 * app/middleware/TenantScope.php 正是这样调用的（P0 缺陷的一部分），本类为
 * 如实验证该缺陷路径必须复现同样调用，故按测试意图显式豁免弃用报告。
 */
#[Group('integration')]
#[IgnoreDeprecations]
class TenantScopeIntegrationTest extends IntegrationTestCase
{
    /** 租户隔离测试表名 */
    private const TENANT_TABLE = 'erp_it_tenant';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::TENANT_TABLE);
        }
        // 复位 trait 的两个静态拷贝，避免污染同一进程内的其他测试
        TenantScope::setCurrentTenantId(null);
        TenantAwareModel::setCurrentTenantId(null);
        parent::tearDown();
    }

    /**
     * 确保租户表存在并清空。
     */
    private function resetTenantTable(): void
    {
        self::createTableIfMissing(self::TENANT_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('tenant_id')->default(0)->index();
            $table->string('name', 100);
        });
        Capsule::table(self::TENANT_TABLE)->delete();
    }

    /**
     * 覆盖点 1：trait 自身的 set/get 读写契约（trait 名直接调用）。
     */
    public function testSetAndGetCurrentTenantIdContract(): void
    {
        TenantScope::setCurrentTenantId(1);
        $this->assertSame(1, TenantScope::getCurrentTenantId(), 'set(1) 后 get 应返回 1');

        TenantScope::setCurrentTenantId(2);
        $this->assertSame(2, TenantScope::getCurrentTenantId(), 'set(2) 后 get 应返回 2');

        TenantScope::setCurrentTenantId(null);
        $this->assertNull(TenantScope::getCurrentTenantId(), 'set(null) 后 get 应返回 null');
    }

    /**
     * 覆盖点 2：已知缺陷（P0 记录）——经 trait 名写入的静态值不会传导到使用类。
     *
     * 当前行为：TenantScope::setCurrentTenantId(42) 写的是 trait 自身拷贝，
     * TenantAwareModel::getCurrentTenantId() 仍为 null（PHP 8.3 实测）。
     * 本测试如实断言该缺陷行为；若后续修复（改为请求上下文注入），
     * 本测试需同步改为断言正确行为。
     */
    public function testKnownDefectStaticTenantIdDoesNotPropagateToUsingModel(): void
    {
        // 复位使用类拷贝，确保断言不受其他用例残留影响
        TenantAwareModel::setCurrentTenantId(null);
        TenantScope::setCurrentTenantId(42);

        $this->assertSame(42, TenantScope::getCurrentTenantId(), 'trait 自身拷贝应写入成功');
        $this->assertNull(
            TenantAwareModel::getCurrentTenantId(),
            '【已知缺陷·P0】经 trait 名 setCurrentTenantId() 写入的值不应传导到使用类（当前为 null）'
        );
    }

    /**
     * 覆盖点 3a：中间件应把 X-Tenant-Id 请求头注入 request->tenantId 并写入 trait。
     */
    public function testMiddlewareInjectsTenantIdFromHeader(): void
    {
        $buffer = "GET /admin HTTP/1.1\r\nHost: localhost\r\nX-Tenant-Id: 7\r\n\r\n";
        $request = new Request($buffer);
        $middleware = new TenantScopeMiddleware();

        $handled = $middleware->process($request, static fn ($req) => $req);

        $this->assertSame(7, $handled->tenantId ?? null, '中间件应把 X-Tenant-Id 注入 request->tenantId');
        $this->assertSame(7, TenantScope::getCurrentTenantId(), '中间件应写入 trait 静态（trait 自身拷贝）');
    }

    /**
     * 覆盖点 3b：无 X-Tenant-Id 请求头时不应注入，也不应写入 trait。
     */
    public function testMiddlewareDoesNotInjectWithoutHeader(): void
    {
        $request = new Request("GET /admin HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $middleware = new TenantScopeMiddleware();

        $handled = $middleware->process($request, static fn ($req) => $req);

        $this->assertNull($handled->tenantId ?? null, '无 X-Tenant-Id 请求头时不应注入 tenantId');
        $this->assertNull(TenantScope::getCurrentTenantId(), '无请求头时 trait 静态应保持 null');
    }

    /**
     * 覆盖点 4a：经【使用类】设置租户时，全局作用域应正确隔离两个租户的数据。
     *
     * TenantAwareModel::setCurrentTenantId() 走的是使用类自己的静态拷贝，
     * 是 trait 的正常用法——该用例验证作用域机制本身是通的；
     * 缺陷仅在"经 trait 名调用"时出现（见 testKnownDefect* 两个用例）。
     */
    public function testScopeFiltersByTenantIdWhenSetViaModelClass(): void
    {
        $this->resetTenantTable();

        // 写入租户1 两条、租户2 一条
        TenantAwareModel::setCurrentTenantId(1);
        TenantAwareModel::query()->create(['id' => 1, 'tenant_id' => 1, 'name' => '租户1-数据A']);
        TenantAwareModel::query()->create(['id' => 2, 'tenant_id' => 1, 'name' => '租户1-数据B']);

        TenantAwareModel::setCurrentTenantId(2);
        TenantAwareModel::query()->create(['id' => 3, 'tenant_id' => 2, 'name' => '租户2-数据C']);

        // 租户1 视角：只能看到 tenant_id=1 的两条
        TenantAwareModel::setCurrentTenantId(1);
        $rows1 = TenantAwareModel::query()->get();
        $this->assertCount(2, $rows1, '租户1 应只看到自己的 2 条数据');
        foreach ($rows1 as $row) {
            $this->assertSame(1, (int) $row->tenant_id, '租户1 不应看到其他租户数据');
        }

        // 租户2 视角：只能看到 tenant_id=2 的一条
        TenantAwareModel::setCurrentTenantId(2);
        $rows2 = TenantAwareModel::query()->get();
        $this->assertCount(1, $rows2, '租户2 应只看到自己的 1 条数据');
        $this->assertSame('租户2-数据C', $rows2->first()->name, '租户2 看到的数据内容应正确');

        // 不设置租户（null）：作用域关闭，可见全部
        TenantAwareModel::setCurrentTenantId(null);
        $this->assertSame(3, (int) TenantAwareModel::query()->count(), '未设置租户时不应过滤任何数据');
    }

    /**
     * 覆盖点 4b：经【trait 名】设置租户时（中间件当前的实际调用方式，缺陷路径），
     * 使用类无作用域 → 跨租户数据可见。如实断言当前（缺陷）行为。
     */
    public function testKnownDefectNoIsolationWhenTenantSetViaTraitName(): void
    {
        $this->resetTenantTable();

        // 写入两个租户各一条数据
        TenantAwareModel::query()->create(['id' => 1, 'tenant_id' => 1, 'name' => '租户1-数据']);
        TenantAwareModel::query()->create(['id' => 2, 'tenant_id' => 2, 'name' => '租户2-数据']);

        // 经 trait 名设置当前租户（与 app/middleware/TenantScope.php 一致）
        TenantScope::setCurrentTenantId(1);
        $this->assertNull(
            TenantAwareModel::getCurrentTenantId(),
            '【已知缺陷·P0】trait 名写入不影响使用类拷贝，使用类读到 null'
        );

        // 使用类读不到租户ID → 全局作用域不生效 → 无隔离（当前行为，如实断言）
        $this->assertSame(
            2,
            (int) TenantAwareModel::query()->count(),
            '【已知缺陷·P0】当前行为：租户1 能查到租户2 的数据，隔离未生效；'
            . '修复前本断言如实记录现状，启用多租户前必须解决（见 docs/ARCHITECTURE.md §22）'
        );
    }
}
