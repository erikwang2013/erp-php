<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use app\controller\purchase\SupplierAssessmentController;
use app\model\PurchaseRfq;
use app\service\purchase\RfqService;
use PHPUnit\Framework\TestCase;

/**
 * P0 寻源采购核心逻辑测试（无 DB）：
 *
 * - 纯函数运行时测试：比价取最低、行金额/总额 bcmath 精确性、中标资格判定、评分等级推导
 * - 落库路径源码契约：award() 事务内 lockForUpdate 重读 + canAward 二次校验（防并发重复中标）、
 *   转单按报价行生成 PurchaseOrder 草稿（status=0）且行金额一律走 lineAmount
 *
 * 金额纪律：全部 bcmath，断言 3×0.10=0.30 精确成立。
 */
class SourcingTest extends TestCase
{
    public function testPickLowestSelectsMinAmountByBccomp(): void
    {
        $svc = new RfqService();
        $quotes = [
            ['id' => 1, 'amount' => '120.50'],
            ['id' => 2, 'amount' => '99.99'],
            ['id' => 3, 'amount' => '100.01'],
        ];
        $this->assertSame(2, $svc->pickLowest($quotes));

        // 大额串比较（float 会丢精度的尺度）
        $big = [
            ['id' => 1, 'amount' => '9007199254740993.25'],
            ['id' => 2, 'amount' => '9007199254740993.24'],
        ];
        $this->assertSame(2, $svc->pickLowest($big));
    }

    public function testPickLowestTieKeepsFirstAndEmptyReturnsNull(): void
    {
        $svc = new RfqService();
        $quotes = [
            ['id' => 7, 'amount' => '88.00'],
            ['id' => 9, 'amount' => '88.00'],
        ];
        $this->assertSame(7, $svc->pickLowest($quotes));
        $this->assertNull($svc->pickLowest([]));
    }

    public function testLineAmountAndSumAreBcmathExact(): void
    {
        $svc = new RfqService();
        // 任务验收点：3×0.10=0.30 必须精确
        $this->assertSame('0.30', $svc->lineAmount('0.10', '3'));
        // 三行各 0.10×1 → 逐行舍入后求和仍为 0.30
        $amounts = [
            $svc->lineAmount('0.10', '1'),
            $svc->lineAmount('0.10', '1'),
            $svc->lineAmount('0.10', '1'),
        ];
        $this->assertSame('0.30', $svc->sumAmounts($amounts));
        // 小数与多行混算
        $this->assertSame('6.07', $svc->lineAmount('1.735', '3.5'));
        $this->assertSame('0.00', $svc->sumAmounts([]));
        $this->assertSame('1234.56', $svc->sumAmounts(['1234.56']));
        // float 尾噪输入经 bc_norm 展开，不得出现 0.30000000000000004
        $this->assertSame('0.30', $svc->sumAmounts(['0.10', '0.10', '0.10']));
    }

    public function testCanAwardOnlyWhenSubmittedAndNotYetAwarded(): void
    {
        $svc = new RfqService();
        // 已发布 + 无中标 → 可中标
        $this->assertTrue($svc->canAward(PurchaseRfq::STATUS_SUBMITTED, 0));
        // 草稿/已中标/已关闭/已取消 一律不可
        $this->assertFalse($svc->canAward(PurchaseRfq::STATUS_DRAFT, 0));
        $this->assertFalse($svc->canAward(PurchaseRfq::STATUS_WON, 0));
        $this->assertFalse($svc->canAward(PurchaseRfq::STATUS_CLOSED, 0));
        $this->assertFalse($svc->canAward(PurchaseRfq::STATUS_CANCELLED, 0));
        // 已有中标报价 → 防重复中标
        $this->assertFalse($svc->canAward(PurchaseRfq::STATUS_SUBMITTED, 42));
    }

    public function testAwardGuardAndOrderDraftWiringInSource(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/service/purchase/RfqService.php');

        // 事务内锁行重读 + canAward 二次校验（并发重复中标防护的双保险）
        $this->assertStringContainsString('lockForUpdate()->find($rfqId)', $source);
        $this->assertStringContainsString('$this->canAward((int) $rfq->status', $source);
        $this->assertStringContainsString('仅已发布且未中标的询价单可执行中标', $source);
        // 中标落定：报价置 awarded、询价单置 WON + awarded_quote_id
        $this->assertStringContainsString('PurchaseRfq::STATUS_WON', $source);
        $this->assertStringContainsString('$quote->awarded = 1', $source);
        // 转单行数 = 中标报价明细行数：逐行 quantity 取询价单需求、金额走 lineAmount
        $this->assertStringContainsString('$this->lineAmount((string) $qi->unit_price, $quantity)', $source);
        $this->assertStringContainsString('foreach ($quoteItems as $qi)', $source);
        // 采购订单草稿：status=0 待审核（绝不自动审核）、总金额 = Σ行金额
        $this->assertStringContainsString('$order->status = 0', $source);
        $this->assertStringContainsString('$order->total_amount = $draft[\'total_amount\']', $source);
        $this->assertStringContainsString('$draft[\'total_amount\']', $source);
    }

    public function testBuildOrderDraftLineShapeInSource(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/service/purchase/RfqService.php');
        $this->assertStringContainsString('$orderItem->received_quantity = \'0.00\'', $source);
        $this->assertStringContainsString('$orderItem->quantity = $oi[\'quantity\']', $source);
        $this->assertStringContainsString("'remark' => '由询比价单 ' . \$rfqNo . ' 中标生成'", $source);
    }

    public function testSupplierGradeThresholds(): void
    {
        $this->assertSame('A', SupplierAssessmentController::gradeFor('100'));
        $this->assertSame('A', SupplierAssessmentController::gradeFor('90'));
        $this->assertSame('B', SupplierAssessmentController::gradeFor('89.99'));
        $this->assertSame('B', SupplierAssessmentController::gradeFor('70'));
        $this->assertSame('C', SupplierAssessmentController::gradeFor('69.99'));
        $this->assertSame('C', SupplierAssessmentController::gradeFor('0'));
        $this->assertSame('A', SupplierAssessmentController::gradeFor('95.5'));
    }

    public function testQuoteWriteGuardsInSource(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/controller/purchase/RfqQuoteController.php');

        // 全量覆盖校验：报价须覆盖询价单全部明细行（store 与 update 双路径均调用）
        $this->assertStringContainsString('private function assertFullCoverage', $source);
        $this->assertStringContainsString('不接受部分报价', $source);
        $this->assertSame(2, substr_count($source, '$this->assertFullCoverage('));
        // 单价 ≤2 位小数：saveQuoteItems 为 store/update 共用写入口，DECIMAL(12,2) 落库口径
        $this->assertStringContainsString('最多 2 位小数', $source);
    }
}
