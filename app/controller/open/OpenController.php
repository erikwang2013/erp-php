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

 * ping 为公开接口（无需认证）；其余路由组挂 OpenApiAuth 中间件，
 * 认证通过后 $request->openapiApp 即当前请求方应用。
 */
class OpenController
{
    /**
     * 公开连通性检查
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("公开连通性检查")]
#[\erikwang2013\apidoc\annotation\Desc("无需 API Key 的公开接口，返回服务标识与当前时间，用于连通性检查")]
#[\erikwang2013\apidoc\annotation\Url("/open/v1/ping")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放接口")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("service", type:"string", desc:"服务标识 erp-open-api")]
#[\erikwang2013\apidoc\annotation\Returned("time", type:"string", desc:"服务器当前时间 Y-m-d H:i:s")]

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
     * 读取应用自身信息
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("读取应用自身信息")]
#[\erikwang2013\apidoc\annotation\Desc("URL 中的 id 必须与请求方应用一致(原始数字ID,非hashid)，仅返回非敏感字段")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放接口")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"int", require:true, desc:"应用ID(原始数字ID,需与请求方应用一致)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("app_name", type:"string", desc:"应用名称")]
#[\erikwang2013\apidoc\annotation\Returned("app_key", type:"string", desc:"应用标识(ak_前缀)")]
#[\erikwang2013\apidoc\annotation\Returned("scopes", type:"array", desc:"授权范围路径前缀数组")]
#[\erikwang2013\apidoc\annotation\Returned("status", type:"int", desc:"状态,0=禁用,1=启用")]
#[\erikwang2013\apidoc\annotation\Returned("created_at", type:"string", desc:"创建时间")]

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
