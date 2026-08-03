<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceArAp;
use app\model\FinanceSettlement;
use app\model\FinanceCashJournal;
use app\model\FinanceBankAccount;
use Illuminate\Database\Capsule\Manager as DB;

class FinanceService
{
    /**
     * 生成应收记录（销售发货后调用）
     */
    public function createAr(
        int $customerId,
        string $sourceType,
        int $sourceId,
        float $amount,
        ?string $dueDate = null
    ): int {
        $existing = FinanceArAp::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
        if ($existing) {
            throw new \RuntimeException("应收记录已存在: {$sourceType}#{$sourceId}");
        }

        $ar = new FinanceArAp();
        $ar->id = SnowflakeService::generate();
        $ar->type = 1;
        $ar->partner_id = $customerId;
        $ar->source_type = $sourceType;
        $ar->source_id = $sourceId;
        $ar->amount = $amount;
        $ar->settled_amount = 0;
        $ar->status = 0;
        $ar->due_date = $dueDate;
        $ar->save();
        return $ar->id;
    }

    /**
     * 生成应付记录（采购收货后调用）
     */
    public function createAp(
        int $supplierId,
        string $sourceType,
        int $sourceId,
        float $amount,
        ?string $dueDate = null
    ): int {
        $existing = FinanceArAp::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
        if ($existing) {
            throw new \RuntimeException("应付记录已存在: {$sourceType}#{$sourceId}");
        }

        $ap = new FinanceArAp();
        $ap->id = SnowflakeService::generate();
        $ap->type = 2;
        $ap->partner_id = $supplierId;
        $ap->source_type = $sourceType;
        $ap->source_id = $sourceId;
        $ap->amount = $amount;
        $ap->settled_amount = 0;
        $ap->status = 0;
        $ap->due_date = $dueDate;
        $ap->save();
        return $ap->id;
    }

    /**
     * 收款核销应收
     */
    public function settleReceipt(int $receiptId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($receiptId, $arApId, $amount) {
            $arAp = FinanceArAp::findOrFail($arApId);
            if ($arAp->type !== 1) {
                throw new \RuntimeException('核销对象不是应收记录');
            }

            $remain = $arAp->amount - $arAp->settled_amount;
            if ($amount > $remain) {
                throw new \RuntimeException("核销金额({$amount})超出未核销余额({$remain})");
            }

            $arAp->settled_amount += $amount;
            $arAp->status = ($arAp->settled_amount >= $arAp->amount) ? 2 : 1;
            $arAp->save();

            $settlement = new FinanceSettlement();
            $settlement->id = SnowflakeService::generate();
            $settlement->ar_ap_id = $arApId;
            $settlement->receipt_payment_id = $receiptId;
            $settlement->type = 1;
            $settlement->amount = $amount;
            $settlement->settled_at = date('Y-m-d H:i:s');
            $settlement->save();
        });
    }

    /**
     * 付款核销应付
     */
    public function settlePayment(int $paymentId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($paymentId, $arApId, $amount) {
            $arAp = FinanceArAp::findOrFail($arApId);
            if ($arAp->type !== 2) {
                throw new \RuntimeException('核销对象不是应付记录');
            }

            $remain = $arAp->amount - $arAp->settled_amount;
            if ($amount > $remain) {
                throw new \RuntimeException("核销金额({$amount})超出未核销余额({$remain})");
            }

            $arAp->settled_amount += $amount;
            $arAp->status = ($arAp->settled_amount >= $arAp->amount) ? 2 : 1;
            $arAp->save();

            $settlement = new FinanceSettlement();
            $settlement->id = SnowflakeService::generate();
            $settlement->ar_ap_id = $arApId;
            $settlement->receipt_payment_id = $paymentId;
            $settlement->type = 2;
            $settlement->amount = $amount;
            $settlement->settled_at = date('Y-m-d H:i:s');
            $settlement->save();
        });
    }

    /**
     * 更新现金银行日记账
     */
    public function recordJournal(
        int $bankAccountId,
        int $direction,
        float $amount,
        string $sourceType,
        int $sourceId,
        string $summary
    ): void {
        DB::transaction(function () use (
            $bankAccountId, $direction, $amount, $sourceType, $sourceId, $summary
        ) {
            $account = FinanceBankAccount::findOrFail($bankAccountId);

            if ($direction === 1) {
                $account->balance += $amount;
            } else {
                $account->balance -= $amount;
            }
            $account->save();

            $journal = new FinanceCashJournal();
            $journal->id = SnowflakeService::generate();
            $journal->bank_account_id = $bankAccountId;
            $journal->direction = $direction;
            $journal->amount = $amount;
            $journal->balance = $account->balance;
            $journal->source_type = $sourceType;
            $journal->source_id = $sourceId;
            $journal->summary = $summary;
            $journal->journal_date = date('Y-m-d');
            $journal->save();
        });
    }
}
