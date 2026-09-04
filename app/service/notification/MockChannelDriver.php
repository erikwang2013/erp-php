<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

/**
 * 短信渠道 Mock 驱动（B4, P2-5）
 *
 * 确定性规则（无真实网关时的可测行为，消息为稳定契约）：
 *   1. 接收方以 '9' 开头 → 失败，error=「接收方号码非法」；
 *   2. 内容超 500 字符（mb_strlen）→ 失败，error=「内容超长」；
 *   3. 其余成功：message_id = 'MOCK' + date('YmdHis') + 4 位同秒序号
 *      （如 MOCK20260905103000_0001，同秒内序号递增保证不重）。
 *
 * 注意：60 秒内重复 (to, content) 的幂等不在驱动内做——由 ChannelService
 * 落日志前按 (channel, to, content_hash) 5 分钟成功窗口去重（见该服务
 * 类头），驱动保持无状态纯函数式。
 */
class MockChannelDriver implements ChannelDriver
{
    /** 同秒序号计数（message_id 尾缀） */
    private static array $seq = [];

    public function name(): string
    {
        return 'sms';
    }

    public function send(string $to, string $subject, string $content): array
    {
        if (str_starts_with($to, '9')) {
            return ['success' => false, 'error' => '接收方号码非法'];
        }
        if (mb_strlen($content) > 500) {
            return ['success' => false, 'error' => '内容超长'];
        }

        return ['success' => true, 'message_id' => $this->messageId()];
    }

    /** 同秒序号自增（跨秒复位），message_id 形如 MOCK20260905103000_0001 */
    private function messageId(): string
    {
        $second = date('YmdHis');
        self::$seq[$second] = (self::$seq[$second] ?? 0) + 1;

        return 'MOCK' . $second . '_' . str_pad((string) self::$seq[$second], 4, '0', STR_PAD_LEFT);
    }
}
