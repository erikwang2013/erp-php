<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * P2-2 F5 进项发票池集成测试：登记唯一性/校验矩阵/验真与抵扣状态机/批量/统计/筛选。
 * 消息文本为服务层稳定契约（TaxInvoicePoolService），逐字断言。
 */
#[Group('integration')]
class F5TaxInvoicePoolTest extends F5TaxScaffold
{
    public function testRegisterAndDuplicateGuard(): void
    {
        $row = $this->registerPool();
        $this->assertNotNull($row->id);
        // 字符串金额直存断言（bcmath 规约：无 float 中间值）
        $this->assertSame('1130.00', $row->amount);
        $this->assertSame('1000.00', $row->untaxed_amount);
        $this->assertSame('130.00', $row->tax_amount);
        $this->assertSame(0, (int) $row->verify_status);
        $this->assertSame(0, (int) $row->deduct_status);
        $this->assertSame('manual', $row->source);
        $saved = Capsule::table('erp_tax_input_invoice')->where('id', $row->id)->first();
        $this->assertNotNull($saved);
        $this->assertNull($saved->verify_at);

        // 同代码+号码 → 唯一性拒绝
        [$dup, $err] = $this->poolService()->registerOne($this->poolData(['invoice_no' => $row->invoice_no]));
        $this->assertNull($dup);
        $this->assertSame('该发票已登记(相同发票代码/号码)', $err);

        // 数电票无代码(code='')：不同号码互不冲突；同号码仍被唯一键拦截
        $blankA = $this->registerPool(['invoice_code' => '', 'invoice_no' => 'E' . $this->nextId()]);
        $blankB = $this->registerPool(['invoice_code' => '', 'invoice_no' => 'E' . $this->nextId()]);
        $this->assertNotNull($blankA->id);
        $this->assertNotNull($blankB->id);
        [$dupBlank, $errBlank] = $this->poolService()->registerOne(['invoice_code' => '', 'invoice_no' => $blankA->invoice_no] + $this->poolData());
        $this->assertNull($dupBlank);
        $this->assertSame('该发票已登记(相同发票代码/号码)', $errBlank);
    }

    public function testRegisterValidationMatrix(): void
    {
        $cases = [
            // [[覆盖字段], 期望错误]
            [['invoice_no' => ''], '发票号码必填'],
            [['seller_name' => ''], '销售方名称必填'],
            [['seller_tax_no' => ''], '销售方税号必填'],
            [['issue_date' => ''], '开票日期必填'],
            [['issue_date' => '2026-02-30'], '开票日期非法'],
            [['issue_date' => '2026/01/01'], '开票日期非法'],
            [['amount' => ''], '价税合计必填'],
            [['amount' => '1e3'], '价税合计非法'],
            [['amount' => '0'], '价税合计必须大于 0'],
            [['untaxed_amount' => '-1.00'], '不含税金额不能为负'],
            [['tax_amount' => ''], '税额必填'],
            [['tax_amount' => 'abc'], '税额非法'],
            [['tax_amount' => '-0.01'], '税额不能为负'],
            [['amount' => '100.00', 'untaxed_amount' => '100.00', 'tax_amount' => '10.00'], '价税合计须等于不含税金额与税额之和'],
            [['invoice_no' => str_repeat('1', 51)], '发票号码长度不能超过 50 个字符'],
            [['source' => 'web'], '来源非法: 仅支持 manual=手工 excel=批量导入'],
        ];
        foreach ($cases as [$override, $expected]) {
            [$row, $err] = $this->poolService()->registerOne($this->poolData($override));
            $this->assertNull($row);
            $this->assertSame($expected, $err, '断言失败: ' . $expected);
        }

        // 3 位小数合法入参：scale 4 勾稽通过，落库 bc_round 半进位到 2 位
        $fine = $this->registerPool(['untaxed_amount' => '100.005', 'tax_amount' => '13.005', 'amount' => '113.01']);
        $this->assertSame('113.01', $fine->amount);
        $this->assertSame('100.01', $fine->untaxed_amount);
        $this->assertSame('13.01', $fine->tax_amount);
    }

    public function testVerifyStateMachine(): void
    {
        // 税号非 9 开头 → 验真通过（Mock 规则），记录 verify_at
        $pass = $this->registerPool(['seller_tax_no' => '81330100TEST1']);
        $this->assertNull($this->poolService()->verify((int) $pass->id));
        $saved = Capsule::table('erp_tax_input_invoice')->where('id', $pass->id)->first();
        $this->assertSame(1, (int) $saved->verify_status);
        $this->assertNotNull($saved->verify_at);
        // 验真幂等：通过后拒绝重复
        $this->assertSame('发票已验真通过，不能重复验真', $this->poolService()->verify((int) $pass->id));

        // 税号 9 开头 → 验真失败
        $fail = $this->registerPool(['seller_tax_no' => '99900100TEST1']);
        $this->assertNull($this->poolService()->verify((int) $fail->id));
        $savedFail = Capsule::table('erp_tax_input_invoice')->where('id', $fail->id)->first();
        $this->assertSame(2, (int) $savedFail->verify_status);
        $this->assertNotNull($savedFail->verify_at);
        $this->assertSame('发票验真未通过，不能重复验真', $this->poolService()->verify((int) $fail->id));

        // 不存在 → 404 语义错误
        $this->assertSame('发票不存在', $this->poolService()->verify($this->nextId()));
    }

    public function testDeductGateAndPeriod(): void
    {
        // 未验真不能勾选
        $row = $this->registerPool();
        $this->assertSame('发票尚未验真，请先验真', $this->poolService()->check((int) $row->id));

        // 未勾选不能抵扣（先给合法期间，隔离期间校验后仍是此门卫）
        $this->assertSame('发票未勾选，不能抵扣', $this->poolService()->deduct((int) $row->id, '2026-08'));

        // 期间非法：格式与月份越界同文案
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $row->id, ''));
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $row->id, '2026-13'));
        $this->assertSame('抵扣期间非法: 须为 YYYY-MM 格式', $this->poolService()->deduct((int) $row->id, '2026-8'));

        // 验真失败者不能勾选
        $failed = $this->registerPool(['seller_tax_no' => '99900100TEST1']);
        $this->assertNull($this->poolService()->verify((int) $failed->id));
        $this->assertSame('发票验真未通过，不能勾选抵扣', $this->poolService()->check((int) $failed->id));

        // 正常链路：验真 → 勾选 → 抵扣（记录期间）
        $this->assertNull($this->poolService()->verify((int) $row->id));
        $this->assertNull($this->poolService()->check((int) $row->id));
        $this->assertSame('发票已勾选待抵扣，不能重复勾选', $this->poolService()->check((int) $row->id));
        $this->assertNull($this->poolService()->deduct((int) $row->id, '2026-08'));
        $saved = Capsule::table('erp_tax_input_invoice')->where('id', $row->id)->first();
        $this->assertSame(2, (int) $saved->deduct_status);
        $this->assertSame('2026-08', $saved->deduct_period);
        $this->assertSame('发票已抵扣，不能重复抵扣', $this->poolService()->deduct((int) $row->id, '2026-08'));
    }

    public function testBatchRegister(): void
    {
        $a = $this->poolData(['invoice_no' => self::MARKER . 'B' . $this->nextId()]);
        $b = $this->poolData(['invoice_no' => self::MARKER . 'B' . $this->nextId()]);
        $c = $this->poolData(['invoice_no' => self::MARKER . 'B' . $this->nextId()]);
        $broken = $this->poolData(['invoice_no' => self::MARKER . 'B' . $this->nextId(), 'seller_name' => '']);
        $rows = [$a, $b, $a, $broken, $c]; // 第3行批内重复、第4行缺销售方
        [$ok, $fail, $errors] = $this->poolService()->registerBatch($rows);
        $this->assertSame(3, $ok);
        $this->assertSame(2, $fail);
        $this->assertCount(2, $errors);
        $this->assertSame('第 3 行: 该发票已登记(相同发票代码/号码)', $errors[0]);
        $this->assertSame('第 4 行: 销售方名称必填', $errors[1]);

        // 批次落库 3 行并登记清理
        $created = Capsule::table('erp_tax_input_invoice')
            ->whereIn('invoice_no', [$a['invoice_no'], $b['invoice_no'], $c['invoice_no']])->get();
        $this->assertSame(3, $created->count());
        foreach ($created as $item) {
            $this->poolIds[] = (int) $item->id;
        }

        // 与库内已有发票跨批次重复 → 服务层预检拒绝
        $dup = $this->poolData(['invoice_no' => $a['invoice_no']]);
        [$okDup, $failDup, $errorsDup] = $this->poolService()->registerBatch([$dup]);
        $this->assertSame(0, $okDup);
        $this->assertSame(1, $failDup);
        $this->assertSame('第 1 行: 该发票已登记(相同发票代码/号码)', $errorsDup[0] ?? '');
    }

    public function testDeductStats(): void
    {
        // 2026-08 两张、2026-09 一张；第四张仅勾选不抵扣（不入统计）
        $one = $this->registerPool(['amount' => '1130.00']);
        $two = $this->registerPool(['amount' => '565.00', 'untaxed_amount' => '500.00', 'tax_amount' => '65.00']);
        $three = $this->registerPool(['amount' => '1130.00']);
        $checkedOnly = $this->registerPool(['amount' => '1130.00']);
        foreach ([$one, $two, $three, $checkedOnly] as $item) {
            $this->assertNull($this->poolService()->verify((int) $item->id));
        }
        foreach ([$one, $two, $three] as $item) {
            $this->assertNull($this->poolService()->check((int) $item->id));
            $this->assertNull($this->poolService()->deduct((int) $item->id, $item === $three ? '2026-09' : '2026-08'));
        }
        $this->assertNull($this->poolService()->check((int) $checkedOnly->id));

        $stats = $this->poolService()->deductStats();
        $this->assertSame(
            [['deduct_period' => '2026-08', 'count' => 2, 'amount' => '1695.00'],
             ['deduct_period' => '2026-09', 'count' => 1, 'amount' => '1130.00']],
            $stats
        );
    }

    public function testListFilters(): void
    {
        $a = $this->registerPool(['seller_name' => '甲公司', 'seller_tax_no' => '813300000001', 'source' => 'manual', 'issue_date' => '2026-07-15']);
        $b = $this->registerPool(['seller_name' => '乙公司', 'seller_tax_no' => '813300000002', 'source' => 'excel', 'issue_date' => '2026-08-10']);
        $c = $this->registerPool(['seller_name' => '甲公司', 'seller_tax_no' => '813300000003', 'source' => 'manual', 'issue_date' => '2026-08-20']);
        $this->assertNull($this->poolService()->verify((int) $a->id));
        $this->assertNull($this->poolService()->check((int) $a->id));
        $this->assertNull($this->poolService()->deduct((int) $a->id, '2026-08'));

        $svc = $this->poolService();
        $base = ['verify_status' => -1, 'deduct_status' => -1];
        $list = fn (array $f): array => $svc->list($f + $base, 1, 20);

        // 关键词（号码精确匹配片段）与销售方模糊
        $keywordResult = $list(['keyword' => (string) $a->invoice_no]);
        $this->assertSame(1, $keywordResult['total']);
        $this->assertSame((string) $a->invoice_no, $keywordResult['list'][0]['invoice_no']);
        $this->assertSame(2, $list(['seller_name' => '甲公司'])['total']);
        $this->assertSame(1, $list(['seller_tax_no' => '813300000002'])['total']);
        // 状态/来源/期间/日期区间筛选
        $this->assertSame(1, $list(['verify_status' => 1])['total']);
        $this->assertSame(2, $list(['deduct_status' => 0])['total']);
        $this->assertSame(1, $list(['source' => 'excel'])['total']);
        $this->assertSame(1, $list(['deduct_period' => '2026-08'])['total']);
        $this->assertSame(3, $list(['issue_date_from' => '2026-07-01', 'issue_date_to' => '2026-12-31'])['total']);
        // 非法日期入参被忽略（不报错，等价无区间）
        $this->assertSame(3, $list(['issue_date_from' => 'not-a-date'])['total']);
        // 分页：limit 1 只回最新一条（id desc）
        $page = $svc->list($base, 1, 1);
        $this->assertSame(3, $page['total']);
        $this->assertCount(1, $page['list']);
        $this->assertSame($c->id, (int) $page['list'][0]['id']);
    }
}
