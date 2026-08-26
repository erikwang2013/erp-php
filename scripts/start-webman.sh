#!/usr/bin/env bash
# ============================================================
# webman 启动包装 — scripts/start-webman.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：以 php start.php 相同的参数启动 webman。
#   LD_PRELOAD 预加载 libgomp：避免 imagick（dpkg 版 ImageMagick
#   带 OpenMP）在 dlopen 时触发 libgomp init 段错误。
#   CLI/测试请自行加 LD_PRELOAD，例：
#     LD_PRELOAD=/lib/x86_64-linux-gnu/libgomp.so.1 php ...
# ============================================================

set -euo pipefail

export LD_PRELOAD=/lib/x86_64-linux-gnu/libgomp.so.1

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec php "$ROOT/start.php" "$@"
