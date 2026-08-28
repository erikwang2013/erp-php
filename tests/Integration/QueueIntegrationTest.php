<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\process\QueueConsumer;
use app\queue\redis\SmokeTask;
use app\queue\RedisQueue;
use PHPUnit\Framework\Attributes\Group;
use support\Redis;
use Throwable;

/**
 * 队列集成测试：Redis 投递 → 消费 端到端冒烟（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_REDIS_HOST（启用开关，必须与 config('redis.default.host') 一致）
 *
 * 覆盖点：
 * 1. 队列键格式（RedisQueue::key 与 config/queue.php 一致）；
 * 2. 冒烟任务端到端：SmokeTask::send 投递 → 模拟 QueueConsumer 消费 →
 *    断言副作用出现（Redis 冒烟计数器 +1、runtime/logs 出现消费日志）；
 * 3. 消费进程白名单守卫：未登记的任务类被拒绝执行（计数器不变）；
 * 4. 非法消息体（非 JSON）被丢弃，不触发任何执行。
 *
 * 说明：QueueConsumer 的 scanTaskClasses/handleJob 为私有方法，测试经
 * Reflection 调用（PHP 8.1+ 可直接 invoke 私有方法），复用的是生产消费逻辑
 * （白名单校验 + 任务分发），不依赖 Workerman Timer/Worker 常驻进程。
 *
 * 注意：本类会在被指向的 Redis 上排空 erp:queue:default 队列并临时累加
 * 冒烟计数器（结束恢复），请确保 TEST_REDIS_HOST/REDIS_HOST 指向专用测试 Redis。
 */
#[Group('integration')]
class QueueIntegrationTest extends IntegrationTestCase
{
    /** 冒烟计数器在测试开始前的值，tearDown 据此恢复现场 */
    private int $counterBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestRedis();

        // 排空队列历史残留，保证本类各测试对队列长度的断言确定
        while (($raw = Redis::lPop(RedisQueue::key())) !== false && $raw !== null) {
            // 丢弃残留消息
        }
        $this->counterBefore = (int) Redis::get(SmokeTask::COUNTER_KEY);
    }

    protected function tearDown(): void
    {
        try {
            // 排空本类测试可能残留的消息
            while (($raw = Redis::lPop(RedisQueue::key())) !== false && $raw !== null) {
                // 丢弃残留消息
            }
            // 恢复冒烟计数器现场（SmokeTask::consume 会 incr 该键）
            if ($this->counterBefore > 0) {
                Redis::set(SmokeTask::COUNTER_KEY, $this->counterBefore);
            } else {
                Redis::del(SmokeTask::COUNTER_KEY);
            }
        } catch (Throwable) {
            // 清理失败不掩盖测试结论
        }
        parent::tearDown();
    }

    /**
     * 构造真实 QueueConsumer 并执行其私有扫描逻辑（登记 consumer_dir 白名单）。
     */
    private function buildConsumer(): QueueConsumer
    {
        $consumer = new QueueConsumer();
        $scan = new \ReflectionMethod($consumer, 'scanTaskClasses');
        $scan->invoke($consumer);

        return $consumer;
    }

    /**
     * 模拟消费进程取出一条消息，并交给 QueueConsumer 的私有单条处理逻辑执行。
     * 端到端测试已预先 lPop 校验消息体契约，须把已取出的消息回传，否则二次弹出为空。
     */
    private function consumeOne(QueueConsumer $consumer, ?string $raw = null): void
    {
        $raw ??= Redis::lPop(RedisQueue::key());
        if ($raw === false || $raw === null) {
            return;
        }
        $handle = new \ReflectionMethod($consumer, 'handleJob');
        $handle->invoke($consumer, (string) $raw);
    }

    /**
     * 覆盖点 1：队列键格式与生产约定一致。
     */
    public function testQueueKeyUsesProductionFormat(): void
    {
        $expected = 'erp:queue:' . (string) config('queue.connections.redis.queue', 'default');
        $this->assertSame($expected, RedisQueue::key(), '队列键应为 erp:queue:<队列名>');
    }

    /**
     * 覆盖点 2：冒烟任务端到端链路，断言副作用（Redis 计数 + 日志文件）出现。
     */
    public function testPushThenConsumeRoundTripProducesSideEffects(): void
    {
        $marker = 'it-' . bin2hex(random_bytes(4));
        $data = ['trigger' => 'integration-test', 'marker' => $marker];

        // 生产者：走生产 API 投递
        $this->assertTrue(SmokeTask::send($data), 'SmokeTask::send 应成功投递冒烟任务');
        $this->assertSame(1, (int) Redis::lLen(RedisQueue::key()), '投递后队列中应有且仅有 1 条消息');

        // 消息体契约：{class, method, data} 与 webman/redis-queue 一致
        $raw = Redis::lPop(RedisQueue::key());
        $this->assertIsString($raw, '队列消息应为字符串');
        $job = json_decode((string) $raw, true);
        $this->assertIsArray($job, '队列消息应为合法 JSON');
        $this->assertSame(SmokeTask::class, $job['class'] ?? null, '消息 class 应为 SmokeTask');
        $this->assertSame('consume', $job['method'] ?? null, '消息 method 应为 consume');
        $this->assertSame($data, $job['data'] ?? null, '消息 data 应原样携带业务数据');

        // 消费者：模拟消费进程处理这条消息（真实白名单校验 + 任务分发）
        $consumer = $this->buildConsumer();
        $this->consumeOne($consumer, $raw);

        // 副作用 1：Redis 冒烟计数器 +1
        $this->assertSame(
            $this->counterBefore + 1,
            (int) Redis::get(SmokeTask::COUNTER_KEY),
            '消费后冒烟计数器应自增 1'
        );

        // 副作用 2：消费日志文件出现本次 marker
        $logFile = runtime_path() . '/logs/queue-smoke-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile, '消费进程应写入冒烟日志文件');
        $this->assertStringContainsString($marker, (string) file_get_contents($logFile), '日志应包含本次冒烟数据 marker');
    }

    /**
     * 覆盖点 3：白名单守卫——consumer_dir 未登记的任务类被拒绝执行。
     */
    public function testConsumerRejectsUnknownTaskClassByWhitelist(): void
    {
        // 未在 app/queue/redis 下登记的任务类（无对应文件）
        $this->assertTrue(
            RedisQueue::push('app\\queue\\redis\\NoSuchSmokeTask', 'consume', ['marker' => 'evil']),
            '投递应成功（入队不校验类是否存在，由消费端白名单把关）'
        );
        $this->assertSame(1, (int) Redis::lLen(RedisQueue::key()));

        $consumer = $this->buildConsumer();
        $this->consumeOne($consumer);

        // 消息被消费（丢弃），但计数器不变 → 未执行任何业务逻辑
        $this->assertSame(0, (int) Redis::lLen(RedisQueue::key()), '未登记消息应被消费丢弃');
        $this->assertSame(
            $this->counterBefore,
            (int) Redis::get(SmokeTask::COUNTER_KEY),
            '未登记任务不应执行，冒烟计数器保持不变'
        );
    }

    /**
     * 覆盖点 4：非法消息体（非 JSON）被丢弃，不触发任何执行。
     */
    public function testConsumerRejectsMalformedJsonMessage(): void
    {
        // 直接注入损坏消息（绕过 RedisQueue::push 的 JSON 编码，模拟脏数据）
        Redis::lPush(RedisQueue::key(), 'not-json{{');

        $consumer = $this->buildConsumer();
        $this->consumeOne($consumer);

        $this->assertSame(0, (int) Redis::lLen(RedisQueue::key()), '非法消息应被消费丢弃');
        $this->assertSame(
            $this->counterBefore,
            (int) Redis::get(SmokeTask::COUNTER_KEY),
            '非法消息不应触发任何任务执行'
        );
    }
}
