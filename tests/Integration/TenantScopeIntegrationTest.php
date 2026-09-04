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
use support\Request;
use support\Response;
use tests\Integration\Fixtures\TenantAwareModel;

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
 *    历史记录）——本类如实断言当前（缺陷）行为。修复后的传递链路为请求上下文
 *    注入（覆盖点 3），静态门面仅保留给测试/CLI 兜底，不再承担传递职责；
 * 3. TenantScope 中间件契约（P2-4 B5 修复版）：X-Tenant-Code 请求头 → erp_tenant
 *    查表（SoftDeletes 自动排除已删行）→ 注入 request->tenantId / request->companyId；
 *    无/空请求头放行不注入（单租户回归线）；查无/未开通/停用/到期一律 403 拒绝
 *    并携带精确中文消息（消息文本为稳定契约）。修复版中间件不再写入 trait 静态
 *    （消除常驻进程跨请求串扰，P0 缺陷修复的一部分）；
 * 4. 数据隔离：经【使用类】设置租户时全局作用域应生效（租户1/租户2互不可见）；
 *    经【trait 名】设置租户时（缺陷路径）当前行为为无隔离——如实断言。
 *
 * 说明：覆盖点 3 需 erp_tenant 注册表（蓝图见 resetRegistryTable，镜像
 * database/b5_tenant.sql 最小结构：双 UNIQUE + deleted_at），与 B5TenantTest
 * 共用该表名——各用例独立清空、tearDown 删表，不跨类依赖。组 1/2 为纯逻辑
 * 契约；组 4 用测试表 erp_it_tenant。
 *
 * 关于 #[IgnoreDeprecations]：经 trait 名直接调用静态方法
 * （TenantScope::setCurrentTenantId / getCurrentTenantId）在 PHP 8.3 触发
 * "should only be called on a class using the trait" 弃用警告——组 1/2/4b 按
 * 测试意图显式豁免弃用报告（生产代码已不再走该路径，见 trait 类头注释）。
 */
#[Group('integration')]
#[IgnoreDeprecations]
class TenantScopeIntegrationTest extends IntegrationTestCase
{
    /** 租户隔离测试表名（租户族 fixture） */
    private const TENANT_TABLE = 'erp_it_tenant';

    /** 租户注册表测试表名（与 b5_tenant.sql 同构，供中间件查表） */
    private const REGISTRY_TABLE = 'erp_tenant';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::REGISTRY_TABLE);
            self::dropTableIfExists(self::TENANT_TABLE);
        }
        // 复位 trait 的两个静态拷贝，避免污染同一进程内的其他测试
        TenantScope::setCurrentTenantId(null);
        TenantAwareModel::setCurrentTenantId(null);
        parent::tearDown();
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
     * 本测试如实断言该缺陷行为；修复后的生产传递链路为请求上下文注入（组 3），
     * 静态门面仅保留给测试/CLI 兜底。
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

    // ---------- 组 3：中间件契约（X-Tenant-Code → erp_tenant 查表注入） ----------

    /**
     * 建表并清空注册表（镜像 b5_tenant.sql：双 UNIQUE + deleted_at + 时间戳默认值）。
     */
    private function resetRegistryTable(): void
    {
        self::createTableIfMissing(self::REGISTRY_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->unique('company_id');
            $table->string('tenant_code', 50);
            $table->unique('tenant_code');
            $table->unsignedTinyInteger('plan')->default(1);
            $table->unsignedTinyInteger('status')->default(0);
            $table->date('expire_at');
            $table->dateTime('opened_at')->nullable();
            $table->string('remark', 500)->default('');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['status', 'expire_at']);
        });
        Capsule::table(self::REGISTRY_TABLE)->delete();
    }

    /** 直插一条租户注册行（组 3 各用例独立 seed；$deletedAt 非空即软删行）。 */
    private function seedRegistryTenant(
        int $id,
        int $companyId,
        string $code,
        int $status,
        string $expireAt,
        ?string $deletedAt = null
    ): void {
        Capsule::table(self::REGISTRY_TABLE)->insert([
            'id' => $id,
            'company_id' => $companyId,
            'tenant_code' => $code,
            'plan' => 1,
            'status' => $status,
            'expire_at' => $expireAt,
            'deleted_at' => $deletedAt,
        ]);
    }

    /** 携带 X-Tenant-Code 请求头的 raw 请求。 */
    private function tenantCodeRequest(string $code): Request
    {
        return new Request("GET /admin HTTP/1.1\r\nHost: localhost\r\nX-Tenant-Code: {$code}\r\n\r\n");
    }

    /** 无任何租户请求头的 raw 请求。 */
    private function plainRequest(): Request
    {
        return new Request("GET /admin HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    /**
     * 断言中间件 403 拒绝：不放行 handler，body code=403 + data=[] + 精确消息。
     */
    private function assertMiddlewareRejects(Request $request, string $expectMessage): void
    {
        $called = false;
        $response = (new TenantScopeMiddleware())->process($request, static function () use (&$called): Response {
            $called = true;

            return response('ok');
        });
        $this->assertFalse($called, '租户校验失败时不应放行到后续处理器');
        $body = json_decode((string) $response->rawBody(), true);
        $this->assertSame(403, $body['code'] ?? null, '拒绝体 code 应为 403（body 承载业务错误码）');
        $this->assertSame($expectMessage, $body['message'] ?? null, '拒绝消息应精确匹配契约');
        $this->assertSame([], $body['data'] ?? null, '拒绝体 data 应为空数组');
    }

    /**
     * 覆盖点 3a：合法 X-Tenant-Code → erp_tenant 查表 → 注入 request->tenantId/
     * companyId 并放行；且修复版中间件不写入 trait 静态。
     */
    public function testMiddlewareInjectsTenantContextFromHeaderCode(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(7, 9001, 'acme', 1, date('Y-m-d', strtotime('+370 days')));

        $handledRequest = null;
        $response = (new TenantScopeMiddleware())->process(
            $this->tenantCodeRequest('acme'),
            static function ($req) use (&$handledRequest) {
                $handledRequest = $req;

                return response('ok');
            }
        );

        $this->assertNotNull($handledRequest, '合法租户应放行到后续处理器');
        $this->assertSame(7, $handledRequest->tenantId ?? null, '应注入 request->tenantId（注册行 id）');
        $this->assertSame(9001, $handledRequest->companyId ?? null, '应注入 request->companyId（注册行 company_id）');
        $this->assertSame(200, $response->getStatusCode(), '放行响应应保持后续处理器结果');
        $this->assertNull(TenantScope::getCurrentTenantId(), '修复版中间件不应写入 trait 静态（无跨请求串扰）');
    }

    /**
     * 覆盖点 3b：无 X-Tenant-Code 请求头 → 放行且不注入（单租户回归线：
     * 未设租户行为与现状完全一致）。
     */
    public function testMiddlewarePassesThroughWithoutHeaderCode(): void
    {
        $this->resetRegistryTable();

        $handledRequest = null;
        (new TenantScopeMiddleware())->process(
            $this->plainRequest(),
            static function ($req) use (&$handledRequest) {
                $handledRequest = $req;

                return response('ok');
            }
        );

        $this->assertNotNull($handledRequest, '无请求头应直接放行');
        $this->assertNull($handledRequest->tenantId ?? null, '无请求头不应注入 tenantId');
        $this->assertNull($handledRequest->companyId ?? null, '无请求头不应注入 companyId');
        $this->assertNull(TenantScope::getCurrentTenantId(), '无请求头时 trait 静态应保持 null');
    }

    /** 覆盖点 3c：查无此编码 → 403「租户不存在」。 */
    public function testMiddlewareRejectsUnknownTenantCode(): void
    {
        $this->resetRegistryTable();
        $this->assertMiddlewareRejects($this->tenantCodeRequest('ghost-404'), '租户不存在');
    }

    /** 覆盖点 3d：status=0 待开通 → 403「租户未开通」。 */
    public function testMiddlewareRejectsPendingTenant(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(11, 9002, 'pending', 0, date('Y-m-d', strtotime('+370 days')));
        $this->assertMiddlewareRejects($this->tenantCodeRequest('pending'), '租户未开通');
    }

    /** 覆盖点 3e：status=2 停用 → 403「租户已停用」。 */
    public function testMiddlewareRejectsSuspendedTenant(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(12, 9003, 'suspended', 2, date('Y-m-d', strtotime('+370 days')));
        $this->assertMiddlewareRejects($this->tenantCodeRequest('suspended'), '租户已停用');
    }

    /** 覆盖点 3f：status=3 到期 → 403「租户已到期」。 */
    public function testMiddlewareRejectsExpiredTenant(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(13, 9004, 'expired', 3, date('Y-m-d', strtotime('+370 days')));
        $this->assertMiddlewareRejects($this->tenantCodeRequest('expired'), '租户已到期');
    }

    /** 覆盖点 3g：status=1 但已过到期日（到期标记任务未跑）→ 403「租户已到期」。 */
    public function testMiddlewareRejectsEnabledTenantPastExpiry(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(14, 9005, 'pastdue', 1, date('Y-m-d', strtotime('-1 day')));
        $this->assertMiddlewareRejects($this->tenantCodeRequest('pastdue'), '租户已到期');
    }

    /** 覆盖点 3h：已软删注册行视同查无 → 403「租户不存在」。 */
    public function testMiddlewareIgnoresSoftDeletedTenant(): void
    {
        $this->resetRegistryTable();
        $this->seedRegistryTenant(
            15,
            9006,
            'softdeleted',
            1,
            date('Y-m-d', strtotime('+370 days')),
            date('Y-m-d H:i:s')
        );
        $this->assertMiddlewareRejects($this->tenantCodeRequest('softdeleted'), '租户不存在');
    }

    // ---------- 组 4：使用类静态设置 + 作用域隔离 ----------

    /**
     * 确保租户隔离测试表存在并清空。
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
     * 覆盖点 4a：经【使用类】设置租户时，全局作用域应正确隔离两个租户的数据。
     *
     * TenantAwareModel::setCurrentTenantId() 走的是使用类自己的静态拷贝，
     * 是 trait 的正常用法（phpunit 无请求上下文时静态门面即兜底路径）——
     * 该用例验证作用域机制本身是通的；缺陷仅在"经 trait 名调用"时出现
     * （见 testKnownDefect* 两个用例）。
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

        // 不设置租户（null）：作用域关闭，可见全部（单租户回归线）
        TenantAwareModel::setCurrentTenantId(null);
        $this->assertSame(3, (int) TenantAwareModel::query()->count(), '未设置租户时不应过滤任何数据');
    }

    /**
     * 覆盖点 4b：经【trait 名】设置租户时（缺陷路径），使用类无作用域 →
     * 跨租户数据可见。如实断言当前（缺陷）行为——生产传递已改走请求上下文
     * （组 3），该缺陷路径不再影响生产链路。
     */
    public function testKnownDefectNoIsolationWhenTenantSetViaTraitName(): void
    {
        $this->resetTenantTable();

        // 写入两个租户各一条数据
        TenantAwareModel::query()->create(['id' => 1, 'tenant_id' => 1, 'name' => '租户1-数据']);
        TenantAwareModel::query()->create(['id' => 2, 'tenant_id' => 2, 'name' => '租户2-数据']);

        // 经 trait 名设置当前租户（缺陷路径）
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
            . '生产链路已由请求上下文注入取代（见 app/model/concerns/TenantScope.php 类头）'
        );
    }
}
