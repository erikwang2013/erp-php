<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\sales;

use app\model\Customer;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 信用控制执行（F7）：销售订单/发货实时额度拦截。
 *
 * 规则集中在服务层，调用方（OrderController/DeliveryController）只做薄封装：
 *   1) 客户冻结（credit_frozen=1）→ 阻断一切新销售单据；
 *   2) 信用未启用（credit_limit <= 0，存量客户默认 0.00）→ 额度/账期校验整体放行，
 *      但冻结校验仍生效（fail-open 兼容存量数据）；
 *   3) 额度占用 = 未核销应收（已审核未收，status 0/1 余客）+ 在途订单占用
 *      （已审核/部分发货订单 未发货余额），下单校验再加本次订单金额（发货为
 *      占用→应收 1:1 平移，占用内已含本次，不加）；
 *   4) 占用 > 额度×(1+超限比例/100) → CreditControlException(422)；
 *   5) 超期未收（due_date < 今天 且未核销）> 超期容忍上限 → 同样拒绝；
 *   6) credit_days>0 时发货应收带到期日（发货日+账期），供超期校验使用。
 *
 * 金额全部 bcmath（bcadd/bcsub/bcmul/bcdiv/bccomp/bc_round），禁止 float 运算；
 * DECIMAL 经 SUM 由 mysqlnd 以字符串返回，bc_norm 直通，不产生浮点误差。
 */
class CreditControlService
{
    /**
     * 销售订单创建前置拦截。$orderAmount 为本次订单金额（十进制字符串）。
     */
    public function assertOrderCreate(int $customerId, string $orderAmount): void
    {
        $this->guard($customerId, bc_norm($orderAmount), '订单');
    }

    /**
     * 发货/出库前置拦截（在事务外、库存与应收落库前调用）。
     *
     * @return string|null 客户账期到期日 'Y-m-d'（credit_days>0 时），否则 null
     */
    public function assertDeliveryCreate(int $customerId): ?string
    {
        return $this->guard($customerId, '0', '发货');
    }

    /**
     * 统一信用闸门。返回账期到期日（供调用方写入应收 due_date）。
     *
     * @throws CreditControlException 冻结/超期/超限，业务拒绝（调用方转 422）
     */
    private function guard(int $customerId, string $additionalAmount, string $bizLabel): ?string
    {
        if ($customerId <= 0) {
            return null; // 历史脏数据（无有效客户）不拦截
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            return null; // 客户档案已删（软删）或不存在 → fail-open，不阻断历史单据流
        }

        $name = (string) $customer->name;
        if ((int) $customer->credit_frozen === 1) {
            throw new CreditControlException("客户「{$name}」已冻结信用，禁止创建新的销售{$bizLabel}");
        }

        $creditDays = (int) $customer->credit_days;
        $dueDate = $creditDays > 0 ? date('Y-m-d', strtotime("+{$creditDays} days")) : null;

        // 信用未启用（额度 0，存量客户默认）：账期照常写 due_date，但不做占用/超期拦截
        $creditLimit = bc_norm($customer->credit_limit);
        if (bccomp($creditLimit, '0', 2) <= 0) {
            return $dueDate;
        }

        $exposure = $this->exposure($customerId);            // 未核销应收 + 在途订单占用
        $used = bc_round(bcadd($exposure, $additionalAmount, 6), 2);

        // 闸门 1：超期未收余额
        $overdueAmount = bc_round($this->overdueAmount($customerId), 2);
        $overdueCap = bc_norm($customer->credit_overdue_limit_amount);
        if (bccomp($overdueAmount, $overdueCap, 2) > 0) {
            throw new CreditControlException(
                "账期超期拦截：客户「{$name}」超期未收 ¥{$overdueAmount} 超过允许上限 ¥{$overdueCap}，本次{$bizLabel}被拒绝，请先收款核销或调高容忍上限"
            );
        }

        // 闸门 2：额度占用（含允许超限比例）
        $ratio = bc_norm($customer->credit_over_ratio);
        $allowed = bccomp($ratio, '0', 2) > 0
            ? bc_round(bcadd($creditLimit, bcdiv(bcmul($creditLimit, $ratio, 6), '100', 6), 6), 2)
            : $creditLimit;
        if (bccomp($used, $allowed, 2) > 0) {
            throw new CreditControlException(
                "信用额度拦截：客户「{$name}」信用占用 ¥{$used} 超过允许额度 ¥{$allowed}（额度 ¥{$creditLimit}，超限比例 {$ratio}%），本次{$bizLabel}被拒绝，请先收款核销或调高信用额度"
            );
        }

        return $dueDate;
    }

    /**
     * 客户未核销应收余额（type=1 应收，status 0 未核销/1 部分核销，按剩余额计）。
     */
    private function unpaidArAmount(int $customerId): string
    {
        return bc_norm(DB::table('erp_finance_ar_ap')
            ->where('type', 1)
            ->where('partner_id', $customerId)
            ->whereIn('status', [0, 1])
            ->sum(DB::raw('amount - settled_amount')));
    }

    /**
     * 客户超期未收余额（due_date 早于今天且未核销完毕）。
     */
    private function overdueAmount(int $customerId): string
    {
        return bc_norm(DB::table('erp_finance_ar_ap')
            ->where('type', 1)
            ->where('partner_id', $customerId)
            ->whereIn('status', [0, 1])
            ->whereNotNull('due_date')
            ->where('due_date', '<', date('Y-m-d'))
            ->sum(DB::raw('amount - settled_amount')));
    }

    /**
     * 在途订单占用：已审核(1)/部分发货(2)订单的未发货余额
     * （订单总额 - 已出库发货单实发额，下限 0）。发货单与其明细均已软删除感知
     * （发货明细无软删列，参照 DeliveryController 既有口径）。
     */
    private function openOrderOccupancy(int $customerId): string
    {
        return bc_norm(DB::table('erp_sales_order as o')
            ->leftJoinSub(
                DB::table('erp_sales_delivery_item as di')
                    ->select('d.order_id as order_id')
                    ->selectRaw('SUM(di.amount) as delivered')
                    ->join('erp_sales_delivery as d', 'd.id', '=', 'di.delivery_id')
                    ->where('d.status', 1)
                    ->whereNull('d.deleted_at')
                    ->groupBy('d.order_id'),
                'sd',
                'sd.order_id',
                '=',
                'o.id'
            )
            ->where('o.customer_id', $customerId)
            ->whereIn('o.status', [1, 2])
            ->whereNull('o.deleted_at')
            ->sum(DB::raw('GREATEST(COALESCE(o.total_amount, 0) - COALESCE(sd.delivered, 0), 0)')));
    }

    /**
     * 信用占用 = 未核销应收 + 在途订单占用（bc 域求和）。
     */
    private function exposure(int $customerId): string
    {
        return bcadd($this->unpaidArAmount($customerId), $this->openOrderOccupancy($customerId), 6);
    }
}

/**
 * 信用控制业务拒绝（期望调用方转为 HTTP 422，消息为中文业务说明）。
 */
class CreditControlException extends \RuntimeException
{
}
