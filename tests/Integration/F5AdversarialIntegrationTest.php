<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * P2-2 F5 进项发票池对抗性验证（tester 轮）。攻击面：畸形金额零落库、
 * 3 位小数分存漂移（KNOWN-DRIFT 锚点）、组合键唯一性、期间与批量退化输入。
 * 断言一律 assertSame；种子标记自清理。
 */
#[Group('integration')]
class F5AdversarialIntegrationTest extends F5TaxScaffold
{
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
        $this->assertSame(0, Capsule::table('tax_input_invoice')->whereIn('invoice_no', $nos)->count());
    }

    #[TestDox('回归 KNOWN-DRIFT(已修复)：3 位小数入参整单拒绝 —— 不再分存半进位致勾稽漂移')]
    public function testThirdDecimalHalfUpRoundingDrift(): void
    {
        // 原缺陷：scale-4 勾稽通过（0.015+0.015=0.03），各栏独立 bc_round 后
        // 0.02+0.02=0.04 ≠ amount 0.03，存储行自相矛盾。修复：金额输入收严为
        // ≤2 位小数（与 DECIMAL(14,2) 列契约一致），3 位小数入参整体拒绝。
        foreach ([
            ['amount' => '0.03', 'untaxed_amount' => '0.015', 'tax_amount' => '0.015'],
            ['amount' => '0.001', 'untaxed_amount' => '0.001', 'tax_amount' => '0.000'],
        ] as $data) {
            [$row, $err] = $this->poolService()->registerOne($this->poolData($data));
            $this->assertNull($row, '3 位小数入参应整单拒绝');
            $this->assertNotNull($err, '应给出明确错误');
            // 被拒栏目标签随校验顺序不同（价税合计/不含税金额/税额），统一断言含「非法」
            $this->assertStringContainsString('非法', (string) $err);
        }

        // 2 位小数正常路径不受影响：勾稽一致且列间自洽
        $fine = $this->registerPool(['amount' => '113.01', 'untaxed_amount' => '100.00', 'tax_amount' => '13.01']);
        $this->assertSame('113.01', $fine->amount);
        $sum = bcadd((string) $fine->untaxed_amount, (string) $fine->tax_amount, 2);
        $this->assertSame('113.01', $sum, '2 位小数落库后勾稽保持');
    }

    #[TestDox('唯一性：uk(code,no) 组合键 —— 同号不同代码允许共存，同码同号拒绝')]
    public function testCompositeKeySameNoDifferentCodeAllowed(): void
    {
        $no = self::MARKER . 'S' . $this->nextId();
        $a = $this->registerPool(['invoice_code' => 'CODE-X1', 'invoice_no' => $no]);
        $b = $this->registerPool(['invoice_code' => 'CODE-X2', 'invoice_no' => $no]);
        $this->assertNotNull($a->id);
        $this->assertNotNull($b->id);
        $this->assertSame(2, Capsule::table('tax_input_invoice')->where('invoice_no', $no)->count(), '不同发票代码下同号允许登记');

        [$dup, $err] = $this->poolService()->registerOne($this->poolData(['invoice_code' => 'CODE-X1', 'invoice_no' => $no]));
        $this->assertNull($dup);
        $this->assertSame('该发票已登记(相同发票代码/号码)', $err);
        $this->assertSame(2, Capsule::table('tax_input_invoice')->where('invoice_no', $no)->count());
    }

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
}
