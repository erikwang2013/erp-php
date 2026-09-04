<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Group;

/**
 * C1 卡券集成测试：发券期限计算（30 天/长期 null）与模板三闸门（停用/限量/越限）、
 * 核销全状态机（归属→过期惰性置 2→已核销），核销来源与单号守卫、禁用会员代核销拒付。
 */
#[Group('integration')]
class C1CouponTest extends C1MemberScaffold
{
    /** 发券成功：期限 = 领取日 +30 天；实例行初始态 + 模板发放数 +1 */
    public function testIssueCouponHappyPath(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $templateId = $this->seedTemplate();

        [$data, $err] = $svc->issueCoupon($memberId, $templateId, 1001);
        $this->assertNull($err);
        $couponId = (int) $data['coupon_id'];
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $data['received_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $data['expire_at']);
        $this->assertSame(
            30 * 86400,
            strtotime((string) $data['expire_at']) - strtotime((string) $data['received_at']),
            '期限差恰为 valid_days=30 天'
        );

        $row = Capsule::table('erp_member_coupon')->where('id', $couponId)->first();
        $this->assertSame($memberId, (int) $row->member_id);
        $this->assertSame($templateId, (int) $row->template_id);
        $this->assertSame(0, (int) $row->status, '初始未使用');
        $this->assertSame('', (string) $row->order_source);
        $this->assertSame((string) $data['expire_at'], (string) $row->expire_at, '返回值与落库一致');
        $tpl = Capsule::table('erp_member_coupon_template')->where('id', $templateId)->first();
        $this->assertSame(1, (int) $tpl->issued_qty, '模板发放数 +1');
    }

    /** valid_days=0 长期券：expire_at 恒 null，总览可用数口径含长期券 */
    public function testIssueValidDaysZeroExpireNull(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $templateId = $this->seedTemplate(['valid_days' => 0]);

        [$data, $err] = $svc->issueCoupon($memberId, $templateId, 1001);
        $this->assertNull($err);
        $this->assertNull($data['expire_at'], '长期券无到期日');
        $row = Capsule::table('erp_member_coupon')->where('id', (int) $data['coupon_id'])->first();
        $this->assertNull($row->expire_at);

        [$ov] = $svc->memberOverview($memberId);
        $this->assertSame(1, $ov['coupons_available'], '长期未用券计入可用');
    }

    /** 发券闸门：模板缺失/停用同文案；限量发满即拒且发放数不动；越限边界恰放行 */
    public function testIssueCouponTemplateGates(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();

        [$d1, $e1] = $svc->issueCoupon($memberId, 999999999, 1001);
        $this->assertNull($d1);
        $this->assertSame('卡券模板不存在或已停用', $e1);

        $disabledTpl = $this->seedTemplate(['status' => 0]);
        [$d2, $e2] = $svc->issueCoupon($memberId, $disabledTpl, 1001);
        $this->assertNull($d2);
        $this->assertSame('卡券模板不存在或已停用', $e2);

        $fullTpl = $this->seedTemplate(['total_qty' => 1, 'issued_qty' => 1]);
        [$d3, $e3] = $svc->issueCoupon($memberId, $fullTpl, 1001);
        $this->assertNull($d3);
        $this->assertSame('该卡券模板已发完', $e3);
        $this->assertRowCount('erp_member_coupon', ['member_id' => $memberId], 0, '拒绝不发券');
        $this->assertSame(1, (int) Capsule::table('erp_member_coupon_template')
            ->where('id', $fullTpl)->value('issued_qty'), '发放数不越限');

        $boundaryTpl = $this->seedTemplate(['total_qty' => 2, 'issued_qty' => 1]);
        [$b1, $b1e] = $svc->issueCoupon($memberId, $boundaryTpl, 1001); // issued 1→2 = total
        $this->assertNull($b1e);
        $this->assertSame(2, (int) Capsule::table('erp_member_coupon_template')
            ->where('id', $boundaryTpl)->value('issued_qty'));
        [$b2, $b2e] = $svc->issueCoupon($memberId, $boundaryTpl, 1001); // 2→3 越限
        $this->assertNull($b2);
        $this->assertSame('该卡券模板已发完', $b2e);

        $badMember = $this->seedMember(['status' => 0]);
        $tpl = $this->seedTemplate();
        [$d4, $e4] = $svc->issueCoupon($badMember, $tpl, 1001);
        $this->assertNull($d4);
        $this->assertSame('会员不存在或已禁用', $e4);
        $this->assertSame(0, (int) Capsule::table('erp_member_coupon_template')
            ->where('id', $tpl)->value('issued_qty'), '禁用会员不发券');
    }

    /** 核销状态机：他人代核拒、属主核销置 1 记来源、再核销拒；总览可用数归零 */
    public function testRedeemHappyPathAndStateMachine(): void
    {
        $svc = $this->memberService();
        $owner = $this->seedMember();
        $other = $this->seedMember();
        $templateId = $this->seedTemplate();
        [$iss] = $svc->issueCoupon($owner, $templateId, 1001);
        $couponId = (int) $iss['coupon_id'];

        [$d1, $e1] = $svc->redeemCoupon($couponId, 'POS-1', 1001, $other);
        $this->assertNull($d1);
        $this->assertSame('该卡券不属于该会员', $e1);

        [$ok, $okErr] = $svc->redeemCoupon($couponId, 'POS-1', 1001, $owner);
        $this->assertNull($okErr);
        $this->assertNotNull($ok['used_at']);
        $row = Capsule::table('erp_member_coupon')->where('id', $couponId)->first();
        $this->assertSame(1, (int) $row->status, '核销置 1');
        $this->assertSame('POS-1', (string) $row->order_source, '记录核销来源单号');
        $this->assertNotNull($row->used_at);

        [$d2, $e2] = $svc->redeemCoupon($couponId, 'POS-2', 1001, $owner);
        $this->assertNull($d2);
        $this->assertSame('该卡券已核销', $e2);

        [$ov] = $svc->memberOverview($owner);
        $this->assertSame(0, $ov['coupons_available'], '核销后可用数归零');
    }

    /** 过期核销：事务内判拒 + 事务外惰性补记 status=2；再核销同拒；总览不计可用 */
    public function testRedeemExpiredLazySetsStatusTwo(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $templateId = $this->seedTemplate();
        $couponId = $this->seedCoupon($memberId, $templateId, [
            'expire_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        [$d1, $e1] = $svc->redeemCoupon($couponId, 'POS-X', 1001); // 管理端代核销 memberId=0
        $this->assertNull($d1);
        $this->assertSame('该卡券已过期', $e1);
        $row = Capsule::table('erp_member_coupon')->where('id', $couponId)->first();
        $this->assertSame(2, (int) $row->status, '回滚事务外惰性补记过期态');
        $this->assertNull($row->used_at);
        $this->assertSame('', (string) $row->order_source);

        [$d2, $e2] = $svc->redeemCoupon($couponId, 'POS-X', 1001);
        $this->assertNull($d2);
        $this->assertSame('该卡券已过期', $e2);

        [$ov] = $svc->memberOverview($memberId);
        $this->assertSame(0, $ov['coupons_available'], '过期券不计可用');
    }

    /** 核销守卫：来源空/超长、卡券不存在；合法来源代核销通过 */
    public function testRedeemInputGuards(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember();
        $templateId = $this->seedTemplate();
        $couponId = $this->seedCoupon($memberId, $templateId);

        [$d1, $e1] = $svc->redeemCoupon($couponId, '   ', 1001, $memberId);
        $this->assertNull($d1);
        $this->assertSame('核销来源不能为空', $e1);
        [$d2, $e2] = $svc->redeemCoupon($couponId, str_repeat('x', 21), 1001, $memberId);
        $this->assertNull($d2);
        $this->assertSame('核销来源超长(20)', $e2);
        [$d3, $e3] = $svc->redeemCoupon(999999999, 'POS-1', 1001, $memberId);
        $this->assertNull($d3);
        $this->assertSame('卡券不存在', $e3);

        [$ok, $okErr] = $svc->redeemCoupon($couponId, 'POS-1', 1001); // 代核销（controller 形态）
        $this->assertNull($okErr);
        $this->assertSame(1, (int) Capsule::table('erp_member_coupon')
            ->where('id', $couponId)->value('status'));
    }

    /** 禁用会员券不可核销（代核销亦拒）；券状态原地不动 */
    public function testRedeemDisabledMemberRejected(): void
    {
        $svc = $this->memberService();
        $memberId = $this->seedMember(['status' => 0]);
        $templateId = $this->seedTemplate();
        $couponId = $this->seedCoupon($memberId, $templateId);

        [$d, $e] = $svc->redeemCoupon($couponId, 'POS-1', 1001);
        $this->assertNull($d);
        $this->assertSame('会员不存在或已禁用', $e);
        $row = Capsule::table('erp_member_coupon')->where('id', $couponId)->first();
        $this->assertSame(0, (int) $row->status, '拒付不改券态');
        $this->assertNull($row->used_at);
    }
}
