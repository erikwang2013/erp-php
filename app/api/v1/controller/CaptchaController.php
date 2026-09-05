<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use support\Log;
use support\Redis;
use support\Request;
use support\Response;
use Throwable;
#[\erikwang2013\apidoc\annotation\Title("生成点击验证码")]
#[\erikwang2013\apidoc\annotation\Group("客户端认证")]

class CaptchaController
{
    /**
     * 生成点击验证码
     *     }),
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("生成点击验证码")]
#[\erikwang2013\apidoc\annotation\Desc("生成点击式验证码图片(base64 PNG)，key 用于后续校验")]
#[\erikwang2013\apidoc\annotation\Url("/api/v1/captcha/generate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("客户端 API")]
#[\erikwang2013\apidoc\annotation\Param(name:"difficulty", type:"string", default:"medium", desc:"难度(easy/medium/hard)")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("key", type:"string", desc:"验证码标识(校验时回传)")]
#[\erikwang2013\apidoc\annotation\Returned("image", type:"string", desc:"验证码图片(base64 PNG)")]

    public function generate(Request $request): Response
    {
        $difficulty = $request->input('difficulty', 'medium');

        try {
            $result = captcha_create('click', ['difficulty' => $difficulty]);

            return json([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'key' => $result['key'],
                    // driver 返回 data URI（data:image/png;base64,...）→ 还原为单层 base64 PNG，
                    // 客户端按「base64 PNG」解码（不再二次编码）
                    'image' => (static function (string $raw): string {
                        $pos = strpos($raw, ';base64,');
                        return $pos !== false ? substr($raw, $pos + 8) : $raw;
                    })($result['image']),
                    // 目标坐标属服务端秘密，仅下发 texts（order+text）供客户端提示点击目标
                    'extra' => [
                        'targets' => $result['extra']['texts'] ?? [],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            // fail-closed：向客户端返回明确失败，同时记录根因便于排查
            Log::error('验证码生成失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

            return json([
                'code' => 500,
                'message' => '验证码生成失败',
                'data' => [],
            ]);
        }
    }

    /**
     * 校验点击验证码
     * })
     */
#[\erikwang2013\apidoc\annotation\Title("校验点击验证码")]
#[\erikwang2013\apidoc\annotation\Desc("点击坐标的唯一校验点：通过即消费验证码挑战并写入一次性放行凭证（captcha_pass:<key>，5 分钟有效），登录/注册接口凭 captcha_key 消费放行，不再重复比对坐标")]
#[\erikwang2013\apidoc\annotation\Url("/api/v1/captcha/verify")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("客户端 API")]
#[\erikwang2013\apidoc\annotation\Param(name:"key", type:"string", require:true, desc:"验证码标识")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=验证通过,422=验证失败")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("valid", type:"bool", desc:"是否验证通过")]

    public function verify(Request $request): Response
    {
        $key = $request->input('key', '');
        $clicks = $request->input('clicks', []);

        if (empty($key) || empty($clicks)) {
            return json(['code' => 422, 'message' => '缺少验证参数', 'data' => []]);
        }

        // 前/后端传递 {x, y} 格式，captcha_verify 内部期望 [x, y]
        $clicks = array_map(fn ($c) => [(int)$c['x'], (int)$c['y']], $clicks);
        $valid = captcha_verify($key, 'click', $clicks);

        if ($valid) {
            // 验证成功：插件挑战已被一次性消费（见 CaptchaManager::verify 成功即 del），
            // 改记业务侧放行凭证，登录/注册凭 captcha_key 消费放行、不再重复比对坐标
            try {
                Redis::setex("captcha_pass:{$key}", 300, '1');
            } catch (\Throwable $e) {
                // 凭证写失败 = 放行链断裂，fail-closed 拒绝并记录根因
                Log::error('验证码放行凭证写入失败: ' . $e->getMessage() . ' | TraceId: ' . trace_id());

                return json(['code' => 500, 'message' => '验证码校验失败，请重试', 'data' => []]);
            }
        }

        return json([
            'code' => $valid ? 0 : 422,
            'message' => $valid ? '验证通过' : '验证失败，请重试',
            'data' => ['valid' => $valid],
        ]);
    }
}
