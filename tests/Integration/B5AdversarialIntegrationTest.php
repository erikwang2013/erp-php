<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\middleware\TenantScope as TenantScopeMiddleware;
use app\model\Tenant;
use app\service\platform\TenantService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;
use support\Request;
use support\Response;
use tests\Integration\Fixtures\CompanyAwareModel;
use Webman\Context;
use Webman\Http\Request as WebRequest;

/**
 * B5 多租户对抗性集成测试（--group=integration）
 *
 * 环境契约/自建表/清理约定同 IntegrationTestCase（TEST_DB_* 缺省整类跳过；
 * 只建删 erp_tenant / erp_it_company_data，用例独立清空、tearDown 删表）。
 * 对抗焦点（与 B5TenantTest / TenantScopeIntegrationTest 不重复）：
 * 1) resume 不过期校验、expireMark 可提前标记（缺陷候选，按实现断言）；
 * 2) 软删行占 UNIQUE → 复开需 restore、1062 兜底、无公司存在性校验；
 * 3) strict_types 数字串/浮点 → TypeError、plan '2.9' 强转收编（缺陷候选）；
 * 4) 完整链路隔离：中间件注入 → Context 绑定 → trait 过滤——A/B 互不可见、
 *    切换无残留、请求上下文优先于静态门面、无上下文全可见（单租户回归线）；
 * 5) 预警窗 [今天, 今天+days] 含右边界、已过期未标记不入窗；
 * 6) 并发 provision 双进程 → 恰一成功恰一行落库（uk_company 兜底）。
 */
#[Group('integration')]
class B5AdversarialIntegrationTest extends IntegrationTestCase
{
    private const REGISTRY_TABLE = 'erp_tenant';

    private const COMPANY_TABLE = 'erp_it_company_data';

    /** 注册表蓝图镜像 database/b5_tenant.sql 最小结构（双 UNIQUE + deleted_at）。 */
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

    /** 公司族隔离 fixture 表（company_id nullable + 无租户列，模拟生产公司族）。 */
    private function resetCompanyTable(): void
    {
        self::createTableIfMissing(self::COMPANY_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 100);
        });
        Capsule::table(self::COMPANY_TABLE)->delete();
    }

    /** 相对今天的 Y-m-d。 */
    private function day(int $offset): string
    {
        return date('Y-m-d', strtotime("{$offset} days"));
    }

    /** 服务直开租户（companyId 需全局唯一，跨用例复用会撞 uk_company）。 */
    private function provision(int $companyId, string $code, array $extra = []): array
    {
        return (new TenantService())->provision(array_merge([
            'company_id' => $companyId,
            'tenant_code' => $code,
            'plan' => 1,
            'expire_at' => $this->day(370),
        ], $extra));
    }

    private function assertFailed(array $result, string $message, string $context = ''): void
    {
        $this->assertSame([null, $message], $result, $context);
    }

    /** 携带 X-Tenant-Code 请求头的 raw 请求（同 coder 套件构造法）。 */
    private function tenantCodeRequest(string $code): Request
    {
        return new Request("GET /admin HTTP/1.1\r\nHost: localhost\r\nX-Tenant-Code: {$code}\r\n\r\n");
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        Context::destroy();
        CompanyAwareModel::setCurrentCompanyId(null);
    }

    protected function tearDown(): void
    {
        Context::destroy();
        CompanyAwareModel::setCurrentCompanyId(null);
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::REGISTRY_TABLE);
            self::dropTableIfExists(self::COMPANY_TABLE);
        }
        parent::tearDown();
    }

    // ---------- 1. 状态机对抗 ----------

    /** 停用且已过期的租户 resume 现状放行（缺陷候选：期望拒绝并提示先续费）。 */
    public function testResumeExpiredSuspendedBypassesExpiryCheck(): void
    {
        $this->resetRegistryTable();
        [$created, $err] = $this->provision(1001, 'co-a');
        $this->assertNull($err);
        $svc = new TenantService();
        [$t, $err] = $svc->suspend((int) $created->id);
        $this->assertNull($err);
        $this->assertSame(2, (int) $t->status);
        // 模拟时间流逝：停用期间到期日已过
        Capsule::table(self::REGISTRY_TABLE)->where('id', $t->id)->update(['expire_at' => $this->day(-5)]);

        [$t2, $err] = $svc->resume((int) $t->id);
        $this->assertNull($t2, '停用期间已过期 → resume 应拒绝');
        $this->assertSame('租户已过期，请先续费', $err, '提示先续费');

        // 中间件入口兜底：resume 被拒后租户保持停用态 → 数据访问被拒
        $this->assertMiddlewareRejects($this->tenantCodeRequest('co-a'), '租户已停用');
    }

    /** expireMark 现状可提前标记未来到期租户（缺陷候选：期望校验 expire_at <= 今天）。 */
    public function testExpireMarkAllowsFutureDatedMark(): void
    {
        $this->resetRegistryTable();
        [, $err] = $this->provision(2001, 'co-2');
        $this->assertNull($err);
        $tenantId = (int) Capsule::table(self::REGISTRY_TABLE)->where('company_id', 2001)->value('id');
        $svc = new TenantService();

        [$t, $err] = $svc->expireMark($tenantId);
        $this->assertNull($t, '未到期租户不可标记');
        $this->assertSame('租户尚未到期，不能标记', $err);

        // 首次被拒后状态未变，二次标记仍报「尚未到期」而非重复标记
        $this->assertFailed($svc->expireMark($tenantId), '租户尚未到期，不能标记');
        $this->assertFailed($svc->expireMark(9999999), '租户不存在');
    }

    /** 2 停用 → expireMark → 3 到期 → renew 续费即复活为 1，且到期日从原日期叠加。 */
    public function testSuspendThenExpireThenRenewRevivesToEnabled(): void
    {
        $this->resetRegistryTable();
        [, $err] = $this->provision(3001, 'co-3');
        $this->assertNull($err);
        $tenantId = (int) Capsule::table(self::REGISTRY_TABLE)->where('company_id', 3001)->value('id');
        $svc = new TenantService();
        $this->assertNull($svc->suspend($tenantId)[1]);
        // 到期日拨到过去（模拟停用期间到期），方可标记
        Capsule::table(self::REGISTRY_TABLE)->where('id', $tenantId)->update(['expire_at' => $this->day(-1)]);
        [$t, $err] = $svc->expireMark($tenantId);
        $this->assertNull($err);
        $this->assertSame(3, (int) $t->status);

        [$t2, $err] = $svc->renew($tenantId, 30);
        $this->assertNull($err);
        $this->assertSame(1, (int) $t2->status, '到期续费即复活（SaaS 语义）');
        $this->assertSame($this->day(30), (string) $t2->expire_at, '到期日拨回过去后 renew 从今天起重计 +30');
    }

    /**
     * 续费为日历天加法而非整月：固定断言 +30 days 公式结果，且与 +1 month
     * 的结果不同（选下一年 1-31 作为种子，两个公式在闰/平年都不重合）。
     */
    public function testRenewAddsCalendarDaysNotMonths(): void
    {
        $this->resetRegistryTable();
        $this->resetCompanyTable();
        $year = (int) date('Y') + 1;
        $seed = "{$year}-01-31";
        $id = 900001;
        Capsule::table(self::REGISTRY_TABLE)->insert([
            'id' => $id, 'company_id' => 9101, 'tenant_code' => 'cal-days',
            'plan' => 1, 'status' => 1, 'expire_at' => $seed,
        ]);

        [$t, $err] = (new TenantService())->renew($id, 30);
        $this->assertNull($err);
        $expected = date('Y-m-d', strtotime('+30 days', strtotime($seed)));
        $monthAdd = date('Y-m-d', strtotime('+1 month', strtotime($seed)));
        $this->assertNotSame($expected, $monthAdd, '判别种子本身应能区分两种语义（防退化断言）');
        $this->assertSame($expected, (string) $t->expire_at, '续费应按日历天 +30，而非整月');
    }

    // ---------- 2. 唯一性对抗 ----------

    /** 软删行占用 uk_company/uk_tenant_code（UNIQUE 含已删行）→ 复开需先 restore。 */
    public function testProvisionAfterSoftDeleteHitsUniqueGuardUntilRestore(): void
    {
        $this->resetRegistryTable();
        [, $err] = $this->provision(4101, 'sd-co');
        $this->assertNull($err);
        $tenant = Tenant::query()->where('company_id', 4101)->first();
        $this->assertNotNull($tenant);
        $tenant->delete(); // 软删
        $this->assertNull(Tenant::query()->where('company_id', 4101)->first(), '软删行对常规查询不可见');

        // 预检只查未删行 → 放行 → 插入撞 uk_company（1062 兜底消息）
        [, $err2] = $this->provision(4101, 'sd-co-2');
        $this->assertSame('公司已开通租户或租户编码已存在', $err2, '软删行仍占用唯一键（1062 兜底）');
        $this->assertSame(1, (int) Capsule::table(self::REGISTRY_TABLE)
            ->where('company_id', 4101)->count(), '含软删行仍只一行');

        // restore 后预检命中 → 恢复常规拒绝路径
        $tenant->restore();
        $this->assertFailed($this->provision(4101, 'sd-co-3'), '公司已开通租户', 'restore 后预检应拦截');
    }

    /** 无外键约定：company 不存在也放行开通（服务头注释声明由上层保证）——按实现断言并记录。 */
    public function testProvisionAcceptsUnknownCompanyPerNoFkConvention(): void
    {
        $this->resetRegistryTable();
        [, $err] = $this->provision(987654321, 'ghost-co');
        $this->assertNull($err, '现状：不校验 erp_company 存在性（无外键约定，按实现断言；'
            . '期望层：调用方/路由层保证公司有效性）');
        $this->assertSame(1, (int) Capsule::table(self::REGISTRY_TABLE)->count());
    }

    // ---------- 3. 参数边界对抗 ----------

    /** plan 数字串被 (int) 强转收编：'2.9' → 2（缺陷候选，同 C1 '2.5'→2 先例）。 */
    public function testProvisionCoercesFloatyPlanString(): void
    {
        $this->resetRegistryTable();
        [$tenant, $err] = (new TenantService())->provision([
            'company_id' => 5201, 'tenant_code' => 'floaty-plan',
            'plan' => '2.9', 'expire_at' => $this->day(400),
        ]);
        $this->assertNull($tenant, "'2.9' 应被拒绝（(int) 静默强转缺陷已修）");
        $this->assertSame('套餐参数错误（1=标准 2=专业 3=旗舰）', $err);
    }

    /**
     * 调用边界：strict_types 下数字串/浮点直传 renew/expiryWarnings → TypeError
     * （业务校验消息仅 int 类型可达，路由层同为 strict 文件时不会落入业务分支）。
     */
    public function testStrictTypeBoundaryForNumericStrings(): void
    {
        $this->resetRegistryTable();
        $id = 600001;
        Capsule::table(self::REGISTRY_TABLE)->insert([
            'id' => $id, 'company_id' => 6101, 'tenant_code' => 'type-ok',
            'plan' => 1, 'status' => 1, 'expire_at' => $this->day(400),
        ]);
        $svc = new TenantService();

        foreach (['30', '0', '1e3'] as $bad) {
            try {
                $svc->renew($id, $bad);
                $this->fail("renew 数字串 {$bad} 应触发 TypeError");
            } catch (\TypeError) {
                $this->assertTrue(true);
            }
        }
        try {
            $svc->expiryWarnings('30');
            $this->fail('expiryWarnings 数字串应触发 TypeError');
        } catch (\TypeError) {
            $this->assertTrue(true);
        }

        // int 越界才到达业务消息
        $this->assertFailed($svc->renew($id, 0), '续费天数必须在1-3650之间');
        $this->assertFailed($svc->renew($id, -30), '续费天数必须在1-3650之间');
        $this->assertFailed($svc->renew($id, 3651), '续费天数必须在1-3650之间');
        $this->assertFailed($svc->expiryWarnings(0), '预警天数必须在1-365之间');
        $this->assertFailed($svc->expiryWarnings(-1), '预警天数必须在1-365之间');
        $this->assertFailed($svc->expiryWarnings(366), '预警天数必须在1-365之间');
    }

    /** 预警窗口 [今天, 今天+days] 含右边界：days=30 含 +30、不含 +31；已过期未标记(status=1)不入窗。 */
    public function testExpiryWarningsWindowInclusiveRightEdge(): void
    {
        $this->resetRegistryTable();
        foreach ([
            700001 => ['c1' => 'w-1', 'expire' => $this->day(-1)],
            700002 => ['c1' => 'w-today', 'expire' => $this->day(0)],
            700003 => ['c1' => 'w-30', 'expire' => $this->day(30)],
            700004 => ['c1' => 'w-31', 'expire' => $this->day(31)],
        ] as $id => $row) {
            Capsule::table(self::REGISTRY_TABLE)->insert([
                'id' => $id, 'company_id' => 700000 + $id % 100, 'tenant_code' => $row['c1'],
                'plan' => 1, 'status' => 1, 'expire_at' => $row['expire'],
            ]);
        }
        [$rows, $err] = (new TenantService())->expiryWarnings(30);
        $this->assertNull($err);
        $codes = array_column($rows, 'tenant_code');
        $this->assertContains('w-today', $codes, '左边界今天应含');
        $this->assertContains('w-30', $codes, '右边界 today+30 应含');
        $this->assertNotContains('w-31', $codes, 'today+31 不应含（30 天窗口）');
        $this->assertNotContains('w-1', $codes, '已过期未标记(status=1)不入预警窗（运维需另路对账——观察项）');
    }

    // ---------- 4. 数据隔离（完整链路） ----------

    /** 断言中间件 403 拒绝 shape（body code=403 + data=[] + 精确消息）。 */
    private function assertMiddlewareRejects(Request $request, string $expectMessage): void
    {
        $called = false;
        $response = (new TenantScopeMiddleware())->process($request, static function () use (&$called): Response {
            $called = true;

            return response('ok');
        });
        $this->assertFalse($called, '租户校验失败时不应放行');
        $body = json_decode((string) $response->rawBody(), true);
        $this->assertSame(403, $body['code'] ?? null);
        $this->assertSame($expectMessage, $body['message'] ?? null);
        $this->assertSame([], $body['data'] ?? null);
    }

    /**
     * 完整链路：服务开通 → 中间件按 X-Tenant-Code 注入 request 属性 → 经
     * Webman\Context 绑定为当前请求 → trait 解析 → 模型按公司过滤。A/B 互不
     * 可见；请求切换无残留；上下文销毁后回落到静态门面/null（单租户回归线）。
     */
    public function testRequestContextIsolationFullChainWithoutResidue(): void
    {
        $this->resetRegistryTable();
        $this->resetCompanyTable();
        Capsule::table(self::COMPANY_TABLE)->insert([
            ['id' => 1, 'company_id' => 8101, 'name' => 'A-数据1'],
            ['id' => 2, 'company_id' => 8101, 'name' => 'A-数据2'],
            ['id' => 3, 'company_id' => 8102, 'name' => 'B-数据1'],
        ]);
        [, $err] = $this->provision(8101, 'chain-a');
        $this->assertNull($err);
        [, $err] = $this->provision(8102, 'chain-b');
        $this->assertNull($err);

        // 请求 A：中间件注入 → 绑定当前上下文 → 只见 A 的两行
        $reqA = $this->tenantCodeRequest('chain-a');
        (new TenantScopeMiddleware())->process($reqA, static fn ($r) => response('ok'));
        Context::set(WebRequest::class, $reqA);
        $this->assertSame(2, (int) CompanyAwareModel::query()->count(), 'A 上下文只见 A 数据');
        $this->assertSame(8101, (int) $reqA->companyId, '中间件注入 companyId');
        $rowsA = CompanyAwareModel::query()->pluck('company_id')->all();
        $this->assertSame([8101, 8101], array_map('intval', $rowsA), 'A 上下文查询结果全为 A 公司');

        // 请求 B 直接覆盖同一 Context 键：A 的属性在 requestA 实例上，不残留
        $reqB = $this->tenantCodeRequest('chain-b');
        (new TenantScopeMiddleware())->process($reqB, static fn ($r) => response('ok'));
        Context::set(WebRequest::class, $reqB);
        $this->assertSame(1, (int) CompanyAwareModel::query()->count(), 'B 上下文只见 B 一行（无 A 残留）');
        $this->assertSame('B-数据1', CompanyAwareModel::query()->first()->name);

        // 请求上下文优先于静态门面（防陈旧静态干扰）
        CompanyAwareModel::setCurrentCompanyId(8101);
        $this->assertSame(1, (int) CompanyAwareModel::query()->count(), '请求上下文应压过静态门面');

        // 上下文销毁 → 回落静态 8101 → 只剩 A（记录：静态仅测试/CLI 兜底，生产不写）
        Context::destroy();
        $this->assertSame(2, (int) CompanyAwareModel::query()->count(), '销毁上下文后回到静态门面值');

        // 静态也清空 → 单租户回归线：全部可见
        CompanyAwareModel::setCurrentCompanyId(null);
        $this->assertSame(3, (int) CompanyAwareModel::query()->count(), '无任何上下文不过滤（单租户回归线）');
    }

    /** HTTP 解析层已裁剪请求头空白：' pad-co ' 命中注册行放行注入（框架边界防御，观察记录）。 */
    public function testPaddedHeaderCodeIsNormalizedByHttpParser(): void
    {
        $this->resetRegistryTable();
        $this->provision(8301, 'pad-co');
        $called = false;
        (new TenantScopeMiddleware())->process($this->tenantCodeRequest(' pad-co '), static function ($req) use (&$called): Response {
            $called = true;

            return response('ok');
        });
        $this->assertTrue($called, '头解析层裁剪空白后命中注册行并放行（垫空白不构成绕过）');
    }

    // ---------- 5. 并发 provision ----------

    /** 并发：双进程同公司开通（不同编码）→ 恰一成功 + 恰一行落库（uk_company 兜底）。 */
    public function testConcurrentProvisionSameCompanySingleWinner(): void
    {
        $this->resetRegistryTable();
        $results = $this->runChildOps([
            ['mode' => 'provision', 'args' => ['company_id' => 8401, 'tenant_code' => 'race-a']],
            ['mode' => 'provision', 'args' => ['company_id' => 8401, 'tenant_code' => 'race-b']],
        ]);
        $wins = array_values(array_filter($results, fn ($r) => $r[1] === null));
        $fails = array_values(array_filter($results, fn ($r) => $r[1] !== null));
        $this->assertCount(1, $wins, '同公司并发开通恰一成功');
        $this->assertSame('race-', substr($wins[0][0]['tenant_code'], 0, 5), '赢家编码合法');
        $this->assertCount(1, $fails);
        $this->assertContains($fails[0][1], ['公司已开通租户', '公司已开通租户或租户编码已存在'],
            '败者消息：预检拦截或 1062 兜底（视调度串行化）');
        $this->assertSame(1, (int) Capsule::table(self::REGISTRY_TABLE)
            ->where('company_id', 8401)->whereNull('deleted_at')->count(), '同公司仅一行落库');
        $this->assertSame(1, (int) Capsule::table(self::REGISTRY_TABLE)
            ->where('company_id', 8401)->count(), '含软删行亦仅一行');
    }

    // ---------- 双进程并发工具（仿 C1：子进程自建 Capsule，凭据仅走 TEST_DB_* 环境变量） ----------

    /** 并发执行 2 个独立 php 子进程，返回逐子进程的 [data|null, err|null] 解码结果（顺序同入参）。 */
    private function runChildOps(array $jobs): array
    {
        $root = dirname(__DIR__, 2);
        $script = tempnam(sys_get_temp_dir(), 'b5race');
        file_put_contents($script, <<<PHP
            <?php
            declare(strict_types=1);
            require '{$root}/vendor/autoload.php';
            \$c = new Illuminate\\Database\\Capsule\\Manager();
            \$c->addConnection([
                'driver' => 'mysql',
                'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
                'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
                'database' => (string) getenv('TEST_DB_DATABASE'),
                'username' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
                'password' => (string) getenv('TEST_DB_PASSWORD'),
                'prefix' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'strict' => true, 'engine' => 'InnoDB',
            ], 'default');
            \$c->setAsGlobal();
            \$c->bootEloquent();
            \$job = json_decode((string) \$argv[1], true);
            \$a = \$job['args'];
            \$res = (new app\\service\\platform\\TenantService())->provision([
                'company_id' => (int) \$a['company_id'],
                'tenant_code' => (string) \$a['tenant_code'],
                'plan' => 1,
                'expire_at' => date('Y-m-d', strtotime('+370 days')),
            ]);
            if (\$res[0] !== null) {
                \$res[0] = \$res[0]->toArray();
            }
            echo json_encode(\$res, JSON_UNESCAPED_UNICODE);
            PHP);
        try {
            $procs = [];
            $pipes = [];
            $env = array_filter(array_merge($_ENV, $_SERVER, [
                'TEST_DB_HOST' => (string) getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'TEST_DB_PORT' => (string) (getenv('TEST_DB_PORT') ?: 3306),
                'TEST_DB_DATABASE' => (string) getenv('TEST_DB_DATABASE'),
                'TEST_DB_USERNAME' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
                'TEST_DB_PASSWORD' => (string) getenv('TEST_DB_PASSWORD'),
            ]), 'is_scalar');
            // 先全部拉起（真并发），再逐个读输出
            foreach ($jobs as $job) {
                $proc = proc_open(
                    [PHP_BINARY, $script, json_encode($job)],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipe,
                    $root,
                    $env
                );
                $this->assertIsResource($proc, '子进程应成功拉起');
                $procs[] = $proc;
                $pipes[] = $pipe;
            }
            $results = [];
            foreach ($procs as $i => $proc) {
                $stdout = stream_get_contents($pipes[$i][1]);
                $stderr = stream_get_contents($pipes[$i][2]);
                fclose($pipes[$i][1]);
                fclose($pipes[$i][2]);
                $exit = proc_close($proc);
                $decoded = json_decode((string) $stdout, true);
                $this->assertIsArray($decoded, "子进程应输出可解码 JSON（exit={$exit} stderr={$stderr}）");
                $results[] = $decoded;
            }

            return $results;
        } finally {
            @unlink($script);
        }
    }
}
