<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceVoucher;
use app\model\FinanceVoucherItem;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 复式记账服务
 *
 * 状态机与 erp_finance_voucher.status 列注释保持一致:
 * 0 = 草稿, 1 = 已审核
 *
 * 账套维度（F1）：ledger_id 为空 = 旧数据默认账套（宽松：不做期间校验）；
 * ledger_id 非空 = 写入/审核前校验账套期间未关账（assertPeriodOpen）。
 */
class DoubleEntryService
{
    private ?LedgerService $ledgerService = null;

    private function ledgerService(): LedgerService
    {
        return $this->ledgerService ??= new LedgerService();
    }

    public function validateBalance(array $items): void
    {
        $totalDebit = '0';
        $totalCredit = '0';
        foreach ($items as $item) {
            $totalDebit = bcadd($totalDebit, bc_norm($item['debit_amount'] ?? 0), 6);
            $totalCredit = bcadd($totalCredit, bc_norm($item['credit_amount'] ?? 0), 6);
        }
        $diff = bc_abs(bcsub($totalDebit, $totalCredit, 6));
        if (bccomp($diff, '0.001', 4) > 0) {
            throw new \RuntimeException(sprintf(
                '借贷不平衡: 借方合计=%.2f, 贷方合计=%.2f, 差额=%.2f',
                $totalDebit,
                $totalCredit,
                $diff
            ));
        }
    }

    public function createVoucher(array $data, array $items, ?int $ledgerId = null): FinanceVoucher
    {
        $this->validateBalance($items);
        if ($ledgerId !== null) {
            $this->ledgerService()->assertPeriodOpen($ledgerId, (string) ($data['voucher_date'] ?? date('Y-m-d')));
        }

        return DB::transaction(function () use ($data, $items, $ledgerId) {
            $voucher = new FinanceVoucher();
            $voucher->id = SnowflakeService::generate();
            $voucher->ledger_id = $ledgerId;
            $voucher->code = $data['code'] ?? ('VCH' . SnowflakeService::generate());
            $voucher->voucher_date = $data['voucher_date'] ?? date('Y-m-d');
            $voucher->remark = (string)($data['remark'] ?? $data['name'] ?? '');
            $voucher->status = 0;
            $voucher->save();
            foreach ($items as $item) {
                $vi = new FinanceVoucherItem();
                $vi->id = SnowflakeService::generate();
                $vi->voucher_id = $voucher->id;
                $vi->account_id = (int)($item['account_id'] ?? $item['account_subject_id'] ?? 0);
                $vi->debit_amount = (float)($item['debit_amount'] ?? 0);
                $vi->credit_amount = (float)($item['credit_amount'] ?? 0);
                $vi->summary = $item['summary'] ?? '';
                $vi->save();
            }

            return $voucher;
        });
    }

    public function audit(int $voucherId): FinanceVoucher
    {
        $voucher = FinanceVoucher::find($voucherId);
        if (!$voucher) {
            throw new \RuntimeException('凭证不存在');
        }
        if ($voucher->status !== 0) {
            throw new \RuntimeException('仅草稿状态的凭证可审核');
        }
        if ($voucher->ledger_id !== null) {
            $this->ledgerService()->assertPeriodOpen((int) $voucher->ledger_id, (string) $voucher->voucher_date);
        }
        $items = FinanceVoucherItem::where('voucher_id', $voucherId)->get()->toArray();
        $this->validateBalance($items);
        $voucher->status = 1;
        $voucher->audited_at = date('Y-m-d H:i:s');
        $voucher->save();

        return $voucher;
    }

    public function reverse(int $voucherId): FinanceVoucher
    {
        $original = FinanceVoucher::find($voucherId);
        if (!$original || $original->status !== 1) {
            throw new \RuntimeException('只能冲销已审核的凭证');
        }
        $exists = FinanceVoucher::where('code', 'REV-' . $original->code)->count();
        if ($exists > 0) {
            throw new \RuntimeException('该凭证已冲销，不能重复冲销');
        }
        $items = FinanceVoucherItem::where('voucher_id', $voucherId)->get()->toArray();
        $reversedItems = array_map(fn ($i) => [
            'account_id' => $i['account_id'],
            'debit_amount' => $i['credit_amount'],
            'credit_amount' => $i['debit_amount'],
            'summary' => '冲销: ' . ($i['summary'] ?? ''),
        ], $items);

        return $this->createVoucher([
            'remark' => '冲销-' . ($original->remark ?? $original->code),
            'code' => 'REV-' . $original->code,
            'voucher_date' => date('Y-m-d'),
        ], $reversedItems, $original->ledger_id !== null ? (int) $original->ledger_id : null);
    }
}
