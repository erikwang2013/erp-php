<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use InvalidArgumentException;
use support\Container;

/**
 * Hashids 编解码服务
 * 用于 API 层 ID 加解密，对外暴露 hash 字符串，隐藏真实数据库 BIGINT ID
 */
class HashidsService
{
    public static function encode(int $id): string
    {
        return Container::get('hashids')->encode($id);
    }

    public static function decode(string $hashid): int
    {
        $ids = Container::get('hashids')->decode($hashid);
        if (empty($ids)) {
            throw new InvalidArgumentException('无效的加密ID');
        }

        return (int) $ids[0];
    }

    /**
     * 批量编码数组中的 ID 字段（默认递归到嵌套数组/明细行）
     *
     * $fields 为空：自动识别键名——id 本身或以 _id 结尾（任意层级）都编码，
     *   避免外键裸雪花 ID 外泄（含 items[] 等嵌套明细）。
     * $fields 非空：显式覆盖自动识别，只编码列出的键名（仍在各层级生效），
     *   兼容旧调用里"只编码这几个字段"的收窄语义（如 ['workstation_id'] 不含 id）。
     *
     * 防二次编码：只编码裸 ID——int，或纯数字串（雪花/自增 ID 的十进制文本）。
     * 已编码的 hashid 是字符串：含字母的直接原样保留；少数小 ID 编码结果恰好全为数字
     * （实测 encode(35)='38'），用 encode(decode(v)) === v 判定其确为规范 hashid 后跳过。
     * 0 一律不编码（哨兵值，见 isRawId）。
     */
    public static function encodeIds(array $data, array $fields = []): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::encodeIds($value, $fields);
                continue;
            }
            if (!self::isIdField($key, $fields) || !self::isRawId($value)) {
                continue;
            }
            $data[$key] = self::encode((int) $value);
        }

        return $data;
    }

    /** 键名是否为 ID 字段：$fields 为空按命名识别，否则只认显式名单 */
    private static function isIdField(int|string $key, array $fields): bool
    {
        if (!is_string($key)) {
            return false;
        }
        if ($fields !== []) {
            return in_array($key, $fields, true);
        }

        return $key === 'id' || str_ends_with($key, '_id');
    }

    /**
     * 值是否为未编码的裸 ID：int，或纯数字串且不是规范 hashid
     *
     * 0 不编码：它是哨兵值（install.sql 里 *_id NOT NULL DEFAULT 0 = 未指定/顶级），
     * 编码只会把 falsy 变成真值串，破坏调用方的 `if ($x_id)` 判定（如前端单选树
     * `cur ? [String(cur)] : []` 会误预选）。真实行 ID 恒 >= 1。
     */
    private static function isRawId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value !== 0;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            return false; // 含字母：已编码的 hashid 或非 ID 串（如 UUID / open_id）
        }

        return (int) $value !== 0 && !self::isEncodedId($value);
    }

    /** 规范化判定：纯数字串能原样 decode→encode 回来即已是 hashid（encode(35)='38'） */
    private static function isEncodedId(string $value): bool
    {
        try {
            return self::encode(self::decode($value)) === $value;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }
}
