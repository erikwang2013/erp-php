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
     * 总账查询
     * @Apidoc\Title("总账查询")
     * @Apidoc\Desc("按科目+会计期间汇总查询总账")
     * @Apidoc\Url("/admin/finance/general-ledger")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="period_year", type="int", desc="会计年度")
     * @Apidoc\Param(name="period_month", type="int", desc="会计月份")
     * @Apidoc\Param(name="account_id", type="int", desc="科目ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }
}
