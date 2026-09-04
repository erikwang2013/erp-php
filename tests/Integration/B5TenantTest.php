<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\Tenant;
use app\service\platform\TenantService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use tests\Integration\Fixtures\CompanyAwareModel;

/**
 * P2-4 B5 租户生命周期与租户上下文隔离集成测试（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 被测对象：
 * 1. TenantService 状态机与校验（消息文本为稳定契约，逐条精确断言）：
 *    provision 直通开通（status=1 + opened_at）；suspend 1→2；resume 2→1；
 *    expireMark 1/2→3；renew SaaS 叠加语义（base=max(到期日,今天)、3 续费自动
 *    复活为 1、2 续费仅延长期限、0 不可续费）；expiryWarnings 启用中窗口内
 *    到期预警（含边界、到期日升序、软删/停用/到期/待开通排除）。
 * 2. 数据隔离（company 族，镜像 erp_finance_* 试点模型用法）：经使用类静态
 *    设置 company_id 时全局作用域生效，同公司可见 / 跨公司不可见 / 无上下文
 *    可见全部（单租户回归线）；company_id 为 NULL 的历史行在租户上下文中
 *    不可见（安全默认）。
 *
 * 日期约定：一律 date('Y-m-d') 相对计算（绝不硬编码），字符串比较与断言。
 * 建表约定：erp_tenant 注册表蓝图镜像 database/b5_tenant.sql（双 UNIQUE +
 * deleted_at 软删），与 TenantScopeIntegrationTest 共用该表名——各自独立
 * 清空、tearDown 删表，不跨类依赖；隔离用测试表 erp_it_company_data。
 */
#[Group('integration')]
class B5TenantTest extends IntegrationTestCase
{
    /** 租户注册表测试表名（服务层查真实 Tenant 模型，须软删列与双 UNIQUE） */
    private const REGISTRY_TABLE = 'erp_tenant';

    /** 公司族隔离测试表名 */
    private const COMPANY_TABLE = 'erp_it_company_data';

    private TenantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->service = new TenantService();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::REGISTRY_TABLE);
            self::dropTableIfExists(self::COMPANY_TABLE);
        }
        // 复位使用类静态（公司族拷贝），避免污染同一进程内的其他测试
        CompanyAwareModel::setCurrentCompanyId(null);
        parent::tearDown();
    }

    /** 相对日期工具：+n/-n 天，Y-m-d 字符串 */
    private static function day(int $offset): string
    {
        return date('Y-m-d', strtotime(sprintf('%+d days', $offset)));
    }

    /** 建表并清空注册表（镜像 b5_tenant.sql：双 UNIQUE + deleted_at + 时间戳默认值）。 */
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

    /** 直插租户注册行（绕过服务层直接构造任意状态；$deletedAt 非空即软删行）。 */
    private function createTenantRow(
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

    /** 断言 [null, $message] 失败结果（消息精确匹配契约）。 */
    private function assertFailed(array $result, string $expectedMessage): void
    {
        $this->assertNull($result[0], "失败结果首个元素应为 null：{$expectedMessage}");
        $this->assertSame($expectedMessage, $result[1], '错误消息应精确匹配契约');
    }

    // ---------- provision：创建即启用 + 校验矩阵 ----------

    public function testProvisionCreatesEnabledTenant(): void
    {
        $this->resetRegistryTable();

        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001,
            'tenant_code' => 'acme-prod',
            'plan' => 2,
            'expire_at' => self::day(30),
            'remark' => '首批开通',
        ]);

        $this->assertNull($error, '合法参数应开通成功');
        $this->assertInstanceOf(Tenant::class, $tenant, '成功应返回 Tenant 行');
        $this->assertSame(TenantService::STATUS_ENABLED, (int) $tenant->status, '创建即启用');
        $this->assertSame(2, (int) $tenant->plan);
        $this->assertSame('acme-prod', (string) $tenant->tenant_code);
        $this->assertSame(1001, (int) $tenant->company_id);
        $this->assertSame(self::day(30), (string) $tenant->expire_at);
        $this->assertStringStartsWith(self::day(0), (string) $tenant->opened_at, 'opened_at 应为今天');
        $this->assertSame('首批开通', (string) $tenant->remark);

        // 已落库校验
        $row = Tenant::query()->find((int) $tenant->id);
        $this->assertNotNull($row, '租户应已持久化');
        $this->assertSame(1001, (int) $row->company_id);
        $this->assertSame(1, (int) Tenant::query()->count(), '注册表应仅此一行');
    }

    /**
     * provision 校验矩阵：非法参数一律 [null, 精确消息] 且不落库。
     *
     * @return array<string, array{0: array, 1: string}>
     */
    public static function provisionValidationProvider(): array
    {
        $base = ['company_id' => 1001, 'tenant_code' => 'ok-tenant', 'plan' => 1, 'expire_at' => self::day(30)];

        return [
            '公司缺失' => [[
                'tenant_code' => 'no-company', 'plan' => 1, 'expire_at' => self::day(30),
            ], '公司不能为空'],
            '公司为0' => [['company_id' => 0] + $base, '公司不能为空'],
            '编码为空' => [['tenant_code' => ''] + $base, '租户编码不能为空'],
            '编码过短' => [['tenant_code' => 'a'] + $base, '租户编码只能包含字母、数字、_、-（2-50位）'],
            '编码含非法字符' => [['tenant_code' => 'bad code!'] + $base, '租户编码只能包含字母、数字、_、-（2-50位）'],
            '编码超长' => [['tenant_code' => str_repeat('x', 51)] + $base, '租户编码只能包含字母、数字、_、-（2-50位）'],
            '套餐为0' => [['plan' => 0] + $base, '套餐参数错误（1=标准 2=专业 3=旗舰）'],
            '套餐为4' => [['plan' => 4] + $base, '套餐参数错误（1=标准 2=专业 3=旗舰）'],
            '到期日为空' => [['expire_at' => ''] + $base, '到期日期必填'],
            '到期日非法日期' => [['expire_at' => '2026-02-30'] + $base, '到期日期非法'],
            '到期日非法格式' => [['expire_at' => '2026/01/01'] + $base, '到期日期非法'],
            '到期日早于今天' => [['expire_at' => self::day(-1)] + $base, '到期日期不能早于今天'],
        ];
    }

    /**
     * @param array $input
     */
    #[DataProvider('provisionValidationProvider')]
    public function testProvisionValidationMatrix(array $input, string $expectedMessage): void
    {
        $this->resetRegistryTable();

        $result = $this->service->provision($input);

        $this->assertFailed($result, $expectedMessage);
        $this->assertSame(0, (int) Tenant::query()->count(), '校验失败不应落库');
    }

    public function testProvisionRejectsDuplicateCompanyAndCode(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'first-co', 'plan' => 1, 'expire_at' => self::day(365),
        ]);
        $this->assertNull($error, '首个租户应开通成功');

        // 同公司复开（新编码）→ 公司级拒绝
        $this->assertFailed($this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'second-co', 'plan' => 1, 'expire_at' => self::day(365),
        ]), '公司已开通租户');

        // 同编码给别的公司 → 编码级拒绝
        $this->assertFailed($this->service->provision([
            'company_id' => 1002, 'tenant_code' => 'first-co', 'plan' => 1, 'expire_at' => self::day(365),
        ]), '租户编码已存在');

        $this->assertSame(1, (int) Tenant::query()->count(), '两次拒绝后应仍只有首个租户');
    }

    // ---------- suspend / resume / expireMark：状态机 ----------

    public function testSuspendAndResumeLifecycle(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'lifecycle', 'plan' => 1, 'expire_at' => self::day(365),
        ]);
        $this->assertNull($error);
        $tenantId = (int) $tenant->id;

        // 1 → 2
        [$suspended, $suspendError] = $this->service->suspend($tenantId);
        $this->assertNull($suspendError);
        $this->assertSame(TenantService::STATUS_SUSPENDED, (int) $suspended->status);
        $this->assertSame(2, (int) Tenant::query()->find($tenantId)->status, '停用应落库');

        // 重复停用拒绝
        $this->assertFailed($this->service->suspend($tenantId), '仅启用状态可停用');

        // 待开通行停用拒绝（命中状态前置校验）
        $this->createTenantRow(910, 9100, 'never-opened', TenantService::STATUS_PENDING, self::day(365));
        $this->assertFailed($this->service->suspend(910), '仅启用状态可停用');

        // 2 → 1
        [$resumed, $resumeError] = $this->service->resume($tenantId);
        $this->assertNull($resumeError);
        $this->assertSame(TenantService::STATUS_ENABLED, (int) $resumed->status);

        // 重复恢复拒绝
        $this->assertFailed($this->service->resume($tenantId), '仅停用状态可恢复');

        // 不存在的租户
        $this->assertFailed($this->service->suspend(999_999_999_999), '租户不存在');
        $this->assertFailed($this->service->resume(999_999_999_999), '租户不存在');
    }

    public function testExpireMarkLifecycle(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'expiring', 'plan' => 1, 'expire_at' => self::day(365),
        ]);
        $this->assertNull($error);
        $tenantId = (int) $tenant->id;

        // 1 → 3
        [$marked, $markError] = $this->service->expireMark($tenantId);
        $this->assertNull($markError);
        $this->assertSame(TenantService::STATUS_EXPIRED, (int) $marked->status);

        // 重复标记拒绝
        $this->assertFailed($this->service->expireMark($tenantId), '租户已到期，无需重复标记');

        // 停用行可标记到期（2 → 3）
        $this->createTenantRow(911, 9110, 'suspended-exp', TenantService::STATUS_SUSPENDED, self::day(365));
        [$marked, $error] = $this->service->expireMark(911);
        $this->assertNull($error);
        $this->assertSame(TenantService::STATUS_EXPIRED, (int) $marked->status);

        // 待开通行拒绝标记
        $this->createTenantRow(912, 9120, 'pending-exp', TenantService::STATUS_PENDING, self::day(365));
        $this->assertFailed($this->service->expireMark(912), '待开通租户无需标记到期');

        $this->assertFailed($this->service->expireMark(999_999_999_999), '租户不存在');
    }

    // ---------- renew：SaaS 叠加语义 ----------

    public function testRenewRestoresExpiredAndStacksDays(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'stacker', 'plan' => 3, 'expire_at' => self::day(30),
        ]);
        $this->assertNull($error);
        $tenantId = (int) $tenant->id;

        // 到期 → 续费自动复活为启用，且从原到期日起叠加（而非今天）
        $this->service->expireMark($tenantId);
        [$renewed, $renewError] = $this->service->renew($tenantId, 30);
        $this->assertNull($renewError);
        $this->assertSame(TenantService::STATUS_ENABLED, (int) $renewed->status, '到期续费应自动恢复启用');
        $this->assertSame(self::day(60), (string) $renewed->expire_at, '应从原到期日 day(30) 叠加 30 天');

        // 再次续费叠加
        [$renewed, $renewError] = $this->service->renew($tenantId, 30);
        $this->assertNull($renewError);
        $this->assertSame(self::day(90), (string) $renewed->expire_at, '续费天数应向后叠加');
    }

    public function testRenewBackdatedExpireRestartsFromToday(): void
    {
        $this->resetRegistryTable();
        // 已过到期日但未被标记（到期任务未跑）的状态=1 行
        $this->createTenantRow(920, 9200, 'past-due', TenantService::STATUS_ENABLED, self::day(-5));

        [$renewed, $error] = $this->service->renew(920, 30);

        $this->assertNull($error);
        $this->assertSame(self::day(30), (string) $renewed->expire_at, '过期租户应自今天起重计');
        $this->assertSame(TenantService::STATUS_ENABLED, (int) $renewed->status);
    }

    public function testRenewOnSuspendedExtendsDatesOnly(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'susp-renew', 'plan' => 1, 'expire_at' => self::day(30),
        ]);
        $this->assertNull($error);
        $tenantId = (int) $tenant->id;
        $this->service->suspend($tenantId);

        [$renewed, $renewError] = $this->service->renew($tenantId, 45);

        $this->assertNull($renewError);
        $this->assertSame(TenantService::STATUS_SUSPENDED, (int) $renewed->status, '停用续费不应改变状态');
        $this->assertSame(self::day(75), (string) $renewed->expire_at, '停用续费仅延长期限');
    }

    public function testRenewValidation(): void
    {
        $this->resetRegistryTable();
        [$tenant, $error] = $this->service->provision([
            'company_id' => 1001, 'tenant_code' => 'renew-guard', 'plan' => 1, 'expire_at' => self::day(365),
        ]);
        $this->assertNull($error);
        $tenantId = (int) $tenant->id;

        // 未知租户
        $this->assertFailed($this->service->renew(999_999_999_999, 30), '租户不存在');
        // 天数越界（1-3650）
        $this->assertFailed($this->service->renew($tenantId, 0), '续费天数必须在1-3650之间');
        $this->assertFailed($this->service->renew($tenantId, 3651), '续费天数必须在1-3650之间');
        // 待开通不可续费
        $this->createTenantRow(921, 9210, 'pending-renew', TenantService::STATUS_PENDING, self::day(365));
        $this->assertFailed($this->service->renew(921, 30), '待开通租户不可续费');

        // 校验失败不应改变既有行
        $row = Tenant::query()->find($tenantId);
        $this->assertSame(self::day(365), (string) $row->expire_at, '拒绝后到期日不应变化');
        $this->assertSame(TenantService::STATUS_ENABLED, (int) $row->status);
    }

    // ---------- expiryWarnings：预警窗口与边界 ----------

    public function testExpiryWarningsWindowBoundariesAndOrder(): void
    {
        $this->resetRegistryTable();
        // 窗口内（status=1）：今天 / +10 / +30（含今天与右边界）
        $this->createTenantRow(101, 1101, 'w-today', TenantService::STATUS_ENABLED, self::day(0));
        $this->createTenantRow(102, 1102, 'w10', TenantService::STATUS_ENABLED, self::day(10));
        $this->createTenantRow(103, 1103, 'w30', TenantService::STATUS_ENABLED, self::day(30));
        // 窗口外（status=1）：+31
        $this->createTenantRow(104, 1104, 'w31', TenantService::STATUS_ENABLED, self::day(31));
        // 已过期未标记：-1
        $this->createTenantRow(105, 1105, 'w-minus', TenantService::STATUS_ENABLED, self::day(-1));
        // 非启用态一律排除：停用+5 / 到期+3 / 待开通+7
        $this->createTenantRow(106, 1106, 'w-sus', TenantService::STATUS_SUSPENDED, self::day(5));
        $this->createTenantRow(107, 1107, 'w-exp', TenantService::STATUS_EXPIRED, self::day(3));
        $this->createTenantRow(108, 1108, 'w-pend', TenantService::STATUS_PENDING, self::day(7));
        // 软删启用行：+2
        $this->createTenantRow(109, 1109, 'w-del', TenantService::STATUS_ENABLED, self::day(2), self::day(0));

        // 默认 30 天窗口：恰好 [今天, +10, +30]，到期日升序
        [$rows, $error] = $this->service->expiryWarnings();
        $this->assertNull($error);
        $codes = array_map(static fn (array $r): string => (string) $r['tenant_code'], $rows);
        $this->assertSame(['w-today', 'w10', 'w30'], $codes, '默认窗口应只含启用态且 [今天,+30] 内，按到期日升序');
        $this->assertSame([self::day(0), self::day(10), self::day(30)], array_map(
            static fn (array $r): string => (string) $r['expire_at'],
            $rows
        ), '预警行应携带到期日字段');

        // 放宽到 31 天 → 纳入 w31
        [$rows, $error] = $this->service->expiryWarnings(31);
        $this->assertNull($error);
        $codes = array_map(static fn (array $r): string => (string) $r['tenant_code'], $rows);
        $this->assertSame(['w-today', 'w10', 'w30', 'w31'], $codes, '31 天窗口应纳入边界日');
    }

    public function testExpiryWarningsValidation(): void
    {
        $this->resetRegistryTable();
        $this->assertFailed($this->service->expiryWarnings(0), '预警天数必须在1-365之间');
        $this->assertFailed($this->service->expiryWarnings(366), '预警天数必须在1-365之间');
    }

    // ---------- 数据隔离：company 族（财务试点模型用法镜像） ----------

    public function testCompanyFamilyIsolation(): void
    {
        $this->resetRegistryTable();
        self::createTableIfMissing(self::COMPANY_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 100);
        });
        Capsule::table(self::COMPANY_TABLE)->delete();

        // 公司1001 两行、公司2002 一行、company_id=NULL 历史行一行
        CompanyAwareModel::query()->create(['id' => 1, 'company_id' => 1001, 'name' => '1001-A']);
        CompanyAwareModel::query()->create(['id' => 2, 'company_id' => 1001, 'name' => '1001-B']);
        CompanyAwareModel::query()->create(['id' => 3, 'company_id' => 2002, 'name' => '2002-C']);
        CompanyAwareModel::query()->create(['id' => 4, 'company_id' => null, 'name' => 'legacy-NULL']);

        // 公司1001 视角：可见 2 行（NULL 行不可见=安全默认）
        CompanyAwareModel::setCurrentCompanyId(1001);
        $rows = CompanyAwareModel::query()->orderBy('id')->get();
        $this->assertCount(2, $rows, '公司1001 应只见自己的 2 行');
        $this->assertSame([1, 2], $rows->map(static fn ($r): int => (int) $r->id)->all());

        // 公司2002 视角：可见 1 行
        CompanyAwareModel::setCurrentCompanyId(2002);
        $this->assertSame(1, (int) CompanyAwareModel::query()->count(), '公司2002 应只见自己的 1 行');
        $this->assertSame('2002-C', (string) CompanyAwareModel::query()->first()->name);

        // 无上下文（null）：可见全部含 NULL 行 → 单租户回归线
        CompanyAwareModel::setCurrentCompanyId(null);
        $this->assertSame(4, (int) CompanyAwareModel::query()->count(), '未设租户上下文不应过滤任何数据');

        // 注册表（Tenant 模型）自身不受公司族作用域影响
        $this->createTenantRow(950, 1001, 'reg-1001', TenantService::STATUS_ENABLED, self::day(365));
        CompanyAwareModel::setCurrentCompanyId(1001);
        $this->assertSame(1, (int) Tenant::query()->count(), '租户注册表不应被公司作用域过滤（平台侧数据）');
    }
}
