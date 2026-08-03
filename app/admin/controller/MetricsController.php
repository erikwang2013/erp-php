<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use support\Db;
use support\Redis;
use support\Request;
use support\Response;
use app\model\AdminUser;
use Throwable;

/**
 * Prometheus 指标端点
 * @Apidoc\Tag("监控指标")
 */
class MetricsController
{
    /**
     * Prometheus监控指标
     * @Apidoc\Title("Prometheus监控指标")
     * @Apidoc\Desc("返回Prometheus text format格式的监控指标，包含活跃用户数、数据库/Redis连接状态、PHP版本等信息")
     * @Apidoc\Url("/metrics")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("监控指标")
     * @Apidoc\Returned("content-type", type="string", desc="text/plain; charset=utf-8")
     * @Apidoc\Returned("body", type="string", desc="Prometheus text format指标数据")
     */
    public function index(Request $request): Response
    {
        $metrics = [];

        // HTTP 请求计数（简化版：按 webman worker 状态估算）
        $metrics[] = '# HELP open_admin_http_requests_total Total HTTP requests processed';
        $metrics[] = '# TYPE open_admin_http_requests_total counter';

        // 活跃用户数
        try {
            $activeUsers = AdminUser::whereDate('last_login_at', date('Y-m-d'))->count();
        } catch (Throwable) {
            $activeUsers = 0;
        }
        $metrics[] = "# HELP open_admin_active_users Active users today";
        $metrics[] = "# TYPE open_admin_active_users gauge";
        $metrics[] = "open_admin_active_users {$activeUsers}";

        // 用户总数
        try {
            $totalUsers = AdminUser::count();
        } catch (Throwable) {
            $totalUsers = 0;
        }
        $metrics[] = "# HELP open_admin_total_users Total registered users";
        $metrics[] = "# TYPE open_admin_total_users gauge";
        $metrics[] = "open_admin_total_users {$totalUsers}";

        // 数据库连接状态
        try {
            Db::select('SELECT 1');
            $dbStatus = 1;
        } catch (Throwable) {
            $dbStatus = 0;
        }
        $metrics[] = "# HELP open_admin_db_up Database connection status (1=up, 0=down)";
        $metrics[] = "# TYPE open_admin_db_up gauge";
        $metrics[] = "open_admin_db_up {$dbStatus}";

        // Redis 连接状态
        try {
            Redis::ping();
            $redisStatus = 1;
        } catch (Throwable) {
            $redisStatus = 0;
        }
        $metrics[] = "# HELP open_admin_redis_up Redis connection status (1=up, 0=down)";
        $metrics[] = "# TYPE open_admin_redis_up gauge";
        $metrics[] = "open_admin_redis_up {$redisStatus}";

        // PHP 信息
        $metrics[] = "# HELP open_admin_info Application info";
        $metrics[] = "# TYPE open_admin_info gauge";
        $metrics[] = 'open_admin_info{version="1.0",php="' . PHP_VERSION . '"} 1';

        return response(implode("\n", $metrics) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
