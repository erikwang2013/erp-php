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
use support\Log;
use support\Redis;
use support\Request;
use support\Response;

/**
 * 仪表盘
 */
#[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
#[\erikwang2013\apidoc\annotation\Title('仪表盘总览')]
#[\erikwang2013\apidoc\annotation\Group('系统管理')]

class DashboardController extends BaseController
{
    /**
     * 仪表盘总览数据
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('仪表盘总览')]
    #[\erikwang2013\apidoc\annotation\Desc('获取经营总览数据，包含用户统计、趋势、分布和最近操作日志。数据缓存5分钟。')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('stats', type:'array', desc:'统计卡片数据')]
    #[\erikwang2013\apidoc\annotation\Returned('trends', type:'object', desc:'30日趋势数据')]
    #[\erikwang2013\apidoc\annotation\Returned('distribution', type:'object', desc:'分布数据')]
    #[\erikwang2013\apidoc\annotation\Returned('recent_logs', type:'array', desc:'最近操作日志')]

    public function index(Request $request): Response
    {
        // Redis 缓存 5 分钟，避免每次请求跑 5+ 条 SQL
        $cacheKey = 'dashboard:data';
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
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

        $this->cacheSet($cacheKey, 300, json_encode($data, JSON_UNESCAPED_UNICODE));

        return $this->success($data);
    }

    /**
     * 日期半开区间 [当天 00:00:00, 次日 00:00:00)。
     * 直接用列比较可走 B-Tree 索引；whereDate() 会对列套 DATE() 导致索引失效。
     *
     * @return array{0:string, 1:string}
     */
    public static function dayBounds(string $date): array
    {
        $start = date('Y-m-d 00:00:00', strtotime($date));

        return [$start, date('Y-m-d 00:00:00', strtotime($date . ' +1 day'))];
    }

    /** 缓存读取 fail-open：Redis 故障时返回 null，由调用方回源数据库 */
    private function cacheGet(string $key): ?string
    {
        try {
            $value = Redis::get($key);
        } catch (\Throwable $e) {
            Log::warning('仪表盘缓存读取失败，回源数据库: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** 缓存写入 fail-open：写失败只记日志，不影响本次响应 */
    private function cacheSet(string $key, int $ttl, string $payload): void
    {
        try {
            Redis::setex($key, $ttl, $payload);
        } catch (\Throwable $e) {
            Log::warning('仪表盘缓存写入失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }
    }

    private function getStats(string $today): array
    {
        [$todayStart, $tomorrowStart] = self::dayBounds($today);
        $totalUsers = AdminUser::count();
        $todayNew = AdminUser::query()->where('created_at', '>=', $todayStart)->where('created_at', '<', $tomorrowStart)->count();
        $todayActive = AdminUser::query()->where('last_login_at', '>=', $todayStart)->where('last_login_at', '<', $tomorrowStart)->count();
        $todayLogs = OperationLog::query()->where('created_at', '>=', $todayStart)->where('created_at', '<', $tomorrowStart)->count();

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

        [$rangeStart] = self::dayBounds($startOfRange);

        // 一次查询获取用户每日新增数，PHP 内累加
        $dailyNewUsers = AdminUser::query()->where('created_at', '>=', $rangeStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $cumulative = AdminUser::query()->where('created_at', '<', $rangeStart)->count();
        foreach ($dates as $date) {
            $cumulative += $dailyNewUsers[$date] ?? 0;
            $userGrowth[] = $cumulative;
        }

        // 一次查询获取操作日志每日数量
        $dailyLogs = OperationLog::query()->where('created_at', '>=', $rangeStart)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        foreach ($dates as $date) {
            $logCounts[] = $dailyLogs[$date] ?? 0;
        }

        return [
            'dates' => $dates,
            // series_key：稳定英文蛇形键，供前端 sparkline 摆脱 label 字符串匹配
            'series' => [
                ['series_key' => 'users', 'name' => '累计用户', 'data' => $userGrowth, 'color' => '#1677FF'],
                ['series_key' => 'logs', 'name' => '操作日志', 'data' => $logCounts, 'color' => '#52C41A'],
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
        [$todayStart, $tomorrowStart] = self::dayBounds(date('Y-m-d'));
        [$yesterdayStart] = self::dayBounds(date('Y-m-d', strtotime('-1 day')));
        $today = $modelClass::where('created_at', '>=', $todayStart)->where('created_at', '<', $tomorrowStart)->count();
        $yesterday = $modelClass::where('created_at', '>=', $yesterdayStart)->where('created_at', '<', $todayStart)->count();

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
    #[\erikwang2013\apidoc\annotation\Title('销售看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取销售看板数据，包含今日/本月销售额、客户排行、商机漏斗。数据缓存5分钟。')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/sales')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('today_sales', type:'float', desc:'今日销售额')]
    #[\erikwang2013\apidoc\annotation\Returned('month_sales', type:'float', desc:'本月销售额')]
    #[\erikwang2013\apidoc\annotation\Returned('top_customers', type:'array', desc:'客户排行')]
    #[\erikwang2013\apidoc\annotation\Returned('funnel', type:'array', desc:'商机漏斗')]
    #[\erikwang2013\apidoc\annotation\Returned('trend', type:'array', desc:'30日销售趋势')]
    #[\erikwang2013\apidoc\annotation\Returned('top_products', type:'array', desc:'Top5热销商品')]
    #[\erikwang2013\apidoc\annotation\Returned('status_distribution', type:'array', desc:'订单状态分布')]

    public function sales(Request $request): Response
    {
        $cacheKey = 'dashboard:sales:' . date('Y-m-d');
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $this->success(json_decode($cached, true));
        }

        $today = date('Y-m-d');
        [$todayStart, $tomorrowStart] = self::dayBounds($today);
        [$monthStart] = self::dayBounds(date('Y-m-01'));
        $startOfRange = date('Y-m-d', strtotime('-29 days'));
        $topCustomers = SalesOrder::selectRaw('customer_id, sum(total_amount) as total')
            ->where('ordered_at', '>=', $monthStart)
            ->where('ordered_at', '<', $tomorrowStart)
            ->where('status', '!=', 4)
            ->groupBy('customer_id')->orderByDesc('total')->limit(10)
            ->get();
        $customerNames = Customer::whereIn('id', $topCustomers->pluck('customer_id'))->pluck('name', 'id');
        // today_sales/month_sales/top_customers/funnel 双端（Flutter/HOS）零消费，保留待 BI 消费（勿删勿改结构）
        $data = [
            'today_sales' => SalesOrder::query()->where('ordered_at', '>=', $todayStart)->where('ordered_at', '<', $tomorrowStart)->where('status', '!=', 4)->sum('total_amount') ?? 0,
            'month_sales' => SalesOrder::query()->where('ordered_at', '>=', $monthStart)->where('ordered_at', '<', $tomorrowStart)->where('status', '!=', 4)->sum('total_amount') ?? 0,
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
        $this->cacheSet($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /** 近30日每日销售额（含0值补全） */
    private function getSalesTrend(string $startOfRange): array
    {
        [$rangeStart] = self::dayBounds($startOfRange);
        $dailySales = SalesOrder::query()->where('ordered_at', '>=', $rangeStart)
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
        [$rangeStart] = self::dayBounds($startOfRange);
        $top = SalesOrderItem::query()
            ->join('sales_order', 'sales_order.id', '=', 'sales_order_item.order_id')
            ->whereNull('sales_order.deleted_at')
            ->where('sales_order.ordered_at', '>=', $rangeStart)
            ->where('sales_order.status', '!=', 4)
            ->selectRaw(sprintf('%s.product_id, SUM(%s.quantity) as qty', db_prefix().'sales_order_item', db_prefix().'sales_order_item'))
            ->groupBy('sales_order_item.product_id')->orderByDesc('qty')->limit(5)
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
        [$rangeStart] = self::dayBounds($startOfRange);
        $counts = SalesOrder::query()->where('ordered_at', '>=', $rangeStart)
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
    #[\erikwang2013\apidoc\annotation\Title('库存看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取库存看板数据，包含库存总值、预警统计和出入库趋势。数据缓存5分钟。')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/inventory')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('total_value', type:'float', desc:'库存总值')]
    #[\erikwang2013\apidoc\annotation\Returned('alert_low', type:'int', desc:'低库存预警数')]
    #[\erikwang2013\apidoc\annotation\Returned('alert_high', type:'int', desc:'高库存预警数')]
    #[\erikwang2013\apidoc\annotation\Returned('flow_trend', type:'array', desc:'出入库趋势')]

    public function inventory(Request $request): Response
    {
        $cacheKey = 'dashboard:inventory:' . date('Y-m-d');
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $this->success(json_decode($cached, true));
        }

        [$monthStart] = self::dayBounds(date('Y-m-01'));
        $data = [
            'total_value' => Inventory::selectRaw('sum(quantity * cost_price) as total')->value('total') ?? 0,
            'alert_low' => InventoryAlertLog::where('alert_type', 1)->where('created_at', '>=', $monthStart)->count(),
            'alert_high' => InventoryAlertLog::where('alert_type', 2)->where('created_at', '>=', $monthStart)->count(),
            'flow_trend' => InventoryFlow::selectRaw('DATE(created_at) as date, direction, sum(quantity) as total')
                ->where('created_at', '>=', $monthStart)->groupBy('date', 'direction')->orderBy('date')->get(),
        ];
        $this->cacheSet($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /**
     * 财务看板
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('财务看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取财务看板数据，包含应收应付、本月收付款和现金余额。数据缓存5分钟。')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/finance')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('total_ar', type:'float', desc:'应收账款总额')]
    #[\erikwang2013\apidoc\annotation\Returned('total_ap', type:'float', desc:'应付账款总额')]
    #[\erikwang2013\apidoc\annotation\Returned('month_receipt', type:'float', desc:'本月收款')]
    #[\erikwang2013\apidoc\annotation\Returned('month_payment', type:'float', desc:'本月付款')]
    #[\erikwang2013\apidoc\annotation\Returned('cash_balance', type:'float', desc:'现金余额')]
    #[\erikwang2013\apidoc\annotation\Returned('ar_aging', type:'array', desc:'应收账龄汇总')]
    #[\erikwang2013\apidoc\annotation\Returned('ap_aging', type:'array', desc:'应付账龄汇总')]

    public function finance(Request $request): Response
    {
        $cacheKey = 'dashboard:finance:' . date('Y-m-d');
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $this->success(json_decode($cached, true));
        }

        [$monthStart] = self::dayBounds(date('Y-m-01'));
        $data = [
            'total_ar' => FinanceArAp::where('type', 1)->where('status', '!=', 2)->sum('amount') ?? 0,
            'total_ap' => FinanceArAp::where('type', 2)->where('status', '!=', 2)->sum('amount') ?? 0,
            'month_receipt' => FinanceReceipt::query()->where('received_at', '>=', $monthStart)->sum('amount') ?? 0,
            'month_payment' => FinancePayment::query()->where('paid_at', '>=', $monthStart)->sum('amount') ?? 0,
            'cash_balance' => FinanceBankAccount::sum('balance') ?? 0,
            'ar_aging' => $this->getAging(1),
            'ap_aging' => $this->getAging(2),
        ];
        $this->cacheSet($cacheKey, 300, json_encode($data));

        return $this->success($data);
    }

    /**
     * 应收/应付账龄汇总（按未核销余额分桶；无到期日视为未到期）。
     * 单条 SQL CASE 聚合，不再把全部未核销记录加载进 PHP 分桶。
     */
    private function getAging(int $type): array
    {
        // 基准日取 PHP 时区（Asia/Shanghai），避免 MySQL 时区不一致导致分桶偏移
        $today = date('Y-m-d');
        $sums = FinanceArAp::where('type', $type)
            ->where('status', '!=', 2)
            ->whereRaw('amount > settled_amount')
            ->selectRaw(
                "CASE
                    WHEN due_date IS NULL OR due_date >= ? THEN '未到期'
                    WHEN DATEDIFF(?, due_date) <= 30 THEN '逾期1-30天'
                    WHEN DATEDIFF(?, due_date) <= 60 THEN '逾期31-60天'
                    WHEN DATEDIFF(?, due_date) <= 90 THEN '逾期61-90天'
                    ELSE '逾期90+天'
                 END AS bucket, SUM(amount - settled_amount) AS total",
                [$today, $today, $today, $today]
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->toArray();

        return self::formatAging($sums);
    }

    /**
     * 账龄分桶格式化（纯函数，可单测）：固定 5 桶及中文名，缺失桶补 0。
     *
     * @param array<string, string|int|float> $sums bucket => 未核销余额合计
     * @return array<int, array{name:string, value:float}>
     */
    public static function formatAging(array $sums): array
    {
        $buckets = ['未到期' => '0', '逾期1-30天' => '0', '逾期31-60天' => '0', '逾期61-90天' => '0', '逾期90+天' => '0'];
        $result = [];
        foreach (array_keys($buckets) as $name) {
            $result[] = ['name' => $name, 'value' => (float) bc_round(bc_norm($sums[$name] ?? '0'), 2)];
        }

        return $result;
    }

    /**
     * OMS 订单履约看板
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('OMS 订单履约看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取 OMS 履约 KPI：待处理/拣货中订单、今日发货数与待处理退货单')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/oms')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_orders', type:'int', desc:'待处理订单数(待审核/待发货)')]
    #[\erikwang2013\apidoc\annotation\Returned('picking_orders', type:'int', desc:'拣货中订单数')]
    #[\erikwang2013\apidoc\annotation\Returned('shipped_today', type:'int', desc:'今日发货订单数')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_rma', type:'int', desc:'待处理退货单数')]

    public function oms(Request $request): Response
    {
        [$todayStart, $tomorrowStart] = self::dayBounds(date('Y-m-d'));

        return $this->success([
            'pending_orders' => OmsOrder::whereIn('fulfillment_status', [0, 1])->count(),
            'picking_orders' => OmsOrder::where('fulfillment_status', 2)->count(),
            'shipped_today' => OmsOrder::where('fulfillment_status', 4)
                ->where('updated_at', '>=', $todayStart)->where('updated_at', '<', $tomorrowStart)->count(),
            'pending_rma' => \app\model\OmsRma::where('status', 0)->count(),
        ]);
    }

    /**
     * WMS 仓储作业看板
     * })
     */
    #[\erikwang2013\apidoc\annotation\Title('WMS 仓储作业看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取 WMS 仓储作业 KPI：待收货/待上架/待拣货/待打包任务数')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/wms')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_receiving', type:'int', desc:'待收货单数')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_putaway', type:'int', desc:'待上架任务数')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_picks', type:'int', desc:'待拣货任务数')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_packs', type:'int', desc:'待打包任务数')]

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
    #[\erikwang2013\apidoc\annotation\Title('TMS 运输管理看板')]
    #[\erikwang2013\apidoc\annotation\Desc('获取 TMS 运输 KPI：待发运/运输中/今日妥投/异常运单数')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/dashboard/tms')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('仪表盘')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('pending_shipments', type:'int', desc:'待发运运单数')]
    #[\erikwang2013\apidoc\annotation\Returned('in_transit', type:'int', desc:'运输中运单数')]
    #[\erikwang2013\apidoc\annotation\Returned('delivered_today', type:'int', desc:'今日妥投运单数')]
    #[\erikwang2013\apidoc\annotation\Returned('exception_shipments', type:'int', desc:'异常运单数')]

    public function tms(Request $request): Response
    {
        [$todayStart, $tomorrowStart] = self::dayBounds(date('Y-m-d'));

        return $this->success([
            'pending_shipments' => TmsShipment::where('status', 0)->count(),
            'in_transit' => TmsShipment::whereIn('status', [1, 2])->count(),
            'delivered_today' => TmsShipment::where('status', 3)
                ->where('actual_delivery_at', '>=', $todayStart)->where('actual_delivery_at', '<', $tomorrowStart)->count(),
            'exception_shipments' => TmsShipment::where('status', 4)->count(),
        ]);
    }
}
