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

    /* ============================ 抵销金额的边界校验（客户端可控串直入 bcmath） ============================ */

    /** 自造一份可编辑草稿：addElimination 只接受 status=0 的报表 */
    private function seedDraftReport(int $id, int $companyId): int
    {
        DB::table('finance_consolidation_report')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'report_year' => 2026,
            'report_month' => 3,
            'status' => 0,
        ]);

        return $id;
    }

    /** 自造一个真科目：addElimination 会拿 account_code 去 erp_finance_account 验存在性 */
    private function seedAccount(int $id, string $code): string
    {
        DB::table('finance_account')->insert([
            'id' => $id,
            'code' => $code,
            'name' => '抵销测试科目',
            'type' => 1,
            'direction' => 1,
            'status' => 1,
        ]);

        return $code;
    }

    /** 经控制器打一次 /finance/consolidation/eliminations（等于真实请求那条路） */
    private function eliminate(int $reportId, array $rows): \support\Response
    {
        return (new \app\controller\finance\ConsolidationController())->eliminations(new FakeRequest([
            'report_id' => \app\common\HashidsService::encode($reportId),
            'eliminations' => $rows,
        ]));
    }

    /**
     * **缺陷本体**：抵销行金额是客户端可控字符串，此前不进任何形状校验就喂 bcmath ——
     * 'abc' / '1e-5' 会让 bccomp 抛 ValueError（PHP 8 起 bcmath 对畸形数字抛异常，
     * 不再静默当 0），而 ValueError 属 Error 不属 RuntimeException ⇒ 控制器的
     * `catch (\RuntimeException)` 拦不住 ⇒ 500（真栈实测两条 TraceId）。形状不对必须在
     * 边界拦成 422。用例值照抄真栈那两条，外加同族 '--1' / '1,000' / 数组 / 布尔。
     * 借方贷方都要覆盖；另一侧给 '100' 以免落进「两侧同时为 0」那条既有 422。
     */
    public function testEliminationRejectsMalformedAmountsInsteadOf500(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $reportId = $this->seedDraftReport(900000000000960000 + random_int(1, 500), 410000000000920001);
            $cases = [
                ['debit_amount', 'abc'],
                ['debit_amount', '1e-5'],
                ['debit_amount', '1.0E-5'],
                ['debit_amount', '--1'],
                ['debit_amount', '1,000'],
                ['debit_amount', ['1']],  // ?debit_amount[]=1：字符串化为 'Array'，同样喂不得
                ['debit_amount', true],
                ['credit_amount', 'abc'],
                ['credit_amount', '1.0E-5'],
            ];
            foreach ($cases as [$field, $bad]) {
                $row = ['account_code' => 'ELIM-XX', 'debit_amount' => '100', 'credit_amount' => '100'];
                $row[$field] = $bad;
                $resp = $this->eliminate($reportId, [$row]);
                $this->assertSame(
                    422,
                    $this->responseCode($resp),
                    $field . '=' . json_encode($bad, JSON_UNESCAPED_UNICODE) . ' 应 422：' . $this->responseMessage($resp)
                );
                $this->assertStringContainsString($field, $this->responseMessage($resp), '文案应点名出错的字段');
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 放行集不能收窄过头：空串与缺省仍是 0（bcmath 自身的语义），正常金额照旧成功。
     * 两行拼一个平衡批次 —— 单行两侧不平时会先撞「借贷不平衡」那条既有校验。
     */
    public function testEliminationEmptyAndAbsentAmountsStillTreatedAsZero(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $base = 900000000000960500 + random_int(1, 400);
            $reportId = $this->seedDraftReport($base, 410000000000920002);
            $code = $this->seedAccount($base + 1, 'ELIM-OK' . random_int(1000, 9999));

            $resp = $this->eliminate($reportId, [
                ['account_code' => $code, 'debit_amount' => '', 'credit_amount' => '1.50'],   // 空串 = 0
                ['account_code' => $code, 'debit_amount' => '1.50'],                          // 缺省 = 0
            ]);
            $this->assertSame(0, $this->responseCode($resp), '空串/缺省应按 0 处理：' . $this->responseMessage($resp));
            $this->assertNotNull(
                json_decode($resp->rawBody(), true)['data']['report_data']['eliminations'][0]['debit_amount'] ?? null,
                '成功时应返回落库后的抵销行'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 同类第二种形状：account_code / summary 传非标量（?account_code[]=1401）时，
     * `(string)` 转换触发 PHP Warning，webman 把 Warning 转成 ErrorException（不属
     * RuntimeException）⇒ 500（真栈实测两条 TraceId，日志为本服务 :241 的 ErrorException）。
     * 单测里 500 不会自然出现（PHPUnit 不转 Warning），所以这里连机制一起钉：
     * 既断言 422 面，也断言**不产生 PHP Warning** —— 后者才是摘掉守卫时会红的那条。
     */
    public function testEliminationNonScalarTextFieldsRejectedWithoutPhpWarning(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $reportId = $this->seedDraftReport(900000000000961500 + random_int(1, 400), 410000000000920004);
            $warnings = [];
            set_error_handler(static function (int $no, string $msg) use (&$warnings): bool {
                $warnings[] = $msg;

                return true;
            });
            try {
                foreach (['account_code', 'summary'] as $field) {
                    $row = ['account_code' => 'ELIM-TX', 'debit_amount' => '100', 'credit_amount' => '100'];
                    $row[$field] = ['x'];
                    $resp = $this->eliminate($reportId, [$row]);
                    $this->assertSame(422, $this->responseCode($resp), $field . ' 传数组应 422：' . $this->responseMessage($resp));
                    // 先钉机制再钉文案：摘掉守卫时这里会红在 ['Array to string conversion'] 上，
                    // 那正是真栈 500 的成因；文案断言在它后面（旧行为下文案是「科目不存在：Array」）。
                    $this->assertSame(
                        [],
                        $warnings,
                        $field . ' 传数组不得触发 PHP Warning（webman 会转 ErrorException ⇒ 500）'
                    );
                    $this->assertStringContainsString($field, $this->responseMessage($resp), '文案应点名出错的字段');
                }
            } finally {
                restore_error_handler();
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * :248 的既有 422 不回归：两侧算出来都是 0（空串 / '0.00' / '0.000'）时照旧拒。
     * 这条不看金额形状，只看数值为 0 ⇒ 不需要真科目（在科目存在性校验之前就抛）。
     */
    public function testEliminationBothSidesZeroStillRejected(): void
    {
        $this->skipIfNoDb();

        DB::beginTransaction();
        try {
            $reportId = $this->seedDraftReport(900000000000961000 + random_int(1, 400), 410000000000920003);
            foreach ([['', ''], ['0.00', '0.000'], ['-0', '+0']] as [$debit, $credit]) {
                $resp = $this->eliminate($reportId, [
                    ['account_code' => 'ELIM-ZERO', 'debit_amount' => $debit, 'credit_amount' => $credit],
                ]);
                $this->assertSame(
                    422,
                    $this->responseCode($resp),
                    "借 {$debit} / 贷 {$credit} 都算 0，应仍拒：" . $this->responseMessage($resp)
                );
                $this->assertStringContainsString('不能同时为 0', $this->responseMessage($resp), '应是「同时为 0」那条校验，不是新加的金额形状校验');
            }
        } finally {
            DB::rollBack();
        }
    }
}
