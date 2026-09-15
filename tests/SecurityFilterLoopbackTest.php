<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use Erikwang2013\Security\SecurityGuard;
use PHPUnit\Framework\TestCase;

/**
 * 回环地址开发期的 WAF 自伤回归。
 *
 * ssrf / dns_rebinding 两个检测器无字段作用域（对**所有**字段跑内网 URL 正则），
 * 而 Origin（SecurityGuard.php:157 注入的 _server.HTTP_ORIGIN）与 Referer
 * （app\middleware\SecurityFilter::payload() 为保覆盖主动补进扫描面）在
 * 前端与 API 同在本机时**必然是** http://localhost:4200 / localhost:8788。
 * 二者一起把开发机上的每个后台 POST 判成 critical：当场 403（获取验证码、退出登录…），
 * 并经 SecurityGuard.php:192 累计触发 IpBlacklist 封禁 127.0.0.1 900 秒。
 * 两个检测器现为 log 模式：命中仍进日志，但不拦、不累计。
 *
 * 本测试锁的是**结果**（回环来源的后台 POST 不被拦），不是锁配置写法 ——
 * 谁把 mode 改回 block，这里就红，这是有意为之。
 */
class SecurityFilterLoopbackTest extends TestCase
{
    private string $storage;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . '/sec_loopback_test_' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        SecurityGuard::reset();
        if (is_file($this->storage)) {
            unlink($this->storage);
        }
    }

    /**
     * 用**本项目真配置**初始化检测链。
     *
     * 存储强制走 file：CLI 无协程连接池（插件的 redis 实例由 SecurityFilter 注入），
     * 且真实存储里可能正封着 127.0.0.1，会污染断言。
     *
     * @param array<string,string> $modeOverrides 检测器名 => mode，用于阴性对照
     */
    private function boot(array $modeOverrides = []): void
    {
        $config = require config_path() . '/plugin/erikwang2013/security-php/app.php';
        $config['storage']['type'] = 'file';
        $config['storage']['file']['path'] = $this->storage;
        foreach ($modeOverrides as $detector => $mode) {
            $config['detectors'][$detector]['mode'] = $mode;
        }
        SecurityGuard::reset();
        SecurityGuard::init($config);
    }

    /**
     * 按 app\middleware\SecurityFilter 的 payload()/meta() 同口径构造一次请求。
     *
     * @return array{block:?array,hits:string[]}
     */
    private function probe(string $origin, string $host, string $referer): array
    {
        $data = SecurityGuard::mergeRequestSources([
            'cookie' => [], 'get' => [], 'post' => ['type' => 'login'], 'file' => [],
        ]);
        $data['headers.User-Agent'] = 'Mozilla/5.0';
        $data['headers.Referer'] = $referer;
        $data['uri'] = '/admin/v1/captcha/generate';

        $threats = SecurityGuard::guard($data, [
            'ip' => '127.0.0.1', 'method' => 'POST', 'uri' => '/admin/v1/captcha/generate',
            'content_length' => '23', 'content_type' => 'application/json',
            'origin' => $origin, 'host' => $host,
            'x_forwarded_for' => '', 'transfer_encoding' => '',
            'cookies' => [], 'user_agent' => 'Mozilla/5.0',
            'headers' => ['authorization' => 'Bearer test', 'x-token' => '', 'x-auth-token' => ''],
        ]);

        $hits = [];
        foreach ($threats as $threat) {
            $arr = is_object($threat) ? get_object_vars($threat) : (array) $threat;
            $type = (string) ($arr['type'] ?? '?');
            $hits[] = $type . '/' . ($arr['severity'] ?? '?')
                . '/mode=' . (SecurityGuard::detectorOption($type, 'mode', '?') ?? '?');
        }

        return ['block' => SecurityGuard::blockDecision($threats), 'hits' => $hits];
    }

    public function testAdminPostFromLoopbackSpaIsNotBlocked(): void
    {
        $this->boot();
        $r = $this->probe('http://localhost:4200', 'localhost:8788', 'http://localhost:4200/admin/captcha');

        self::assertNull(
            $r['block'],
            '本机前后端分离开发时，后台 POST 不该被安全策略拦下：' . json_encode($r['block'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function testLoopbackHitsAreStillDetectedInLogMode(): void
    {
        $this->boot();
        $r = $this->probe('http://localhost:4200', 'localhost:8788', 'http://localhost:4200/admin/captcha');

        // 降的是 mode，不是删规则：特征仍须命中并进日志，否则「保留 enabled 便于日后核对」是空话
        self::assertNotEmpty($r['hits'], 'ssrf/dns_rebinding 规则应当仍然命中');
        foreach ($r['hits'] as $hit) {
            self::assertStringContainsString('/mode=log', $hit, '命中的检测器应处于 log 模式：' . $hit);
        }
    }

    public function testLogModeHitsDoNotEscalateToIpBan(): void
    {
        $this->boot();

        // 旧行为：5 次 / 60 秒即封禁 900 秒，封禁后连正常请求也 403（ip_blacklist 为 block 模式）
        for ($i = 0; $i < 12; $i++) {
            $r = $this->probe('http://localhost:4200', 'localhost:8788', 'http://localhost:4200/admin/captcha');
            self::assertNull(
                $r['block'],
                "第 {$i} 次请求被拦（说明 log 命中仍计入封禁累计）：" . json_encode($r['block'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    /**
     * 阴性对照：同一条请求在 ssrf=block 下必须被拦。
     *
     * 没有这一条，上面「不被拦」的断言可能只是因为判据根本没命中（$hits 为空也能全绿）。
     */
    public function testBlockModeStillBlocksTheSameRequest(): void
    {
        $this->boot(['ssrf' => 'block']);
        $r = $this->probe('http://localhost:4200', 'localhost:8788', 'http://localhost:4200/admin/captcha');

        self::assertNotNull($r['block'], 'ssrf 回到 block 模式后同一请求应被拦，否则本文件的判据空转');
    }

    /**
     * 生产口径：Origin/Referer 是真域名时，ssrf 规则本就不命中，故降级不影响生产的拦截能力。
     */
    public function testRealDomainOriginDoesNotTripSsrfEvenInBlockMode(): void
    {
        $this->boot(['ssrf' => 'block']);
        $r = $this->probe('https://erp.example.com', 'erp.example.com', 'https://erp.example.com/admin/captcha');

        self::assertSame(
            [],
            array_values(array_filter($r['hits'], static fn (string $h): bool => str_starts_with($h, 'ssrf/'))),
            '真域名不该命中 ssrf：' . implode(' | ', $r['hits'])
        );
    }
}
