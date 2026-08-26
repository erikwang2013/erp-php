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
 *  - 失败消息携带 attempts 延迟入队（指数退避，非立即重试），
 *    延迟到期后提升回主队列，attempts 达到上限后进入死信队列；
 *  - 重试后成功的消息不再进死信。
 *
 * 生产逻辑经 QueueConsumer 的可覆写钩子（popMessage/requeueDelayed/
 * promoteDueDelayed/deadLetter）注入内存实现，其余（白名单校验、attempts
 * 递增、退避计算、死信判定）走真实代码路径。
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

        // 失败后延迟入队而非立即重试：消息离开主队列、进入延迟集，携带 attempts 计数
        $this->assertSame(1, FailingTask::$calls, '首次失败后不应立即重试');
        $this->assertSame([], $consumer->queue(), '失败消息应离开主队列');
        $this->assertCount(1, $consumer->delayed(), '失败消息应延迟入队');
        $delayedJob = json_decode($consumer->delayed()[0], true);
        $this->assertSame(1, $delayedJob['attempts'] ?? 0, '延迟消息应保留 attempts 计数');
        $this->assertSame(FailingTask::class, $delayedJob['class'] ?? null, '延迟消息应保留原任务');

        $this->advanceAndConsume($consumer); // 第 2 次尝试（延迟到期后重试）
        $this->assertSame(2, FailingTask::$calls);

        $this->advanceAndConsume($consumer); // 第 3 次尝试 → 超限进死信
        // 首次执行 + 2 次重试 = 3 次尝试后进入死信，消息不丢失
        $this->assertSame(3, FailingTask::$calls, '失败消息应被重试 2 次后放弃');
        $this->assertSame([], $consumer->queue(), '重试耗尽后消息应离开主队列');
        $this->assertSame([], $consumer->delayed(), '重试耗尽后延迟集应清空');
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

        $this->assertSame(1, FailingTask::$calls, '首次失败后不应立即重试（须等延迟到期）');
        $this->assertCount(1, $consumer->delayed(), '失败消息应延迟入队');

        $this->advanceAndConsume($consumer);

        $this->assertSame(2, FailingTask::$calls, '延迟到期后应重试一次并成功');
        $this->assertSame([], $consumer->queue());
        $this->assertSame([], $consumer->delayed(), '成功后延迟消息应清空');
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
        $this->assertSame([], $consumer->queue(), '正常消息消费后主队列应清空');

        $this->advanceAndConsume($consumer); // 失败消息第 2 次尝试
        $this->advanceAndConsume($consumer); // 失败消息第 3 次尝试 → 死信
        $this->assertCount(1, $consumer->dead(), '失败消息最终进入死信');
    }

    private function invokeConsumeOnce(QueueConsumer $consumer): void
    {
        $method = new \ReflectionMethod($consumer, 'consumeOnce');
        $method->invoke($consumer);
    }

    /** 模拟退避延迟流逝：让全部延迟消息到期，再消费一轮 */
    private function advanceAndConsume(MemoryQueueConsumer $consumer): void
    {
        $consumer->advanceTime(3600);
        $this->invokeConsumeOnce($consumer);
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

    /** @var array<int, array{0:int, 1:string}> 延迟集 [到期时间戳, 消息体] */
    private array $delayed = [];

    public function __construct(array $messages)
    {
        parent::__construct('');
        $this->queue = $messages;
    }

    protected function popMessage(string $key): ?string
    {
        return $this->queue === [] ? null : (string) array_shift($this->queue);
    }

    protected function requeueDelayed(string $delayKey, string $raw, int $delay): void
    {
        $this->delayed[] = [time() + $delay, $raw];
    }

    protected function promoteDueDelayed(): void
    {
        $now = time();
        $this->delayed = array_values(array_filter(
            $this->delayed,
            function (array $item) use ($now): bool {
                if ($item[0] <= $now) {
                    $this->queue[] = $item[1];
                    return false;
                }

                return true;
            }
        ));
    }

    protected function deadLetter(string $key, string $raw): void
    {
        $this->dead[] = $raw;
    }

    /** 测试辅助：让全部延迟消息提前到期（模拟退避时间流逝） */
    public function advanceTime(int $seconds): void
    {
        foreach ($this->delayed as $i => $item) {
            $this->delayed[$i][0] -= $seconds;
        }
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

    /** @return string[] */
    public function delayed(): array
    {
        return array_map(static fn (array $item): string => $item[1], $this->delayed);
    }
}
