<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("健康检查")
 */

declare(strict_types=1);

namespace app\admin\controller;

use erikwang2013\apidoc\annotation as Apidoc;

use support\Db;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

class HealthController
{
    /**
     * 健康检查
     * @Apidoc\Title("健康检查")
     * @Apidoc\Desc("检查系统各组件的运行状态，包括数据库、Redis和Elasticsearch连接状态")
     * @Apidoc\Url("/health")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("健康检查")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="健康状态", children={
     *     @Apidoc\Returned("app", type="string", desc="应用名称"),
     *     @Apidoc\Returned("version", type="string", desc="应用版本"),
     *     @Apidoc\Returned("php", type="string", desc="PHP版本"),
     *     @Apidoc\Returned("database", type="string", desc="数据库状态(ok/unavailable)"),
     *     @Apidoc\Returned("redis", type="string", desc="Redis状态(ok/unavailable)"),
     *     @Apidoc\Returned("elasticsearch", type="string", desc="ES状态(ok/unavailable)"),
     *     @Apidoc\Returned("timestamp", type="int", desc="当前时间戳"),
     * })
     */
    public function index(Request $request): Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'app' => 'open-admin',
                'version' => '1.1',
                'php' => PHP_VERSION,
                'database' => $this->checkDb(),
                'redis' => $this->checkRedis(),
                'elasticsearch' => $this->checkES(),
                'timestamp' => time(),
            ],
        ]);
    }

    private function checkDb(): string
    {
        try {
            Db::select('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            // 有意为之：健康检查端点必须始终可响应，组件故障以状态值上报而非抛异常
            return 'unavailable';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();

            return 'ok';
        } catch (Throwable) {
            // 有意为之：健康检查端点必须始终可响应，组件故障以状态值上报而非抛异常
            return 'unavailable';
        }
    }

    private function checkES(): string
    {
        try {
            $hosts = config('plugin.erikwang2013.webman-scout.scout.hosts', ['http://localhost:9200']);
            $client = new \GuzzleHttp\Client(['timeout' => 2]);
            $resp = $client->get(rtrim($hosts[0], '/') . '/_cluster/health');
            $body = json_decode((string) $resp->getBody(), true);

            return $body['status'] ?? 'unknown';
        } catch (Throwable) {
            // 有意为之：健康检查端点必须始终可响应，组件故障以状态值上报而非抛异常
            return 'unavailable';
        }
    }
}
