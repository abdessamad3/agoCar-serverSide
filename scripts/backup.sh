#!/usr/bin/env bash
# =============================================================================
# AGOCAR — MySQL/MariaDB Backup Script
# Retention:  daily × 7  |  weekly × 4  |  monthly × 12
#
# Setup (cron, run as the app user):
#   0 2 * * * /var/www/autoloc/scripts/backup.sh >> /var/log/autoloc-backup.log 2>&1
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration — edit these or export them from the environment
# ---------------------------------------------------------------------------
DB_HOST="${BACKUP_DB_HOST:-127.0.0.1}"
DB_PORT="${BACKUP_DB_PORT:-3306}"
DB_NAME="${BACKUP_DB_NAME:-autoloc}"
DB_USER="${BACKUP_DB_USER:-root}"
DB_PASS="${BACKUP_DB_PASS:-}"          # set via env, not hardcoded in prod

BACKUP_DIR="${BACKUP_DIR:-/var/backups/autoloc}"
KEEP_DAILY=7
KEEP_WEEKLY=4
KEEP_MONTHLY=12

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DAY_OF_WEEK=$(date +"%u")    # 1=Mon … 7=Sun
DAY_OF_MONTH=$(date +"%d")   # 01–31

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

dump_db() {
    local dest="$1"
    MYSQL_PWD="$DB_PASS" mysqldump \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USER" \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-table \
        "$DB_NAME" | gzip -9 > "$dest"
}

prune_old() {
    local dir="$1"
    local keep="$2"
    # delete oldest files beyond the keep count
    ls -1t "$dir"/*.sql.gz 2>/dev/null | tail -n +"$((keep + 1))" | xargs -r rm -f
}

# ---------------------------------------------------------------------------
# Create directory structure
# ---------------------------------------------------------------------------
mkdir -p "$BACKUP_DIR"/{daily,weekly,monthly}

# ---------------------------------------------------------------------------
# Daily backup — always
# ---------------------------------------------------------------------------
DAILY_FILE="$BACKUP_DIR/daily/${DB_NAME}_daily_${TIMESTAMP}.sql.gz"
log "Starting daily backup → $DAILY_FILE"
dump_db "$DAILY_FILE"
log "Daily backup complete ($(du -sh "$DAILY_FILE" | cut -f1))"
prune_old "$BACKUP_DIR/daily" "$KEEP_DAILY"
log "Daily pruned to last $KEEP_DAILY"

# ---------------------------------------------------------------------------
# Weekly backup — every Sunday (day 7)
# ---------------------------------------------------------------------------
if [ "$DAY_OF_WEEK" = "7" ]; then
    WEEKLY_FILE="$BACKUP_DIR/weekly/${DB_NAME}_weekly_${TIMESTAMP}.sql.gz"
    log "Sunday — copying to weekly → $WEEKLY_FILE"
    cp "$DAILY_FILE" "$WEEKLY_FILE"
    prune_old "$BACKUP_DIR/weekly" "$KEEP_WEEKLY"
    log "Weekly pruned to last $KEEP_WEEKLY"
fi

# ---------------------------------------------------------------------------
# Monthly backup — on the 1st of each month
# ---------------------------------------------------------------------------
if [ "$DAY_OF_MONTH" = "01" ]; then
    MONTHLY_FILE="$BACKUP_DIR/monthly/${DB_NAME}_monthly_${TIMESTAMP}.sql.gz"
    log "1st of month — copying to monthly → $MONTHLY_FILE"
    cp "$DAILY_FILE" "$MONTHLY_FILE"
    prune_old "$BACKUP_DIR/monthly" "$KEEP_MONTHLY"
    log "Monthly pruned to last $KEEP_MONTHLY"
fi

log "Backup finished."

# ---------------------------------------------------------------------------
# RESTORE PROCEDURE (reference, not executed here)
# ---------------------------------------------------------------------------
# 1. Find the backup to restore:
#      ls -lh /var/backups/autoloc/daily/
#
# 2. Restore (replace DB_NAME, DB_USER, DB_PASS):
#      gunzip -c /var/backups/autoloc/daily/autoloc_daily_<TIMESTAMP>.sql.gz \
#        | mysql --host=127.0.0.1 --user=<DB_USER> --password=<DB_PASS> autoloc
#
# 3. Verify row counts:
#      mysql -u <DB_USER> -p autoloc -e "SELECT TABLE_NAME, TABLE_ROWS
#        FROM information_schema.TABLES WHERE TABLE_SCHEMA='autoloc';"
#
# 4. Restart Symfony:
#      php bin/console cache:clear --env=prod
# ---------------------------------------------------------------------------
