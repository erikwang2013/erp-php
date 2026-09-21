<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\service\finance\BankReconService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 银企对账(流水导入/自动核销/手工核销/未达报告) — P2 F6
 * 对账目标 = 现金日记账 erp_finance_cash_journal：只写核销匹配轨，不改动日记账。
 * 匹配严格 1:1；批次导入按 (账户,批次) 幂等；同条件两次自动核销结果一致。
 */
#[\erikwang2013\apidoc\annotation\Tag('财务管理')]
#[\erikwang2013\apidoc\annotation\Title('对账单行')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class BankReconController extends BaseController
{
    /** 响应 hashid 字段：对账单/日记账/账户 */
    private const STMT_ID_FIELDS = ['id', 'bank_account_id'];
    private const PAIR_ID_FIELDS = ['statement_id', 'cash_journal_id', 'created_by'];
    private const CAND_ID_FIELDS = ['id', 'statement_id', 'cash_journal_id', 'bank_account_id', 'created_by'];

    /**
     * 对账单行列表（日期范围必填；批次/对账状态筛选）
     */
    #[\erikwang2013\apidoc\annotation\Title('对账单行列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-statement')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from', type:'string', required:true, desc:'起始日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to', type:'string', required:true, desc:'截止日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Param(name:'batch', type:'string', default:'', desc:'导入批次')]
    #[\erikwang2013\apidoc\annotation\Param(name:'matched', type:'int', default:-1, desc:'对账状态(-1全部 0未对账 1已对账)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]

    public function statementIndex(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'string',
            'from' => 'string',
            'to' => 'string',
            'batch' => 'string',
            'matched' => 'integer',
            'page' => 'integer',
            'limit' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request, 15, 100);
        $accountId = $this->optionalId($request->input('bank_account_id', '0'));
        if ($accountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $matched = (int) $request->input('matched', -1);
        [$data, $error] = $this->service()->statementList(
            $accountId,
            (string) $request->input('from', ''),
            (string) $request->input('to', ''),
            trim((string) $request->input('batch', '')),
            $matched === 1 ? 1 : ($matched === 0 ? 0 : -1),
            $page,
            $limit
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $data['list'] = array_map(fn ($row) => $this->encodeIds($row, self::STMT_ID_FIELDS), $data['list']);

        return $this->successPage($data['list'], (int) $data['total'], $page, $limit);
    }

    /**
     * 导入对账单行（整批原子；同账户同批次重复导入整批跳过）
     */
    #[\erikwang2013\apidoc\annotation\Title('导入对账单')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-statement/import')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'batch', type:'string', required:true, desc:'导入批次号(幂等键)')]

    public function import(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'batch' => 'required|string|max:50',
            'rows' => 'required|array|min:1|max:5000',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $accountId = $this->optionalId($request->input('bank_account_id'));
        if ($accountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $result = $this->service()->importStatement(
            $accountId,
            (string) $request->input('batch', ''),
            $request->input('rows', [])
        );
        if ($result[1] !== null) {
            return $this->fail($result[1], 422);
        }
        $msg = (bool) $result[0]['duplicated']
            ? "批次已导入过，本次跳过 {$result[0]['skipped']} 行"
            : "导入成功 {$result[0]['imported']} 行";

        return $this->success($result[0], $msg);
    }

    /**
     * 自动核销（金额+日期窗口±N 天 → 摘要，候选唯一才落库；返回匹配/人工候选/未达清单）
     */
    #[\erikwang2013\apidoc\annotation\Title('自动核销')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-recon/auto')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from', type:'string', required:true, desc:'流水起始日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to', type:'string', required:true, desc:'流水截止日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Param(name:'window_days', type:'int', default:3, desc:'日期容差天数(0~30)')]

    public function auto(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date',
            'window_days' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $accountId = $this->optionalId($request->input('bank_account_id'));
        if ($accountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $result = $this->service()->autoReconcile(
            $accountId,
            (string) $request->input('from'),
            (string) $request->input('to'),
            (int) $request->input('window_days', 3)
        );
        if ($result[1] !== null) {
            return $this->fail($result[1], 422);
        }
        $data = $result[0];
        $data['matched'] = array_map(fn ($row) => $this->encodeIds($row, self::PAIR_ID_FIELDS), $data['matched']);
        foreach ($data['manual_candidates'] as &$cand) {
            $cand = $this->encodeIds($cand, ['statement_id']);
            $cand['journals'] = array_map(fn ($j) => $this->encodeIds($j, ['id']), $cand['journals']);
        }
        unset($cand);
        $data['unmatched_journals'] = array_map(fn ($j) => $this->encodeIds($j, ['id']), $data['unmatched_journals']);

        return $this->success($data, $this->trans('Automatic write-off completed'));
    }

    /**
     * 手工核销（金额与方向必须一致）
     */
    #[\erikwang2013\apidoc\annotation\Title('手工核销')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-recon/manual')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'statement_id', type:'string', required:true, desc:'对账单行(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'cash_journal_id', type:'string', required:true, desc:'日记账行(hashid)')]

    public function manual(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'statement_id' => 'required',
            'cash_journal_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $adminId = $request->adminId ?? 0;
        $accountId = $this->optionalId($request->input('bank_account_id'));
        $statementId = $this->optionalId($request->input('statement_id'));
        $journalId = $this->optionalId($request->input('cash_journal_id'));
        if ($accountId === null || $statementId === null || $journalId === null) {
            return $this->fail($this->trans('Invalid bank account/statement/journal ID'), 422);
        }
        $error = $this->service()->manualReconcile(
            $accountId,
            $statementId,
            $journalId,
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, $this->trans('Written off successfully'));
    }

    /**
     * 取消核销
     */
    #[\erikwang2013\apidoc\annotation\Title('取消核销')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-recon/unreconcile')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'statement_id', type:'string', required:true, desc:'对账单行(hashid)')]

    public function unreconcile(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'statement_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $accountId = $this->optionalId($request->input('bank_account_id'));
        $statementId = $this->optionalId($request->input('statement_id'));
        if ($accountId === null || $statementId === null) {
            return $this->fail($this->trans('Invalid bank account/statement ID'), 422);
        }
        $error = $this->service()->unreconcile($accountId, $statementId);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, $this->trans('Write-off cancelled successfully'));
    }

    /**
     * 对账报告（已对清单 + 双方未达清单 + 分向汇总）
     */
    #[\erikwang2013\apidoc\annotation\Title('对账报告')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/bank-recon/report')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'bank_account_id', type:'string', required:true, desc:'银行账户(hashid)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from', type:'string', required:true, desc:'起始日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to', type:'string', required:true, desc:'截止日期 Y-m-d')]

    public function report(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'string',
            'from' => 'string',
            'to' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $accountId = $this->optionalId($request->input('bank_account_id', '0'));
        if ($accountId === null) {
            return $this->fail($this->trans('Invalid bank account ID'), 422);
        }
        $result = $this->service()->reconReport(
            $accountId,
            (string) $request->input('from', ''),
            (string) $request->input('to', '')
        );
        if ($result[1] !== null) {
            return $this->fail($result[1], 422);
        }
        $data = $result[0];
        $data['matched'] = array_map(fn ($row) => $this->encodeIds($row, self::CAND_ID_FIELDS), $data['matched']);
        $data['unmatched_statements'] = array_map(fn ($row) => $this->encodeIds($row, self::STMT_ID_FIELDS), $data['unmatched_statements']);
        $data['unmatched_journals'] = array_map(fn ($row) => $this->encodeIds($row, ['id', 'source_id']), $data['unmatched_journals']);

        return $this->success($data);
    }

    /**
     * 可选外键入参：缺省/null/空串 → 0（无关联哨兵）；非空 → decodeFlexibleId，
     * 解不出（垃圾串/数组）→ null 由调用方 422。
     * 不用 `decodeIdSafe($v) ?? (int)$v`：hashids 会把某些纯数字串（'410000000000000402'）
     * 解成 PHP_INT_MAX，`(int)` 兜底又会让 'abc' 静默变 0 → 查/写错账户。
     */
    private function optionalId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }

        return $this->decodeFlexibleId($raw);
    }

    /**
     * 对账服务实例
     */
    private function service(): BankReconService
    {
        return Container::get(BankReconService::class);
    }
}
