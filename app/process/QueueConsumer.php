<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\queue\RedisQueue;
use support\Log;
use support\Redis;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Redis 队列消费进程（最小实现）
 *
 * 背景：项目当前未安装 webman/redis-queue 扩展包（composer show 无该包），
 * 本进程使用 Workerman 原生 Timer + Redis LIST（LPUSH/LPOP）实现最小队列消费：
 *  - 进程启动（onWorkerStart）时扫描 consumer_dir 下的任务类并注册 0.5 秒定时器，
 *    周期性排空 Redis 队列；
 *  - 消息体格式见 app/queue/RedisQueue.php，与 webman/redis-queue 的
 *    {class, method, data} 约定一致，便于将来替换为官方扩展时平滑迁移；
 *  - 只允许消费 consumer_dir 下扫描到的任务类，防止队列消息触发任意类调用。
 *
 * 切换为官方 webman/redis-queue 时，将 config/process.php 中本进程的 handler
 * 替换为 Webman\RedisQueue\Process\Consumer::class 即可，任务类目录
 * （consumer_dir => app/queue/redis/）保持不变，详见 docs/queue.md。
 */
class QueueConsumer
{
    /** 轮询间隔（秒） */
    private const POLL_INTERVAL = 0.5;

    /** 最大尝试次数（含首次执行），超过后消息进入死信队列 */
    private const MAX_ATTEMPTS = 3;

    /** 死信队列键（失败消息兜底，人工排查后可重投） */
    private const FAILED_QUEUE_KEY = 'erp:queue:failed';

    /** 消费任务目录（由 config/process.php 的 constructor 注入） */
    private string $consumerDir;

    /** 队列名（默认取 config/queue.php 中 redis 连接的 queue 字段） */
    private string $queue = '';

    /** 已扫描到的任务类白名单（类名 => true） */
    protected array $taskClasses = [];

    public function __construct(string $consumerDir = '')
    {
        $this->consumerDir = $consumerDir ?: app_path() . '/queue/redis';
    }

    /**
     * 进程启动回调：扫描任务类并注册轮询定时器
     */
    public function onWorkerStart(Worker $worker): void
    {
        $this->queue = (string)config('queue.connections.redis.queue', 'default');
        $this->scanTaskClasses();

        if ($this->taskClasses === []) {
            Log::warning("redis-queue: consumer_dir {$this->consumerDir} 下未发现任何任务类");
        }

        // 注册周期轮询定时器（Timer 随 Worker 生命周期自动清理）
        Timer::add(self::POLL_INTERVAL, function (): void {
            $this->consumeOnce();
        });

        Log::info(
            'redis-queue 消费进程已启动，队列: ' . $this->queue
            . '，任务类: ' . implode(', ', array_keys($this->taskClasses))
        );
    }

    /**
     * 扫描 consumer_dir 下的任务类（仅登记拥有 consume 方法且可实例化的类）
     */
    private function scanTaskClasses(): void
    {
        if (!is_dir($this->consumerDir)) {
            Log::warning("redis-queue: consumer_dir {$this->consumerDir} 不存在");

            return;
        }

        foreach (glob($this->consumerDir . '/*.php') ?: [] as $file) {
            // 通过文件名推断类名（PSR-4：app/queue/redis/Xxx.php => app\queue\redis\Xxx）
            $class = 'app\\queue\\redis\\' . basename($file, '.php');
            if (!class_exists($class) || !method_exists($class, 'consume')) {
                continue;
            }
            $this->taskClasses[$class] = true;
        }
    }

    /**
     * 单次消费：排空队列中的全部消息（异常兜底，避免定时器回调崩溃）
     */
    private function consumeOnce(): void
    {
        try {
            $key = RedisQueue::key($this->queue);

            while (($raw = $this->popMessage($key)) !== null) {
                try {
                    $this->handleJob($raw);
                } catch (Throwable $e) {
                    // 单条消息处理异常不中断消费循环（handleJob 内部已兜底，此处双保险）
                    Log::error('redis-queue 消费单条消息异常: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            Log::error('redis-queue 消费循环异常: ' . $e->getMessage());
        }
    }

    /**
     * 处理单条队列消息：白名单校验后调用任务类的消费方法
     */
    private function handleJob(string $raw): void
    {
        $job = json_decode($raw, true);
        if (!is_array($job) || !isset($job['class'], $job['method'])) {
            Log::error('redis-queue: 非法消息体，已丢弃: ' . $raw);

            return;
        }

        $class = $job['class'];
        $method = $job['method'];
        $data = is_array($job['data'] ?? null) ? $job['data'] : [];

        // 白名单校验：只允许 consumer_dir 下扫描到的任务类，防止任意类调用
        if (!isset($this->taskClasses[$class]) || !method_exists($class, $method)) {
            Log::error("redis-queue: 拒绝执行未登记任务 {$class}::{$method}");

            return;
        }

        try {
            $class::$method($data);
        } catch (Throwable $e) {
            $this->retryOrFail($job, $raw, $e);
        }
    }

    /**
     * 失败重试：attempts 未超限则携带 attempts+1 重新入队，否则推入死信队列
     * ponytail: 重试立即回到队尾、无延迟退避，靠 MAX_ATTEMPTS 封顶；
     * 需要指数退避时改为 zset 延迟队列 + 到期扫描进程。
     */
    private function retryOrFail(array $job, string $raw, Throwable $e): void
    {
        $attempts = (int)($job['attempts'] ?? 0) + 1;
        $failed = "{$job['class']}::{$job['method']}: " . $e->getMessage();

        if ($attempts < self::MAX_ATTEMPTS) {
            $job['attempts'] = $attempts;
            $requeued = json_encode($job, JSON_UNESCAPED_UNICODE);
            if ($requeued !== false) {
                $this->requeue(RedisQueue::key($this->queue), $requeued);
                Log::warning("redis-queue: 任务执行失败，第{$attempts}次重试入队 {$failed}");

                return;
            }
        }

        // 超限：带最终尝试次数写入死信，便于人工排查重试历史
        $job['attempts'] = $attempts;
        $deadRaw = json_encode($job, JSON_UNESCAPED_UNICODE);
        $this->deadLetter(self::FAILED_QUEUE_KEY, $deadRaw !== false ? $deadRaw : $raw);
        Log::error("redis-queue: 任务失败已进入死信队列 {$failed}");
    }

    /**
     * 从队列头取出一条消息（拆出可覆写的方法，便于单元测试注入内存实现）
     */
    protected function popMessage(string $key): ?string
    {
        $raw = Redis::lPop($key);

        return ($raw === false || $raw === null) ? null : (string)$raw;
    }

    /**
     * 重新入队（回队尾）
     */
    protected function requeue(string $key, string $raw): void
    {
        Redis::rPush($key, $raw);
    }

    /**
     * 写入死信队列
     */
    protected function deadLetter(string $key, string $raw): void
    {
        Redis::lPush($key, $raw);
    }
}
