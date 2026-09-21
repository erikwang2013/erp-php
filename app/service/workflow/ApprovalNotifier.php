<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\workflow;

use app\model\ApprovalNode;
use app\service\notification\NotificationService;
use app\service\notification\WebhookService;
use support\Log;

/**
 * 审批旁路副作用：站内通知 + Webhook 事件
 *
 * 三个方法都是「失败不影响主流程」语义：内部 try/catch + Log::error，调用方不必再包一层。
 * 文案由调用方 trans() 后传入（通知是落库内容，按触发者语言渲染）。
 *
 * Webhook 走队列异步：本请求只出一条 LPUSH，真实投递与到期失败补偿都在消费端
 * （app/queue/redis/WebhookTask）；事件名目录见 WebhookService 类注释。
 */
class ApprovalNotifier
{
    /**
     * 节点审批人 ID —— 仅 approver_type=1「指定人」可解析；
     * 2角色/3部门负责人/4直属上级 无解析实现，返回 0 由调用方跳过（与 myApprovals 的取人口径一致）
     */
    public static function nodeApproverId(?ApprovalNode $node): int
    {
        if ($node === null || (int) ($node->approver_type ?? 0) !== 1) {
            return 0;
        }

        return (int) ($node->approver_id ?? 0);
    }

    /**
     * 站内通知：收件人非法或等于 $excludeUserId 时跳过
     */
    public static function notify(int $userId, int $excludeUserId, string $title, string $content, int $instanceId): void
    {
        if ($userId <= 0 || $userId === $excludeUserId) {
            return;
        }

        try {
            NotificationService::send($userId, $title, $content, 'approval', 'approval_instance', $instanceId);
        } catch (\Throwable $e) {
            Log::error('[Approval] 通知发送失败 instance=' . $instanceId . ': ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }
    }

    /**
     * Webhook 事件入队（异步）
     */
    public static function webhook(string $event, array $payload): void
    {
        try {
            (new WebhookService())->dispatchAsync($event, $payload);
        } catch (\Throwable $e) {
            Log::error('[Approval] Webhook 入队失败 event=' . $event . ': ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }
    }
}
