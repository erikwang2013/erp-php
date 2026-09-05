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
 * 会员价值引擎·卡券 — P2-3 C1
 * 发券（模板限量）与核销（归属/过期/已核销判拒）；模板维护为管理端手工建数据。
 * 核销来源 order_source 必填（记调用方单号）。路由注册随批次 lead 关闸（本批不注册）。
 */
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]

class CouponController extends BaseController
{
    /**
     * 发券（模板须启用且有余量；valid_days=0 的券长期有效 expire_at=null）
     */
#[\erikwang2013\apidoc\annotation\Title("会员发券")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/coupon/issue")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"member_id", type:"string", required:true, desc:"会员(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"template_id", type:"string", required:true, desc:"卡券模板(hashid)")]

    public function issue(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->issueCoupon(
            $this->decodeMaybe((string) $request->input('member_id', '0')),
            $this->decodeMaybe((string) $request->input('template_id', '0')),
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $data['coupon_id'] = $this->encodeId((int) $data['coupon_id']);

        return $this->success($data, '发券成功');
    }

    /**
     * 核销卡券（管理端代核销；过期判拒时惰性置 2，已核销/已过期不可再核销）
     */
#[\erikwang2013\apidoc\annotation\Title("卡券核销")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/coupon/redeem")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("会员管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"coupon_id", type:"string", required:true, desc:"卡券(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"order_source", type:"string", required:true, desc:"核销来源单号(≤20)")]

    public function redeem(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        [$data, $error] = $this->service()->redeemCoupon(
            $this->decodeMaybe((string) $request->input('coupon_id', '0')),
            (string) $request->input('order_source', ''),
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($data, '核销成功');
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
