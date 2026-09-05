<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\retail;

use app\admin\controller\BaseController;
use app\service\retail\MemberService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 会员价值引擎·会员/储值/积分 — P2-3 C1
 * 开卡/总览/储值充-消-退/积分赚-抵-作废；语义校验与幂等在 MemberService，
 * 本层仅 hashid 编解码 + 透传。路由注册随批次 lead 关闸（本批不注册）。
 */
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]

class MemberController extends BaseController
{
    /**
     * 会员开卡（手机号唯一，软删号码拒重开；含储值 0.00 + 积分 0 建档）
     */
#[\erikwang2013\apidoc\annotation\Title("会员开卡")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/open")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"phone", type:"string", required:true, desc:"手机号(11 位)")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", required:true, desc:"姓名")]
#[\erikwang2013\apidoc\annotation\Param(name:"level", type:"int", default:0, desc:"等级 0普通/1银卡/2金卡/3铂金")]
#[\erikwang2013\apidoc\annotation\Param(name:"customer_id", type:"string", default:"0", desc:"关联客户(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"source", type:"string", default:"manual", desc:"开卡来源 pos/miniapp/manual")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function open(Request $request): Response
    {
        $payload = $request->post();
        if (array_key_exists('customer_id', $payload) && (string) $payload['customer_id'] !== '') {
            $payload['customer_id'] = (string) $this->decodeMaybe((string) $payload['customer_id']);
        }
        [$data, $error] = $this->service()->openMember($payload);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->encodeIds($data, ['id', 'customer_id']), '开卡成功');
    }

    /**
     * 会员总览（主档 + 储值/积分余额 + 可用卡券数 + 累计充值/消费）
     */
#[\erikwang2013\apidoc\annotation\Title("会员总览")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/overview")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]

    public function overview(Request $request): Response
    {
        [$data, $error] = $this->service()->memberOverview(
            $this->decodeMaybe((string) $request->input('member_id', '0'))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->encodeIds($data, ['id', 'customer_id']));
    }

    /**
     * 储值充值
     */
#[\erikwang2013\apidoc\annotation\Title("储值充值")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/recharge")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"string", required:true, desc:"金额(≤2 位小数)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function recharge(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->recharge(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (string) $request->input('amount', ''),
            $adminId,
            trim((string) $request->input('remark', ''))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '充值成功');
    }

    /**
     * 储值消费（biz_id 为调用方业务单号，同号重复消费由服务幂等判拒）
     */
#[\erikwang2013\apidoc\annotation\Title("储值消费")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/consume")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"string", required:true, desc:"金额(≤2 位小数)")]
#[\erikwang2013\apidoc\annotation\Param(name:"biz_id", type:"string", required:true, desc:"业务单号(纯数字)")]

    public function consume(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->consume(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (string) $request->input('amount', ''),
            (string) $request->input('biz_id', ''),
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '消费成功');
    }

    /**
     * 储值退款（冲正原消费；同 biz_id 已退 → 拒绝，部分退款由调用方控累计上限）
     */
#[\erikwang2013\apidoc\annotation\Title("储值退款")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/refund")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"amount", type:"string", required:true, desc:"金额(≤2 位小数)")]
#[\erikwang2013\apidoc\annotation\Param(name:"biz_id", type:"string", required:true, desc:"业务单号(纯数字)")]

    public function refund(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->refund(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (string) $request->input('amount', ''),
            (string) $request->input('biz_id', ''),
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '退款成功');
    }

    /**
     * 积分入账（赚取）
     */
#[\erikwang2013\apidoc\annotation\Title("积分入账")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/points-earn")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"points", type:"int", required:true, desc:"积分数(>0)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function pointsEarn(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->earnPoints(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (int) $request->input('points', 0),
            $adminId,
            trim((string) $request->input('remark', ''))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '积分入账成功');
    }

    /**
     * 积分抵扣（管理端手工扣减；不足整笔拒绝）
     */
#[\erikwang2013\apidoc\annotation\Title("积分抵扣")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/points-consume")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"points", type:"int", required:true, desc:"积分数(>0)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function pointsConsume(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->consumePoints(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (int) $request->input('points', 0),
            $adminId,
            trim((string) $request->input('remark', ''))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '积分抵扣成功');
    }

    /**
     * 积分作废（手工调过期积分）
     */
#[\erikwang2013\apidoc\annotation\Title("积分作废")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/member/points-expire")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"points", type:"int", required:true, desc:"积分数(>0)")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", default:"", desc:"备注")]

    public function pointsExpire(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->expirePoints(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            (int) $request->input('points', 0),
            $adminId,
            trim((string) $request->input('remark', ''))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '积分作废成功');
    }

    /** hashid 优先，兼容直传数字 */
    private function decodeMaybe(string $value): int
    {
        $decoded = $this->decodeIdSafe($value);
        if ($decoded !== null) {
            return $decoded;
        }

        return (int) $value;
    }

    /** 会员服务实例 */
    private function service(): MemberService
    {
        return Container::get(MemberService::class);
    }
}
