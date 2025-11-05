# NetServa Database Backup & Restore Guide

## 🚨 CRITICAL: Backups Created!

Your database has been backed up to `/home/markc/.ns/backups/`

All backup files follow the pattern: `database.sqlite.YYYYMMDD_HHMMSS_label.backup`

---

## Quick Commands

### Create Backup
```bash
# Quick backup with auto-timestamp
composer backup

# Labeled backup (recommended before major changes)
./scripts/backup-db.sh "before-feature-x"
```

### List Available Backups
```bash
ls -lht backups/
```

### Restore from Backup
```bash
# Interactive - shows list and prompts
composer restore

# Direct restore (if you know the filename)
./scripts/restore-db.sh backups/database.sqlite.20251105_143414_pre-cms-seed.backup
```

### Safe Migrate Fresh
```bash
# Automatically creates backup before wiping database
composer safe-fresh

# OR directly:
./scripts/safe-migrate-fresh.sh
```

---

## Daily Workflow

### Before Making Risky Changes
```bash
cd ~/.ns
composer backup              # Creates timestamped backup
# ... make your changes ...
```

### If Something Goes Wrong
```bash
composer restore             # Shows backups, prompts for which to restore
```

---

## Manual SQLite Backup (Alternative Method)

```bash
# Simple file copy
cp database/database.sqlite database/database.sqlite.backup

# Restore
cp database/database.sqlite.backup database/database.sqlite
```

---

## Automatic Retention Policy

The backup script automatically:
- ✅ Keeps the last 10 backups
- ✅ Deletes older backups automatically
- ✅ Creates safety backup before each restore
- ✅ Shows record counts for verification

---

## Common Scenarios

### 1. Before Running CMS Seeder
```bash
composer backup
php artisan db:seed --class="\NetServa\Cms\Database\Seeders\NetServaCmsSeeder"
```

### 2. Before Migrate Fresh (DANGEROUS!)
```bash
# Use safe version that auto-backs up
composer safe-fresh

# NOT recommended (no auto-backup):
# php artisan migrate:fresh
```

### 3. Testing New Migrations
```bash
./scripts/backup-db.sh "before-new-migration"
php artisan migrate
# If problems:
composer restore
```

### 4. Recovering from Accident
```bash
# If you accidentally ran migrate:fresh
composer restore
# Select the most recent backup before the accident
```

---

## What Gets Backed Up

The entire SQLite database including:
- ✅ Fleet data (venues, vsites, vnodes, vhosts)
- ✅ DNS zones and records
- ✅ Mail configurations and mailboxes
- ✅ Secrets and credentials
- ✅ Backup jobs and snapshots
- ✅ CMS content (pages, posts, menus)
- ✅ All other tables

---

## Where Backups Are Stored

- **Location**: `/home/markc/.ns/backups/`
- **Git**: Excluded (in `.gitignore`)
- **Format**: SQLite database file (can be opened directly)

---

## Emergency Recovery

If all else fails and you need to manually inspect a backup:

```bash
# Open backup in SQLite
sqlite3 backups/database.sqlite.20251105_143414_pre-cms-seed.backup

# Check what's in it
.tables
SELECT COUNT(*) FROM fleet_vhosts;
SELECT COUNT(*) FROM dns_zones;
.quit
```

---

## Best Practices

1. **Always backup before**:
   - Running `migrate:fresh`
   - Making schema changes
   - Bulk data operations
   - Testing new features

2. **Label your backups**:
   ```bash
   ./scripts/backup-db.sh "before-mail-refactor"
   ./scripts/backup-db.sh "working-state-oct-6"
   ```

3. **Verify backups work**:
   - Periodically test restore process
   - Check record counts match expectations

4. **Never run `migrate:fresh` without backup**:
   - Use `composer safe-fresh` instead
   - It auto-backs up before wiping

---

## Current Database Status

Run this to see current counts:
```bash
php artisan tinker --execute="
echo 'Venues: ' . \Ns\Platform\Models\Venue::count() . PHP_EOL;
echo 'Vsites: ' . \Ns\Platform\Models\Vsite::count() . PHP_EOL;
echo 'Vnodes: ' . \Ns\Platform\Models\Vnode::count() . PHP_EOL;
echo 'Vhosts: ' . \Ns\Platform\Models\Vhost::count() . PHP_EOL;
echo 'DNS Zones: ' . \Ns\Dns\Models\DnsZone::count() . PHP_EOL;
echo 'CMS Pages: ' . \NetServa\Cms\Models\Page::count() . PHP_EOL;
echo 'CMS Posts: ' . \NetServa\Cms\Models\Post::count() . PHP_EOL;
"
```
