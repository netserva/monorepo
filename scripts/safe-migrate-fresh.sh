#!/bin/bash
# Safe migrate:fresh with automatic backup
# This prevents accidental data loss

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "⚠️  WARNING: migrate:fresh will DELETE ALL DATA in the database!"
echo ""

# Show current data counts
echo "📊 Current database contents:"
php artisan tinker --execute="
echo 'Venues: ' . \Ns\Platform\Models\Venue::count() . PHP_EOL;
echo 'Vsites: ' . \Ns\Platform\Models\Vsite::count() . PHP_EOL;
echo 'Vnodes: ' . \Ns\Platform\Models\Vnode::count() . PHP_EOL;
echo 'Vhosts: ' . \Ns\Platform\Models\Vhost::count() . PHP_EOL;
echo 'DNS Zones: ' . \Ns\Dns\Models\DnsZone::count() . PHP_EOL;
echo 'Secrets: ' . \Ns\Secrets\Models\Secret::count() . PHP_EOL;
" 2>/dev/null || echo "Unable to check counts"

echo ""
echo "This will:"
echo "  1. Create an automatic backup"
echo "  2. Drop all tables"
echo "  3. Run all migrations"
echo "  4. Optionally run seeders"
echo ""
read -p "Type 'DELETE ALL DATA' to confirm: " CONFIRM

if [ "$CONFIRM" != "DELETE ALL DATA" ]; then
    echo "❌ Operation cancelled - no changes made"
    exit 1
fi

# Create backup before proceeding
echo ""
echo "📦 Creating automatic backup..."
"$SCRIPT_DIR/backup-db.sh" "before-migrate-fresh"

# Ask about seeding
echo ""
read -p "Run seeders after migration? (y/N): " RUN_SEEDERS

# Run migrate:fresh
echo ""
echo "🔄 Running migrate:fresh..."
php artisan migrate:fresh

# Run seeders if requested
if [[ "$RUN_SEEDERS" =~ ^[Yy]$ ]]; then
    echo ""
    read -p "Seed CMS content? (y/N): " SEED_CMS
    if [[ "$SEED_CMS" =~ ^[Yy]$ ]]; then
        php artisan db:seed --class="\NetServa\Cms\Database\Seeders\NetServaCmsSeeder"
    fi
fi

echo ""
echo "✅ Complete!"
echo ""
echo "💡 To restore from backup, run:"
echo "   ./scripts/restore-db.sh backups/database.sqlite.[timestamp]_before-migrate-fresh.backup"
