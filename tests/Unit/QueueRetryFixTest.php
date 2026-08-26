<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Unit;

use app\process\QueueConsumer;
use PHPUnit\Framework\TestCase;

/**
 * 队列失败重试修复测试（内存队列，不依赖 Redis）：
 *  - 任务失败不中断消费循环（队列中后续消息仍被消费）；
 *  - 失败消息携带 attempts 重试入队，attempts 达到上限后进入死信队列；
 *  - 重试后成功的消息不再进死信。
 *
 * 生产逻辑经 QueueConsumer 的可覆写钩子（popMessage/requeue/deadLetter）
 * 注入内存实现，其余（白名单校验、attempts 递增、死信判定）走真实代码路径。
 */
class QueueRetryFixTest extends TestCase
{
    protected function tearDown(): void
    {
        FailingTask::$calls = 0;
        FailingTask::$failTimes = 1;
        CountingTask::$calls = 0;
        parent::tearDown();
    }

    /** 构造内存队列消费者：队列里有 $messages，出队/重投/死信均落在内存 */
    private function buildConsumer(array $messages): MemoryQueueConsumer
    {
        return new MemoryQueueConsumer($messages);
    }

    private function jobMessage(string $class, string $method = 'consume', array $data = []): string
    {
        return (string) json_encode([
            'class' => $class,
            'method' => $method,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function testFailedMessageIsRetriedUpToLimitThenDeadLettered(): void
    {
        FailingTask::$failTimes = 999; // 永远失败
        $consumer = $this->buildConsumer([$this->jobMessage(FailingTask::class)]);

        $this->invokeConsumeOnce($consumer);

        // 首次执行 + 2 次重试 = 3 次尝试后进入死信，消息不丢失
        $this->assertSame(3, FailingTask::$calls, '失败消息应被重试 2 次后放弃');
        $this->assertSame([], $consumer->queue(), '重试耗尽后消息应离开主队列');
        $this->assertCount(1, $consumer->dead(), '超限消息应进入死信队列');
        $deadJob = json_decode($consumer->dead()[0], true);
        $this->assertSame(FailingTask::class, $deadJob['class'] ?? null, '死信消息应保留原任务');
        $this->assertSame(3, $deadJob['attempts'] ?? 0, '死信消息应记录最终尝试次数');
    }

    public function testFailedMessageRetriesAndSucceeds(): void
    {
        FailingTask::$failTimes = 1; // 第一次失败，第二次成功
        $consumer = $this->buildConsumer([$this->jobMessage(FailingTask::class)]);

        $this->invokeConsumeOnce($consumer);

        $this->assertSame(2, FailingTask::$calls, '失败后应重试一次并成功');
        $this->assertSame([], $consumer->queue());
        $this->assertSame([], $consumer->dead(), '重试成功的消息不应进死信');
    }

    public function testSingleMessageFailureDoesNotStopConsumeLoop(): void
    {
        FailingTask::$failTimes = 999; // 永远失败（3 次尝试后进死信）
        $consumer = $this->buildConsumer([
            $this->jobMessage(FailingTask::class),
            $this->jobMessage(CountingTask::class, 'consume', ['marker' => 'after-failure']),
        ]);

        $this->invokeConsumeOnce($consumer);

        // 失败消息之后的正常消息仍被消费 → 单条失败不中断循环
        $this->assertSame(1, CountingTask::$calls, '失败消息之后的正常消息应被消费');
        $this->assertCount(1, $consumer->dead(), '失败消息最终进入死信');
    }

    private function invokeConsumeOnce(QueueConsumer $consumer): void
    {
        $method = new \ReflectionMethod($consumer, 'consumeOnce');
        $method->invoke($consumer);
    }
}

/** 总是/按需抛异常的任务（failTimes 次内失败，之后成功） */
class FailingTask
{
    public static int $calls = 0;
    public static int $failTimes = 1;

    public static function consume(array $data = []): void
    {
        self::$calls++;
        if (self::$calls <= self::$failTimes) {
            throw new \RuntimeException('任务执行失败(测试)');
        }
    }
}

/** 记录调用次数的正常任务 */
class CountingTask
{
    public static int $calls = 0;

    public static function consume(array $data = []): void
    {
        self::$calls++;
    }
}

/** 内存队列版 QueueConsumer：覆写 Redis 出入队钩子 */
class MemoryQueueConsumer extends QueueConsumer
{
    protected array $taskClasses = [
        FailingTask::class => true,
        CountingTask::class => true,
    ];

    /** @var string[] 主队列（队尾追加） */
    private array $queue;

    /** @var string[] 死信队列 */
    private array $dead = [];

    public function __construct(array $messages)
    {
        parent::__construct('');
        $this->queue = $messages;
    }

    protected function popMessage(string $key): ?string
    {
        return $this->queue === [] ? null : (string) array_shift($this->queue);
    }

    protected function requeue(string $key, string $raw): void
    {
        $this->queue[] = $raw;
    }

    protected function deadLetter(string $key, string $raw): void
    {
        $this->dead[] = $raw;
    }

    /** @return string[] */
    public function queue(): array
    {
        return $this->queue;
    }

    /** @return string[] */
    public function dead(): array
    {
        return $this->dead;
    }
}
