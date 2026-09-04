<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

use app\common\SnowflakeService;
use app\model\NotificationChannelLog;

/**
 * 通知渠道发送服务（B4, P2-5）
 *
 * 边界：本服务只管外发渠道（sms/mail）并落 erp_notification_channel_log；
 * 站内通知（inapp）走既有 NotificationService(erp_notification)，勿混用。
 * 真实网关为后续适配器：换 ChannelService 的渠道→驱动 map 即可（F5 先例）。
 *
 * 幂等：同 (channel, to, content_hash) 在 DEDUP_SECONDS(300s) 成功窗口内
 * 命中即返回既有记录（dedup=true），不重复发送、不重复落日志。失败记录
 * 不进窗口，供 retryFailures 60 秒后重试。
 *
 * 消息文本（error）为稳定契约，测试断言以其为准，勿改写。
 */
class ChannelService
{
    /** 支持的外发渠道（inapp 属站内通知，不在此列） */
    public const CHANNELS = ['sms', 'mail'];

    /** status 词汇：0=发送中（异步队列预留，同步路径不写） 1=成功 2=失败 */
    public const STATUS_SENDING = 0;

    public const STATUS_SUCCESS = 1;

    public const STATUS_FAILURE = 2;

    /** 幂等成功窗口（秒） */
    public const DEDUP_SECONDS = 300;

    /** 失败重试冷却（秒）：status=2 且 sent_at 早于该门槛才可重试 */
    public const RETRY_AFTER_SECONDS = 60;

    /** 渠道 → 驱动（真实网关在此替换为对应驱动类） */
    private const DRIVER_MAP = [
        'sms' => MockChannelDriver::class,
        'mail' => MailMockChannelDriver::class,
    ];

    /**
     * 发送（同步）：校验 → 幂等判重 → 驱动发送 → 落日志。
     *
     * @return array{success: bool, log_id: int|null, message_id: string, dedup: bool, error: string}
     */
    public function send(string $channel, string $to, string $subject, string $content, int $operatorId = 0): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            return ['success' => false, 'log_id' => null, 'message_id' => '', 'dedup' => false, 'error' => '不支持的渠道'];
        }
        if ($to === '') {
            return ['success' => false, 'log_id' => null, 'message_id' => '', 'dedup' => false, 'error' => '接收方不能为空'];
        }
        if ($content === '') {
            return ['success' => false, 'log_id' => null, 'message_id' => '', 'dedup' => false, 'error' => '内容不能为空'];
        }

        $hash = hash('sha256', $content);
        $duplicate = $this->findDuplicate($channel, $to, $hash);
        if ($duplicate !== null) {
            return [
                'success' => true,
                'log_id' => (int) $duplicate->id,
                'message_id' => (string) $duplicate->message_id,
                'dedup' => true,
                'error' => '',
            ];
        }

        $driver = $this->driver($channel);
        if ($driver === null) {
            return ['success' => false, 'log_id' => null, 'message_id' => '', 'dedup' => false, 'error' => '不支持的渠道'];
        }
        $result = $driver->send($to, $subject, $content);

        $log = $this->writeLog($channel, $to, $subject, $content, $hash, $operatorId, $result);

        return [
            'success' => $result['success'],
            'log_id' => $log !== null ? (int) $log->id : null,
            'message_id' => (string) ($result['message_id'] ?? ''),
            'dedup' => false,
            'error' => (string) ($result['error'] ?? ''),
        ];
    }

    /**
     * 重试失败记录：status=2 且上次尝试早于 RETRY_AFTER_SECONDS 之前的
     * 记录（id 升序，最多 limit 条）。每次尝试都刷新 sent_at（防击穿）；
     * 成功转 1 并记 message_id，仍失败保持 2 并刷新 error。
     *
     * @return array{attempted: int, succeeded: int, failed: int, error: string}
     */
    public function retryFailures(?string $channel = null, int $limit = 50): array
    {
        if ($channel !== null && $channel !== '' && !in_array($channel, self::CHANNELS, true)) {
            return ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'error' => '不支持的渠道'];
        }

        $query = NotificationChannelLog::query()
            ->where('status', self::STATUS_FAILURE)
            ->where('sent_at', '<', date('Y-m-d H:i:s', time() - self::RETRY_AFTER_SECONDS));
        if ($channel !== null && $channel !== '') {
            $query->where('channel', $channel);
        }
        $logs = $query->orderBy('id')->limit(max(1, $limit))->get();

        $attempted = 0;
        $succeeded = 0;
        $failed = 0;
        foreach ($logs as $log) {
            $attempted++;
            $driver = $this->driver((string) $log->channel);
            $log->sent_at = date('Y-m-d H:i:s');
            if ($driver === null) {
                $log->status = self::STATUS_FAILURE;
                $log->error = '不支持的渠道';
                $failed++;
            } else {
                $result = $driver->send((string) $log->to, (string) $log->subject, (string) $log->content);
                if ($result['success']) {
                    $log->status = self::STATUS_SUCCESS;
                    $log->message_id = (string) ($result['message_id'] ?? '');
                    $log->error = '';
                    $succeeded++;
                } else {
                    $log->status = self::STATUS_FAILURE;
                    $log->message_id = '';
                    $log->error = (string) ($result['error'] ?? '');
                    $failed++;
                }
            }
            $log->save();
        }

        return ['attempted' => $attempted, 'succeeded' => $succeeded, 'failed' => $failed, 'error' => ''];
    }

    /**
     * 发送日志分页查询。filter 支持 channel（白名单内才过滤）、status、
     * to（模糊）。倒序分页。
     *
     * @return array{list: array, total: int}
     */
    public function sendLogs(array $filter = [], int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = min(100, max(1, $pageSize));

        $query = NotificationChannelLog::query();
        $channel = (string) ($filter['channel'] ?? '');
        if ($channel !== '' && in_array($channel, self::CHANNELS, true)) {
            $query->where('channel', $channel);
        }
        if (array_key_exists('status', $filter) && $filter['status'] !== '') {
            $query->where('status', (int) $filter['status']);
        }
        $to = (string) ($filter['to'] ?? '');
        if ($to !== '') {
            $query->where('to', 'like', '%' . $to . '%');
        }

        $total = (clone $query)->count();
        $list = $query->orderByDesc('id')->forPage($page, $pageSize)->get()
            ->map(fn (NotificationChannelLog $log): array => $this->row($log))
            ->all();

        return ['list' => $list, 'total' => $total];
    }

    /** 幂等判重：同 (channel,to,hash) 在 300s 成功窗口内最早返回 */
    private function findDuplicate(string $channel, string $to, string $hash): ?NotificationChannelLog
    {
        return NotificationChannelLog::query()
            ->where('channel', $channel)
            ->where('to', $to)
            ->where('content_hash', $hash)
            ->where('status', self::STATUS_SUCCESS)
            ->where('sent_at', '>=', date('Y-m-d H:i:s', time() - self::DEDUP_SECONDS))
            ->orderByDesc('id')
            ->first();
    }

    /** 渠道驱动解析（未知渠道返回 null，语义同「不支持的渠道」） */
    private function driver(string $channel): ?ChannelDriver
    {
        $class = self::DRIVER_MAP[$channel] ?? null;
        if ($class === null) {
            return null;
        }

        return new $class();
    }

    /** 落一条发送尝试日志（成败皆记），返回模型行 */
    private function writeLog(
        string $channel,
        string $to,
        string $subject,
        string $content,
        string $hash,
        int $operatorId,
        array $result
    ): ?NotificationChannelLog {
        $log = new NotificationChannelLog();
        $log->id = SnowflakeService::generate();
        $log->channel = $channel;
        $log->to = $to;
        $log->subject = $subject;
        $log->content = $content;
        $log->content_hash = $hash;
        $log->status = $result['success'] ? self::STATUS_SUCCESS : self::STATUS_FAILURE;
        $log->message_id = (string) ($result['message_id'] ?? '');
        $log->error = (string) ($result['error'] ?? '');
        $log->sent_at = date('Y-m-d H:i:s');
        $log->operator_id = $operatorId;
        if (!$log->save()) {
            return null;
        }

        return $log;
    }

    /** 日志行输出（列表用） */
    private function row(NotificationChannelLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'channel' => (string) $log->channel,
            'to' => (string) $log->to,
            'subject' => (string) $log->subject,
            'content' => (string) $log->content,
            'status' => (int) $log->status,
            'message_id' => (string) $log->message_id,
            'error' => (string) $log->error,
            'sent_at' => (string) $log->sent_at,
            'operator_id' => (int) $log->operator_id,
            'created_at' => (string) $log->created_at,
            'updated_at' => (string) $log->updated_at,
        ];
    }
}
