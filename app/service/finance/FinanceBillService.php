<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceBankAccount;
use app\model\FinanceBill;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 承兑汇票票据台账服务 — P2 F6（全量 bcmath，禁 float 算术）
 *
 * 边界：票据为资产追踪单据，与 erp_finance_invoice 同款注释 —— 不新增 ARAP
 * 分录、不联动收付款/核销/结算。背书/贴现/托收兑付的到账资金由收付款单或
 * 银行流水进 erp_finance_cash_journal（对账单核销域），本服务只推进票据状态。
 *
 * 状态机（status: 0在库 1已背书 2已贴现 3托收中 4已到期兑付 5已退票）：
 *   direction=1 收票(应收): 0→1背书(endorsee 必填) / 0→2贴现(discount_fee 记录) /
 *     0→3托收(bank_account_id 必填) → 3→4兑付到账；0→5 退票、3→5 托收被拒付退回
 *   direction=2 开票(应付): 0→4 到期解付；0→5 对方退回；背书/贴现/托收禁用
 *   到期约束：背书/贴现/托收须在到期日当天或之前（银行实务，逾期票据拒收）；
 *   到期兑付不自动标记（需银行实际到账确认，逾期未兑付由预警清单暴露）。
 *   退票仅允许票在己方手上(0/3)；已背书/已贴现票已交付对方，退回走新收票登记。
 */
class FinanceBillService
{
    /** 全部转换/登记共用字段校验失败消息（稳定供测试与前端断言） */

    /**
     * 登记票据。receipt 来源关联校验：金额与收款单一致、收款单已审核、一单一票。
     *
     * @return array{0: ?FinanceBill, 1: ?string}
     */
    public function store(array $data): array
    {
        $billNo = trim((string) ($data['bill_no'] ?? ''));
        if ($billNo === '') {
            return [null, '票号必填'];
        }
        $type = (int) ($data['type'] ?? 0);
        $direction = (int) ($data['direction'] ?? 0);
        if (!in_array($type, [1, 2], true)) {
            return [null, '票据类型非法: 仅支持 1=银行承兑 2=商业承兑'];
        }
        if (!in_array($direction, [1, 2], true)) {
            return [null, '票据方向非法: 仅支持 1=收票(应收) 2=开票(应付)'];
        }
        if (FinanceBill::withTrashed()->where('bill_no', $billNo)->exists()) {
            return [null, '票号已存在'];
        }
        $rawAmount = (string) ($data['amount'] ?? '0');
        if (!preg_match('/^-?\d+(\.\d+)?$/', $rawAmount)) {
            return [null, '票面金额非法'];
        }
        $amount = bc_norm($rawAmount);
        if (bccomp($amount, '0', 4) !== 1) {
            return [null, '票面金额必须大于 0'];
        }
        $issueDate = (string) ($data['issue_date'] ?? '');
        $dueDate = (string) ($data['due_date'] ?? '');
        if (!$this->isDate($dueDate)) {
            return [null, '到期日非法'];
        }
        if ($issueDate !== '') {
            if (!$this->isDate($issueDate)) {
                return [null, '出票日期非法'];
            }
            if ($dueDate < $issueDate) {
                return [null, '到期日不能早于出票日期'];
            }
        }
        $sourceType = (string) ($data['source_type'] ?? 'manual');
        $sourceId = (int) ($data['source_id'] ?? 0);
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        if (!in_array($sourceType, ['manual', 'receipt'], true)) {
            return [null, '来源类型非法: 仅支持 manual=手工 receipt=关联收款单'];
        }
        if ($sourceType === 'manual' && $sourceId > 0) {
            return [null, '手工票据不能关联来源单'];
        }
        if ($sourceType === 'receipt' && $direction !== 1) {
            return [null, '开票(应付)票据不能关联收款单'];
        }
        if ($direction === 2 && $bankAccountId > 0) {
            return [null, '开票(应付)票据无需指定托收账户'];
        }
        if (($err = $this->checkReceiptLink($sourceType, $sourceId, $amount)) !== null) {
            return [null, $err];
        }
        // 此处方向必为收票(1)：开票(2)带账户已在上方拦截，仅校验账户存在性
        if ($bankAccountId > 0 && !FinanceBankAccount::find($bankAccountId)) {
            return [null, '托收银行账户不存在'];
        }

        try {
            $bill = DB::transaction(function () use ($data, $billNo, $type, $direction, $amount, $issueDate, $dueDate, $sourceType, $sourceId, $bankAccountId) {
                $bill = new FinanceBill();
                $bill->id = SnowflakeService::generate();
                $bill->bill_no = $billNo;
                $bill->type = $type;
                $bill->direction = $direction;
                $bill->drawer = trim((string) ($data['drawer'] ?? ''));
                $bill->payee = trim((string) ($data['payee'] ?? ''));
                $bill->acceptor = trim((string) ($data['acceptor'] ?? ''));
                $bill->issue_date = $issueDate === '' ? null : $issueDate;
                $bill->due_date = $dueDate;
                $bill->amount = bc_round($amount, 2);
                $bill->bank_account_id = $direction === 1 ? $bankAccountId : 0;
                $bill->status = 0;
                $bill->source_type = $sourceType;
                $bill->source_id = $sourceType === 'receipt' ? $sourceId : 0;
                $bill->remark = trim((string) ($data['remark'] ?? ''));
                $bill->save();

                return $bill;
            });
        } catch (\Throwable $e) {
            return [null, '票据保存失败: ' . $e->getMessage()];
        }

        return [$bill, null];
    }

    /**
     * 更新在库票据基本要素（金额/日期/当事方等；票号与来源不可改）。
     * 关联收款单的应收票据改金额时复检与收款单一致。返回错误或 null。
     */
    public function update(int $billId, array $data): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->status !== 0) {
            return '仅 在库 票据可修改';
        }
        $amount = null;
        if (isset($data['amount'])) {
            if (!preg_match('/^-?\d+(\.\d+)?$/', (string) $data['amount'])) {
                return '票面金额非法';
            }
            $amount = bc_norm($data['amount']);
            if (bccomp($amount, '0', 4) !== 1) {
                return '票面金额必须大于 0';
            }
        }
        $dueDate = isset($data['due_date']) ? (string) $data['due_date'] : null;
        if ($dueDate !== null && !$this->isDate($dueDate)) {
            return '到期日非法';
        }
        $issueDate = array_key_exists('issue_date', $data) ? (string) $data['issue_date'] : null;
        $effectiveDue = $dueDate ?? (string) $bill->due_date;
        if ($issueDate !== null) {
            if ($issueDate !== '' && !$this->isDate($issueDate)) {
                return '出票日期非法';
            }
            if ($issueDate !== '' && $effectiveDue < $issueDate) {
                return '到期日不能早于出票日期';
            }
        }
        if (isset($data['direction']) && (int) $data['direction'] !== (int) $bill->direction) {
            return '票据方向不可修改';
        }
        if ($amount !== null && $bill->source_type === 'receipt' && $bill->source_id > 0) {
            if (($err = $this->checkReceiptLink('receipt', (int) $bill->source_id, $amount, $billId)) !== null) {
                return $err;
            }
        }

        foreach (['drawer', 'payee', 'acceptor', 'remark'] as $f) {
            if (isset($data[$f])) {
                $bill->{$f} = trim((string) $data[$f]);
            }
        }
        if ($dueDate !== null) {
            $bill->due_date = $dueDate;
        }
        if ($issueDate !== null) {
            $bill->issue_date = $issueDate === '' ? null : $issueDate;
        }
        if ($amount !== null) {
            $bill->amount = bc_round($amount, 2);
        }
        $bankAccountId = array_key_exists('bank_account_id', $data) ? (int) $data['bank_account_id'] : null;
        if ($bankAccountId !== null) {
            if ((int) $bill->direction === 2) {
                return '开票(应付)票据无需指定托收账户';
            }
            if ($bankAccountId > 0 && !FinanceBankAccount::find($bankAccountId)) {
                return '托收银行账户不存在';
            }
            $bill->bank_account_id = $bankAccountId;
        }
        $bill->save();

        return null;
    }

    /** 背书转出：0→1（被背书人必填；应收票、未到期） */
    public function endorse(int $billId, string $endorsee): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->status !== 0) {
            return '仅 在库 票据可背书';
        }
        if ((int) $bill->direction !== 1) {
            return '开票(应付)票据不能背书转让';
        }
        if (trim($endorsee) === '') {
            return '被背书人必填';
        }
        if ($bill->due_date < date('Y-m-d')) {
            return '票据已到期，不能背书';
        }
        $bill->status = 1;
        $bill->endorsee = trim($endorsee);
        $bill->endorsed_at = date('Y-m-d H:i:s');
        $bill->save();

        return null;
    }

    /** 贴现：0→2（贴现息记录 0~票面金额之间；应收票、未到期） */
    public function discount(int $billId, string $fee): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->status !== 0) {
            return '仅 在库 票据可贴现';
        }
        if ((int) $bill->direction !== 1) {
            return '开票(应付)票据不能贴现';
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $fee)) {
            return '贴现息非法';
        }
        $fee = bc_norm($fee);
        if (bccomp($fee, '0', 4) === -1 || bccomp($fee, $bill->amount, 4) !== -1) {
            return '贴现息须在 0~票面金额之间';
        }
        if ($bill->due_date < date('Y-m-d')) {
            return '票据已到期，不能贴现';
        }
        $bill->status = 2;
        $bill->discount_fee = bc_round($fee, 2);
        $bill->discounted_at = date('Y-m-d H:i:s');
        $bill->save();

        return null;
    }

    /** 托收：0→3（须指定托收账户；应收票、未到期）。返回错误或 null */
    public function collect(int $billId, int $bankAccountId): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->status !== 0) {
            return '仅 在库 票据可托收';
        }
        if ((int) $bill->direction !== 1) {
            return '开票(应付)票据不能托收';
        }
        $accountId = $bankAccountId > 0 ? $bankAccountId : (int) $bill->bank_account_id;
        if ($accountId <= 0) {
            return '请先指定托收银行账户';
        }
        if (!FinanceBankAccount::find($accountId)) {
            return '托收银行账户不存在';
        }
        if ($bill->due_date < date('Y-m-d')) {
            return '票据已到期，不能托收';
        }
        $bill->status = 3;
        $bill->bank_account_id = $accountId;
        $bill->collected_at = date('Y-m-d H:i:s');
        $bill->save();

        return null;
    }

    /** 到期兑付/解付：收票 3→4（托收到账）、开票 0→4（到期付款）。返回错误或 null */
    public function cash(int $billId): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->direction === 1) {
            if ((int) $bill->status !== 3) {
                return '仅 托收中 票据可确认兑付';
            }
        } elseif ((int) $bill->status !== 0) {
            return '仅 在库 票据可确认解付';
        }
        $bill->status = 4;
        $bill->cashed_at = date('Y-m-d H:i:s');
        $bill->save();

        return null;
    }

    /** 退票：0→5（收票退回/开票对方退回）、3→5（托收被拒付）。返回错误或 null */
    public function reject(int $billId): ?string
    {
        $bill = FinanceBill::find($billId);
        if (!$bill) {
            return '票据不存在';
        }
        if ((int) $bill->status !== 0 && (int) $bill->status !== 3) {
            return '仅 在库/托收中 票据可退票';
        }
        $bill->status = 5;
        $bill->returned_at = date('Y-m-d H:i:s');
        $bill->save();

        return null;
    }

    /** 到期预警清单：未兑付(在库/托收中)且 due_date <= 今天+days（上限 200 条） */
    public function dueWarnings(int $days, int $direction = 0): array
    {
        $cutoff = date('Y-m-d', strtotime('+' . max(0, $days) . ' days'));
        $query = FinanceBill::whereIn('status', [0, 3])->where('due_date', '<=', $cutoff);
        if (in_array($direction, [1, 2], true)) {
            $query->where('direction', $direction);
        }
        $today = date('Y-m-d');
        $list = [];
        foreach ($query->orderBy('due_date')->orderBy('id')->limit(200)->get() as $bill) {
            $row = $bill->toArray();
            $row['due_days'] = (int) ((strtotime($bill->due_date) - strtotime($today)) / 86400);
            $list[] = $row;
        }

        return $list;
    }

    /**
     * receipt 来源校验：单据存在且已审核(1)、金额一致、一单一票未删除。
     * $exceptBillId：更新复检时排除自身（改金额重查不把当前票据当重复关联）。
     */
    private function checkReceiptLink(string $sourceType, int $sourceId, string $amount, int $exceptBillId = 0): ?string
    {
        if ($sourceType !== 'receipt') {
            return null;
        }
        if ($sourceId <= 0) {
            return '关联收款单缺失';
        }
        $receipt = DB::table('erp_finance_receipt')->find($sourceId);
        if (!$receipt || (int) $receipt->status !== 1) {
            return '关联收款单不存在或未审核';
        }
        if (bccomp(bc_norm($receipt->amount), bc_norm($amount), 2) !== 0) {
            return '收票金额须与关联收款单金额一致';
        }
        $linked = FinanceBill::where('source_type', 'receipt')->where('source_id', $sourceId);
        if ($exceptBillId > 0) {
            $linked->where('id', '!=', $exceptBillId);
        }
        if ($linked->exists()) {
            return '该收款单已关联其他票据';
        }

        return null;
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $ts = strtotime($value);

        return $ts !== false && date('Y-m-d', $ts) === $value;
    }
}
