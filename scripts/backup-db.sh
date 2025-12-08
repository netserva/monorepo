#!/bin/bash
# NetServa Database & VPass Backup Script
# Usage: ./scripts/backup-db.sh [label] [--quiet]
#
# Backs up to TWO locations:
#   1. ~/.ns/backups/ (project-local, may be wiped by git clean)
#   2. ~/.netserva/backups/ (external, survives everything)

set -e

# Check for --quiet flag
QUIET=false
for arg in "$@"; do
    if [ "$arg" = "--quiet" ]; then
        QUIET=true
    fi
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# External backup location (survives git clean, project deletion)
EXTERNAL_BACKUP_DIR="$HOME/.netserva/backups"
EXTERNAL_DB_DIR="$EXTERNAL_BACKUP_DIR/db"
EXTERNAL_PW_DIR="$EXTERNAL_BACKUP_DIR/pw"

# Local backup location
LOCAL_BACKUP_DIR="$PROJECT_ROOT/storage/app/backups"

DB_FILE="$PROJECT_ROOT/database/database.sqlite"

# Create backup directories
mkdir -p "$EXTERNAL_DB_DIR" "$EXTERNAL_PW_DIR" "$LOCAL_BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +%Y-%m-%d_%H%M%S)

# Optional label from first argument (skip --quiet)
LABEL="manual"
for arg in "$@"; do
    if [ "$arg" != "--quiet" ]; then
        LABEL="$arg"
        break
    fi
done

log() {
    if [ "$QUIET" = false ]; then
        echo "$@"
    fi
}

log "NetServa Backup - $TIMESTAMP"
log "================================"

# --------------------------
# Database Backup
# --------------------------
if [ -f "$DB_FILE" ]; then
    BACKUP_NAME="database_${TIMESTAMP}_${LABEL}.sqlite"

    # Copy to both locations
    cp "$DB_FILE" "$LOCAL_BACKUP_DIR/$BACKUP_NAME"
    cp "$DB_FILE" "$EXTERNAL_DB_DIR/$BACKUP_NAME"

    BACKUP_SIZE=$(du -h "$EXTERNAL_DB_DIR/$BACKUP_NAME" | cut -f1)
    log "Database backed up ($BACKUP_SIZE)"
    log "   Local:    $LOCAL_BACKUP_DIR/$BACKUP_NAME"
    log "   External: $EXTERNAL_DB_DIR/$BACKUP_NAME"

    # Show record counts
    log ""
    log "Record counts:"
    if [ "$QUIET" = false ]; then
        sqlite3 "$DB_FILE" "
            SELECT '   VPass:    ' || COUNT(*) FROM vpass;
            SELECT '   SSH Hosts:' || COUNT(*) FROM ssh_hosts;
            SELECT '   VSites:   ' || COUNT(*) FROM fleet_vsites;
            SELECT '   VNodes:   ' || COUNT(*) FROM fleet_vnodes;
            SELECT '   VHosts:   ' || COUNT(*) FROM fleet_vhosts;
        " 2>/dev/null || log "   (some tables may not exist yet)"
    fi
else
    log "No database file found - skipping database backup"
fi

# --------------------------
# VPass CSV Backup (plain text, recoverable without APP_KEY)
# --------------------------
log ""
VPASS_FILE="$EXTERNAL_PW_DIR/vpass.csv"
VPASS_BAK="$EXTERNAL_PW_DIR/vpass.csv.bak"

# Rotate: current -> .bak (keep one previous version)
if [ -f "$VPASS_FILE" ]; then
    mv "$VPASS_FILE" "$VPASS_BAK"
    log "Previous VPass backup rotated to .bak"
fi

# Export VPass to CSV
cd "$PROJECT_ROOT"
if php artisan shpw --csv > "$VPASS_FILE" 2>/dev/null; then
    VPASS_COUNT=$(wc -l < "$VPASS_FILE")
    VPASS_COUNT=$((VPASS_COUNT - 1))  # Subtract header row
    log "VPass exported: $VPASS_COUNT credentials"
    log "   File: $VPASS_FILE"
    if [ -f "$VPASS_BAK" ]; then
        log "   Backup: $VPASS_BAK"
    fi
else
    log "VPass export failed (table may be empty)"
    # Restore .bak if export failed
    if [ -f "$VPASS_BAK" ]; then
        mv "$VPASS_BAK" "$VPASS_FILE"
    fi
fi

# --------------------------
# JSON Export (database-agnostic, for SQLite<->MySQL migration)
# --------------------------
log ""
if php artisan db:export --name="${TIMESTAMP}_${LABEL}" --quiet 2>/dev/null; then
    log "JSON export created (for cross-database restore)"
else
    log "JSON export skipped"
fi

# --------------------------
# Cleanup old database backups (keep last 20)
# --------------------------
log ""
log "Cleaning up old backups (keeping last 20)..."

for DIR in "$LOCAL_BACKUP_DIR" "$EXTERNAL_DB_DIR"; do
    if [ -d "$DIR" ]; then
        # Find and remove old .sqlite backups (keep last 20)
        ls -t "$DIR"/*.sqlite 2>/dev/null | tail -n +21 | xargs -r rm -f
        # Find and remove old .json exports (keep last 10)
        ls -t "$DIR"/*_export.json 2>/dev/null | tail -n +11 | xargs -r rm -f
    fi
done

# --------------------------
# Summary
# --------------------------
log ""
log "External backups location: $EXTERNAL_BACKUP_DIR"
log "   (This location survives git clean and project deletion)"
log ""
log "Restore commands:"
log "   Same DB:     php artisan db:restore latest"
log "   Cross-DB:    php artisan db:import <file>.json --truncate"
log "   VPass only:  php artisan addpw --import=~/.netserva/backups/pw/vpass.csv"
