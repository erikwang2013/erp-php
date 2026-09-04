<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\tax;

use app\common\SnowflakeService;
use app\model\FinanceInvoice;
use app\model\TaxIssueLog;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 数电票开票/红冲服务 — P2-2 F5（全量 bcmath；金额一律十进制字符串）
 *
 * ⚠️ 本域最重要约束 —— 幂等：
 *   已开具(issue_status=issued)的发票再次调用 issueInvoice()，绝不重复调平台，
 *   直接返回既有 electronic_no（幂等成功，idempotent=true）。真实税务场景中同一
 *   发票被两个渠道各开一次会造成不可撤销的重复开票，因此本服务用「行锁 + 状态
 *   判重」把该约束钉死：锁内先读再判，并发请求只有一个能走到平台。
 *
 * 状态机（issue_status，与 erp_finance_invoice.status 作废流正交）：
 *   none(未开具) → issued(已开具) → voided(已红冲)
 *   - 仅 type=ar 且 status=audited 且未红冲可开票；
 *   - 红冲仅对 issued 生效（void 后 electronic_no 保留供对账）；
 *   - voided 不可再开、不可再冲；红冲请求报文/回执进 erp_tax_issue_log。
 *
 * 平台接入：构造注入 EInvoiceAdapter（默认 MockEInvoiceAdapter），真实开票通道
 * 以同接口实现 + 配置切换注入。适配器无状态，并发与幂等都在本服务层。
 *
 * 审计：guard 拒绝（状态/属性不符）不写日志；只有真实平台调用（成功或失败）才
 * 落 erp_tax_issue_log —— 日志行即「平台调用记录」，未调用不伪造轨迹。
 */
class EInvoiceService
{
    private readonly EInvoiceAdapter $adapter;

    public function __construct(?EInvoiceAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? new MockEInvoiceAdapter();
    }

    /**
     * 开票（行锁 + 幂等，返回结果数组，错误不抛异常）。
     *
     * @return array{success: bool, bill_no: string, error: string,
     *               issue_status: string, idempotent?: bool}
     */
    public function issueInvoice(int $invoiceId, int $operatorId): array
    {
        return DB::transaction(function () use ($invoiceId, $operatorId) {
            /** @var FinanceInvoice|null $invoice */
            $invoice = FinanceInvoice::query()->lockForUpdate()->find($invoiceId);
            if (!$invoice) {
                return ['success' => false, 'bill_no' => '', 'error' => '发票不存在', 'issue_status' => 'none'];
            }
            if ((string) $invoice->type !== 'ar') {
                return ['success' => false, 'bill_no' => '', 'error' => '仅应收(ar)发票可开具数电票', 'issue_status' => 'none'];
            }
            if ((string) $invoice->status !== 'audited') {
                return ['success' => false, 'bill_no' => '', 'error' => '仅 已审核(audited) 状态发票可开具数电票', 'issue_status' => 'none'];
            }
            if ((string) $invoice->issue_status === 'voided') {
                return ['success' => false, 'bill_no' => '', 'error' => '该发票已红冲，不能再次开票', 'issue_status' => 'voided'];
            }
            // 幂等：已开具直接返回既有号码，不调平台、不写日志
            if ((string) $invoice->issue_status === 'issued') {
                return [
                    'success' => true,
                    'bill_no' => (string) $invoice->electronic_no,
                    'error' => '',
                    'issue_status' => 'issued',
                    'idempotent' => true,
                ];
            }

            $payload = $this->buildPayload($invoice);
            try {
                $result = $this->adapter->issue($payload);
            } catch (\Throwable $e) {
                $error = '平台调用异常: ' . mb_substr($e->getMessage(), 0, 200);
                $this->writeLog($invoiceId, 'issue', '', $payload, [], 0, $error, $operatorId);

                return ['success' => false, 'bill_no' => '', 'error' => $error, 'issue_status' => 'none'];
            }

            $ok = (bool) ($result['success'] ?? false);
            $billNo = trim((string) ($result['bill_no'] ?? ''));
            $error = $ok && $billNo === '' ? '平台返回异常: 缺少数电票号码' : (string) ($result['error'] ?? '开票失败');
            $this->writeLog($invoiceId, 'issue', $billNo, $payload, $result, $ok ? 1 : 0, $error, $operatorId);
            if (!$ok || $billNo === '') {
                return ['success' => false, 'bill_no' => '', 'error' => $error, 'issue_status' => 'none'];
            }

            $invoice->electronic_no = $billNo;
            $invoice->issue_status = 'issued';
            $invoice->save();

            return ['success' => true, 'bill_no' => $billNo, 'error' => '', 'issue_status' => 'issued'];
        });
    }

    /**
     * 红冲：仅 issued 可冲。成功 → issue_status=voided（electronic_no 保留）、
     * 平台调用落日志；失败返回错误不抛异常。返回结果数组。
     *
     * @return array{success: bool, bill_no: string, error: string, issue_status: string}
     */
    public function voidInvoice(int $invoiceId, string $reason, int $operatorId): array
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($invoiceId, $reason, $operatorId) {
            /** @var FinanceInvoice|null $invoice */
            $invoice = FinanceInvoice::query()->lockForUpdate()->find($invoiceId);
            if (!$invoice) {
                return ['success' => false, 'bill_no' => '', 'error' => '发票不存在', 'issue_status' => 'none'];
            }
            if ($reason === '') {
                return ['success' => false, 'bill_no' => '', 'error' => '红冲原因必填', 'issue_status' => (string) $invoice->issue_status];
            }
            if ((string) $invoice->issue_status === 'none') {
                return ['success' => false, 'bill_no' => '', 'error' => '该发票未开具数电票，不能红冲', 'issue_status' => 'none'];
            }
            if ((string) $invoice->issue_status === 'voided') {
                return ['success' => false, 'bill_no' => (string) $invoice->electronic_no, 'error' => '发票已红冲，不能重复红冲', 'issue_status' => 'voided'];
            }

            $billNo = (string) $invoice->electronic_no;
            $payload = ['bill_no' => $billNo, 'reason' => $reason];
            try {
                $result = $this->adapter->void($billNo, $reason);
            } catch (\Throwable $e) {
                $error = '平台调用异常: ' . mb_substr($e->getMessage(), 0, 200);
                $this->writeLog($invoiceId, 'void', $billNo, $payload, [], 0, $error, $operatorId);

                return ['success' => false, 'bill_no' => $billNo, 'error' => $error, 'issue_status' => 'issued'];
            }

            $ok = (bool) ($result['success'] ?? false);
            $error = $ok ? '' : (string) ($result['error'] ?? '红冲失败');
            $this->writeLog($invoiceId, 'void', $billNo, $payload, $result, $ok ? 1 : 0, $error, $operatorId);
            if (!$ok) {
                return ['success' => false, 'bill_no' => $billNo, 'error' => $error, 'issue_status' => 'issued'];
            }

            $invoice->issue_status = 'voided';
            $invoice->save();

            return ['success' => true, 'bill_no' => $billNo, 'error' => '', 'issue_status' => 'voided'];
        });
    }

    /**
     * 某发票的开票/红冲日志（新→旧）。
     *
     * @return array<int, array>
     */
    public function issueLogs(int $invoiceId): array
    {
        return TaxIssueLog::where('invoice_id', $invoiceId)
            ->orderBy('id', 'desc')->get()->toArray();
    }

    /**
     * 组装平台开票报文。erp_customer 尚无税号列（P2-2 seam：真实开票需要客户税号
     * 扩展，届时补 erp_customer.tax_no 后此处 property_exists 分支自然生效），
     * 购买方税号暂取 ''，仅由 Mock 规则覆盖 9 开头失败分支。
     *
     * @return array<string, mixed>
     */
    private function buildPayload(FinanceInvoice $invoice): array
    {
        $customer = DB::table('erp_customer')->find((int) $invoice->customer_id);
        $buyerName = $customer ? (string) ($customer->name ?? '') : '';
        $buyerTaxNo = $customer && property_exists($customer, 'tax_no') ? (string) $customer->tax_no : '';

        return [
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'issue_date' => (string) $invoice->invoice_date,
            'buyer_name' => $buyerName,
            'buyer_tax_no' => $buyerTaxNo,
            'untaxed_amount' => (string) $invoice->untaxed_amount,
            'tax_amount' => (string) $invoice->tax_amount,
            'amount' => (string) $invoice->amount,
            'remark' => (string) $invoice->remark,
        ];
    }

    /** 平台调用日志（只追加）：request/response 快照原文，error ≤500 截断 */
    private function writeLog(
        int $invoiceId,
        string $action,
        string $billNo,
        array $request,
        array $response,
        int $success,
        string $error,
        int $operatorId
    ): void {
        $log = new TaxIssueLog();
        $log->id = SnowflakeService::generate();
        $log->invoice_id = $invoiceId;
        $log->action = $action;
        $log->bill_no = $billNo;
        $log->platform = $this->adapter->platform();
        $log->request = $request;
        $log->response = $response;
        $log->success = $success;
        $log->error = mb_substr($error, 0, 500);
        $log->operator_id = $operatorId;
        $log->save();
    }
}
