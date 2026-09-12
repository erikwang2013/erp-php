<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\common\I18n;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use Webman\Context;
use Webman\Http\Request as WebmanRequest;

/**
 * i18n 请求级 locale 契约：I18n::trans() 未显式传 locale 时须从当前请求的
 * Accept-Language 解析（此前恒取配置默认 zh_CN，resource/translations/ 形同虚设）；
 * 无请求上下文（CLI/队列）时落回配置默认，行为与改造前一致。
 */
class I18nLocaleTest extends TestCase
{
    /** 模拟 webman App::onMessage 的 Context::reset，跑完即销毁，避免污染其它用例。 */
    private function withRequest(string $acceptLanguage, callable $assert): void
    {
        $request = new \support\Request(
            "GET /x HTTP/1.1\r\nHost: localhost\r\nAccept-Language: {$acceptLanguage}\r\n\r\n"
        );
        Context::reset(new ArrayObject([WebmanRequest::class => $request]));
        try {
            $assert();
        } finally {
            Context::destroy();
        }
    }

    public function testLocaleComesFromAcceptLanguage(): void
    {
        $this->withRequest('en-US,en;q=0.9', function (): void {
            self::assertSame('en', I18n::getLocale(request()));
            self::assertSame('Operation successful', I18n::trans('Operation successful'));
        });

        $this->withRequest('zh-CN,zh;q=0.9', function (): void {
            self::assertSame('zh_CN', I18n::getLocale(request()));
            self::assertSame('操作成功', I18n::trans('Operation successful'));
        });

        // 带地区的 2 字母语言须剥掉地区段：de_de 无对应目录会白白落回 zh_CN
        $this->withRequest('de-DE,de;q=0.9', function (): void {
            self::assertSame('de', I18n::getLocale(request()));
            self::assertSame('Vorgang erfolgreich', I18n::trans('Operation successful'));
        });
    }

    public function testFallsBackToConfigWithoutRequest(): void
    {
        self::assertSame('zh_CN', I18n::getLocale());
        self::assertSame('zh_CN', I18n::getLocale(null));
        self::assertSame('操作成功', I18n::trans('Operation successful'));
    }

    public function testExplicitLocaleWinsOverRequest(): void
    {
        $this->withRequest('en-US,en;q=0.9', function (): void {
            self::assertSame('操作成功', I18n::trans('Operation successful', [], 'zh_CN'));
        });
    }
}
