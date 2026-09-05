<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use app\process\WebSocket;
use app\queue\RedisQueue;
use support\Db;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

/**
 * Prometheus 指标端点
 * @Apidoc\Tag("监控指标")
 */#[\erikwang2013\apidoc\annotation\Tag("监控指标")]

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
     */#[\erikwang2013\apidoc\annotation\Title("Prometheus监控指标")]
#[\erikwang2013\apidoc\annotation\Desc("返回Prometheus text format格式的监控指标，包含活跃用户数、数据库/Redis连接状态、PHP版本等信息")]
#[\erikwang2013\apidoc\annotation\Url("/metrics")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("监控指标")]
#[\erikwang2013\apidoc\annotation\Returned("content-type", type:"string", desc:"text/plain; charset:utf-8")]
#[\erikwang2013\apidoc\annotation\Returned("body", type:"string", desc:"Prometheus text format指标数据")]

    public function index(Request $request): Response
    {
        $metrics = [];

        // HTTP 请求计数（简化版：按 webman worker 状态估算）
        $metrics[] = '# HELP open_admin_http_requests_total Total HTTP requests processed';
        $metrics[] = '# TYPE open_admin_http_requests_total counter';

        // 活跃用户数
        try {
            $activeUsers = AdminUser::whereDate('last_login_at', date('Y-m-d'))->count();
        } catch (Throwable $e) {
            // 指标端点不应因单一指标失败而崩溃，但需记录以便监控告警
            Log::warning('指标：活跃用户数统计失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            $activeUsers = 0;
        }
        $metrics[] = '# HELP open_admin_active_users Active users today';
        $metrics[] = '# TYPE open_admin_active_users gauge';
        $metrics[] = "open_admin_active_users {$activeUsers}";

        // 用户总数
        try {
            $totalUsers = AdminUser::count();
        } catch (Throwable $e) {
            // 指标端点不应因单一指标失败而崩溃，但需记录以便监控告警
            Log::warning('指标：用户总数统计失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            $totalUsers = 0;
        }
        $metrics[] = '# HELP open_admin_total_users Total registered users';
        $metrics[] = '# TYPE open_admin_total_users gauge';
        $metrics[] = "open_admin_total_users {$totalUsers}";

        // 数据库连接状态
        try {
            Db::select('SELECT 1');
            $dbStatus = 1;
        } catch (Throwable $e) {
            // 指标端点不应因单一指标失败而崩溃，但需记录以便监控告警
            Log::warning('指标：数据库连通性检查失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            $dbStatus = 0;
        }
        $metrics[] = '# HELP open_admin_db_up Database connection status (1=up, 0=down)';
        $metrics[] = '# TYPE open_admin_db_up gauge';
        $metrics[] = "open_admin_db_up {$dbStatus}";

        // Redis 连接状态
        try {
            Redis::ping();
            $redisStatus = 1;
        } catch (Throwable $e) {
            // 指标端点不应因单一指标失败而崩溃，但需记录以便监控告警
            Log::warning('指标：Redis 连通性检查失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            $redisStatus = 0;
        }
        $metrics[] = '# HELP open_admin_redis_up Redis connection status (1=up, 0=down)';
        $metrics[] = '# TYPE open_admin_redis_up gauge';
        $metrics[] = "open_admin_redis_up {$redisStatus}";

        // 队列积压数量（redis-queue 消费的 Redis LIST 长度，队列键如 erp:queue:default）
        try {
            $queueBacklog = (int)Redis::lLen(RedisQueue::key());
        } catch (Throwable $e) {
            // fail-open：获取失败输出 0 并记录日志，避免指标端点本身故障
            Log::error('获取队列积压指标失败: ' . $e->getMessage());
            $queueBacklog = 0;
        }
        $metrics[] = '# HELP open_admin_queue_backlog Redis queue backlog (pending message count)';
        $metrics[] = '# TYPE open_admin_queue_backlog gauge';
        $metrics[] = "open_admin_queue_backlog {$queueBacklog}";

        // WebSocket 在线连接数（WebSocket 进程维护，经 Redis 键跨进程同步；内部已 fail-open 为 0）
        $websocketConnections = WebSocket::getConnectionCount();
        $metrics[] = '# HELP open_admin_websocket_connections Current WebSocket online connection count';
        $metrics[] = '# TYPE open_admin_websocket_connections gauge';
        $metrics[] = "open_admin_websocket_connections {$websocketConnections}";

        // PHP 信息
        $metrics[] = '# HELP open_admin_info Application info';
        $metrics[] = '# TYPE open_admin_info gauge';
        $metrics[] = 'open_admin_info{version="1.0",php="' . PHP_VERSION . '"} 1';

        return response(implode("\n", $metrics) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
