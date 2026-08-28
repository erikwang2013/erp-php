<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\finance\ConsolidationService;
use PHPUnit\Framework\TestCase;

/**
 * 合并报表服务：多币种合并尚未实现（缺汇率折算与子公司间抵销规则），
 * 显式抛业务异常拒绝，绝不返回占位数据冒充成功。
 */
class ConsolidationServiceTest extends TestCase
{
    public function testConsolidateThrowsNotImplemented(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ConsolidationService())->consolidate([['currency' => 'USD']]);
    }

    public function testErrorMessageMentionsUnimplemented(): void
    {
        $this->expectExceptionMessage('未实现');
        (new ConsolidationService())->consolidate([]);
    }
}
