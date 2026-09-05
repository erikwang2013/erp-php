<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\FinancePeriod;
use app\service\finance\LedgerService;
use support\Request;
use support\Response;

/**
 * 账套会计期间管理（F1）——开账/关账/期间列表。
 */
class LedgerPeriodController extends BaseController
{
    /**
     * 期间列表
     * @Apidoc\Title("期间列表")
     * @Apidoc\Desc("指定账套（缺省回落到公司默认/存量默认账套）的会计期间，倒序")
     * @Apidoc\Url("/admin/v1/finance/ledger/period-list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="company_id", type="string", desc="公司ID(hashid)，可选")
     * @Apidoc\Param(name="ledger_id", type="string", desc="账套ID(hashid)，可选，优先于company_id")
     * @Apidoc\Returned("data", type="object", desc="期间列表")
     */
    public function list(Request $request): Response
    {
        try {
            $scope = $this->resolveScope($request);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        $rows = FinancePeriod::where('ledger_id', $scope['ledger_id'])
            ->orderByDesc('period')->get();

        $items = [];
        foreach ($rows as $row) {
            $item = $this->encodeIds($row->toArray(), ['id', 'ledger_id']);
            $items[] = $item;
        }

        return $this->success(['list' => $items, 'total' => count($items)]);
    }

    /**
     * 开账
     * @Apidoc\Title("开账")
     * @Apidoc\Desc("账套下开一个会计期间 YYYY-MM（重复开账 → 业务异常）")
     * @Apidoc\Url("/admin/v1/finance/ledger/period-open")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="company_id", type="string", desc="公司ID(hashid)，可选")
     * @Apidoc\Param(name="ledger_id", type="string", desc="账套ID(hashid)，可选")
     * @Apidoc\Param(name="period", type="string", desc="期间 YYYY-MM，必填")
     */
    public function open(Request $request): Response
    {
        $period = trim((string) $request->input('period', ''));
        try {
            $scope = $this->resolveScope($request);
            $row = (new LedgerService())->openPeriod($scope['ledger_id'], $period);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($row->toArray(), ['id', 'ledger_id']), '期间开账成功');
    }

    /**
     * 关账
     * @Apidoc\Title("关账")
     * @Apidoc\Desc("实时重算三张单体快照并落库，期间置为已关；前置拒绝期间内未审核凭证")
     * @Apidoc\Url("/admin/v1/finance/ledger/period-close")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="company_id", type="string", desc="公司ID(hashid)，可选")
     * @Apidoc\Param(name="ledger_id", type="string", desc="账套ID(hashid)，可选")
     * @Apidoc\Param(name="period", type="string", desc="期间 YYYY-MM，必填")
     * @Apidoc\Returned("data", type="object", desc="三张快照行ID(hashid)")
     */
    public function close(Request $request): Response
    {
        $period = trim((string) $request->input('period', ''));
        try {
            $scope = $this->resolveScope($request);
            $result = (new LedgerService())->closePeriod($scope['ledger_id'], $period);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        $result = $this->encodeIds($result, ['balance_sheet_id', 'profit_id', 'cash_flow_id']);

        return $this->success($result, $period . ' 期间关账成功');
    }

    /**
     * 解析账套范围：company/ledger 均可缺省（回落默认公司默认账套）。
     */
    private function resolveScope(Request $request): array
    {
        $companyId = $this->decodeIdSafe((string) $request->input('company_id', ''));
        $ledgerId = $this->decodeIdSafe((string) $request->input('ledger_id', ''));

        return (new LedgerService())->resolveScope($companyId, $ledgerId);
    }
}
