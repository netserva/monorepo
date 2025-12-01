# Roadmap: netserva-db Plugin

**Status:** Planned
**Priority:** Medium (nice-to-have, community value)
**Dependencies:** netserva-core (db:snapshot/restore commands)
**Model:** Standalone like netserva-cms (uses config package, not core)

---

## Overview

A Filament-native database management plugin providing:
1. Migration & Seeder GUI
2. Database Snapshot Management
3. Simple Table Browser with CRUD

**Unique Value:** No existing Filament plugin provides unified migration/seeder/backup management with a visual interface.

---

## Architecture

```
packages/netserva-db/
├── composer.json                 # Standalone, requires filament/filament
├── src/
│   ├── NetServaDbServiceProvider.php
│   ├── Filament/
│   │   ├── NetServaDbPlugin.php
│   │   ├── Pages/
│   │   │   ├── MigrationManager.php
│   │   │   ├── SeederManager.php
│   │   │   ├── SnapshotManager.php
│   │   │   └── TableBrowser.php
│   │   ├── Resources/
│   │   │   └── SnapshotResource.php
│   │   └── Widgets/
│   │       ├── MigrationStatusWidget.php
│   │       ├── DatabaseHealthWidget.php
│   │       └── PendingMigrationsWidget.php
│   ├── Services/
│   │   ├── MigrationService.php
│   │   ├── SeederService.php
│   │   ├── SnapshotService.php
│   │   └── DynamicTableService.php
│   └── Models/
│       └── DynamicModel.php      # Runtime model for any table
├── config/
│   └── netserva-db.php
├── resources/
│   └── views/
│       └── filament/
│           └── pages/
└── tests/
```

---

## Feature Specifications

### 1. Migration Manager Page

**URL:** `/admin/database/migrations`

**Features:**
- [ ] List all migrations with status (applied ✓, pending ○, unknown ?)
- [ ] Show migration file path and batch number
- [ ] View migration source code in modal (read-only, syntax highlighted)
- [ ] Run single pending migration
- [ ] Run all pending migrations
- [ ] Rollback last batch (with confirmation)
- [ ] Rollback specific migration (with confirmation)
- [ ] Auto-snapshot before any rollback operation
- [ ] Filter: All / Applied / Pending
- [ ] Search by migration name

**Table Columns:**
| Migration | Batch | Status | Applied At | Actions |
|-----------|-------|--------|------------|---------|
| 2024_01_01_create_users | 1 | ✓ Applied | 2024-01-01 12:00 | View / Rollback |
| 2024_02_01_create_posts | 2 | ✓ Applied | 2024-02-01 14:30 | View / Rollback |
| 2024_03_01_add_slug | - | ○ Pending | - | View / Run |

**Safety Features:**
- Confirmation modal for destructive operations
- Auto-snapshot before rollback
- Production environment warning/block

---

### 2. Seeder Manager Page

**URL:** `/admin/database/seeders`

**Features:**
- [ ] List all seeders from database/seeders/
- [ ] List package seeders (auto-discovered)
- [ ] View seeder source code in modal
- [ ] Run individual seeder
- [ ] Run DatabaseSeeder (main)
- [ ] Track last run timestamp (stored in settings)
- [ ] Show estimated record counts per seeder (if detectable)

**Table Columns:**
| Seeder | Location | Last Run | Actions |
|--------|----------|----------|---------|
| DatabaseSeeder | App | 2024-01-15 10:00 | View / Run |
| UserSeeder | App | 2024-01-15 10:00 | View / Run |
| DnsZoneSeeder | netserva-dns | Never | View / Run |

**Safety Features:**
- Confirmation before running (seeders may duplicate data)
- Option to truncate table before seeding
- Production environment warning

---

### 3. Snapshot Manager Page

**URL:** `/admin/database/snapshots`

**Features:**
- [ ] List all snapshots from storage/app/backups/
- [ ] Show snapshot size, date, connection type
- [ ] Create new snapshot (with optional name)
- [ ] Restore snapshot (with confirmation)
- [ ] Download snapshot as file
- [ ] Delete old snapshots
- [ ] Bulk delete snapshots older than X days
- [ ] Auto-cleanup settings (retain last N snapshots)

**Table Columns:**
| Name | Connection | Size | Created | Actions |
|------|------------|------|---------|---------|
| auto_2024-03-01_120000 | sqlite | 2.5 MB | 2024-03-01 12:00 | Restore / Download / Delete |
| pre-migration_2024-02-28 | sqlite | 2.3 MB | 2024-02-28 09:15 | Restore / Download / Delete |
| manual-backup | sqlite | 2.1 MB | 2024-02-15 14:00 | Restore / Download / Delete |

**Header Actions:**
- Create Snapshot (modal with name input)
- Cleanup Old (modal with age selector)

---

### 4. Table Browser Page

**URL:** `/admin/database/tables` and `/admin/database/tables/{table}`

**Features:**
- [ ] List all database tables with row counts
- [ ] Click table to browse records
- [ ] Dynamic columns based on table schema
- [ ] Basic CRUD operations (create, read, update, delete)
- [ ] Search across all columns
- [ ] Sort by any column
- [ ] Pagination
- [ ] Export table to CSV
- [ ] View table structure (columns, types, indexes)

**Table List Columns:**
| Table | Rows | Size | Engine | Actions |
|-------|------|------|--------|---------|
| users | 150 | 48 KB | InnoDB | Browse / Structure |
| dns_zones | 25 | 12 KB | InnoDB | Browse / Structure |
| migrations | 45 | 8 KB | InnoDB | Browse / Structure |

**Record Browser Features:**
- Auto-detect primary key
- Handle JSON columns gracefully
- Truncate long text in table view
- Full content in view/edit modal
- Foreign key links (if detectable)

**Safety Features:**
- Confirm before delete
- Highlight system tables (migrations, jobs, etc.)
- Read-only mode option for production

---

### 5. Dashboard Widgets

**MigrationStatusWidget:**
- Shows: "3 pending migrations"
- Click to go to Migration Manager
- Color-coded: green (none), yellow (pending), red (failed)

**DatabaseHealthWidget:**
- Database size
- Table count
- Last backup age
- Connection status

**PendingMigrationsWidget:**
- List of pending migration names
- Quick "Run All" button

---

## Technical Implementation Notes

### Dynamic Model for Table Browser

```php
class DynamicModel extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    public static function forTable(string $table): static
    {
        $instance = new static();
        $instance->setTable($table);

        // Auto-detect primary key
        $primaryKey = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableDetails($table)
            ->getPrimaryKey()
            ?->getColumns()[0] ?? 'id';

        $instance->setKeyName($primaryKey);

        return $instance;
    }
}
```

### Migration Service

```php
class MigrationService
{
    public function getPendingMigrations(): Collection
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles($this->getMigrationPaths());
        $ran = $migrator->getRepository()->getRan();

        return collect($files)->reject(fn ($file, $name) => in_array($name, $ran));
    }

    public function runMigration(string $migration): bool
    {
        // Auto-snapshot first
        Artisan::call('db:snapshot', ['name' => "pre-migration_{$migration}"]);

        // Run single migration
        Artisan::call('migrate', [
            '--path' => $this->getMigrationPath($migration),
            '--force' => true,
        ]);

        return true;
    }
}
```

---

## Configuration

```php
// config/netserva-db.php
return [
    // Navigation
    'navigation_group' => 'Database',
    'navigation_sort' => 100,

    // Features toggles
    'enable_migrations' => true,
    'enable_seeders' => true,
    'enable_snapshots' => true,
    'enable_table_browser' => true,

    // Table browser settings
    'table_browser' => [
        'excluded_tables' => [
            'migrations',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ],
        'readonly_tables' => [
            'migrations',
            'users', // Optional: protect sensitive tables
        ],
        'max_rows_per_page' => 50,
    ],

    // Snapshot settings
    'snapshots' => [
        'path' => storage_path('app/backups'),
        'auto_cleanup' => true,
        'keep_last' => 10,
        'max_age_days' => 30,
    ],

    // Safety settings
    'production_readonly' => true,
    'require_confirmation' => true,
    'auto_snapshot_before_destructive' => true,
];
```

---

## Estimated Effort

| Component | Complexity | Time Estimate |
|-----------|------------|---------------|
| Migration Manager | Medium | 4-6 hours |
| Seeder Manager | Low | 2-3 hours |
| Snapshot Manager | Low | 2-3 hours (builds on existing commands) |
| Table Browser | High | 8-12 hours |
| Widgets | Low | 1-2 hours |
| Testing | Medium | 4-6 hours |
| Documentation | Low | 2-3 hours |
| **Total** | | **23-35 hours** |

---

## Community Value

**Why this would be popular:**

1. **Fills a gap** - No existing Filament plugin for migrations/seeders GUI
2. **Developer-friendly** - Visual feedback during development
3. **Production-safe** - Built-in safeguards and backups
4. **Standalone** - Can be used in any Filament project
5. **Unified UX** - Native Filament look and feel

**Potential Packagist name:** `netserva/filament-database-manager` or `netserva/filament-db`

---

## Migration Notes

**IMPORTANT:** When netserva-db is developed, the following commands must be moved from netserva-core:

```
packages/netserva-core/src/Console/Commands/
├── DbSnapshotCommand.php   → Move to netserva-db
├── DbRestoreCommand.php    → Move to netserva-db
└── DbListCommand.php       → Move to netserva-db
```

Also move the database protection logic from `NetServaCoreServiceProvider.php`:
- `registerDatabaseProtection()` method
- `createAutoBackup()` method

This ensures netserva-db is truly standalone and can be used in any Laravel/Filament project without requiring netserva-core.

**Temporary location:** Commands live in netserva-core until netserva-db is developed, providing immediate value for NetServa users.

---

## References

- Foundation: `packages/netserva-core/src/Console/Commands/Db*.php` (snapshot/restore)
- Standalone pattern: `packages/netserva-cms/` (uses config, not core)
- Filament v4 table patterns: Existing NetServa resources

---

**Created:** 2025-12-01
**Author:** Claude Code session
**Status:** Roadmap - Future Release
