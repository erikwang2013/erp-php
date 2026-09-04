<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\tax;

/**
 * 进项发票验真适配器(模拟) — P2-2 F5
 *
 * 确定性规则（供测试与联调稳定断言）：
 *   销售方税号以 '9' 开头 → 验真失败；其余一律验真通过。
 *
 * 真实税局接口接入点：以同签名（输入税号、返回 bool）实现真实验真适配器并替换
 * TaxInvoicePoolService 构造注入即可，池服务不感知差异。适配器无状态，并发安全
 * 由服务层保证。
 */
class MockTaxVerifier
{
    /** 验真：税号以 '9' 开头判失败（确定性 mock 规则），其余通过 */
    public function verify(string $sellerTaxNo): bool
    {
        return !str_starts_with(trim($sellerTaxNo), '9');
    }
}
