<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\SnowflakeService;
use Erikwang2013\Snowflake\Snowflake;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * 多进程 Snowflake 节点分配：文件锁注册优先，PID 低 10 位仅作 CLI/测试 fallback。
 */
class SnowflakeMultiProcessTest extends TestCase
{
    #[Test]
    public function pid_mapping_is_unique_and_in_range(): void
    {
        foreach ([[1, 1], [0, 0], [31, 31]] as [$baseWorker, $baseDatacenter]) {
            $seen = [];
            for ($pid = 0; $pid < 1024; $pid++) {
                [$worker, $datacenter] = SnowflakeService::nodeIdsForPid($pid, $baseWorker, $baseDatacenter);

                $this->assertGreaterThanOrEqual(0, $worker);
                $this->assertLessThanOrEqual(31, $worker);
                $this->assertGreaterThanOrEqual(0, $datacenter);
                $this->assertLessThanOrEqual(31, $datacenter);

                $key = $worker * 32 + $datacenter;
                $this->assertArrayNotHasKey(
                    $key,
                    $seen,
                    sprintf('PID %d 与 PID %d 映射到同一节点 (%d, %d)', $pid, $seen[$key] ?? -1, $worker, $datacenter)
                );
                $seen[$key] = $pid;
            }
            $this->assertCount(1024, $seen, 'PID 0..1023 应覆盖全部 1024 个节点组合');
        }
    }

    #[Test]
    public function two_processes_register_distinct_nodes(): void
    {
        $lockDir = $this->lockDir('spawn');
        $this->assertTrue(mkdir($lockDir, 0755, true) || is_dir($lockDir));

        // 子进程先写 ready，等父进程创建 go 后再并发抢锁；抢到后持有 1 秒，
        // 保证另一个进程注册时该节点仍被占用（避免串行执行时复用刚释放的节点）。
        $code = sprintf(
            'require %s;'
            . '$dir = %s;'
            . 'file_put_contents($dir . "/ready-" . getmypid() . ".txt", "1");'
            . '$deadline = microtime(true) + 15;'
            . 'while (!is_file($dir . "/go") && microtime(true) < $deadline) { usleep(10000); }'
            . '\\app\\common\\SnowflakeService::registerProcessNode($dir);'
            . 'usleep(1000000);'
            . 'echo \\app\\common\\SnowflakeService::generate();',
            var_export(__DIR__ . '/bootstrap.php', true),
            var_export($lockDir, true)
        );

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        try {
            $procA = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipesA);
            $procB = proc_open([PHP_BINARY, '-r', $code], $descriptors, $pipesB);
            $this->assertIsResource($procA);
            $this->assertIsResource($procB);

            $deadline = microtime(true) + 15;
            do {
                clearstatcache();
                $ready = glob($lockDir . '/ready-*.txt') ?: [];
                if (count($ready) >= 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertCount(2, $ready, '子进程未全部就绪');
            touch($lockDir . '/go');

            $outA = stream_get_contents($pipesA[1]);
            $errA = stream_get_contents($pipesA[2]);
            $outB = stream_get_contents($pipesB[1]);
            $errB = stream_get_contents($pipesB[2]);
            foreach ([$pipesA, $pipesB] as $pipes) {
                fclose($pipes[1]);
                fclose($pipes[2]);
            }
            $exitA = proc_close($procA);
            $exitB = proc_close($procB);

            $this->assertSame(0, $exitA, 'child A stderr: ' . $errA);
            $this->assertSame(0, $exitB, 'child B stderr: ' . $errB);
            $this->assertSame(1, preg_match('/\d{15,}/', (string)$outA), 'child A 未输出 ID: ' . $outA);
            $this->assertSame(1, preg_match('/\d{15,}/', (string)$outB), 'child B 未输出 ID: ' . $outB);
        } finally {
            $this->removeLockDir($lockDir);
        }

        preg_match('/\d{15,}/', (string)$outA, $matchA);
        preg_match('/\d{15,}/', (string)$outB, $matchB);
        $nodeA = $this->nodeOf((int)$matchA[0]);
        $nodeB = $this->nodeOf((int)$matchB[0]);

        $this->assertNotSame($nodeA, $nodeB, '两个进程必须注册到不同节点');
    }

    #[Test]
    public function register_is_idempotent_in_process(): void
    {
        $lockDir = $this->lockDir('idem');
        try {
            SnowflakeService::registerProcessNode($lockDir);
            $first = SnowflakeService::generate();
            SnowflakeService::registerProcessNode($lockDir);
            $second = SnowflakeService::generate();
        } finally {
            $this->removeLockDir($lockDir);
        }

        $this->assertSame($this->nodeOf($first), $this->nodeOf($second), '重复注册不应切换节点');
    }

    /**
     * @return array{0: int, 1: int} [worker_id, datacenter_id]
     */
    private function nodeOf(int $id): array
    {
        $parsed = Snowflake::parse($id, (int)config('snowflake.start_timestamp'));

        return [$parsed['worker_id'], $parsed['datacenter_id']];
    }

    private function lockDir(string $suffix): string
    {
        return __DIR__ . '/tmp/snowflake-' . $suffix . '-' . bin2hex(random_bytes(4));
    }

    private function removeLockDir(string $lockDir): void
    {
        foreach (glob($lockDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($lockDir);
    }
}
