<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

use app\common\SnowflakeService;
use app\model\Notification;
use app\model\NotificationTemplate;

class NotificationService
{
    /**
     * 发送通知
     */
    public static function send(int $userId, string $title, string $content, string $type = 'system', string $sourceType = '', int $sourceId = 0): void
    {
        $notification = new Notification();
        $notification->id = SnowflakeService::generate();
        $notification->user_id = $userId;
        $notification->title = $title;
        $notification->content = $content;
        $notification->type = $type;
        $notification->source_type = $sourceType;
        $notification->source_id = $sourceId;
        $notification->is_read = 0;
        $notification->save();
    }

    /**
     * 按模板发送通知
     *
     * @param array<string, string> $vars 模板变量
     */
    public static function sendByTemplate(int $userId, string $templateCode, array $vars = []): void
    {
        $template = NotificationTemplate::where('code', $templateCode)->where('enabled', 1)->first();
        if (!$template) {
            return;
        }

        $title = $template->title_tpl;
        $content = $template->content_tpl ?? '';

        foreach ($vars as $key => $value) {
            $title = str_replace('{' . $key . '}', (string) $value, $title);
            $content = str_replace('{' . $key . '}', (string) $value, $content);
        }

        self::send($userId, $title, $content, 'system');
    }

    /**
     * 标记单条通知已读
     */
    public static function markRead(int $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 标记全部已读
     */
    public static function markAllRead(int $userId): void
    {
        Notification::where('user_id', $userId)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 未读数量
     */
    public static function unreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->where('is_read', 0)->count();
    }
}
