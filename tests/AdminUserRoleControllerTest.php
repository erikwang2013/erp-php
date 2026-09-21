<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\admin\controller\RoleController;
use app\admin\controller\UserController;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 管理端用户/角色控制器：校验失败路径与批量操作边界（JWT/RBAC 中间件不参与单测）
 */
class AdminUserRoleControllerTest extends TestCase
{
    private function code(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    private function message(Response $resp): string
    {
        $body = json_decode($resp->rawBody(), true);

        return (string) ($body['message'] ?? '');
    }

    /* ======================== UserController ======================== */

    public function testStoreRejectsMissingUsername(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'password' => 'secret123',
            'real_name' => '张三',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testStoreRejectsShortPassword(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'username' => 'zhangsan',
            'password' => '123',
            'real_name' => '张三',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testStoreRejectsMissingRealName(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'username' => 'zhangsan',
            'password' => 'secret123',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testStoreRejectsInvalidStatus(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'username' => 'zhangsan',
            'password' => 'secret123',
            'real_name' => '张三',
            'status' => 9,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testBatchStatusRejectsEmptyIds(): void
    {
        $resp = (new UserController())->batchStatus(new FakeRequest([
            'ids' => [],
            'status' => 1,
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertSame('请选择用户', $this->message($resp));
    }

    public function testBatchStatusRejectsNonArrayIds(): void
    {
        $resp = (new UserController())->batchStatus(new FakeRequest([
            'ids' => '1,2',
            'status' => 1,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testBatchStatusRejectsInvalidStatusValue(): void
    {
        $resp = (new UserController())->batchStatus(new FakeRequest([
            'ids' => ['abc'],
            'status' => 5,
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertSame('状态值无效', $this->message($resp));
    }

    public function testBatchDestroyRejectsEmptyIds(): void
    {
        $resp = (new UserController())->batchDestroy(new FakeRequest([
            'ids' => [],
            'password' => 'secret123',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertSame('请选择要删除的用户', $this->message($resp));
    }

    public function testBatchDestroyRejectsNonArrayIds(): void
    {
        $resp = (new UserController())->batchDestroy(new FakeRequest([
            'ids' => 'abc',
            'password' => 'secret123',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    /**
     * role_ids 非法即 422（须在触库前拦下：归一发生在 find/exists 之前，故本用例无需 DB）。
     * 关联表 erp_admin_user_role 无 FK 约束，放行垃圾值只会静默写孤儿行。
     */
    public function testStoreRejectsInvalidRoleIds(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'username' => 'zhangsan',
            'password' => 'secret123',
            'real_name' => '张三',
            'role_ids' => ['abc'],
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('role_ids', $this->message($resp));
    }

    /** 数组元素不是标量：PHP 里 (int)[] === 1，若不拦会凭空授出 id=1 */
    public function testStoreRejectsNonScalarRoleIds(): void
    {
        $resp = (new UserController())->store(new FakeRequest([
            'username' => 'zhangsan',
            'password' => 'secret123',
            'real_name' => '张三',
            'role_ids' => [[]],
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testUpdateRejectsInvalidRoleIds(): void
    {
        $resp = (new UserController())->update(new FakeRequest(['role_ids' => ['abc']]), 'irrelevant');
        $this->assertSame(422, $this->code($resp));
    }

    /* ======================== RoleController ======================== */

    public function testRoleStoreRejectsMissingName(): void
    {
        $resp = (new RoleController())->store(new FakeRequest([
            'slug' => 'admin',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testRoleStoreRejectsMissingSlug(): void
    {
        $resp = (new RoleController())->store(new FakeRequest([
            'name' => '管理员',
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testRoleStoreRejectsEmptyPayload(): void
    {
        $resp = (new RoleController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }
}
