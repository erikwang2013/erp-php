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
