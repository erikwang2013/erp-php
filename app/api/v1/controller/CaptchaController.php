<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("验证码")
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use support\Log;
use support\Request;
use support\Response;
use Throwable;

class CaptchaController
{
    /**
     * 生成点击验证码
     * @Apidoc\Title("生成点击验证码")
     * @Apidoc\Desc("生成点击式验证码图片(base64 PNG)，key 用于后续校验")
     * @Apidoc\Url("/api/v1/captcha/generate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="difficulty", type="string", default="medium", desc="难度(easy/medium/hard)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("key", type="string", desc="验证码标识(校验时回传)"),
     *     @Apidoc\Returned("image", type="string", desc="验证码图片(base64 PNG)"),
     *     @Apidoc\Returned("extra", type="object", desc="附加信息", children={
     *         @Apidoc\Returned("targets", type="array", desc="点击目标提示[{order,text}],不含坐标"),
     *     }),
     * })
     */#[\erikwang2013\apidoc\annotation\Title("生成点击验证码")]
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
                    'image' => base64_encode($result['image']), // base64 PNG
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
     * @Apidoc\Title("校验点击验证码")
     * @Apidoc\Desc("提交 key 与点击坐标进行校验，供登录/注册等流程预校验或重试")
     * @Apidoc\Url("/api/v1/captcha/verify")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="key", type="string", require=true, desc="验证码标识")
     * @Apidoc\Param(name="clicks", type="array", require=true, desc="点击坐标数组[{x,y}]")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=验证通过,422=验证失败")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据", children={
     *     @Apidoc\Returned("valid", type="bool", desc="是否验证通过"),
     * })
     */#[\erikwang2013\apidoc\annotation\Title("校验点击验证码")]
#[\erikwang2013\apidoc\annotation\Desc("提交 key 与点击坐标进行校验，供登录/注册等流程预校验或重试")]
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

        return json([
            'code' => $valid ? 0 : 422,
            'message' => $valid ? '验证通过' : '验证失败，请重试',
            'data' => ['valid' => $valid],
        ]);
    }
}
