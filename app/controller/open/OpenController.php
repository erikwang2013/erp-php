<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\open;

use support\Request;
use support\Response;

/**
 * P0 开放接口演示控制器（/open/v1 组）
 *
 * ping 为公开接口（无需认证）；其余路由组挂 OpenApiAuth 中间件，
 * 认证通过后 $request->openapiApp 即当前请求方应用。
 */
class OpenController
{
    /**
     * 公开连通性检查（无需 API Key）
     */
    public function ping(): Response
    {
        return json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'service' => 'erp-open-api',
                'time' => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * 读取应用自身信息（演示：URL 中的 id 必须与请求方应用一致，仅返回非敏感字段）
     */
    public function apps(Request $request, string $id): Response
    {
        $app = $request->openapiApp;
        if ((string) $app->id !== $id) {
            return json(['code' => 403, 'message' => '无权访问其他应用信息', 'data' => []])->withStatus(403);
        }

        return json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'app_name' => $app->app_name,
                'app_key' => $app->app_key,
                'scopes' => $app->scopes,
                'status' => $app->status,
                'created_at' => $app->created_at,
            ],
        ]);
    }
}
