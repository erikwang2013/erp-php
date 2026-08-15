#!/bin/bash
# Database Migration Rollback Script
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# Usage: ./rollback.sh [migration_name]
#   ./rollback.sh 2026_08_04_000024_qms_tables  # rollback specific migration
#   ./rollback.sh --last                          # rollback most recent migration

set -e

MYSQL_CMD="mysql -h${DB_HOST:-127.0.0.1} -P${DB_PORT:-3306} -u${DB_USERNAME:-root}"
[ -n "${DB_PASSWORD}" ] && MYSQL_CMD="$MYSQL_CMD -p${DB_PASSWORD}"
MYSQL_CMD="$MYSQL_CMD ${DB_DATABASE:-open_admin}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# 回滚文件与迁移文件同目录存放（*_rollback.sql），与 database/migrations/ 下的迁移文件一一对应
ROLLBACK_DIR="$SCRIPT_DIR"

if [ "$1" = "--last" ]; then
    MIGRATION=$(ls "$ROLLBACK_DIR"/*_rollback.sql 2>/dev/null | sort -r | head -1)
    if [ -z "$MIGRATION" ]; then
        echo "No rollback files found in $ROLLBACK_DIR"
        echo "Create a _rollback.sql file to enable rollback for a migration."
        exit 1
    fi
elif [ -n "$1" ]; then
    ROLLBACK_FILE="$ROLLBACK_DIR/${1}_rollback.sql"
    if [ ! -f "$ROLLBACK_FILE" ]; then
        echo "Rollback file not found: $ROLLBACK_FILE"
        exit 1
    fi
    MIGRATION="$ROLLBACK_FILE"
else
    echo "Usage: ./rollback.sh [migration_name] | --last"
    echo "Available rollback files:"
    ls "$ROLLBACK_DIR"/*_rollback.sql 2>/dev/null || echo "  (none — create _rollback.sql files to enable)"
    exit 1
fi

echo "Executing rollback: $(basename "$MIGRATION")"
$MYSQL_CMD < "$MIGRATION"
echo "Rollback complete."
