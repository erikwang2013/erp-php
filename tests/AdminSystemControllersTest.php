<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\admin\controller\DocsController;
use app\admin\controller\HealthController;
use app\admin\controller\MetricsController;
use app\admin\controller\UploadController;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 管理端系统类控制器：健康检查/指标/API文档/上传（均为 fail-open 或纯逻辑，无 DB 依赖）
 */
class AdminSystemControllersTest extends TestCase
{
    private function code(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    /* ======================== HealthController ======================== */

    public function testHealthAlwaysRespondsOkEvenWithoutDbRedis(): void
    {
        $resp = (new HealthController())->index(new FakeRequest([]));
        $this->assertSame(0, $this->code($resp));

        $body = json_decode($resp->rawBody(), true);
        $data = $body['data'];
        $this->assertSame('open-admin', $data['app']);
        $this->assertSame('1.1', $data['version']);
        $this->assertContains($data['database'], ['ok', 'unavailable']);
        $this->assertContains($data['redis'], ['ok', 'unavailable']);
        // yellow：单节点 ES 集群无副本分片时的合法健康状态（见 checkES 直接透传 /_cluster/health 的 status）
        $this->assertContains($data['elasticsearch'], ['ok', 'yellow', 'unavailable', 'unknown']);
        $this->assertIsInt($data['timestamp']);
    }

    /* ======================== MetricsController ======================== */

    public function testMetricsAlwaysReturnsPrometheusText(): void
    {
        // db/redis 状态随测试环境而定（0=down, 1=up），仅断言行存在且值合法
        $resp = (new MetricsController())->index(new FakeRequest([]));
        $body = $resp->rawBody();
        $this->assertStringContainsString('# HELP open_admin_info', $body);
        $this->assertMatchesRegularExpression('/^open_admin_active_users \d+$/m', $body);
        $this->assertMatchesRegularExpression('/^open_admin_total_users \d+$/m', $body);
        $this->assertMatchesRegularExpression('/^open_admin_db_up [01]$/m', $body);
        $this->assertMatchesRegularExpression('/^open_admin_redis_up [01]$/m', $body);
        $this->assertMatchesRegularExpression('/^open_admin_queue_backlog \d+$/m', $body);
        $this->assertMatchesRegularExpression('/^open_admin_websocket_connections \d+$/m', $body);
    }

    /* ======================== DocsController ======================== */

    public function testDocsSpecIsOpenApi303(): void
    {
        // 文档端点返回 OpenAPI 规范本体（无统一 code 包装），直接断言规范结构
        $resp = (new DocsController())->index(new FakeRequest([]));
        $body = json_decode($resp->rawBody(), true);
        $this->assertSame('3.0.3', $body['openapi']);
        $this->assertSame('开放管理后台 API', $body['info']['title']);
        $this->assertArrayHasKey('paths', $body);
        $this->assertArrayHasKey('/health', $body['paths']);
        $this->assertArrayHasKey('/admin/user', $body['paths']);
        $this->assertArrayHasKey('bearerAuth', $body['components']['securitySchemes']);
    }

    /* ======================== UploadController ======================== */

    // UploadController::upload 依赖 Workerman 真实上传报文（file() 访问未初始化 buffer），
    // 无 HTTP 环境下无法构造，属集成测试范畴，单测跳过。
}
