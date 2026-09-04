<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\finance;

use app\common\SnowflakeService;
use app\model\FinanceBankAccount;
use app\model\FinanceBankReconMatch;
use app\model\FinanceBankStatement;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 银企对账服务 — P2 F6（全量 bcmath，禁 float 算术）
 *
 * 对账目标 = erp_finance_cash_journal 现金日记账行（方向口径同表：
 * 1=收入 2=支出；金额同列）。只写 erp_finance_bank_recon_match 匹配轨，
 * 绝不改动日记账/账户余额/收付款单。
 *
 * 匹配规则（确定性，同条件两次执行结果一致——循环按 日期,id 升序）：
 *   1) 自动-金额日期(±N 天可配，默认 3)：金额相等 + 方向一致 + 日期窗口内。
 *      候选唯一才落库；多个候选 → manual_candidates 人工清单，不猜配。
 *   2) 自动-摘要：金额+方向一致 且 摘要含对方户名或流水号（按序取首个命中
 *      非空关键词）；候选唯一才落库，多候选同样进人工清单。
 *   3) 手工：金额、方向须一致（防止错配），日期不限。
 * 匹配严格 1:1（uk_statement/uk_journal 硬约束）；取消核销 = 删除匹配轨。
 * 幂等：import_batch 同账户已存在 → 整批跳过不落行。
 * ponytail: 单账户并发对账由唯一键兜底抛错，财务单用户场景足够；
 *           数据量大时改游标分批，当前整窗口载入内存。
 */
class BankReconService
{
    public const MATCH_AUTO_DATE = 1;
    public const MATCH_AUTO_SUMMARY = 2;
    public const MATCH_MANUAL = 3;

    /**
     * 导入对账单行（整批校验，任一行非法整批拒绝；批次重复整批跳过）。
     *
     * @return array{0: ?array, 1: ?string} 结果或错误信息
     */
    public function importStatement(int $accountId, string $batch, array $rows): array
    {
        if (!$this->accountExists($accountId)) {
            return [null, '银行账户不存在'];
        }
        $batch = trim($batch);
        if ($batch === '' || mb_strlen($batch) > 50) {
            return [null, '导入批次号必填且不超过 50 字符'];
        }
        $exists = FinanceBankStatement::where('bank_account_id', $accountId)
            ->where('import_batch', $batch)->count();
        if ($exists > 0) {
            return [['imported' => 0, 'skipped' => $exists, 'duplicated' => true], null];
        }
        if (empty($rows)) {
            return [null, '导入行不能为空'];
        }
        $normalized = [];
        foreach ($rows as $i => $row) {
            $no = (int) $i + 1;
            $date = (string) ($row['stmt_date'] ?? '');
            if (!$this->isDate($date)) {
                return [null, "第 {$no} 行交易日期非法"];
            }
            $direction = (int) ($row['direction'] ?? 0);
            if (!in_array($direction, [1, 2], true)) {
                return [null, "第 {$no} 行方向非法: 仅支持 1=收入 2=支出"];
            }
            $rawAmount = (string) ($row['amount'] ?? '0');
            if (!preg_match('/^-?\d+(\.\d+)?$/', $rawAmount)) {
                return [null, "第 {$no} 行发生额非法"];
            }
            $amount = bc_norm($rawAmount);
            if (bccomp($amount, '0', 4) !== 1) {
                return [null, "第 {$no} 行发生额必须大于 0"];
            }
            $counterparty = trim((string) ($row['counterparty'] ?? ''));
            $reference = trim((string) ($row['reference'] ?? ''));
            if (mb_strlen($counterparty) > 200 || mb_strlen($reference) > 200) {
                return [null, "第 {$no} 行对方户名/摘要超长(200)"];
            }
            $balanceAfter = $row['balance_after'] ?? null;
            if ($balanceAfter !== null && $balanceAfter !== '' && !is_numeric($balanceAfter)) {
                return [null, "第 {$no} 行交易后余额非法"];
            }
            $normalized[] = [
                'stmt_date' => $date,
                'direction' => $direction,
                'amount' => bc_round($amount, 2),
                'counterparty' => $counterparty,
                'reference' => $reference,
                'balance_after' => $balanceAfter === null || $balanceAfter === '' ? null : bc_round(bc_norm($balanceAfter), 2),
            ];
        }
        try {
            DB::transaction(function () use ($accountId, $batch, $normalized): void {
                foreach ($normalized as $row) {
                    $stmt = new FinanceBankStatement();
                    $stmt->id = SnowflakeService::generate();
                    $stmt->bank_account_id = $accountId;
                    $stmt->stmt_date = $row['stmt_date'];
                    $stmt->direction = $row['direction'];
                    $stmt->amount = $row['amount'];
                    $stmt->counterparty = $row['counterparty'];
                    $stmt->reference = $row['reference'];
                    $stmt->balance_after = $row['balance_after'];
                    $stmt->import_batch = $batch;
                    $stmt->save();
                }
            });
        } catch (\Throwable $e) {
            return [null, '导入失败: ' . $e->getMessage()];
        }

        return [['imported' => count($normalized), 'skipped' => 0, 'duplicated' => false], null];
    }

    /**
     * 对账单行分页列表（date 起止 + 批次 + 对账状态筛选；状态 -1=全部 0=未对账 1=已对账）。
     *
     * @return array{0: ?array, 1: ?string} ['list'=>[], 'total'=>int] 或错误
     */
    public function statementList(int $accountId, string $from, string $to, string $batch = '', int $matched = -1, int $page = 1, int $limit = 15): array
    {
        if (!$this->accountExists($accountId)) {
            return [null, '银行账户不存在'];
        }
        if (!$this->isDate($from) || !$this->isDate($to) || $from > $to) {
            return [null, '日期范围非法'];
        }
        $query = FinanceBankStatement::where('bank_account_id', $accountId)
            ->whereBetween('stmt_date', [$from, $to]);
        if ($batch !== '') {
            $query->where('import_batch', $batch);
        }
        if ($matched === 1) {
            $query->whereExists(function ($q) use ($accountId): void {
                $q->select(DB::raw(1))->from('erp_finance_bank_recon_match')
                    ->whereColumn('statement_id', 'erp_finance_bank_statement.id')
                    ->where('bank_account_id', $accountId);
            });
        } elseif ($matched === 0) {
            $query->whereNotExists(function ($q) use ($accountId): void {
                $q->select(DB::raw(1))->from('erp_finance_bank_recon_match')
                    ->whereColumn('statement_id', 'erp_finance_bank_statement.id')
                    ->where('bank_account_id', $accountId);
            });
        }
        $total = $query->count();

        return [['list' => $query->orderBy('stmt_date')->orderBy('id')
            ->offset((max(1, $page) - 1) * $limit)->limit($limit)->get()->toArray(), 'total' => $total], null];
    }

    /**
     * 自动核销：金额+日期窗口优先，摘要次之；唯一候选才自动落库。
     * 返回 {matched[], manual_candidates[], unmatched_journals[]}（落库整体事务，失败回滚全批）。
     * 无候选的对账单行以 manual_candidates 内 journals=[] 呈现，即银行未达。
     *
     * @return array{0: ?array, 1: ?string}
     */
    public function autoReconcile(int $accountId, string $from, string $to, int $windowDays = 3): array
    {
        if (!$this->accountExists($accountId)) {
            return [null, '银行账户不存在'];
        }
        if (!$this->isDate($from) || !$this->isDate($to) || $from > $to) {
            return [null, '日期范围非法'];
        }
        if ($windowDays < 0 || $windowDays > 30) {
            return [null, '日期窗口须在 0~30 天之间'];
        }
        $statements = FinanceBankStatement::where('bank_account_id', $accountId)
            ->whereBetween('stmt_date', [$from, $to])->whereNotExists(function ($q) use ($accountId): void {
                $q->select(DB::raw(1))->from('erp_finance_bank_recon_match')
                    ->whereColumn('statement_id', 'erp_finance_bank_statement.id')
                    ->where('bank_account_id', $accountId);
            })->orderBy('stmt_date')->orderBy('id')->get()->all();
        $pool = $this->unmatchedJournals($accountId, $from, $to, $windowDays);
        if (empty($statements)) {
            return [['matched' => [], 'manual_candidates' => [], 'unmatched_statements' => [], 'unmatched_journals' => $pool], null];
        }
        try {
            $data = DB::transaction(function () use ($statements, $pool, $accountId, $windowDays): array {
                $usedJ = [];
                $matched = [];
                $manual = [];
                $leftover = [];
                foreach ($statements as $stmt) {
                    $cands = $this->journalCandidates($pool, $usedJ, $stmt->amount, (int) $stmt->direction, $stmt->stmt_date, $windowDays);
                    if (count($cands) === 1) {
                        $err = $this->persistMatch($accountId, (int) $stmt->id, $cands[0]['id'], self::MATCH_AUTO_DATE);
                        if ($err !== null) {
                            throw new \RuntimeException($err);
                        }
                        $usedJ[] = $cands[0]['id'];
                        $matched[] = $this->pairRow($stmt, $cands[0], self::MATCH_AUTO_DATE);
                    } elseif (count($cands) > 1) {
                        $manual[] = $this->manualRow($stmt, $cands);
                    } else {
                        $leftover[] = $stmt;
                    }
                }
                // 次优先：摘要含对方户名/流水号。放宽日期约束（银行到账日与记账日可错位），
                // 在全未用池内按 金额+方向+摘要 过滤；命中唯一才自动，多候选进人工清单
                foreach ($leftover as $stmt) {
                    $keywords = array_values(array_filter([
                        (string) $stmt->counterparty !== '' ? (string) $stmt->counterparty : '',
                        (string) $stmt->reference !== '' ? (string) $stmt->reference : '',
                    ]));
                    $hit = false;
                    foreach ($keywords as $kw) {
                        $cands = array_values(array_filter($pool, function (array $j) use ($usedJ, $stmt, $kw): bool {
                            if (in_array($j['id'], $usedJ, true)) {
                                return false;
                            }
                            if ((int) $j['direction'] !== (int) $stmt->direction) {
                                return false;
                            }
                            if (bccomp(bc_norm($j['amount']), bc_norm($stmt->amount), 2) !== 0) {
                                return false;
                            }

                            return mb_strpos((string) $j['summary'], $kw) !== false;
                        }));
                        if (count($cands) === 1) {
                            $err = $this->persistMatch($accountId, (int) $stmt->id, $cands[0]['id'], self::MATCH_AUTO_SUMMARY);
                            if ($err !== null) {
                                throw new \RuntimeException($err);
                            }
                            $usedJ[] = $cands[0]['id'];
                            $matched[] = $this->pairRow($stmt, $cands[0], self::MATCH_AUTO_SUMMARY);
                            $hit = true;
                            break;
                        }
                        if (count($cands) > 1) {
                            $manual[] = $this->manualRow($stmt, $cands);
                            $hit = true;
                            break;
                        }
                    }
                    if (!$hit) {
                        $manual[] = $this->manualRow($stmt, []);
                    }
                }

                return [
                    'matched' => $matched,
                    'manual_candidates' => $manual,
                    'unmatched_journals' => array_values(array_filter($pool, fn (array $j) => !in_array($j['id'], $usedJ, true))),
                ];
            });
        } catch (\Throwable $e) {
            return [null, '自动核销失败: ' . $e->getMessage()];
        }

        return [$data, null];
    }

    /** 手工核销：金额与方向必须一致（日期不限）。返回错误或 null */
    public function manualReconcile(int $accountId, int $statementId, int $journalId, int $operatorId): ?string
    {
        $stmt = FinanceBankStatement::where('bank_account_id', $accountId)->find($statementId);
        if (!$stmt) {
            return '对账单行不存在';
        }
        $journal = DB::table('erp_finance_cash_journal')
            ->where('bank_account_id', $accountId)->find($journalId);
        if (!$journal) {
            return '日记账行不存在或不属于该银行账户';
        }
        if (bccomp(bc_norm($stmt->amount), bc_norm($journal->amount), 2) !== 0) {
            return '流水金额与日记账金额不一致，不能核销';
        }
        if ((int) $stmt->direction !== (int) $journal->direction) {
            return '收支方向不一致，不能核销';
        }
        if (FinanceBankReconMatch::where('bank_account_id', $accountId)->where('statement_id', $statementId)->exists()) {
            return '该对账单行已核销';
        }
        if (FinanceBankReconMatch::where('bank_account_id', $accountId)->where('cash_journal_id', $journalId)->exists()) {
            return '该日记账行已核销';
        }

        return $this->persistMatch($accountId, $statementId, $journalId, self::MATCH_MANUAL, $operatorId);
    }

    /** 取消核销（删除匹配轨；1:1 约束保证两侧同步释放）。返回错误或 null */
    public function unreconcile(int $accountId, int $statementId): ?string
    {
        $match = FinanceBankReconMatch::where('bank_account_id', $accountId)
            ->where('statement_id', $statementId)->first();
        if (!$match) {
            return '核销记录不存在';
        }
        $match->delete();

        return null;
    }

    /** 对账报告：已对清单 + 双方未达清单 + 分向汇总（余额调节表素材）。返回 [结果, 错误] */
    public function reconReport(int $accountId, string $from, string $to): array
    {
        if (!$this->accountExists($accountId)) {
            return [null, '银行账户不存在'];
        }
        if (!$this->isDate($from) || !$this->isDate($to) || $from > $to) {
            return [null, '日期范围非法'];
        }
        $matched = DB::table('erp_finance_bank_recon_match as m')
            ->join('erp_finance_bank_statement as s', 's.id', '=', 'm.statement_id')
            ->join('erp_finance_cash_journal as j', 'j.id', '=', 'm.cash_journal_id')
            ->where('m.bank_account_id', $accountId)
            ->whereBetween('s.stmt_date', [$from, $to])
            ->orderBy('s.stmt_date')->orderBy('s.id')
            ->get(['m.statement_id', 'm.cash_journal_id', 'm.match_type', 'm.created_by',
                's.stmt_date', 'j.journal_date', 's.direction', 's.amount', 's.counterparty', 's.reference', 'j.summary'])
            ->all();
        $stmtUnmatched = DB::table('erp_finance_bank_statement')
            ->where('bank_account_id', $accountId)
            ->whereBetween('stmt_date', [$from, $to])->whereNotExists(function ($q) use ($accountId): void {
                $q->select(DB::raw(1))->from('erp_finance_bank_recon_match')
                    ->whereColumn('statement_id', 'erp_finance_bank_statement.id')
                    ->where('bank_account_id', $accountId);
            })->orderBy('stmt_date')->orderBy('id')
            ->get(['id', 'bank_account_id', 'stmt_date', 'direction', 'amount', 'counterparty', 'reference', 'balance_after', 'import_batch'])
            ->all();
        $journalUnmatched = $this->unmatchedJournals($accountId, $from, $to);

        return [[
            'matched' => array_map(fn ($r) => (array) $r, $matched),
            'unmatched_statements' => array_map(fn ($r) => (array) $r, $stmtUnmatched),
            'unmatched_journals' => $journalUnmatched,
            'summary' => [
                'matched' => $this->sumByDirection($matched, 'amount'),
                'unmatched_stmt' => $this->sumByDirection($stmtUnmatched, 'amount'),
                'unmatched_journal' => $this->sumByDirection($journalUnmatched, 'amount'),
            ],
        ], null];
    }

    /** 未对账日记账池：bank_account_id + journal_date 窗口内 + 未核销，按 日期,id 升序 */
    private function unmatchedJournals(int $accountId, string $from, string $to, int $windowDays = 0): array
    {
        $start = $windowDays > 0 ? date('Y-m-d', strtotime("{$from} -{$windowDays} days")) : $from;
        $end = $windowDays > 0 ? date('Y-m-d', strtotime("{$to} +{$windowDays} days")) : $to;

        return DB::table('erp_finance_cash_journal')
            ->where('bank_account_id', $accountId)
            ->whereBetween('journal_date', [$start, $end])
            ->whereNotExists(function ($q) use ($accountId): void {
                $q->select(DB::raw(1))->from('erp_finance_bank_recon_match')
                    ->whereColumn('cash_journal_id', 'erp_finance_cash_journal.id')
                    ->where('bank_account_id', $accountId);
            })->orderBy('journal_date')->orderBy('id')
            ->get(['id', 'journal_date', 'direction', 'amount', 'summary', 'source_type', 'source_id'])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** 金额+方向+日期窗口候选（未使用池内）。确定性：journal_date,id 升序自然稳定 */
    private function journalCandidates(array $pool, array $usedJ, string $amount, int $direction, string $stmtDate, int $windowDays): array
    {
        $start = date('Y-m-d', strtotime("{$stmtDate} -{$windowDays} days"));
        $end = date('Y-m-d', strtotime("{$stmtDate} +{$windowDays} days"));
        $cands = [];
        foreach ($pool as $j) {
            if (in_array($j['id'], $usedJ, true)) {
                continue;
            }
            if ((int) $j['direction'] !== $direction) {
                continue;
            }
            if (bccomp(bc_norm($j['amount']), bc_norm($amount), 2) !== 0) {
                continue;
            }
            if ($j['journal_date'] < $start || $j['journal_date'] > $end) {
                continue;
            }
            $cands[] = $j;
        }

        return $cands;
    }

    /** 匹配对展示行（两侧日期+摘要齐备，供前端/报告直出） */
    private function pairRow(object $stmt, array $journal, int $matchType): array
    {
        return [
            'statement_id' => (int) $stmt->id,
            'cash_journal_id' => (int) $journal['id'],
            'match_type' => $matchType,
            'stmt_date' => (string) $stmt->stmt_date,
            'journal_date' => (string) $journal['journal_date'],
            'direction' => (int) $stmt->direction,
            'amount' => bc_norm($stmt->amount),
            'counterparty' => (string) $stmt->counterparty,
            'reference' => (string) $stmt->reference,
            'summary' => (string) $journal['summary'],
        ];
    }

    /** 人工候选行：候选日记账列表（空 = 无候选，进未达清单） */
    private function manualRow(object $stmt, array $cands): array
    {
        $journals = array_map(fn (array $j) => [
            'id' => $j['id'],
            'journal_date' => $j['journal_date'],
            'amount' => $j['amount'],
            'summary' => $j['summary'],
        ], $cands);

        return [
            'statement_id' => (int) $stmt->id,
            'stmt_date' => (string) $stmt->stmt_date,
            'direction' => (int) $stmt->direction,
            'amount' => bc_norm($stmt->amount),
            'counterparty' => (string) $stmt->counterparty,
            'reference' => (string) $stmt->reference,
            'journals' => $journals,
        ];
    }

    /** 落核销轨；唯一键冲突(并发/重复) → 返回错误 */
    private function persistMatch(int $accountId, int $statementId, int $journalId, int $matchType, int $operatorId = 0): ?string
    {
        try {
            $match = new FinanceBankReconMatch();
            $match->id = SnowflakeService::generate();
            $match->bank_account_id = $accountId;
            $match->statement_id = $statementId;
            $match->cash_journal_id = $journalId;
            $match->match_type = $matchType;
            $match->created_by = $operatorId;
            $match->save();
        } catch (\Throwable $e) {
            return '核销记录已存在或写入失败: ' . $e->getMessage();
        }

        return null;
    }

    /** 按方向合计（rows 为对象或数组，取 amount 键） */
    private function sumByDirection(array $rows, string $amountKey): array
    {
        $in = $out = '0';
        foreach ($rows as $r) {
            $amount = is_array($r) ? $r[$amountKey] : $r->{$amountKey};
            if ((int) (is_array($r) ? $r['direction'] : $r->direction) === 1) {
                $in = bcadd($in, bc_norm($amount), 4);
            } else {
                $out = bcadd($out, bc_norm($amount), 4);
            }
        }

        return ['in' => bc_round($in, 2), 'out' => bc_round($out, 2)];
    }

    private function accountExists(int $accountId): bool
    {
        return FinanceBankAccount::find($accountId) !== null;
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
