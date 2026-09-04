<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\notification;

/**
 * 邮件渠道 Mock 驱动（B4, P2-5），与短信 Mock 同构（确定性规则）：
 *   1. 接收方不含 '@' → 失败，error=「接收方邮箱非法」；
 *   2. 内容超 500 字符（mb_strlen）→ 失败，error=「内容超长」；
 *   3. 其余成功：message_id = 'MOCK' + date('YmdHis') + 4 位同秒序号
 *      （格式同 MockChannelDriver，见其类头；幂等同样由服务层负责）。
 */
class MailMockChannelDriver implements ChannelDriver
{
    /** 同秒序号计数（message_id 尾缀） */
    private static array $seq = [];

    public function name(): string
    {
        return 'mail';
    }

    public function send(string $to, string $subject, string $content): array
    {
        if (!str_contains($to, '@')) {
            return ['success' => false, 'error' => '接收方邮箱非法'];
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
