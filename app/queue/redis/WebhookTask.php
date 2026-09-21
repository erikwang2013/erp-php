<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\queue\redis;

use app\queue\RedisQueue;
use app\service\notification\WebhookService;
use support\Log;

/**
 * Webhook 事件异步投递任务
 *
 * 生产者：WebhookService::dispatchAsync()（HTTP 请求内只入队，不跑同步 curl）；
 * 消费者：redis-queue 消费进程回调 consume()，执行 WebhookService::dispatch()
 *        （含 dispatch 内的 retryDue 补偿，即到期失败记录由消费端顺带重试）。
 *
 * 本类必须直接放在 app/queue/redis/ 下：消费进程的 consumer_dir 非递归扫描，
 * 且只放行扫描到的任务类（见 app/process/QueueConsumer.php）。
 */
class WebhookTask
{
    /**
     * 投递一条异步 Webhook 事件
     */
    public static function send(string $event, array $payload): bool
    {
        return RedisQueue::push(self::class, 'consume', [
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    /**
     * 消费：执行真实投递
     *
     * 异常就地吞掉并记日志，不抛给队列层：WebhookService 自带落库账本 + 指数退避重试，
     * 再叠一层队列重试只会让同一事件重复投递。
     *
     * @param array $data 投递时携带的数据（event / payload）
     */
    public static function consume(array $data): void
    {
        $event = (string) ($data['event'] ?? '');
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        if ($event === '') {
            Log::error('webhook-queue: 消息缺少 event，已丢弃: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

            return;
        }

        try {
            (new WebhookService())->dispatch($event, $payload);
        } catch (\Throwable $e) {
            Log::error('webhook-queue: 事件投递失败 event=' . $event . ': ' . $e->getMessage());
        }
    }
}
