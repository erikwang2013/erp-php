#!/usr/bin/env bash
# ============================================================
# 部署密钥生成（CHANGE_ME_ 占位替换）— scripts/gen-env-keys.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：把 env 文件中 CHANGE_ME_* 占位值替换为 openssl rand -hex 32 强随机值。
#   用法: bash scripts/gen-env-keys.sh <env文件路径>   （如 bash scripts/gen-env-keys.sh .env）
#   幂等: 无占位值时不做任何改动（可重复运行）；替换后文件权限收紧为 600。
#   配套: app/functions.php 的 assert_env_not_placeholder() 会拒绝占位启动，
#         部署前必须完成本脚本替换。
# ============================================================

set -euo pipefail

ENV_FILE="${1:-}"
if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
  echo "用法: bash scripts/gen-env-keys.sh <env文件路径>" >&2
  exit 1
fi

# 占位判定与 app/functions.php 拒绝规则一致（KEY= 值含 CHANGE_ME_ 即视为占位；
# 注释行/空行不参与，避免误替换注释中的占位字样）
if ! grep -qE '^[A-Za-z_][A-Za-z0-9_]*=.*CHANGE_ME_' "$ENV_FILE"; then
  echo "无占位值（CHANGE_ME_*），未做改动: $ENV_FILE"
  exit 0
fi

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

count=0
root_pw=""
while IFS= read -r line; do
  if [[ "$line" =~ ^[A-Za-z_][A-Za-z0-9_]*=.*CHANGE_ME_ ]]; then
    key="${line%%=*}"
    if [[ "$key" == "DB_PASSWORD" || "$key" == "MYSQL_ROOT_PASSWORD" ]]; then
      # app 以 root 连接 MySQL，两值必须一致（否则一键启动必现 Access denied）
      if [[ -z "$root_pw" ]]; then root_pw="$(openssl rand -hex 32)"; fi
      echo "$key=$root_pw"
    else
      echo "$key=$(openssl rand -hex 32)"
    fi
    count=$((count + 1))
  else
    echo "$line"
  fi
done < "$ENV_FILE" > "$TMP"

mv "$TMP" "$ENV_FILE"
chmod 600 "$ENV_FILE"
echo "已替换 ${count} 个占位值，文件权限已收紧为 600: $ENV_FILE"
