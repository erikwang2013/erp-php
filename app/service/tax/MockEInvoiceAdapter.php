<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\tax;

/**
 * 数电票开票适配器(模拟) — P2-2 F5
 *
 * 确定性规则（供测试与联调稳定断言，永不发真实请求）：
 *   购买方税号以 '9' 开头      → issue 失败，error='税号校验未通过'
 *   价税合计 > 1000000.00      → issue 失败，error='超出单张开票限额'
 *   其余                      → 成功，bill_no='MOCK' . Ymd . 发票id%1000000 补 6 位
 *                              （同一发票 id 永远得到同一 bill_no —— 无状态幂等来源）
 *   void 恒成功（平台红冲无前置拒绝）
 *
 * 真实开票通道接入点：以同接口（EInvoiceAdapter）实现真适配器，经配置注入
 * EInvoiceService 替换本类。适配器无状态，并发/幂等由服务层保证。
 */
class MockEInvoiceAdapter implements EInvoiceAdapter
{
    public function platform(): string
    {
        return 'mock';
    }

    public function issue(array $invoice): array
    {
        $buyerTaxNo = trim((string) ($invoice['buyer_tax_no'] ?? ''));
        if ($buyerTaxNo !== '' && str_starts_with($buyerTaxNo, '9')) {
            return ['success' => false, 'error' => '税号校验未通过'];
        }

        $amount = (string) ($invoice['amount'] ?? '0');
        if (!preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            return ['success' => false, 'error' => '金额非法'];
        }
        if (bccomp(bc_norm($amount), '1000000.00', 4) === 1) {
            return ['success' => false, 'error' => '超出单张开票限额'];
        }

        $seq = str_pad((string) ((int) ($invoice['id'] ?? 0) % 1000000), 6, '0', STR_PAD_LEFT);

        return ['success' => true, 'bill_no' => 'MOCK' . date('Ymd') . $seq];
    }

    public function void(string $billNo, string $reason): array
    {
        return ['success' => true];
    }
}
