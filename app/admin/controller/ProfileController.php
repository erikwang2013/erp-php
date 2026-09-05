<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use app\model\AdminUser;
use Erikwang2013\Jwt\JWT;
use support\Log;
use support\Redis;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("更新个人")]

class ProfileController extends BaseController
{
    private static function getJWT(): JWT
    {
        return jwt_instance();
    }

    /**
     * 更新个人信息
     */
#[\erikwang2013\apidoc\annotation\Title("更新个人信息")]
#[\erikwang2013\apidoc\annotation\Desc("更新当前登录用户的真实姓名、手机号和邮箱")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/profile")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("个人中心")]
#[\erikwang2013\apidoc\annotation\Param(name:"real_name", type:"string", default:"", desc:"真实姓名")]
#[\erikwang2013\apidoc\annotation\Param(name:"phone", type:"string", default:"", desc:"手机号")]
#[\erikwang2013\apidoc\annotation\Param(name:"email", type:"string", default:"", desc:"邮箱")]
#[\erikwang2013\apidoc\annotation\Param(name:"avatar", type:"string", default:"", desc:"头像URL")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的用户信息(脱敏)")]

    public function updateProfile(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        if ($request->has('real_name')) {
            $user->real_name = $request->input('real_name');
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone', '');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email', '');
        }

        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        // phone/email 由 Encryptable cast 自动加解密，无需额外处理

        return $this->success($this->encodeIds($data), '更新成功');
    }

    /**
     * 修改密码
     */
#[\erikwang2013\apidoc\annotation\Title("修改密码")]
#[\erikwang2013\apidoc\annotation\Desc("修改当前登录用户的登录密码，需验证旧密码")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/profile/password")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("个人中心")]
#[\erikwang2013\apidoc\annotation\Param(name:"old_password", type:"string", require:true, desc:"旧密码")]
#[\erikwang2013\apidoc\annotation\Param(name:"new_password", type:"string", require:true, desc:"新密码(6-32位)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function updatePassword(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail('用户不存在', 404);
        }

        $oldPassword = $request->input('old_password', '');
        $newPassword = $request->input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail('请填写旧密码和新密码', 422);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return $this->fail('旧密码错误', 422);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 32) {
            return $this->fail('新密码长度 6-32 位', 422);
        }

        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        return $this->success([], '密码修改成功');
    }

    /**
     * 登出
     */
#[\erikwang2013\apidoc\annotation\Title("登出")]
#[\erikwang2013\apidoc\annotation\Desc("退出当前登录，将当前JWT令牌加入黑名单使其立即失效")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/profile/logout")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("个人中心")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function logout(Request $request): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return $this->fail('未登录', 401);
        }

        // 令牌已失效/无效：登出本身即为幂等成功，无需写入黑名单
        try {
            $payload = self::getJWT()->decode($token);
        } catch (\Throwable $e) {
            Log::warning('登出：令牌已失效，按幂等成功处理 | TraceId: ' . trace_id());

            return $this->success([], '已登出');
        }

        // 黑名单写入失败：令牌可能仍有效，不能谎报登出成功（fail-closed）
        try {
            $ttl = max((int)($payload['exp'] ?? 0) - time(), 0);
            Redis::setex('jwt_blacklist:' . md5($token), $ttl, '1');
        } catch (\Throwable $e) {
            Log::error('登出失败：JWT 黑名单写入异常: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return $this->fail('登出失败，请稍后重试', 500);
        }

        return $this->success([], '已登出');
    }
}
