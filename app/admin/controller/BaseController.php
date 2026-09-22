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
use support\Log;
use support\Model;
use support\Request;
use support\Response;

/**
 * 管理端基础控制器
 * 提供统一响应格式、ID编解码、snowflake ID 生成
 */
#[\erikwang2013\apidoc\annotation\Group('系统管理')]
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
     * 分页成功响应（规范形状：list/total/page/limit）
     */
    protected function successPage($list, int $total, int $page, int $limit): Response
    {
        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
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
     * 双模解码：hashid 串解码；原生数字（int/数字串）直用；其余（含数组等非标量）返回 null
     * （调用方 422 拒绝，避免 (int)'abc'=0 静默写入无 FK 约束的关联列产生孤儿行）
     *
     * hashid 只在「解回来再编一次与原文逐字相同」时才采信：hashids 会把某些纯数字串
     * （实测 '410000000000000402'）当成合法密文解出 PHP_INT_MAX，只认解码不认往返就会把
     * 数字 ID 静默写成 9223372036854775807 这种垃圾外键（关联列无 FK 约束时无人拦）。
     * 真实 hashid 的往返恒等（encode(decode(x)) === x），故往返校验不会误拒。
     */
    protected function decodeFlexibleId(mixed $raw): ?int
    {
        if (!is_scalar($raw)) {
            return null;
        }
        $raw = (string) $raw;
        $decoded = $this->decodeIdSafe($raw);
        if ($decoded !== null && $decoded > 0 && HashidsService::encode($decoded) === $raw) {
            return $decoded;
        }

        return is_numeric($raw) ? (int) $raw : null;
    }

    /**
     * ID 数组归一为原始 ID 数组；含无效项返回 null（调用方 422 拒绝）。
     * 判定顺序与 decodeFlexibleId 一致（hashid 优先、数字兜底）：
     * 传输层契约是 hashid 字符串数组（前端均按 string 集合下发），而 hashid 字母表含 0-9，
     * 纯数字 hashid 真实存在（id=9 → '69'），is_numeric 先行会把它误读成 id=69 授错权限。
     * 关联表无 FK 约束，放行垃圾值只会静默写入孤儿行 —— 故拒绝而非退化。
     */
    protected function normalizeIdArray(mixed $ids): ?array
    {
        $normalized = [];
        foreach ((array) $ids as $v) {
            // 只收 int/string 两种合法形态：PHP 里 (int)[] === 1，数组元素会凭空变成 id=1
            if (!is_string($v) && !is_int($v)) {
                return null;
            }
            $decoded = $this->decodeFlexibleId((string) $v);
            if ($decoded === null) {
                return null;
            }
            $normalized[] = $decoded;
        }

        return $normalized;
    }

    /**
     * 明细行批量双模解码：把 items[].<字段> 里的 hashid（前端 source 下拉下发）转回原始 ID。
     * 任一行任一字段非法（数组 / 垃圾串）返回 null，由调用方 422 拒绝 ——
     * 直灌 BIGINT 列在 MySQL 严格模式报 1366（→500），作为 WHERE 条件则按数字比较恒不命中
     * （UPDATE 影响 0 行且不报错，静默丢数据）。
     */
    protected function decodeItemIds(array $items, array $fields): ?array
    {
        foreach ($items as $i => $row) {
            if (!is_array($row)) {
                return null;
            }
            foreach ($fields as $field) {
                if (!array_key_exists($field, $row) || $row[$field] === '' || $row[$field] === null) {
                    continue;
                }
                $id = $this->decodeFlexibleId($row[$field]);
                if ($id === null) {
                    return null;
                }
                $items[$i][$field] = $id;
            }
        }

        return $items;
    }

    /**
     * 模型的外键列名（$fillable 里除主键外全部 *_id），供 decodeIdFields 批量解码。
     * 从模型声明推导而不是各控制器手写名单：新增外键列时不会漏解（漏解即 1366 → 500）。
     *
     * @return array<int, string>
     */
    protected function foreignKeyFields(Model $model): array
    {
        return array_values(array_filter($model->getFillable(), fn ($f) => str_ends_with((string) $f, '_id')));
    }

    /**
     * 主表外键批量双模解码：客户端外键是列表 encodeIds 下发的 hashid 串，fill 会把它
     * 直填 BIGINT 列（MySQL 严格模式报 1366 → 500）——「编辑即 500」的根因。
     *
     * 只返回请求中**带值**的字段：缺失或空串（前端下拉留空的语义）由调用方决定，
     * store 补 0 = 未关联（列 NOT NULL DEFAULT 0）、update 不补 = 不改动（局部更新
     * 不得把既有外键清零）；垃圾串落 0，口径同 purchase/OrderController 的可选外键。
     *
     * @param array<int, string> $fields
     * @return array<string, int>
     */
    protected function decodeIdFields(Request $request, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $raw = $request->input($field, null);
            if ($raw === null || $raw === '') {
                continue;
            }
            $out[$field] = $this->decodeFlexibleId($raw) ?? 0;
        }

        return $out;
    }

    /**
     * 批量编码数组中的 ID 字段（默认递归：任意层级的 id / *_id，见 HashidsService::encodeIds）
     */
    protected function encodeIds(array $data, array $idFields = []): array
    {
        return HashidsService::encodeIds($data, $idFields);
    }

    /**
     * 分页参数归一：page ≥ 1，1 ≤ limit ≤ maxLimit（缺省 500）。
     * 负 page/limit 会被编译成 `limit -5` 落 MySQL 语法错误（→500）；超大 limit 是一次
     * 无上限拉取（分页参数是外部输入，属信任边界）。默认 15 与前端列表页一致；
     * 上限取 500 而不是更小，是因为前端下拉数据源普遍请求 `?limit=500`
     * （Flutter 各页下拉、Angular/React 的 source 拉取），卡到 100 会把下拉静默截断。
     *
     * @return array{0:int,1:int} [page, limit]
     */
    protected function pageParams(Request $request, int $defaultLimit = 15, int $maxLimit = 500): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = (int) $request->input('limit', $defaultLimit);

        return [$page, min(max($limit, 1), $maxLimit)];
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

     * @param int $adminId 当前登录用户 ID
     * @param string $password 用户输入的密码
     * @return string|null 错误消息，null 表示验证通过
     */
    protected function confirmPassword(int $adminId, string $password, Request $request): ?string
    {
        if (empty($password)) {
            return $this->trans('Password confirmation required for sensitive operations');
        }

        $admin = AdminUser::find($adminId);
        if (!$admin || !password_verify($password, $admin->password)) {
            return $this->trans('Password verification failed');
        }

        return null; // 验证通过
    }

    /**
     * 安全填充模型 — 仅允许 $fillable 字段，防止 mass assignment
     */
    protected function fillModelFromRequest(Model $model, Request $request): void
    {
        $model->fill($request->only($model->getFillable()));
    }

    /**
     * 服务端故障的统一出口：文案与 ApiHandler 的未捕获 500 逐字一致，只带 TraceId。
     *
     * 局部 catch 若把 $e->getMessage() 用在 500 分支，会把表名、SQL 片段、服务端绝对路径
     * 一并回给客户端（实测库存分配 500 曾回显 InventoryService.php 的全路径）—— 框架层
     * ApiHandler 早已收口，这里是同一条底线在 catch 分支上的补齐。细节仍由 logError 进日志。
     */
    protected function failServer(): Response
    {
        return $this->fail('服务器内部错误，请稍后重试（TraceId: ' . trace_id() . '）', 500);
    }

    /**
     * 记录异常日志（含 TraceId），供各控制器 catch 分支统一调用。

     * fail-open 审计要求：任何被捕获吞掉的异常都必须留下可观测日志，
     * 避免"静默失败"导致问题无法排查。
     */
    protected function logError(string $action, \Throwable $e): void
    {
        Log::error(
            '[' . static::class . '] ' . $action . ' 失败: '
            . $e->getMessage() . ' | TraceId: ' . trace_id()
        );
    }
}
