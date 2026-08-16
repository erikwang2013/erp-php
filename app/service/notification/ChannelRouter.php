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
                'email' => $this->sendEmail($userId, $title, $content),
                // wecom/dingtalk: 预留适配点，待企业微信/钉钉应用接入后在此注册对应驱动
                default => false,
            };
        }

        return $results;
    }

    /**
     * 邮件渠道：当前以文件日志驱动落地（无 SMTP 依赖，邮件写入 runtime/mail/outbox.log）。
     * 接入真实 SMTP 时替换此方法内部实现即可，签名保持不变。
     */
    private function sendEmail(int $userId, string $title, string $content): bool
    {
        try {
            $dir = runtime_path() . '/mail';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException('无法创建邮件目录: ' . $dir);
            }

            $line = json_encode([
                'time' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'title' => $title,
                'content' => $content,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($dir . '/outbox.log', $line, FILE_APPEND);

            return true;
        } catch (\Throwable $e) {
            // 邮件写文件失败：向调用方返回 false（fail-closed 信号），并记录根因
            Log::error('邮件发送失败（写文件异常）: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return false;
        }
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
