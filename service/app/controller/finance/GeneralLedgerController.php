<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceGeneralLedger;
use support\Request;
use support\Response;

class GeneralLedgerController extends BaseController
{
    /**
     * 总账查询（按科目+会计期间汇总）
     * GET /admin/finance/general-ledger
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $year = $request->input('period_year');
        $month = $request->input('period_month');
        $accountId = $request->input('account_id');

        $query = FinanceGeneralLedger::query();
        if ($year !== null && $year !== '') {
            $query->where('period_year', (int) $year);
        }
        if ($month !== null && $month !== '') {
            $query->where('period_month', (int) $month);
        }
        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', (int) $accountId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
}
