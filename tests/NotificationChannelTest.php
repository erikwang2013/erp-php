<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\service\notification\WebSocketService;
use PHPUnit\Framework\TestCase;
use Workerman\Worker;

/**
 * 通知基础设施：WebSocketService 推送
 * - WebSocketService: 无 worker 时静默失败；有 worker 时按 userId 定向推送/广播
 */
class NotificationChannelTest extends TestCase
{
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
