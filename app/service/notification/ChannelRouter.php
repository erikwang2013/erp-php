<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\notification;

use support\Log;

class ChannelRouter
{
    /**
     * Send notification through configured channels.
     * Channels are attempted in priority order: in-app → email → wecom → dingtalk.
     * Returns array of [channel => success] results.
     */
    public function send(int $userId, string $title, string $content, array $channels = ['in_app']): array
    {
        $results = [];
        foreach ($channels as $channel) {
            $results[$channel] = match ($channel) {
                'in_app' => $this->sendInApp($userId, $title, $content),
                default => false, // email/wecom/dingtalk: stub for future implementation
            };
        }

        return $results;
    }

    private function sendInApp(int $userId, string $title, string $content): bool
    {
        try {
            $notification = new \app\model\Notification();
            $notification->id = \app\common\SnowflakeService::generate();
            $notification->user_id = $userId;
            $notification->title = $title;
            $notification->content = $content;
            $notification->is_read = 0;
            $notification->save();

            return true;
        } catch (\Throwable $e) {
            // 站内信落库失败：向调用方返回 false（fail-closed 信号），并记录根因
            Log::error('站内信发送失败（落库异常）: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return false;
        }
    }
}
