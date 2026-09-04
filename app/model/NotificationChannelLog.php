<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 通知渠道发送日志（B4, P2-5）——外发渠道（sms/mail）每笔尝试一条
 *
 * 幂等键 content_hash（sha256(content)）：同 (channel, to, content_hash)
 * 在 5 分钟成功窗口内去重（ChannelService::send 返回既有记录不重发）。
 * status: 0=发送中（保留给异步队列，同步路径不写） 1=成功 2=失败；
 * sent_at = 每次发送(尝试)时间，重试队列（status=2 且 >60 秒）以其为门。
 * 站内通知（inapp）走 erp_notification，不入本表。
 *
 * @property int $id
 * @property string $channel
 * @property string $to
 * @property string $subject
 * @property string $content
 * @property string $content_hash
 * @property int $status
 * @property string $message_id
 * @property string $error
 * @property string $sent_at
 * @property int $operator_id
 * @property string $created_at
 * @property string $updated_at
 */
class NotificationChannelLog extends Model
{
    protected $table = 'erp_notification_channel_log';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
