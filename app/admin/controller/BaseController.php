<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\common\HashidsService;
use app\common\I18n;
use app\common\SnowflakeService;
use app\model\AdminUser;
use support\Request;
use support\Response;

/**
 * 管理端基础控制器
 * 提供统一响应格式、ID编解码、snowflake ID 生成
 */
class BaseController
{
    /**
     * 成功响应
     */
    protected function success($data = [], string $message = 'success', int $code = 0): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * 失败响应
     */
    protected function fail(string $message = 'fail', int $code = 500, $data = []): Response
    {
        return json(['code' => $code, 'message' => $message, 'data' => $data]);
    }

    /**
     * 将模型 ID 编码为 hashid 字符串
     */
    protected function encodeId(int $id): string
    {
        return HashidsService::encode($id);
    }

    /**
     * 将 hashid 字符串解码为原始 ID
     */
    protected function decodeId(string $hashid): int
    {
        return HashidsService::decode($hashid);
    }

    /**
     * 安全解码 hashid，无效时返回 null 而不抛异常
     */
    protected function decodeIdSafe(string $hashid): ?int
    {
        try {
            return $this->decodeId($hashid);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * 批量编码数组中的 ID 字段
     */
    protected function encodeIds(array $data, array $idFields = ['id']): array
    {
        return HashidsService::encodeIds($data, $idFields);
    }

    /**
     * 生成新的 snowflake ID
     */
    protected function generateId(): int
    {
        return SnowflakeService::generate();
    }

    /**
     * 翻译消息，自动根据当前语言环境返回对应语言的文本
     */
    protected function trans(string $key, array $replace = []): string
    {
        return I18n::trans($key, $replace);
    }

    /**
     * 二次确认 — 验证当前登录用户密码
     * 敏感操作（删除、导出等）调用此方法确认身份
     *
     * @param int $adminId 当前登录用户 ID
     * @param string $password 用户输入的密码
     * @return string|null 错误消息，null 表示验证通过
     */
    protected function confirmPassword(int $adminId, string $password, Request $request): ?string
    {
        if (empty($password)) {
            return $this->trans('password_required');
        }

        $admin = AdminUser::find($adminId);
        if (!$admin || !password_verify($password, $admin->password)) {
            return $this->trans('password_error');
        }

        return null; // 验证通过
    }
}
