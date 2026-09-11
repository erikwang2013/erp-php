#!/usr/bin/env bash
# ============================================================
# 文档统计自动化（D3/D5）— scripts/doc-stats.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：统计项目代码规模的「真实数字」，供 docs/ 引用与 CI 校验。
#   - 默认模式：输出稳定 key=value 行（每行一个统计键），供文档与 CI 解析；
#   - --check 模式：生成当前统计后，扫描 docs/**/*.md 与根 README.md 中形如
#     <!-- stats:key=value --> 的注释标注，逐键比对，漂移即非零退出。
#   - --fix 模式：同样先采集，再就地把标注改写为实测值，最后复验（漂移自愈）。
#
# 口径：全部统计键都是「源码静态计数」，不执行 phpunit、不连数据库、不读环境变量，
#   因此本地与 CI 任何机器量出的值都相同（原因见 collect() 中 tests/assertions 注释）。
#
# 用法：
#   bash scripts/doc-stats.sh                 # 输出 key=value 统计
#   bash scripts/doc-stats.sh --check [docs]  # 校验 docs 标注与实测一致（默认 docs/）
#   bash scripts/doc-stats.sh --fix [docs]    # 就地改写 docs 标注为实测值后复验
#   bash scripts/doc-stats.sh --help
#
# CI 集成：.github/workflows/ci.yml 的 docs 作业执行
#   bash scripts/doc-stats.sh --check
# 该作业无需 PHP/vendor/数据库（全部键为静态计数，仅用 find/grep/wc）。
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

  # 测试方法数 / 断言调用点数：源码静态计数（不执行 phpunit）。
  # 为什么不用 `phpunit --no-coverage` 的实测值（2026-09-12 改）：
  #   实测值是环境函数而非代码事实 —— 同一份代码在本机与 CI 会量出不同的 (tests, assertions)：
  #     * 集成用例由 TEST_DB_*/TEST_REDIS_* 环境变量开关（tests/Integration/IntegrationTestCase.php），
  #       未配置即 markTestSkipped；跳过用例计入 tests 总数、断言数为 0；
  #     * 扩展集/PHP 补丁版本不同（本机无 gmp、CI docs 作业装 gmp+bcmath）会改变执行路径；
  #     * 数据提供器（#[DataProvider]）在运行时按行展开，静态只有 1 个方法。
  #   于是文档里写死哪个数都必然有一边红。静态计数只取决于源码，任何环境（含
  #   无 vendor、无数据库的 CI docs 作业）都得出同一个数。
  # 代价（口径变化，文档须同步措辞）：计的是「测试方法数 / assert* 调用点」而非
  #   「本次执行的方法数 / 断言数」——提供器展开不计（全仓仅 tests/Integration/B5TenantTest.php
  #   一处，1 方法 12 行），循环体内的断言只按 1 个调用点计，`->assertXxx()` 项目自有
  #   断言辅助方法（如 assertBcEquals/assertServiceThrows）同样计入调用点。
  local tests assertions attributed
  tests="$(grep -rhoE 'public[[:space:]]+function[[:space:]]+test[A-Za-z0-9_]*[[:space:]]*\(' tests --include='*Test.php' | wc -l | tr -d ' ')"
  # PHPUnit 11+ 的 #[Test] 属性写法：方法名不必以 test 开头，按属性数补计（两者不重叠）
  attributed="$(grep -rhoE '#\[Test\]' tests --include='*Test.php' | wc -l | tr -d ' ')"
  echo "tests=$(( ${tests:-0} + ${attributed:-0} ))"
  assertions="$(grep -rhoE '(->|::)assert[A-Za-z0-9_]*[[:space:]]*\(' tests --include='*Test.php' | wc -l | tr -d ' ')"
  echo "assertions=${assertions:-0}"
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
  echo "校验范围: $docs_dir + 根目录 README.md"
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
  done < <(grep -rnoE '<!-- stats:[a-zA-Z0-9_]+=[0-9]+ -->' "$docs_dir" "$ROOT/README.md" --include='*.md' 2>/dev/null || true)

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
# fix_docs —— 把 docs/**/*.md 中的 <!-- stats:key=value --> 标注改写为实测值。
# 只对齐文档中「已出现」的键（不为未使用的键无中生有）；实测不可用
# （unknown/空，如无 vendor 时 phpunit 解析失败）时保留原值，宁可不改也不写坏数字。
# 改完立即交由 check_docs 复验，因此返回码即最终一致性结论。
# ------------------------------------------------------------
fix_docs() {
  local docs_dir="${1:-$ROOT/docs}"
  local keys expr script f key val

  if [[ ! -d "$docs_dir" ]]; then
    echo "✗ 文档目录不存在: $docs_dir"
    return 1
  fi

  echo "== 文档统计对齐 =="
  echo "统计来源: bash scripts/doc-stats.sh（实时采集）"
  echo "对齐范围: $docs_dir + 根目录 README.md"
  echo ""

  keys="$(grep -rhoE '<!-- stats:[a-zA-Z0-9_]+=' "$docs_dir" "$ROOT/README.md" --include='*.md' 2>/dev/null \
    | sed -E 's/.*stats:([a-zA-Z0-9_]+)=/\1/' | sort -u || true)"
  if [[ -z "$keys" ]]; then
    echo "✗ 未找到任何 <!-- stats:key=value --> 标注（无可对齐项）"
    return 1
  fi

  # 逐键生成 sed 表达式。标注格式固定为 `<!-- stats:key=value -->`（单空格），
  # 按完整 key + = 精确匹配，故 controllers 不会误伤 controllers_admin。
  expr=""
  while IFS= read -r key; do
    [[ -z "$key" ]] && continue
    val="$(grep -m1 "^${key}=" "$STATS_FILE" | cut -d= -f2- || true)"
    if [[ -z "$val" || "$val" == "unknown" ]]; then
      echo "! stats:${key} 实测不可用（${val:-空}），保留原值跳过"
      continue
    fi
    expr+="s|<!-- stats:${key}=[0-9]+ -->|<!-- stats:${key}=${val} -->|g"$'\n'
  done <<< "$keys"

  if [[ -z "$expr" ]]; then
    echo "✗ 无可用实测值，未做任何修改"
    return 1
  fi

  script="$(mktemp)"
  printf '%s' "$expr" > "$script"
  while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    if grep -qE '<!-- stats:[a-zA-Z0-9_]+=[0-9]+ -->' "$f"; then
      sed -i -E -f "$script" "$f" && echo "  ✓ $f"
    fi
  done < <(grep -rlE '<!-- stats:[a-zA-Z0-9_]+=' "$docs_dir" "$ROOT/README.md" --include='*.md' 2>/dev/null || true)
  rm -f "$script"
  echo ""

  check_docs "$docs_dir"
}

# ------------------------------------------------------------
# 入口
# ------------------------------------------------------------
case "${1:-}" in
  --help|-h)
    sed -n '2,25p' "$0"
    ;;
  --check)
    collect > "$STATS_FILE"
    check_docs "${2:-$ROOT/docs}"
    ;;
  --fix)
    collect > "$STATS_FILE"
    fix_docs "${2:-$ROOT/docs}"
    ;;
  *)
    collect
    ;;
esac
