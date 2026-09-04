<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * C1 会员/储值/积分集成测试：开卡校验全链与手机号唯一（含软删占用）、
 * 充-消-退账实一致（金额一律字符串断言）、消费余额门槛不留痕、
 * 退款同单判重、积分三向变动与不足拒付、总览汇总口径（余额取账户行/累计逐行求和）。
 */
#[Group('integration')]
class C1MemberTest extends C1MemberScaffold
{
    /** 开卡成功：主档 shape 全显式键 + 储值 0.00/积分 0 同步建档，三表各 1 行 */
    public function testOpenHappyPath(): void
    {
        [$member, $err] = $this->memberService()->openMember([
            'phone' => $this->freshPhone(),
            'name' => '张三',
            'source' => 'pos',
        ]);
        $this->assertNull($err);
        $this->assertNotNull($member);
        $id = (int) $member['id'];
        $this->assertSame(1, $member['status']);
        $this->assertSame('张三', $member['name']);
        $this->assertSame(0, $member['level']);
        $this->assertSame(0, $member['customer_id']);
        $this->assertSame('pos', $member['source']);
        $this->assertSame('', $member['remark']);
        $this->assertMatchesRegularExpression('/^1[3-9]\d{9}$/', (string) $member['phone']);
        $this->memberIds[] = $id; // openMember 直建：手动登记清理
        $this->assertRowCount('erp_member', ['id' => $id], 1);
        $this->assertRowCount('erp_member_balance_account', ['member_id' => $id], 1);
        $this->assertRowCount('erp_member_point_account', ['member_id' => $id], 1);
        $acc = Capsule::table('erp_member_balance_account')->where('member_id', $id)->first();
        $this->assertSame('0.00', (string) $acc->balance, '储值账户初始 0.00 字符串落库');
        $point = Capsule::table('erp_member_point_account')->where('member_id', $id)->first();
        $this->assertSame(0, (int) $point->points, '积分账户初始 0');
    }

    /** 开卡校验全链：手机号格式/姓名/等级/客户/来源/备注逐条稳定断言 */
    public function testOpenValidationGuards(): void
    {
        $svc = $this->memberService();
        $base = ['phone' => $this->freshPhone(), 'name' => '李四'];
        $cases = [
            [['phone' => ''], '手机号格式非法，须为 11 位手机号'],
            [['phone' => '12345'], '手机号格式非法，须为 11 位手机号'],
            [['phone' => '12345678901'], '手机号格式非法，须为 11 位手机号'], // 非 13x-19x 号段
            [['phone' => '1391234567a'], '手机号格式非法，须为 11 位手机号'],
            [['name' => ''], '姓名必填'],
            [['name' => str_repeat('名', 51)], '姓名超长(50)'],
            [['level' => 4], '会员等级非法'],
            [['level' => -1], '会员等级非法'],
            [['customer_id' => 999999999], '关联客户不存在'],
            [['source' => 'wechat'], '开卡来源非法'],
            [['remark' => str_repeat('备', 501)], '备注超长(500)'],
        ];
        foreach ($cases as $i => [$overrides, $expectedErr]) {
            [$data, $err] = $svc->openMember(array_merge($base, $overrides));
            $this->assertNull($data, 'case#' . $i);
            $this->assertSame($expectedErr, $err, 'case#' . $i);
        }
        $this->assertRowCount('erp_member', ['phone' => $base['phone']], 0, '校验失败不落行');
    }

    /** 手机号唯一：重复开卡拒绝；软删后号码仍占用（uk_phone + withTrashed 双保险） */
    public function testOpenDuplicatePhoneRejected(): void
    {
        $svc = $this->memberService();
        $phone = $this->freshPhone();
        [$member] = $svc->openMember(['phone' => $phone, 'name' => '王五']);
        $this->assertNotNull($member);
        $this->memberIds[] = (int) $member['id'];

        [$dup, $dupErr] = $svc->openMember(['phone' => $phone, 'name' => '王五二号']);
        $this->assertNull($dup);
        $this->assertSame('该手机号已开卡，不可重复开卡', $dupErr);

        // 软删占用：withTrashed 复核仍拒绝重开
        Capsule::table('erp_member')->where('id', (int) $member['id'])
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        [$again, $againErr] = $svc->openMember(['phone' => $phone, 'name' => '王五三号']);
        $this->assertNull($again);
        $this->assertSame('该手机号已开卡，不可重复开卡', $againErr, '软删会员号码不可重开');
        $this->assertRowCount('erp_member', ['phone' => $phone], 1, '不产生第二行');
    }

    /** 储值充值：金额边界校验（bcmath 陷阱形态全拒）+ 落账与流水勾稽 + 累计入总览 */
    public function testRechargeCreditsAndValidates(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $cases = [
            ['abc', '充值金额非法（最多两位小数）'],
            ['1e3', '充值金额非法（最多两位小数）'],   // bcmath ValueError 前哨
            ['1.234', '充值金额非法（最多两位小数）'],
            ['INF', '充值金额非法（最多两位小数）'],
            ['0', '充值金额必须大于 0'],
            ['-5.00', '充值金额必须大于 0'],
        ];
        foreach ($cases as $i => [$amount, $expectedErr]) {
            [$data, $err] = $svc->recharge($memberId, $amount, 1001, '');
            $this->assertNull($data, 'case#' . $i);
            $this->assertSame($expectedErr, $err, 'case#' . $i);
        }
        [$d, $e] = $svc->recharge($memberId, '10.00', 1001, str_repeat('备', 501));
        $this->assertNull($d);
        $this->assertSame('备注超长(500)', $e);
        [$d2, $e2] = $svc->recharge($this->seedMember(['status' => 0]), '10.00', 1001);
        $this->assertNull($d2);
        $this->assertSame('会员不存在或已禁用', $e2);

        [$ok, $okErr] = $svc->recharge($memberId, '100.50', 1001, '首充');
        $this->assertNull($okErr);
        $this->assertSame('100.50', $ok['balance_after'], '2 位小数字符串直出');
        $log = Capsule::table('erp_member_balance_log')->where('member_id', $memberId)->first();
        $this->assertSame('recharge', (string) $log->biz_type);
        $this->assertSame('100.50', (string) $log->amount);
        $this->assertSame('100.50', (string) $log->balance_after);
        $this->assertSame(1001, (int) $log->operator_id);
        $this->assertSame('首充', (string) $log->remark);

        [$ok2] = $svc->recharge($memberId, '49.50', 1001);
        $this->assertSame('150.00', $ok2['balance_after']);
        $acc = Capsule::table('erp_member_balance_account')->where('member_id', $memberId)->first();
        $this->assertSame('150.00', (string) $acc->balance, '账户行与流水同步');
        $this->assertRowCount('erp_member_balance_log', ['member_id' => $memberId, 'biz_type' => 'recharge'], 2);
    }

    /** 储值消费：余额不足整笔拒绝且不留流水；biz_id 纯数字校验；流水出负落库 */
    public function testConsumeEnforcesBalance(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember(); // 无账户行 → 事务内自动补建 0.00
        [$r] = $svc->recharge($memberId, '50.00', 1001);
        $this->assertSame('50.00', $r['balance_after']);

        [$d1, $e1] = $svc->consume($memberId, 'abc', (string) $this->nextId(), 1001);
        $this->assertSame('消费金额非法（最多两位小数）', $e1);
        [$d2, $e2] = $svc->consume($memberId, '0.00', (string) $this->nextId(), 1001);
        $this->assertSame('消费金额必须大于 0', $e2);
        [$d3, $e3] = $svc->consume($memberId, '1.00', '', 1001);
        $this->assertSame('业务单号非法（须为纯数字）', $e3);
        [$d4, $e4] = $svc->consume($memberId, '1.00', 'SO-001', 1001);
        $this->assertSame('业务单号非法（须为纯数字）', $e4);

        $biz = (string) $this->nextId();
        [$ok, $okErr] = $svc->consume($memberId, '30.00', $biz, 1001);
        $this->assertNull($okErr);
        $this->assertSame('20.00', $ok['balance_after']);
        $log = Capsule::table('erp_member_balance_log')->where('biz_id', (int) $biz)->first();
        $this->assertSame('consume', (string) $log->biz_type);
        $this->assertSame('-30.00', (string) $log->amount, '消费流水出负');

        // 超余额：拒绝且流水数/余额分毫不动
        $before = Capsule::table('erp_member_balance_log')->where('member_id', $memberId)->count();
        [$over, $overErr] = $svc->consume($memberId, '20.01', (string) $this->nextId(), 1001);
        $this->assertNull($over);
        $this->assertSame('储值余额不足', $overErr);
        $this->assertSame(
            $before,
            Capsule::table('erp_member_balance_log')->where('member_id', $memberId)->count(),
            '拒绝时不留流水'
        );
        $acc = Capsule::table('erp_member_balance_account')->where('member_id', $memberId)->first();
        $this->assertSame('20.00', (string) $acc->balance);
    }

    /** 储值退款：同 biz_id 判重（一单一次退款，部分退由调用方控累计）；退款入正 */
    public function testRefundSameBizIdRejected(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        [$r] = $svc->recharge($memberId, '100.00', 1001);
        $this->assertSame('100.00', $r['balance_after']);
        $biz = (string) $this->nextId();
        [$c] = $svc->consume($memberId, '40.00', $biz, 1001);
        $this->assertSame('60.00', $c['balance_after']);

        [$ref, $refErr] = $svc->refund($memberId, '30.00', $biz, 1001); // 部分退
        $this->assertNull($refErr);
        $this->assertSame('90.00', $ref['balance_after']);

        [$dup, $dupErr] = $svc->refund($memberId, '10.00', $biz, 1001); // 同单再退
        $this->assertNull($dup);
        $this->assertSame('该业务单已退款', $dupErr);
        $this->assertRowCount(
            'erp_member_balance_log',
            ['member_id' => $memberId, 'biz_type' => 'refund', 'biz_id' => (int) $biz],
            1
        );

        [$d1, $e1] = $svc->refund($memberId, 'x', $biz, 1001);
        $this->assertSame('退款金额非法（最多两位小数）', $e1);
        [$d2, $e2] = $svc->refund($memberId, '0', $biz, 1001);
        $this->assertSame('退款金额必须大于 0', $e2);
        [$d3, $e3] = $svc->refund($memberId, '1.00', '', 1001);
        $this->assertSame('业务单号非法（须为纯数字）', $e3);

        $log = Capsule::table('erp_member_balance_log')
            ->where('member_id', $memberId)->where('biz_type', 'refund')->first();
        $this->assertSame('30.00', (string) $log->amount, '退款流水入正');
        $acc = Capsule::table('erp_member_balance_account')->where('member_id', $memberId)->first();
        $this->assertSame('90.00', (string) $acc->balance);
    }

    /** 积分三向变动：带符号流水勾稽（Σpoints=账户）；不足整笔拒绝；方向/长度/状态闸门 */
    public function testPointsThreeWayMovement(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();

        [$earn, $eErr] = $svc->earnPoints($memberId, 100, 1001, '消费赠积分');
        $this->assertNull($eErr);
        $this->assertSame(100, $earn['points_after']);

        [$use, $uErr] = $svc->consumePoints($memberId, 30, 1001, '抵扣');
        $this->assertNull($uErr);
        $this->assertSame(70, $use['points_after']);

        [$over, $overErr] = $svc->consumePoints($memberId, 71, 1001);
        $this->assertNull($over);
        $this->assertSame('积分不足', $overErr);

        [$exp, $expErr] = $svc->expirePoints($memberId, 20, 1001, '过期作废');
        $this->assertNull($expErr);
        $this->assertSame(50, $exp['points_after']);

        $logs = Capsule::table('erp_member_point_log')->where('member_id', $memberId)->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $signs = [100, -30, -20];
        $sum = 0;
        foreach ($logs as $i => $log) {
            $this->assertSame($signs[$i], (int) $log->points, '流水带符号#' . $i);
            $this->assertSame(0, (int) $log->biz_id, '积分流水 biz_id 恒 0');
            $sum += (int) $log->points;
        }
        $acc = Capsule::table('erp_member_point_account')->where('member_id', $memberId)->first();
        $this->assertSame(50, (int) $acc->points);
        $this->assertSame(50, $sum, 'Σpoints = 账户积分');

        [$z1, $z1Err] = $svc->earnPoints($memberId, 0, 1001);
        $this->assertSame('积分数必须大于 0', $z1Err);
        [$z2, $z2Err] = $svc->expirePoints($memberId, -1, 1001);
        $this->assertSame('作废积分数必须大于 0', $z2Err);
        [$z3, $z3Err] = $svc->earnPoints($memberId, 5, 1001, str_repeat('备', 501));
        $this->assertSame('备注超长(500)', $z3Err);
        [$z4, $z4Err] = $svc->consumePoints($this->seedMember(['status' => 0]), 5, 1001);
        $this->assertSame('会员不存在或已禁用', $z4Err);
    }

    /** 总览口径：余额取账户行、累计按流水逐行求和（充值入正/消费取绝对值） */
    public function testMemberOverviewAggregates(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        [$r1] = $svc->recharge($memberId, '100.50', 1001);
        $this->assertSame('100.50', $r1['balance_after']);
        [$r2] = $svc->recharge($memberId, '50.00', 1001);
        $this->assertSame('150.50', $r2['balance_after']);
        $biz = (string) $this->nextId();
        [$c1] = $svc->consume($memberId, '30.25', $biz, 1001);
        $this->assertSame('120.25', $c1['balance_after']);
        [$rf] = $svc->refund($memberId, '10.00', $biz, 1001);
        $this->assertSame('130.25', $rf['balance_after']);
        [$p1] = $svc->earnPoints($memberId, 500, 1001);
        $this->assertSame(500, $p1['points_after']);
        [$p2] = $svc->consumePoints($memberId, 200, 1001);
        $this->assertSame(300, $p2['points_after']);

        [$ov, $ovErr] = $svc->memberOverview($memberId);
        $this->assertNull($ovErr);
        $this->assertSame($memberId, $ov['id']);
        $this->assertSame('C1测试会员', $ov['name']);
        $this->assertSame(1, $ov['status']);
        $this->assertBcEquals('130.25', $ov['balance'], '余额=100.50+50-30.25+10');
        $this->assertBcEquals('150.50', $ov['total_recharge']);
        $this->assertBcEquals('30.25', $ov['total_consume'], '累计消费取绝对值口径');
        $this->assertSame(300, $ov['points']);
        $this->assertSame(0, $ov['coupons_available']);

        [$miss, $missErr] = $svc->memberOverview(99999999);
        $this->assertNull($miss);
        $this->assertSame('会员不存在', $missErr);
    }
}
