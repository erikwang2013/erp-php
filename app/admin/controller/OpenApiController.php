<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\OpenApiApp;
use app\model\WebhookSubscription;
use support\Request;
use support\Response;

/**
 * P0 OpenAPI 开放平台应用管理
 *
 * app_key 为公开标识（ak_ 前缀）；app_secret 加密入库，仅在创建 / 重置时明文展示一次，
 * 请管理员自行留存（丢失后只能重置）。
 */
class OpenApiController extends BaseController
{
    /**
     * 开放平台应用列表
     */#[\erikwang2013\apidoc\annotation\Title("开放平台应用列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询开放平台应用，支持按应用名称/app_key关键字与状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/openapi/app")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:"1", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:"10", desc:"每页数量")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"应用名称或app_key关键字")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态,0=禁用,1=启用")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"分页列表(list/total/page/limit)")]

    public function index(Request $request): Response
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = (int) $request->get('limit', 10);
        $limit = min(max($limit, 1), 100);

        $query = OpenApiApp::query();
        $keyword = trim((string) $request->get('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('app_name', 'like', "%{$keyword}%")
                    ->orWhere('app_key', 'like', "%{$keyword}%");
            });
        }
        $status = $request->get('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (OpenApiApp $app) => $this->encodeIds($app->toArray()))
            ->toArray();

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 开放平台应用详情
     */#[\erikwang2013\apidoc\annotation\Title("开放平台应用详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看应用详情(app_secret 不参与回显)")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"应用ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"应用详情(hashid)")]

    public function show(Request $request, string $id): Response
    {
        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        return $this->success($this->encodeIds($app->toArray()));
    }

    /**
     * 创建开放平台应用
     */#[\erikwang2013\apidoc\annotation\Title("创建开放平台应用")]
#[\erikwang2013\apidoc\annotation\Desc("创建应用并生成 app_key/app_secret，app_secret 仅此一次明文返回，请立即保存")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/openapi/app")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"app_name", type:"string", require:true, desc:"应用名称(≤100字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"scopes", type:"array", desc:"授权范围(以/开头的路径前缀数组,缺省=不限制)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"1", desc:"状态,0=禁用,1=启用")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"id(hashid)/app_key/app_secret(仅一次展示)")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'app_name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:200',
            'status' => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $scopes = $this->sanitizeScopes($request->input('scopes'));
        if ($scopes === false) {
            return $this->fail('scopes 须为以 / 开头的路径前缀数组', 422);
        }

        $secret = bin2hex(random_bytes(32));
        $app = new OpenApiApp();
        $app->id = $this->generateId();
        $app->app_name = trim((string) $request->input('app_name'));
        $app->app_key = 'ak_' . bin2hex(random_bytes(16));
        $app->app_secret = $secret; // Encryptable cast 自动加密入库
        $app->app_secret_hash = hash('sha256', $secret); // 完整性校验位
        $app->scopes = $scopes;
        $app->status = $request->input('status') === null ? 1 : (int) $request->input('status');
        $app->created_by = (int) ($request->adminId ?? 0);
        $app->save();

        return $this->success([
            'id' => $this->encodeId((int) $app->id),
            'app_key' => $app->app_key,
            'app_secret' => $secret, // 仅此一次展示，请妥善保存
        ], '创建成功，app_secret 仅展示一次，请立即保存');
    }

    /**
     * 更新开放平台应用
     */#[\erikwang2013\apidoc\annotation\Title("更新开放平台应用")]
#[\erikwang2013\apidoc\annotation\Desc("更新应用名称/授权范围/状态，不改动密钥")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"应用ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"app_name", type:"string", require:true, desc:"应用名称(≤100字符)")]
#[\erikwang2013\apidoc\annotation\Param(name:"scopes", type:"array", desc:"授权范围(以/开头的路径前缀数组)")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态,0=禁用,1=启用")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后应用详情(hashid)")]

    public function update(Request $request, string $id): Response
    {
        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        $validator = validator($request->all(), [
            'app_name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:200',
            'status' => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $scopes = $this->sanitizeScopes($request->input('scopes'));
        if ($scopes === false) {
            return $this->fail('scopes 须为以 / 开头的路径前缀数组', 422);
        }

        $app->app_name = trim((string) $request->input('app_name'));
        $app->scopes = $scopes;
        if ($request->input('status') !== null) {
            $app->status = (int) $request->input('status');
        }
        $app->save();

        return $this->success($this->encodeIds($app->toArray()), '更新成功');
    }

    /**
     * 删除开放平台应用
     */#[\erikwang2013\apidoc\annotation\Title("删除开放平台应用")]
#[\erikwang2013\apidoc\annotation\Desc("软删除应用，同时停用其名下所有 Webhook 订阅，需二次密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"应用ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"操作密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        WebhookSubscription::query()->where('app_id', $app->id)->update(['enabled' => 0]);
        $app->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 重置应用密钥
     */#[\erikwang2013\apidoc\annotation\Title("重置应用密钥")]
#[\erikwang2013\apidoc\annotation\Desc("原密钥即刻作废，新 app_secret 仅此一次明文返回，请立即保存，需二次密码确认")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"应用ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", require:true, desc:"操作密码(二次确认)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"id(hashid)/app_secret(仅一次展示)")]

    public function resetSecret(Request $request, string $id): Response
    {
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        $secret = bin2hex(random_bytes(32));
        $app->app_secret = $secret;
        $app->app_secret_hash = hash('sha256', $secret);
        $app->save();

        return $this->success([
            'id' => $this->encodeId((int) $app->id),
            'app_secret' => $secret,
        ], '密钥已重置，新密钥仅展示一次，请立即保存');
    }

    /**
     * 启用/禁用应用
     */#[\erikwang2013\apidoc\annotation\Title("启用/禁用应用")]
#[\erikwang2013\apidoc\annotation\Desc("切换应用启用状态")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("开放平台")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", require:true, desc:"应用ID(hashid)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"id(hashid)/status(切换后状态)")]

    public function toggleStatus(Request $request, string $id): Response
    {
        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        $app->status = (int) $app->status === 1 ? 0 : 1;
        $app->save();

        return $this->success(
            ['id' => $this->encodeId((int) $app->id), 'status' => $app->status],
            $app->status === 1 ? '已启用' : '已禁用'
        );
    }

    /**
     * 规范化 scopes：空数组/未提供 -> null（不限制）；否则校验每个元素均以 / 开头
     *
     * @return array|null|false null=不限制，array=合法范围，false=非法输入
     */
    private function sanitizeScopes($scopes)
    {
        if ($scopes === null || (is_array($scopes) && $scopes === [])) {
            return null;
        }
        if (!is_array($scopes)) {
            return false;
        }

        $result = [];
        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '' || !str_starts_with($scope, '/')) {
                return false;
            }
            $result[] = rtrim($scope, '/');
        }

        return array_values(array_unique($result));
    }
}
