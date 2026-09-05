<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\service\finance\BankReconService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 银企对账(流水导入/自动核销/手工核销/未达报告) — P2 F6
 * 对账目标 = 现金日记账 erp_finance_cash_journal：只写核销匹配轨，不改动日记账。
 * 匹配严格 1:1；批次导入按 (账户,批次) 幂等；同条件两次自动核销结果一致。
 * @Apidoc\Tag("财务管理")
 */#[Apidoc\Tag("财务管理")]

class BankReconController extends BaseController
{
    /** 响应 hashid 字段：对账单/日记账/账户 */
    private const STMT_ID_FIELDS = ['id', 'bank_account_id'];
    private const PAIR_ID_FIELDS = ['statement_id', 'cash_journal_id', 'created_by'];
    private const CAND_ID_FIELDS = ['id', 'statement_id', 'cash_journal_id', 'bank_account_id', 'created_by'];

    /**
     * 对账单行列表（日期范围必填；批次/对账状态筛选）
     * @Apidoc\Title("对账单行列表")
     * @Apidoc\Url("/admin/v1/finance/bank-statement")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="from", type="string", required=true, desc="起始日期 Y-m-d")
     * @Apidoc\Param(name="to", type="string", required=true, desc="截止日期 Y-m-d")
     * @Apidoc\Param(name="batch", type="string", default="", desc="导入批次")
     * @Apidoc\Param(name="matched", type="int", default=-1, desc="对账状态(-1全部 0未对账 1已对账)")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     */#[Apidoc\Title("对账单行列表")]
#[Apidoc\Url("/admin/v1/finance/bank-statement")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"from", type:"string", required:true, desc:"起始日期 Y-m-d")]
#[Apidoc\Param(name:"to", type:"string", required:true, desc:"截止日期 Y-m-d")]
#[Apidoc\Param(name:"batch", type:"string", default:"", desc:"导入批次")]
#[Apidoc\Param(name:"matched", type:"int", default:-1, desc:"对账状态(-1全部 0未对账 1已对账)")]
#[Apidoc\Param(name:"page", type:"int", default:1, desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", default:15, desc:"每页条数")]

    public function statementIndex(Request $request): Response
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(100, max(1, (int) $request->input('limit', 15)));
        $accountId = $this->decodeMaybe((string) $request->input('bank_account_id', '0'));
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
     * @Apidoc\Title("导入对账单")
     * @Apidoc\Url("/admin/v1/finance/bank-statement/import")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="batch", type="string", required=true, desc="导入批次号(幂等键)")
     * @Apidoc\Param(name="rows", type="array", required=true, desc="行[{stmt_date,direction(1收/2支),amount,counterparty,reference,balance_after}]")
     */#[Apidoc\Title("导入对账单")]
#[Apidoc\Url("/admin/v1/finance/bank-statement/import")]
#[Apidoc\Method("POST")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"batch", type:"string", required:true, desc:"导入批次号(幂等键)")]

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
        $result = $this->service()->importStatement(
            $this->decodeMaybe((string) $request->input('bank_account_id')),
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
     * @Apidoc\Title("自动核销")
     * @Apidoc\Url("/admin/v1/finance/bank-recon/auto")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="from", type="string", required=true, desc="流水起始日期 Y-m-d")
     * @Apidoc\Param(name="to", type="string", required=true, desc="流水截止日期 Y-m-d")
     * @Apidoc\Param(name="window_days", type="int", default=3, desc="日期容差天数(0~30)")
     */#[Apidoc\Title("自动核销")]
#[Apidoc\Url("/admin/v1/finance/bank-recon/auto")]
#[Apidoc\Method("POST")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"from", type:"string", required:true, desc:"流水起始日期 Y-m-d")]
#[Apidoc\Param(name:"to", type:"string", required:true, desc:"流水截止日期 Y-m-d")]
#[Apidoc\Param(name:"window_days", type:"int", default:3, desc:"日期容差天数(0~30)")]

    public function auto(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $result = $this->service()->autoReconcile(
            $this->decodeMaybe((string) $request->input('bank_account_id')),
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

        return $this->success($data, '自动核销完成');
    }

    /**
     * 手工核销（金额与方向必须一致）
     * @Apidoc\Title("手工核销")
     * @Apidoc\Url("/admin/v1/finance/bank-recon/manual")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="statement_id", type="string", required=true, desc="对账单行(hashid)")
     * @Apidoc\Param(name="cash_journal_id", type="string", required=true, desc="日记账行(hashid)")
     */#[Apidoc\Title("手工核销")]
#[Apidoc\Url("/admin/v1/finance/bank-recon/manual")]
#[Apidoc\Method("POST")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"statement_id", type:"string", required:true, desc:"对账单行(hashid)")]
#[Apidoc\Param(name:"cash_journal_id", type:"string", required:true, desc:"日记账行(hashid)")]

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
        $error = $this->service()->manualReconcile(
            $this->decodeMaybe((string) $request->input('bank_account_id')),
            $this->decodeMaybe((string) $request->input('statement_id')),
            $this->decodeMaybe((string) $request->input('cash_journal_id')),
            $adminId
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '核销成功');
    }

    /**
     * 取消核销
     * @Apidoc\Title("取消核销")
     * @Apidoc\Url("/admin/v1/finance/bank-recon/unreconcile")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="statement_id", type="string", required=true, desc="对账单行(hashid)")
     */#[Apidoc\Title("取消核销")]
#[Apidoc\Url("/admin/v1/finance/bank-recon/unreconcile")]
#[Apidoc\Method("POST")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"statement_id", type:"string", required:true, desc:"对账单行(hashid)")]

    public function unreconcile(Request $request): Response
    {
        $validator = validator($request->all(), [
            'bank_account_id' => 'required',
            'statement_id' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $error = $this->service()->unreconcile(
            $this->decodeMaybe((string) $request->input('bank_account_id')),
            $this->decodeMaybe((string) $request->input('statement_id'))
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success(null, '取消核销成功');
    }

    /**
     * 对账报告（已对清单 + 双方未达清单 + 分向汇总）
     * @Apidoc\Title("对账报告")
     * @Apidoc\Url("/admin/v1/finance/bank-recon/report")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="bank_account_id", type="string", required=true, desc="银行账户(hashid)")
     * @Apidoc\Param(name="from", type="string", required=true, desc="起始日期 Y-m-d")
     * @Apidoc\Param(name="to", type="string", required=true, desc="截止日期 Y-m-d")
     */#[Apidoc\Title("对账报告")]
#[Apidoc\Url("/admin/v1/finance/bank-recon/report")]
#[Apidoc\Method("GET")]
#[Apidoc\Param(name:"bank_account_id", type:"string", required:true, desc:"银行账户(hashid)")]
#[Apidoc\Param(name:"from", type:"string", required:true, desc:"起始日期 Y-m-d")]
#[Apidoc\Param(name:"to", type:"string", required:true, desc:"截止日期 Y-m-d")]

    public function report(Request $request): Response
    {
        $result = $this->service()->reconReport(
            $this->decodeMaybe((string) $request->input('bank_account_id', '0')),
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

    /** hashid 优先，兼容直传数字 */
    private function decodeMaybe(string $value): int
    {
        $decoded = $this->decodeIdSafe($value);
        if ($decoded !== null) {
            return $decoded;
        }

        return (int) $value;
    }

    /**
     * 对账服务实例
     */
    private function service(): BankReconService
    {
        return Container::get(BankReconService::class);
    }
}
