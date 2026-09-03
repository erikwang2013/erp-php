<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

use app\common\SnowflakeService;
use app\model\WebhookDeliveryLog;
use app\model\WebhookSubscription;
use support\Log;

/**
 * P0 Webhook 订阅投递服务
 *
 * 投递链路（同步实现 + DB 账本重试）：
 *   1) dispatch() 每次先顺带补偿到期的失败记录（retryDue，指数退避窗口已过者）；
 *   2) 命中事件的所有启用订阅（event 数组含 "*" 或该事件名）逐条同步 curl 投递；
 *   3) 每次尝试都落库 erp_webhook_delivery_log；失败时 next_retry_at 按
 *      base * 2^(attempts-1)（封顶 max_backoff_seconds）排期，达 max_attempts 后置 NULL 放弃；
 *   4) 订阅维度记录 last_status / last_delivered_at / 连续失败计数 failed_count（成功后归零）。
 *
 * 签名：X-Webhook-Signature = HMAC-SHA256(secret, 规范化 payload JSON)，正文与签名同字节序，
 * 接收方用同一 json_encode(JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) 结果复算即可验签。
 *
 * ponytail: P0 同步投递会阻塞调用方；升级异步时把事件塞入 RedisQueue 消费端复用 deliverEvent()，
 * 见 docs/queue.md（app/queue/RedisQueue）。
 */
class WebhookService
{
    /** 订阅测试事件名（订阅管理端 "发送测试" 动作使用） */
    public const EVENT_TEST = 'webhook.test';

    /**
     * 分发事件到所有匹配订阅（先补偿到期重试，再投递新事件）
     */
    public function dispatch(string $event, array $payload): void
    {
        try {
            $this->retryDue();
        } catch (\Throwable $e) {
            Log::error('[WebhookService] 补偿重试投递失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        $subscriptions = WebhookSubscription::query()
            ->where('enabled', 1)
            ->orderBy('id')
            ->get()
            ->filter(fn (WebhookSubscription $sub) => $this->matches($sub, $event));

        foreach ($subscriptions as $sub) {
            try {
                $this->deliverEvent($sub, $event, $payload);
            } catch (\Throwable $e) {
                // 单订阅失败不阻断其他订阅的投递
                Log::error('[WebhookService] 事件投递异常 event=' . $event
                    . ' sub=' . $sub->id . ': ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            }
        }
    }

    /**
     * 向单个订阅发送测试事件（不校验订阅是否启用，供管理端手动测试；会真实落库并记录日志）
     *
     * @return array{status: string, http_code: int|null, response_summary: string}
     */
    public function testDeliver(WebhookSubscription $sub): array
    {
        $payload = [
            'event' => self::EVENT_TEST,
            'message' => '这是一条 Webhook 测试事件',
            'time' => date('Y-m-d H:i:s'),
        ];
        $this->deliverEvent($sub, self::EVENT_TEST, $payload);

        return [
            'status' => $sub->last_status,
            'http_code' => $this->lastLog($sub->id)?->http_code,
            'response_summary' => $this->lastLog($sub->id)?->response_summary ?? '',
        ];
    }

    /**
     * 事件是否被订阅（event 数组含 "*" 通配即全部命中）
     */
    private function matches(WebhookSubscription $sub, string $event): bool
    {
        $events = is_array($sub->event) ? $sub->event : [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }

    /**
     * 新事件投递：新建 pending 日志记录并执行首次尝试
     */
    private function deliverEvent(WebhookSubscription $sub, string $event, array $payload): void
    {
        $log = new WebhookDeliveryLog();
        $log->id = SnowflakeService::generate();
        $log->subscription_id = $sub->id;
        $log->event = $event;
        $log->payload = $payload;
        $log->status = 'pending';
        $log->attempts = 0;
        $log->save();

        $this->attempt($log, $sub);
    }

    /**
     * 执行一次真实投递并更新日志/订阅状态（attempts 先自增；仅新事件的首次失败计入 failed_count）
     */
    private function attempt(WebhookDeliveryLog $log, WebhookSubscription $sub): void
    {
        $firstAttempt = (int) $log->attempts === 0;
        $log->attempts = (int) $log->attempts + 1;

        $secret = (string) ($sub->secret ?: '');
        $body = $this->encodePayload($log->payload ?: []);
        $result = $this->httpPost($sub->target_url, $body, $secret, (string) $log->event);

        $success = ($result['code'] ?? 0) >= 200 && ($result['code'] ?? 0) < 300;
        $log->http_code = ($result['code'] ?? 0) > 0 ? (int) $result['code'] : null;
        $log->response_summary = $this->summarize($result);

        if ($success) {
            $log->status = 'success';
            $log->next_retry_at = null;
            $sub->last_status = 'success';
            $sub->last_delivered_at = date('Y-m-d H:i:s');
            $sub->failed_count = 0;
        } else {
            $log->status = 'failed';
            $log->next_retry_at = $this->nextRetryAt((int) $log->attempts);
            $sub->last_status = 'failed';
            if ($firstAttempt) {
                $sub->failed_count = (int) $sub->failed_count + 1;
            }
        }

        $log->save();
        $sub->save();
    }

    /**
     * 补偿重试：捞取到期（next_retry_at <= now）且未达最大次数的失败日志，逐条重试
     */
    private function retryDue(): void
    {
        $maxAttempts = (int) config('openapi.webhook.max_attempts', 5);
        $logs = WebhookDeliveryLog::query()
            ->where('status', 'failed')
            ->where('attempts', '<', $maxAttempts)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', date('Y-m-d H:i:s'))
            ->orderBy('next_retry_at')
            ->limit((int) config('openapi.webhook.sweep_limit', 20))
            ->get();

        foreach ($logs as $log) {
            try {
                $sub = WebhookSubscription::find($log->subscription_id);
                if (!$sub || (int) $sub->enabled !== 1) {
                    continue; // 订阅已删除或停用：保留原排期，恢复启用后由下一次 dispatch 补偿
                }
                $this->attempt($log, $sub);
            } catch (\Throwable $e) {
                Log::error('[WebhookService] 重试投递异常 log=' . $log->id . ': '
                    . $e->getMessage() . ' | TraceId: ' . trace_id());
            }
        }
    }

    /**
     * 指数退避排期：delay = base * 2^(attempts-1)（封顶 max_backoff_seconds）；达最大次数返回 null（放弃）
     */
    private function nextRetryAt(int $attempts): ?string
    {
        $maxAttempts = (int) config('openapi.webhook.max_attempts', 5);
        if ($attempts >= $maxAttempts) {
            return null;
        }

        $delay = (int) config('openapi.webhook.retry_base_seconds', 60)
            * (2 ** max($attempts - 1, 0));
        $delay = min($delay, (int) config('openapi.webhook.max_backoff_seconds', 3600));

        return date('Y-m-d H:i:s', time() + $delay);
    }

    /**
     * 规范化 payload JSON（与签名使用同一字节序列，保证接收方验签一致）
     */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('payload json_encode 失败: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * 响应/错误摘要（截断 500 字符）
     */
    private function summarize(array $result): string
    {
        if (!empty($result['error'])) {
            return 'curl: ' . mb_substr((string) $result['error'], 0, 500);
        }

        $body = trim((string) ($result['body'] ?? ''));
        if ($body === '') {
            return 'HTTP ' . (int) ($result['code'] ?? 0) . '（空响应体）';
        }

        return 'HTTP ' . (int) ($result['code'] ?? 0) . ' ' . mb_substr($body, 0, 500);
    }

    /**
     * 最近一条投递日志
     */
    private function lastLog(int $subscriptionId): ?WebhookDeliveryLog
    {
        return WebhookDeliveryLog::query()->where('subscription_id', $subscriptionId)->orderBy('id', 'desc')->first();
    }

    /**
     * 计算 X-Webhook-Signature（公开静态方法：接收方复算验签 / 测试断言复用同一实现）
     */
    public static function signature(string $payloadJson, string $secret): string
    {
        return hash_hmac('sha256', $payloadJson, $secret);
    }

    /**
     * 同步 POST 投递（curl：字节级可控正文，HMAC 与正文严格同字节；失败返回 code=0 + error）
     *
     * @return array{code: int, body: string, error: string}
     */
    protected function httpPost(string $url, string $body, string $secret, string $event): array
    {
        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Signature: ' . self::signature($body, $secret),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => (int) config('openapi.webhook.http_timeout', 5),
            CURLOPT_USERAGENT => (string) config('openapi.webhook.user_agent', 'erp-webhook/1.0'),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = (string) curl_error($ch);
            $code = 0;
        } else {
            $error = '';
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        curl_close($ch);

        return ['code' => $code, 'body' => $raw === false ? '' : (string) $raw, 'error' => $error];
    }
}
