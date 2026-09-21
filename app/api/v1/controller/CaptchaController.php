<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use support\Log;
use support\Request;
use support\Response;
use Throwable;

#[\erikwang2013\apidoc\annotation\Title('验证码')]
#[\erikwang2013\apidoc\annotation\Group('客户端认证')]

class CaptchaController
{
    /**
     * 生成验证码：click（点击字）/ rotate（旋转图）/ slider（滑块拼图），默认 click
     */
    #[\erikwang2013\apidoc\annotation\Title('生成验证码')]
    #[\erikwang2013\apidoc\annotation\Desc('按类型生成验证码图片(base64 PNG)，key 用于后续校验；click/rotate 主图在 image，slider 拼图块在 extra.puzzle')]
    #[\erikwang2013\apidoc\annotation\Url('/api/v1/captcha/generate')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('客户端 API')]
    #[\erikwang2013\apidoc\annotation\Param(name:'type', type:'string', default:'click', desc:'验证码类型(click/rotate/slider)；random=三型随机标识，实际类型见 data.type')]
    #[\erikwang2013\apidoc\annotation\Param(name:'difficulty', type:'string', default:'medium', desc:'难度(easy/medium/hard)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('key', type:'string', desc:'验证码标识(校验时回传)')]
    #[\erikwang2013\apidoc\annotation\Returned('image', type:'string', desc:'验证码图片(base64 PNG)')]
    #[\erikwang2013\apidoc\annotation\Returned('type', type:'string', desc:'实际验证码类型(click/rotate/slider)')]

    public function generate(Request $request): Response
    {
        $type = $request->input('type', 'click');
        // random 非类型，= 三型随机标识（插件内 array_rand 分发）；响应 data.type
        // 为实际具体类型，客户端按它分支渲染。verify 只收具体三型（见下）。
        if (!in_array($type, ['click', 'rotate', 'slider', 'random'], true)) {
            return json(['code' => 422, 'message' => '不支持的验证码类型', 'data' => []]);
        }
        $difficulty = $request->input('difficulty', 'medium');
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            return json(['code' => 422, 'message' => '不支持的验证码难度', 'data' => []]);
        }

        try {
            $result = captcha_create($type, ['difficulty' => $difficulty]);
            // driver 返回 data URI（data:image/png;base64,...）→ 还原为单层 base64 PNG，
            // 客户端按「base64 PNG」解码（不再二次编码）
            $png = static function (string $raw): string {
                $pos = strpos($raw, ';base64,');

                return $pos !== false ? substr($raw, $pos + 8) : $raw;
            };

            return json([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'key' => $result['key'],
                    // 插件 generate 已带 type（click/rotate/slider），透传供客户端按类型处理
                    'type' => $result['type'] ?? $type,
                    'image' => $png($result['image']),
                    // 各类型挑战信息：click 仅下发目标文字（坐标属服务端秘密）、
                    // slider 下发拼图块与其像素尺寸、rotate 无额外信息
                    // 注意按「实际返回类型」组装（type=random 时以 data.type 为准）
                    'extra' => match ($result['type'] ?? $type) {
                        'click' => ['targets' => $result['extra']['texts'] ?? []],
                        'slider' => [
                            'puzzle' => $png((string)($result['extra']['puzzle'] ?? '')),
                            'puzzle_w' => (int)($result['extra']['puzzle_w'] ?? 0),
                            'puzzle_h' => (int)($result['extra']['puzzle_h'] ?? 0),
                            // 缺口纵坐标（非校验要素，仅为客户端同高渲染拼图块；x 仍是答案不下发）
                            'puzzle_y' => (int)($result['extra']['puzzle_y'] ?? 0),
                        ],
                        default => [],
                    },
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
     * 校验验证码（三种类型统一入口，默认 click）
     */
    #[\erikwang2013\apidoc\annotation\Title('校验验证码')]
    #[\erikwang2013\apidoc\annotation\Desc('三型验证码统一校验点：click 传 clicks 坐标序列、rotate 传 angle（用户为摆正图片所施加的顺时针读数 0-359）、slider 传 distance 拖动距离（与原图同尺度像素）；通过即消费挑战并写入一次性放行凭证（captcha_pass:<key>，5 分钟有效），登录/注册接口凭 captcha_key 消费放行，不再重复比对')]
    #[\erikwang2013\apidoc\annotation\Url('/api/v1/captcha/verify')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('客户端 API')]
    #[\erikwang2013\apidoc\annotation\Param(name:'type', type:'string', default:'click', desc:'验证码类型(click/rotate/slider)')]
    #[\erikwang2013\apidoc\annotation\Param(name:'key', type:'string', require:true, desc:'验证码标识')]
    #[\erikwang2013\apidoc\annotation\Param(name:'clicks', type:'array', desc:'点击坐标(click 必填, 如 [{"x":120,"y":80}])')]
    #[\erikwang2013\apidoc\annotation\Param(name:'angle', type:'number', desc:'旋转角度(rotate 必填, 0-359)：用户为摆正图片所施加的顺时针读数（图片被顺时针转了 A°，摆正时读数即 360-A）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'distance', type:'number', desc:'滑块拖动距离(slider 必填, 与验证码原图同尺度像素)')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=验证通过,422=验证失败')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('valid', type:'bool', desc:'是否验证通过')]

    public function verify(Request $request): Response
    {
        $key = $request->input('key', '');
        if ($key === '') {
            return json(['code' => 422, 'message' => '缺少验证参数', 'data' => []]);
        }

        $type = $request->input('type', 'click');
        if (!in_array($type, ['click', 'rotate', 'slider'], true)) {
            return json(['code' => 422, 'message' => '不支持的验证码类型', 'data' => []]);
        }

        // 校验负载按类型提取；null = 参数缺失或格式错误
        $payload = match ($type) {
            'click' => $this->clickPayload($request),
            'rotate' => $this->rotatePayload($request),
            'slider' => $this->floatPayload($request, 'distance'),
        };
        if ($payload === null) {
            return json(['code' => 422, 'message' => '缺少或无效的验证参数', 'data' => []]);
        }

        $valid = captcha_verify($key, $type, $payload);

        if ($valid) {
            // 验证成功：插件挑战已被一次性消费（见 CaptchaManager::verify 成功即 del），
            // 改记业务侧放行凭证，登录/注册凭 captcha_key 消费放行、不再重复比对坐标。
            // 存储必须与挑战同驱动（captcha_pass_store()），不能硬编码 Redis。
            try {
                $written = captcha_pass_store()->set("captcha_pass:{$key}", ['pass' => 1], 300);
            } catch (\Throwable $e) {
                $written = false;
                Log::error('验证码放行凭证写入异常: ' . $e->getMessage() . ' | TraceId: ' . trace_id());
            }
            if (!$written) {
                // 凭证写失败 = 放行链断裂，fail-closed 拒绝；这是服务端存储故障，
                // 不能报成「验证失败」（用户会以为是自己答错而反复重试）
                Log::error("验证码放行凭证写入失败 key={$key} | TraceId: " . trace_id());

                return json(['code' => 500, 'message' => '验证码服务异常，请稍后重试', 'data' => []]);
            }
        }

        return json([
            'code' => $valid ? 0 : 422,
            'message' => $valid ? '验证通过' : '验证失败，请重试',
            'data' => ['valid' => $valid],
        ]);
    }

    /** 点击坐标序列：{x,y}[] → [x,y][]；参数缺失或非数组返回 null */
    private function clickPayload(Request $request): ?array
    {
        $clicks = $request->input('clicks', []);
        if (!is_array($clicks) || $clicks === []) {
            return null;
        }

        // 前/后端传递 {x, y} 格式，captcha_verify 内部期望 [x, y]
        return array_map(fn ($c) => [(int)$c['x'], (int)$c['y']], $clicks);
    }

    /** 数值型负载（旋转角度/滑块距离）：缺失或非数值返回 null */
    private function floatPayload(Request $request, string $field): ?float
    {
        $value = $request->input($field);

        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * 旋转型负载：取客户端读数（用户为摆正图片所施加的顺时针度数）的负值交给插件。
     *
     * 插件按「还原幅度 A」比对（CaptchaManager::checkRotate 比 |θ-A| ≤ 容差），而
     * GdDriver::rotate(+A) 画出的图片内容本身就是**顺时针 A°**（内部 imagerotate(-A)，
     * 已实测），用户把图摆正时读数 = 360-A ≠ A —— 直接透传等于无论怎么摆正都判失败。
     * 取负后 -(360-A) ≡ A (mod 360)，插件的负角归一化（fmod 后 +360）正好接住。
     *
     * 四个前端（Angular/React/Flutter/HarmonyOS）读数约定一致：顺时针递增、提交读数。
     * 容差与一次性消费不变，取负是双射，不会放宽判定。驱动方向随驱动而变，
     * 故 config/poster.php 把 image.driver 钉死 gd（该包硬依赖 ext-gd）。
     */
    private function rotatePayload(Request $request): ?float
    {
        $angle = $this->floatPayload($request, 'angle');

        return $angle === null ? null : -$angle;
    }
}
