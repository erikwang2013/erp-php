<?php

declare(strict_types=1);

namespace tests;

use app\service\notification\ChannelRouter;
use app\service\notification\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    public function testTemplateRenderer(): void
    {
        $renderer = new TemplateRenderer();
        $result = $renderer->render('Hello {name}, you have {count} messages', ['name' => 'Zhang San', 'count' => '5']);
        $this->assertEquals('Hello Zhang San, you have 5 messages', $result);
    }

    public function testApprovalTemplate(): void
    {
        $renderer = new TemplateRenderer();
        $msg = $renderer->renderNotification('approval_pending', ['applicant' => 'Li Si', 'title' => '请假申请']);
        $this->assertStringContainsString('Li Si', $msg['title']);
        $this->assertStringContainsString('请假申请', $msg['content']);
    }

    public function testUnknownTemplateFallsBack(): void
    {
        $renderer = new TemplateRenderer();
        $msg = $renderer->renderNotification('nonexistent', ['message' => 'Hello']);
        $this->assertEquals('通知', $msg['title']);
    }

    public function testEmailChannelWritesOutbox(): void
    {
        $router = new ChannelRouter();
        $title = '邮件测试 ' . uniqid('', true);

        $result = $router->send(1, $title, '内容', ['email']);

        $this->assertTrue($result['email'] ?? false);
        $logFile = runtime_path() . '/mail/outbox.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString($title, (string) file_get_contents($logFile));
    }

    public function testReservedChannelsReturnFalse(): void
    {
        $router = new ChannelRouter();

        $result = $router->send(1, 't', 'c', ['wecom', 'dingtalk']);

        $this->assertFalse($result['wecom'] ?? true);
        $this->assertFalse($result['dingtalk'] ?? true);
    }
}
