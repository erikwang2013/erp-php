<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\queue;

use support\Log;
use support\Redis;

/**
 * 轻量 Redis 队列工具（最小实现）
 *
 * 说明：项目当前未安装 webman/redis-queue 扩展包（composer show 无该包），
 * 这里基于 Redis LIST（LPUSH 入队 / LPOP 出队）实现最小可用的队列，
 * 供冒烟任务与后续业务任务使用。升级为官方扩展的方案见 docs/queue.md。
 *
 * 消息体约定（与 webman/redis-queue 的作业格式保持一致，便于将来平滑切换）：
 * {
 *   "class":  "任务类全名（必须位于消费进程的 consumer_dir 目录下）",
 *   "method": "消费方法名，默认 consume",
 *   "data":   "业务数据（数组）"
 * }
 */
class RedisQueue
{
    /** 队列键前缀 */
    public const KEY_PREFIX = 'erp:queue:';

    /**
     * 计算 Redis 队列键（默认队列名取自 config/queue.php 的 redis 连接）
     */
    public static function key(string $queue = ''): string
    {
        $queue = $queue ?: (string)config('queue.connections.redis.queue', 'default');

        return self::KEY_PREFIX . $queue;
    }

    /**
     * 投递一个队列任务（生产者入口）
     *
     * @param string $class  任务类全名（如 \app\queue\redis\SmokeTask::class）
     * @param string $method 消费方法名，默认 consume
     * @param array  $data   业务数据
     * @param string $queue  队列名，空则使用默认队列
     */
    public static function push(string $class, string $method, array $data = [], string $queue = ''): bool
    {
        $job = json_encode([
            'class' => $class,
            'method' => $method,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if ($job === false) {
            return false;
        }

        try {
            return (bool)Redis::lPush(self::key($queue), $job);
        } catch (\Throwable $e) {
            // Redis 不可用时记录错误，避免生产者侧异常上抛影响业务主流程
            Log::error('队列投递失败: ' . $e->getMessage());

            return false;
        }
    }
}
