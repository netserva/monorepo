# Phase 5: Filament UI Enhancements - NetServa 3.0

## Overview

Phase 5 adds comprehensive Filament UI components for migration management. This phase transforms the CLI-only migration system into a user-friendly web interface with visual dashboards, bulk actions, and detailed reporting.

**Prerequisites:** Phase 1-4 completed
**Duration:** 3-5 days
**Complexity:** Medium

---

## Goals

1. **Migration Dashboard** - Visual overview of migration status across all vhosts
2. **Validation Viewer** - Interactive interface for validation results
3. **Bulk Actions** - Web-based batch migration controls
4. **Migration Logs** - Detailed execution history viewer
5. **Rollback UI** - Visual rollback with archive selection

---

## Architecture

### 1. Migration Dashboard Widget

**Location:** `src/Filament/Widgets/MigrationDashboardWidget.php`

**Purpose:** Provides at-a-glance migration statistics

**Features:**
- Migration status breakdown (native, discovered, validated, migrated, failed)
- Total vhosts count
- Success/failure rates
- Recent migration activity
- Quick action buttons

**Layout:**
```
┌─────────────────────────────────────────────────────┐
│ Migration Status Overview                           │
├─────────────────────────────────────────────────────┤
│  Native: 15    Discovered: 42    Validated: 28     │
│  Migrated: 156    Failed: 3                         │
├─────────────────────────────────────────────────────┤
│ Success Rate: 98.1% (156/159)                       │
│ Last Migration: nc.goldcoast.org (2 mins ago)       │
├─────────────────────────────────────────────────────┤
│ [Validate All] [Migrate Validated] [View Logs]     │
└─────────────────────────────────────────────────────┘
```

### 2. Validation Results Viewer

**Location:** `src/Filament/Resources/VHostResource/Pages/ViewValidation.php`

**Purpose:** Display detailed validation results with visual indicators

**Features:**
- Color-coded validation status (✅ passed, ⚠️ warnings, ❌ errors)
- Expandable check categories (structure, database, service, security)
- JSON viewer for migration_issues field
- Action buttons (Re-validate, Migrate, Export)

**UI Components:**
```php
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;

Section::make('Validation Results')
    ->schema([
        TextEntry::make('migration_status')
            ->badge()
            ->color(fn ($state) => match($state) {
                'validated' => 'success',
                'discovered' => 'warning',
                'failed' => 'danger',
                default => 'gray'
            }),
        KeyValueEntry::make('migration_issues')
            ->keyLabel('Check')
            ->valueLabel('Result')
    ])
```

### 3. Bulk Migration Actions

**Location:** `src/Filament/Resources/VHostResource/Actions/BulkMigrateAction.php`

**Purpose:** Enable batch migration from Filament table

**Features:**
- Table bulk action for selected vhosts
- Pre-migration confirmation modal
- Progress tracking with live updates
- Summary report after completion

**Implementation:**
```php
use Filament\Tables\Actions\BulkAction;

BulkAction::make('migrate')
    ->label('Migrate Selected')
    ->icon('heroicon-o-arrow-path')
    ->requiresConfirmation()
    ->modalHeading('Migrate Selected VHosts')
    ->modalDescription('This will migrate all selected vhosts to NS 3.0 structure. Backups will be created automatically.')
    ->action(function (Collection $records) {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($records as $vhost) {
            $service = app(MigrationExecutionService::class);
            $result = $service->migrateVhost($vhost);

            $result['success'] ? $results['success']++ : $results['failed']++;
        }

        Notification::make()
            ->title("Migration Complete")
            ->body("Success: {$results['success']}, Failed: {$results['failed']}")
            ->success()
            ->send();
    })
```

### 4. Migration Logs Viewer

**Location:** `src/Filament/Resources/VHostResource/Pages/ViewMigrationLog.php`

**Purpose:** Display detailed migration execution history

**Features:**
- Timeline view of migration steps
- Step-by-step execution details
- Error/warning highlighting
- Backup archive links
- Execution duration

**UI Structure:**
```php
use Filament\Infolists\Components\RepeatableEntry;

Section::make('Migration Execution Log')
    ->schema([
        TextEntry::make('migration_issues.migration_execution.started_at')
            ->label('Started')
            ->dateTime(),
        TextEntry::make('migration_issues.migration_execution.completed_at')
            ->label('Completed')
            ->dateTime(),
        RepeatableEntry::make('migration_issues.migration_execution.steps_completed')
            ->schema([
                TextEntry::make('step')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
            ]),
        RepeatableEntry::make('migration_issues.migration_execution.errors')
            ->schema([
                TextEntry::make('error')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
            ])
    ])
```

### 5. Rollback UI

**Location:** `src/Filament/Resources/VHostResource/Actions/RollbackAction.php`

**Purpose:** Visual rollback interface with archive selection

**Features:**
- Action button on migrated vhosts
- Archive listing modal
- Archive selection dropdown
- Rollback confirmation
- Status update notification

**Implementation:**
```php
use Filament\Tables\Actions\Action;

Action::make('rollback')
    ->label('Rollback')
    ->icon('heroicon-o-arrow-uturn-left')
    ->color('warning')
    ->visible(fn (FleetVHost $record) => $record->rollback_available)
    ->requiresConfirmation()
    ->modalHeading('Rollback Migration')
    ->form([
        Select::make('archive')
            ->label('Rollback Point')
            ->options(function (FleetVHost $record) {
                $service = app(MigrationExecutionService::class);
                $result = $service->listRollbackPoints($record);

                return collect($result['rollback_points'] ?? [])
                    ->mapWithKeys(fn ($point) => [
                        $point['path'] => $point['filename'] . ' (' . $point['created_at'] . ')'
                    ])
                    ->toArray();
            })
            ->required()
    ])
    ->action(function (FleetVHost $record, array $data) {
        $service = app(MigrationExecutionService::class);
        $result = $service->rollbackVhost($record, $data['archive']);

        if ($result['success']) {
            Notification::make()
                ->title('Rollback Successful')
                ->body("VHost restored to validated status")
                ->success()
                ->send();
        }
    })
```

---

## Component Hierarchy

```
FleetVHostResource (existing)
├── Widgets/
│   └── MigrationDashboardWidget (NEW)
│       ├── Stats overview
│       ├── Quick actions
│       └── Recent activity
├── Pages/
│   ├── ViewValidation (NEW)
│   │   ├── Validation status
│   │   ├── Check results
│   │   └── Actions (re-validate, migrate)
│   └── ViewMigrationLog (NEW)
│       ├── Timeline view
│       ├── Step details
│       └── Error/warning display
└── Actions/
    ├── BulkMigrateAction (NEW)
    │   ├── Confirmation modal
    │   ├── Progress tracking
    │   └── Summary report
    └── RollbackAction (NEW)
        ├── Archive selector
        ├── Confirmation
        └── Status update
```

---

## Database Schema (No Changes)

Phase 5 uses existing fields from Phase 3-4:
- `migration_status` - Status enum
- `migration_issues` - JSON validation/execution data
- `migrated_at` - Timestamp
- `migration_backup_path` - Archive path
- `rollback_available` - Boolean flag

---

## User Workflows

### Workflow 1: View Migration Dashboard

1. Navigate to Admin Panel
2. See Migration Dashboard widget on homepage
3. View status breakdown and statistics
4. Click "View Logs" for detailed history

### Workflow 2: Validate and Migrate Single VHost

1. Open VHosts resource
2. Click vhost row to view details
3. Click "View Validation" tab
4. Review validation results
5. Click "Migrate" action button
6. Confirm migration in modal
7. See success notification
8. View updated status badge

### Workflow 3: Batch Migration

1. Open VHosts resource
2. Filter by `migration_status = validated`
3. Select multiple vhosts (checkboxes)
4. Click "Migrate Selected" bulk action
5. Confirm in modal
6. See progress notifications
7. View summary report

### Workflow 4: Rollback Migration

1. Open migrated vhost
2. Click "View Migration Log" tab
3. Click "Rollback" action button
4. Select archive from dropdown
5. Confirm rollback
6. See success notification
7. Status reset to "validated"

---

## Implementation Plan

### Step 1: Migration Dashboard Widget (2 hours)
- [ ] Create `MigrationDashboardWidget.php`
- [ ] Add stats queries (count by status)
- [ ] Implement recent activity feed
- [ ] Add quick action buttons
- [ ] Register widget in AdminPanelProvider

### Step 2: Validation Results Viewer (3 hours)
- [ ] Create `ViewValidation.php` page
- [ ] Build Infolist schema for validation results
- [ ] Add color-coded badges
- [ ] Implement JSON viewer for migration_issues
- [ ] Add action buttons (re-validate, migrate)

### Step 3: Bulk Migration Actions (4 hours)
- [ ] Create `BulkMigrateAction.php`
- [ ] Implement confirmation modal
- [ ] Add progress tracking
- [ ] Build summary notification
- [ ] Register action in VHostResource table

### Step 4: Migration Logs Viewer (3 hours)
- [ ] Create `ViewMigrationLog.php` page
- [ ] Build timeline component
- [ ] Display step-by-step execution
- [ ] Highlight errors/warnings
- [ ] Add backup archive links

### Step 5: Rollback UI (3 hours)
- [ ] Create `RollbackAction.php`
- [ ] Implement archive listing
- [ ] Build selection dropdown
- [ ] Add confirmation modal
- [ ] Register action in VHostResource

### Step 6: Testing & Polish (3 hours)
- [ ] Test all UI components
- [ ] Verify real-time updates
- [ ] Check responsive layout
- [ ] Test error scenarios
- [ ] Update documentation

---

## File Structure

```
packages/netserva-cli/src/Filament/
├── Widgets/
│   └── MigrationDashboardWidget.php (NEW)
├── Resources/
│   └── VHostResource/
│       ├── Pages/
│       │   ├── ViewValidation.php (NEW)
│       │   └── ViewMigrationLog.php (NEW)
│       └── Actions/
│           ├── BulkMigrateAction.php (NEW)
│           └── RollbackAction.php (NEW)
└── NetServaCliPlugin.php (MODIFIED - register widget)
```

---

## Testing Strategy

### Manual UI Testing
1. **Dashboard Widget**
   - Verify stats accuracy
   - Test quick actions
   - Check responsive layout

2. **Validation Viewer**
   - View validation results
   - Test re-validate action
   - Verify JSON display

3. **Bulk Migration**
   - Select multiple vhosts
   - Test confirmation modal
   - Verify progress tracking
   - Check summary report

4. **Migration Logs**
   - View execution timeline
   - Check step details
   - Verify error display

5. **Rollback UI**
   - List rollback points
   - Select archive
   - Confirm rollback
   - Verify status update

### Integration Testing
- Test with various migration_status values
- Verify with different migration_issues structures
- Test with missing/corrupted data
- Check permission-based visibility

---

## Success Criteria

- ✅ Dashboard widget shows accurate migration statistics
- ✅ Validation results display with visual indicators
- ✅ Bulk migration processes multiple vhosts successfully
- ✅ Migration logs show complete execution history
- ✅ Rollback UI lists archives and executes rollback
- ✅ All actions provide user feedback (notifications)
- ✅ UI is responsive and works on mobile/tablet
- ✅ Error states are handled gracefully

---

## Security Considerations

1. **Authorization** - Check user permissions for migration actions
2. **Validation** - Verify vhost ownership before operations
3. **Confirmation** - Require confirmation for destructive actions
4. **Logging** - Log all UI-initiated migrations
5. **Rate Limiting** - Prevent abuse of bulk actions

---

## Known Limitations

1. **No Real-Time Progress** - Batch migration shows summary only (no live progress bar)
2. **No Archive Preview** - Cannot view archive contents before rollback
3. **No Diff Viewer** - Cannot compare pre/post migration state
4. **No Email Notifications** - UI-only feedback
5. **No Undo for Rollback** - Rollback is permanent (re-migration required)

---

## Next Steps After Phase 5

### Phase 6: Advanced Features
1. Archive retention policies
2. Parallel migration support
3. Migration scheduling
4. Email notifications
5. Advanced reporting

---

**Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)**
