<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

return [
    // ── Image Driver 图像处理驱动 ──
    'image' => [
        // 驱动类型 / Driver type: 'auto' | 'gd' | 'imagick'
        // 'auto' auto-detects available driver / 自动检测可用驱动
        'driver' => 'auto',

        // JPEG output quality / JPEG 输出质量 0-100
        'quality' => 90,

        // 默认字体路径 / Default font path
        // null = 使用包内置字体（vendor/erikwang2013/poster-php/src/fonts，ClickCaptcha 回退路径），
        // 项目根无 src/fonts，勿填 dirname(__DIR__).'/src/fonts/...'（路径不存在时
        // GdDriver 静默回退 ASCII 位图字体，中文目标字整批消失）
        'font' => null,
    ],

    // ── Captcha Module 验证码模块 ──
    'captcha' => [
        // 验证数据存储 / Storage driver: 'auto' | 'file' | 'session' | 'redis'
        // 'auto': Redis > Session > File, auto-detect / 自动检测
        'storage' => 'auto',

        // 验证码有效期（秒）/ TTL in seconds
        // Key expires after this duration / 超时后 key 作废
        'ttl' => 300,

        // 同一 key 最多验证次数 / Max verification attempts per key
        // Prevents brute-force enumeration / 防暴力枚举
        'max_attempts' => 3,

        // 默认验证码类型 / Default captcha type: 'click' | 'rotate' | 'slider' | 'random'
        'default_type' => 'random',

        // 默认难度 / Default difficulty: 'easy' | 'medium' | 'hard'
        'default_difficulty' => 'medium',

        // 默认背景图目录（放 png/jpg/gif/webp），随机选用
        // null = 使用程序化生成
        // Background image directory; null = procedural generation
        'background_dir' => dirname(__DIR__) . '/assets/backgrounds',

        // 程序化背景风格 / Procedural background styles
        // Available: 'minimal', 'vibrant', 'natural'
        'background_styles' => ['minimal', 'vibrant', 'natural'],

        // 验证误差容忍 / Verification tolerance
        'tolerance' => [
            'click' => 18,   // 点击验证像素半径 / Click: pixel radius
            'rotate' => 5,    // 旋转验证角度 / Rotate: degrees
            // slider 4→8：人工拖动目视对齐即过（原生 300 宽 ≈ 显示 8.5px），
            // 避免"看着已对齐却被判失败"的挫败；块图纹已与缺口 1:1，命中即无缝
            'slider' => 8,    // 滑块验证像素 / Slider: pixels
        ],

        // Redis 存储配置（storage=redis 时生效）/ Redis config (effective when storage=redis)
        'redis' => [
            // Redis key prefix / Redis 键前缀
            'prefix' => 'poster:captcha:',
            // Redis connection name (framework-specific) / Redis 连接名（框架相关）
            'connection' => 'default',
        ],

        // 文件存储配置（storage=file 时生效）/ File storage config (effective when storage=file)
        'file' => [
            // 存储路径 / Storage path, null = system temp dir / 系统临时目录
            'path' => null,
        ],
        'click_words' => [
            '合',
            '家',
            '欢',
            '乐',
            '良',
            '辰',
            '美',
            '景',
            '千',
            '变',
            '万',
            '化',
            '心',
            '有',
            '灵',
            '犀',
            '五',
            '湖',
            '四',
            '海',
            '山',
            '川',
            '美',
            '景',
            '花',
            '好',
            '月',
            '圆',

        ],
    ],

    // ── Poster Module 海报生成模块 ──
    'poster' => [
        // 画布默认宽高（px）/ Default canvas width & height
        'default_width' => 750,
        'default_height' => 1334,

        // 默认字体路径 / Default font path（同上：null=包内置字体）
        'font' => null,

        // JPEG output quality / JPEG 输出质量 0-100
        'jpeg_quality' => 90,

        // PNG compression level / PNG 压缩级别 0-9
        // 0 = no compression / 不压缩, 9 = max / 最大压缩
        'png_compression' => 6,
    ],
];
