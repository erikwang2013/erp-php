<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

use app\common\SnowflakeService;
use app\model\WebhookDeliveryLog;
use app\model\WebhookSubscription;
use app\queue\redis\WebhookTask;
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
 * 已接通的事件（订阅方按事件名或 "*" 订阅；无事件目录接口，事件名即下列字符串约定）：
 *   approval.submitted / approval.approved / approval.rejected —— app/controller/workflow/ApprovalController.php
 *   webhook.test —— 管理端「发送测试事件」（WebhookController::test）
 *
 * 投递方式：
 *   dispatchAsync() —— 业务侧默认入口，只入队（app/queue/redis/WebhookTask），由消费进程执行 dispatch()；
 *   dispatch()      —— 同步投递，管理端「测试」要即时结果、集成测试也直接调它，队列消费端同样复用它。
 *
 * ponytail: dispatch() 同步投递会阻塞调用方（每订阅 curl 最长 http_timeout）；换官方 webman/redis-queue
 * 扩展时只需替换消费进程 handler，任务类目录不变，见 docs/queue.md（app/queue/RedisQueue）。
 */
class WebhookService
{
    /** 订阅测试事件名（订阅管理端 "发送测试" 动作使用） */
    public const EVENT_TEST = 'webhook.test';

    /** filter_var 的 NO_RES_RANGE 未覆盖的运营商级 NAT / 基准测试 / 文档 / 保留网段 */
    private const BLOCKED_CIDRS = [
        '100.64.0.0/10',
        '198.18.0.0/15',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '240.0.0.0/4',
        '2001:db8::/32',
    ];

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
     * 异步分发事件：只把事件投进 Redis 队列，真实投递与 retryDue 补偿都由消费进程做
     * （见 app/queue/redis/WebhookTask）；入队失败只记日志，不影响调用方主流程
     */
    public function dispatchAsync(string $event, array $payload): void
    {
        if (!WebhookTask::send($event, $payload)) {
            Log::error('[WebhookService] 事件入队失败 event=' . $event . ' | TraceId: ' . trace_id());
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
     * Webhook URL 安全校验：仅允许公网 http(s)，端口由 openapi.webhook.allowed_ports 控制（默认 80/443）。
     */
    public static function isSafeUrl(string $url): bool
    {
        return self::resolveSafeTarget($url) !== null;
    }

    /**
     * 解析并校验 Webhook 目标，返回投递时固定使用的 host/port/公网 IP 列表；不安全返回 null。
     *
     * 拒绝 localhost/回环/私网/链路本地（含 169.254.169.254 元数据地址）/IPv6 唯一本地地址；
     * 域名同时解析 A（gethostbynamel）与 AAAA（dns_get_record），任一记录非公网即拒绝，无记录按不安全处理。
     * httpPost 用返回的 IP 设置 CURLOPT_RESOLVE，消除校验与连接之间的 DNS rebinding TOCTOU。
     *
     * @return array{host: string, port: int, ips: list<string>}|null
     */
    public static function resolveSafeTarget(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $allowedPorts = config('openapi.webhook.allowed_ports', [80, 443]);
        if (!is_array($allowedPorts)) {
            $allowedPorts = [80, 443];
        }
        if (!in_array($port, array_map('intval', $allowedPorts), true)) {
            return null;
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host) ? ['host' => $host, 'port' => $port, 'ips' => [$host]] : null;
        }

        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        $ips = array_values(array_unique($ips));
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return null;
            }
        }

        return ['host' => $host, 'port' => $port, 'ips' => $ips];
    }

    /**
     * 生成 CURLOPT_RESOLVE 条目；IPv6 地址按 curl 语法用方括号包裹。
     *
     * @param array{host: string, port: int, ips: list<string>} $target
     * @return list<string>
     */
    public static function resolveEntries(array $target): array
    {
        $entries = [];
        foreach ($target['ips'] as $ip) {
            $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $entries[] = $target['host'] . ':' . $target['port'] . ':' . $address;
        }

        return $entries;
    }

    /**
     * 公网单播地址判定：私网、保留、回环、链路本地、运营商级 NAT 等均不通过。
     */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        foreach (self::BLOCKED_CIDRS as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $packedIp = inet_pton($ip);
        $packedNetwork = inet_pton($network);
        if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }
        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        if (strncmp($packedIp, $packedNetwork, $fullBytes) !== 0) {
            return false;
        }
        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }

    /**
     * 同步 POST 投递（curl：字节级可控正文，HMAC 与正文严格同字节；失败返回 code=0 + error）
     *
     * @return array{code: int, body: string, error: string}
     */
    protected function httpPost(string $url, string $body, string $secret, string $event): array
    {
        // 投递前再校验一次：订阅可能是历史数据，或校验后被改成内网地址
        $target = self::resolveSafeTarget($url);
        if ($target === null) {
            return ['code' => 0, 'body' => '', 'error' => 'blocked: unsafe webhook URL'];
        }

        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Signature: ' . self::signature($body, $secret),
        ];

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => (int) config('openapi.webhook.http_timeout', 5),
            CURLOPT_USERAGENT => (string) config('openapi.webhook.user_agent', 'erp-webhook/1.0'),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        // 域名投递固定已校验 IP；IP 字面量本身无需 RESOLVE
        if (filter_var($target['host'], FILTER_VALIDATE_IP) === false) {
            $options[CURLOPT_RESOLVE] = self::resolveEntries($target);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

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
