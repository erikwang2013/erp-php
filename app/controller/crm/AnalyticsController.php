<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmAnalyticsMetric;
use app\model\CrmAnalyticsReport;
use support\Request;
use support\Response;

class AnalyticsController extends BaseController
{
    // 分析报表

    /**
     * 报表列表
     * @Apidoc\Title("分析报表列表")
     * @Apidoc\Desc("分页查询分析报表记录")
     * @Apidoc\Url("/admin/crm/analytics")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="type", type="string", desc="报表类型")
     * @Apidoc\Param(name="period_year", type="int", desc="报表年度")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function reports(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $type = $request->input('type', '');
        $periodYear = $request->input('period_year');

        $query = CrmAnalyticsReport::query();
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($periodYear) {
            $query->where('period_year', (int) $periodYear);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 生成分析报表
     * @Apidoc\Title("生成分析报表")
     * @Apidoc\Desc("根据类型生成模拟分析报表数据并保存")
     * @Apidoc\Url("/admin/crm/analytics")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="name", type="string", desc="报表名称，必填")
     * @Apidoc\Param(name="type", type="string", desc="报表类型:customer/order/revenue/activity/retention，必填")
     * @Apidoc\Param(name="period_year", type="int", desc="报表年度，必填")
     * @Apidoc\Param(name="period_value", type="int", desc="期间值，必填")
     * @Apidoc\Param(name="period_type", type="int", desc="期间类型:1=月2=季3=年")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

        $type = $request->input('type');
        $periodYear = (int) $request->input('period_year');
        $periodValue = (int) $request->input('period_value');
        $periodType = (int) $request->input('period_type', 1);

        // 根据类型生成模拟报表数据
        $reportData = $this->buildReportData($type, $periodYear, $periodValue, $periodType);

        $report = new CrmAnalyticsReport();
        $report->id = $this->generateId();
        $report->name = $request->input('name');
        $report->type = $type;
        $report->period_type = $periodType;
        $report->period_year = $periodYear;
        $report->period_value = $periodValue;
        $report->report_data = json_encode($reportData, JSON_UNESCAPED_UNICODE);
        $report->generated_at = date('Y-m-d H:i:s');
        $report->save();

        return $this->success($this->encodeIds($report->toArray()), '报表生成成功');
    }

    /**
     * 报表详情
     * @Apidoc\Title("报表详情")
     * @Apidoc\Desc("查看分析报表详细信息")
     * @Apidoc\Url("/admin/crm/analytics/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="报表ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function reportShow(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = CrmAnalyticsReport::find($id);
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
     * @Apidoc\Title("分析指标列表")
     * @Apidoc\Desc("查询全部分析指标配置")
     * @Apidoc\Url("/admin/crm/analytics")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function metrics(Request $request): Response
    {
        $list = CrmAnalyticsMetric::query()->orderBy('id', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * 创建/更新指标
     * @Apidoc\Title("创建或更新分析指标")
     * @Apidoc\Desc("有id则更新，无id则创建分析指标")
     * @Apidoc\Url("/admin/crm/analytics")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="name", type="string", desc="指标名称，必填")
     * @Apidoc\Param(name="key", type="string", desc="指标键名，必填")
     * @Apidoc\Param(name="type", type="string", desc="指标类型，必填")
     * @Apidoc\Param(name="id", type="string", desc="记录ID，传则更新")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

        $hashid = $request->input('id', '');
        if ($hashid) {
            $id = $this->decodeId($id);
            $item = CrmAnalyticsMetric::find($id);
            if (!$item) {
                return $this->fail('记录不存在', 404);
            }
        } else {
            $item = new CrmAnalyticsMetric();
            $item->id = $this->generateId();
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $hashid ? '更新成功' : '创建成功');
    }

    /**
     * 构建报表数据
     */
    private function buildReportData(string $type, int $year, int $period, int $periodType): array
    {
        $periodLabel = match ($periodType) {
            2 => "{$year}年Q{$period}",
            3 => "{$year}年度",
            default => "{$year}年{$period}月",
        };

        return match ($type) {
            'customer' => [
                'new_customers' => rand(10, 200),
                'active_customers' => rand(50, 500),
                'churn_customers' => rand(1, 30),
                'retention_rate' => round(mt_rand(750, 950) / 1000, 4),
                'period' => $periodLabel,
            ],
            'order' => [
                'total_orders' => rand(50, 500),
                'total_amount' => round(mt_rand(10000, 500000) / 100, 2),
                'avg_order_value' => round(mt_rand(5000, 50000) / 100, 2),
                'period' => $periodLabel,
            ],
            'revenue' => [
                'total_revenue' => round(mt_rand(50000, 1000000) / 100, 2),
                'total_cost' => round(mt_rand(30000, 700000) / 100, 2),
                'gross_profit' => 0,
                'gross_margin' => 0,
                'period' => $periodLabel,
            ],
            'activity' => [
                'total_campaigns' => rand(1, 10),
                'total_participants' => rand(50, 500),
                'conversion_count' => rand(5, 50),
                'conversion_rate' => round(mt_rand(20, 150) / 1000, 4),
                'period' => $periodLabel,
            ],
            'retention' => [
                'cohort_size' => rand(100, 1000),
                'month1_retention' => round(mt_rand(500, 850) / 1000, 4),
                'month3_retention' => round(mt_rand(350, 650) / 1000, 4),
                'month6_retention' => round(mt_rand(200, 500) / 1000, 4),
                'period' => $periodLabel,
            ],
            default => ['period' => $periodLabel],
        };
    }
}
