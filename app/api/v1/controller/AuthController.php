<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("认证")
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;

use app\common\SnowflakeService;
use app\model\AdminUser;
use Erikwang2013\Jwt\JWT;
use support\Container;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;

class AuthController
{
    private static function getJWT(): JWT
    {
        return jwt_instance();
    }

    /**
     * 用户登录
     * @Apidoc\Title("用户登录")
     * @Apidoc\Desc("用户名密码登录，需先通过点击验证码；连续失败 5 次账号锁定 15 分钟")
     * @Apidoc\Url("/api/v1/auth/login")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="username", type="string", require=true, desc="用户名(3-50字符)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码(6-32字符)")
     * @Apidoc\Param(name="captcha_key", type="string", require=true, desc="验证码标识(来自生成接口)")
     * @Apidoc\Param(name="clicks", type="array", require=true, desc="点击坐标数组[{x,y}],至少2个")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("access_token", type="string", desc="访问令牌"),
     *     @Apidoc\Returned("refresh_token", type="string", desc="刷新令牌"),
     *     @Apidoc\Returned("expires_in", type="int", desc="访问令牌有效期(秒)"),
     *     @Apidoc\Returned("user", type="object", desc="用户信息", children={
     *         @Apidoc\Returned("id", type="string", desc="用户ID(hashid)"),
     *         @Apidoc\Returned("username", type="string", desc="用户名"),
     *         @Apidoc\Returned("real_name", type="string", desc="姓名"),
     *     }),
     * })
     */#[Apidoc\Title("用户登录")]
#[Apidoc\Desc("用户名密码登录，需先通过点击验证码；连续失败 5 次账号锁定 15 分钟")]
#[Apidoc\Url("/api/v1/auth/login")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("客户端 API")]
#[Apidoc\Param(name:"username", type:"string", require:true, desc:"用户名(3-50字符)")]
#[Apidoc\Param(name:"password", type:"string", require:true, desc:"密码(6-32字符)")]
#[Apidoc\Param(name:"captcha_key", type:"string", require:true, desc:"验证码标识(来自生成接口)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("access_token", type:"string", desc:"访问令牌")]
#[Apidoc\Returned("refresh_token", type:"string", desc:"刷新令牌")]
#[Apidoc\Returned("expires_in", type:"int", desc:"访问令牌有效期(秒)")]
#[Apidoc\Returned("id", type:"string", desc:"用户ID(hashid)")]
#[Apidoc\Returned("username", type:"string", desc:"用户名")]
#[Apidoc\Returned("real_name", type:"string", desc:"姓名")]

    public function login(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6|max:32',
            'captcha_key' => 'required|string',
            'clicks' => 'required|array|min:2',
        ]);

        if ($validator->fails()) {
            return json(['code' => 422, 'message' => $validator->errors()->first(), 'data' => []]);
        }

        // 验证点击验证码
        // E2E 测试口令：仅当服务端 .env 显式设置 E2E_CAPTCHA_CODE 且请求携带相同口令时放行
        // （CI 专用；生产 .env 无此变量，旁路不生效）
        $bypass = getenv('E2E_CAPTCHA_CODE');
        if ($bypass !== false && $bypass !== '' && $request->input('captcha_key') === $bypass) {
            $clicks = [];
        } else {
            $clicks = array_map(fn ($c) => [(int)$c['x'], (int)$c['y']], $request->input('clicks'));
            if (!captcha_verify($request->input('captcha_key'), 'click', $clicks)) {
                return json(['code' => 422, 'message' => '验证码错误，请重试', 'data' => []]);
            }
        }

        // 校验用户凭证
        $username = $request->input('username');
        $user = AdminUser::where('username', $username)->first();

        // 账号锁定检查（5次失败/15分钟）
        $lockKey = "account_lock:{$username}";
        try {
            if (Redis::get($lockKey)) {
                return json(['code' => 429, 'message' => '账号已被临时锁定，请15分钟后再试', 'data' => []]);
            }
        } catch (\Throwable $e) {
            // 锁定检查失败 = 防爆破保护可能失效（fail-open 降级），必须记录告警日志
            Log::warning('登录：账号锁定检查失败（Redis 不可用）: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        if (!$user || !password_verify($request->input('password'), $user->password)) {
            // 登录失败：计数 + 锁定
            try {
                $failKey = "login_fail:{$username}";
                $fails = Redis::incr($failKey);
                if ($fails === 1) {
                    Redis::expire($failKey, 900);
                }
                if ($fails >= 5) {
                    Redis::setex($lockKey, 900, '1');
                    Redis::del($failKey);

                    return json(['code' => 429, 'message' => '账号已被临时锁定，请15分钟后再试', 'data' => []]);
                }
            } catch (\Throwable $e) {
                // 失败计数写入失败 = 登录防爆破锁定不会触发（fail-open 降级），必须记录告警日志
                Log::warning('登录：失败计数/锁定写入失败（Redis 不可用）: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            }

            return json(['code' => 401, 'message' => '用户名或密码错误', 'data' => []]);
        }

        // 登录成功：清除失败计数
        try {
            Redis::del("login_fail:{$username}");
            Redis::del($lockKey);
        } catch (\Throwable $e) {
            // 清理失败仅影响下次计数准确性，记录日志即可
            Log::warning('登录：清除失败计数异常: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }

        if ($user->status === 0) {
            return json(['code' => 403, 'message' => '账号已被禁用', 'data' => []]);
        }

        // 签发 JWT
        $jwt = self::getJWT();
        $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
        $token = $jwt->encode(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = $jwt->encode(
            ['sub' => $user->id, 'token_type' => 'refresh'],
            (int)(config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600)
        );

        // 并发会话限制
        $this->trackSession($user->id, $token, $tokenExpire);

        // 更新登录信息
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp();
        $user->save();

        return json([
            'code' => 0,
            'message' => '登录成功',
            'data' => [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                'user' => [
                    'id' => Container::get('hashids')->encode($user->id),
                    'username' => $user->username,
                    'real_name' => $user->real_name,
                ],
            ],
        ]);
    }

    /**
     * 用户注册
     * @Apidoc\Title("用户注册")
     * @Apidoc\Desc("需先通过点击验证码；受 REGISTRATION_ENABLED=1 配置开关控制，默认关闭")
     * @Apidoc\Url("/api/v1/auth/register")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="username", type="string", require=true, desc="用户名(3-50字符)")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码(6-32字符)")
     * @Apidoc\Param(name="real_name", type="string", require=true, desc="姓名(≤50字符)")
     * @Apidoc\Param(name="captcha_key", type="string", require=true, desc="验证码标识(来自生成接口)")
     * @Apidoc\Param(name="clicks", type="array", require=true, desc="点击坐标数组[{x,y}],至少2个")
     * @Apidoc\Param(name="phone", type="string", desc="手机号(选填)")
     * @Apidoc\Param(name="email", type="string", desc="邮箱(选填)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("access_token", type="string", desc="访问令牌"),
     *     @Apidoc\Returned("refresh_token", type="string", desc="刷新令牌"),
     *     @Apidoc\Returned("expires_in", type="int", desc="访问令牌有效期(秒)"),
     *     @Apidoc\Returned("user", type="object", desc="用户信息", children={
     *         @Apidoc\Returned("id", type="string", desc="用户ID(hashid)"),
     *         @Apidoc\Returned("username", type="string", desc="用户名"),
     *         @Apidoc\Returned("real_name", type="string", desc="姓名"),
     *     }),
     * })
     */#[Apidoc\Title("用户注册")]
#[Apidoc\Desc("需先通过点击验证码；受 REGISTRATION_ENABLED:1 配置开关控制，默认关闭")]
#[Apidoc\Url("/api/v1/auth/register")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("客户端 API")]
#[Apidoc\Param(name:"username", type:"string", require:true, desc:"用户名(3-50字符)")]
#[Apidoc\Param(name:"password", type:"string", require:true, desc:"密码(6-32字符)")]
#[Apidoc\Param(name:"real_name", type:"string", require:true, desc:"姓名(≤50字符)")]
#[Apidoc\Param(name:"captcha_key", type:"string", require:true, desc:"验证码标识(来自生成接口)")]
#[Apidoc\Param(name:"phone", type:"string", desc:"手机号(选填)")]
#[Apidoc\Param(name:"email", type:"string", desc:"邮箱(选填)")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("access_token", type:"string", desc:"访问令牌")]
#[Apidoc\Returned("refresh_token", type:"string", desc:"刷新令牌")]
#[Apidoc\Returned("expires_in", type:"int", desc:"访问令牌有效期(秒)")]
#[Apidoc\Returned("id", type:"string", desc:"用户ID(hashid)")]
#[Apidoc\Returned("username", type:"string", desc:"用户名")]
#[Apidoc\Returned("real_name", type:"string", desc:"姓名")]

    public function register(Request $request): Response
    {
        // 注册开关：REGISTRATION_ENABLED=1 才开放，默认关闭（生产环境建议保持关闭）
        if (getenv('REGISTRATION_ENABLED') !== '1') {
            return json(['code' => 403, 'message' => '注册功能未开放', 'data' => []]);
        }

        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:50',
            'password' => 'required|string|min:6|max:32',
            'real_name' => 'required|string|max:50',
            'captcha_key' => 'required|string',
            'clicks' => 'required|array|min:2',
        ]);

        if ($validator->fails()) {
            return json(['code' => 422, 'message' => $validator->errors()->first(), 'data' => []]);
        }

        if (!captcha_verify($request->input('captcha_key'), 'click', $request->input('clicks'))) {
            return json(['code' => 422, 'message' => '验证码错误，请重试', 'data' => []]);
        }

        $username = $request->input('username');
        if (AdminUser::where('username', $username)->exists()) {
            return json(['code' => 422, 'message' => '用户名已存在', 'data' => []]);
        }

        $user = new AdminUser();
        $user->id = SnowflakeService::generate();
        $user->username = $username;
        $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $user->real_name = $request->input('real_name');
        $user->phone = $request->input('phone', '');
        $user->email = $request->input('email', '');
        $user->status = 1;
        $user->save();

        $jwt = self::getJWT();
        $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
        $token = $jwt->encode(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = $jwt->encode(
            ['sub' => $user->id, 'token_type' => 'refresh'],
            (int)(config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600)
        );

        $this->trackSession($user->id, $token, $tokenExpire);

        return json([
            'code' => 0,
            'message' => '注册成功',
            'data' => [
                'access_token' => $token,
                'refresh_token' => $refreshToken,
                'expires_in' => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                'user' => [
                    'id' => Container::get('hashids')->encode($user->id),
                    'username' => $user->username,
                    'real_name' => $user->real_name,
                ],
            ],
        ]);
    }

    /**
     * 刷新令牌
     * @Apidoc\Title("刷新令牌")
     * @Apidoc\Desc("用刷新令牌换取新的访问令牌与刷新令牌，仅接受 refresh 类型令牌")
     * @Apidoc\Url("/api/v1/auth/refresh")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="refresh_token", type="string", require=true, desc="刷新令牌")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("access_token", type="string", desc="新访问令牌"),
     *     @Apidoc\Returned("refresh_token", type="string", desc="新刷新令牌"),
     *     @Apidoc\Returned("expires_in", type="int", desc="访问令牌有效期(秒)"),
     * })
     */#[Apidoc\Title("刷新令牌")]
#[Apidoc\Desc("用刷新令牌换取新的访问令牌与刷新令牌，仅接受 refresh 类型令牌")]
#[Apidoc\Url("/api/v1/auth/refresh")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("客户端 API")]
#[Apidoc\Param(name:"refresh_token", type:"string", require:true, desc:"刷新令牌")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("access_token", type:"string", desc:"新访问令牌")]
#[Apidoc\Returned("refresh_token", type:"string", desc:"新刷新令牌")]
#[Apidoc\Returned("expires_in", type:"int", desc:"访问令牌有效期(秒)")]

    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token', '');

        if (empty($refreshToken)) {
            return json(['code' => 422, 'message' => '缺少刷新令牌', 'data' => []]);
        }

        try {
            $jwt = self::getJWT();
            $payload = $jwt->decode($refreshToken);
        } catch (Throwable $e) {
            // 令牌无效：fail-closed 拒绝，记录失败原因便于审计
            Log::warning('刷新令牌解析失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return json(['code' => 401, 'message' => '刷新令牌无效或已过期', 'data' => []]);
        }

        // 仅接受刷新令牌，访问令牌不可用于续期
        if (($payload['token_type'] ?? '') !== 'refresh') {
            return json(['code' => 401, 'message' => '请使用刷新令牌', 'data' => []]);
        }

        try {
            // 刷新时更新最后登录时间和IP
            $userId = $payload['sub'] ?? 0;
            if ($userId) {
                $user = AdminUser::find($userId);
                if ($user) {
                    $user->last_login_at = date('Y-m-d H:i:s');
                    $user->last_login_ip = $request->getRealIp();
                    $user->save();
                }
            }

            $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
            $token = $jwt->encode(['sub' => $payload['sub'], 'username' => $payload['username'] ?? '']);
            $newRefresh = $jwt->encode(
                ['sub' => $payload['sub'], 'token_type' => 'refresh'],
                (int)(config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600)
            );

            // 并发会话限制：注册新 token，移除旧 refresh token 的活跃状态
            $this->trackSession($userId, $token, $tokenExpire);
            try {
                Redis::zrem("user_tokens:{$userId}", md5($refreshToken));
            } catch (\Throwable $e) {
                // 旧令牌清理失败仅影响会话计数精度，记录日志即可
                Log::warning('刷新令牌：移除旧 refresh token 失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            }

            return json([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'access_token' => $token,
                    'refresh_token' => $newRefresh,
                    'expires_in' => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                ],
            ]);
        } catch (Throwable $e) {
            // 刷新流程内任何异常（含数据库写入失败）统一按 fail-closed 拒绝，并记录根因
            Log::error('刷新令牌流程异常: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return json(['code' => 401, 'message' => '刷新令牌无效或已过期', 'data' => []]);
        }
    }

    /**
     * 并发会话限制 — 同一用户最多 3 个有效 token
     * @param int $userId 用户 ID
     * @param string $token JWT access_token
     * @param int $expiresIn token 有效期（秒）
     */
    private function trackSession(int $userId, string $token, int $expiresIn): void
    {
        try {
            $key = "user_tokens:{$userId}";
            $exp = time() + $expiresIn;
            $member = md5($token);

            // 清理已过期的 token
            Redis::zremrangebyscore($key, 0, time());
            // 添加新 token
            Redis::zadd($key, $exp, $member);
            // 超过 3 个 → 踢出最旧的
            $count = Redis::zcard($key);
            if ($count > 3) {
                $oldest = Redis::zrange($key, 0, 0, true);
                if ($oldest) {
                    $oldMember = array_key_first($oldest);
                    $oldExp = (int) $oldest[$oldMember];
                    $ttl = max($oldExp - time(), 0);
                    Redis::zrem($key, $oldMember);
                    if ($ttl > 0) {
                        Redis::setex("jwt_blacklist:{$oldMember}", $ttl, '1');
                    }
                }
            }
            Redis::expire($key, $expiresIn + 3600);
        } catch (\Throwable $e) {
            // 有意的 fail-open 降级：会话跟踪失败不阻断登录，但并发会话限制将失效，需记录告警日志
            Log::warning('会话跟踪失败（并发会话限制可能失效）: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
        }
    }
}
