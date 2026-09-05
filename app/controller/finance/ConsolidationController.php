<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\service\finance\ConsolidationService;
use support\Request;
use support\Response;

/**
 * 集团合并报表（F2）——草稿生成/版本/抵销/出表。
 */
class ConsolidationController extends BaseController
{
    /**
     * 生成合并草稿（集团=公司及其直接子公司，全部经默认账套）
     */#[\erikwang2013\apidoc\annotation\Title("生成合并草稿")]
#[\erikwang2013\apidoc\annotation\Desc("以报表期间内各子公司默认账套的单体报表（快照优先/实时兜底）合并；外币经期末汇率折算")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/consolidation/draft")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"company_id", type:"string", desc:"集团组织ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_year", type:"int", desc:"报表年，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_month", type:"int", desc:"报表月 1-12，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_currency", type:"string", desc:"合并本位币，缺省=集团本位币")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"合并报表行（含 report_data 底稿）")]

    public function draft(Request $request): Response
    {
        $companyId = $this->decodeIdSafe((string) $request->input('company_id', ''));
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        if ($companyId === null) {
            return $this->fail('company_id 必填', 422);
        }
        try {
            $report = (new ConsolidationService())->generateDraft(
                $companyId,
                $year,
                $month,
                (string) $request->input('base_currency', '')
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), '合并草稿生成成功');
    }

    /**
     * 最新版本（当前草稿）
     */#[\erikwang2013\apidoc\annotation\Title("最新合并报表")]
#[\erikwang2013\apidoc\annotation\Desc("同一集团+期间的当前版本（最新 created_at，可含已出表历史）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/consolidation/latest")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"company_id", type:"string", desc:"集团组织ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_year", type:"int", desc:"报表年，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_month", type:"int", desc:"报表月，必填")]

    public function latest(Request $request): Response
    {
        $companyId = $this->decodeIdSafe((string) $request->input('company_id', ''));
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        if ($companyId === null) {
            return $this->fail('company_id 必填', 422);
        }
        $report = (new ConsolidationService())->latest($companyId, $year, $month);
        if (!$report) {
            return $this->fail('该集团与期间暂无合并报表', 404);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']));
    }

    /**
     * 版本列表（含历史已出表）
     */#[\erikwang2013\apidoc\annotation\Title("合并报表版本列表")]
#[\erikwang2013\apidoc\annotation\Desc("同一集团+期间的全部历史版本，新→旧")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/consolidation/list")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"company_id", type:"string", desc:"集团组织ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_year", type:"int", desc:"报表年，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_month", type:"int", desc:"报表月，必填")]

    public function list(Request $request): Response
    {
        $companyId = $this->decodeIdSafe((string) $request->input('company_id', ''));
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        if ($companyId === null) {
            return $this->fail('company_id 必填', 422);
        }
        $rows = (new ConsolidationService())->list($companyId, $year, $month);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->encodeIds($row->toArray(), ['id', 'company_id']);
        }

        return $this->success(['list' => $items, 'total' => count($items)]);
    }

    /**
     * 抵销分录（仅作用最新草稿）
     */#[\erikwang2013\apidoc\annotation\Title("合并抵销分录")]
#[\erikwang2013\apidoc\annotation\Desc("新增一组抵销行到当前草稿并重算合计；行=account_code+debit/credit（bcmath字符串）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/consolidation/eliminations")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_id", type:"string", desc:"合并报表ID(hashid)，必填且须为未出表草稿")]

    public function eliminations(Request $request): Response
    {
        $reportId = $this->decodeIdSafe((string) $request->input('report_id', ''));
        $rows = $request->input('eliminations', []);
        if ($reportId === null || !is_array($rows) || $rows === []) {
            return $this->fail('report_id 与 eliminations 必填', 422);
        }
        try {
            $report = (new ConsolidationService())->addElimination($reportId, $rows);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), '抵销分录已保存');
    }

    /**
     * 出表（草稿 → 已出）
     */#[\erikwang2013\apidoc\annotation\Title("合并报表出表")]
#[\erikwang2013\apidoc\annotation\Desc("status 0→1 并落 issued_at；仅未出表草稿可出，已出不可重复")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/consolidation/issue")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"report_id", type:"string", desc:"合并报表ID(hashid)，必填")]

    public function issue(Request $request): Response
    {
        $reportId = $this->decodeIdSafe((string) $request->input('report_id', ''));
        if ($reportId === null) {
            return $this->fail('report_id 必填', 422);
        }
        try {
            $report = (new ConsolidationService())->issue($reportId);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), '出表成功');
    }
}
