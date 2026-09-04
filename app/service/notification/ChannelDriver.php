<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

/**
 * 通知渠道驱动接口（B4, P2-5）
 *
 * 每个渠道一个驱动：sms → MockChannelDriver、mail → MailMockChannelDriver。
 * 真实 SMS/邮件网关为后续适配器（同 F5 EInvoiceAdapter 抽象先例）：在
 * ChannelService 的渠道 map 处替换驱动即可，驱动返回值契约与发送日志
 * 结构不变。send() 只做渠道语义校验与投递，幂等与日志由 ChannelService
 * 负责，驱动不得自己建日志。
 */
interface ChannelDriver
{
    /**
     * 渠道名（与 erp_notification_channel_log.channel 一致：sms/mail）。
     */
    public function name(): string;

    /**
     * 发送。返回约定：
     *   success=true  → message_id 必填；
     *   success=false → error 必填（中文消息，稳定契约），message_id 缺省。
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function send(string $to, string $subject, string $content): array;
}
