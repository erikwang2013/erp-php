<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * C1 对抗性集成测试：coder 用例之外的边界与并发攻击面——
 * ①消费精确扣减至 0.00/同 biz 重复消费（服务层无消费幂等，锁语义）；②双进程并发
 * 消费不超扣（行锁串行化，且恒 ≥1 侧命中「储值余额不足」）；③双进程并发退款同单
 * 恰一单成（判重锁内复查）；④双进程同号开卡仅一人成（uk_phone 1062 兜底路径）；
 * ⑤退款无原消费单/超原额上限（调用方控累计语义锁定）；⑥积分归零后作废/类型闸门
 * （TypeError 无副作用）；⑦核销守卫次序（归属先于过期、过期先于已核销、惰性置 2
 * 不覆盖已核销）；⑧同模板重复领无每人上限 + 核销不动发放数；⑨总览逐行独立勾稽
 * （退款不计入累计充/消）；⑩金额无尾噪（0.10+0.20=0.30 恒等串）与大额亿级回环。
 * 并发子进程凭据仅经 TEST_DB_* 环境变量传入（子进程自建连接，行锁串行化）。
 */
#[Group('integration')]
final class C1AdversarialIntegrationTest extends C1MemberScaffold
{
    /**
     * ①储值消费原子性：恰等于余额通过并落 0.00；0.01 超余额整笔拒绝零副作用；
     * 同 biz_id 可重复消费（服务层无消费侧幂等——POS 以自身单号防重，语义锁定）；
     * 金额形态矩阵（科学计数/3 位小数/负数/零）全部事务前拒绝且零残留。
     */
    public function testConsumeZeroRemainderRepeatBizAndFormMatrix(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        [$r] = $svc->recharge($memberId, '50.00', 1001);
        $this->assertSame('50.00', $r['balance_after']);

        $bizA = (string) $this->nextId();
        [$exact, $e1] = $svc->consume($memberId, '50.00', $bizA, 1001);
        $this->assertNull($e1);
        $this->assertSame('0.00', $exact['balance_after'], '恰等于余额 → 0.00 无负');

        $beforeLogs = Capsule::table('erp_member_balance_log')->where('member_id', $memberId)->count();
        [$over, $e2] = $svc->consume($memberId, '0.01', (string) $this->nextId(), 1001);
        $this->assertNull($over);
        $this->assertSame('储值余额不足', $e2);
        $this->assertSame($beforeLogs, Capsule::table('erp_member_balance_log')
            ->where('member_id', $memberId)->count(), '不足拒绝不留流水');
        $this->assertSame('0.00', (string) Capsule::table('erp_member_balance_account')
            ->where('member_id', $memberId)->value('balance'), '账户分毫不动');

        // 同 biz 二次消费：无幂等闸门，余额够即再扣（POS 侧自防重，语义锁定）
        [$r2] = $svc->recharge($memberId, '20.00', 1001);
        $this->assertSame('20.00', $r2['balance_after']);
        [$again, $e3] = $svc->consume($memberId, '5.00', $bizA, 1001);
        $this->assertNull($e3);
        $this->assertSame('15.00', $again['balance_after'], '同 biz 二次消费照常扣款');
        $this->assertRowCount('erp_member_balance_log',
            ['member_id' => $memberId, 'biz_type' => 'consume', 'biz_id' => (int) $bizA], 2);

        // 金额形态矩阵：全部事务前拒绝，账户行都未建（零副作用）
        $m2 = $this->seedMember();
        foreach ([['1e3', '消费金额非法（最多两位小数）'], ['1.234', '消费金额非法（最多两位小数）'],
                  ['1.230', '消费金额非法（最多两位小数）'], ['-1.00', '消费金额必须大于 0'],
                  ['0.00', '消费金额必须大于 0']] as $i => [$amount, $msg]) {
            [$d, $e] = $svc->consume($m2, $amount, (string) $this->nextId(), 1001);
            $this->assertNull($d, "consume case#$i");
            $this->assertSame($msg, $e, "consume case#$i");
        }
        foreach ([['1e3', '退款金额非法（最多两位小数）'], ['1.230', '退款金额非法（最多两位小数）'],
                  ['-0.01', '退款金额必须大于 0'], ['0', '退款金额必须大于 0']] as $i => [$amount, $msg]) {
            [$d, $e] = $svc->refund($m2, $amount, (string) $this->nextId(), 1001);
            $this->assertNull($d, "refund case#$i");
            $this->assertSame($msg, $e, "refund case#$i");
        }
        [$d, $e] = $svc->refund($m2, '1.00', 'REF-X', 1001);
        $this->assertNull($d);
        $this->assertSame('业务单号非法（须为纯数字）', $e);
        $this->assertRowCount('erp_member_balance_log', ['member_id' => $m2], 0, '形态拒绝零残留');
        $this->assertRowCount('erp_member_balance_account', ['member_id' => $m2], 0, '未建账户行');
    }

    /**
     * ⑤退款语义锁定：无原消费单的 biz 退款照常入账（无原单校验，凭空入账路径）；
     * 退款额可超原消费额（上限完全在调用方）。两处均为 POS 侧控累计的设计缺口观察。
     */
    public function testRefundNoOriginCheckAndOverRefundLocked(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        [$r] = $svc->recharge($memberId, '100.00', 1001);
        $this->assertSame('100.00', $r['balance_after']);

        // biz 从未被消费：退款仍成功（无原消费单校验）
        $ghostBiz = (string) $this->nextId();
        [$rf1, $e1] = $svc->refund($memberId, '30.00', $ghostBiz, 1001);
        $this->assertNull($e1);
        $this->assertSame('130.00', $rf1['balance_after'], '无原单退款照常入账（语义锁定）');

        // 退款额超原消费额：仍成功（累计上限在 POS 退款单侧，语义锁定）
        $biz = (string) $this->nextId();
        [$c] = $svc->consume($memberId, '40.00', $biz, 1001);
        $this->assertSame('90.00', $c['balance_after']);
        [$rf2, $e2] = $svc->refund($memberId, '60.00', $biz, 1001);
        $this->assertNull($e2);
        $this->assertSame('150.00', $rf2['balance_after'], '超原消费额退款入账');

        [$dup, $e3] = $svc->refund($memberId, '10.00', $biz, 1001);
        $this->assertNull($dup);
        $this->assertSame('该业务单已退款', $e3, '同单判重仍生效');
        $this->assertRowCount('erp_member_balance_log',
            ['member_id' => $memberId, 'biz_type' => 'refund', 'biz_id' => (int) $biz], 1);
        $this->assertSame('150.00', (string) Capsule::table('erp_member_balance_account')
            ->where('member_id', $memberId)->value('balance'));
    }

    /**
     * ⑥积分锐边：消费恰至 0 后作废 1 分 →「积分不足」零副作用（流水/账户不动）；
     * 非整数/非 int 入参在 strict_types 下 TypeError（int 型闸门，无静默截断）；
     * 流水 points_after 快照链与账户逐行勾稽。
     */
    public function testPointsExactZeroTypeGateAndSnapshotChain(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        [$e] = $svc->earnPoints($memberId, 200, 1001);
        $this->assertSame(200, $e['points_after']);
        [$u] = $svc->consumePoints($memberId, 200, 1001);
        $this->assertSame(0, $u['points_after'], '消费恰至 0 通过');

        [$over, $oe] = $svc->expirePoints($memberId, 1, 1001);
        $this->assertNull($over);
        $this->assertSame('积分不足', $oe);
        $this->assertRowCount('erp_member_point_log', ['member_id' => $memberId], 2, '拒绝不留积分流水');
        $this->assertSame(0, (int) Capsule::table('erp_member_point_account')
            ->where('member_id', $memberId)->value('points'), '账户仍为 0');

        [$a] = $svc->earnPoints($memberId, 5, 1001);
        $this->assertSame(5, $a['points_after']);
        foreach ([fn () => $svc->earnPoints($memberId, 1.5, 1001),
                  fn () => $svc->consumePoints($memberId, '30', 1001)] as $i => $call) {
            try {
                $call();
                $this->fail("type gate case#$i 未抛 TypeError");
            } catch (\TypeError) {
                $this->assertTrue(true, "type gate case#$i 生效");
            }
        }
        $this->assertSame(5, (int) Capsule::table('erp_member_point_account')
            ->where('member_id', $memberId)->value('points'), 'TypeError 无副作用');
        $this->assertRowCount('erp_member_point_log', ['member_id' => $memberId], 3);

        $logs = Capsule::table('erp_member_point_log')->where('member_id', $memberId)->orderBy('id')->get();
        $sum = 0;
        foreach ($logs as $i => $log) {
            $sum += (int) $log->points;
            $accAfter = [$e['points_after'], $u['points_after'], $a['points_after']][$i];
            $this->assertSame($accAfter, (int) $log->points_after, "points_after 快照#$i 与返回一致");
        }
        $this->assertSame(5, $sum, 'Σ流水 = 账户积分');
    }

    /**
     * ⑦核销守卫次序与过期秒级边界：归属校验先于过期（他人核已过期的券 → 归属错误、
     * 不触发惰性置 2）；expire_at == 当前秒即判过期（PHP 侧 <= 与 SQL 侧 > 同口径）；
     * 过期判断先于已核销判断（已核销且已过期的券报「该卡券已过期」）；惰性置 2 带
     * status=0 守卫不覆盖已核销行。
     */
    public function testRedeemGuardOrderingAndExpiryBoundary(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $other = $this->seedMember();
        $templateId = $this->seedTemplate();

        // 过期券（expire_at = 当前秒）：他人以归属 id 核销 → 归属错误先于过期，且不改券态
        $now = date('Y-m-d H:i:s');
        $c1 = $this->seedCoupon($memberId, $templateId, ['expire_at' => $now]);
        [$d1, $e1] = $svc->redeemCoupon($c1, 'POS-1', 1001, $other);
        $this->assertNull($d1);
        $this->assertSame('该卡券不属于该会员', $e1);
        $row = Capsule::table('erp_member_coupon')->where('id', $c1)->first();
        $this->assertSame(0, (int) $row->status, '归属错误不改券态（惰性置 2 未触发）');
        $this->assertNull($row->used_at);

        // 属主/代核销路径 → 过期拒绝 + 惰性置 2
        [$d2, $e2] = $svc->redeemCoupon($c1, 'POS-1', 1001);
        $this->assertNull($d2);
        $this->assertSame('该卡券已过期', $e2, 'expire_at == 当前秒即过期（<= 口径）');
        $this->assertSame(2, (int) Capsule::table('erp_member_coupon')->where('id', $c1)->value('status'));

        // 已核销且已过期：过期先于已核销被命中；惰性置 2 不覆盖 status=1 行
        $c2 = $this->seedCoupon($memberId, $templateId, [
            'expire_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'status' => 1,
            'used_at' => date('Y-m-d H:i:s', strtotime('-2 hour')),
            'order_source' => 'POS-OLD',
        ]);
        [$d3, $e3] = $svc->redeemCoupon($c2, 'POS-2', 1001);
        $this->assertNull($d3);
        $this->assertSame('该卡券已过期', $e3, '过期判断先于已核销判断');
        $row2 = Capsule::table('erp_member_coupon')->where('id', $c2)->first();
        $this->assertSame(1, (int) $row2->status, '惰性置 2 守卫不覆盖已核销行');
        $this->assertNotNull($row2->used_at);

        // 未过期券（+2 分钟）正常核销；总览可用数三券归零
        $c3 = $this->seedCoupon($memberId, $templateId, [
            'expire_at' => date('Y-m-d H:i:s', strtotime('+2 minutes')),
        ]);
        [$ok, $e4] = $svc->redeemCoupon($c3, 'POS-3', 1001, $memberId);
        $this->assertNull($e4);
        $this->assertNotNull($ok['used_at']);
        [$ov] = $svc->memberOverview($memberId);
        $this->assertSame(0, $ov['coupons_available'], '过期/已核销均不计可用');
    }

    /**
     * ⑧发券边界：同会员同模板可无限重复领取（无每人限领上限——观察）；
     * 核销不回改模板发放数（发放数只在发券时计数）。
     */
    public function testIssueRepeatSameMemberRedeemKeepsIssuedQty(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $templateId = $this->seedTemplate(['total_qty' => 0]);

        [$i1] = $svc->issueCoupon($memberId, $templateId, 1001);
        [$i2, $e2] = $svc->issueCoupon($memberId, $templateId, 1001);
        $this->assertNull($e2);
        $this->assertNotSame($i1['coupon_id'], $i2['coupon_id']);
        $this->assertRowCount('erp_member_coupon', ['member_id' => $memberId], 2, '同人同模板可重复领');
        $this->assertSame(2, (int) Capsule::table('erp_member_coupon_template')
            ->where('id', $templateId)->value('issued_qty'));

        [$ok] = $svc->redeemCoupon((int) $i1['coupon_id'], 'POS-1', 1001, $memberId);
        $this->assertNotNull($ok['used_at']);
        $this->assertSame(2, (int) Capsule::table('erp_member_coupon_template')
            ->where('id', $templateId)->value('issued_qty'), '核销不动模板发放数');
        [$ov] = $svc->memberOverview($memberId);
        $this->assertSame(1, $ov['coupons_available']);
    }

    /** ⑨总览独立勾稽：余额=Σ全量流水（测试内 bcadd 独立重算）；累计仅按 biz_type 过滤，退款两边都不计 */
    public function testOverviewLedgerIndependentCrossCheck(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $biz = (string) $this->nextId();
        $this->assertSame('100.50', $svc->recharge($memberId, '100.50', 1001)[0]['balance_after']);
        $this->assertSame('150.00', $svc->recharge($memberId, '49.50', 1001)[0]['balance_after']);
        $this->assertSame('119.75', $svc->consume($memberId, '30.25', $biz, 1001)[0]['balance_after']);
        $this->assertSame('129.75', $svc->refund($memberId, '10.00', $biz, 1001)[0]['balance_after']);
        $this->assertSame('120.00', $svc->consume($memberId, '9.75', (string) $this->nextId(), 1001)[0]['balance_after']);

        $sum = '0';
        foreach (Capsule::table('erp_member_balance_log')->where('member_id', $memberId)->get() as $log) {
            $sum = bcadd($sum, (string) $log->amount, 2);
        }
        $acc = Capsule::table('erp_member_balance_account')->where('member_id', $memberId)->first();
        $this->assertBcEquals($sum, (string) $acc->balance, '账户 = Σ全量流水(独立重算)');

        [$ov] = $svc->memberOverview($memberId);
        $this->assertBcEquals('120.00', $ov['balance']);
        $this->assertBcEquals($sum, $ov['balance'], '总览余额 = Σ流水');
        $this->assertSame('150.00', $ov['total_recharge'], '退款 10.00 不计入累计充值');
        $this->assertSame('40.00', $ov['total_consume'], '30.25+9.75 恰合计，退款不计');

        [$p1] = $svc->earnPoints($memberId, 500, 1001);
        [$p2] = $svc->consumePoints($memberId, 200, 1001);
        $this->assertSame(300, $p2['points_after']);
        $this->assertSame(300, (int) Capsule::table('erp_member_point_account')
            ->where('member_id', $memberId)->value('points'));
        $pointSum = 0;
        foreach (Capsule::table('erp_member_point_log')->where('member_id', $memberId)->get() as $log) {
            $pointSum += (int) $log->points;
        }
        $this->assertSame(300, $pointSum, 'Σ积分流水 = 账户');
        [$ov2] = $svc->memberOverview($memberId);
        $this->assertSame(300, $ov2['points']);

        // 总览只读：无账户行的会员出 0.00 且不自动补建账户
        $bare = $this->seedMember();
        [$bo] = $svc->memberOverview($bare);
        $this->assertSame('0.00', $bo['balance']);
        $this->assertRowCount('erp_member_balance_account', ['member_id' => $bare], 0, '总览不补建账户');
    }

    /** ⑩金额精度：0.10+0.20 恒等串 0.30 无尾噪；亿级大额 DECIMAL(14,2) 满回环 */
    public function testMoneyPrecisionAndHugeAmountRoundTrip(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $this->assertSame('0.10', $svc->recharge($memberId, '0.10', 1001)[0]['balance_after']);
        $this->assertSame('0.30', $svc->recharge($memberId, '0.20', 1001)[0]['balance_after'], '0.10+0.20=0.30 无尾噪');
        $biz = (string) $this->nextId();
        $this->assertSame('0.20', $svc->consume($memberId, '0.10', $biz, 1001)[0]['balance_after']);
        $this->assertSame('0.25', $svc->refund($memberId, '0.05', $biz, 1001)[0]['balance_after']);
        $log = Capsule::table('erp_member_balance_log')->where('biz_type', 'refund')->first();
        $this->assertSame('0.05', (string) $log->amount, '退款流水入正无尾噪');

        [$ov] = $svc->memberOverview($memberId);
        $this->assertSame('0.25', $ov['balance']);
        $this->assertSame('0.30', $ov['total_recharge'], '0.10+0.20 累计口径无尾噪');
        $this->assertSame('0.10', $ov['total_consume'], '退款 0.05 不进累计消费');

        $big = $this->seedMember();
        $this->assertSame('1000000000.00', $svc->recharge($big, '1000000000.00', 1001)[0]['balance_after']);
        $bb = (string) $this->nextId();
        [$c] = $svc->consume($big, '999999999.01', $bb, 1001);
        $this->assertSame('0.99', $c['balance_after'], '10 亿 - 999999999.01 = 0.99 精确');
        $clog = Capsule::table('erp_member_balance_log')->where('biz_id', (int) $bb)->first();
        $this->assertSame('-999999999.01', (string) $clog->amount);
        $this->assertSame('0.99', (string) Capsule::table('erp_member_balance_account')
            ->where('member_id', $big)->value('balance'));
        [$bov] = $svc->memberOverview($big);
        $this->assertSame('1000000000.00', $bov['total_recharge']);
        $this->assertSame('999999999.01', $bov['total_consume']);
    }

    /** 并发：同账户双进程各消费 60（余额 100）——行锁串行化，恒恰一单成，绝不通兑 */
    public function testConcurrentDoubleConsumeNoOverdraw(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $this->assertSame('100.00', $svc->recharge($memberId, '100.00', 1001)[0]['balance_after']);

        $results = $this->runChildOps([
            ['mode' => 'consume', 'args' => ['member' => $memberId, 'amount' => '60.00', 'biz' => (string) $this->nextId()]],
            ['mode' => 'consume', 'args' => ['member' => $memberId, 'amount' => '60.00', 'biz' => (string) $this->nextId()]],
        ]);
        $wins = array_values(array_filter($results, fn ($r) => $r[1] === null));
        $fails = array_values(array_filter($results, fn ($r) => $r[1] !== null));
        $this->assertCount(1, $wins, '两笔 60 对余额 100 恒恰一单成功');
        $this->assertSame('40.00', $wins[0][0]['balance_after']);
        $this->assertCount(1, $fails);
        $this->assertSame('储值余额不足', $fails[0][1]);
        $this->assertSame('40.00', (string) Capsule::table('erp_member_balance_account')
            ->where('member_id', $memberId)->value('balance'), '余额永不为负（不超扣）');
        $this->assertRowCount('erp_member_balance_log',
            ['member_id' => $memberId, 'biz_type' => 'consume'], 1, '仅一单留流水');
    }

    /** 并发：同 biz 双进程并发退款——判重在账户行锁内复查，恒恰一单退成 */
    public function testConcurrentDoubleRefundSingleWin(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $this->assertSame('100.00', $svc->recharge($memberId, '100.00', 1001)[0]['balance_after']);
        $biz = (string) $this->nextId();
        $this->assertSame('60.00', $svc->consume($memberId, '40.00', $biz, 1001)[0]['balance_after']);

        $results = $this->runChildOps([
            ['mode' => 'refund', 'args' => ['member' => $memberId, 'amount' => '40.00', 'biz' => $biz]],
            ['mode' => 'refund', 'args' => ['member' => $memberId, 'amount' => '40.00', 'biz' => $biz]],
        ]);
        $wins = array_values(array_filter($results, fn ($r) => $r[1] === null));
        $fails = array_values(array_filter($results, fn ($r) => $r[1] !== null));
        $this->assertCount(1, $wins, '并发双退恰一单成');
        $this->assertSame('100.00', $wins[0][0]['balance_after']);
        $this->assertCount(1, $fails);
        $this->assertSame('该业务单已退款', $fails[0][1]);
        $this->assertRowCount('erp_member_balance_log',
            ['member_id' => $memberId, 'biz_type' => 'refund', 'biz_id' => (int) $biz], 1);
        $this->assertSame('100.00', (string) Capsule::table('erp_member_balance_account')
            ->where('member_id', $memberId)->value('balance'));
    }

    /** 并发：双进程同号开卡——uk_phone 兜底（1062 分支）恒一人成，账户/积分各 1 行 */
    public function testConcurrentOpenSamePhoneSingleWinner(): void
    {
        $phone = $this->freshPhone();
        $results = $this->runChildOps([
            ['mode' => 'open', 'args' => ['phone' => $phone]],
            ['mode' => 'open', 'args' => ['phone' => $phone]],
        ]);
        $wins = array_values(array_filter($results, fn ($r) => $r[1] === null));
        $fails = array_values(array_filter($results, fn ($r) => $r[1] !== null));
        $this->assertCount(1, $wins, '并发同号开卡恰一人成');
        $this->assertSame($phone, $wins[0][0]['phone']);
        $this->assertCount(1, $fails);
        $this->assertSame('该手机号已开卡，不可重复开卡', $fails[0][1]);

        $winner = Capsule::table('erp_member')->where('phone', $phone)->first();
        $this->assertNotNull($winner);
        $this->assertRowCount('erp_member', ['phone' => $phone], 1, '同号仅一行');
        $this->assertRowCount('erp_member_balance_account', ['member_id' => (int) $winner->id], 1);
        $this->assertRowCount('erp_member_point_account', ['member_id' => (int) $winner->id], 1);
        $this->memberIds[] = (int) $winner->id; // 子进程落行，父进程登记清理
    }

    // ---------- 双进程并发工具（仿 F5：子进程自建 Capsule，凭据仅走 TEST_DB_* 环境变量） ----------

    /**
     * 并发执行 2 个独立 php 子进程（各自新建 DB 连接；行锁在服务层事务内串行化）。
     * 返回逐个子进程的 [data|null, err|null] 解码结果（顺序与入参一致）。
     */
    private function runChildOps(array $jobs): array
    {
        $root = dirname(__DIR__, 2);
        $script = tempnam(sys_get_temp_dir(), 'c1race');
        file_put_contents($script, <<<PHP
            <?php
            declare(strict_types=1);
            require '{$root}/vendor/autoload.php'; // composer files 已含 app/functions.php（bcmath 助手）
            \$c = new Illuminate\\Database\\Capsule\\Manager();
            \$c->addConnection([
                'driver' => 'mysql',
                'host' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
                'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
                'database' => (string) getenv('TEST_DB_DATABASE'),
                'username' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
                'password' => (string) getenv('TEST_DB_PASSWORD'),
                'prefix' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'strict' => true, 'engine' => 'InnoDB',
            ], 'default');
            \$c->setAsGlobal();
            \$c->bootEloquent();
            \$job = json_decode((string) \$argv[1], true);
            \$svc = new app\\service\\retail\\MemberService();
            \$a = \$job['args'];
            \$out = match (\$job['mode']) {
                'consume' => \$svc->consume((int) \$a['member'], (string) \$a['amount'], (string) \$a['biz'], 1001),
                'refund' => \$svc->refund((int) \$a['member'], (string) \$a['amount'], (string) \$a['biz'], 1001),
                'open' => \$svc->openMember(['phone' => (string) \$a['phone'], 'name' => 'T-C1-并发开卡']),
                default => [null, 'unknown mode'],
            };
            echo json_encode(\$out, JSON_UNESCAPED_UNICODE);
            PHP);
        try {
            $env = array_filter(array_merge($_ENV, $_SERVER, [
                'TEST_DB_HOST' => (string) getenv('TEST_DB_HOST') ?: '127.0.0.1',
                'TEST_DB_PORT' => (string) (getenv('TEST_DB_PORT') ?: 3306),
                'TEST_DB_DATABASE' => (string) getenv('TEST_DB_DATABASE'),
                'TEST_DB_USERNAME' => (string) (getenv('TEST_DB_USERNAME') ?: 'root'),
                'TEST_DB_PASSWORD' => (string) getenv('TEST_DB_PASSWORD'),
            ]), 'is_scalar');
            $procs = $pipes = [];
            foreach ($jobs as $i => $job) {
                $procs[$i] = proc_open(
                    [PHP_BINARY, $script, json_encode($job, JSON_UNESCAPED_UNICODE)],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes[$i], $root, $env
                );
                $this->assertIsResource($procs[$i], "无法启动并发子进程 #{$i}");
            }
            $results = [];
            foreach ($jobs as $i => $job) {
                stream_set_timeout($pipes[$i][1], 30);
                stream_set_timeout($pipes[$i][2], 30);
                $out = stream_get_contents($pipes[$i][1]);
                $err = stream_get_contents($pipes[$i][2]);
                $code = proc_close($procs[$i]);
                $decoded = json_decode((string) $out, true);
                $this->assertIsArray($decoded, "子进程 #{$i} 输出非 JSON（exit={$code}）stderr: " . (string) $err);
                $results[] = $decoded;
            }

            return $results;
        } finally {
            @unlink($script);
        }
    }
}
