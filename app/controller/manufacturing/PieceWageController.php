<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgPieceWage;
use app\service\manufacturing\PieceWageService;
use support\Container;
use support\Request;
use support\Response;

/**
 * 计件工资月度归集查询（P1-M1b，只读台账）
 *
 * 数据由报工审核自动写入（WorkReportService::audit → PieceWageService::accumulate），
 * HR 薪资批量生成（HrService::batchGenerateSalaries）按员工+期间并入。
 * @Apidoc\Tag("生产制造")
 */#[\erikwang2013\apidoc\annotation\Tag("生产制造")]

class PieceWageController extends BaseController
{
    /**
     * 计件工资台账（分页，按员工/期间筛选）
     * @Apidoc\Title("计件工资台账")
     * @Apidoc\Url("/admin/v1/mfg/piece-wage")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID")
     * @Apidoc\Param(name="period_year", type="int", desc="归集年份，如 2026")
     * @Apidoc\Param(name="period_month", type="int", desc="归集月份 1-12")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */#[\erikwang2013\apidoc\annotation\Title("计件工资台账")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/piece-wage")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"归集年份，如 2026")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"归集月份 1-12")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->service()->list(MfgPieceWage::class, [
            'employee_id' => $request->input('employee_id'),
            'period_year' => $request->input('period_year'),
            'period_month' => $request->input('period_month'),
        ], $page, $limit, [
            'eqFilters' => ['period_year', 'period_month'],
            'truthyFilters' => ['employee_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /** 计件工资服务 */
    private function service(): PieceWageService
    {
        return Container::get(PieceWageService::class);
    }
}
