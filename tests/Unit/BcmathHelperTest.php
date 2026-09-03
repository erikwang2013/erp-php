<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * bcmath 全局 helpers（bc_norm / bc_round / bc_abs）纯逻辑测试。
 *
 * - 半值上入（half-up, away from zero）：恰 0.005 处 float round() 因二进制下溢
 *   多向下舍（2.675→2.67、1.005→1.00），bc 路径应得 2.68 / 1.01——这是修正。
 * - 规范化：float 尾噪（0.105）与科学计数法（1e-5）必须展开为十进制串。
 * - 负数对称、scale=0 不崩、-0 归零。
 */
class BcmathHelperTest extends TestCase
{
    public function testRoundHalfUp(): void
    {
        $this->assertSame('1.01', bc_round('1.005', 2));
        $this->assertSame('2.68', bc_round('2.675', 2));
        $this->assertSame('-1.01', bc_round('-1.005', 2));
        $this->assertSame('120.01', bc_round('120.005', 2));
        $this->assertSame('0.08', bc_round('0.075', 2));
    }

    public function testRoundScaleZero(): void
    {
        $this->assertSame('35', bc_round('34.5', 0));
        // 默认 scale=2：34.5+0.005 在 2 位截断得 34.50（按 scale 补零），非 35
        $this->assertSame('34.50', bc_round('34.5'));
        $this->assertSame('-35', bc_round('-34.5', 0));
        $this->assertSame('0', bc_round('0.4', 0));
    }

    public function testNormFloatTailNoise(): void
    {
        $this->assertSame('0.105', bc_norm(0.105));
        $this->assertSame('0.00001', bc_norm(1e-5));
        $this->assertSame('133.74', bc_norm('133.74'));
        $this->assertSame('0', bc_norm(-0.0));
        $this->assertSame('120', bc_norm(120.0));
    }

    public function testRoundWithFloatInput(): void
    {
        // float 经 bc_norm 展开后无二进制尾噪（2.675 float 实际为 2.674999...）
        $this->assertSame('2.68', bc_round(2.675, 2));
        $this->assertSame('120.00', bc_round(120.0, 2));
    }

    public function testAbs(): void
    {
        $this->assertSame('123.45', bc_abs('123.45'));
        $this->assertSame('123.45', bc_abs('-123.45'));
        $this->assertSame('0', bc_abs('0'));
        $this->assertSame('1.2', bc_abs('-1.2'));
    }

    public function testRoundMatchesPhpRoundOnRegularValues(): void
    {
        foreach (['0.01', '12.34', '99.99', '0.5', '7.499', '1000000.99'] as $v) {
            // (string) 浮点会去尾零（0.5→"0.5"），bc_round 按 scale 补零（"0.50"）——先统一格式再比数值
            $this->assertSame(sprintf('%.2F', round((float) $v, 2)), bc_round($v, 2), "v={$v}");
        }
    }
}
