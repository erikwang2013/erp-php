<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\ConsolidationService;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase;

/**
 * 合并报表服务 consolidate()：入参契约（纯内存校验，不触库）。
 * 金额/报表取数口径由集成测试（F12MultiCompanyConsolidationTest）覆盖。
 * 列表契约（list/版本列表）见文件末尾三条：无参整表下发是配置驱动页面的既定预期
 * （apps/angular/src/app/pages/resource-page/resource-page.ts:461-463）。
 */
class ConsolidationServiceTest extends TestCase
{
    public function testConsolidateEmptyRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('subsidiary_reports 不能为空');
        (new ConsolidationService())->consolidate([]);
    }

    public function testConsolidateNonArrayItemRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('subsidiary_reports[0] 必须为对象');
        (new ConsolidationService())->consolidate(['x']);
    }

    public function testConsolidateUnknownLedgerRejected(): void
    {
        // ledger_id 缺省 0、company_id 缺省 → 无库查询即拒绝
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('subsidiary_reports[0] 指向的账套不存在或已停用');
        (new ConsolidationService())->consolidate([['currency' => 'USD']]);
    }

    /* ============================ 版本列表契约（/finance/consolidation 页面） ============================ */

    /** 落库类用例的守卫：无 DB 连接即跳过（与 PurchaseModuleTest::skipIfNoDb 同款） */
    private function skipIfNoDb(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('依赖 MySQL: ' . $e->getMessage());
        }
    }

    /** 读取 Response JSON 中的业务 code */
    private function responseCode(\support\Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    /** 读取 Response JSON 中的业务 message */
    private function responseMessage(\support\Response $resp): string
    {
        $body = json_decode($resp->rawBody(), true);

        return (string) ($body['message'] ?? '');
    }

    /** 自造一行合并报表（company_id/report_year/report_month 无默认值，必须给） */
    private function seedReport(int $id, int $companyId, int $year, int $month): int
    {
        DB::table('finance_consolidation_report')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'report_year' => $year,
            'report_month' => $month,
        ]);

        return $id;
    }

    /** 从列表响应里取 id（hashid）集合 */
    private function listIds(\support\Response $resp): array
    {
        $body = json_decode($resp->rawBody(), true);

        return array_column((array) ($body['data']['list'] ?? []), 'id');
    }

    /**
     * **缺陷本体**：/finance/consolidation 是配置驱动列表页，cfg 既无 params 也无 filters
     * （两端 finance.ts 同款），引擎只发 page/limit ⇒ 无参 GET 必须整表下发。
     * 此前 list() 把 company_id 当必填，页面一打开就 422（前端对 code!==0 直接抛）。
     * 真库 + 事务回滚；断言的是「自造行在返回里」，不受库中既有行影响。
     */
    public function testListWithoutParamsReturnsSeededRows(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $base = 900000000000940000 + random_int(1, 500);
            $seeded = [
                $this->seedReport($base, 410000000000900001, 2026, 3),
                $this->seedReport($base + 1, 410000000000900002, 2025, 7),
            ];

            $resp = (new \app\controller\finance\ConsolidationController())->list(new FakeRequest([]));
            $this->assertSame(0, $this->responseCode($resp), '无参列表应成功：' . $this->responseMessage($resp));

            $ids = $this->listIds($resp);
            foreach ($seeded as $id) {
                $this->assertContains(
                    \app\common\HashidsService::encode($id),
                    $ids,
                    '无参列表应含自造行 ' . $id . '（缺省不过滤 ⇒ 整表下发）'
                );
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 上一条的反面：**传了但解不出**的 company_id 仍是非法值（不是缺省），照旧 422。
     * 纯内存分支（validator + decodeFlexibleId），不触库。
     * '0' 一并覆盖：decodeFlexibleId('0') 会返回 0（数字兜底），0 号组织不存在 ⇒ 也必须拒。
     */
    public function testListWithUnresolvableCompanyIdRejected(): void
    {
        foreach (['zzz', '0'] as $bad) {
            $resp = (new \app\controller\finance\ConsolidationController())->list(
                new FakeRequest(['company_id' => $bad])
            );
            $this->assertSame(422, $this->responseCode($resp), "company_id={$bad} 应被拒（非法值≠缺省）");
            $this->assertStringContainsString('company_id', $this->responseMessage($resp), '文案应点名 company_id');
        }
    }

    /**
     * 有参调用语义不变：三个参数都给了就必须**只**返回该组合的行
     * （防止把「缺省不过滤」修成「一律不过滤」）。
     * 真库 + 事务回滚；比较只用自造行，避免受库中既有行影响。
     */
    public function testListWithExplicitParamsFiltersToThatCombination(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $base = 900000000000950000 + random_int(1, 500);
            $companyA = 410000000000910001;
            $companyB = 410000000000910002;
            $hit1 = $this->seedReport($base, $companyA, 2026, 3);
            $hit2 = $this->seedReport($base + 1, $companyA, 2026, 3);
            $missCompany = $this->seedReport($base + 2, $companyB, 2026, 3);
            $missYear = $this->seedReport($base + 3, $companyA, 2025, 3);
            $missMonth = $this->seedReport($base + 4, $companyA, 2026, 4);

            $resp = (new \app\controller\finance\ConsolidationController())->list(new FakeRequest([
                'company_id' => \app\common\HashidsService::encode($companyA),
                'report_year' => 2026,
                'report_month' => 3,
            ]));
            $this->assertSame(0, $this->responseCode($resp), '三参列表应成功：' . $this->responseMessage($resp));

            $ids = $this->listIds($resp);
            $encode = fn (int $id): string => \app\common\HashidsService::encode($id);
            $this->assertSame([], array_diff([$encode($hit1), $encode($hit2)], $ids), '该组合的两行都不能漏');
            $this->assertSame(
                [],
                array_intersect([$encode($missCompany), $encode($missYear), $encode($missMonth)], $ids),
                '别家集团 / 别的年 / 别的月都不该返回'
            );
        } finally {
            DB::rollBack();
        }
    }
}
