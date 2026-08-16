#!/usr/bin/env bash
# ============================================================
# 文档统计自动化（D3/D5）— scripts/doc-stats.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：统计项目代码规模的「真实数字」，供 docs/ 引用与 CI 校验。
#   - 默认模式：输出稳定 key=value 行（每行一个统计键），供文档与 CI 解析；
#   - --check 模式：生成当前统计后，扫描 docs/**/*.md 中形如
#     <!-- stats:key=value --> 的注释标注，逐键比对，漂移即非零退出。
#
# 用法：
#   bash scripts/doc-stats.sh                 # 输出 key=value 统计
#   bash scripts/doc-stats.sh --check [docs]  # 校验 docs 标注与实测一致（默认 docs/）
#   bash scripts/doc-stats.sh --help
#
# CI 集成：.github/workflows/ci.yml 的 docs 作业执行
#   bash scripts/doc-stats.sh --check
# ============================================================

set -uo pipefail

# 项目根目录：脚本位于 scripts/ 下，任何工作目录都可运行
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# 统计结果写入临时文件（避免子 shell 环境污染），退出时自动清理
STATS_FILE="$(mktemp)"
trap 'rm -f "$STATS_FILE"' EXIT

# ------------------------------------------------------------
# collect —— 采集全部统计键，逐行写入 $STATS_FILE（key=value）
# 每个统计都带中文注释说明口径；命令失败时给安全默认值而非中断脚本。
# ------------------------------------------------------------
collect() {
  local v

  # ---- 控制器 ----
  # 全量：app 下所有 controller 目录内的 .php（含 admin/api/common/业务）
  v="$(find app -path '*/controller/*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "controllers=${v:-0}"
  # 分组 1：系统管理后台（app/admin/controller）
  v="$(find app/admin -path '*/controller/*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "controllers_admin=${v:-0}"
  # 分组 2：客户端 API（app/api/v*/controller）
  v="$(find app/api -path '*/controller/*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "controllers_api=${v:-0}"
  # 分组 3：业务模块（app/controller 及其子目录）
  v="$(find app/controller -name '*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "controllers_business=${v:-0}"
  # 分组 4：公共（app/common/controller，Definitions.php 为数据结构定义、非控制器类）
  v="$(find app/common -path '*/controller/*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "controllers_common=${v:-0}"

  # ---- 服务（业务逻辑层，容器注册）----
  v="$(find app/service -name '*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "services=${v:-0}"

  # ---- 模型 ----
  # 原始文件数（find app/model -name '*.php'）
  v="$(find app/model -name '*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "models_files=${v:-0}"
  # 真实模型类数：排除 concerns/ 下的 trait（TenantScope.php 为 trait 非模型）
  v="$(find app/model -name '*.php' -type f ! -path '*/concerns/*' 2>/dev/null | wc -l | tr -d ' ')"
  echo "models=${v:-0}"

  # ---- 迁移 ----
  # 正向迁移数：回滚（*rollback*）文件不计入
  v="$(find database/migrations -maxdepth 1 -name '*.sql' -type f ! -name '*rollback*' 2>/dev/null | wc -l | tr -d ' ')"
  echo "migrations=${v:-0}"

  # ---- 中间件 ----
  v="$(find app/middleware -maxdepth 1 -name '*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "middleware=${v:-0}"

  # ---- 数据库表 ----
  v="$(grep -c 'CREATE TABLE' database/install.sql 2>/dev/null | tr -d ' ')"
  echo "tables=${v:-0}"

  # ---- 业务模块 ----
  # 口径：app/controller 下的模块目录数（bi/crm/dms/eam/finance/hr/...）
  v="$(find app/controller -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l | tr -d ' ')"
  echo "modules=${v:-0}"

  # ---- PHP 源文件 ----
  v="$(find app -name '*.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "php_files=${v:-0}"

  # ---- 测试 ----
  # 测试文件数：tests 下所有 *Test.php（含 tests/Integration/）
  v="$(find tests -name '*Test.php' -type f 2>/dev/null | wc -l | tr -d ' ')"
  echo "test_files=${v:-0}"

  # 测试方法数 / 断言数：优先实测 vendor/bin/phpunit --no-coverage 解析输出；
  # 无 vendor 时回退读取 .phpunit.result.cache 的方法数（断言数无法从缓存取得）。
  local tests="" assertions=""
  if [[ -x vendor/bin/phpunit ]]; then
    local raw out
    raw="$(php vendor/bin/phpunit --no-coverage 2>&1 || true)"
    # 剥离 ANSI 颜色码，兼容 phpunit.xml colors=true 的输出
    out="$(printf '%s' "$raw" | sed -E $'s/\x1B\\[[0-9;]*[mK]//g')"
    if [[ "$out" =~ OK\ \(([0-9]+)\ tests?,\ ([0-9]+)\ assertions?\) ]]; then
      tests="${BASH_REMATCH[1]}"
      assertions="${BASH_REMATCH[2]}"
    elif [[ "$out" =~ Tests:\ ([0-9]+),\ Assertions:\ ([0-9]+) ]]; then
      tests="${BASH_REMATCH[1]}"
      assertions="${BASH_REMATCH[2]}"
    fi
  fi
  if [[ -z "$tests" && -f .phpunit.result.cache ]]; then
    tests="$(php -r '$d=json_decode(file_get_contents(".phpunit.result.cache"), true); echo count($d["times"] ?? []);' 2>/dev/null || true)"
  fi
  [[ -z "$tests" ]] && tests="unknown"
  [[ -z "$assertions" ]] && assertions="unknown"
  echo "tests=${tests}"
  echo "assertions=${assertions}"
}

# ------------------------------------------------------------
# check_docs —— 校验 docs/**/*.md 中的 <!-- stats:key=value --> 标注
# 与实测统计是否一致；任一不匹配（或标注了脚本不存在的键）即失败退出 1。
# ------------------------------------------------------------
check_docs() {
  local docs_dir="${1:-$ROOT/docs}"
  local fail=0 checked=0 line file lineno ann inner key val actual

  if [[ ! -d "$docs_dir" ]]; then
    echo "✗ 文档目录不存在: $docs_dir"
    return 1
  fi

  echo "== 文档统计校验 =="
  echo "统计来源: bash scripts/doc-stats.sh（实时采集）"
  echo "校验目录: $docs_dir"
  echo ""

  # grep -rnoE 输出格式: 文件:行号:<!-- stats:key=value -->
  while IFS= read -r line; do
    [[ -z "$line" ]] && continue
    file="${line%%:*}"
    rest="${line#*:}"
    lineno="${rest%%:*}"
    ann="${rest#*:}"

    # 提取 key 与标注值
    inner="${ann#<!-- stats:}"
    inner="${inner% -->}"
    key="${inner%%=*}"
    val="${inner#*=}"

    checked=$((checked + 1))
    actual="$(grep -m1 "^${key}=" "$STATS_FILE" | cut -d= -f2- || true)"

    if [[ -z "$actual" ]]; then
      echo "✗ $file:$lineno — 未知统计键 stats:${key}（脚本未输出该键）"
      fail=$((fail + 1))
    elif [[ "$actual" != "$val" ]]; then
      echo "✗ $file:$lineno — stats:${key} 标注 ${val} ≠ 实测 ${actual}"
      fail=$((fail + 1))
    fi
  done < <(grep -rnoE '<!-- stats:[a-zA-Z0-9_]+=[0-9]+ -->' "$docs_dir" --include='*.md' 2>/dev/null || true)

  echo ""
  if [[ $checked -eq 0 ]]; then
    echo "✗ 未在 $docs_dir 中找到任何 <!-- stats:key=value --> 标注（无可校验项）"
    return 1
  fi
  if [[ $fail -ne 0 ]]; then
    echo "✗ 共 $checked 处标注，${fail} 处不一致 —— 文档数字与代码事实漂移，请按实测值更新文档"
    return 1
  fi
  echo "✓ 共 $checked 处统计标注全部与实测一致"
  return 0
}

# ------------------------------------------------------------
# 入口
# ------------------------------------------------------------
case "${1:-}" in
  --help|-h)
    sed -n '2,24p' "$0"
    ;;
  --check)
    collect > "$STATS_FILE"
    check_docs "${2:-$ROOT/docs}"
    ;;
  *)
    collect
    ;;
esac
