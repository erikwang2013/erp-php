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
     * 明细账查询
     */#[\erikwang2013\apidoc\annotation\Title("明细账查询")]
#[\erikwang2013\apidoc\annotation\Desc("按科目列出每笔凭证分录明细，支持日期范围筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/subsidiary-ledger")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"account_id", type:"int", desc:"科目ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"start_date", type:"string", desc:"开始日期")]
#[\erikwang2013\apidoc\annotation\Param(name:"end_date", type:"string", desc:"结束日期")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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
