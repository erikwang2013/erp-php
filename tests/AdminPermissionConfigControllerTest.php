<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\admin\controller\ConfigController;
use app\admin\controller\PermissionController;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 管理端权限/系统配置控制器：校验失败路径
 */
class AdminPermissionConfigControllerTest extends TestCase
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

    /* ======================== PermissionController ======================== */

    public function testPermissionStoreRejectsMissingName(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([
            'slug' => 'user:create',
            'type' => 2,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testPermissionStoreRejectsMissingSlug(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([
            'name' => '创建用户',
            'type' => 2,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testPermissionStoreRejectsInvalidType(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([
            'name' => '创建用户',
            'slug' => 'user:create',
            'type' => 99,
        ]));
        $this->assertSame(422, $this->code($resp));
    }

    public function testPermissionStoreRejectsEmptyPayload(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }

    /**
     * store() 的 parent_id 双模解码在查库之前，所以这两条拒绝路径无需 DB 即可断言。
     * （存在性/自引用/成环三条守卫要读 admin_permission 表，集成用例才有得测，
     * 不在本文件覆盖范围。）
     */
    public function testPermissionStoreRejectsGarbageParentId(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([
            'name' => '创建用户',
            'slug' => 'user:create',
            'type' => 2,
            'parent_id' => 'abc',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('父级权限无效', $this->message($resp));
    }

    public function testPermissionStoreRejectsNonPositiveParentId(): void
    {
        $resp = (new PermissionController())->store(new FakeRequest([
            'name' => '创建用户',
            'slug' => 'user:create',
            'type' => 2,
            'parent_id' => '-1',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('父级权限无效', $this->message($resp));
    }

    /* ======================== ConfigController ======================== */

    public function testConfigStoreRejectsMissingGroup(): void
    {
        $resp = (new ConfigController())->store(new FakeRequest([
            'key' => 'site_name',
            'value' => 'ERP',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('group', $this->message($resp));
    }

    public function testConfigStoreRejectsMissingKey(): void
    {
        $resp = (new ConfigController())->store(new FakeRequest([
            'group' => 'site',
            'value' => 'ERP',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('key', $this->message($resp));
    }

    public function testConfigStoreRejectsMissingValue(): void
    {
        $resp = (new ConfigController())->store(new FakeRequest([
            'group' => 'site',
            'key' => 'site_name',
        ]));
        $this->assertSame(422, $this->code($resp));
        $this->assertStringContainsString('value', $this->message($resp));
    }

    public function testConfigStoreRejectsEmptyPayload(): void
    {
        $resp = (new ConfigController())->store(new FakeRequest([]));
        $this->assertSame(422, $this->code($resp));
    }
}
