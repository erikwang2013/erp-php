<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\middleware\OpenApiAuth;
use app\model\OpenApiApp;
use app\model\WebhookDeliveryLog;
use app\model\WebhookSubscription;
use app\service\notification\WebhookService;
use PHPUnit\Framework\Attributes\Group;
use support\Redis;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * P0-3 OpenAPI/Webhook 集成测试（真库 + 可用时真 Redis）
 *
 * 覆盖：API Key 认证（签名/时间戳/禁用/scope）、Redis 限流（fail-open 降级）、
 * Webhook 订阅投递链路（成功/不匹配/通配/失败退避重试/放弃/测试事件）。
 * 依赖 install.sql 中 P0 OpenAPI 段建出的 erp_openapi_app / erp_webhook_subscription /
 * erp_webhook_delivery_log 三张表；用例结束后清理自身产生的数据。
 */
#[Group('integration')]
class OpenApiIntegrationTest extends IntegrationTestCase
{
    private const APP_ID = 900001;
    private const APP_ID_2 = 900002;
    private const APP_ID_3 = 900003;
    private const SECRET = 'test-secret-0123456789abcdef0123456789abcdef';
    private const KEY_1 = 'ak_test1';
    private const KEY_2 = 'ak_test2';
    private const KEY_3 = 'ak_test_disabled';

    private static array $subIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        OpenApiStubService::reset();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    // ---------- 认证 / 限流 ----------

    public function testSecretEncryptedAtRestAndHashIntegrity(): void
    {
        $this->newApp(self::APP_ID, self::KEY_1, 1, null);

        $fresh = OpenApiApp::find(self::APP_ID);
        $raw = $fresh->getAttributes()['app_secret'] ?? '';
        $this->assertNotSame(self::SECRET, (string) $raw, 'app_secret 必须以密文落库');
        $this->assertSame(hash('sha256', self::SECRET), $fresh->app_secret_hash, '完整性校验位须与明文一致');
        $this->assertSame(self::SECRET, $fresh->app_secret, '读取时须解密还原明文');
    }

    public function testValidSignedRequestPassesAndInjectsApp(): void
    {
        $this->newApp(self::APP_ID, self::KEY_1, 1, null);

        $res = $this->process($this->rawRequest(
            'GET',
            '/open/v1/apps/' . self::APP_ID,
            '',
            $this->signedHeaders(self::KEY_1, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID, '')
        ));

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(
            (string) self::APP_ID,
            $this->body($res)['data']['injected'] ?? null,
            '$request->openapiApp 须注入当前请求方应用'
        );
    }

    public function testScopeRestrictsAccess(): void
    {
        // 仅授权自身信息路径：命中范围放行，范围外路径在中间件层被拒
        $this->newApp(self::APP_ID_2, self::KEY_2, 1, ['/open/v1/apps/' . self::APP_ID_2]);

        $allowed = $this->process($this->rawRequest(
            'GET',
            '/open/v1/apps/' . self::APP_ID_2,
            '',
            $this->signedHeaders(self::KEY_2, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID_2, '')
        ));
        $this->assertSame(200, $allowed->getStatusCode());

        $denied = $this->process($this->rawRequest(
            'GET',
            '/open/v1/apps/' . self::APP_ID,
            '',
            $this->signedHeaders(self::KEY_2, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID, '')
        ));
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertStringContainsString('授权范围', $this->body($denied)['message']);
    }

    public function testTamperedBodyRejected(): void
    {
        $this->newApp(self::APP_ID, self::KEY_1, 1, null);
        $body = '{"order_no":"SO001"}';

        // 签名针对原始 body，随后篡改 body → 签名不匹配
        $headers = $this->signedHeaders(self::KEY_1, self::SECRET, 'POST', '/open/v1/orders', $body);
        $res = $this->process($this->rawRequest('POST', '/open/v1/orders', '{"order_no":"SO002"}', $headers));

        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsString('签名校验失败', $this->body($res)['message']);
    }

    public function testStaleTimestampRejected(): void
    {
        $this->newApp(self::APP_ID, self::KEY_1, 1, null);

        foreach ([time() - 600, time() + 600] as $ts) {
            $res = $this->process($this->rawRequest(
                'GET',
                '/open/v1/apps/' . self::APP_ID,
                '',
                $this->signedHeaders(self::KEY_1, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID, '', $ts)
            ));
            $this->assertSame(401, $res->getStatusCode());
            $this->assertStringContainsString('时间戳', $this->body($res)['message']);
        }
    }

    public function testMissingOrDisabledKeyRejected(): void
    {
        $this->newApp(self::APP_ID, self::KEY_1, 1, null);
        $this->newApp(self::APP_ID_3, self::KEY_3, 0, null);

        // 完全缺头
        $res = $this->process($this->rawRequest('GET', '/open/v1/apps/' . self::APP_ID, '', []));
        $this->assertSame(401, $res->getStatusCode());

        // 已禁用应用的 key
        $res = $this->process($this->rawRequest(
            'GET',
            '/open/v1/apps/' . self::APP_ID_3,
            '',
            $this->signedHeaders(self::KEY_3, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID_3, '')
        ));
        $this->assertSame(401, $res->getStatusCode());
        $this->assertStringContainsString('API Key', $this->body($res)['message']);
    }

    public function testRateLimit429(): void
    {
        $this->requireTestRedis();
        $this->newApp(self::APP_ID_2, self::KEY_2, 1, null);

        $key = OpenApiAuth::rateKey(self::KEY_2);
        $now = (int) (microtime(true) * 1000);
        for ($i = 0; $i < 60; $i++) {
            // 预置 60 条窗口内滑动计数（与中间件同款 ZSET 结构），使下一次请求超限
            Redis::zadd($key, $now - 500 + $i, ($now - 500 + $i) . '.' . $i);
        }
        Redis::expire($key, 120);

        $res = $this->process($this->rawRequest(
            'GET',
            '/open/v1/apps/' . self::APP_ID_2,
            '',
            $this->signedHeaders(self::KEY_2, self::SECRET, 'GET', '/open/v1/apps/' . self::APP_ID_2, '')
        ));

        $this->assertSame(429, $res->getStatusCode());
        $this->assertStringContainsString('请求过于频繁', $this->body($res)['message']);
        $retryAfter = $res->getHeader('Retry-After');
        $this->assertGreaterThanOrEqual(
            1,
            (int) (is_array($retryAfter) ? ($retryAfter[0] ?? 0) : $retryAfter),
            '须携带 Retry-After 响应头'
        );
        $remaining = $res->getHeader('X-RateLimit-Remaining');
        $this->assertSame(
            '0',
            is_array($remaining) ? ($remaining[0] ?? '') : $remaining,
            '超限时须携带 X-RateLimit-Remaining: 0（与全局 RateLimit 头约定一致）'
        );
    }

    // ---------- Webhook 订阅投递 ----------

    public function testWebhookSuccessDelivery(): void
    {
        $payload = ['event' => 'order.created', 'order_no' => 'SO20260904001',
            'amount' => '1888.88', 'remark' => '测试订单', 'url' => 'https://hook.example.com/cb?a=1&b=2'];
        $this->newSub(910001, self::APP_ID, ['order.created'], 'https://hook.example.com/order');

        (new OpenApiStubService())->dispatch('order.created', $payload);

        $call = OpenApiStubService::$calls[0] ?? null;
        $this->assertNotNull($call, '命中订阅须发起 HTTP 投递');
        $this->assertSame('https://hook.example.com/order', $call['url']);
        $this->assertSame('wh-secret-0123456789abcdef', $call['secret']);
        $this->assertSame('order.created', $call['event']);
        // 规范化 JSON 须原样（unicode/斜杠不转义），与验签字节序列一致
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSame($canonical, $call['body']);
        $this->assertStringContainsString('测试订单', $call['body'], '中文须以 UTF-8 明文投递');
        $this->assertSame(WebhookService::signature($canonical, 'wh-secret-0123456789abcdef'), $call['signature']);

        $log = $this->onlyLog(910001);
        $this->assertSame('success', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertSame(200, $log->http_code);
        $this->assertNull($log->next_retry_at);
        $this->assertSame('HTTP 200 {"ok":true}', $log->response_summary);

        $sub = WebhookSubscription::find(910001);
        $this->assertSame('success', $sub->last_status);
        $this->assertSame(0, $sub->failed_count);
        $this->assertNotNull($sub->last_delivered_at);
    }

    public function testWebhookEventMismatchNoDelivery(): void
    {
        $this->newSub(910002, self::APP_ID, ['order.created'], 'https://hook.example.com/order');

        (new OpenApiStubService())->dispatch('stock.low', ['sku' => 'A001']);

        $this->assertSame([], OpenApiStubService::$calls, '事件不匹配时不得投递');
        $this->assertSame(
            0,
            WebhookDeliveryLog::query()->where('subscription_id', 910002)->count(),
            '事件不匹配时不得落日志'
        );
    }

    public function testWildcardSubscriptionMatchesAll(): void
    {
        $this->newSub(910003, self::APP_ID, ['*'], 'https://hook.example.com/wildcard');

        (new OpenApiStubService())->dispatch('any.custom.event', ['x' => 1]);

        $this->assertCount(1, OpenApiStubService::$calls);
        $this->assertSame('any.custom.event', OpenApiStubService::$calls[0]['event']);
        $log = $this->onlyLog(910003);
        $this->assertSame('success', $log->status);
    }

    public function testWebhookDeadPortFailureState(): void
    {
        // 真实 curl 打 127.0.0.1:1（必然连接失败），验证失败账本与退避排期
        $this->newSub(910004, self::APP_ID, ['order.created'], 'http://127.0.0.1:1/hook');

        (new WebhookService())->dispatch('order.created', ['order_no' => 'SO001']);

        $log = $this->onlyLog(910004);
        $this->assertSame('failed', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertNull($log->http_code);
        $this->assertNotNull($log->next_retry_at, '未达最大次数须排期重试');
        $this->assertStringContainsString('curl:', (string) $log->response_summary);

        $sub = WebhookSubscription::find(910004);
        $this->assertSame('failed', $sub->last_status);
        $this->assertSame(1, $sub->failed_count);
    }

    public function testWebhookRetrySweepDoesNotInflateFailedCount(): void
    {
        $this->newSub(910005, self::APP_ID, ['order.created'], 'https://hook.example.com/order');
        OpenApiStubService::$fail = true;

        (new WebhookService())->dispatch('order.created', ['order_no' => 'SO001']);
        $log = $this->onlyLog(910005);
        $this->assertSame(1, $log->attempts);

        // 到期后任意一次 dispatch 顺带补偿重试；新事件与订阅不匹配，不产生新日志
        $this->forcePast($log);
        (new WebhookService())->dispatch('unrelated.event', []);
        $log = $this->onlyLog(910005);

        $this->assertSame(2, $log->attempts, '补偿重试须推进 attempts');
        $sub = WebhookSubscription::find(910005);
        $this->assertSame(1, $sub->failed_count, '重试失败不得重复累计 failed_count');
        $this->assertNotNull($log->next_retry_at);
    }

    public function testWebhookBackoffAbandonAndSuccessReset(): void
    {
        $this->newSub(910006, self::APP_ID, ['order.created'], 'https://hook.example.com/order');
        OpenApiStubService::$fail = true;

        (new OpenApiStubService())->dispatch('order.created', ['order_no' => 'SO001']);
        $log = $this->onlyLog(910006);

        // 再失败 4 次（共 5 次尝试）→ 达 max_attempts，放弃重试（next_retry_at 置 NULL）
        for ($i = 0; $i < 4; $i++) {
            $this->forcePast($log);
            (new OpenApiStubService())->dispatch('unrelated.event', []);
            $log = $this->onlyLog(910006);
        }
        $this->assertSame(5, $log->attempts);
        $this->assertNull($log->next_retry_at, '达最大尝试次数后不得再排期');

        // 下一次成功投递须将订阅账本复位
        OpenApiStubService::reset();
        (new OpenApiStubService())->dispatch('order.created', ['order_no' => 'SO002']);
        $sub = WebhookSubscription::find(910006);
        $this->assertSame('success', $sub->last_status);
        $this->assertSame(0, $sub->failed_count);
        $latest = WebhookDeliveryLog::query()->where('subscription_id', 910006)->orderBy('id', 'desc')->first();
        $this->assertNotNull($latest);
        $this->assertSame('success', $latest->status);
    }

    public function testDeliverSendsTestEvent(): void
    {
        $sub = $this->newSub(910007, self::APP_ID, ['order.created'], 'https://hook.example.com/order');

        $result = (new OpenApiStubService())->testDeliver($sub);

        $this->assertSame('success', $result['status']);
        $this->assertSame(200, $result['http_code']);
        $this->assertCount(1, OpenApiStubService::$calls);
        $this->assertSame(WebhookService::EVENT_TEST, OpenApiStubService::$calls[0]['event']);

        $log = $this->onlyLog(910007);
        $this->assertSame(WebhookService::EVENT_TEST, $log->event);
        $this->assertSame(WebhookService::EVENT_TEST, $log->payload['event'] ?? null);
        $this->assertStringContainsString('这是一条 Webhook 测试事件', (string) ($log->payload['message'] ?? ''));
    }

    // ---------- 测试辅助 ----------

    private function newApp(int $id, string $appKey, int $status, ?array $scopes): OpenApiApp
    {
        $app = new OpenApiApp();
        $app->id = $id;
        $app->app_name = '集成测试应用 ' . $id;
        $app->app_key = $appKey;
        $app->app_secret = self::SECRET; // Encryptable cast 自动加密入库
        $app->app_secret_hash = hash('sha256', self::SECRET);
        $app->scopes = $scopes;
        $app->status = $status;
        $app->save();

        return $app;
    }

    private function newSub(int $id, int $appId, array $events, string $targetUrl): WebhookSubscription
    {
        self::$subIds[] = $id;
        $sub = new WebhookSubscription();
        $sub->id = $id;
        $sub->app_id = $appId;
        $sub->event = $events;
        $sub->target_url = $targetUrl;
        $sub->secret = 'wh-secret-0123456789abcdef'; // Encryptable cast 自动加密入库
        $sub->enabled = 1;
        $sub->save();

        return $sub;
    }

    /**
     * 构造原始 HTTP 缓冲 Request（与 TrackingSignatureFixTest 同法）
     */
    private function rawRequest(string $method, string $path, string $body, array $headers): Request
    {
        $buffer = $method . ' ' . $path . " HTTP/1.1\r\nHost: localhost\r\n";
        foreach ($headers as $name => $value) {
            $buffer .= $name . ': ' . $value . "\r\n";
        }
        if ($body !== '') {
            $buffer .= 'Content-Type: application/json' . "\r\n";
        }
        $buffer .= 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body;

        return new Request($buffer);
    }

    /**
     * 按协议生成签名请求头：X-Timestamp 缺省为当前时间
     */
    private function signedHeaders(string $appKey, string $secret, string $method, string $path, string $body, ?int $ts = null): array
    {
        $timestamp = (string) ($ts ?? time());
        $canonical = $timestamp . $method . $path . $body;

        return [
            'X-API-Key' => $appKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => hash_hmac('sha256', $canonical, $secret),
        ];
    }

    private function process(Request $request): Response
    {
        return (new OpenApiAuth())->process(
            $request,
            fn (Request $r): Response => json([
                'code' => 0,
                'data' => ['injected' => (string) ($r->openapiApp->id ?? '')],
            ])
        );
    }

    private function body(Response $res): array
    {
        return (array) json_decode($res->rawBody(), true);
    }

    private function onlyLog(int $subId): WebhookDeliveryLog
    {
        $log = WebhookDeliveryLog::query()->where('subscription_id', $subId)->orderBy('id')->first();

        return $log ?: $this->fail('订阅 ' . $subId . ' 无投递日志');
    }

    private function forcePast(WebhookDeliveryLog $log): void
    {
        $log->next_retry_at = date('Y-m-d H:i:s', time() - 10);
        $log->save();
    }

    /**
     * 清理本类产生/可能残留（上次中断）的数据：日志 → 订阅 → 应用 → 限流计数
     */
    private function cleanup(): void
    {
        $subIds = WebhookSubscription::query()
            ->whereIn('app_id', [self::APP_ID, self::APP_ID_2, self::APP_ID_3])
            ->pluck('id')
            ->merge(self::$subIds)
            ->unique()
            ->values();
        self::$subIds = [];

        if ($subIds->isNotEmpty()) {
            WebhookDeliveryLog::query()->whereIn('subscription_id', $subIds)->delete();
            WebhookSubscription::query()->whereIn('id', $subIds)->delete();
        }
        OpenApiApp::query()->whereIn('id', [self::APP_ID, self::APP_ID_2, self::APP_ID_3])->forceDelete();

        try {
            Redis::del([OpenApiAuth::rateKey(self::KEY_1), OpenApiAuth::rateKey(self::KEY_2), OpenApiAuth::rateKey(self::KEY_3)]);
        } catch (Throwable) {
            // Redis 未配置时静默跳过（认证路径 fail-open，不受影响）
        }
    }
}

/**
 * WebhookService 投递桩：拦截 httpPost 记录调用，可按需模拟失败
 */
class OpenApiStubService extends WebhookService
{
    public static array $calls = [];
    public static bool $fail = false;

    public static function reset(): void
    {
        self::$calls = [];
        self::$fail = false;
    }

    protected function httpPost(string $url, string $body, string $secret, string $event): array
    {
        self::$calls[] = [
            'url' => $url,
            'body' => $body,
            'secret' => $secret,
            'event' => $event,
            'signature' => WebhookService::signature($body, $secret),
        ];

        if (self::$fail) {
            return ['code' => 0, 'body' => '', 'error' => 'Connection refused (stub)'];
        }

        return ['code' => 200, 'body' => '{"ok":true}', 'error' => ''];
    }
}
