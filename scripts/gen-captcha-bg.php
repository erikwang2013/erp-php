<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz>
 *
 * 验证码背景图生成：把原图目录压成 config/poster.php 用的背景目录
 *
 * 为什么需要：poster-php 的 AbstractCaptcha 对 background_dir 里的图片**无尺寸守卫**
 * （GdDriver 的 MAX_PIXELS=40M 按像素算，折算 ~170MB，128M 上限下永不触发），
 * 选中哪张就整张解码 —— 实测一张 5472x3648（20MP）照片解码增量 **85.5MB**，
 * 于是每次验证码请求有约一半概率 fatal（phpunit 全套跑挂、/api/v1/captcha/generate 500）。
 * 本脚本把照片压到长边 800px（~2MB 解码），原图一张不动。
 *
 * 用法:
 *   php scripts/gen-captcha-bg.php
 *
 * 何时跑: public/img 里新增/更换照片之后（幂等，重跑即全量重建）。
 * 配套: config/poster.php 的 captcha.background_dir 指向本脚本的输出目录；
 *       输出目录不存在时该配置自动回退程序化背景（不报错）。
 */

// 原图目录 → 背景目录：两者都在 public/ 下（Web 静态目录），且已被 .gitignore 整目录忽略，
// 故压缩产物不入库；换机器没有这两个目录时，验证码自动走程序化背景。
const SRC_DIR = __DIR__.'/../public/img';
const DST_DIR = __DIR__.'/../public/img/captcha';

// 长边上限。画布最大 300x200（AbstractCaptcha::width/height），800 留足旋转/裁剪余量。
const MAX_SIDE = 800;

// 逐张解码 20MP 原图本身就要 85MB+，必须放开 CLI 默认的 128M 上限
//（memory_limit 是 INI_ALL，脚本内可改；一次性批处理，不属于请求路径）
ini_set('memory_limit', '-1');

if (!is_dir(SRC_DIR)) {
    fwrite(STDERR, '原图目录不存在: '.SRC_DIR."\n");
    exit(1);
}
if (!is_dir(DST_DIR) && !mkdir(DST_DIR, 0o755, true) && !is_dir(DST_DIR)) {
    fwrite(STDERR, '无法创建背景目录: '.DST_DIR."\n");
    exit(1);
}

$files = glob(SRC_DIR.'/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
$done = 0;
$skip = 0;
foreach ($files as $file) {
    $info = getimagesize($file);
    if ($info === false) {
        fwrite(STDERR, '跳过（无法识别）: '.basename($file)."\n");
        ++$skip;

        continue;
    }
    [$w, $h, $type] = $info;

    // 长边缩到 MAX_SIDE；原图本就够小则保持原尺寸（不放大）
    $scale = min(1.0, MAX_SIDE / max($w, $h));
    $tw = max(1, (int) round($w * $scale));
    $th = max(1, (int) round($h * $scale));

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
        IMAGETYPE_PNG => @imagecreatefrompng($file),
        IMAGETYPE_GIF => @imagecreatefromgif($file),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file),
        default => false,
    };
    if ($src === false) {
        fwrite(STDERR, '跳过（解码失败）: '.basename($file)."\n");
        ++$skip;

        continue;
    }

    $dst = imagescale($src, $tw, $th);
    imagedestroy($src);
    if ($dst === false) {
        fwrite(STDERR, '跳过（缩放失败）: '.basename($file)."\n");
        ++$skip;

        continue;
    }

    // 保持原扩展名输出：AbstractCaptcha 按 *.jpg/*.png/... 的 glob 选图，
    // 而 GdDriver 用 getimagesize 判类型，扩展名与实际编码一致才不会白白浪费一次解析
    $out = DST_DIR.'/'.basename($file);
    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dst, $out, 85),
        IMAGETYPE_PNG => imagepng($dst, $out, 8),
        IMAGETYPE_GIF => imagegif($dst, $out),
        IMAGETYPE_WEBP => imagewebp($dst, $out, 85),
    };
    imagedestroy($dst);

    if ($ok) {
        printf("%-28s %5dx%-5d → %4dx%-4d\n", basename($file), $w, $h, $tw, $th);
        ++$done;
    } else {
        fwrite(STDERR, '写入失败: '.$out."\n");
        ++$skip;
    }
}

printf("\n完成：%d 张生成 → %s（跳过 %d）\n", $done, realpath(DST_DIR) ?: DST_DIR, $skip);
exit($done > 0 ? 0 : 1);
