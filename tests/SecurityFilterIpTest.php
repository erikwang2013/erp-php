<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\SecurityFilter;
use PHPUnit\Framework\TestCase;

/**
 * 客户端 IP 解析的回归夹逼。
 *
 * 背景：workerman Request::getRealIp() 在对端是内网地址时取 X-Forwarded-For 的
 * **最左值**（webman Request.php:217），而 nginx 用追加式 $proxy_add_x_forwarded_for
 * 时最左值由客户端自己塞入 —— 来源 IP 可任意伪造，会污染安全插件的黑名单与会话基线。
 * SecurityFilter::resolveIp() 改成取最右一跳修掉它，本类锁死这个行为。
 */
class SecurityFilterIpTest extends TestCase
{
    /**
     * 对端是公网地址：直接采信 TCP 对端，XFF 无论写什么都不作数
     */
    public function testPublicPeerIgnoresForgedXff(): void
    {
        $this->assertSame(
            '203.0.113.9',
            SecurityFilter::resolveIp('203.0.113.9', '1.2.3.4')
        );
    }

    /**
     * 核心回归：对端是 nginx（内网），追加式 XFF 下最左值是伪造的，
     * 必须取最右一跳 —— 那才是 nginx 实际看到的客户端
     */
    public function testAppendStyleXffReturnsRightmostNotForgedLeftmost(): void
    {
        $this->assertSame(
            '203.0.113.9',
            SecurityFilter::resolveIp('172.18.0.5', '1.2.3.4, 203.0.113.9')
        );
    }

    /**
     * 覆盖式 XFF（proxy_set_header 直接赋值）：只有一个值，仍然取最右
     */
    public function testOverwriteStyleXff(): void
    {
        $this->assertSame(
            '203.0.113.9',
            SecurityFilter::resolveIp('172.18.0.5', '203.0.113.9')
        );
    }

    /**
     * 无 XFF：回落到 TCP 对端
     */
    public function testNoXffFallsBackToPeer(): void
    {
        $this->assertSame('172.18.0.5', SecurityFilter::resolveIp('172.18.0.5', ''));
    }

    /**
     * XFF 不是合法 IP：回落到 TCP 对端，不能把垃圾串当 IP 用
     */
    public function testInvalidXffFallsBackToPeer(): void
    {
        $this->assertSame('172.18.0.5', SecurityFilter::resolveIp('172.18.0.5', 'not-an-ip'));
        $this->assertSame('172.18.0.5', SecurityFilter::resolveIp('172.18.0.5', ', ,'));
    }

    /**
     * 空跳先被剔除再取最右 —— 这是安全性使然，不是实现细节：
     * 攻击者发 "X-Forwarded-For: 1.2.3.4," 经 nginx 追加成 "1.2.3.4,, <真实客户端>"，
     * 剔空后最右仍是真实客户端，伪造值不会因形似「最后一跳」而胜出。
     */
    public function testEmptyHopsRemovedBeforeTakingLast(): void
    {
        $this->assertSame(
            '203.0.113.9',
            SecurityFilter::resolveIp('172.18.0.5', '1.2.3.4,, 203.0.113.9')
        );
        $this->assertSame(
            '1.2.3.4',
            SecurityFilter::resolveIp('172.18.0.5', '1.2.3.4, ')
        );
    }

    /**
     * 对端不可用（0.0.0.0）时同样不会采信 XFF —— 0.0.0.0 属保留段
     */
    public function testReservedPeerStillFollowsProxyRules(): void
    {
        $this->assertSame('203.0.113.9', SecurityFilter::resolveIp('0.0.0.0', '203.0.113.9'));
        $this->assertSame('0.0.0.0', SecurityFilter::resolveIp('0.0.0.0', ''));
    }

    /**
     * 空白与多余逗号要被规范化掉
     */
    public function testWhitespaceNormalised(): void
    {
        $this->assertSame(
            '203.0.113.9',
            SecurityFilter::resolveIp('10.0.0.1', ' 1.2.3.4 ,  203.0.113.9  ')
        );
    }
}
