<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceSubsidiaryLedger;
use support\Request;
use support\Response;

class SubsidiaryLedgerController extends BaseController
{
    /**
     * 明细账查询（按科目列出每笔凭证分录明细）
     * GET /admin/finance/subsidiary-ledger
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date', '');
        $endDate = $request->input('end_date', '');

        $query = FinanceSubsidiaryLedger::query();
        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', (int) $accountId);
        }
        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('entry_date', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
}
