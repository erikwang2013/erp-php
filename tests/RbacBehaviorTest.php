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

    /** 只回答 path()/method() 的请求替身（不解析真实 HTTP 报文） */
    private function requestFor(string $path, string $method): FakeRequest
    {
        return new class ($path, $method) extends FakeRequest {
            public function __construct(private string $fakePath, private string $fakeMethod)
            {
                parent::__construct();
            }

            public function path(): string
            {
                return $this->fakePath;
            }

            public function method(): string
            {
                return $this->fakeMethod;
            }
        };
    }

    private function invokePermissionOf(FakeRequest $request): string
    {
        $method = (new \ReflectionClass(AdminPermission::class))->getMethod('permissionOf');
        $method->setAccessible(true);

        return $method->invoke(new AdminPermission(), $request);
    }

    /**
     * 版本段剥离：路由挂在 /admin/v1/* 下，种子 slug（get.admin/product）不带
     * 版本段。Request::path() 带前导斜杠，剥离分支若按 'admin/v1' 前缀判等则
     * 恒不命中，拼出 get.admin/v1/product —— 于是超管（'*' 旁路）之外的
     * 所有角色在全部接口上 403。此用例锁死该契约。
     */
    public function testVersionedPathStripsVersionSegment(): void
    {
        $this->assertSame('get.admin/product', $this->invokePermissionOf($this->requestFor('/admin/v1/product', 'GET')));
        $this->assertSame(
            'post.admin/user/batch/destroy',
            $this->invokePermissionOf($this->requestFor('/admin/v1/user/batch/destroy', 'POST'))
        );
        // 已是 unversioned 的路径原样（仅去前导斜杠）
        $this->assertSame('get.admin/product', $this->invokePermissionOf($this->requestFor('/admin/product', 'GET')));
        // 版本号不为 v1 时不剥离（避免误伤 /admin/v1x 这类前缀相同的路径）
        $this->assertSame('get.admin/v1x/product', $this->invokePermissionOf($this->requestFor('/admin/v1x/product', 'GET')));
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

    /**
     * 权限树的 parent_id 只编码一次：encodeIds 已按 `*_id` 命名把 parent_id 转成
     * hashid，若其后又 `(int)` 取前导数字再 encodeId，会得到 '2JPxVNKM24X' → '2y' 的
     * 错值；客户端按树回写该值时父级就指向 id=2（或 422 父级不存在）。
     */
    public function testPermissionTreeParentIdIsEncodedOnce(): void
    {
        $parentId = 21000000000000002;
        $rows = [
            ['id' => $parentId, 'parent_id' => 0, 'name' => '用户管理', 'slug' => 'get.admin/user', 'type' => 1, 'icon' => '', 'path' => '', 'sort' => 0],
            ['id' => 21000000000000003, 'parent_id' => $parentId, 'name' => '用户列表', 'slug' => 'get.admin/user/index', 'type' => 1, 'icon' => '', 'path' => '', 'sort' => 0],
        ];

        $method = (new \ReflectionClass(\app\admin\controller\PermissionController::class))->getMethod('buildTree');
        $method->setAccessible(true);
        $tree = $method->invoke(new \app\admin\controller\PermissionController(), $rows);

        $this->assertSame(0, $tree[0]['parent_id'], '顶级节点的 parent_id 保持 0');
        $childParent = $tree[0]['children'][0]['parent_id'];
        $this->assertSame(\app\common\HashidsService::encode($parentId), $childParent);
        $this->assertSame($parentId, \app\common\HashidsService::decode((string) $childParent), '回写值必须能还原出父节点 id');
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
