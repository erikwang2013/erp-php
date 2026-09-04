<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\tax;

/**
 * 数电票平台适配器接口 — P2-2 F5
 *
 * 真实税局/开票服务商的接入点：实现本接口（新类 + 配置切换注入 EInvoiceService）
 * 即为接入真实开票通道，服务层不感知差异。
 *
 * 适配器必须无状态：并发安全（行锁防重复开票）与幂等（已开票重复调用返回既有
 * bill_no）由 EInvoiceService 层保证，适配器不做任何自身记忆。
 *
 * 金额口径：$invoice['amount'] 为价税合计十进制字符串；适配器以 bccomp 比较，
 * 禁止 float 转换。
 */
interface EInvoiceAdapter
{
    /** 平台标识（写 erp_tax_issue_log.platform，供多适配器切换后追溯/审计） */
    public function platform(): string;

    /**
     * 开票。
     *
     * @param array $invoice 发票数据快照（含 id、invoice_no、buyer_name、
     *                       buyer_tax_no、untaxed_amount、tax_amount、amount 等）
     * @return array{success: bool, bill_no?: string, error?: string}
     *               success=true 必须带 bill_no；false 必须带 error
     */
    public function issue(array $invoice): array;

    /**
     * 红冲（数电票作废需平台侧发起，回执为准）。
     *
     * @return array{success: bool, error?: string}
     */
    public function void(string $billNo, string $reason): array;
}
