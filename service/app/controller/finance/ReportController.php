<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);
namespace app\controller\finance;
use app\admin\controller\BaseController;
use app\model\FinanceProfit;
use support\Request;
use support\Response;

class ReportController extends BaseController
{
    /**
     * 财务利润报表
     * @Apidoc\Title("财务利润报表")
     * @Apidoc\Desc("按年度和月份查询利润数据，含汇总统计")
     * @Apidoc\Url("/admin/finance/report/profit")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="year", type="int", desc="年份，默认当前年")
     * @Apidoc\Param(name="month", type="int", desc="月份，可选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="利润数据，含list和summary")
     */
    public function profit(Request $request): Response
    {
        $year = (int) $request->input('year', (int) date('Y'));
        $month = $request->input('month');

        $query = FinanceProfit::where('year', $year);
        if ($month !== null && $month !== '') {
            $query->where('month', (int) $month);
        }

        $data = $query->orderBy('month')->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        $summary = [
            'total_revenue' => $data->sum('revenue'),
            'total_cost' => $data->sum('cost'),
            'total_expense' => $data->sum('expense'),
            'total_profit' => $data->sum('profit'),
        ];

        return $this->success(['list' => $data, 'summary' => $summary]);
    }
}
