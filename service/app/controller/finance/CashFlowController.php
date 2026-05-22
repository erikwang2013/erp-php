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
     * GET /admin/finance/report/cash-flow
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

        $operatingInflow = 0; $operatingOutflow = 0;
        $investingInflow = 0; $investingOutflow = 0;
        $financingInflow = 0; $financingOutflow = 0;

        foreach ($journals as $journal) {
            $amount = $journal->amount ?? 0;
            $direction = $journal->direction ?? 1;
            $category = $journal->category_id ?? 0;

            if ($direction === 1) {
                // 收入
                if ($category >= 10 && $category < 20) {
                    $operatingInflow += $amount;
                } elseif ($category >= 20 && $category < 30) {
                    $investingInflow += $amount;
                } elseif ($category >= 30 && $category < 40) {
                    $financingInflow += $amount;
                }
            } else {
                // 支出
                if ($category >= 10 && $category < 20) {
                    $operatingOutflow += $amount;
                } elseif ($category >= 20 && $category < 30) {
                    $investingOutflow += $amount;
                } elseif ($category >= 30 && $category < 40) {
                    $financingOutflow += $amount;
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
        $beginningCash = $prevSnapshot ? (float) $prevSnapshot->ending_cash : 0;
        $endingCash = $beginningCash + ($operatingInflow - $operatingOutflow) + ($investingInflow - $investingOutflow) + ($financingInflow - $financingOutflow);

        $reportData = [
            'report_year' => $year,
            'report_month' => $month,
            'operating_inflow' => round($operatingInflow, 2),
            'operating_outflow' => round($operatingOutflow, 2),
            'operating_net' => round($operatingInflow - $operatingOutflow, 2),
            'investing_inflow' => round($investingInflow, 2),
            'investing_outflow' => round($investingOutflow, 2),
            'investing_net' => round($investingInflow - $investingOutflow, 2),
            'financing_inflow' => round($financingInflow, 2),
            'financing_outflow' => round($financingOutflow, 2),
            'financing_net' => round($financingInflow - $financingOutflow, 2),
            'beginning_cash' => round($beginningCash, 2),
            'ending_cash' => round($endingCash, 2),
            'report_data' => json_encode([
                'generated_from' => 'cash_journal',
                'journal_count' => $journals->count(),
            ]),
        ];

        return $this->success($reportData, '报表已从现金日记账生成（未保存为快照）');
    }

    /**
     * 保存为快照
     * POST /admin/finance/report/cash-flow
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
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '快照保存成功');
    }
}
