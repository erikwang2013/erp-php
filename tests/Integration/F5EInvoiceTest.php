<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\tax\MockEInvoiceAdapter;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * P2-2 F5 数电票开票出口集成测试：MockEInvoiceAdapter 确定性规则 + EInvoiceService
 * 状态机（none → issued → voided）与幂等、红冲语义、平台调用日志。
 */
#[Group('integration')]
class F5EInvoiceTest extends F5TaxScaffold
{
    public function testIssueSuccessAndIdempotency(): void
    {
        $customerId = $this->seedCustomer('开票');
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');

        $result = $this->einvoiceService()->issueInvoice($invoiceId, 9001);
        $this->assertTrue($result['success'], $result['error']);
        $this->assertSame('issued', $result['issue_status']);
        $this->assertMatchesRegularExpression('/^MOCK\d{8}\d{6}$/', $result['bill_no']);
        $this->assertFalse(isset($result['idempotent']));

        // DB 落 electronic_no + issue_status
        $saved = Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->first();
        $this->assertSame($result['bill_no'], $saved->electronic_no);
        $this->assertSame('issued', $saved->issue_status);
        // 平台调用轨迹：1 条 issue 成功日志
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 1);
        $log = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->first();
        $this->assertSame('issue', $log->action);
        $this->assertSame('mock', $log->platform);
        $this->assertSame(1, (int) $log->success);
        $this->assertSame($result['bill_no'], $log->bill_no);
        $this->assertSame(9001, (int) $log->operator_id);

        // 幂等重放：不调平台（日志不增）、返回既有号码
        $replay = $this->einvoiceService()->issueInvoice($invoiceId, 9002);
        $this->assertTrue($replay['success']);
        $this->assertTrue($replay['idempotent']);
        $this->assertSame($result['bill_no'], $replay['bill_no']);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 1);
    }

    public function testIssueGuards(): void
    {
        $customerId = $this->seedCustomer('守卫');
        $svc = $this->einvoiceService();

        // 不存在 → 发票不存在
        $missing = $svc->issueInvoice($this->nextId(), 0);
        $this->assertFalse($missing['success']);
        $this->assertSame('发票不存在', $missing['error']);

        // type 非 ar（先于状态判断）
        $ap = $this->seedInvoice(['type' => 'ap', 'status' => 'audited', 'customer_id' => $customerId]);
        $resAp = $svc->issueInvoice($ap, 0);
        $this->assertFalse($resAp['success']);
        $this->assertSame('仅应收(ar)发票可开具数电票', $resAp['error']);

        // 未审核
        $draft = $this->seedInvoice(['status' => 'draft', 'customer_id' => $customerId]);
        $resDraft = $svc->issueInvoice($draft, 0);
        $this->assertFalse($resDraft['success']);
        $this->assertSame('仅 已审核(audited) 状态发票可开具数电票', $resDraft['error']);

        // guard 拒绝不写日志（无平台调用即无轨迹）
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $ap], 0);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $draft], 0);

        // 未开票发票红冲 → 拒绝
        $fresh = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $voidFresh = $svc->voidInvoice($fresh, '测试红冲', 0);
        $this->assertFalse($voidFresh['success']);
        $this->assertSame('该发票未开具数电票，不能红冲', $voidFresh['error']);
    }

    public function testIssueAmountCap(): void
    {
        $customerId = $this->seedCustomer('限额');
        // Mock 规则：价税合计 > 1000000.00 拒绝（确定性，金额原样从发票带出）
        $big = $this->seedAuditedArInvoice($customerId, '1000000.01', '900000.00', '100000.01');
        $result = $this->einvoiceService()->issueInvoice($big, 0);
        $this->assertFalse($result['success']);
        $this->assertSame('超出单张开票限额', $result['error']);
        $this->assertSame('none', $result['issue_status']);
        $saved = Capsule::table('erp_finance_invoice')->where('id', $big)->first();
        $this->assertSame('', $saved->electronic_no);
        $this->assertSame('none', $saved->issue_status);
        // 失败的平台调用同样留痕（可追溯），success=0
        $log = Capsule::table('erp_tax_issue_log')->where('invoice_id', $big)->first();
        $this->assertNotNull($log);
        $this->assertSame('issue', $log->action);
        $this->assertSame(0, (int) $log->success);
        $this->assertSame('超出单张开票限额', $log->error);
    }

    public function testVoidFlow(): void
    {
        $customerId = $this->seedCustomer('红冲');
        $svc = $this->einvoiceService();
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $issue = $svc->issueInvoice($invoiceId, 0);
        $this->assertTrue($issue['success']);

        // 红冲原因必填（先于状态判定，issue_status 原样回传）
        $noReason = $svc->voidInvoice($invoiceId, '  ', 0);
        $this->assertFalse($noReason['success']);
        $this->assertSame('红冲原因必填', $noReason['error']);
        $this->assertSame('issued', $noReason['issue_status']);

        // 正常红冲：voided + electronic_no 保留；成功日志 +1
        $void = $svc->voidInvoice($invoiceId, '测试红冲', 9001);
        $this->assertTrue($void['success'], $void['error']);
        $this->assertSame('voided', $void['issue_status']);
        $this->assertSame($issue['bill_no'], $void['bill_no']);
        $saved = Capsule::table('erp_finance_invoice')->where('id', $invoiceId)->first();
        $this->assertSame('voided', $saved->issue_status);
        $this->assertSame($issue['bill_no'], $saved->electronic_no);
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 2);
        $voidLog = Capsule::table('erp_tax_issue_log')->where('invoice_id', $invoiceId)->where('action', 'void')->first();
        $this->assertNotNull($voidLog);
        $this->assertSame(1, (int) $voidLog->success);

        // 红冲终态：不能再开、不能再冲
        $reIssue = $svc->issueInvoice($invoiceId, 0);
        $this->assertFalse($reIssue['success']);
        $this->assertSame('该发票已红冲，不能再次开票', $reIssue['error']);
        $this->assertSame('voided', $reIssue['issue_status']);
        $reVoid = $svc->voidInvoice($invoiceId, '再冲一次', 0);
        $this->assertFalse($reVoid['success']);
        $this->assertSame('发票已红冲，不能重复红冲', $reVoid['error']);
        $this->assertSame($issue['bill_no'], $reVoid['bill_no']);
        // 终态拒绝不产生平台调用
        $this->assertRowCount('erp_tax_issue_log', ['invoice_id' => $invoiceId], 2);
    }

    public function testIssueLogsOrder(): void
    {
        $customerId = $this->seedCustomer('日志');
        $svc = $this->einvoiceService();
        $invoiceId = $this->seedAuditedArInvoice($customerId, '1130.00', '1000.00', '130.00');
        $svc->issueInvoice($invoiceId, 0);
        $svc->voidInvoice($invoiceId, '红冲归档', 0);

        $logs = $svc->issueLogs($invoiceId);
        $this->assertCount(2, $logs);
        // 新→旧：void 在 issue 之前
        $this->assertSame('void', $logs[0]['action']);
        $this->assertSame('issue', $logs[1]['action']);
        foreach ($logs as $log) {
            $this->assertSame('mock', $log['platform']);
            $this->assertSame(1, (int) $log['success']);
            $this->assertArrayHasKey('request', $log);
            $this->assertArrayHasKey('response', $log);
        }
        // 无日志发票 → 空数组
        $quiet = $this->seedAuditedArInvoice($customerId, '565.00', '500.00', '65.00');
        $this->assertSame([], $svc->issueLogs($quiet));
    }

    public function testMockAdapterRules(): void
    {
        $adapter = new MockEInvoiceAdapter();
        $this->assertSame('mock', $adapter->platform());

        // 购买方税号 9 开头 → 拒绝（service 侧经 buildPayload 的 tax_no seam 未通前由直调覆盖）
        $badTax = $adapter->issue(['id' => 1, 'buyer_tax_no' => '9133', 'amount' => '100.00']);
        $this->assertFalse($badTax['success']);
        $this->assertSame('税号校验未通过', $badTax['error']);

        // 金额非法/超限（1e3 进 bcmath 前即被十进制正则拦下）
        $this->assertSame('金额非法', $adapter->issue(['id' => 2, 'buyer_tax_no' => '', 'amount' => '1e3'])['error']);
        $this->assertSame('超出单张开票限额', $adapter->issue(['id' => 3, 'buyer_tax_no' => '', 'amount' => '1000000.01'])['error']);

        // 确定性：同 id 同金额 → 恒同 bill_no（无状态幂等来源）；格式 MOCK+Ymd+6位序
        $payload = ['id' => 500123, 'buyer_tax_no' => '5001', 'amount' => '1000000.00'];
        $first = $adapter->issue($payload);
        $second = $adapter->issue($payload);
        $this->assertTrue($first['success']);
        $this->assertMatchesRegularExpression('/^MOCK\d{8}\d{6}$/', $first['bill_no']);
        $this->assertSame($first['bill_no'], $second['bill_no']);
        // 序列段 = 发票 id % 1000000 补 6 位
        $this->assertSame('500123', substr($first['bill_no'], -6));

        // void 恒成功（平台红冲无前置拒绝）
        $void = $adapter->void('MOCK20260905000123', '测试');
        $this->assertSame(['success' => true], $void);
    }
}
