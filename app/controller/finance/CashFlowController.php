<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceCashFlow;
use app\service\finance\LedgerBalanceService;
use app\service\finance\LedgerService;
use support\Request;
use support\Response;

class CashFlowController extends BaseController
{
    /**
     * 现金流量表
     */
#[\erikwang2013\apidoc\annotation\Title("现金流量表")]
#[\erikwang2013\apidoc\annotation\Desc("查询快照或从已审核凭证实时生成现金流量表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/cash-flow")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_year", type:"int", desc:"报表年份")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_month", type:"int", desc:"报表月份")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"现金流量表数据")]

    public function index(Request $request): Response
    {
        $year = (int) $request->input('report_year', (int) date('Y'));
        $month = (int) $request->input('report_month', (int) date('m'));

        // 作用域：company_id/ledger_id 可选（hashid 编码），缺省回落到默认公司/账套
        try {
            $scope = (new LedgerService())->resolveScope(
                $request->input('company_id') ? $this->decodeIdSafe((string) $request->input('company_id')) : null,
                $request->input('ledger_id') ? $this->decodeIdSafe((string) $request->input('ledger_id')) : null
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        // 先查找已有快照（账套维度）
        $snapshot = FinanceCashFlow::where('company_id', $scope['company_id'])
            ->where('ledger_id', $scope['ledger_id'])
            ->where('report_year', $year)
            ->where('report_month', $month)
            ->first();

        if ($snapshot) {
            return $this->success($this->encodeIds($snapshot->toArray()));
        }

        // 无快照：从已审核凭证实时重算（期初现金优先取上期快照，见 LedgerBalanceService）
        $balance = new LedgerBalanceService();
        $report = $balance->computeCashFlow(
            $scope['ledger_id'],
            $year,
            $month,
            $balance->beginningCash($scope['ledger_id'], $year, $month)
        );
        $reportData = $report;
        $reportData['report_data'] = json_encode($report['report_data'], JSON_UNESCAPED_UNICODE);

        return $this->success($reportData, '报表已从凭证实时生成（未保存为快照）');
    }

    /**
     * 保存现金流量表快照
     */
#[\erikwang2013\apidoc\annotation\Title("保存现金流量表快照")]
#[\erikwang2013\apidoc\annotation\Desc("将现金流量表数据保存为快照记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/report/cash-flow")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_year", type:"int", desc:"报表年份")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_month", type:"int", desc:"报表月份")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"快照数据")]

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
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '快照保存成功');
    }
}
