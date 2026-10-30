<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Erikwang2013\Snowflake\Snowflake;
use RuntimeException;

/**
 * Snowflake ID 生成服务
 * 用于生成全局唯一 BIGINT 主键，替代数据库自增
 *
 * 进程级节点分配（单机多进程）：
 *  - 推荐在 Worker 进程启动时调用 registerProcessNode()，通过
 *    runtime/snowflake/node-0.lock .. node-1023.lock 的文件锁（flock）独占一个节点，
 *    锁句柄由 static 持有到进程结束；1024 个节点全部占用时抛 RuntimeException。
 *  - 未走 Worker 启动注册的 CLI/测试场景，回退到 PID 低 10 位映射（nodeIdsForPid）。
 *  - worker_id / datacenter_id 由节点号叠加 env 的 SNOWFLAKE_WORKER_ID /
 *    SNOWFLAKE_DATACENTER_ID 得到；文件锁只保证单机唯一，跨主机部署必须为每台
 *    主机设置不同的 SNOWFLAKE_WORKER_ID / SNOWFLAKE_DATACENTER_ID。
 */
class SnowflakeService
{
    /** 节点总数：5 位 worker + 5 位 datacenter */
    private const MAX_NODES = 1024;

    private static ?Snowflake $instance = null;

    private static ?int $pid = null;

    /**
     * 已注册节点的锁文件句柄。必须由 static 持有到进程结束：
     * 句柄一旦被 GC/关闭，flock 即释放，节点会被其他进程抢走。
     *
     * @var resource|null
     */
    private static $lockHandle = null;

    private static ?string $lockDir = null;

    /**
     * 在 Worker 进程启动时注册独占节点。同一进程重复调用幂等。
     *
     * @param string|null $lockDir 锁目录（测试可注入），默认 runtime_path()/snowflake
     */
    public static function registerProcessNode(?string $lockDir = null): void
    {
        if (self::$lockHandle !== null) {
            return;
        }

        $lockDir ??= runtime_path() . '/snowflake';
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
            throw new RuntimeException("Snowflake 节点锁目录创建失败: {$lockDir}");
        }

        for ($node = 0; $node < self::MAX_NODES; $node++) {
            $path = $lockDir . '/node-' . $node . '.lock';
            $handle = @fopen($path, 'c');
            if ($handle === false) {
                throw new RuntimeException("Snowflake 节点锁文件无法打开: {$path}");
            }
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                self::$lockHandle = $handle;
                self::$lockDir = $lockDir;
                self::useNode($node);

                return;
            }
            fclose($handle);
        }

        throw new RuntimeException('Snowflake 节点已耗尽：' . self::MAX_NODES . ' 个节点全部被占用');
    }

    public static function generate(): int
    {
        $pid = getmypid() ?: 0;

        if (self::$instance === null || self::$pid !== $pid) {
            if (self::$lockHandle !== null) {
                // 注册后 fork：子进程需释放继承的句柄并重新抢节点
                $lockDir = self::$lockDir;
                $handle = self::$lockHandle;
                self::$lockHandle = null;
                self::$lockDir = null;
                self::$instance = null;
                fclose($handle);
                self::registerProcessNode($lockDir);
            } else {
                // 未注册的 CLI/测试：回退 PID 低 10 位映射
                self::useNode($pid & (self::MAX_NODES - 1));
            }
        }

        return self::$instance->nextId();
    }

    /**
     * 把 PID 低 10 位拆成 5+5 位节点号，并叠加主机基础节点号。
     *
     * @return array{0: int, 1: int} [worker_id, datacenter_id]
     */
    public static function nodeIdsForPid(int $pid, int $baseWorker, int $baseDatacenter): array
    {
        return self::nodeIdsForNode($pid & (self::MAX_NODES - 1), $baseWorker, $baseDatacenter);
    }

    private static function useNode(int $node): void
    {
        $config = config('snowflake', []);
        [$workerId, $datacenterId] = self::nodeIdsForNode(
            $node,
            (int)($config['worker_id'] ?? 1),
            (int)($config['datacenter_id'] ?? 1),
        );

        self::$instance = new Snowflake(
            workerId: $workerId,
            datacenterId: $datacenterId,
            epoch: $config['start_timestamp'] ?? null,
        );
        self::$pid = getmypid() ?: 0;
    }

    /**
     * @return array{0: int, 1: int} [worker_id, datacenter_id]
     */
    private static function nodeIdsForNode(int $node, int $baseWorker, int $baseDatacenter): array
    {
        return [
            (($baseWorker & 0x1F) + ($node & 0x1F)) & 0x1F,
            (($baseDatacenter & 0x1F) + ($node >> 5)) & 0x1F,
        ];
    }
}
