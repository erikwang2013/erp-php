<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\OpenApiApp;
use app\model\WebhookDeliveryLog;
use app\model\WebhookSubscription;
use app\service\notification\WebhookService;
use support\Request;
use support\Response;

/**
 * P0 Webhook 订阅管理（归属开放平台应用，事件投递见 WebhookService）
 *
 * secret 加密入库，仅在创建时明文展示一次（不提供回显，丢失可重置）；
 * 事件名支持 "*" 通配订阅全部事件。
 */
class WebhookController extends BaseController
{
    /**
     * 订阅列表（可按 app_id 过滤）
     */
    public function index(Request $request): Response
    {
        $page = max((int) $request->get('page', 1), 1);
        $limit = (int) $request->get('limit', 10);
        $limit = min(max($limit, 1), 100);

        $query = WebhookSubscription::query();
        $appId = $request->get('app_id');
        if ($appId !== null && $appId !== '') {
            $decoded = $this->decodeIdSafe((string) $appId);
            if ($decoded === null) {
                return $this->fail('app_id 无效', 422);
            }
            $query->where('app_id', $decoded);
        }

        $total = $query->count();
        $list = $query->with('app')->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function (WebhookSubscription $sub) {
                $row = $sub->toArray();
                $row['app_name'] = $sub->app ? $sub->app->app_name : '';
                unset($row['app']);

                return $this->encodeIds($row, ['id', 'app_id']);
            })
            ->toArray();

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 订阅详情
     */
    public function show(Request $request, string $id): Response
    {
        $sub = WebhookSubscription::find($this->decodeId($id));
        if (!$sub) {
            return $this->fail('订阅不存在', 404);
        }

        return $this->success($this->encodeIds($sub->toArray()));
    }

    /**
     * 新建订阅（secret 明文仅此一次返回）
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'app_id' => 'required|string',
            'event' => 'required|array',
            'event.*' => 'string|max:100',
            'target_url' => 'required|string|max:500',
            'secret' => 'nullable|string|min:16|max:200',
            'enabled' => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $app = OpenApiApp::find($this->decodeIdSafe((string) $request->input('app_id')) ?? 0);
        if (!$app) {
            return $this->fail('应用不存在', 404);
        }

        $events = $this->sanitizeEvents($request->input('event'));
        if ($events === false) {
            return $this->fail('订阅事件为空，或含非法事件名（合法字符: 字母数字._-，或 "*"）', 422);
        }
        $targetUrl = trim((string) $request->input('target_url'));
        if (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://')) {
            return $this->fail('target_url 必须以 http:// 或 https:// 开头', 422);
        }

        $secret = (string) $request->input('secret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(16)); // 未提供则自动生成 32 位十六进制密钥
        }

        $sub = new WebhookSubscription();
        $sub->id = $this->generateId();
        $sub->app_id = (int) $app->id;
        $sub->event = $events;
        $sub->target_url = $targetUrl;
        $sub->secret = $secret; // Encryptable cast 自动加密入库
        $sub->enabled = $request->input('enabled') === null ? 1 : (int) $request->input('enabled');
        $sub->created_by = (int) ($request->adminId ?? 0);
        $sub->save();

        return $this->success([
            'id' => $this->encodeId((int) $sub->id),
            'app_id' => $this->encodeId((int) $sub->app_id),
            'secret' => $secret, // 仅此一次展示
        ], '创建成功，secret 仅展示一次，请转发给接收方用于验签');
    }

    /**
     * 更新订阅（enabled/target_url/event 可改；secret 仅在本次传入时重置）
     */
    public function update(Request $request, string $id): Response
    {
        $sub = WebhookSubscription::find($this->decodeId($id));
        if (!$sub) {
            return $this->fail('订阅不存在', 404);
        }

        $validator = validator($request->all(), [
            'event' => 'nullable|array',
            'event.*' => 'string|max:100',
            'target_url' => 'nullable|string|max:500',
            'secret' => 'nullable|string|min:16|max:200',
            'enabled' => 'nullable|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if ($request->input('event') !== null) {
            $events = $this->sanitizeEvents($request->input('event'));
            if ($events === false) {
                return $this->fail('订阅事件为空，或含非法事件名（合法字符: 字母数字._-，或 "*"）', 422);
            }
            $sub->event = $events;
        }
        if ($request->input('target_url') !== null) {
            $targetUrl = trim((string) $request->input('target_url'));
            if (!str_starts_with($targetUrl, 'http://') && !str_starts_with($targetUrl, 'https://')) {
                return $this->fail('target_url 必须以 http:// 或 https:// 开头', 422);
            }
            $sub->target_url = $targetUrl;
        }
        $secret = (string) $request->input('secret', '');
        if ($secret !== '') {
            $sub->secret = $secret; // 传入即视为重置密钥
        }
        if ($request->input('enabled') !== null) {
            $sub->enabled = (int) $request->input('enabled');
        }
        $sub->save();

        return $this->success($this->encodeIds($sub->toArray()), '更新成功');
    }

    /**
     * 删除订阅（硬删除，并级联清理其投递日志）
     */
    public function destroy(Request $request, string $id): Response
    {
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $sub = WebhookSubscription::find($this->decodeId($id));
        if (!$sub) {
            return $this->fail('订阅不存在', 404);
        }

        WebhookDeliveryLog::query()->where('subscription_id', $sub->id)->delete();
        $sub->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 发送测试事件（走真实投递链路并落库，便于管理端验证回调可达性与验签）
     */
    public function test(Request $request, string $id): Response
    {
        $sub = WebhookSubscription::find($this->decodeId($id));
        if (!$sub) {
            return $this->fail('订阅不存在', 404);
        }

        $result = (new WebhookService())->testDeliver($sub);

        return $this->success($result, $result['status'] === 'success' ? '测试事件投递成功' : '测试事件投递失败（详情见日志）');
    }

    /**
     * 投递日志（按订阅分页，最新在前）
     */
    public function logs(Request $request, string $id): Response
    {
        $subId = $this->decodeIdSafe($id);
        if ($subId === null) {
            return $this->fail('订阅不存在', 404);
        }

        $page = max((int) $request->get('page', 1), 1);
        $limit = (int) $request->get('limit', 10);
        $limit = min(max($limit, 1), 100);

        $query = WebhookDeliveryLog::query()->where('subscription_id', $subId);
        $total = $query->count();
        $list = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn (WebhookDeliveryLog $log) => $this->encodeIds($log->toArray()))
            ->toArray();

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 规范化订阅事件数组
     *
     * @return array|false array=合法事件，false=非法输入
     */
    private function sanitizeEvents($events)
    {
        if (!is_array($events) || $events === []) {
            return false;
        }

        $result = [];
        foreach ($events as $event) {
            $event = trim((string) $event);
            if ($event === '' || ($event !== '*' && !preg_match('/^[a-zA-Z0-9_.\-]+$/', $event))) {
                return false;
            }
            $result[] = $event;
        }

        return array_values(array_unique($result));
    }
}
