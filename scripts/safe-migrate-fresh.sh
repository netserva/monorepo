#!/bin/bash
# Safe migrate:fresh with automatic backup
# This prevents accidental data loss by backing up to ~/.netserva/backups/

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "WARNING: migrate:fresh will DELETE ALL DATA in the database!"
echo ""

# Show current data counts
echo "Current database contents:"
sqlite3 database/database.sqlite "
    SELECT 'VPass: ' || COUNT(*) FROM vpass;
    SELECT 'SSH Hosts: ' || COUNT(*) FROM ssh_hosts;
    SELECT 'VSites: ' || COUNT(*) FROM fleet_vsites;
    SELECT 'VNodes: ' || COUNT(*) FROM fleet_vnodes;
    SELECT 'VHosts: ' || COUNT(*) FROM fleet_vhosts;
    SELECT 'DNS Zones: ' || COUNT(*) FROM dns_zones;
    SELECT 'DNS Records: ' || COUNT(*) FROM dns_records;
" 2>/dev/null || echo "Unable to check counts (some tables may not exist)"

echo ""
echo "This will:"
echo "  1. Backup database to ~/.netserva/backups/db/"
echo "  2. Backup VPass to ~/.netserva/backups/pw/vpass.csv"
echo "  3. Drop all tables and run all migrations"
echo ""
read -p "Type 'DELETE ALL DATA' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE ALL DATA" ]; then
    echo "Operation cancelled - no changes made"
    exit 1
fi

# Create backup before proceeding
echo ""
echo "Creating automatic backup..."
"$SCRIPT_DIR/backup-db.sh" "before-migrate-fresh"

# Run migrate:fresh
echo ""
echo "Running migrate:fresh..."
php artisan migrate:fresh

echo ""
echo "Complete! Database reset with fresh migrations."
echo ""
echo "Restore from backup:"
echo "  cp ~/.netserva/backups/db/database_*_before-migrate-fresh.sqlite database/database.sqlite"
echo ""
echo "Emergency recovery (APP_KEY lost):"
echo "  php artisan addpw --import=~/.netserva/backups/pw/vpass.csv"
