#!/bin/bash
# NetServa Database Restore Script
# Usage: ./scripts/restore-db.sh [backup-file]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"
DB_FILE="$PROJECT_ROOT/database/database.sqlite"

# If no backup file specified, show available backups and prompt
if [ -z "$1" ]; then
    echo "📁 Available backups:"
    echo ""
    ls -lht "$BACKUP_DIR"/database.sqlite.*.backup | head -10
    echo ""
    echo "Usage: ./scripts/restore-db.sh <backup-file>"
    echo "Example: ./scripts/restore-db.sh backups/database.sqlite.20251105_143000_manual.backup"
    exit 1
fi

BACKUP_FILE="$1"

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Error: Backup file not found: $BACKUP_FILE"
    exit 1
fi

# Show what will be restored
echo "📊 Backup file contents:"
sqlite3 "$BACKUP_FILE" "
    SELECT 'Venues: ' || COUNT(*) FROM fleet_venues UNION ALL
    SELECT 'Vsites: ' || COUNT(*) FROM fleet_vsites UNION ALL
    SELECT 'Vnodes: ' || COUNT(*) FROM fleet_vnodes UNION ALL
    SELECT 'Vhosts: ' || COUNT(*) FROM fleet_vhosts UNION ALL
    SELECT 'DNS Zones: ' || COUNT(*) FROM dns_zones UNION ALL
    SELECT 'DNS Records: ' || COUNT(*) FROM dns_records UNION ALL
    SELECT 'Secrets: ' || COUNT(*) FROM secrets;
"

echo ""
echo "⚠️  WARNING: This will REPLACE the current database!"
echo "   Current DB: $DB_FILE"
echo "   Restore from: $BACKUP_FILE"
echo ""
read -p "Are you sure? (type 'yes' to confirm): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "❌ Restore cancelled"
    exit 1
fi

# Create a safety backup of current database before restoring
SAFETY_BACKUP="$BACKUP_DIR/database.sqlite.$(date +%Y%m%d_%H%M%S)_before_restore.backup"
if [ -f "$DB_FILE" ]; then
    echo "📦 Creating safety backup of current database..."
    cp "$DB_FILE" "$SAFETY_BACKUP"
    echo "   Safety backup: $SAFETY_BACKUP"
fi

# Restore the backup
echo "🔄 Restoring database..."
cp "$BACKUP_FILE" "$DB_FILE"

# Verify restore
if [ -f "$DB_FILE" ]; then
    echo "✅ Database restored successfully!"
    echo ""
    echo "📊 Restored database contents:"
    sqlite3 "$DB_FILE" "
        SELECT 'Venues: ' || COUNT(*) FROM fleet_venues UNION ALL
        SELECT 'Vsites: ' || COUNT(*) FROM fleet_vsites UNION ALL
        SELECT 'Vnodes: ' || COUNT(*) FROM fleet_vnodes UNION ALL
        SELECT 'Vhosts: ' || COUNT(*) FROM fleet_vhosts UNION ALL
        SELECT 'DNS Zones: ' || COUNT(*) FROM dns_zones UNION ALL
        SELECT 'DNS Records: ' || COUNT(*) FROM dns_records UNION ALL
        SELECT 'Secrets: ' || COUNT(*) FROM secrets;
    "
else
    echo "❌ Error: Restore failed"
    exit 1
fi
