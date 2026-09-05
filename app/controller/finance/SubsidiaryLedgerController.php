<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\FinanceSubsidiaryLedger;
use support\Request;
use support\Response;

class SubsidiaryLedgerController extends BaseController
{
    /**
     * 明细账查询
     * @Apidoc\Title("明细账查询")
     * @Apidoc\Desc("按科目列出每笔凭证分录明细，支持日期范围筛选")
     * @Apidoc\Url("/admin/v1/finance/subsidiary-ledger")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="account_id", type="int", desc="科目ID")
     * @Apidoc\Param(name="start_date", type="string", desc="开始日期")
     * @Apidoc\Param(name="end_date", type="string", desc="结束日期")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }
}
