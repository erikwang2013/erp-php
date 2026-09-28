#!/usr/bin/env bash
# ============================================================
# 部署密钥生成（CHANGE_ME_ 占位替换 + 密钥长度自愈）— scripts/gen-env-keys.sh
# ------------------------------------------------------------
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
#
# 用途：把 env 文件中 CHANGE_ME_* 占位值、以及**仍等于 .env.example 公开值**的残留
#       secret 替换为强随机值，并自愈加密密钥长度。
#   用法: bash scripts/gen-env-keys.sh <env文件路径>   （如 bash scripts/gen-env-keys.sh .env）
#   幂等: 无占位值、无模板残留且密钥长度正确时不做任何改动（可重复运行）；替换后权限收紧为 600。
#   配套: app/functions.php 的 assert_env_not_placeholder() 会拒绝占位启动，
#         部署前必须完成本脚本替换。
# ============================================================

set -euo pipefail

ENV_FILE="${1:-}"
if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
  echo "用法: bash scripts/gen-env-keys.sh <env文件路径>" >&2
  exit 1
fi

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT

# 模板对照文件：识别「值仍是 .env.example 公开值」的残留行
EXAMPLE="$(cd "$(dirname "$0")/.." && pwd)/.env.example"
[[ -f "$EXAMPLE" ]] || EXAMPLE=""

count=0
root_pw=""
shopt -s nocasematch
while IFS= read -r line; do
  # 注释行/空行不参与（键名形如 "# ENCRYPTABLE_KEY" 不会匹配）
  [[ "$line" =~ ^[A-Za-z_][A-Za-z0-9_]*= ]] || { echo "$line"; continue; }
  key="${line%%=*}"
  value="${line#*=}"

  # 模板残留判定：与 .env.example 同名行逐字相同 ⇒ 该值是公开仓库里的值，必须换掉。
  # 键名必须像机密：否则 DB_HOST=127.0.0.1 / DB_DATABASE=erp 这类与模板同形的非机密行
  # 会被换成随机串（实测 .env.example 里确有这些同形行）。空值不参与（REDIS_PASSWORD=）。
  is_template=0
  if [[ -n "$EXAMPLE" && -n "$value" && "$key" =~ (KEY|SALT|SECRET|PASSWORD)$ ]] \
     && grep -qxF "$line" "$EXAMPLE"; then
    is_template=1
  fi

  # 加密密钥：AES-256 硬校验 32 字节（EncryptionService.php:30 与 vendor encryptable
  # Encrypter.php:71 均校验 strlen===32）→ 必须 hex 16（32 字符），hex 32 会在
  # 首次访问加密字段时抛 MissingEncryptionKeyException。旧脚本产物（hex-32）一并自愈。
  if [[ "$key" == "ENCRYPTION_KEY" || "$key" == "ENCRYPTABLE_KEY" ]]; then
    if [[ -z "$value" || ${#value} -ne 32 || "$is_template" -eq 1 ]]; then
      echo "$key=$(openssl rand -hex 16)"
      count=$((count + 1))
    else
      echo "$line"
    fi
    continue
  fi

  # 占位判定与 app/functions.php:142 的 /(change[-_]me|xxx)/i 一致（本脚本另加「模板残留」一条）
  if [[ "$is_template" -eq 1 || "$line" =~ ^[A-Za-z_][A-Za-z0-9_]*=.*(change[-_]me|xxx) ]]; then
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

if [[ "$count" -eq 0 ]]; then
  echo "无占位值、密钥长度均正确，未做改动: $ENV_FILE"
  exit 0
fi

mv "$TMP" "$ENV_FILE"
chmod 600 "$ENV_FILE"
echo "已替换 ${count} 个键，文件权限已收紧为 600: $ENV_FILE"
