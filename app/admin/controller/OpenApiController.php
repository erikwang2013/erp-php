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
     * 应用列表
     */
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
     * 应用详情
     */
    public function show(Request $request, string $id): Response
    {
        $app = OpenApiApp::find($this->decodeId($id));
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        return $this->success($this->encodeIds($app->toArray()));
    }

    /**
     * 创建应用（app_secret 仅此一次明文返回）
     */
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
     * 更新应用（名称 / 授权范围 / 状态；不改动密钥）
     */
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
     * 删除应用（软删除；同时停用其名下所有 Webhook 订阅）
     */
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
     * 重置密钥（原密钥即刻作废，新密钥仅此一次返回）
     */
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
     * 启用 / 禁用应用
     */
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
