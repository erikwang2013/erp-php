<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * 代码覆盖率门槛检查脚本（任务 B4）
 *
 * 解析 PHPUnit 生成的 clover.xml（<coverage><project> 的 metrics 与逐文件 metrics），
 * 计算整体覆盖率与业务层（默认 app/service）覆盖率，低于门槛则非零退出，
 * 供本地与 CI 作为覆盖率门槛（gate）使用。
 *
 * 用法:
 *   php scripts/check-coverage.php [选项]
 *
 * 选项:
 *   --clover=<path>             clover.xml 路径，默认 runtime/coverage/clover.xml
 *   --threshold=<int>           整体覆盖率门槛（%），默认 30
 *   --business-dir=<dir>        业务层目录（相对 app/），默认 service
 *   --business-threshold=<int>  业务层覆盖率门槛（%），默认 40；设 0 关闭该门槛
 *   --top=<int>                 目录覆盖率 Top 展示数量，默认 5
 *   --bottom=<int>              目录覆盖率 Bottom 展示数量，默认 5
 *   --help / -h                 显示帮助
 *
 * 退出码:
 *   0  通过（覆盖率达标）
 *   1  覆盖率低于门槛（CI 失败）
 *   2  参数错误 / clover.xml 缺失或无法解析 / 无语句数据
 */

final class CoverageChecker
{
    private const DEFAULT_CLOVER = 'runtime/coverage/clover.xml';
    private const DEFAULT_THRESHOLD = 30;
    private const DEFAULT_BUSINESS_DIR = 'service';
    private const DEFAULT_BUSINESS_THRESHOLD = 40;

    private string $clover;
    private int $threshold;
    private string $businessDir;
    private int $businessThreshold;
    private int $top;
    private int $bottom;

    /** @var array{statements:int,covered:int} 整体 */
    private array $overall = ['statements' => 0, 'covered' => 0];

    /** @var array<string, array{statements:int,covered:int}> 顶层目录聚合 */
    private array $dirs = [];

    private int $fileCount = 0;

    public static function main(array $argv): int
    {
        $checker = new self();
        try {
            $checker->parseArgs($argv);
            $checker->parseClover();
        } catch (RuntimeException $e) {
            fwrite(STDERR, "错误: {$e->getMessage()}\n");
            return 2;
        }

        return $checker->run();
    }

    public function parseArgs(array $argv): void
    {
        $this->clover = self::DEFAULT_CLOVER;
        $this->threshold = self::DEFAULT_THRESHOLD;
        $this->businessDir = self::DEFAULT_BUSINESS_DIR;
        $this->businessThreshold = self::DEFAULT_BUSINESS_THRESHOLD;
        $this->top = 5;
        $this->bottom = 5;

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $this->printHelp();
                exit(0);
            }
            if (!str_starts_with($arg, '--')) {
                throw new RuntimeException("无法识别的参数: {$arg}（用 --help 查看用法）");
            }
            [$name, $value] = array_pad(explode('=', $arg, 2), 2, null);
            if ($value === null || $value === '') {
                throw new RuntimeException("参数 {$name} 缺少值（格式 --name=value）");
            }
            switch ($name) {
                case '--clover':
                    $this->clover = $value;
                    break;
                case '--threshold':
                    $this->threshold = $this->parseInt($name, $value);
                    break;
                case '--business-dir':
                    $this->businessDir = trim($value, '/');
                    break;
                case '--business-threshold':
                    $this->businessThreshold = $this->parseInt($name, $value);
                    break;
                case '--top':
                    $this->top = $this->parseInt($name, $value);
                    break;
                case '--bottom':
                    $this->bottom = $this->parseInt($name, $value);
                    break;
                default:
                    throw new RuntimeException("未知参数: {$name}（用 --help 查看用法）");
            }
        }
    }

    public function parseClover(): void
    {
        if (!is_file($this->clover)) {
            throw new RuntimeException("找不到 clover.xml: {$this->clover}（先运行 vendor/bin/phpunit --coverage-clover ...）");
        }

        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET: 禁止加载外部实体（XXE 防护）
        $xml = simplexml_load_file($this->clover, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if ($xml === false) {
            throw new RuntimeException("clover.xml 解析失败（不是有效的 XML）: {$this->clover}");
        }
        $project = $xml->project ?? null;
        if ($project === null || !isset($project->metrics)) {
            throw new RuntimeException("clover.xml 缺少 <project><metrics> 节点，不是有效的 Clover 报告");
        }

        $pm = $project->metrics;
        $this->overall['statements'] = (int) $pm['statements'];
        $this->overall['covered'] = (int) $pm['coveredstatements'];

        if ($this->overall['statements'] <= 0) {
            throw new RuntimeException("clover.xml 中没有可统计的语句（statements=0），无法计算覆盖率");
        }

        // 兼容两种 Clover 结构：
        //   1) PHPUnit 12 真实输出：<file> 按命名空间包在 <package> 内（无命名空间文件如
        //      app/functions.php 直接挂在 <project> 下）；
        //   2) 简化/旧版输出：<file> 直接挂在 <project> 下。
        // 用 xpath('.//file') 统一遍历全部后代 <file>。
        foreach ($project->xpath('.//file') as $file) {
            $this->fileCount++;
            $path = (string) $file['name'];
            $rel = $this->toAppRelative($path);
            if ($rel === null) {
                continue; // 不在 app/ 下的文件（正常不会出现）
            }
            $fm = $file->metrics;
            $dir = $this->topDir($rel);
            $this->dirs[$dir]['statements'] = ($this->dirs[$dir]['statements'] ?? 0) + (int) $fm['statements'];
            $this->dirs[$dir]['covered'] = ($this->dirs[$dir]['covered'] ?? 0) + (int) $fm['coveredstatements'];
        }
    }

    public function run(): int
    {
        $overallPct = $this->percent($this->overall);

        $business = ['statements' => 0, 'covered' => 0];
        foreach ($this->dirs as $dir => $metrics) {
            if ($dir === $this->businessDir || str_starts_with($dir, $this->businessDir . '/')) {
                $business['statements'] += $metrics['statements'];
                $business['covered'] += $metrics['covered'];
            }
        }
        $businessPct = $business['statements'] > 0 ? $this->percent($business) : null;

        $overallOk = $overallPct >= $this->threshold;
        $businessOk = $this->businessThreshold <= 0
            || ($businessPct !== null && $businessPct >= $this->businessThreshold);
        $pass = $overallOk && $businessOk;

        $this->printReport($overallPct, $businessPct, $business, $overallOk, $businessOk, $pass);

        return $pass ? 0 : 1;
    }

    private function printReport(
        float $overallPct,
        ?float $businessPct,
        array $business,
        bool $overallOk,
        bool $businessOk,
        bool $pass
    ): void {
        $mark = fn (bool $ok): string => $ok ? 'PASS' : 'FAIL';
        $w = max(24, strlen($this->businessDir) + 4);

        echo "=== 代码覆盖率检查 ===\n";
        echo "报告文件: {$this->clover}\n";
        echo sprintf("采集文件数: %d\n", $this->fileCount);
        echo "----------------------------------------\n";
        printf(
            "整体覆盖率  : %6.2f%%  (%d/%d 语句)  门槛 %2d%%  -> %s\n",
            $overallPct, $this->overall['covered'], $this->overall['statements'],
            $this->threshold, $mark($overallOk)
        );
        if ($businessPct === null) {
            printf(
                "业务层 %-{$w}s:    N/A  (无采集文件)  门槛 %2d%%  -> %s\n",
                'app/' . $this->businessDir, $this->businessThreshold, $mark($businessOk)
            );
        } else {
            printf(
                "业务层 %-{$w}s: %6.2f%%  (%d/%d 语句)  门槛 %2d%%  -> %s\n",
                'app/' . $this->businessDir, $businessPct,
                $business['covered'], $business['statements'],
                $this->businessThreshold, $mark($businessOk)
            );
        }
        echo "----------------------------------------\n";

        $rows = [];
        foreach ($this->dirs as $dir => $metrics) {
            if ($metrics['statements'] === 0) {
                continue;
            }
            $rows[] = ['dir' => 'app/' . $dir, 'pct' => $this->percent($metrics), 'covered' => $metrics['covered'], 'stmts' => $metrics['statements']];
        }
        usort($rows, fn (array $a, array $b): int => $b['pct'] <=> $a['pct']);

        $this->printDirRows('目录覆盖率 Top ' . $this->top, array_slice($rows, 0, $this->top));
        $this->printDirRows('目录覆盖率 Bottom ' . $this->bottom, array_slice(array_reverse($rows), 0, $this->bottom));
        echo "----------------------------------------\n";

        $reasons = [];
        if (!$overallOk) {
            $reasons[] = sprintf('整体 %.2f%% < %d%%', $overallPct, $this->threshold);
        }
        if (!$businessOk) {
            $reasons[] = sprintf('业务层 %s < %d%%', $businessPct === null ? '无数据' : sprintf('%.2f%%', $businessPct), $this->businessThreshold);
        }
        echo $pass
            ? "结果: PASS（覆盖率达标）\n"
            : "结果: FAIL（" . implode('；', $reasons) . "）\n";
    }

    private function printDirRows(string $title, array $rows): void
    {
        if ($rows === []) {
            echo "{$title}: (无)\n";
            return;
        }
        echo "{$title}:\n";
        foreach ($rows as $row) {
            printf("    %-32s %6.2f%%  (%d/%d)\n", $row['dir'], $row['pct'], $row['covered'], $row['stmts']);
        }
    }

    /** 把绝对路径转为相对 app/ 的路径，如 /x/app/service/Foo.php -> service/Foo.php；不在 app/ 下返回 null */
    private function toAppRelative(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $pos = strpos($normalized, '/app/');
        if ($pos === false) {
            return null;
        }
        $rel = substr($normalized, $pos + strlen('/app/'));
        return $rel === '' || $rel === false ? null : $rel;
    }

    /** 取相对路径的顶层目录，如 service/finance/Bank.php -> service；app 根文件归入 app/root */
    private function topDir(string $rel): string
    {
        $slash = strpos($rel, '/');
        return $slash === false ? 'root' : substr($rel, 0, $slash);
    }

    private function percent(array $metrics): float
    {
        if ($metrics['statements'] <= 0) {
            return 0.0;
        }
        return round($metrics['covered'] * 100 / $metrics['statements'], 2);
    }

    private function parseInt(string $name, string $value): int
    {
        if (!preg_match('/^\d+$/', $value)) {
            throw new RuntimeException("参数 {$name} 需要非负整数，得到: {$value}");
        }
        return (int) $value;
    }

    private function printHelp(): void
    {
        echo <<<HELP
        代码覆盖率门槛检查脚本（任务 B4）

        用法: php scripts/check-coverage.php [选项]

        选项:
          --clover=<path>             clover.xml 路径，默认 runtime/coverage/clover.xml
          --threshold=<int>           整体覆盖率门槛（%），默认 30
          --business-dir=<dir>        业务层目录（相对 app/），默认 service
          --business-threshold=<int>  业务层覆盖率门槛（%），默认 40；设 0 关闭该门槛
          --top=<int>                 目录覆盖率 Top 展示数量，默认 5
          --bottom=<int>              目录覆盖率 Bottom 展示数量，默认 5
          --help / -h                 显示帮助

        退出码: 0 通过；1 覆盖率低于门槛；2 参数/IO 错误

        HELP;
    }
}

exit(CoverageChecker::main($argv));
