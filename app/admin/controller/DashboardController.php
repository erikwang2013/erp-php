<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use app\model\CrmOpportunity;
use app\model\Customer;
use app\model\FinanceArAp;
use app\model\FinanceBankAccount;
use app\model\FinancePayment;
use app\model\FinanceReceipt;
use app\model\Inventory;
use app\model\InventoryAlertLog;
use app\model\InventoryFlow;
use app\model\OperationLog;
use app\model\SalesOrder;
use support\Redis;
use support\Request;
use support\Response;

/**
 * 仪表盘
 * @Apidoc\Tag("仪表盘")
 */
class DashboardController extends BaseController
{
    /**
     * 仪表盘总览数据
     * @Apidoc\Title("仪表盘总览")
     * @Apidoc\Desc("获取经营总览数据，包含用户统计、趋势、分布和最近操作日志。数据缓存5分钟。")
     * @Apidoc\Url("/admin/dashboard")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仪表盘")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("stats", type="array", desc="统计卡片数据"),
     *     @Apidoc\Returned("trends", type="object", desc="30日趋势数据"),
     *     @Apidoc\Returned("distribution", type="object", desc="分布数据"),
     *     @Apidoc\Returned("recent_logs", type="array", desc="最近操作日志"),
     * })
     */
    public function index(Request $request): Response
    {
        // Redis 缓存 5 分钟，避免每次请求跑 5+ 条 SQL
        $cacheKey = 'dashboard:data';
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $today = date('Y-m-d');
        $startOfRange = date('Y-m-d', strtotime('-29 days'));

        $data = [
            'stats' => $this->getStats($today),
            'trends' => $this->getTrends($startOfRange),
            'distribution' => $this->getDistribution(),
            'recent_logs' => $this->getRecentLogs(),
        ];

        Redis::setex($cacheKey, 300, json_encode($data, JSON_UNESCAPED_UNICODE));

        return $this->success($data);
    }

    private function getStats(string $today): array
    {
        $totalUsers = AdminUser::count();
        $todayNew = AdminUser::whereDate('created_at', $today)->count();
        $todayActive = AdminUser::whereDate('last_login_at', $today)->count();
        $todayLogs = OperationLog::whereDate('created_at', $today)->count();

        return [
            [
                'label' => '用户总数',
                'value' => (string) $totalUsers,
                'icon' => 'people',
                'color' => '#1677FF',
                'trend' => $this->calcTrend(AdminUser::class),
            ],
            [
                'label' => '今日新增',
                'value' => (string) $todayNew,
                'icon' => 'person_add',
                'color' => '#52C41A',
            ],
            [
                'label' => '活跃用户',
                'value' => (string) $todayActive,
                'icon' => 'bolt',
                'color' => '#FA8C16',
            ],
            [
                'label' => '操作日志',
                'value' => (string) $todayLogs,
                'icon' => 'description',
                'color' => '#722ED1',
            ],
        ];
    }

    private function getTrends(string $startOfRange): array
    {
        $dates = [];
        $userGrowth = [];
        $logCounts = [];

        // 生成日期序列
        for ($i = 29; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("+{$i} days", strtotime($startOfRange)));
        }

        // 一次查询获取用户每日新增数，PHP 内累加
        $dailyNewUsers = AdminUser::whereDate('created_at', '>=', $startOfRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $cumulative = AdminUser::whereDate('created_at', '<', $startOfRange)->count();
        foreach ($dates as $date) {
            $cumulative += $dailyNewUsers[$date] ?? 0;
            $userGrowth[] = $cumulative;
        }

        // 一次查询获取操作日志每日数量
        $dailyLogs = OperationLog::whereDate('created_at', '>=', $startOfRange)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        foreach ($dates as $date) {
            $logCounts[] = $dailyLogs[$date] ?? 0;
        }

        return [
            'dates' => $dates,
            'series' => [
                ['name' => '累计用户', 'data' => $userGrowth, 'color' => '#1677FF'],
                ['name' => '操作日志', 'data' => $logCounts, 'color' => '#52C41A'],
            ],
        ];
    }

    private function getDistribution(): array
    {
        return [
            'user_status' => [
                ['name' => '启用', 'value' => AdminUser::where('status', 1)->count()],
                ['name' => '禁用', 'value' => AdminUser::where('status', 0)->count()],
            ],
        ];
    }

    private function getRecentLogs(): array
    {
        return OperationLog::with('user')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                $data = $log->toArray();
                $data['id'] = $this->encodeId($data['id']);
                $data['user_name'] = $log->user->username ?? '系统';
                unset($data['user'], $data['user_id']);

                return $data;
            })
            ->toArray();
    }

    private function calcTrend(string $modelClass): ?float
    {
        $today = $modelClass::whereDate('created_at', date('Y-m-d'))->count();
        $yesterday = $modelClass::whereDate('created_at', date('Y-m-d', strtotime('-1 day')))->count();

        if ($yesterday === 0) {
            return $today > 0 ? 100.0 : 0.0;
        }

        return round(($today - $yesterday) / $yesterday * 100, 1);
    }

    /**
     * 销售看板
     * @Apidoc\Title("销售看板")
     * @Apidoc\Desc("获取销售看板数据，包含今日/本月销售额、客户排行、商机漏斗。数据缓存5分钟。")
     * @Apidoc\Url("/admin/dashboard/sales")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仪表盘")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("today_sales", type="float", desc="今日销售额"),
     *     @Apidoc\Returned("month_sales", type="float", desc="本月销售额"),
     *     @Apidoc\Returned("top_customers", type="array", desc="客户排行"),
     *     @Apidoc\Returned("funnel", type="array", desc="商机漏斗"),
     * })
     */
    public function sales(Request $request): Response
    {
        $cacheKey = 'dashboard:sales:' . date('Y-m-d');
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $today = date('Y-m-d');
        $data = [
            'today_sales' => SalesOrder::whereDate('ordered_at', $today)->where('status', '!=', 4)->sum('total_amount') ?? 0,
            'month_sales' => SalesOrder::whereBetween('ordered_at', [date('Y-m-01'), $today])->where('status', '!=', 4)->sum('total_amount') ?? 0,
            'top_customers' => SalesOrder::selectRaw('customer_id, sum(total_amount) as total')
                ->whereBetween('ordered_at', [date('Y-m-01'), $today])
                ->where('status', '!=', 4)
                ->groupBy('customer_id')->orderByDesc('total')->limit(10)
                ->get()
                ->map(function ($row) {
                    static $customers = null;
                    if ($customers === null) {
                        $ids = SalesOrder::selectRaw('customer_id, sum(total_amount) as total')
                            ->whereBetween('ordered_at', [date('Y-m-01'), $today])
                            ->where('status', '!=', 4)
                            ->groupBy('customer_id')->orderByDesc('total')->limit(10)
                            ->pluck('customer_id');
                        $customers = Customer::whereIn('id', $ids)->pluck('name', 'id');
                    }

                    return [
                        'customer_id' => $this->encodeId($row->customer_id),
                        'customer_name' => $customers[$row->customer_id] ?? '',
                        'total' => $row->total,
                    ];
                }),
            'funnel' => CrmOpportunity::selectRaw('stage_id, count(*) as count, sum(estimated_amount) as amount')
                ->where('status', 1)->groupBy('stage_id')->get(),
        ];
        Redis::setex($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /**
     * 库存看板
     * @Apidoc\Title("库存看板")
     * @Apidoc\Desc("获取库存看板数据，包含库存总值、预警统计和出入库趋势。数据缓存5分钟。")
     * @Apidoc\Url("/admin/dashboard/inventory")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仪表盘")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("total_value", type="float", desc="库存总值"),
     *     @Apidoc\Returned("alert_low", type="int", desc="低库存预警数"),
     *     @Apidoc\Returned("alert_high", type="int", desc="高库存预警数"),
     *     @Apidoc\Returned("flow_trend", type="array", desc="出入库趋势"),
     * })
     */
    public function inventory(Request $request): Response
    {
        $cacheKey = 'dashboard:inventory:' . date('Y-m-d');
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $data = [
            'total_value' => Inventory::selectRaw('sum(quantity * cost_price) as total')->value('total') ?? 0,
            'alert_low' => InventoryAlertLog::where('alert_type', 1)->whereDate('created_at', '>=', date('Y-m-01'))->count(),
            'alert_high' => InventoryAlertLog::where('alert_type', 2)->whereDate('created_at', '>=', date('Y-m-01'))->count(),
            'flow_trend' => InventoryFlow::selectRaw('DATE(created_at) as date, direction, sum(quantity) as total')
                ->whereDate('created_at', '>=', date('Y-m-01'))->groupBy('date', 'direction')->orderBy('date')->get(),
        ];
        Redis::setex($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /**
     * 财务看板
     * @Apidoc\Title("财务看板")
     * @Apidoc\Desc("获取财务看板数据，包含应收应付、本月收付款和现金余额。数据缓存5分钟。")
     * @Apidoc\Url("/admin/dashboard/finance")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("仪表盘")
     * @Apidoc\Returned("code", type="int", desc="业务代码")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("total_ar", type="float", desc="应收账款总额"),
     *     @Apidoc\Returned("total_ap", type="float", desc="应付账款总额"),
     *     @Apidoc\Returned("month_receipt", type="float", desc="本月收款"),
     *     @Apidoc\Returned("month_payment", type="float", desc="本月付款"),
     *     @Apidoc\Returned("cash_balance", type="float", desc="现金余额"),
     * })
     */
    public function finance(Request $request): Response
    {
        $cacheKey = 'dashboard:finance:' . date('Y-m-d');
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $data = [
            'total_ar' => FinanceArAp::where('type', 1)->where('status', '!=', 2)->sum('amount') ?? 0,
            'total_ap' => FinanceArAp::where('type', 2)->where('status', '!=', 2)->sum('amount') ?? 0,
            'month_receipt' => FinanceReceipt::whereDate('received_at', '>=', date('Y-m-01'))->sum('amount') ?? 0,
            'month_payment' => FinancePayment::whereDate('paid_at', '>=', date('Y-m-01'))->sum('amount') ?? 0,
            'cash_balance' => FinanceBankAccount::sum('balance') ?? 0,
        ];
        Redis::setex($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }
}
