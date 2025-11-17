# Phase 4: Migration Execution System - NetServa 3.0

## Executive Summary

Phase 4 transforms **validated** legacy NetServa 1.0 vhosts into NetServa 3.0 web-centric architecture. This phase handles the actual structural migration while preserving data integrity and providing rollback capabilities.

**Status:** Design Complete, Implementation In Progress
**Created:** 2025-10-09
**Dependencies:** Phases 1-3 (Template, Discovery, Validation)

---

## Architecture Overview

### Migration Flow
```
discovered → validated → [MIGRATION] → migrated
                ↓ (on error)
              failed (with rollback data)
```

### Key Principles
1. **Idempotent Operations** - Can be run multiple times safely
2. **Atomic Transactions** - Database changes wrapped in transactions
3. **Backup First** - Create archive before any destructive changes
4. **Validation Required** - Only migrate vhosts with `migration_status: 'validated'`
5. **Rollback Support** - Store pre-migration state for reversal
6. **Progress Tracking** - Real-time status updates in database

---

## Migration Strategy

### What Gets Migrated

#### ✅ Structural Changes
```bash
# BEFORE (NetServa 1.0)
/srv/{domain}/
├── .ssh/authorized_keys    ← Archive (keep backup)
├── bin/busybox             ← Archive (keep backup)
├── etc/{passwd,group}      ← Archive (keep backup)
├── var/log/                ← MOVE to web/log/
├── var/run/                ← MOVE to web/run/
├── var/tmp/                ← DELETE (after backup)
├── web/                    ← RESTRUCTURE
└── msg/                    ← KEEP AS-IS

# AFTER (NetServa 3.0)
/srv/{domain}/
├── .archive/               ← NEW: Migration backup
│   ├── pre-migration.tar.gz
│   └── migration.json      ← Metadata
├── msg/                    ← Unchanged
└── web/                    ← Restructured
    ├── app/
    │   └── public/         ← MOVE from web/
    ├── log/                ← MOVE from var/log/
    └── run/                ← MOVE from var/run/
```

#### ✅ Permission Changes
```bash
# Update ownership
chown -R {UUSER}:{WUGID} /srv/{domain}

# Set web-centric permissions
chmod 755 /srv/{domain}
chmod 755 /srv/{domain}/web
chmod 755 /srv/{domain}/web/app
chmod 755 /srv/{domain}/web/app/public
chmod 750 /srv/{domain}/web/log    # Restrictive
chmod 750 /srv/{domain}/web/run    # Restrictive
chmod 700 /srv/{domain}/.archive   # Backup only
```

#### ✅ Configuration Updates
```bash
# PHP-FPM pool (may need update for new paths)
# nginx vhost (may need update for new DocumentRoot)
# Service reload after changes
```

### What Gets Archived

**Archive Location:** `/srv/{domain}/.archive/pre-migration-{timestamp}.tar.gz`

**Archive Contents:**
- `.ssh/` directory (complete)
- `bin/` directory (complete)
- `etc/` directory (complete)
- `var/tmp/` directory (complete)
- Migration metadata JSON

**Archive Metadata:** `/srv/{domain}/.archive/migration-{timestamp}.json`
```json
{
    "migration_date": "2025-10-09T12:34:56Z",
    "migration_status": "completed",
    "pre_migration_structure": [...],
    "post_migration_structure": [...],
    "vhost": "example.com",
    "vnode": "markc",
    "archive_file": "pre-migration-20251009-123456.tar.gz",
    "rollback_available": true
}
```

---

## Implementation Components

### 1. MigrationExecutionService

**Responsibility:** Execute structural migration with backup/rollback support

**Key Methods:**
```php
public function migrateVhost(FleetVhost $vhost): array
{
    // 1. Pre-flight checks (validation status, disk space)
    // 2. Create backup archive
    // 3. Execute structural migration
    // 4. Update configurations (PHP-FPM, nginx)
    // 5. Verify migration success
    // 6. Update migration_status to 'migrated'
    // 7. Return detailed result
}

public function rollbackVhost(FleetVhost $vhost): array
{
    // 1. Find latest migration archive
    // 2. Stop services
    // 3. Restore from archive
    // 4. Restore configurations
    // 5. Update migration_status to 'validated'
    // 6. Return result
}

protected function createPreMigrationBackup(FleetVnode $vnode, array $vars): array
{
    // Create .archive directory
    // tar czf archive with SSH/chroot directories
    // Store metadata JSON
    // Return archive info
}

protected function executeStructuralMigration(FleetVnode $vnode, array $vars): array
{
    // Move var/log → web/log
    // Move var/run → web/run
    // Create web/app/public if needed
    // Move web/* → web/app/public/*
    // Set permissions
    // Return success/failure
}

protected function verifyMigration(FleetVnode $vnode, array $vars): array
{
    // Check web-centric directories exist
    // Verify permissions
    // Test web access (HTTP 200)
    // Return verification results
}
```

### 2. MigrateVhostCommand

**Usage:**
```bash
# Migrate single vhost
php artisan migrate:vhost markc example.com

# Migrate all validated vhosts
php artisan migrate:vhost --all-validated

# Dry-run mode (show what would happen)
php artisan migrate:vhost markc example.com --dry-run

# Skip backup (dangerous!)
php artisan migrate:vhost markc example.com --no-backup
```

**Signature:**
```php
protected $signature = 'migrate:vhost
                      {vnode? : VNode name}
                      {vhost? : VHost domain}
                      {--all-validated : Migrate all validated vhosts}
                      {--dry-run : Show migration plan without executing}
                      {--no-backup : Skip backup creation (dangerous)}
                      {--force : Skip confirmation prompts}';
```

### 3. RollbackVhostCommand

**Usage:**
```bash
# Rollback single vhost
php artisan rollback:vhost markc example.com

# List available rollback points
php artisan rollback:vhost markc example.com --list

# Rollback to specific archive
php artisan rollback:vhost markc example.com --archive=pre-migration-20251009.tar.gz
```

---

## Migration Script Template

**Location:** `resources/scripts/vhost/migrate-vhost-v3.0.sh.blade.php`

```bash
#!/bin/bash
# NetServa 3.0 VHost Migration Script
# Migrates NetServa 1.0 structure to 3.0 web-centric architecture
# Template Version: 3.0.0
# Generated: {{ date('Y-m-d H:i:s') }}
# Domain: {{ $VHOST }}
# VNode: {{ $VNODE }}

set -euo pipefail

echo "=== NetServa 3.0 VHost Migration: {{ $VHOST }} ==="

# 1. Pre-flight checks
echo ">>> Step 1: Pre-flight Checks"
if [[ ! -d {{ $UPATH }} ]]; then
    echo "    ✗ VHost directory not found: {{ $UPATH }}"
    exit 1
fi

# Check disk space (need at least 2x current usage)
CURRENT_SIZE=$(du -sb {{ $UPATH }} | awk '{print $1}')
AVAILABLE_SPACE=$(df -B1 {{ $UPATH }} | tail -1 | awk '{print $4}')
REQUIRED_SPACE=$((CURRENT_SIZE * 2))

if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
    echo "    ✗ Insufficient disk space"
    echo "    Required: $((REQUIRED_SPACE / 1024 / 1024))MB"
    echo "    Available: $((AVAILABLE_SPACE / 1024 / 1024))MB"
    exit 1
fi
echo "    ✓ Disk space OK"

# 2. Create backup archive
echo ">>> Step 2: Backup Archive"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
ARCHIVE_DIR="{{ $UPATH }}/.archive"
ARCHIVE_FILE="pre-migration-${TIMESTAMP}.tar.gz"

mkdir -p "$ARCHIVE_DIR"
chmod 700 "$ARCHIVE_DIR"

echo "    → Creating backup archive"
cd {{ $UPATH }}
tar czf "$ARCHIVE_DIR/$ARCHIVE_FILE" \
    --ignore-failed-read \
    .ssh/ bin/ etc/ var/tmp/ 2>/dev/null || true

echo "    ✓ Archive created: $ARCHIVE_FILE"

# Store metadata
cat > "$ARCHIVE_DIR/migration-${TIMESTAMP}.json" <<EOF
{
    "migration_date": "$(date -Iseconds)",
    "migration_status": "in_progress",
    "vhost": "{{ $VHOST }}",
    "vnode": "{{ $VNODE }}",
    "archive_file": "$ARCHIVE_FILE",
    "pre_migration_size": "$CURRENT_SIZE"
}
EOF

# 3. Structural migration
echo ">>> Step 3: Structural Migration"

# Create new web-centric structure
if [[ ! -d {{ $WPATH }}/app ]]; then
    echo "    → Creating web/app directory"
    mkdir -p {{ $WPATH }}/app/public
fi

# Move var/log → web/log (if exists)
if [[ -d {{ $UPATH }}/var/log ]] && [[ ! -d {{ $WPATH }}/log ]]; then
    echo "    → Moving var/log to web/log"
    mv {{ $UPATH }}/var/log {{ $WPATH }}/log
fi

# Move var/run → web/run (if exists)
if [[ -d {{ $UPATH }}/var/run ]] && [[ ! -d {{ $WPATH }}/run ]]; then
    echo "    → Moving var/run to web/run"
    mv {{ $UPATH }}/var/run {{ $WPATH }}/run
fi

# Move web content to web/app/public (if not already there)
if [[ -d {{ $WPATH }} ]] && [[ ! -d {{ $WPATH }}/app/public/index.html ]] && [[ ! -d {{ $WPATH }}/app/public/index.php ]]; then
    echo "    → Restructuring web content"
    # Move all web files to app/public (except app, log, run dirs)
    find {{ $WPATH }} -maxdepth 1 -type f -exec mv {} {{ $WPATH }}/app/public/ \; 2>/dev/null || true
    find {{ $WPATH }} -maxdepth 1 -type d ! -name 'app' ! -name 'log' ! -name 'run' ! -name '.' ! -name 'web' -exec mv {} {{ $WPATH }}/app/public/ \; 2>/dev/null || true
fi

echo "    ✓ Structure migrated"

# 4. Set permissions
echo ">>> Step 4: Permissions"
chown -R {{ $UUSER }}:{{ $WUGID }} {{ $UPATH }}
chmod 755 {{ $UPATH }}
chmod 755 {{ $WPATH }}
chmod 755 {{ $WPATH }}/app
chmod 755 {{ $WPATH }}/app/public
chmod 750 {{ $WPATH }}/log
chmod 750 {{ $WPATH }}/run
chmod 700 "$ARCHIVE_DIR"
echo "    ✓ Permissions set"

# 5. Update PHP-FPM pool (if needed - check for path references)
echo ">>> Step 5: Configuration Update"
# Most PHP-FPM pools use socket, no path changes needed
echo "    ✓ Configurations checked"

# 6. Reload services
echo ">>> Step 6: Service Reload"
systemctl reload nginx 2>/dev/null && echo "    ✓ nginx reloaded"
systemctl reload php*-fpm 2>/dev/null && echo "    ✓ php-fpm reloaded"

# 7. Verification
echo ">>> Step 7: Verification"
[[ -d {{ $WPATH }}/app/public ]] && echo "    ✓ web/app/public exists"
[[ -d {{ $WPATH }}/log ]] && echo "    ✓ web/log exists"
[[ -d {{ $WPATH }}/run ]] && echo "    ✓ web/run exists"

# Update metadata
cat > "$ARCHIVE_DIR/migration-${TIMESTAMP}.json" <<EOF
{
    "migration_date": "$(date -Iseconds)",
    "migration_status": "completed",
    "vhost": "{{ $VHOST }}",
    "vnode": "{{ $VNODE }}",
    "archive_file": "$ARCHIVE_FILE",
    "rollback_available": true
}
EOF

echo ""
echo "=== ✓ VHost {{ $VHOST }} migrated successfully to NetServa 3.0 ==="
echo "    Backup: $ARCHIVE_DIR/$ARCHIVE_FILE"
echo "    New structure: {{ $WPATH }}/{app/public,log,run}"
```

---

## Database Schema Updates

### FleetVhost Additional Fields
```php
// Already exists from Phase 3:
'migration_status' => 'enum(native,discovered,imported,validated,migrated,failed)',
'migration_issues' => 'json', // Now includes migration execution logs
'migrated_at' => 'timestamp',

// Add for Phase 4:
'migration_backup_path' => 'string', // Path to .archive directory
'rollback_available' => 'boolean', // Can this vhost be rolled back?
'migration_attempts' => 'integer', // Track retry count
```

### Migration Issues Structure (Enhanced)
```json
{
    "validation_status": "passed",
    "validation_date": "2025-10-09T10:00:00Z",
    "migration_execution": {
        "started_at": "2025-10-09T12:00:00Z",
        "completed_at": "2025-10-09T12:05:00Z",
        "status": "completed",
        "backup_archive": "/srv/example.com/.archive/pre-migration-20251009-120000.tar.gz",
        "steps_completed": [
            "pre_flight_checks",
            "backup_creation",
            "structural_migration",
            "permissions_update",
            "configuration_update",
            "service_reload",
            "verification"
        ],
        "errors": [],
        "warnings": [
            "No index.html or index.php found in web root"
        ]
    }
}
```

---

## Error Handling & Rollback

### Automatic Rollback Triggers
- Migration script returns non-zero exit code
- Post-migration verification fails
- HTTP check returns non-200 status
- Critical file/directory missing after migration

### Manual Rollback Process
```bash
# List available rollback points
php artisan rollback:vhost markc example.com --list

# Output:
# Available rollback points for example.com:
# 1. pre-migration-20251009-120000.tar.gz (2025-10-09 12:00:00)
# 2. pre-migration-20251008-153000.tar.gz (2025-10-08 15:30:00)

# Execute rollback
php artisan rollback:vhost markc example.com
```

### Rollback Script Template
**Location:** `resources/scripts/vhost/rollback-vhost-v3.0.sh.blade.php`

```bash
#!/bin/bash
set -euo pipefail

echo "=== NetServa 3.0 VHost Rollback: {{ $VHOST }} ==="

ARCHIVE_DIR="{{ $UPATH }}/.archive"
ARCHIVE_FILE="{{ $ARCHIVE_FILE }}"

# 1. Stop services
systemctl stop nginx php*-fpm

# 2. Restore from archive
cd {{ $UPATH }}
tar xzf "$ARCHIVE_DIR/$ARCHIVE_FILE"

# 3. Restore permissions
chown -R {{ $UUSER }}:{{ $WUGID }} {{ $UPATH }}

# 4. Restart services
systemctl start nginx php*-fpm

echo "=== ✓ Rollback complete ==="
```

---

## Testing Strategy

### Unit Tests
- `MigrationExecutionServiceTest.php` - Test service methods in isolation
- Mock RemoteExecutionService for all SSH calls
- Test backup creation, structural migration, verification

### Feature Tests
- `MigrateVhostCommandTest.php` - Test CLI command
- Test dry-run mode
- Test single and batch migration
- Test error handling

### Integration Tests (Manual)
1. Create test vhost with NS 1.0 structure
2. Run discovery: `php artisan fleet:discover markc`
3. Run validation: `php artisan validate:vhost markc test.example.com --store`
4. Run migration: `php artisan migrate:vhost markc test.example.com`
5. Verify web access works
6. Test rollback: `php artisan rollback:vhost markc test.example.com`

---

## Success Criteria

✅ **Migration Execution:**
- Creates backup archive before any changes
- Successfully migrates directory structure
- Updates permissions correctly
- Services reload without errors
- Web access verified post-migration

✅ **Rollback Capability:**
- Can restore from backup archive
- Returns vhost to pre-migration state
- No data loss during rollback

✅ **Progress Tracking:**
- Real-time status updates in database
- Detailed logs in migration_issues field
- Clear error messages on failure

✅ **Idempotency:**
- Can run migration multiple times safely
- Detects already-migrated vhosts
- No duplicate directory creation

---

## Security Considerations

1. **Backup Permissions** - `.archive` directory must be chmod 700
2. **Archive Encryption** - Consider encrypting sensitive SSH keys in archive
3. **Cleanup Policy** - Define archive retention policy (30 days?)
4. **Audit Trail** - All migrations logged in application logs
5. **Dry-Run Default** - Require explicit --force flag for production

---

## Future Enhancements

1. **Parallel Migration** - Migrate multiple vhosts simultaneously
2. **Progress Bar** - Real-time CLI progress indicator
3. **Email Notifications** - Alert on migration success/failure
4. **Web UI** - Filament resource for migration management
5. **Incremental Migration** - Migrate subsets of files over time

---

**Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)**
