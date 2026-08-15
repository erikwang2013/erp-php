#!/usr/bin/env bash
# ============================================================
# 数据库备份校验脚本（恢复演练 / Backup Validator）
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 功能: 对指定备份文件（或最新备份）执行"恢复校验"：
#   1. gzip -t 校验压缩包完整性；
#   2. 解压备份 → 恢复到临时校验库 VALIDATE_DB（绝不写入生产库 DB_DATABASE）；
#   3. 比对表数量：备份内 CREATE TABLE 数 / 恢复库实际表数 / mysqldump --no-data 重导出结构表数，三者一致；
#   4. 抽样 COUNT 关键表行数（KEY_TABLES，默认 erik_admin_user,erik_product）；
#   5. 输出校验报告 → 清理临时资源（校验库 + 临时文件）。
#
# 用法:
#   ./backup-validator.sh                      # 自动取最新备份
#   ./backup-validator.sh <备份文件.sql.gz>     # 校验指定备份
#   ./backup-validator.sh --dry-run            # 静态自检（仅检查工具/配置，不连数据库）
#   ./backup-validator.sh --help               # 显示帮助
#
# 环境变量（未设置时尝试从项目根 .env 读取 DB_* 配置）:
#   DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
#   VALIDATE_DB  临时校验库名（默认 erik_backup_validate，禁止等于 DB_DATABASE）
#   KEY_TABLES   抽样行数校验的关键表，逗号分隔
#   VALIDATE_KEEP 设为 1 时校验通过后保留校验库与临时文件（便于排查，默认清理）
#
# 失败（工具缺失 / 凭据错误 / 校验不通过）时以非零状态退出，可接入 CI/定时任务。
# ============================================================

set -euo pipefail

# ------------------------------------------------------------
# 常量与项目路径
# ------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "$PROJECT_ROOT"

ENV_FILE="${ENV_FILE:-${PROJECT_ROOT}/.env}"

# 临时资源（trap 中清理）
TMP_SQL=""
MYSQL_STDERR=""

# ------------------------------------------------------------
# 从 .env 读取 DB_* 配置（仅当对应环境变量未设置时）
# .env 为 PHP 风格 KEY=VALUE，此处仅提取所需键，避免 source 整文件引入风险。
# ------------------------------------------------------------
load_env() {
    [ -f "$ENV_FILE" ] || return 0
    local key value
    while IFS='=' read -r key value; do
        key="${key%%[[:space:]]*}"
        case "$key" in
            DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD|VALIDATE_DB|KEY_TABLES)
                if [ -z "${!key:-}" ]; then
                    value="${value%%[[:space:]]*}"
                    value="${value#\"}"; value="${value%\"}"
                    value="${value#\'}"; value="${value%\'}"
                    export "$key=$value"
                fi
                ;;
        esac
    done < <(grep -E '^[[:space:]]*(DB_|VALIDATE_DB|KEY_TABLES)=' "$ENV_FILE" 2>/dev/null || true)
}

# ------------------------------------------------------------
# 参数与默认配置
# ------------------------------------------------------------
load_env

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-open_admin}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
VALIDATE_DB="${VALIDATE_DB:-erik_backup_validate}"
KEY_TABLES="${KEY_TABLES:-erik_admin_user,erik_product}"
BACKUP_DIR="${BACKUP_DIR:-database/backup}"
VALIDATE_KEEP="${VALIDATE_KEEP:-0}"

# 安全护栏 1: 校验库必须是独立临时 schema，绝不能等于生产库
if [ "$VALIDATE_DB" = "$DB_DATABASE" ]; then
    echo "错误: VALIDATE_DB（校验库）不能等于 DB_DATABASE（生产库）" >&2
    echo "      请设置独立的临时校验库，例如: VALIDATE_DB=erik_backup_validate" >&2
    exit 1
fi

# 安全护栏 2: 库名/表名只允许 [a-zA-Z0-9_]，防 SQL 注入
if ! printf '%s' "$VALIDATE_DB" | grep -qE '^[a-zA-Z0-9_]+$'; then
    echo "错误: VALIDATE_DB 含非法字符（仅允许字母/数字/下划线）: $VALIDATE_DB" >&2
    exit 1
fi

# ------------------------------------------------------------
# 工具函数
# ------------------------------------------------------------
usage() {
    sed -n '2,26p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

mysql_cmd() { # mysql 客户端（凭据走 MYSQL_PWD 环境变量，避免命令行泄露密码）
    MYSQL_PWD="$DB_PASSWORD" mysql \
        -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
        --default-character-set=utf8mb4 --batch --skip-column-names "$@"
}

mysqldump_cmd() {
    MYSQL_PWD="$DB_PASSWORD" mysqldump \
        -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
        --default-character-set=utf8mb4 "$@"
}

check_tools() {
    local missing=0 tool
    for tool in mysql mysqldump gunzip gzip; do
        if ! command -v "$tool" >/dev/null 2>&1; then
            echo "错误: 缺少必需命令: $tool" >&2
            missing=1
        fi
    done
    [ "$missing" -eq 0 ]
}

cleanup() { # 清理临时校验库与临时文件（trap 触发）
    local rc=$?
    if [ "${VALIDATE_KEEP:-0}" != "1" ]; then
        if [ -n "${VALIDATE_DB:-}" ] && command -v mysql >/dev/null 2>&1 \
           && [ "${DB_DATABASE:-}" != "${VALIDATE_DB:-}" ]; then
            mysql_cmd -e "DROP DATABASE IF EXISTS \`${VALIDATE_DB}\`" >/dev/null 2>&1 || true
        fi
        [ -n "$TMP_SQL" ] && rm -f "$TMP_SQL" 2>/dev/null || true
        [ -n "$MYSQL_STDERR" ] && rm -f "$MYSQL_STDERR" 2>/dev/null || true
    else
        [ -n "$TMP_SQL" ] && echo "[提示] VALIDATE_KEEP=1，保留临时文件: $TMP_SQL" || true
    fi
    exit "$rc"
}
trap cleanup EXIT

# ------------------------------------------------------------
# 模式 1: --help / --dry-run
# ------------------------------------------------------------
case "${1:-}" in
    --help|-h)
        usage 0
        ;;
    --dry-run)
        echo "== 备份校验脚本静态自检 =="
        check_tools || { echo "自检失败: 存在缺失工具" >&2; exit 1; }
        echo "  [OK] 必需命令齐全: mysql mysqldump gunzip gzip"
        [ -d "$BACKUP_DIR" ] && echo "  [OK] 备份目录存在: $BACKUP_DIR" \
                             || echo "  [WARN] 备份目录不存在: $BACKUP_DIR"
        [ -f "$ENV_FILE" ] && echo "  [OK] 读取配置文件: $ENV_FILE" \
                            || echo "  [WARN] 未找到 .env，将使用环境变量/默认值"
        echo "  配置: DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_DATABASE=$DB_DATABASE"
        echo "         DB_USERNAME=$DB_USERNAME VALIDATE_DB=$VALIDATE_DB"
        echo "         KEY_TABLES=$KEY_TABLES"
        if ls "$BACKUP_DIR"/*.sql.gz >/dev/null 2>&1; then
            echo "  [OK] 发现备份文件: $(ls -t "$BACKUP_DIR"/*.sql.gz | head -1)"
        else
            echo "  [WARN] 未发现备份文件: $BACKUP_DIR/*.sql.gz"
        fi
        echo "自检完成（未连接数据库）。"
        exit 0
        ;;
esac

# ------------------------------------------------------------
# 模式 2: 校验备份
# ------------------------------------------------------------
check_tools || { echo "错误: 环境缺少必需工具，无法校验" >&2; exit 1; }

# 定位备份文件：显式参数 或 最新备份
if [ $# -ge 1 ]; then
    BACKUP_FILE="$1"
    [ $# -gt 1 ] && { echo "错误: 参数过多，用法见 --help" >&2; usage 1; }
else
    BACKUP_FILE="$(ls -t "$BACKUP_DIR"/backup_*.sql.gz "$BACKUP_DIR"/auto_*.sql.gz 2>/dev/null | head -1 || true)"
    if [ -z "$BACKUP_FILE" ]; then
        echo "错误: 备份目录中未找到 *.sql.gz，请指定备份文件" >&2
        exit 1
    fi
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "错误: 备份文件不存在 — $BACKUP_FILE" >&2
    exit 1
fi

echo "=========================================="
echo "  数据库备份校验（恢复演练）"
echo "  备份文件: $BACKUP_FILE"
echo "  校验库  : $VALIDATE_DB（临时，不会触碰 $DB_DATABASE）"
echo "=========================================="

FAILED=0   # 0=通过 1=失败

# --- 1. 压缩包完整性 ---
if gzip -t "$BACKUP_FILE" 2>/dev/null; then
    echo "[1/5] 压缩完整性: OK"
else
    echo "[1/5] 压缩完整性: FAILED（gzip -t 报错，备份可能损坏）" >&2
    exit 1
fi

# --- 2. 解压到临时文件并统计结构 ---
TMP_SQL="$(mktemp /tmp/backup_validate_XXXXXX.sql)"
MYSQL_STDERR="$(mktemp /tmp/backup_validate_XXXXXX.err)"
gunzip -c "$BACKUP_FILE" > "$TMP_SQL"
EXPECTED_TABLES="$(grep -c '^CREATE TABLE' "$TMP_SQL" || true)"
BACKUP_SIZE="$(du -h "$BACKUP_FILE" | cut -f1)"
echo "[2/5] 解压完成: 大小=$BACKUP_SIZE, 备份内 CREATE TABLE 数=$EXPECTED_TABLES"

if [ "$EXPECTED_TABLES" -eq 0 ]; then
    echo "错误: 备份中未解析到任何 CREATE TABLE，备份可能无效或格式异常" >&2
    exit 1
fi

# --- 3. 重建临时校验库并导入 ---
echo "[3/5] 重建校验库 $VALIDATE_DB 并导入备份..."
if ! mysql_cmd -e "DROP DATABASE IF EXISTS \`${VALIDATE_DB}\`; CREATE DATABASE \`${VALIDATE_DB}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        >>"$MYSQL_STDERR" 2>&1; then
    echo "错误: 无法创建校验库 $VALIDATE_DB（请检查 DB 凭据与权限）" >&2
    cat "$MYSQL_STDERR" >&2 || true
    exit 1
fi

IMPORT_START=$(date +%s)
if ! mysql_cmd "$VALIDATE_DB" < "$TMP_SQL" >>"$MYSQL_STDERR" 2>&1; then
    echo "错误: 备份恢复到校验库失败（备份可能损坏或与当前 MySQL 版本不兼容）" >&2
    cat "$MYSQL_STDERR" >&2 || true
    exit 1
fi
IMPORT_ELAPSED=$(( $(date +%s) - IMPORT_START ))

# --- 4. 表数量三方比对 + 关键表行数 ---
ACTUAL_TABLES="$(mysql_cmd -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${VALIDATE_DB}' AND table_type='BASE TABLE';" 2>>"$MYSQL_STDERR" || true)"
STRUCT_TABLES="$(mysqldump_cmd --no-data --skip-comments "$VALIDATE_DB" 2>>"$MYSQL_STDERR" | grep -c '^CREATE TABLE' || true)"

echo "[4/5] 表数量比对: 备份=$EXPECTED_TABLES, 恢复库=$ACTUAL_TABLES, 结构重导出=$STRUCT_TABLES（导入耗时 ${IMPORT_ELAPSED}s）"
if [ -z "$ACTUAL_TABLES" ] || [ -z "$STRUCT_TABLES" ]; then
    echo "错误: 无法读取恢复库统计信息（请检查 DB 凭据与权限）" >&2
    cat "$MYSQL_STDERR" >&2 || true
    exit 1
fi
if [ "$EXPECTED_TABLES" -ne "$ACTUAL_TABLES" ] || [ "$EXPECTED_TABLES" -ne "$STRUCT_TABLES" ]; then
    echo "  → 表数量不一致: 校验失败" >&2
    FAILED=1
else
    echo "  → 表数量一致: OK"
fi

echo "[5/5] 关键表行数抽样（表: 行数）:"
while IFS=',' read -r t; do
    [ -z "$t" ] && continue
    t="${t//[[:space:]]/}"
    # 表名白名单校验
    if ! printf '%s' "$t" | grep -qE '^[a-zA-Z0-9_]+$'; then
        echo "  - $t : 非法表名，跳过（仅允许字母/数字/下划线）" >&2
        FAILED=1
        continue
    fi
    ROW_CNT="$(mysql_cmd -e "SELECT COUNT(*) FROM \`${VALIDATE_DB}\`.\`${t}\`;" 2>>"$MYSQL_STDERR" || true)"
    if [ -z "$ROW_CNT" ]; then
        echo "  - $t : 读取失败（表缺失或查询错误）→ 校验失败" >&2
        FAILED=1
    else
        echo "  - $t : $ROW_CNT"
    fi
done <<< "$KEY_TABLES"

# --- 5. 输出报告并退出 ---
echo "=========================================="
if [ "$FAILED" -eq 0 ]; then
    echo "  校验结果: PASS ✅"
    echo "  备份文件: $BACKUP_FILE 可正常恢复（表数一致，关键表可查询）"
else
    echo "  校验结果: FAIL ❌（详见上方错误信息）" >&2
fi
echo "=========================================="
exit "$FAILED"
