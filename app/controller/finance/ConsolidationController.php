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
#[\erikwang2013\apidoc\annotation\Title('生成合并草稿')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]
class ConsolidationController extends BaseController
{
    /**
     * 生成合并草稿（集团=公司及其直接子公司，全部经默认账套）
     */
    #[\erikwang2013\apidoc\annotation\Title('生成合并草稿')]
    #[\erikwang2013\apidoc\annotation\Desc('以报表期间内各子公司默认账套的单体报表（快照优先/实时兜底）合并；外币经期末汇率折算')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/consolidation/draft')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'company_id', type:'string', desc:'集团组织ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_year', type:'int', desc:'报表年，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_month', type:'int', desc:'报表月 1-12，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'base_currency', type:'string', desc:'合并本位币，缺省=集团本位币')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'合并报表行（含 report_data 底稿）')]

    public function draft(Request $request): Response
    {
        $validator = validator($request->all(), [
            'company_id' => 'string',
            'report_year' => 'integer',
            'report_month' => 'integer',
            'base_currency' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 双模：hashid 串或原生数字（数字 ID 也是合法入参，decodeIdSafe 会把后者静默判成 null → 误报「必填」）
        $companyId = $this->decodeFlexibleId($request->input('company_id', ''));
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        if ($companyId === null) {
            return $this->fail($this->trans('company_id is required'), 422);
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

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), $this->trans('Consolidation draft generated successfully'));
    }

    /**
     * 最新版本（当前草稿）
     */
    #[\erikwang2013\apidoc\annotation\Title('最新合并报表')]
    #[\erikwang2013\apidoc\annotation\Desc('同一集团+期间的当前版本（最新 created_at，可含已出表历史）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/consolidation/latest')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'company_id', type:'string', desc:'集团组织ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_year', type:'int', desc:'报表年，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_month', type:'int', desc:'报表月，必填')]

    public function latest(Request $request): Response
    {
        $validator = validator($request->all(), [
            'company_id' => 'string',
            'report_year' => 'integer',
            'report_month' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $companyId = $this->decodeFlexibleId($request->input('company_id', ''));
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        if ($companyId === null) {
            return $this->fail($this->trans('company_id is required'), 422);
        }
        $report = (new ConsolidationService())->latest($companyId, $year, $month);
        if (!$report) {
            return $this->fail($this->trans('No consolidated report exists for this group and period'), 404);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']));
    }

    /**
     * 版本列表（含历史已出表）
     */
    #[\erikwang2013\apidoc\annotation\Title('合并报表版本列表')]
    #[\erikwang2013\apidoc\annotation\Desc('同一集团+期间的全部历史版本，新→旧；三个条件都可缺省，缺省即不过滤（/finance/consolidation 页面不带任何参数整表下发）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/consolidation/list')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'company_id', type:'string', desc:'集团组织ID(hashid)，可缺省；缺省/空串=不过滤，传了但解不出仍 422')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_year', type:'int', desc:'报表年，可缺省；缺省/0=不过滤')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_month', type:'int', desc:'报表月 1-12，可缺省；缺省/0=不过滤')]

    public function list(Request $request): Response
    {
        $validator = validator($request->all(), [
            'company_id' => 'string',
            'report_year' => 'integer',
            'report_month' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 三个条件都是「传了才过滤」：本接口同时是配置驱动列表页（/finance/consolidation）的数据源，
        // 那页 cfg 既无 params 也无 filters（两端 finance.ts），引擎只发 page/limit ⇒ 无参必须整表下发，
        // 否则页面一打开就是 422（前端 api.service 对 code!==0 直接抛）。
        // 但「缺省」与「非法」是两回事：company_id 传了却解不出（含 '0'，decodeFlexibleId 的数字兜底
        // 会把它判成 0 号组织）照旧 422 —— 静默返回空表会把打错的参数伪装成「没有数据」。
        $companyId = null;
        $rawCompany = $request->input('company_id', '');
        if ($rawCompany !== null && $rawCompany !== '') {
            $companyId = $this->decodeFlexibleId($rawCompany);
            if ($companyId === null || $companyId < 1) {
                return $this->fail($this->trans('Invalid company_id'), 422);
            }
        }
        $year = (int) $request->input('report_year', 0);
        $month = (int) $request->input('report_month', 0);
        $rows = (new ConsolidationService())->list($companyId, $year, $month);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->encodeIds($row->toArray(), ['id', 'company_id']);
        }

        return $this->success(['list' => $items, 'total' => count($items)]);
    }

    /**
     * 抵销分录（仅作用最新草稿）
     */
    #[\erikwang2013\apidoc\annotation\Title('合并抵销分录')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一组抵销行到当前草稿并重算合计；行=account_code+debit_amount/credit_amount（bcmath字符串）')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/consolidation/eliminations')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_id', type:'string', desc:'合并报表ID(hashid)，必填且须为未出表草稿')]

    public function eliminations(Request $request): Response
    {
        $validator = validator($request->all(), [
            'report_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 双模（同上）：decodeIdSafe 只认 hashid，数字 report_id 会被误报「必填」
        $reportId = $this->decodeFlexibleId($request->input('report_id', ''));
        $rows = $request->input('eliminations', []);
        if ($reportId === null || !is_array($rows) || $rows === []) {
            return $this->fail($this->trans('report_id and eliminations are required'), 422);
        }
        try {
            $report = (new ConsolidationService())->addElimination($reportId, $rows);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), $this->trans('Elimination entries saved'));
    }

    /**
     * 出表（草稿 → 已出）
     */
    #[\erikwang2013\apidoc\annotation\Title('合并报表出表')]
    #[\erikwang2013\apidoc\annotation\Desc('status 0→1 并落 issued_at；仅未出表草稿可出，已出不可重复')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/consolidation/issue')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'report_id', type:'string', desc:'合并报表ID(hashid)，必填')]

    public function issue(Request $request): Response
    {
        $validator = validator($request->all(), [
            'report_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $reportId = $this->decodeFlexibleId($request->input('report_id', ''));
        if ($reportId === null) {
            return $this->fail($this->trans('report_id is required'), 422);
        }
        try {
            $report = (new ConsolidationService())->issue($reportId);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($report->toArray(), ['id', 'company_id']), $this->trans('Statement generated successfully'));
    }
}
