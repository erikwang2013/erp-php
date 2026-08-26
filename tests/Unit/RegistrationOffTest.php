<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\api\v1\controller\AuthController;
use PHPUnit\Framework\TestCase;

/**
 * 注册接口配置开关回归测试
 *
 * 覆盖：REGISTRATION_ENABLED 默认（未配置）时注册返回 403；
 * 显式开启后进入正常校验流程（空参数返回 422 而非 403）。
 */
class RegistrationOffTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('REGISTRATION_ENABLED');
    }

    public function testRegisterDisabledByDefault(): void
    {
        putenv('REGISTRATION_ENABLED');
        $controller = new AuthController();
        $response = $controller->register(new FakeRequest(['username' => 'testuser']));
        $payload = json_decode($response->rawBody(), true);
        $this->assertSame(403, $payload['code'] ?? null);
    }

    public function testRegisterDisabledWhenEnvIsZero(): void
    {
        putenv('REGISTRATION_ENABLED=0');
        $controller = new AuthController();
        $response = $controller->register(new FakeRequest(['username' => 'testuser']));
        $payload = json_decode($response->rawBody(), true);
        $this->assertSame(403, $payload['code'] ?? null);
    }

    public function testRegisterEnabledProceedsToValidation(): void
    {
        putenv('REGISTRATION_ENABLED=1');
        $controller = new AuthController();
        // 开关放行后走正常校验分支（缺参 → 422），证明未被开关拦截
        $response = $controller->register(new FakeRequest(['username' => 'testuser']));
        $payload = json_decode($response->rawBody(), true);
        $this->assertSame(422, $payload['code'] ?? null);
    }
}
