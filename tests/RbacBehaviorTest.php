<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\AdminPermission;
use PHPUnit\Framework\TestCase;

/**
 * RBAC 权限校验行为：AdminPermission 中间件的核心判定逻辑为
 * 私有方法 hasPermission()（纯函数，无 DB/Redis 依赖），此处经反射
 * 固化其匹配规则；process() 的"无权限拒绝"出口依赖权限数据源
 * （Redis/DB），纯单测只覆盖 adminId 为空的放行路径。
 */
class RbacBehaviorTest extends TestCase
{
    private function invokeHasPermission(array $permissions, string $required): bool
    {
        $method = (new \ReflectionClass(AdminPermission::class))->getMethod('hasPermission');
        $method->setAccessible(true);

        return $method->invoke(new AdminPermission(), $permissions, $required);
    }

    public function testNoPermissionUserIsDenied(): void
    {
        // 无任何权限的用户请求受保护操作 → 拒绝（核心判定）
        $this->assertFalse($this->invokeHasPermission([], 'get.admin/product'));
    }

    public function testExactMatchAllows(): void
    {
        $this->assertTrue($this->invokeHasPermission(['get.admin/product'], 'get.admin/product'));
    }

    public function testDynamicSegmentFallsBackToResourcePermission(): void
    {
        // put.admin/user/123 命中资源级权限 put.admin/user
        $this->assertTrue($this->invokeHasPermission(['put.admin/user'], 'put.admin/user/123'));
        // 跨资源不误放行
        $this->assertFalse($this->invokeHasPermission(['put.admin/order'], 'put.admin/user/123'));
    }

    public function testMethodPrefixIsPartOfPermission(): void
    {
        // 方法前缀参与匹配：get 权限不放行 post 请求
        $this->assertFalse($this->invokeHasPermission(['get.admin/user'], 'post.admin/user'));
        // Route::any 兼容：any.* 权限对任意方法请求放行（含动态段回退）
        $this->assertTrue($this->invokeHasPermission(['any.admin/user'], 'post.admin/user/123'));
    }

    public function testPassThroughWhenNoAdminId(): void
    {
        $request = new FakeRequest();
        $request->adminId = 0;

        $called = false;
        $response = (new AdminPermission())->process($request, function () use (&$called) {
            $called = true;

            return response('next');
        });

        $this->assertTrue($called, '未登录请求应直接放行，不触发权限校验');
    }
}
