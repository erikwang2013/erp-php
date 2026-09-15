<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\SecurityFilter;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;

/**
 * 待扫数据装配的回归夹逼：**任何来源的值都不许被同名参数挤出扫描面**。
 *
 * 背景：payload() 原用 array_merge(cookie, get, post, files) 拼扫描面，后者覆盖前者的
 * 同名值，而检测器是按值扫的 —— 攻击者只要在 query 里放一个与恶意 cookie 同名的无害
 * 值，恶意值就整个离开扫描面。集成实测（真实 Request 过中间件）：
 *   Cookie: evil=<script>alert(1)</script>              → 403
 *   Cookie: evil=<script>alert(1)</script>  +  ?evil=1  → 200  ← 绕过
 *
 * 合并本身自 security-php v1.3.3 起由 SecurityGuard::mergeRequestSources() 承担
 * （插件自带的 Webman 中间件中过同一个洞，v1.3.3 一并修）。策略换实现是插件的事，
 * 本类只锁**我们这条装配线**：四个来源都得进 merge，且值不许丢。
 *
 * 断言故意只看"值在不在"，不看键名 —— 插件把被顶掉的值挂成
 * _<来源>.<键>（如 _cookie.evil），那是它的内部命名，不该被我们钉死。
 *
 * 注：payload() 私有，故用反射。首次调用会让插件按**默认配置**兜底 init（无网络、
 * 仅文件存储），因为我们没走 boot()；这只影响本测试进程，不触达 Redis。
 */
class SecurityFilterPayloadTest extends TestCase
{
    /**
     * 核心回归：cookie 与 query 同名，两个值都必须还在扫描面里
     */
    public function testSameNameCookieAndQueryBothReachScan(): void
    {
        $payload = $this->payloadFor(
            "GET /admin/v1/x?evil=1 HTTP/1.1\r\nHost: erp.example.com\r\n"
            . "Cookie: evil=<script>alert(1)</script>\r\n\r\n"
        );

        $values = $this->scalars($payload);

        $this->assertContains('<script>alert(1)</script>', $values, 'cookie 的恶意值被同名 query 挤掉了');
        $this->assertContains('1', $values, 'query 的值也必须在（它同样可能携带攻击）');
    }

    /**
     * post 遮蔽 get 同理
     */
    public function testSameNameQueryAndPostBothReachScan(): void
    {
        $body = 'sql=safe';
        $payload = $this->payloadFor(
            "POST /admin/v1/x?sql=DROP+TABLE+erp_users HTTP/1.1\r\nHost: erp.example.com\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body
        );

        $values = $this->scalars($payload);

        $this->assertContains('DROP TABLE erp_users', $values, 'query 的恶意值被同名 post 挤掉了');
        $this->assertContains('safe', $values);
    }

    /**
     * 上传文件的键也曾被直接赋值覆盖（插件同款缺陷的第三处），
     * 文件来源必须作为一路进 merge，不能事后 $data[$key] = ...
     */
    public function testUploadSourceJoinsTheScan(): void
    {
        $bound = '----SecPayloadBoundary';
        $body = "--$bound\r\nContent-Disposition: form-data; name=\"doc\"; filename=\"a.pdf\"\r\n"
            . "Content-Type: application/pdf\r\n\r\n%PDF-1.4 x\r\n--$bound--\r\n";
        $payload = $this->payloadFor(
            "POST /admin/v1/x HTTP/1.1\r\nHost: erp.example.com\r\n"
            . "Content-Type: multipart/form-data; boundary=$bound\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n" . $body
        );

        $this->assertContains('a.pdf', $this->scalars($payload), '上传文件名没进扫描面');
    }

    /**
     * 正常请求的键值不得被改形 —— 防遮蔽不能以打乱业务数据为代价
     */
    public function testPlainRequestUnchanged(): void
    {
        $payload = $this->payloadFor(
            "GET /admin/v1/user/list?page=1&size=20 HTTP/1.1\r\nHost: erp.example.com\r\n\r\n"
        );

        $this->assertSame('1', $payload['page']);
        $this->assertSame('20', $payload['size']);
    }

    /**
     * 用反射驱动私有装配线；不 boot 插件，只走 Request → payload 这一段
     */
    private function payloadFor(string $raw): array
    {
        $method = new \ReflectionMethod(SecurityFilter::class, 'payload');

        return $method->invoke(null, new Request($raw));
    }

    /**
     * 递归取所有标量值，作为「值是否还在扫描面里」的判据
     *
     * @return string[]
     */
    private function scalars(array $data): array
    {
        $out = [];
        array_walk_recursive($data, static function ($v) use (&$out): void {
            if (is_scalar($v)) {
                $out[] = (string) $v;
            }
        });

        return $out;
    }
}
