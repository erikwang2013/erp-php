<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceCashFlow;
use app\model\FinanceCashJournal;
use support\Request;
use support\Response;

class CashFlowController extends BaseController
{
    /**
     * 现金流量表
     * @Apidoc\Title("现金流量表")
     * @Apidoc\Desc("查询或从现金日记账生成现金流量表")
     * @Apidoc\Url("/admin/finance/report/cash-flow")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="report_year", type="int", desc="报表年份")
     * @Apidoc\Param(name="report_month", type="int", desc="报表月份")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="现金流量表数据")
     */
    public function index(Request $request): Response
    {
        $year = (int) $request->input('report_year', (int) date('Y'));
        $month = (int) $request->input('report_month', (int) date('m'));

        // 先查找已有快照
        $snapshot = FinanceCashFlow::where('report_year', $year)
            ->where('report_month', $month)
            ->first();

        if ($snapshot) {
            return $this->success($this->encodeIds($snapshot->toArray()));
        }

        // 从现金日记账生成现金流量表
        $journals = FinanceCashJournal::whereYear('journal_date', $year)
            ->whereMonth('journal_date', $month)
            ->get();

        $operatingInflow = '0';
        $operatingOutflow = '0';
        $investingInflow = '0';
        $investingOutflow = '0';
        $financingInflow = '0';
        $financingOutflow = '0';

        foreach ($journals as $journal) {
            $amount = bc_norm($journal->amount ?? 0);
            $direction = $journal->direction ?? 1;
            $category = $journal->category_id ?? 0;

            if ($direction === 1) {
                // 收入
                if ($category >= 10 && $category < 20) {
                    $operatingInflow = bcadd($operatingInflow, $amount, 6);
                } elseif ($category >= 20 && $category < 30) {
                    $investingInflow = bcadd($investingInflow, $amount, 6);
                } elseif ($category >= 30 && $category < 40) {
                    $financingInflow = bcadd($financingInflow, $amount, 6);
                }
            } else {
                // 支出
                if ($category >= 10 && $category < 20) {
                    $operatingOutflow = bcadd($operatingOutflow, $amount, 6);
                } elseif ($category >= 20 && $category < 30) {
                    $investingOutflow = bcadd($investingOutflow, $amount, 6);
                } elseif ($category >= 30 && $category < 40) {
                    $financingOutflow = bcadd($financingOutflow, $amount, 6);
                }
            }
        }

        // 期初现金余额：上月期末
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $prevSnapshot = FinanceCashFlow::where('report_year', $prevYear)
            ->where('report_month', $prevMonth)
            ->first();
        $beginningCash = $prevSnapshot ? bc_norm($prevSnapshot->ending_cash ?? 0) : '0';
        $endingCash = bcadd($beginningCash, bcsub($operatingInflow, $operatingOutflow, 6), 6);
        $endingCash = bcadd($endingCash, bcsub($investingInflow, $investingOutflow, 6), 6);
        $endingCash = bcadd($endingCash, bcsub($financingInflow, $financingOutflow, 6), 6);

        $reportData = [
            'report_year' => $year,
            'report_month' => $month,
            'operating_inflow' => (float) bc_round($operatingInflow, 2),
            'operating_outflow' => (float) bc_round($operatingOutflow, 2),
            'operating_net' => (float) bc_round(bcsub($operatingInflow, $operatingOutflow, 6), 2),
            'investing_inflow' => (float) bc_round($investingInflow, 2),
            'investing_outflow' => (float) bc_round($investingOutflow, 2),
            'investing_net' => (float) bc_round(bcsub($investingInflow, $investingOutflow, 6), 2),
            'financing_inflow' => (float) bc_round($financingInflow, 2),
            'financing_outflow' => (float) bc_round($financingOutflow, 2),
            'financing_net' => (float) bc_round(bcsub($financingInflow, $financingOutflow, 6), 2),
            'beginning_cash' => (float) bc_round($beginningCash, 2),
            'ending_cash' => (float) bc_round($endingCash, 2),
            'report_data' => json_encode([
                'generated_from' => 'cash_journal',
                'journal_count' => $journals->count(),
            ]),
        ];

        return $this->success($reportData, '报表已从现金日记账生成（未保存为快照）');
    }

    /**
     * 保存现金流量表快照
     * @Apidoc\Title("保存现金流量表快照")
     * @Apidoc\Desc("将现金流量表数据保存为快照记录")
     * @Apidoc\Url("/admin/finance/report/cash-flow")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="report_year", type="int", desc="报表年份")
     * @Apidoc\Param(name="report_month", type="int", desc="报表月份")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="快照数据")
     */
    public function store(Request $request): Response
    {
        $year = (int) $request->input('report_year', (int) date('Y'));
        $month = (int) $request->input('report_month', (int) date('m'));

        $existing = FinanceCashFlow::where('report_year', $year)
            ->where('report_month', $month)
            ->first();
        if ($existing) {
            return $this->fail('该期间已存在现金流量表快照', 422);
        }

        $item = new FinanceCashFlow();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '快照保存成功');
    }
}
