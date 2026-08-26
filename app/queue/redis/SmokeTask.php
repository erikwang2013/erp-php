<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\queue\redis;

use app\queue\RedisQueue;
use support\Redis;

/**
 * 队列冒烟任务
 *
 * 用于验证「投递 → 消费」端到端链路是否可用：
 * - send()   : 生产者，把任务投递到 Redis 队列；
 * - consume(): 消费者，由 redis-queue 消费进程回调，把执行日志写入 runtime/logs/
 *              并累加 Redis 计数器，二者均可用于观察消费是否发生。
 *
 * 业务代码中投递示例：
 *   RedisQueue::push(SmokeTask::class, 'consume', ['trigger' => 'business']);
 */
class SmokeTask
{
    /** 冒烟计数 Redis 键（可用 `redis-cli GET erp:queue:smoke:count` 观察） */
    public const COUNTER_KEY = 'erp:queue:smoke:count';

    /**
     * 投递一条冒烟任务
     *
     * @param array $data 冒烟数据（如触发来源）
     */
    public static function send(array $data = []): bool
    {
        return RedisQueue::push(self::class, 'consume', $data);
    }

    /**
     * 消费冒烟任务：异步写操作日志到 runtime/logs/，并累加 Redis 计数器
     *
     * @param array $data 投递时携带的数据
     */
    public static function consume(array $data = []): void
    {
        $line = sprintf(
            "[%s] queue-smoke consumed: %s\n",
            date('Y-m-d H:i:s'),
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        $logDir = runtime_path() . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logDir . '/queue-smoke-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);

        // 同时写入 Redis 计数器，便于在无文件系统权限的环境观察
        try {
            Redis::incr(self::COUNTER_KEY);
        } catch (\Throwable) {
            // Redis 异常不影响文件日志落盘
        }
    }
}
