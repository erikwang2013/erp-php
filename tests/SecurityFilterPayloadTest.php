<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\SecurityFilter;
use PHPUnit\Framework\TestCase;

/**
 * 待扫数据合并的回归夹逼。
 *
 * 背景：原来用 array_merge(cookie, get, post, files) 拼扫描面，后一来源会挤掉
 * 前一来源的同名值，而扫描面是按值扫的 —— 攻击者只要在 query 里放一个与恶意
 * cookie 同名的无害值，恶意值就整个离开扫描面。集成实测（真实 Request 过中间件）：
 *   Cookie: evil=<script>alert(1)</script>              → 403
 *   Cookie: evil=<script>alert(1)</script>  +  ?evil=1  → 200  ← 绕过
 * 本类锁死 merge() 的「同名全留」语义。
 */
class SecurityFilterPayloadTest extends TestCase
{
    /**
     * 核心回归：cookie 与 query 同名时两个值都要在，缺一个就是绕过
     */
    public function testSameNameCookieAndQueryBothKept(): void
    {
        $merged = SecurityFilter::merge(
            ['evil' => '<script>alert(1)</script>'],
            ['evil' => '1']
        );

        $this->assertSame(['evil' => ['<script>alert(1)</script>', '1']], $merged);
    }

    /**
     * post 遮蔽 get 同理：后一来源不能吃掉前一来源
     */
    public function testSameNameQueryAndPostBothKept(): void
    {
        $this->assertSame(
            ['sql' => ['DROP TABLE erp_users', 'safe']],
            SecurityFilter::merge(['sql' => 'DROP TABLE erp_users'], ['sql' => 'safe'])
        );
    }

    /**
     * 三个来源同名：嵌套一层也要全留，SecurityGuard::flattenData() 会递归展开
     */
    public function testThreeSourcesAllKept(): void
    {
        $merged = SecurityFilter::merge(['a' => '1'], ['a' => '2'], ['a' => '3']);

        $this->assertSame([['1', '2'], '3'], $merged['a']);
    }

    /**
     * 无同名冲突时值原样保留 —— 不能为了防遮蔽把正常数据也改了形状
     */
    public function testDistinctKeysPassThroughUnchanged(): void
    {
        $cookie = ['sid' => 'abc'];
        $get = ['page' => '1'];
        $post = ['name' => 'x'];

        $this->assertSame(
            ['sid' => 'abc', 'page' => '1', 'name' => 'x'],
            SecurityFilter::merge($cookie, $get, $post)
        );
    }

    /**
     * 单个来源内部的数组值不得被当成冲突包装（?a[]=1&a[]=2 是正常列表）
     */
    public function testArrayValueWithoutCollisionNotWrapped(): void
    {
        $this->assertSame(
            ['tags' => ['x', 'y']],
            SecurityFilter::merge(['tags' => ['x', 'y']])
        );
    }

    /**
     * 上传文件的 {name, tmp_name} 结构在无冲突时保持原形，
     * 有冲突时与对方并列 —— 两者都不能丢
     */
    public function testFileShapePreserved(): void
    {
        $file = ['doc' => ['name' => 'a.pdf', 'tmp_name' => '/tmp/php123']];

        $this->assertSame($file, SecurityFilter::merge([], $file));
        $this->assertSame(
            ['doc' => ['benign', ['name' => 'a.pdf', 'tmp_name' => '/tmp/php123']]],
            SecurityFilter::merge(['doc' => 'benign'], $file)
        );
    }

    /**
     * 顺序即优先级：先 cookie 后 get 后 post 后 files，与调用处一致
     */
    public function testSourceOrderIsPreservedInCollision(): void
    {
        $merged = SecurityFilter::merge(['k' => 'cookie'], ['k' => 'get']);

        $this->assertSame(['cookie', 'get'], $merged['k']);
    }

    /**
     * 空值也要参与冲突判定：array_key_exists 而非 isset，
     * 否则空串 cookie 会被后来源悄悄顶掉（值没了就是扫描面缺一块）
     */
    public function testEmptyValueStillCollides(): void
    {
        $this->assertSame(['', 'x'], SecurityFilter::merge(['k' => ''], ['k' => 'x'])['k']);
        $this->assertSame(['x', ''], SecurityFilter::merge(['k' => 'x'], ['k' => ''])['k']);
    }
}
