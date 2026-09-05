<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceProfit;
use app\service\finance\AccountBalanceService;
use app\service\finance\ConsolidationService;
use app\service\finance\FinancialRatioService;
use app\service\finance\LedgerService;
use app\service\finance\PeriodCloseService;
use support\Request;
use support\Response;

class ReportController extends BaseController
{
    /**
     * 财务利润报表
     */
#[\erikwang2013\apidoc\annotation\Title("财务利润报表")]
#[\erikwang2013\apidoc\annotation\Desc("按年度和月份查询利润数据，含汇总统计")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/profit")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"year", type:"int", desc:"年份，默认当前年")]
#[\erikwang2013\apidoc\annotation\Param(name:"month", type:"int", desc:"月份，可选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"利润数据，含list和summary")]

    public function profit(Request $request): Response
    {
        $year = (int) $request->input('year', (int) date('Y'));
        $month = $request->input('month');

        // 作用域：company_id/ledger_id 可选（hashid 编码），缺省回落到默认公司/账套
        try {
            $scope = (new LedgerService())->resolveScope(
                $request->input('company_id') ? $this->decodeIdSafe((string) $request->input('company_id')) : null,
                $request->input('ledger_id') ? $this->decodeIdSafe((string) $request->input('ledger_id')) : null
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $query = FinanceProfit::where('company_id', $scope['company_id'])
            ->where('ledger_id', $scope['ledger_id'])
            ->where('year', $year);
        if ($month !== null && $month !== '') {
            $query->where('month', (int) $month);
        }

        $data = $query->orderBy('month')->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        $summary = [
            'total_revenue' => $data->sum('revenue'),
            'total_cost' => $data->sum('cost'),
            'total_expense' => $data->sum('expense'),
            'total_profit' => $data->sum('profit'),
        ];

        return $this->success(['list' => $data, 'summary' => $summary]);
    }

    /**
     * 期末损益结转
     */
#[\erikwang2013\apidoc\annotation\Title("期末损益结转")]
#[\erikwang2013\apidoc\annotation\Desc("将损益类科目余额结转至本年利润")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/close-period")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"year", type:"int", desc:"年份，默认当前年")]
#[\erikwang2013\apidoc\annotation\Param(name:"month", type:"int", desc:"月份(1-12)，默认当前月")]

    public function closePeriod(Request $request): Response
    {
        $year = (int) $request->input('year', (int) date('Y'));
        $month = (int) $request->input('month', (int) date('m'));
        if ($month < 1 || $month > 12) {
            return $this->fail('月份必须在1-12之间', 422);
        }

        return $this->success((new PeriodCloseService())->closeProfitAndLoss($year, $month));
    }

    /**
     * 多币种报表合并
     */
#[\erikwang2013\apidoc\annotation\Title("多币种合并")]
#[\erikwang2013\apidoc\annotation\Desc("按期末汇率将外币报表折算为本位币")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/consolidate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"subsidiary_reports", type:"array", desc:"子公司报表列表")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_currency", type:"string", desc:"本位币，默认CNY")]

    public function consolidate(Request $request): Response
    {
        $subsidiaryReports = $request->input('subsidiary_reports', []);
        if (!is_array($subsidiaryReports)) {
            return $this->fail('subsidiary_reports 必须为数组', 422);
        }
        $baseCurrency = (string) $request->input('base_currency', 'CNY');

        try {
            return $this->success((new ConsolidationService())->consolidate($subsidiaryReports, $baseCurrency));
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 501);
        }
    }

    /**
     * 财务指标计算
     */
#[\erikwang2013\apidoc\annotation\Title("财务指标计算")]
#[\erikwang2013\apidoc\annotation\Desc("由资产负债表与利润表计算流动比率/负债率/净利率/资产收益率")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/ratios")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"balance_sheet", type:"object", desc:"资产负债表(流动资产/流动负债/总负债/总资产)")]
#[\erikwang2013\apidoc\annotation\Param(name:"profit_statement", type:"object", desc:"利润表(净利润/营业收入)")]

    public function ratios(Request $request): Response
    {
        $balanceSheet = $request->input('balance_sheet', []);
        $profitStatement = $request->input('profit_statement', []);
        if (!is_array($balanceSheet) || !is_array($profitStatement)) {
            return $this->fail('balance_sheet 与 profit_statement 必须为对象', 422);
        }

        return $this->success((new FinancialRatioService())->calculate($balanceSheet, $profitStatement));
    }

    /**
     * 试算平衡表
     */
#[\erikwang2013\apidoc\annotation\Title("试算平衡表")]
#[\erikwang2013\apidoc\annotation\Desc("按期间汇总科目借贷方发生额与余额")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/trial-balance")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"period", type:"string", desc:"期间 YYYY-MM，默认当前月")]

    public function trialBalance(Request $request): Response
    {
        $period = (string) $request->input('period', date('Y-m'));

        try {
            return $this->success((new AccountBalanceService())->getTrialBalance($period));
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }

    /**
     * 科目余额查询
     */
#[\erikwang2013\apidoc\annotation\Title("科目余额查询")]
#[\erikwang2013\apidoc\annotation\Desc("查询指定会计科目在期间的期初/本期/期末余额")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/account-balance")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"account_subject_id", type:"int", desc:"科目ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period", type:"string", desc:"期间 YYYY-MM，默认当前月")]

    public function accountBalance(Request $request): Response
    {
        $accountSubjectId = (int) $request->input('account_subject_id', 0);
        if ($accountSubjectId <= 0) {
            return $this->fail('account_subject_id 必须大于0', 422);
        }
        $period = (string) $request->input('period', '');

        try {
            return $this->success((new AccountBalanceService())->getBalance($accountSubjectId, $period));
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
