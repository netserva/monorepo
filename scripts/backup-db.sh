#!/bin/bash
# NetServa Database Backup Script
# Usage: ./scripts/backup-db.sh [label]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"
DB_FILE="$PROJECT_ROOT/database/database.sqlite"

# Create backups directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Optional label from first argument
LABEL="${1:-manual}"

# Backup filename
BACKUP_FILE="$BACKUP_DIR/database.sqlite.${TIMESTAMP}_${LABEL}.backup"

# Check if database exists
if [ ! -f "$DB_FILE" ]; then
    echo "Error: Database file not found at $DB_FILE"
    exit 1
fi

# Create backup
cp "$DB_FILE" "$BACKUP_FILE"

# Verify backup was created
if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "✅ Backup created successfully:"
    echo "   File: $BACKUP_FILE"
    echo "   Size: $BACKUP_SIZE"

    # Show record counts from backup
    echo ""
    echo "📊 Record counts in backup:"
    sqlite3 "$BACKUP_FILE" "
        SELECT 'Venues: ' || COUNT(*) FROM fleet_venues UNION ALL
        SELECT 'Vsites: ' || COUNT(*) FROM fleet_vsites UNION ALL
        SELECT 'Vnodes: ' || COUNT(*) FROM fleet_vnodes UNION ALL
        SELECT 'Vhosts: ' || COUNT(*) FROM fleet_vhosts UNION ALL
        SELECT 'DNS Zones: ' || COUNT(*) FROM dns_zones UNION ALL
        SELECT 'DNS Records: ' || COUNT(*) FROM dns_records UNION ALL
        SELECT 'Secrets: ' || COUNT(*) FROM secrets;
    "

    # Keep only last 10 backups (cleanup old ones)
    echo ""
    echo "🧹 Cleaning up old backups (keeping last 10)..."
    ls -t "$BACKUP_DIR"/database.sqlite.*.backup | tail -n +11 | xargs -r rm

    echo ""
    echo "📁 Available backups:"
    ls -lh "$BACKUP_DIR"/database.sqlite.*.backup | tail -5

    exit 0
else
    echo "❌ Error: Backup failed"
    exit 1
fi
