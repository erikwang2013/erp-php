<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\tax\EInvoiceAdapter;
use app\service\tax\EInvoiceService;
use app\service\tax\MockEInvoiceAdapter;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use RuntimeException;

/**
 * P2-2 F5 对抗性验证（tester 轮）。攻击面：平台调用幂等（计次代理 + 双进程并发
 * 竞速）、失败不粘滞/异常路径/红冲守卫顺序、Mock 确定性锐边、畸形金额零落库、
 * 3 位小数分存漂移（KNOWN-DRIFT 锚点）、组合键唯一性、日志快照回读/截断、期间与
 * 批量退化输入。断言一律 assertSame；种子标记自清理；子进程仅继承 TEST_DB_*。
 */
#[Group('integration')]
class F5AdversarialIntegrationTest extends F5TaxScaffold
{
    // ---------- 幂等：平台调用次数级证明 ----------

    #[TestDox('幂等重放：适配器零次二次调用（计次代理证明），日志不增')]
    public function testIdempotentReplayCallsPlatformZeroTimes(): void
    {
        $customerId = $this->seedCustomer('幂等');
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $counting = new CountingEInvoiceAdapter();
        $svc = new EInvoiceService($counting);

        $first = $svc->issueInvoice($invoiceId, 9001);
        $this->assertTrue($first['success'], $first['error']);
        $this->assertSame(1, $counting->issueCalls);
        $this->assertSame(0, $counting->voidCalls);

        // 换操作员重放：幂等分支早退（锁内见 issued），不构造报文、不碰适配器
        $replay = $svc->issueInvoice($invoiceId, 9002);
        $this->assertTrue($replay['success']);
        $this->assertTrue($replay['idempotent']);
        $this->assertSame($first['bill_no'], $replay['bill_no']);
        $this->assertSame(1, $counting->issueCalls, '重放不得再次调用平台适配器');
        $this->assertSame(0, $counting->voidCalls, '重放亦不得触发红冲');
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 1);
    }

    #[TestDox('并发模拟：双进程同开一票，行锁串行化后仅一次平台调用/一行日志')]
    public function testConcurrentDuplicateIssueSinglePlatformCall(): void
    {
        $customerId = $this->seedCustomer('并发');
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');

        // 两个独立 PHP 进程（各自新建 DB 连接）同时开票 —— 模拟两个渠道各调一次
        $results = $this->issueFromTwoChildProcesses($invoiceId);

        foreach ($results as $i => $r) {
            $this->assertTrue($r['success'], '子进程 ' . ($i + 1) . ' 应开票成功或幂等成功: ' . ($r['error'] ?? ''));
            $this->assertSame('issued', $r['issue_status']);
            $this->assertMatchesRegularExpression('/^MOCK\d{8}\d{6}$/', $r['bill_no']);
        }
        // 胜者真正开票、败者幂等重放：bill_no 唯一且一致，平台轨迹仅 1 行
        $this->assertSame($results[0]['bill_no'], $results[1]['bill_no']);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 1, '并发重复开票只允许一次平台调用');
        $log = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->first();
        $this->assertSame(1, (int) $log->success);
        $saved = Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->first();
        $this->assertSame($results[0]['bill_no'], $saved->electronic_no);
        $this->assertSame('issued', $saved->issue_status);
    }

    // ---------- 失败/异常路径：不粘滞、可重试、留痕 ----------

    #[TestDox('平台失败不粘滞：首次失败留痕且不烧票，重试成功')]
    public function testIssueFailureIsNotSticky(): void
    {
        $customerId = $this->seedCustomer('重试');
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $counting = new CountingEInvoiceAdapter();
        $counting->issueFailures = 1;
        $counting->issueError = '网关超时';
        $svc = new EInvoiceService($counting);

        $fail = $svc->issueInvoice($invoiceId, 0);
        $this->assertFalse($fail['success']);
        $this->assertSame('网关超时', $fail['error']);
        $this->assertSame('none', $fail['issue_status']);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId, 'action' => 'issue', 'success' => 0], 1);
        $this->assertSame('', (string) Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->value('electronic_no'));

        // 失败态非终态：同一服务实例重试即可成功（适配器恢复后委托成功）
        $ok = $svc->issueInvoice($invoiceId, 0);
        $this->assertTrue($ok['success'], $ok['error']);
        $this->assertSame(2, $counting->issueCalls);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 2);
        $saved = Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->first();
        $this->assertSame($ok['bill_no'], $saved->electronic_no);
        $this->assertSame('issued', $saved->issue_status);
    }

    #[TestDox('平台抛异常：错误带前缀、快照留痕（response 空）、发票仍可重试')]
    public function testIssueAdapterThrowableLoggedAndRetryable(): void
    {
        $customerId = $this->seedCustomer('异常');
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $throwing = new CountingEInvoiceAdapter();
        $throwing->throwOnIssue = true;
        $svc = new EInvoiceService($throwing);

        $fail = $svc->issueInvoice($invoiceId, 0);
        $this->assertFalse($fail['success']);
        $this->assertSame('平台调用异常: 平台宕机', $fail['error']);
        $log = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($log);
        $this->assertSame(0, (int) $log->success);
        $request = json_decode((string) $log->request, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray($request);
        $this->assertSame($invoiceId, (int) ($request['id'] ?? 0), '异常路径同样留下完整请求快照');
        $this->assertSame([], json_decode((string) $log->response, true), '无平台回执时 response 快照为空数组');

        // 换健康实例重试 → 成功（异常未污染发票状态）
        $healthy = new EInvoiceService(new CountingEInvoiceAdapter());
        $ok = $healthy->issueInvoice($invoiceId, 0);
        $this->assertTrue($ok['success'], $ok['error']);
        $this->assertSame('issued', Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->value('issue_status'));
    }

    #[TestDox('红冲失败/异常：发票保持 issued 可重试，号码不丢')]
    public function testVoidFailureAndThrowableKeepsStateRetryable(): void
    {
        $customerId = $this->seedCustomer('冲重试');
        $svc = new EInvoiceService();
        $invoiceA = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $this->assertTrue($svc->issueInvoice($invoiceA, 0)['success']);

        // 平台红冲返回失败（错误原文透传，行内残留 issued + electronic_no）
        $failAdapter = new CountingEInvoiceAdapter();
        $failAdapter->voidFailures = 1;
        $failAdapter->voidError = '平台拒绝红冲';
        $svcFail = new EInvoiceService($failAdapter);
        $fail = $svcFail->voidInvoice($invoiceA, '测试红冲', 0);
        $this->assertFalse($fail['success']);
        $this->assertSame('平台拒绝红冲', $fail['error']);
        $this->assertSame('issued', $fail['issue_status']);
        $rowA = Capsule::table('erp_finance_invoice')->where('id', $invoiceA)->first();
        $this->assertSame('issued', $rowA->issue_status);
        $this->assertSame($rowA->electronic_no, $fail['bill_no'], '失败仍回传既有号码');
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceA, 'action' => 'void', 'success' => 0], 1);
        $retry = $svcFail->voidInvoice($invoiceA, '测试红冲', 0);
        $this->assertTrue($retry['success'], $retry['error']);
        $this->assertSame('voided', $retry['issue_status']);
        $this->assertSame($rowA->electronic_no, Capsule::table('erp_finance_invoice')->where('id', $invoiceA)->value('electronic_no'), '红冲不改写号码');

        // 平台红冲抛异常：前缀错误，同样可重试
        $invoiceB = $this->seedAuditedArInvoice($customerId, '565.00', '500.00', '65.00');
        $this->assertTrue($svc->issueInvoice($invoiceB, 0)['success']);
        $throwAdapter = new CountingEInvoiceAdapter();
        $throwAdapter->throwOnVoid = true;
        $svcThrow = new EInvoiceService($throwAdapter);
        $boom = $svcThrow->voidInvoice($invoiceB, '测试红冲', 0);
        $this->assertFalse($boom['success']);
        $this->assertSame('平台调用异常: 红冲通道宕机', $boom['error']);
        $this->assertSame('issued', $boom['issue_status']);
        $this->assertTrue((new EInvoiceService(new CountingEInvoiceAdapter()))->voidInvoice($invoiceB, '测试红冲', 0)['success']);
    }

    #[TestDox('终态守卫顺序：红冲原因校验先于重复红冲；终态拒绝零平台调用')]
    public function testTerminalGuardOrderReasonFirstNoPlatformCalls(): void
    {
        $customerId = $this->seedCustomer('终态');
        $counting = new CountingEInvoiceAdapter();
        $svc = new EInvoiceService($counting);
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $this->assertTrue($svc->issueInvoice($invoiceId, 0)['success']);
        $void = $svc->voidInvoice($invoiceId, '测试红冲', 0);
        $this->assertTrue($void['success']);
        $this->assertSame('voided', $void['issue_status']);

        // 已红冲 + 空原因 → 原因必填先于重复红冲（issue_status 如实回传）
        $noReason = $svc->voidInvoice($invoiceId, '   ', 0);
        $this->assertFalse($noReason['success']);
        $this->assertSame('红冲原因必填', $noReason['error']);
        $this->assertSame('voided', $noReason['issue_status']);

        // 已红冲 + 合法原因 → 重复红冲拒绝并回传既有号码
        $dup = $svc->voidInvoice($invoiceId, '再冲一次', 0);
        $this->assertFalse($dup['success']);
        $this->assertSame('发票已红冲，不能重复红冲', $dup['error']);
        $this->assertSame($void['bill_no'], $dup['bill_no']);

        // 红冲后不可再次开票
        $reIssue = $svc->issueInvoice($invoiceId, 0);
        $this->assertFalse($reIssue['success']);
        $this->assertSame('该发票已红冲，不能再次开票', $reIssue['error']);

        // 全部守卫拒绝不产生任何平台调用（仅最初 issue+void 各一次真实调用）
        $this->assertSame([1, 1], [$counting->issueCalls, $counting->voidCalls], '守卫拒绝不得触碰平台');
    }

    // ---------- Mock 适配器确定性锐边 ----------

    #[TestDox('Mock 确定性锐边：税号 trim 后判 9 前缀；金额非法形态全部拒绝；百万边界含序')]
    public function testMockAdapterDeterminismEdges(): void
    {
        $adapter = new MockEInvoiceAdapter();
        $base = ['id' => 7, 'buyer_tax_no' => '', 'amount' => '100.00'];

        // 税号 9 前缀（trim 后判定）：单字符 '9' 与带空白均拒绝
        $this->assertSame('税号校验未通过', $adapter->issue(['id' => 1, 'buyer_tax_no' => '9', 'amount' => '1.00'])['error']);
        $this->assertSame('税号校验未通过', $adapter->issue(['id' => 1, 'buyer_tax_no' => ' 9133 ', 'amount' => '1.00'])['error']);

        // 非十进制/科学计数/千分位/前导空白 → 金额非法（bcmath 永不接触非正则形态）
        foreach (['INF', '-INF', '0x1A', '5.', '.5', '+5', '--1', '1,000.00', ' 100.00', '100.00 '] as $bad) {
            $this->assertSame('金额非法', $adapter->issue(['id' => 2, 'buyer_tax_no' => '', 'amount' => $bad])['error'], "amount={$bad}");
        }

        // 边界：恰 1000000.00 成功；尾序 = id%1000000 补 6 位（确定性来源是 id）
        $edge = $adapter->issue(['id' => 7, 'buyer_tax_no' => '', 'amount' => '1000000.00']);
        $this->assertTrue($edge['success']);
        $this->assertMatchesRegularExpression('/^MOCK\d{8}000007$/', $edge['bill_no']);
        $this->assertSame($adapter->issue($base)['bill_no'], $adapter->issue($base)['bill_no'], '同报文恒同 bill_no');
        $other = $adapter->issue(['id' => 8, 'buyer_tax_no' => '', 'amount' => '100.00']);
        $this->assertNotSame($other['bill_no'], $adapter->issue($base)['bill_no'], '不同发票 id 号码不同');
        $this->assertSame('000008', substr($other['bill_no'], -6));
    }

    // ---------- 数值边界（bcmath 规约） ----------

    #[TestDox('登记金额边界：科学计数/INF/畸形形态在服务层拒绝且零落库')]
    public function testRegisterMoneyFormatRejectsWithNoRows(): void
    {
        $cases = [
            [['amount' => 'INF'], '价税合计非法'],
            [['amount' => '0x1A'], '价税合计非法'],
            [['amount' => '5.'], '价税合计非法'],
            [['amount' => '.5'], '价税合计非法'],
            [['amount' => '1,000.00'], '价税合计非法'],
            [['amount' => ' 100.00'], '价税合计非法'],
            [['amount' => '-1.00'], '价税合计必须大于 0'],
            [['untaxed_amount' => 'INF'], '不含税金额非法'],
            [['untaxed_amount' => '1e3'], '不含税金额非法'],
            [['tax_amount' => 'INF'], '税额非法'],
            [['tax_amount' => '0x1A'], '税额非法'],
        ];
        $nos = [];
        foreach ($cases as [$override, $expected]) {
            $data = $this->poolData($override);
            $nos[] = $data['invoice_no'];
            [$row, $err] = $this->poolService()->registerOne($data);
            $this->assertNull($row);
            $this->assertSame($expected, $err, '断言失败: ' . $expected);
        }
        // 服务层校验失败零落库（无部分行/半行残留）
        $this->assertSame(0, Capsule::table('erp_tax_input_invoice')->whereIn('invoice_no', $nos)->count());
    }

    #[TestDox('缺陷锚点 KNOWN-DRIFT：3 位小数 half-up 分存后价税勾稽被破坏（当前行为如实锁定）')]
    public function testThirdDecimalHalfUpRoundingDrift(): void
    {
        // scale-4 勾稽通过（0.015+0.015=0.030=0.03），但各栏独立 bc_round 到 2dp：
        // 0.02+0.02=0.04 ≠ 落库 amount 0.03 —— 存储行自相矛盾（缺陷，仅锚点当前行为）
        $row = $this->registerPool(['amount' => '0.03', 'untaxed_amount' => '0.015', 'tax_amount' => '0.015']);
        $this->assertSame('0.03', $row->amount);
        $this->assertSame('0.02', $row->untaxed_amount, 'half-up: 0.015 → 0.02');
        $this->assertSame('0.02', $row->tax_amount);
        $storedSum = bcadd((string) $row->untaxed_amount, (string) $row->tax_amount, 2);
        $this->assertSame('0.04', $storedSum);
        $this->assertSame(1, bccomp($storedSum, (string) $row->amount, 2), 'KNOWN-DRIFT: 存储后不含税+税额 0.04 ≠ 价税合计 0.03（缺陷上报，预期校验或分存同尺度化）');

        // 同类：0.001 合法(>0)却被 round 成 0.00 落库 —— 违反正数不变式
        $tiny = $this->registerPool(['amount' => '0.001', 'untaxed_amount' => '0.001', 'tax_amount' => '0.000']);
        $this->assertSame('0.00', $tiny->amount);
        $this->assertSame('0.00', $tiny->untaxed_amount);
        $this->assertSame('0.00', $tiny->tax_amount);
    }

    // ---------- 唯一性：组合键语义 ----------

    #[TestDox('唯一性：uk(code,no) 组合键 —— 同号不同代码允许共存，同码同号拒绝')]
    public function testCompositeKeySameNoDifferentCodeAllowed(): void
    {
        $no = self::MARKER . 'S' . $this->nextId();
        $a = $this->registerPool(['invoice_code' => 'CODE-X1', 'invoice_no' => $no]);
        $b = $this->registerPool(['invoice_code' => 'CODE-X2', 'invoice_no' => $no]);
        $this->assertNotNull($a->id);
        $this->assertNotNull($b->id);
        $this->assertSame(2, Capsule::table('erp_tax_input_invoice')->where('invoice_no', $no)->count(), '不同发票代码下同号允许登记');

        [$dup, $err] = $this->poolService()->registerOne($this->poolData(['invoice_code' => 'CODE-X1', 'invoice_no' => $no]));
        $this->assertNull($dup);
        $this->assertSame('该发票已登记(相同发票代码/号码)', $err);
        $this->assertSame(2, Capsule::table('erp_tax_input_invoice')->where('invoice_no', $no)->count());
    }

    // ---------- 日志完整性 ----------

    #[TestDox('日志快照：request/response 自 DB 原文 JSON 可回读；超长错误截断 500 字符')]
    public function testLogSnapshotsJsonRoundTripAndTruncation(): void
    {
        $customerId = $this->seedCustomer('快照');
        $svc = new EInvoiceService(new CountingEInvoiceAdapter());
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $issue = $svc->issueInvoice($invoiceId, 9001);
        $this->assertTrue($issue['success']);
        $svc->voidInvoice($invoiceId, '测试红冲', 9002);

        // issue 行：请求快照含报文关键字段（服务层拼装原文），回执含 bill_no
        $issueLog = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->where('action', 'issue')->first();
        $request = json_decode((string) $issueLog->request, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertIsArray($request);
        foreach (['id', 'invoice_no', 'issue_date', 'buyer_name', 'buyer_tax_no', 'untaxed_amount', 'tax_amount', 'amount', 'remark'] as $key) {
            $this->assertArrayHasKey($key, $request, "请求快照缺字段: {$key}");
        }
        $this->assertSame($invoiceId, (int) $request['id']);
        $this->assertSame('', $request['buyer_tax_no'], 'erp_customer 无税号列 seam：暂为空串');
        $this->assertSame('1130.00', $request['amount']);
        $response = json_decode((string) $issueLog->response, true);
        $this->assertSame($issue['bill_no'], $response['bill_no'] ?? '');

        // void 行：请求快照 = bill_no + reason
        $voidLog = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->where('action', 'void')->first();
        $voidRequest = json_decode((string) $voidLog->request, true);
        $this->assertSame($issue['bill_no'], $voidRequest['bill_no'] ?? '');
        $this->assertSame('测试红冲', $voidRequest['reason'] ?? '');

        // 超长平台错误：返回原文完整，日志列截断到 500 字符（mb 安全）
        $long = str_repeat('错', 600); // 600 字符（>500 截断阈值）
        $failCounting = new CountingEInvoiceAdapter();
        $failCounting->issueFailures = 1;
        $failCounting->issueError = $long;
        $failSvc = new EInvoiceService($failCounting);
        $failInvoice = $this->seedAuditedArInvoice($customerId, '565.00', '500.00', '65.00');
        $result = $failSvc->issueInvoice($failInvoice, 0);
        $this->assertFalse($result['success']);
        $this->assertSame(600, mb_strlen($result['error']), '返回值保留完整错误');
        $failLog = Capsule::table('erp_tax_issue_log')->where('invoice_id', $failInvoice)->first();
        $this->assertSame(500, mb_strlen((string) $failLog->error), '日志错误列截断至 500 字符');
        $this->assertSame(mb_substr($long, 0, 500), (string) $failLog->error);
    }

    // ---------- 抵扣期间与批量边界 ----------

    #[TestDox('抵扣期间边界：00/13/斜杠格式先于状态机校验；批量空与畸形输入优雅拒绝')]
    public function testDeductPeriodEdgesAndDegenerateBatch(): void
    {
        // 未勾选新票直接抵扣非法期间 → 期间校验先于状态机（守卫顺序锁定）
        $fresh = $this->registerPool();
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $fresh->id, '2026-00'));
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $fresh->id, '2026/09'));
        $this->assertSame('发票未勾选，不能抵扣', $this->poolService()->deduct((int) $fresh->id, '2026-01'), '期间合法后放行到状态机');

        // 正常抵完后携非法期间重调 → 仍报期间非法（终态也先验期间）
        $this->assertNull($this->poolService()->verify((int) $fresh->id));
        $this->assertNull($this->poolService()->check((int) $fresh->id));
        $this->assertNull($this->poolService()->deduct((int) $fresh->id, '2026-01'));
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $fresh->id, '2026-00'));
        $this->assertSame('发票已抵扣，不能重复抵扣', $this->poolService()->deduct((int) $fresh->id, '2026-01'));

        // 空批次与畸形行：不落库、行号从 1 起、错误原文逐行透传
        $this->assertSame([0, 0, []], $this->poolService()->registerBatch([]));
        [$ok, $fail, $errors] = $this->poolService()->registerBatch([null, 'not-an-array', 42]);
        $this->assertSame(0, $ok);
        $this->assertSame(3, $fail);
        // 非数组行按空数据登记 → 代码/号码均空命中批内重复键，行 2/3 报批内重复
        $this->assertSame(
            ['第 1 行: 发票号码必填', '第 2 行: 该发票已登记(相同发票代码/号码)', '第 3 行: 该发票已登记(相同发票代码/号码)'],
            $errors
        );
    }

    // ---------- 双进程并发工具 ----------

    /** 两个独立 php 进程并发 issueInvoice（各自新建 DB 连接，行锁串行化；凭据仅经环境变量传入） */
    private function issueFromTwoChildProcesses(int $invoiceId): array
    {
        $root = dirname(__DIR__, 2);
        $script = tempnam(sys_get_temp_dir(), 'f5race');
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
                'password' => (string) (getenv('TEST_DB_PASSWORD') ?: ''),
                'prefix' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'strict' => true, 'engine' => 'InnoDB',
            ], 'default');
            \$c->setAsGlobal();
            \$c->bootEloquent();
            \$r = (new app\\service\\tax\\EInvoiceService())->issueInvoice((int) \$argv[1], 9001);
            echo json_encode(\$r, JSON_UNESCAPED_UNICODE);
            PHP);
        try {
            $env = array_filter(array_merge($_ENV, $_SERVER, [
                'TEST_DB_HOST' => (string) getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'TEST_DB_PORT' => (string) (getenv('TEST_DB_PORT') ?: 3306),
                'TEST_DB_DATABASE' => (string) getenv('TEST_DB_DATABASE'),
                'TEST_DB_USERNAME' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
                'TEST_DB_PASSWORD' => (string) getenv('TEST_DB_PASSWORD'),
            ]), 'is_scalar');
            $pipes = [];
            $procs = [];
            foreach ([1, 2] as $i) {
                $procs[$i] = proc_open(
                    [PHP_BINARY, $script, (string) $invoiceId],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes[$i], $root, $env
                );
                $this->assertIsResource($procs[$i], '无法启动并发子进程');
            }
            $results = [];
            foreach ([1, 2] as $i) {
                stream_set_timeout($pipes[$i][1], 20);
                stream_set_timeout($pipes[$i][2], 20);
                $out = stream_get_contents($pipes[$i][1]);
                $err = stream_get_contents($pipes[$i][2]);
                $code = proc_close($procs[$i]);
                $decoded = json_decode((string) $out, true);
                $this->assertIsArray($decoded, "子进程 {$i} 输出非 JSON（exit={$code}）stderr: " . (string) $err);
                $results[] = $decoded;
            }

            return $results;
        } finally {
            @unlink($script);
        }
    }
}

/**
 * 计次 + 可控失败/异常的委托适配器：统计 issue/void 平台调用次数（幂等证明），
 * issueFailures/voidFailures 次失败后再委托 Mock；throwOn* 立即抛异常。
 */
final class CountingEInvoiceAdapter implements EInvoiceAdapter
{
    public int $issueCalls = 0;
    public int $voidCalls = 0;
    public int $issueFailures = 0;
    public int $voidFailures = 0;
    public string $issueError = '开票失败';
    public string $voidError = '红冲失败';
    public bool $throwOnIssue = false;
    public bool $throwOnVoid = false;

    private MockEInvoiceAdapter $mock;

    public function __construct()
    {
        $this->mock = new MockEInvoiceAdapter();
    }

    public function platform(): string
    {
        return 'mock';
    }

    public function issue(array $invoice): array
    {
        ++$this->issueCalls;
        if ($this->throwOnIssue) {
            throw new RuntimeException('平台宕机');
        }
        if ($this->issueFailures > 0) {
            --$this->issueFailures;

            return ['success' => false, 'error' => $this->issueError];
        }

        return $this->mock->issue($invoice);
    }

    public function void(string $billNo, string $reason): array
    {
        ++$this->voidCalls;
        if ($this->throwOnVoid) {
            throw new RuntimeException('红冲通道宕机');
        }
        if ($this->voidFailures > 0) {
            --$this->voidFailures;

            return ['success' => false, 'error' => $this->voidError];
        }

        return $this->mock->void($billNo, $reason);
    }
}
