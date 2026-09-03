<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceInvoice;
use app\model\FinanceInvoiceItem;
use app\model\FinanceInvoiceMatchLog;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 发票服务 — P0 三单匹配核心（全量 bcmath，禁 float 算术）
 *
 * 边界：发票是税务票据追踪单据，不新增 ARAP 分录、不联动既有收付款/
 * 核销/结算逻辑（避免破坏既有闭环）。金额一律字符串 bc 运算后 DECIMAL 入库。
 *
 * 三单匹配：应付发票 ⇐ 采购收货单(PurchaseReceive)、应收发票 ⇐ 销售发货单(SalesDelivery)
 * 未开票余额 = 来源单金额合计 − 已开票累计(erp_finance_invoice 同 source 且 status != voided)；
 * 发票含税合计 > 余额即超开拦截（bccomp scale 4 比较）；manual 手工单跳过匹配。
 */
class InvoiceService
{
    /** biz_type => [来源头表, 头表外键列, 头表伙伴列, 期望发票 type] */
    private const SOURCE_MAP = [
        'purchase_receive' => ['erp_purchase_receive', 'receive_id', 'supplier_id', 'ap'],
        'sales_delivery' => ['erp_sales_delivery', 'delivery_id', 'customer_id', 'ar'],
    ];

    /**
     * 单行金额计算（纯函数，全部 bc）：
     * amount=数量×单价(2位)、tax_amount=金额×税率(2位 half-up)、line_total=amount+tax
     */
    public function calcLineAmounts(string $quantity, string $price, string $taxRate): array
    {
        $qty = bc_round($quantity, 2);
        $price2 = bc_round($price, 2);
        $rate = bc_round($taxRate, 2);
        $amount = bc_round(bcmul($qty, $price2, 4), 2);
        $tax = bc_round(bcmul($amount, $rate, 4), 2);

        return [
            'quantity' => $qty,
            'price' => $price2,
            'tax_rate' => $rate,
            'amount' => $amount,
            'tax_amount' => $tax,
            'line_total' => bcadd($amount, $tax, 2),
        ];
    }

    /** 行合计 → 头金额：untaxed=Σamount、tax=Σtax、total=Σline_total（与头 amount 一致） */
    public function totalsFromLines(array $lines): array
    {
        $untaxed = $tax = $total = '0';
        foreach ($lines as $line) {
            $untaxed = bcadd($untaxed, $line['amount'], 4);
            $tax = bcadd($tax, $line['tax_amount'], 4);
            $total = bcadd($total, $line['line_total'], 4);
        }

        return [
            'untaxed_amount' => bc_round($untaxed, 2),
            'tax_amount' => bc_round($tax, 2),
            'amount' => bc_round($total, 2),
        ];
    }

    /**
     * 校验并规范化发票行（数量>0、单价/税率 ≥0、税率 ≤1、金额计算）
     *
     * @return array{0: ?array, 1: ?string} 行数组或错误信息
     */
    public function validateLines(array $rows): array
    {
        if (empty($rows)) {
            return [null, '发票明细不能为空'];
        }
        $lines = [];
        foreach ($rows as $i => $row) {
            $no = (int) $i + 1;
            foreach (['quantity' => '数量', 'price' => '单价', 'tax_rate' => '税率'] as $k => $label) {
                if (!isset($row[$k]) || !is_numeric($row[$k]) || $row[$k] === '') {
                    return [null, "第 {$no} 行{$label}非法"];
                }
            }
            $qty = bc_norm($row['quantity']);
            $price = bc_norm($row['price']);
            $rate = bc_norm($row['tax_rate']);
            if (bccomp($qty, '0', 4) !== 1) {
                return [null, "第 {$no} 行数量必须大于 0"];
            }
            if (bccomp($price, '0', 4) === -1) {
                return [null, "第 {$no} 行单价不能为负"];
            }
            if (bccomp($rate, '0', 4) === -1 || bccomp($rate, '1', 4) === 1) {
                return [null, "第 {$no} 行税率须在 0~1 之间(如 0.13)"];
            }
            $lines[] = $this->calcLineAmounts($qty, $price, $rate) + [
                'product_id' => (int) ($row['product_id'] ?? 0) ?: null,
                'source_item_id' => (int) ($row['source_item_id'] ?? 0) ?: null,
            ];
        }

        return [$lines, null];
    }

    /**
     * 来源头校验：单据存在、发票 type 与来源匹配、客户/供应商与来源一致。
     * manual 单返回 null（跳过）。错误返回消息字符串。
     */
    public function validateSourceHeader(string $type, string $bizType, int $sourceId, int $customerId, int $supplierId): ?string
    {
        if ($bizType === 'manual') {
            return $sourceId > 0 ? '手工发票不能关联来源单' : null;
        }
        if (!isset(self::SOURCE_MAP[$bizType])) {
            return "不支持的来源类型: {$bizType}";
        }
        if ($sourceId <= 0) {
            return '来源单缺失';
        }
        [$table, , $partnerCol, $expectType] = self::SOURCE_MAP[$bizType];
        if ($type !== $expectType) {
            return $type === 'ap' ? '应付发票必须关联采购收货单' : '应收发票必须关联销售发货单';
        }
        $header = DB::table($table)->whereNull('deleted_at')->find($sourceId);
        if (!$header) {
            return '来源单不存在';
        }
        $invoicePartner = $expectType === 'ap' ? $supplierId : $customerId;
        if ($invoicePartner > 0 && (int) $header->{$partnerCol} !== $invoicePartner) {
            return $expectType === 'ap' ? '供应商与收货单不一致' : '客户与发货单不一致';
        }

        return null;
    }

    /**
     * 来源金额信息：source_total=来源明细金额合计、invoiced_total=已开票累计(status!=voided)、
     * balance=未开票余额。SQL SUM 返回 DECIMAL 字符串，不经 float。
     */
    public function balanceInfo(string $bizType, int $sourceId, int $excludeInvoiceId = 0): array
    {
        if ($bizType === 'manual' || !isset(self::SOURCE_MAP[$bizType]) || $sourceId <= 0) {
            return ['source_total' => '0', 'invoiced_total' => '0', 'balance' => '0'];
        }
        [$headerTable, $itemFk] = self::SOURCE_MAP[$bizType];
        $itemTable = $headerTable . '_item';
        $sum = DB::table($itemTable)->where($itemFk, $sourceId)->sum('amount');
        $sourceTotal = $sum === null ? '0' : bc_norm($sum);

        $query = DB::table('erp_finance_invoice')
            ->where('biz_type', $bizType)->where('source_id', $sourceId)
            ->where('status', '!=', 'voided')->whereNull('deleted_at');
        if ($excludeInvoiceId > 0) {
            $query->where('id', '!=', $excludeInvoiceId);
        }
        $sum = $query->sum('amount');
        $invoiced = $sum === null ? '0' : bc_norm($sum);
        $balance = bc_round(bcsub($sourceTotal, $invoiced, 4), 2);

        return [
            'source_total' => bc_round($sourceTotal, 2),
            'invoiced_total' => bc_round($invoiced, 2),
            'balance' => $balance,
        ];
    }

    /** 比较发票金额与未开票余额：相等 ok / 小于 under / 大于 over（拦截） */
    public function resultOf(string $invoiceTotal, string $balance): string
    {
        $cmp = bccomp(bc_norm($invoiceTotal), bc_norm($balance), 4);
        if ($cmp === 1) {
            return 'over';
        }

        return $cmp === 0 ? 'ok' : 'under';
    }

    /** 写三单匹配日志（invoice_id=0 表示校验未通过的拟开票尝试） */
    public function logMatch(int $invoiceId, string $bizType, int $sourceId, string $invoicedTotal, string $result, array $detail): void
    {
        $log = new FinanceInvoiceMatchLog();
        $log->id = SnowflakeService::generate();
        $log->invoice_id = $invoiceId;
        $log->source_type = $bizType;
        $log->source_id = $sourceId;
        $log->invoiced_total = $invoicedTotal;
        $log->result = $result;
        $log->detail = $detail;
        $log->save();
    }

    /**
     * 创建开票申请(draft)。来源关联单超开直接返回错误（写 result=over 日志、不落发票）。
     *
     * @return array{0: ?FinanceInvoice, 1: ?string}
     */
    public function storeDraft(array $data): array
    {
        $type = (string) $data['type'];
        $bizType = (string) $data['biz_type'];
        $sourceId = (int) $data['source_id'];
        $customerId = (int) $data['customer_id'];
        $supplierId = (int) $data['supplier_id'];

        if ($type === 'ap' && $supplierId <= 0) {
            return [null, '应付发票必须指定供应商'];
        }
        if ($type === 'ar' && $customerId <= 0) {
            return [null, '应收发票必须指定客户'];
        }
        [$lines, $err] = $this->validateLines($data['items'] ?? []);
        if ($err !== null) {
            return [null, $err];
        }
        if (FinanceInvoice::where('invoice_no', $data['invoice_no'])->exists()) {
            return [null, '发票号已存在'];
        }
        $err = $this->validateSourceHeader($type, $bizType, $sourceId, $customerId, $supplierId);
        if ($err !== null) {
            return [null, $err];
        }

        $totals = $this->totalsFromLines($lines);
        $invoiceId = 0;
        // 来源关联单校验未开票余额；manual 跳过
        $result = '';
        if ($bizType !== 'manual') {
            $info = $this->balanceInfo($bizType, $sourceId);
            $result = $this->resultOf($totals['amount'], $info['balance']);
            if ($result === 'over') {
                $this->logMatch(0, $bizType, $sourceId, $info['invoiced_total'], $result, [
                    'source_total' => $info['source_total'],
                    'invoiced_total' => $info['invoiced_total'],
                    'balance' => $info['balance'],
                    'match_total' => $totals['amount'],
                    'reason' => '开票申请超出未开票余额',
                ]);

                return [null, "发票金额 {$totals['amount']} 超出未开票余额 {$info['balance']}(来源单总额 {$info['source_total']}，已开票 {$info['invoiced_total']})"];
            }
        }

        try {
            $invoice = DB::transaction(function () use ($data, $totals, $lines, $type, $bizType, $sourceId, $customerId, $supplierId) {
                $invoice = new FinanceInvoice();
                $invoice->id = SnowflakeService::generate();
                $invoice->invoice_no = $data['invoice_no'];
                $invoice->type = $type;
                $invoice->customer_id = $customerId;
                $invoice->supplier_id = $supplierId;
                $invoice->biz_type = $bizType;
                $invoice->source_id = $bizType === 'manual' ? 0 : $sourceId;
                $invoice->invoice_date = $data['invoice_date'] ?: null;
                $invoice->untaxed_amount = $totals['untaxed_amount'];
                $invoice->tax_amount = $totals['tax_amount'];
                $invoice->amount = $totals['amount'];
                $invoice->currency = $data['currency'] ?: 'CNY';
                $invoice->status = 'draft';
                $invoice->remark = $data['remark'] ?? '';
                $invoice->save();
                $this->persistLines($invoice->id, $lines);

                return $invoice;
            });
        } catch (\Throwable $e) {
            return [null, '发票保存失败: ' . $e->getMessage()];
        }

        // 来源关联单：draft 即占余额，记录匹配日志(result 仅可能 ok/under)
        if ($bizType !== 'manual') {
            $info = $this->balanceInfo($bizType, $sourceId, $invoice->id);
            $this->logMatch($invoice->id, $bizType, $sourceId, $info['invoiced_total'], $result, [
                'source_total' => $info['source_total'],
                'invoiced_total' => $info['invoiced_total'],
                'balance' => $info['balance'],
                'match_total' => $invoice->amount,
            ]);
        }

        return [$invoice, null];
    }

    /** 覆盖写入发票行并重算头金额（draft 更新用；items 空则仅刷新既有行合计） */
    public function replaceLines(int $invoiceId, array $lines): void
    {
        FinanceInvoiceItem::where('invoice_id', $invoiceId)->delete();
        $this->persistLines($invoiceId, $lines);
        $this->refreshHeader($invoiceId);
    }

    public function refreshHeader(int $invoiceId): void
    {
        $rows = FinanceInvoiceItem::where('invoice_id', $invoiceId)->get(['amount', 'tax_amount', 'line_total']);
        $untaxed = $tax = $total = '0';
        foreach ($rows as $row) {
            $untaxed = bcadd($untaxed, $row->getAttribute('amount'), 4);
            $tax = bcadd($tax, $row->getAttribute('tax_amount'), 4);
            $total = bcadd($total, $row->getAttribute('line_total'), 4);
        }
        FinanceInvoice::where('id', $invoiceId)->update([
            'untaxed_amount' => bc_round($untaxed, 2),
            'tax_amount' => bc_round($tax, 2),
            'amount' => bc_round($total, 2),
        ]);
    }

    private function persistLines(int $invoiceId, array $lines): void
    {
        foreach ($lines as $line) {
            $item = new FinanceInvoiceItem();
            $item->id = SnowflakeService::generate();
            $item->invoice_id = $invoiceId;
            $item->product_id = $line['product_id'];
            $item->source_item_id = $line['source_item_id'];
            $item->quantity = $line['quantity'];
            $item->price = $line['price'];
            $item->amount = $line['amount'];
            $item->tax_rate = $line['tax_rate'];
            $item->tax_amount = $line['tax_amount'];
            $item->line_total = $line['line_total'];
            $item->save();
        }
    }

    /** 提交审核：draft→submitted，来源单再次校验余额。返回错误或 null */
    public function submit(int $invoiceId): ?string
    {
        $invoice = FinanceInvoice::find($invoiceId);
        if (!$invoice) {
            return '发票不存在';
        }
        if ($invoice->status !== 'draft') {
            return '仅开票申请(draft)状态可提交';
        }
        if (($err = $this->checkLinkedBalance($invoice)) !== null) {
            return $err;
        }
        $invoice->status = 'submitted';
        $invoice->save();

        return null;
    }

    /** 审核入账：submitted→audited，写匹配日志。返回错误或 null */
    public function audit(int $invoiceId, int $adminId): ?string
    {
        $invoice = FinanceInvoice::find($invoiceId);
        if (!$invoice) {
            return '发票不存在';
        }
        if ($invoice->status !== 'submitted') {
            return '仅已提交(submitted)状态可审核';
        }
        if (($err = $this->checkLinkedBalance($invoice)) !== null) {
            return $err;
        }
        if ($invoice->biz_type !== 'manual') {
            $info = $this->balanceInfo($invoice->biz_type, (int) $invoice->source_id, $invoiceId);
            $this->logMatch($invoiceId, $invoice->biz_type, (int) $invoice->source_id, $info['invoiced_total'], $this->resultOf($invoice->amount, $info['balance']), [
                'source_total' => $info['source_total'],
                'invoiced_total' => $info['invoiced_total'],
                'balance' => $info['balance'],
                'match_total' => $invoice->amount,
            ]);
        }
        $invoice->status = 'audited';
        $invoice->audited_by = $adminId;
        $invoice->audited_at = date('Y-m-d H:i:s');
        $invoice->save();

        return null;
    }

    /** 作废：任何非 voided 状态可作废（余额随之回补）。返回错误或 null */
    public function void(int $invoiceId, string $reason): ?string
    {
        $invoice = FinanceInvoice::find($invoiceId);
        if (!$invoice) {
            return '发票不存在';
        }
        if ($invoice->status === 'voided') {
            return '发票已作废';
        }
        if ($reason === '') {
            return '作废原因必填';
        }
        $invoice->status = 'voided';
        $invoice->void_reason = $reason;
        $invoice->save();

        return null;
    }

    /** 重算链接来源余额校验（超开即拦；manual 跳过） */
    private function checkLinkedBalance(FinanceInvoice $invoice): ?string
    {
        if ($invoice->biz_type === 'manual') {
            return null;
        }
        $sourceId = (int) $invoice->source_id;
        $info = $this->balanceInfo($invoice->biz_type, $sourceId, (int) $invoice->id);
        $result = $this->resultOf($invoice->amount, $info['balance']);
        if ($result === 'over') {
            return "发票金额 {$invoice->amount} 超出未开票余额 {$info['balance']}";
        }

        return null;
    }
}
