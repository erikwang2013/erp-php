#!/usr/bin/env bash
# ============================================================
# 品牌吉祥物位图生成（docs/mascot.svg → 四端图标/启动图）— scripts/gen-mascot-assets.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：从唯一源 docs/mascot.svg 渲染出四端所需的全部位图 —— Web favicon/PWA 图标、
#       Flutter 五平台 launcher icon、Android/iOS 启动图，保证四端同源同形。
#   用法: bash scripts/gen-mascot-assets.sh      （生成/覆盖，无参数）
#   幂等: 逐字节可复现 —— 56 个产出里 44 个由 magick 写出，每处输出都必须带
#         -define png:exclude-chunks=date,time，剔掉 PNG 里的 date:* 文本块（其值取输出
#         文件 mtime，1 秒粒度）；否则同一像素两次编码会得到不同字节（实测 39/56 字节
#         不同、像素零漂移）。`-strip` 无效 —— 属性是写时重算的，必须用该 define。
#         另 12 个由 rsvg-convert 直写（Android 传统图标 5 档 + macOS 7 档），不经该 define，
#         实测本就不嵌时间戳（两跑零差异）—— 别把「幂等」全归因给 define。
#         判据是内容哈希：跑两次比对全部位图 md5 / `%#` 像素签名。
#   依赖: rsvg-convert (librsvg2-bin)、magick (ImageMagick 7)
#   约定: 圆徽占源图 96%（r=192 / viewBox 400），圆外透明。因此
#         • 需要不透明底的平台（iOS / PWA"any"图标）压 #EEF4FF（圆徽自身底色）
#         • 会被系统裁剪的平台按安全区缩放：Android 自适应 66/108、PWA maskable 80%
#         • 保留透明的平台（Android 传统图标 / macOS / Windows / HOS）用原图 alpha
#   配套: docs/mascot.svg 是唯一真源；改图只改 SVG，再重跑本脚本。
#         400×400 基准图（$MASTER）正常已在库中；若被删除，本脚本会从 SVG 重渲染。
# ============================================================

set -euo pipefail
cd "$(dirname "$0")/.."

SVG="$(pwd)/docs/mascot.svg"
MASTER="apps/flutter/assets/mascot.png"   # 400×400 基准位图（四端同源，与 HOS 端逐字节相同）
FLAT="#EEF4FF"                            # 圆徽底色，用于必须不透明的平台
PNG_DEF=(-define png:exclude-chunks=date,time)   # 见文件头「幂等」：保证逐字节可复现
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT

[ -f "$SVG" ] || { echo "缺少源文件 $SVG" >&2; exit 1; }
command -v rsvg-convert >/dev/null || { echo "缺少 rsvg-convert" >&2; exit 1; }
command -v magick >/dev/null || { echo "缺少 ImageMagick 7 (magick)" >&2; exit 1; }
[ -f "$MASTER" ] || rsvg-convert -w 400 -h 400 "$SVG" -o "$MASTER"

render() { rsvg-convert -w "$1" -h "$1" "$SVG" -o "$2"; }                 # 透明底正方形
flat()   { render "$1" "$TMP/f.png"; magick "$TMP/f.png" -background "$FLAT" -alpha remove -alpha off "${PNG_DEF[@]}" "$2"; }
soft()   { # $1 画布边长 $2 圆徽直径 $3 输出：圆徽按直径缩放后居中，透明底
  local c=$1 d=$2 content
  content=$(awk -v c="$c" -v d="$d" 'BEGIN{printf "%d", (d/0.96)+0.5}')
  render "$content" "$TMP/p.png"
  magick "$TMP/p.png" -background none -gravity center -extent "${c}x${c}" "${PNG_DEF[@]}" "$3"
}
softflat() { soft "$1" "$2" "$TMP/s.png"; magick "$TMP/s.png" -background "$FLAT" -alpha remove -alpha off "${PNG_DEF[@]}" "$3"; }

# ---------- 1. Web 两端（Angular / React）：favicon + PWA 图标 + 页面内用图 ----------
for dir in apps/angular/public apps/react/public; do
  mkdir -p "$dir"
  cp "$MASTER" "$dir/mascot.png"                       # 页面内品牌位（登录页/侧栏/空态）
  render 16 "$TMP/16.png"; render 32 "$TMP/32.png"; render 48 "$TMP/48.png"
  magick "$TMP/16.png" "$TMP/32.png" "$TMP/48.png" "${PNG_DEF[@]}" "$dir/favicon.ico"
  flat 180 "$dir/apple-touch-icon.png"
  flat 192 "$dir/icon-192.png"
  flat 512 "$dir/icon-512.png"
  softflat 192 154 "$dir/icon-maskable-192.png"        # 80% 安全区
  softflat 512 410 "$dir/icon-maskable-512.png"
done

# ---------- 2. Flutter / Android ----------
cd apps/flutter/android/app/src/main/res
# 传统 launcher 图标（API<26 与部分启动器直接用；透明底，与 HOS app_icon 观感一致）
for spec in mdpi:48 hdpi:72 xhdpi:96 xxhdpi:144 xxxhdpi:192; do
  d=${spec%%:*}; px=${spec##*:}
  mkdir -p "drawable-$d"
  render "$px" "mipmap-$d/ic_launcher.png"
  # 自适应图标前景（108dp 画布，圆徽缩进 66dp 安全区，避免被 OEM 圆形遮罩裁掉）
  soft "$((px * 108 / 48))" "$((px * 66 / 48))" "drawable-$d/ic_launcher_foreground.png"
done
# 启动图（冷启动窗口：白底 + 居中吉祥物）
# 只出 xxxhdpi 一档是设计如此，不是漏档：位图固有 dp = 像素 ÷ 密度倍率 ⇒ 512px 落在
# xxxhdpi = 128dp（想要的 splash 图标尺寸）；若放 drawable/（按 mdpi 解释）就成了 512dp，
# 会撑爆屏幕。自适应图标前景图必须按 108dp × 倍率 精确对齐安全区，故那里才要五档。
soft 512 384 "drawable-xxxhdpi/launch_image.png"
cd - >/dev/null

# ---------- 3. Flutter / iOS（AppIcon 禁 alpha，必须压底） ----------
IOS=apps/flutter/ios/Runner/Assets.xcassets
for spec in 20x20@1x:20 20x20@2x:40 20x20@3x:60 29x29@1x:29 29x29@2x:58 29x29@3x:87 \
            40x40@1x:40 40x40@2x:80 40x40@3x:120 60x60@2x:120 60x60@3x:180 \
            76x76@1x:76 76x76@2x:152 83.5x83.5@2x:167 1024x1024@1x:1024; do
  flat "${spec##*:}" "$IOS/AppIcon.appiconset/Icon-App-${spec%%:*}.png"
done
# 启动图（storyboard 里是 168×185 的 image view，白底）
for spec in "LaunchImage.png 168x185 120" "LaunchImage@2x.png 336x370 240" "LaunchImage@3x.png 504x555 360"; do
  set -- $spec
  render "$3" "$TMP/l.png"
  magick "$TMP/l.png" -background white -gravity center -extent "$2" "${PNG_DEF[@]}" "$IOS/LaunchImage.imageset/$1"
done

# ---------- 4. Flutter / macOS（允许 alpha，保留圆徽） ----------
for px in 16 32 64 128 256 512 1024; do
  render "$px" "apps/flutter/macos/Runner/Assets.xcassets/AppIcon.appiconset/app_icon_$px.png"
done

# ---------- 5. Flutter / Windows ----------
for px in 16 24 32 48 64 128 256; do render "$px" "$TMP/w$px.png"; done
magick "$TMP"/w{16,24,32,48,64,128,256}.png "${PNG_DEF[@]}" apps/flutter/windows/runner/resources/app_icon.ico

# ---------- 6. Flutter / Web ----------
flat 32 apps/flutter/web/favicon.png
flat 192 apps/flutter/web/icons/Icon-192.png
flat 512 apps/flutter/web/icons/Icon-512.png
softflat 192 154 apps/flutter/web/icons/Icon-maskable-192.png
softflat 512 410 apps/flutter/web/icons/Icon-maskable-512.png

echo "吉祥物位图已生成（源：$SVG）"
