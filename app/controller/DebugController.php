<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller;

use app\queue\redis\SmokeTask;
use app\queue\RedisQueue;
use support\Request;
use support\Response;

/**
 * 调试控制器（TODO: 队列冒烟联调完成后整体移除）
 */
class DebugController
{
    /**
     * 投递一条队列冒烟任务（调试用）
     *
     * TODO(调试): 队列端到端联调完成后，删除本方法及 config/route.php 中的对应路由。
     */
    public function queueSmoke(Request $request): Response
    {
        $ok = SmokeTask::send([
            'trigger' => 'debug-route',
            'time' => time(),
        ]);

        return json([
            'code' => $ok ? 0 : 1,
            'message' => $ok ? '队列冒烟任务已投递' : 'Redis 不可用，投递失败',
            'data' => [
                'queue_key' => RedisQueue::key(),
                'observe' => 'tail -f runtime/logs/queue-smoke-$(date +%F).log 或 redis-cli GET erp:queue:smoke:count',
            ],
        ]);
    }
}
