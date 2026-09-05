<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmAnalyticsMetric;
use app\model\CrmAnalyticsReport;
use app\service\crm\CrmService;
use support\Container;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("分析报表")]
#[\erikwang2013\apidoc\annotation\Group("CRM")]

class AnalyticsController extends BaseController
{
    // 分析报表

    /**
     * 报表列表
     */
#[\erikwang2013\apidoc\annotation\Title("分析报表列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询分析报表记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/analytics")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", desc:"报表类型")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"报表年度")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function reports(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $type = $request->input('type', '');
        $periodYear = $request->input('period_year');

        $result = $this->crm()->list(CrmAnalyticsReport::class, [
            'type' => $type,
            'period_year' => $periodYear,
        ], $page, $limit, [
            'stringEqFilters' => ['type'],
            'truthyFilters' => ['period_year'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 生成分析报表
     */
#[\erikwang2013\apidoc\annotation\Title("生成分析报表")]
#[\erikwang2013\apidoc\annotation\Desc("根据类型生成模拟分析报表数据并保存")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/analytics")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"报表名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", desc:"报表类型:customer/order/revenue/activity/retention，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"报表年度，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_value", type:"int", desc:"期间值，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_type", type:"int", desc:"期间类型:1=月2=季3=年")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function generate(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|string|max:30',
            'period_year' => 'required|integer',
            'period_value' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $type = (string) $request->input('type');
        $periodYear = (int) $request->input('period_year');
        $periodValue = (int) $request->input('period_value');
        $periodType = (int) $request->input('period_type', 1);

        // 根据类型生成模拟报表数据
        $reportData = $this->crm()->buildReportData($type, $periodYear, $periodValue, $periodType);

        $report = $this->crm()->createAnalyticsReport(
            (string) $request->input('name'),
            $type,
            $periodType,
            $periodYear,
            $periodValue,
            $reportData
        );

        return $this->success($this->encodeIds($report->toArray()), '报表生成成功');
    }

    /**
     * 报表详情
     */
#[\erikwang2013\apidoc\annotation\Title("报表详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看分析报表详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"报表ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function reportShow(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmAnalyticsReport::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $this->encodeIds($item->toArray());
        $data['report_data'] = $item->report_data ? json_decode($item->report_data, true) : null;

        return $this->success($data);
    }

    // 分析指标

    /**
     * 指标列表
     */
#[\erikwang2013\apidoc\annotation\Title("分析指标列表")]
#[\erikwang2013\apidoc\annotation\Desc("查询全部分析指标配置")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/analytics")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function metrics(Request $request): Response
    {
        $list = $this->crm()->all(CrmAnalyticsMetric::class, [], ['orderBy' => 'id', 'orderDir' => 'asc']);
        $list = array_map(fn ($item) => $this->encodeIds($item), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 创建/更新指标
     */
#[\erikwang2013\apidoc\annotation\Title("创建或更新分析指标")]
#[\erikwang2013\apidoc\annotation\Desc("有id则更新，无id则创建分析指标")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/analytics")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"指标名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"key", type:"string", desc:"指标键名，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"type", type:"string", desc:"指标类型，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"记录ID，传则更新")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function storeMetric(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'key' => 'required|string|max:50',
            'type' => 'required|string|max:30',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $hashid = (string) $request->input('id', '');
        // 修复：旧实现误用未定义变量 $id 解码，导致更新分支必然 500；此处按文档意图解码 hashid
        $metricId = $hashid !== '' ? $this->decodeId($hashid) : null;

        $item = $this->crm()->upsertMetric($metricId, $request->all());
        if ($item === null) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), $hashid !== '' ? '更新成功' : '创建成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
