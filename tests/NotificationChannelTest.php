<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\notification\ChannelRouter;
use app\service\notification\WebSocketService;
use PHPUnit\Framework\TestCase;
use Workerman\Worker;

/**
 * 通知基础设施：ChannelRouter 渠道路由与 WebSocketService 推送
 * - ChannelRouter: in_app 落库失败 fail-closed；email 文件日志驱动；未注册渠道返回 false
 * - WebSocketService: 无 worker 时静默失败；有 worker 时按 userId 定向推送/广播
 */
class NotificationChannelTest extends TestCase
{
    /* ======================== ChannelRouter ======================== */

    public function testUnknownChannelReturnsFalse(): void
    {
        $result = (new ChannelRouter())->send(1, 't', 'c', ['wecom']);
        $this->assertSame(['wecom' => false], $result);
    }

    public function testReservedChannelDingtalkReturnsFalse(): void
    {
        $result = (new ChannelRouter())->send(1, 't', 'c', ['dingtalk']);
        $this->assertArrayHasKey('dingtalk', $result);
        $this->assertFalse($result['dingtalk']);
    }

    public function testInAppFailsClosedWithoutDatabase(): void
    {
        // 无 DB 时落库抛异常被捕获，返回 false（fail-closed 信号）
        $result = (new ChannelRouter())->send(1, 't', 'c', ['in_app']);
        $this->assertFalse($result['in_app']);
    }

    public function testEmailChannelWritesOutboxFile(): void
    {
        $result = (new ChannelRouter())->send(7, '标题', '内容', ['email']);
        $this->assertTrue($result['email']);

        $file = runtime_path() . '/mail/outbox.log';
        $this->assertFileExists($file);
        $line = trim((string) file_get_contents($file));
        $entry = json_decode(substr($line, strrpos($line, "\n") + 1), true);
        $this->assertSame(7, $entry['user_id']);
        $this->assertSame('标题', $entry['title']);
        $this->assertSame('内容', $entry['content']);
    }

    public function testMultipleChannelsReportedIndependently(): void
    {
        $result = (new ChannelRouter())->send(1, 't', 'c', ['email', 'wecom']);
        $this->assertTrue($result['email']);
        $this->assertFalse($result['wecom']);
    }

    /* ======================== WebSocketService ======================== */

    public function testPushToUserWithoutWorkerReturnsFalse(): void
    {
        $this->assertFalse(WebSocketService::pushToUser(1, ['a' => 1]));
    }

    public function testBroadcastWithoutWorkerReturnsZero(): void
    {
        $this->assertSame(0, WebSocketService::broadcast(['a' => 1]));
    }

    public function testPushToUserTargetsMatchingConnectionOnly(): void
    {
        $sent = new Collector();
        $worker = new FakeWorker([
            $this->fakeConn(1, $sent),
            $this->fakeConn(2, $sent),
            $this->fakeConn(1, $sent),
        ]);
        $this->injectWorker($worker);

        try {
            $ok = WebSocketService::pushToUser(1, ['msg' => 'hi']);
            $this->assertTrue($ok);
            $this->assertCount(2, $sent->messages);
            $this->assertSame('notification', json_decode($sent->messages[0], true)['type']);
            $this->assertSame(['msg' => 'hi'], json_decode($sent->messages[0], true)['data']);
        } finally {
            $this->injectWorker(null);
        }
    }

    public function testPushToUserReturnsFalseWhenUserOffline(): void
    {
        $sent = new Collector();
        $worker = new FakeWorker([$this->fakeConn(9, $sent)]);
        $this->injectWorker($worker);

        try {
            $this->assertFalse(WebSocketService::pushToUser(42, ['msg' => 'hi']));
            $this->assertSame([], $sent->messages);
        } finally {
            $this->injectWorker(null);
        }
    }

    public function testBroadcastCountsAllConnections(): void
    {
        $sent = new Collector();
        $worker = new FakeWorker([$this->fakeConn(1, $sent), $this->fakeConn(2, $sent)]);
        $this->injectWorker($worker);

        try {
            $count = WebSocketService::broadcast(['msg' => 'all']);
            $this->assertSame(2, $count);
            $this->assertCount(2, $sent->messages);
            $this->assertSame('broadcast', json_decode($sent->messages[0], true)['type']);
        } finally {
            $this->injectWorker(null);
        }
    }

    /* ======================== helpers ======================== */

    private function fakeConn(int $userId, Collector $sent): FakeConnection
    {
        return new FakeConnection($userId, $sent);
    }

    private function injectWorker(?Worker $worker): void
    {
        $prop = new \ReflectionProperty(WebSocketService::class, 'worker');
        $prop->setAccessible(true);
        $prop->setValue(null, $worker);
    }
}

/**
 * Worker 测试替身：Workerman\Worker::$connections 为受保护静态属性，
 * 子类以实例属性暴露，供单测注入连接集合
 */
class FakeWorker extends Worker
{
    public array $connections = [];

    public function __construct(array $connections = [])
    {
        $this->connections = $connections;
    }
}

/**
 * send() 载荷收集器：多个连接共享同一实例
 */
class Collector
{
    public array $messages = [];
}

/**
 * WebSocket 连接替身：send() 写入共享收集器
 */
class FakeConnection
{
    public int $userId;

    private Collector $collector;

    public function __construct(int $userId, Collector $collector)
    {
        $this->userId = $userId;
        $this->collector = $collector;
    }

    public function send(string $payload): void
    {
        $this->collector->messages[] = $payload;
    }
}
