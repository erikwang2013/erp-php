#!/bin/bash
# Automated Database Backup with Retention
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# Cron: 0 2 * * * /path/to/auto-backup.sh

set -e
BACKUP_DIR="${BACKUP_DIR:-database/backup}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
DB_NAME="${DB_DATABASE:-open_admin}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-root}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/auto_${DB_NAME}_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Starting backup..."
mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
    --single-transaction --routines --triggers --events "$DB_NAME" | gzip > "$BACKUP_FILE"

echo "[$(date)] Backup saved: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"

# Validate backup integrity
if gzip -t "$BACKUP_FILE" 2>/dev/null; then
    echo "[$(date)] Integrity check: OK"
else
    echo "[$(date)] ERROR: Backup integrity check FAILED for $BACKUP_FILE" >&2
    exit 1
fi

# Cleanup old backups
DELETED=$(find "$BACKUP_DIR" -name "auto_${DB_NAME}_*.sql.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
echo "[$(date)] Cleanup: removed $DELETED backups older than ${RETENTION_DAYS} days"
echo "[$(date)] Backup complete."
