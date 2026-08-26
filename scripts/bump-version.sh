#!/usr/bin/env bash
# ============================================================
# 版本增量发布（推送规则）— scripts/bump-version.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：推送后按最新 tag 增量创建新版本 tag（v1.1.4 → v1.1.5）。
#   - --check   仅打印下一个版本号（不写远端，供 CI 与本地预览）
#   - --create  创建 annotated tag 并推送 origin（远端已存在则跳过，
#                避免并发 CI / 手动已打时重复创建失败）
#
# CI 集成：.github/workflows/ci.yml 的 release 作业（全部检查通过后执行）
# 本地用法：bash scripts/bump-version.sh --check
# ============================================================

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# 最新 vX.Y.Z 三段格式 tag 的 patch+1；无匹配 tag 时兜底 v0.0.1
# 注：先 sed 去掉 v 前缀再 awk，避免 "v1"+0 被 awk 数值化误判为 0
next_version() {
  git tag --sort=-v:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -1 \
    | sed 's/^v//' \
    | awk -F. '{printf "v%d.%d.%d\n", $1, $2, $3+1}' \
    || echo "v0.0.1"
}

case "${1:-}" in
  --check)
    next_version
    ;;
  --create)
    NEW="$(next_version)"
    if git ls-remote --tags origin "refs/tags/${NEW}" 2>/dev/null | grep -q .; then
      echo "tag ${NEW} 已存在于 origin，跳过"
      exit 0
    fi
    git tag -a "$NEW" -m "Release ${NEW}"
    git push origin "$NEW"
    echo "已推送版本 tag: ${NEW}"
    ;;
  *)
    echo "用法: bash scripts/bump-version.sh --check|--create" >&2
    exit 1
    ;;
esac
