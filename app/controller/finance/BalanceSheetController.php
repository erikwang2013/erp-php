<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceBalanceSheet;
use app\model\FinanceGeneralLedger;
use support\Request;
use support\Response;

class BalanceSheetController extends BaseController
{
    /**
     * 资产负债表
     * @Apidoc\Title("资产负债表")
     * @Apidoc\Desc("查询或从总账生成资产负债表")
     * @Apidoc\Url("/admin/finance/report/balance-sheet")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="report_year", type="int", desc="报表年份")
     * @Apidoc\Param(name="report_month", type="int", desc="报表月份")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="资产负债表数据")
     */
    public function index(Request $request): Response
    {
        $year = (int) $request->input('report_year', (int) date('Y'));
        $month = (int) $request->input('report_month', (int) date('m'));

        // 先查找已有快照
        $snapshot = FinanceBalanceSheet::where('report_year', $year)
            ->where('report_month', $month)
            ->first();

        if ($snapshot) {
            return $this->success($this->encodeIds($snapshot->toArray()));
        }

        // 从总账数据生成资产负债表
        $ledgers = FinanceGeneralLedger::where('period_year', $year)
            ->where('period_month', $month)
            ->get();

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;
        $currentAssets = 0;
        $nonCurrentAssets = 0;
        $currentLiabilities = 0;
        $nonCurrentLiabilities = 0;

        foreach ($ledgers as $ledger) {
            $net = $ledger->closing_debit - $ledger->closing_credit;
            // 根据科目类别聚合（资产类1，负债类2，权益类3）
            $accountId = $ledger->account_id;
            // 简化处理：account_id 1000-1999 资产，2000-2999 负债，3000-3999 权益
            if ($accountId >= 1000 && $accountId < 2000) {
                $totalAssets += $net;
                if ($accountId >= 1000 && $accountId < 1500) {
                    $currentAssets += $net;
                } else {
                    $nonCurrentAssets += $net;
                }
            } elseif ($accountId >= 2000 && $accountId < 3000) {
                $totalLiabilities += abs($net);
                if ($accountId >= 2000 && $accountId < 2500) {
                    $currentLiabilities += abs($net);
                } else {
                    $nonCurrentLiabilities += abs($net);
                }
            } elseif ($accountId >= 3000 && $accountId < 4000) {
                $totalEquity += $net;
            }
        }

        $reportData = [
            'report_year' => $year,
            'report_month' => $month,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'current_assets' => round($currentAssets, 2),
            'non_current_assets' => round($nonCurrentAssets, 2),
            'current_liabilities' => round($currentLiabilities, 2),
            'non_current_liabilities' => round($nonCurrentLiabilities, 2),
            'report_data' => json_encode([
                'generated_from' => 'general_ledger',
                'ledger_count' => $ledgers->count(),
            ]),
        ];

        return $this->success($reportData, '报表已从总账生成（未保存为快照）');
    }

    /**
     * 保存资产负债表快照
     * @Apidoc\Title("保存资产负债表快照")
     * @Apidoc\Desc("将资产负债表数据保存为快照记录")
     * @Apidoc\Url("/admin/finance/report/balance-sheet")
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

        $existing = FinanceBalanceSheet::where('report_year', $year)
            ->where('report_month', $month)
            ->first();
        if ($existing) {
            return $this->fail('该期间已存在资产负债表快照', 422);
        }

        $item = new FinanceBalanceSheet();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '快照保存成功');
    }
}
