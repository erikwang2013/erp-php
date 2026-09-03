<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\ConsolidationService;
use PHPUnit\Framework\TestCase;

/**
 * 合并报表服务 consolidate()：入参契约（纯内存校验，不触库）。
 * 金额/报表取数口径由集成测试（F12MultiCompanyConsolidationTest）覆盖。
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
}
