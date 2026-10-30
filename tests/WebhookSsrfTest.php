<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\service\notification\WebhookService;
use PHPUnit\Framework\TestCase;
use Webman\Config;

/**
 * Webhook 投递地址 SSRF 防护：内网/回环/链路本地/元数据地址必须拒绝。
 */
class WebhookSsrfTest extends TestCase
{
    public function testLoopbackAndMetadataAddressesRejected(): void
    {
        foreach ([
            'http://127.0.0.1/hook',
            'http://127.0.0.1:443/hook',
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/hook',
            'http://192.168.1.10:8080/hook',
            'http://172.16.0.1/hook',
            'http://localhost/hook',
            'http://[::1]/hook',
            'http://[fc00::1]/hook',
            'http://2130706433/hook', // 十进制形式的 127.0.0.1
        ] as $url) {
            $this->assertFalse(WebhookService::isSafeUrl($url), "{$url} 应被拒绝");
        }
    }

    public function testReservedAndCgnatAddressesRejected(): void
    {
        foreach ([
            'http://100.64.0.1/hook',      // 运营商级 NAT
            'http://100.127.255.254/hook', // CGNAT 上边界
            'http://198.18.0.1/hook',      // 基准测试
            'http://198.19.255.254/hook',
            'http://192.0.0.1/hook',       // IETF 协议分配
            'http://192.0.2.1/hook',       // TEST-NET-1
            'http://198.51.100.1/hook',    // TEST-NET-2
            'http://203.0.113.1/hook',     // TEST-NET-3
            'http://240.0.0.1/hook',       // 保留
            'http://[2001:db8::1]/hook',   // 文档网段
        ] as $url) {
            $this->assertFalse(WebhookService::isSafeUrl($url), "{$url} 应被拒绝");
        }
        $this->assertTrue(WebhookService::isPublicIp('100.128.0.1'), 'CGNAT 之外的 100/8 地址不应被误伤');
    }

    public function testNonHttpSchemesAndPortsRejected(): void
    {
        $this->assertFalse(WebhookService::isSafeUrl('file:///etc/passwd'));
        $this->assertFalse(WebhookService::isSafeUrl('gopher://8.8.8.8/'));
        $this->assertFalse(WebhookService::isSafeUrl('https://8.8.8.8:8443/hook'));
        $this->assertFalse(WebhookService::isSafeUrl('not-a-url'));
    }

    public function testPublicAddressesAllowed(): void
    {
        $this->assertTrue(WebhookService::isSafeUrl('https://8.8.8.8/hook'));
        $this->assertTrue(WebhookService::isSafeUrl('http://1.1.1.1:80/hook'));
        $this->assertTrue(WebhookService::isSafeUrl('https://[2001:4860:4860::8888]/hook'));
    }

    public function testPublicHostnameAllowedWhenDnsAvailable(): void
    {
        if (gethostbynamel('example.com') === false) {
            $this->markTestSkipped('测试环境无 DNS');
        }
        $this->assertTrue(WebhookService::isSafeUrl('https://example.com/hook'));

        $target = WebhookService::resolveSafeTarget('https://example.com/hook');
        $this->assertNotNull($target);
        $this->assertSame('example.com', $target['host']);
        $this->assertSame(443, $target['port']);
        $this->assertNotEmpty($target['ips']);
        foreach ($target['ips'] as $ip) {
            $this->assertTrue(WebhookService::isPublicIp($ip), "解析结果 {$ip} 应为公网地址");
        }
    }

    public function testResolveSafeTargetReturnsPinnedIpsAndRejectsPrivate(): void
    {
        $this->assertSame(
            ['host' => '8.8.8.8', 'port' => 443, 'ips' => ['8.8.8.8']],
            WebhookService::resolveSafeTarget('https://8.8.8.8/hook')
        );
        $this->assertSame(
            ['host' => '2001:4860:4860::8888', 'port' => 443, 'ips' => ['2001:4860:4860::8888']],
            WebhookService::resolveSafeTarget('https://[2001:4860:4860::8888]/hook')
        );

        foreach ([
            'http://127.0.0.1/hook',
            'http://[::1]/hook',
            'http://10.0.0.1/hook',
            'http://169.254.169.254/latest/meta-data',
            'https://8.8.8.8:8443/hook',
            'not-a-url',
        ] as $url) {
            $this->assertNull(WebhookService::resolveSafeTarget($url), "{$url} 不应返回可投递目标");
        }
    }

    public function testResolveEntriesFormat(): void
    {
        $this->assertSame(
            ['hooks.example.com:443:1.2.3.4'],
            WebhookService::resolveEntries(['host' => 'hooks.example.com', 'port' => 443, 'ips' => ['1.2.3.4']])
        );
        $this->assertSame(
            ['hooks.example.com:443:[2001:4860:4860::8888]'],
            WebhookService::resolveEntries([
                'host' => 'hooks.example.com',
                'port' => 443,
                'ips' => ['2001:4860:4860::8888'],
            ])
        );
        $this->assertSame(
            ['hooks.example.com:8080:1.2.3.4', 'hooks.example.com:8080:[2001:4860:4860::8888]'],
            WebhookService::resolveEntries([
                'host' => 'hooks.example.com',
                'port' => 8080,
                'ips' => ['1.2.3.4', '2001:4860:4860::8888'],
            ])
        );
    }

    public function testAllowedPortsComeFromConfig(): void
    {
        // 默认仍是 80/443
        $this->assertTrue(WebhookService::isSafeUrl('http://1.1.1.1:80/hook'));
        $this->assertTrue(WebhookService::isSafeUrl('https://8.8.8.8/hook'));
        $this->assertFalse(WebhookService::isSafeUrl('http://1.1.1.1:8080/hook'));

        $this->withAllowedPorts([8080], function (): void {
            $this->assertTrue(WebhookService::isSafeUrl('http://1.1.1.1:8080/hook'), '配置后应允许 8080');
            $this->assertFalse(WebhookService::isSafeUrl('https://8.8.8.8/hook'), '未配置的 443 应拒绝');
            $this->assertFalse(WebhookService::isSafeUrl('http://1.1.1.1:80/hook'), '未配置的 80 应拒绝');
        });
    }

    private function withAllowedPorts(array $ports, callable $assertions): void
    {
        $ref = new \ReflectionClass(Config::class);
        $configProp = $ref->getProperty('config');
        $cacheProp = $ref->getProperty('flatCache');
        $oldConfig = $configProp->getValue();
        $oldCache = $cacheProp->getValue();
        $config = $oldConfig;
        $config['openapi']['webhook']['allowed_ports'] = $ports;
        $configProp->setValue(null, $config);
        $cacheProp->setValue(null, []);
        try {
            $assertions();
        } finally {
            $configProp->setValue(null, $oldConfig);
            $cacheProp->setValue(null, $oldCache);
        }
    }
}
