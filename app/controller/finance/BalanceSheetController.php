<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\FinanceBalanceSheet;
use app\service\finance\LedgerBalanceService;
use app\service\finance\LedgerService;
use support\Request;
use support\Response;

class BalanceSheetController extends BaseController
{
    /**
     * 资产负债表
     * @Apidoc\Title("资产负债表")
     * @Apidoc\Desc("查询或从总账生成资产负债表")
     * @Apidoc\Url("/admin/v1/finance/report/balance-sheet")
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
        $snapshot = FinanceBalanceSheet::where('company_id', $scope['company_id'])
            ->where('ledger_id', $scope['ledger_id'])
            ->where('report_year', $year)
            ->where('report_month', $month)
            ->first();

        if ($snapshot) {
            return $this->success($this->encodeIds($snapshot->toArray()));
        }

        // 无快照：从已审核凭证按账套实时重算（科目类型为权威口径，见 LedgerBalanceService）
        $report = (new LedgerBalanceService())->computeBalanceSheet($scope['ledger_id'], $year, $month);
        $reportData = $report;
        $reportData['report_data'] = json_encode($report['report_data'], JSON_UNESCAPED_UNICODE);

        return $this->success($reportData, '报表已从凭证实时生成（未保存为快照）');
    }

    /**
     * 保存资产负债表快照
     * @Apidoc\Title("保存资产负债表快照")
     * @Apidoc\Desc("将资产负债表数据保存为快照记录")
     * @Apidoc\Url("/admin/v1/finance/report/balance-sheet")
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
