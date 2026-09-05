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
use app\model\OmsOrder;
use app\model\OperationLog;
use app\model\Product;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\model\TmsShipment;
use app\model\WmsPickTask;
use app\model\WmsReceiving;
use support\Redis;
use support\Request;
use support\Response;

/**
 * 仪表盘
 */
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]

class DashboardController extends BaseController
{
    /**
     * 仪表盘总览数据
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("仪表盘总览")]
#[\erikwang2013\apidoc\annotation\Desc("获取经营总览数据，包含用户统计、趋势、分布和最近操作日志。数据缓存5分钟。")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("stats", type:"array", desc:"统计卡片数据")]
#[\erikwang2013\apidoc\annotation\Returned("trends", type:"object", desc:"30日趋势数据")]
#[\erikwang2013\apidoc\annotation\Returned("distribution", type:"object", desc:"分布数据")]
#[\erikwang2013\apidoc\annotation\Returned("recent_logs", type:"array", desc:"最近操作日志")]

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

        $growth = bcdiv(
            bcmul(bcsub(bc_norm($today), bc_norm($yesterday), 6), '100', 6),
            bc_norm($yesterday),
            6
        );

        return (float) bc_round($growth, 1);
    }

    /**
     * 销售看板
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("销售看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取销售看板数据，包含今日/本月销售额、客户排行、商机漏斗。数据缓存5分钟。")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/sales")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("today_sales", type:"float", desc:"今日销售额")]
#[\erikwang2013\apidoc\annotation\Returned("month_sales", type:"float", desc:"本月销售额")]
#[\erikwang2013\apidoc\annotation\Returned("top_customers", type:"array", desc:"客户排行")]
#[\erikwang2013\apidoc\annotation\Returned("funnel", type:"array", desc:"商机漏斗")]
#[\erikwang2013\apidoc\annotation\Returned("trend", type:"array", desc:"30日销售趋势")]
#[\erikwang2013\apidoc\annotation\Returned("top_products", type:"array", desc:"Top5热销商品")]
#[\erikwang2013\apidoc\annotation\Returned("status_distribution", type:"array", desc:"订单状态分布")]

    public function sales(Request $request): Response
    {
        $cacheKey = 'dashboard:sales:' . date('Y-m-d');
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return $this->success(json_decode($cached, true));
        }

        $today = date('Y-m-d');
        $startOfRange = date('Y-m-d', strtotime('-29 days'));
        $topCustomers = SalesOrder::selectRaw('customer_id, sum(total_amount) as total')
            ->whereBetween('ordered_at', [date('Y-m-01'), $today])
            ->where('status', '!=', 4)
            ->groupBy('customer_id')->orderByDesc('total')->limit(10)
            ->get();
        $customerNames = Customer::whereIn('id', $topCustomers->pluck('customer_id'))->pluck('name', 'id');
        $data = [
            'today_sales' => SalesOrder::whereDate('ordered_at', $today)->where('status', '!=', 4)->sum('total_amount') ?? 0,
            'month_sales' => SalesOrder::whereBetween('ordered_at', [date('Y-m-01'), $today])->where('status', '!=', 4)->sum('total_amount') ?? 0,
            'top_customers' => $topCustomers->map(fn ($row) => [
                'customer_id' => $this->encodeId($row->customer_id),
                'customer_name' => $customerNames[$row->customer_id] ?? '',
                'total' => $row->total,
            ]),
            'funnel' => CrmOpportunity::selectRaw('stage_id, count(*) as count, sum(estimated_amount) as amount')
                ->where('status', 1)->groupBy('stage_id')->get(),
            'trend' => $this->getSalesTrend($startOfRange),
            'top_products' => $this->getTopProducts($startOfRange),
            'status_distribution' => $this->getOrderStatusDistribution($startOfRange),
        ];
        Redis::setex($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /** 近30日每日销售额（含0值补全） */
    private function getSalesTrend(string $startOfRange): array
    {
        $dailySales = SalesOrder::whereDate('ordered_at', '>=', $startOfRange)
            ->where('status', '!=', 4)
            ->selectRaw('DATE(ordered_at) as date, SUM(total_amount) as total')
            ->groupBy('date')->pluck('total', 'date')->toArray();

        $dates = [];
        $amounts = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("+{$i} days", strtotime($startOfRange)));
            $dates[] = $date;
            $amounts[] = (float) ($dailySales[$date] ?? 0);
        }

        return ['dates' => $dates, 'amounts' => $amounts];
    }

    /** Top5 热销商品（近30日订购数量） */
    private function getTopProducts(string $startOfRange): array
    {
        $top = SalesOrderItem::query()
            ->join('erp_sales_order', 'erp_sales_order.id', '=', 'erp_sales_order_item.order_id')
            ->whereNull('erp_sales_order.deleted_at')
            ->whereDate('erp_sales_order.ordered_at', '>=', $startOfRange)
            ->where('erp_sales_order.status', '!=', 4)
            ->selectRaw('erp_sales_order_item.product_id, SUM(erp_sales_order_item.quantity) as qty')
            ->groupBy('erp_sales_order_item.product_id')->orderByDesc('qty')->limit(5)
            ->get();
        $productNames = Product::whereIn('id', $top->pluck('product_id'))->pluck('name', 'id');

        return $top->map(fn ($row) => [
            'product_id' => $this->encodeId($row->product_id),
            'name' => $productNames[$row->product_id] ?? '',
            'quantity' => (float) $row->qty,
        ])->values()->toArray();
    }

    /** 订单状态分布（近30日，状态: 0待审核 1已审核 2部分发货 3已发货 4已取消） */
    private function getOrderStatusDistribution(string $startOfRange): array
    {
        $statusNames = [0 => '待审核', 1 => '已审核', 2 => '部分发货', 3 => '已发货', 4 => '已取消'];
        $counts = SalesOrder::whereDate('ordered_at', '>=', $startOfRange)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();

        return array_map(fn ($status, $name) => [
            'status' => $status,
            'name' => $name,
            'value' => (int) ($counts[$status] ?? 0),
        ], array_keys($statusNames), $statusNames);
    }

    /**
     * 库存看板
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("库存看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取库存看板数据，包含库存总值、预警统计和出入库趋势。数据缓存5分钟。")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/inventory")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("total_value", type:"float", desc:"库存总值")]
#[\erikwang2013\apidoc\annotation\Returned("alert_low", type:"int", desc:"低库存预警数")]
#[\erikwang2013\apidoc\annotation\Returned("alert_high", type:"int", desc:"高库存预警数")]
#[\erikwang2013\apidoc\annotation\Returned("flow_trend", type:"array", desc:"出入库趋势")]

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
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("财务看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取财务看板数据，包含应收应付、本月收付款和现金余额。数据缓存5分钟。")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/finance")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("total_ar", type:"float", desc:"应收账款总额")]
#[\erikwang2013\apidoc\annotation\Returned("total_ap", type:"float", desc:"应付账款总额")]
#[\erikwang2013\apidoc\annotation\Returned("month_receipt", type:"float", desc:"本月收款")]
#[\erikwang2013\apidoc\annotation\Returned("month_payment", type:"float", desc:"本月付款")]
#[\erikwang2013\apidoc\annotation\Returned("cash_balance", type:"float", desc:"现金余额")]
#[\erikwang2013\apidoc\annotation\Returned("ar_aging", type:"array", desc:"应收账龄汇总")]
#[\erikwang2013\apidoc\annotation\Returned("ap_aging", type:"array", desc:"应付账龄汇总")]

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
            'ar_aging' => $this->getAging(1),
            'ap_aging' => $this->getAging(2),
        ];
        Redis::setex($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /** 应收/应付账龄汇总（按未核销余额分桶；无到期日视为未到期） */
    private function getAging(int $type): array
    {
        $rows = FinanceArAp::where('type', $type)->where('status', '!=', 2)
            ->selectRaw('due_date, amount - settled_amount as outstanding')
            ->get();

        $buckets = ['未到期' => '0', '逾期1-30天' => '0', '逾期31-60天' => '0', '逾期61-90天' => '0', '逾期90+天' => '0'];
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $row) {
            $outstanding = bc_norm($row->outstanding);
            if (bccomp($outstanding, '0', 4) <= 0) {
                continue;
            }
            // due_date 为空视为未到期；模型 cast 为 date，取 Y-m-d 再转时间戳
            $days = $row->due_date === null ? 0 : (int) floor(($today - strtotime($row->due_date->toDateString())) / 86400);
            if ($days <= 0) {
                $buckets['未到期'] = bcadd($buckets['未到期'], $outstanding, 6);
            } elseif ($days <= 30) {
                $buckets['逾期1-30天'] = bcadd($buckets['逾期1-30天'], $outstanding, 6);
            } elseif ($days <= 60) {
                $buckets['逾期31-60天'] = bcadd($buckets['逾期31-60天'], $outstanding, 6);
            } elseif ($days <= 90) {
                $buckets['逾期61-90天'] = bcadd($buckets['逾期61-90天'], $outstanding, 6);
            } else {
                $buckets['逾期90+天'] = bcadd($buckets['逾期90+天'], $outstanding, 6);
            }
        }

        return array_map(
            fn ($name, $value) => ['name' => $name, 'value' => (float) bc_round($value, 2)],
            array_keys($buckets),
            array_values($buckets)
        );
    }

    /**
     * OMS 订单履约看板
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("OMS 订单履约看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取 OMS 履约 KPI：待处理/拣货中订单、今日发货数与待处理退货单")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/oms")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("pending_orders", type:"int", desc:"待处理订单数(待审核/待发货)")]
#[\erikwang2013\apidoc\annotation\Returned("picking_orders", type:"int", desc:"拣货中订单数")]
#[\erikwang2013\apidoc\annotation\Returned("shipped_today", type:"int", desc:"今日发货订单数")]
#[\erikwang2013\apidoc\annotation\Returned("pending_rma", type:"int", desc:"待处理退货单数")]

    public function oms(Request $request): Response
    {
        return $this->success([
            'pending_orders' => OmsOrder::whereIn('fulfillment_status', [0, 1])->count(),
            'picking_orders' => OmsOrder::where('fulfillment_status', 2)->count(),
            'shipped_today' => OmsOrder::where('fulfillment_status', 4)->whereDate('updated_at', date('Y-m-d'))->count(),
            'pending_rma' => \app\model\OmsRma::where('status', 0)->count(),
        ]);
    }

    /**
     * WMS 仓储作业看板
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("WMS 仓储作业看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取 WMS 仓储作业 KPI：待收货/待上架/待拣货/待打包任务数")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/wms")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("pending_receiving", type:"int", desc:"待收货单数")]
#[\erikwang2013\apidoc\annotation\Returned("pending_putaway", type:"int", desc:"待上架任务数")]
#[\erikwang2013\apidoc\annotation\Returned("pending_picks", type:"int", desc:"待拣货任务数")]
#[\erikwang2013\apidoc\annotation\Returned("pending_packs", type:"int", desc:"待打包任务数")]

    public function wms(Request $request): Response
    {
        return $this->success([
            'pending_receiving' => WmsReceiving::where('status', 0)->count(),
            'pending_putaway' => \app\model\WmsPutawayTask::where('status', 0)->count(),
            'pending_picks' => WmsPickTask::whereIn('status', [0, 1])->count(),
            'pending_packs' => \app\model\WmsPackTask::where('status', 0)->count(),
        ]);
    }

    /**
     * TMS 运输管理看板
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("TMS 运输管理看板")]
#[\erikwang2013\apidoc\annotation\Desc("获取 TMS 运输 KPI：待发运/运输中/今日妥投/异常运单数")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/dashboard/tms")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("仪表盘")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("pending_shipments", type:"int", desc:"待发运运单数")]
#[\erikwang2013\apidoc\annotation\Returned("in_transit", type:"int", desc:"运输中运单数")]
#[\erikwang2013\apidoc\annotation\Returned("delivered_today", type:"int", desc:"今日妥投运单数")]
#[\erikwang2013\apidoc\annotation\Returned("exception_shipments", type:"int", desc:"异常运单数")]

    public function tms(Request $request): Response
    {
        return $this->success([
            'pending_shipments' => TmsShipment::where('status', 0)->count(),
            'in_transit' => TmsShipment::whereIn('status', [1, 2])->count(),
            'delivered_today' => TmsShipment::where('status', 3)->whereDate('actual_delivery_at', date('Y-m-d'))->count(),
            'exception_shipments' => TmsShipment::where('status', 4)->count(),
        ]);
    }
}
