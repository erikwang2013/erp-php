<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\AdminAuth;
use PHPUnit\Framework\TestCase;

/**
 * WebSocket 鉴权测试
 *
 * 覆盖 WebSocket 首条 auth 消息的 JWT 校验复用逻辑（AdminAuth::validateToken）：
 * 空令牌 / 非法令牌 / refresh 令牌均必须被拒绝，且返回对应用户提示。
 */
class WebSocketAuthTest extends TestCase
{
    public function testEmptyTokenRejected(): void
    {
        $result = AdminAuth::validateToken('');
        $this->assertFalse($result['ok']);
        $this->assertSame('未登录', $result['error']);
    }

    public function testInvalidTokenRejected(): void
    {
        // 非法签名令牌：decode 抛出异常，校验失败
        $result = AdminAuth::validateToken('invalid.token.value');
        $this->assertFalse($result['ok']);
        $this->assertSame('Token已过期或无效', $result['error']);
    }

    public function testRefreshTokenRejected(): void
    {
        // 刷新令牌不能作为访问令牌使用（签名有效但 token_type 为 refresh）
        $token = jwt_instance()->encode([
            'sub' => 1,
            'username' => 'tester',
            'token_type' => 'refresh',
        ]);

        $result = AdminAuth::validateToken($token);
        $this->assertFalse($result['ok']);
        $this->assertSame('请使用访问令牌', $result['error']);
    }
}
