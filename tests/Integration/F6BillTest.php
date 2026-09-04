<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * F6 承兑汇票票据台账集成测试：登记校验/收款单关联/更新规则/双向状态机/到期预警。
 * 金额一律字符串断言（bcmath 规整 2 位小数），状态机错误消息逐条稳定断言。
 */
#[Group('integration')]
class F6BillTest extends F6FundScaffold
{
    /** 默认登记参数：方向1收票 手工来源，60 天后到期（天然不过期） */
    private function baseBill(): array
    {
        return [
            'bill_no' => self::MARKER . 'B' . $this->nextId(),
            'type' => 1,
            'direction' => 1,
            'amount' => '1000.00',
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+60 days')),
            'drawer' => '测试出票人',
            'acceptor' => '测试承兑人',
            'payee' => '测试收款人',
            'source_type' => 'manual',
        ];
    }

    /** 登记成功返回模型并落库：金额规整字符串、初始状态 0 在库 */
    public function testStoreHappyPath(): void
    {
        [$bill, $err] = $this->billService()->store($this->baseBill());
        $this->assertNull($err);
        $this->assertNotNull($bill);
        $this->assertSame('1000.00', (string) $bill->amount, '金额须为 2 位小数字符串');
        $this->assertSame(0, (int) $bill->status);
        $this->assertSame('manual', (string) $bill->source_type);
        $this->assertSame(0, (int) $bill->source_id);
        $this->assertSame(0, (int) $bill->bank_account_id, '收票未指定托收账户时允许登记');
        $this->assertRowCount('erp_finance_bill', ['id' => (int) $bill->id], 1);
        $this->billIds[] = (int) $bill->id;
    }

    /** 登记校验全链：票号/类型/方向/金额(含 bcmath 前哨)/日期/来源/账户 */
    public function testStoreValidationGuards(): void
    {
        $svc = $this->billService();
        $cases = [
            [['bill_no' => ''], '票号必填'],
            [['type' => 3], '票据类型非法: 仅支持 1=银行承兑 2=商业承兑'],
            [['direction' => 3], '票据方向非法: 仅支持 1=收票(应收) 2=开票(应付)'],
            [['amount' => 'abc'], '票面金额非法'],
            [['amount' => '1e3'], '票面金额非法'],       // bcmath ValueError 前哨
            [['amount' => '0'], '票面金额必须大于 0'],
            [['amount' => '-1.00'], '票面金额必须大于 0'],
            [['due_date' => '2026-06-32'], '到期日非法'],
            [['due_date' => 'not-date'], '到期日非法'],
            [['issue_date' => '2026/06/10'], '出票日期非法'],
            [['issue_date' => '2026-12-31', 'due_date' => '2026-06-30'], '到期日不能早于出票日期'],
            [['source_type' => 'invoice'], '来源类型非法: 仅支持 manual=手工 receipt=关联收款单'],
            [['source_type' => 'manual', 'source_id' => 123], '手工票据不能关联来源单'],
            [['direction' => 2, 'source_type' => 'receipt', 'source_id' => 123], '开票(应付)票据不能关联收款单'],
            [['direction' => 2, 'bank_account_id' => 123], '开票(应付)票据无需指定托收账户'],
            [['direction' => 1, 'bank_account_id' => 999999], '托收银行账户不存在'],
        ];
        foreach ($cases as $i => [$overrides, $expectedErr]) {
            [$bill, $err] = $svc->store(array_merge($this->baseBill(), $overrides));
            $this->assertNull($bill);
            $this->assertSame($expectedErr, $err, 'case#' . $i);
        }

        // 票号唯一（含软删除行）：同号二次登记被拒；软删后再登记成功
        $data = $this->baseBill();
        $billNo = $data['bill_no'];
        [$bill] = $svc->store($data);
        $this->assertNotNull($bill);
        $this->billIds[] = (int) $bill->id;
        [$dup, $dupErr] = $svc->store(array_merge($this->baseBill(), ['bill_no' => $billNo]));
        $this->assertNull($dup);
        $this->assertSame('票号已存在', $dupErr);
        $bill->delete();   // 软删除
        [$again, $againErr] = $svc->store(array_merge($this->baseBill(), ['bill_no' => $billNo]));
        $this->assertNull($again, '软删除行仍占用票号（withTrashed 去重）');
        $this->assertSame('票号已存在', $againErr);
    }

    /** 收款单关联：已审核/金额一致/一单一票；收票与开票方向规则 */
    public function testStoreReceiptLink(): void
    {
        $svc = $this->billService();
        $accountId = $this->seedBankAccount('bill-r');
        $receipt = $this->seedReceipt($accountId, '500.00');
        $base = function () use ($receipt): array {
            $d = $this->baseBill();
            $d['amount'] = '500.00';
            $d['source_type'] = 'receipt';
            $d['source_id'] = $receipt;

            return $d;
        };

        [$ok, $err] = $svc->store($base());
        $this->assertNull($err, '已审核同额收款单可登记');
        $this->assertSame('receipt', (string) $ok->source_type);
        $this->assertSame($receipt, (int) $ok->source_id);
        $this->billIds[] = (int) $ok->id;

        // 一单一票：同收款单再登记
        [$dup, $dupErr] = $svc->store($base());
        $this->assertNull($dup);
        $this->assertSame('该收款单已关联其他票据', $dupErr);

        // 金额不一致（收款单 500 对票据 499）
        $mis = $base();
        $mis['amount'] = '499.00';
        [$m, $mErr] = $svc->store($mis);
        $this->assertNull($m);
        $this->assertSame('收票金额须与关联收款单金额一致', $mErr);

        // 收款单未审核
        $pending = $this->seedReceipt($accountId, '500.00', 0);
        $p = $base();
        $p['source_id'] = $pending;
        [$r, $rErr] = $svc->store($p);
        $this->assertNull($r);
        $this->assertSame('关联收款单不存在或未审核', $rErr);

        // receipt 但未给 source_id
        $none = $this->baseBill();
        $none['source_type'] = 'receipt';
        [$n, $nErr] = $svc->store($none);
        $this->assertNull($n);
        $this->assertSame('关联收款单缺失', $nErr);
    }

    /** 更新规则：状态/方向/金额/日期/收款单复检 */
    public function testUpdateRules(): void
    {
        $svc = $this->billService();
        $accountId = $this->seedBankAccount('bill-u');

        // 在库票据普通修改
        $d = $this->baseBill();
        [$bill] = $svc->store($d);
        $this->billIds[] = (int) $bill->id;
        $this->assertNull($svc->update((int) $bill->id, ['amount' => '888.88', 'drawer' => '新出票人']));
        $fresh = Capsule::table('erp_finance_bill')->find((int) $bill->id);
        $this->assertSame('888.88', (string) $fresh->amount);
        $this->assertSame('新出票人', (string) $fresh->drawer);

        // 已背书不可修改
        $d2 = $this->baseBill();
        [$b2] = $svc->store($d2);
        $this->billIds[] = (int) $b2->id;
        $this->assertNull($svc->endorse((int) $b2->id, '下家'));
        $this->assertSame('仅 在库 票据可修改', $svc->update((int) $b2->id, ['drawer' => 'x']));

        // 方向不可修改
        $this->assertSame('票据方向不可修改', $svc->update((int) $bill->id, ['direction' => 2]));

        // 金额非法
        $this->assertSame('票面金额非法', $svc->update((int) $bill->id, ['amount' => '1e3']));
        $this->assertSame('票面金额必须大于 0', $svc->update((int) $bill->id, ['amount' => '0']));
        $this->assertSame('到期日非法', $svc->update((int) $bill->id, ['due_date' => '2026-06-32']));

        // 开票(应付)更新不允许带托收账户
        $d3 = $this->baseBill();
        $d3['direction'] = 2;
        [$b3] = $svc->store($d3);
        $this->billIds[] = (int) $b3->id;
        $this->assertSame('开票(应付)票据无需指定托收账户', $svc->update((int) $b3->id, ['bank_account_id' => 1]));

        // 票据不存在
        $this->assertSame('票据不存在', $svc->update($this->nextId(), ['drawer' => 'x']));

        // 关联收款单票据改金额 → 与收款单复检
        $receipt = $this->seedReceipt($accountId, '600.00');
        $d4 = $this->baseBill();
        $d4['amount'] = '600.00';
        $d4['source_type'] = 'receipt';
        $d4['source_id'] = $receipt;
        [$b4] = $svc->store($d4);
        $this->billIds[] = (int) $b4->id;
        $this->assertSame('收票金额须与关联收款单金额一致', $svc->update((int) $b4->id, ['amount' => '599.00']));
        $this->assertNull($svc->update((int) $b4->id, ['amount' => '600.00']), '同额可改');
    }

    /** 收票(应收)状态机：背书/贴现/托收/兑付/退票全链守卫 */
    public function testDirectionOneLifecycle(): void
    {
        $svc = $this->billService();
        $accountId = $this->seedBankAccount('bill-d1');
        $this->assertSame('票据不存在', $svc->endorse($this->nextId(), '下家'));
        $this->assertSame('票据不存在', $svc->discount($this->nextId(), '10'));
        $this->assertSame('票据不存在', $svc->collect($this->nextId(), $accountId));
        $this->assertSame('票据不存在', $svc->cash($this->nextId()));
        $this->assertSame('票据不存在', $svc->reject($this->nextId()));

        // --- 背书 0→1
        [$b1] = $svc->store($this->baseBill());
        $this->billIds[] = (int) $b1->id;
        $this->assertSame('被背书人必填', $svc->endorse((int) $b1->id, '  '));
        $this->assertNull($svc->endorse((int) $b1->id, '华南实业'));
        $this->assertSame('华南实业', (string) Capsule::table('erp_finance_bill')->find((int) $b1->id)->endorsee);
        $this->assertSame('仅 在库 票据可背书', $svc->endorse((int) $b1->id, '再背书'));

        // --- 贴现 0→2：贴现息边界 0 允许、等于/大于票面拒绝、负数拒绝
        [$b2] = $svc->store($this->baseBill());
        $this->billIds[] = (int) $b2->id;
        $this->assertSame('贴现息非法', $svc->discount((int) $b2->id, '1e3'));
        $this->assertSame('贴现息非法', $svc->discount((int) $b2->id, ''));
        $this->assertSame('贴现息须在 0~票面金额之间', $svc->discount((int) $b2->id, '-0.01'));
        $this->assertSame('贴现息须在 0~票面金额之间', $svc->discount((int) $b2->id, '1000.00'));
        $this->assertSame('贴现息须在 0~票面金额之间', $svc->discount((int) $b2->id, '1000.01'));
        $this->assertNull($svc->discount((int) $b2->id, '0'));
        $this->assertSame('0.00', (string) Capsule::table('erp_finance_bill')->find((int) $b2->id)->discount_fee);
        $this->assertSame(2, (int) Capsule::table('erp_finance_bill')->find((int) $b2->id)->status);
        $this->assertSame('仅 在库 票据可贴现', $svc->discount((int) $b2->id, '1'));

        // --- 托收 0→3：未指定账户/账户不存在；兑付 3→4
        [$b3] = $svc->store($this->baseBill());
        $this->billIds[] = (int) $b3->id;
        $this->assertSame('仅 托收中 票据可确认兑付', $svc->cash((int) $b3->id), '在库收票不能直接兑付');
        $this->assertSame('请先指定托收银行账户', $svc->collect((int) $b3->id, 0));
        $this->assertSame('托收银行账户不存在', $svc->collect((int) $b3->id, $this->nextId()));
        $this->assertNull($svc->collect((int) $b3->id, $accountId));
        $this->assertSame(3, (int) Capsule::table('erp_finance_bill')->find((int) $b3->id)->status);
        $this->assertSame('仅 在库 票据可托收', $svc->collect((int) $b3->id, $accountId));
        $this->assertNull($svc->cash((int) $b3->id));
        $this->assertSame(4, (int) Capsule::table('erp_finance_bill')->find((int) $b3->id)->status);

        // --- 退票：0→5、3→5；状态 4 不允许
        [$b4] = $svc->store($this->baseBill());
        $this->billIds[] = (int) $b4->id;
        $this->assertNull($svc->reject((int) $b4->id));
        $this->assertSame(5, (int) Capsule::table('erp_finance_bill')->find((int) $b4->id)->status);
        $this->assertSame('仅 在库/托收中 票据可退票', $svc->reject((int) $b3->id), '已兑付不可退票');

        [$b5] = $svc->store($this->baseBill());
        $this->billIds[] = (int) $b5->id;
        $this->assertNull($svc->collect((int) $b5->id, $accountId));
        $this->assertNull($svc->reject((int) $b5->id), '托收被拒付退回');
        $this->assertSame(5, (int) Capsule::table('erp_finance_bill')->find((int) $b5->id)->status);
    }

    /** 到期约束与开票(应付)方向禁用链 */
    public function testDirectionTwoAndExpiry(): void
    {
        $svc = $this->billService();
        $accountId = $this->seedBankAccount('bill-d2');

        // 收票已过到期日：背书/贴现/托收全拒（出票日提前 60 天以通过登记校验）
        $d = $this->baseBill();
        $d['issue_date'] = date('Y-m-d', strtotime('-60 days'));
        $d['due_date'] = date('Y-m-d', strtotime('-1 day'));
        [$b] = $svc->store($d);
        $this->billIds[] = (int) $b->id;
        $this->assertSame('票据已到期，不能背书', $svc->endorse((int) $b->id, 'x'));
        $this->assertSame('票据已到期，不能贴现', $svc->discount((int) $b->id, '1'));
        $this->assertSame('票据已到期，不能托收', $svc->collect((int) $b->id, $accountId));
        $this->assertNull($svc->reject((int) $b->id), '到期票仍可退票');

        // 开票(应付)：背书/贴现/托收全禁；0→4 解付、0→5 退回
        $d2 = $this->baseBill();
        $d2['direction'] = 2;
        [$p] = $svc->store($d2);
        $this->billIds[] = (int) $p->id;
        $this->assertSame('开票(应付)票据不能背书转让', $svc->endorse((int) $p->id, 'x'));
        $this->assertSame('开票(应付)票据不能贴现', $svc->discount((int) $p->id, '1'));
        $this->assertSame('开票(应付)票据不能托收', $svc->collect((int) $p->id, $accountId));

        $d3 = $this->baseBill();
        $d3['direction'] = 2;
        [$q] = $svc->store($d3);
        $this->billIds[] = (int) $q->id;
        $this->assertNull($svc->cash((int) $q->id), '开票到期解付 0→4');
        $this->assertSame('仅 在库 票据可确认解付', $svc->cash((int) $q->id), '已解付不可重复解付');
        $this->assertSame(4, (int) Capsule::table('erp_finance_bill')->find((int) $q->id)->status);

        $d4 = $this->baseBill();
        $d4['direction'] = 2;
        [$r] = $svc->store($d4);
        $this->billIds[] = (int) $r->id;
        $this->assertNull($svc->reject((int) $r->id), '开票对方退回 0→5');
        $this->assertSame(5, (int) Capsule::table('erp_finance_bill')->find((int) $r->id)->status);
        $this->assertSame('仅 在库 票据可确认解付', $svc->cash((int) $r->id), '已退票不可解付');
    }

    /** 到期预警：天数截止/direction 筛选/状态(在库+托收中)筛选/due_days 整数 */
    public function testDueWarnings(): void
    {
        $svc = $this->billService();
        $plus1 = date('Y-m-d', strtotime('+1 day'));
        $plus3 = date('Y-m-d', strtotime('+3 days'));
        $plus10 = date('Y-m-d', strtotime('+10 days'));

        $store = function (int $direction, string $due, string $amount = '1000.00') use ($svc): int {
            $d = $this->baseBill();
            $d['direction'] = $direction;
            $d['due_date'] = $due;
            $d['amount'] = $amount;
            [$bill] = $svc->store($d);
            $this->billIds[] = (int) $bill->id;

            return (int) $bill->id;
        };
        $b1 = $store(1, $plus1);                    // 收票 在库 1 天后到期 → 命中
        $store(1, $plus10);                          // 10 天后到期 → 超 7 天截止
        $b3 = $store(2, $plus1);                     // 开票 在库 → 命中(全量) / direction=1 时排除
        $b4 = $store(1, $plus1);                     // 收票 明天到期 → 背书后(已背书)排除
        $this->assertNull($svc->endorse($b4, '下家'));
        $b5 = $store(1, $plus3);                     // 收票 → 直接置 3 托收中(夹具手段) → 命中
        Capsule::table('erp_finance_bill')->where('id', $b5)->update(['status' => 3, 'collected_at' => date('Y-m-d H:i:s')]);
        $b6 = $store(1, $plus1);                     // 置 3 后兑付 → 状态4(已兑付)从预警排除
        Capsule::table('erp_finance_bill')->where('id', $b6)->update(['status' => 3, 'collected_at' => date('Y-m-d H:i:s')]);
        $this->assertNull($svc->cash($b6), '托收中收票兑付 3→4');

        $ids = fn (array $rows): array => array_map(fn ($r) => (int) $r['id'], $rows);

        $warn7 = $svc->dueWarnings(7);
        $this->assertSame([$b1, $b3, $b5], $ids($warn7), '在库/托收中 + 7 天内');
        $this->assertSame([$b1, $b5], $ids($svc->dueWarnings(7, 1)), 'direction=1 过滤开票');
        $this->assertSame([], $svc->dueWarnings(0, 1), '当天截止：明日到期不命中');
        foreach ($warn7 as $row) {
            if ((int) $row['id'] === $b1) {
                $this->assertSame(1, $row['due_days'], 'due_days 为距今天整数字段');
            }
        }
        $this->assertSame([$b3], $ids($svc->dueWarnings(7, 2)), 'direction=2 只看开票');
    }
}
