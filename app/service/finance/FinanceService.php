<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceArAp;
use app\model\FinanceBankAccount;
use app\model\FinanceCashJournal;
use app\model\FinancePayment;
use app\model\FinanceReceipt;
use app\model\FinanceSettlement;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

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
        $this->saveUniqueArAp($ar, "应收记录已存在: {$sourceType}#{$sourceId}");

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
        $this->saveUniqueArAp($ap, "应付记录已存在: {$sourceType}#{$sourceId}");

        return $ap->id;
    }

    /**
     * 保存应收应付记录，唯一索引(uk_source)冲突时转业务异常而非 SQL 500
     */
    private function saveUniqueArAp(FinanceArAp $model, string $duplicateMessage): void
    {
        try {
            $model->save();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) {
                throw new \RuntimeException($duplicateMessage);
            }
            throw $e;
        }
    }

    /**
     * 收款核销应收
     */
    public function settleReceipt(int $receiptId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($receiptId, $arApId, $amount) {
            $arAp = FinanceArAp::where('id', $arApId)->lockForUpdate()->firstOrFail();
            if ($arAp->type !== 1) {
                throw new \RuntimeException('核销对象不是应收记录');
            }
            if ($amount <= 0) {
                throw new \InvalidArgumentException('核销金额必须大于0');
            }
            $this->assertReceiptPaymentUsable(1, $receiptId, $arAp->partner_id, $amount);

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
     * 校验收/付款单可核销：存在、已审核、归属一致、剩余可核销额充足。
     * $type: 1=收款单（核销应收） 2=付款单（核销应付）
     */
    private function assertReceiptPaymentUsable(int $type, int $id, int $partnerId, float $amount): void
    {
        $doc = $type === 1 ? FinanceReceipt::find($id) : FinancePayment::find($id);
        $label = $type === 1 ? '收款单' : '付款单';
        if (!$doc) {
            throw new \RuntimeException("{$label}不存在");
        }
        if ((int) $doc->status !== 1) {
            throw new \RuntimeException("{$label}未审核，不可核销");
        }
        if ((int) ($type === 1 ? $doc->customer_id : $doc->supplier_id) !== $partnerId) {
            throw new \RuntimeException("{$label}与核销对象归属不一致");
        }
        $used = (float) FinanceSettlement::where('receipt_payment_id', $id)->sum('amount');
        if (($used + $amount) > (float) $doc->amount) {
            throw new \RuntimeException("核销金额超出{$label}剩余可核销额");
        }
    }

    /**
     * 付款核销应付
     */
    public function settlePayment(int $paymentId, int $arApId, float $amount): void
    {
        DB::transaction(function () use ($paymentId, $arApId, $amount) {
            $arAp = FinanceArAp::where('id', $arApId)->lockForUpdate()->firstOrFail();
            if ($arAp->type !== 2) {
                throw new \RuntimeException('核销对象不是应付记录');
            }
            if ($amount <= 0) {
                throw new \InvalidArgumentException('核销金额必须大于0');
            }
            $this->assertReceiptPaymentUsable(2, $paymentId, $arAp->partner_id, $amount);

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
            $bankAccountId,
            $direction,
            $amount,
            $sourceType,
            $sourceId,
            $summary
        ) {
            $account = FinanceBankAccount::where('id', $bankAccountId)->lockForUpdate()->firstOrFail();

            if (!in_array($direction, [1, 2], true)) {
                throw new \InvalidArgumentException('direction 非法: 仅支持 1=收入, 2=支出');
            }
            if ($direction === 1) {
                $account->balance += $amount;
            } else {
                if ($account->balance < $amount) {
                    throw new \RuntimeException('账户余额不足');
                }
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
