<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use app\common\SnowflakeService;
use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class OperationLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $method = $request->method();

        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $handler($request);
        }

        $response = $handler($request);

        try {
            // 递归脱敏凭据与个人敏感信息
            $input = self::redactPayload($request->all());

            $log = new \app\model\OperationLog();
            $log->id = SnowflakeService::generate();
            $log->user_id = $request->adminId ?? 0;
            $log->action = $method;
            $log->method = $method;
            $log->path = $request->path();
            $log->ip = SecurityFilter::clientIp($request);
            $log->source = $this->detectSource($request);
            $log->input = json_encode($input, JSON_UNESCAPED_UNICODE);
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        } catch (\Throwable $e) {
            // 操作日志落库失败不应影响业务请求（fail-open 保留），
            // 但审计日志缺失必须显式告警，否则审计链路静默失效无从发现
            Log::warning('操作日志落库失败（审计记录缺失）: ' . $e->getMessage() . ' | Path: ' . $request->path() . ' | TraceId: ' . trace_id());
        }

        return $response;
    }

    /**
     * 递归脱敏凭据与个人敏感信息，防止明文落入 operation_log.input。
     *
     * 键名按「小写 + 去下划线」归一，兼容 snake_case 与 camelCase（phone / Phone / contactPhone）。
     * 公开静态方法便于单测直接夹逼。
     */
    public static function redactPayload(array $data): array
    {
        static $keys = null;
        if ($keys === null) {
            $keys = array_flip(array_map(
                static fn (string $key): string => str_replace('_', '', $key),
                [
                    // 凭据
                    'password', 'password_confirmation', 'old_password', 'new_password', 'new_password_confirmation',
                    'token', 'access_token', 'refresh_token', 'api_token', 'secret', 'app_secret', 'client_secret',
                    // 个人/财务敏感信息
                    'phone', 'mobile', 'email', 'contact_email', 'id_card', 'bank_account', 'account_number',
                    'tax_number', 'emergency_phone', 'contact_phone', 'address', 'billing_address', 'shipping_address',
                    'address_line1', 'address_line2', 'contact_name', 'contact_person', 'phone_number',
                    'emergency_contact', 'birthday',
                ]
            ));
        }
        foreach ($data as $key => $value) {
            $normalized = strtolower(str_replace('_', '', (string) $key));
            if (isset($keys[$normalized])) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::redactPayload($value);
            }
        }

        return $data;
    }

    /**
     * 从请求头检测客户端来源端
     */
    private function detectSource(Request $request): string
    {
        // 优先使用原生客户端显式声明的平台头
        $platform = $request->header('X-Client-Platform', '');
        if ($platform && in_array(strtolower($platform), [
            'ipados', 'macos', 'windows', 'linux', 'ios', 'android', 'harmonyos', 'web',
        ], true)) {
            return strtolower($platform);
        }

        // 通过 User-Agent 推断
        $ua = $request->header('User-Agent', '');

        if (stripos($ua, 'HarmonyOS') !== false || stripos($ua, 'OpenHarmony') !== false) {
            return 'harmonyos';
        }
        if (stripos($ua, 'iPad') !== false) {
            return 'ipados';
        }
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iOS') !== false || stripos($ua, 'CFNetwork') !== false) {
            return 'ios';
        }
        if (stripos($ua, 'Android') !== false) {
            return 'android';
        }
        if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            return 'macos';
        }
        if (stripos($ua, 'Windows') !== false) {
            return 'windows';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'linux';
        }

        return 'web';
    }
}
