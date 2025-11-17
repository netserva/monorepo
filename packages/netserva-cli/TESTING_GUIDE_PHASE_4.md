# Phase 4 Testing Guide - Migration Execution System

## Test Suite Overview

Phase 4 includes comprehensive automated tests covering all migration execution and rollback functionality.

**Created:** 2025-10-09
**Test Framework:** Pest 4.0
**Coverage:** Unit + Feature + Integration (manual)

---

## Automated Tests

### Test Files Created

1. **`tests/Feature/MigrationExecutionServiceTest.php`** (11 tests)
   - Service unit tests with mocked RemoteExecutionService
   - Tests all public methods with various scenarios

2. **`tests/Feature/MigrateVhostCommandTest.php`** (7 tests)
   - CLI command tests
   - Tests user interaction, output, and error handling

3. **`tests/Feature/RollbackVhostCommandTest.php`** (7 tests)
   - Rollback CLI command tests
   - Tests rollback scenarios and archive management

**Total:** 25 automated tests

---

## Running Tests

### Run All Phase 4 Tests
```bash
php artisan test --filter=Migration
```

### Run Specific Test Suites
```bash
# Migration execution service tests
php artisan test --filter=MigrationExecutionService

# Migrate command tests
php artisan test --filter=MigrateVhostCommand

# Rollback command tests
php artisan test --filter=RollbackVhostCommand
```

### Run Single Test
```bash
# Example: Test backup creation
php artisan test --filter="it successfully migrates validated vhost with backup"
```

---

## Test Coverage

### MigrationExecutionService Tests

| Test Case | What It Tests | Expected Result |
|-----------|--------------|-----------------|
| fails migration when vhost has no vnode | Pre-flight validation | Error: "no associated VNode" |
| fails migration when vhost is already migrated | Prevent re-migration | Error: "already migrated" |
| fails migration when vhost is native | Skip native vhosts | Error: "native NS 3.0" |
| fails migration when vhost has no configuration | Require vconfs | Error: "No vhost configuration" |
| successfully migrates validated vhost with backup | Full migration flow | Success + backup created |
| successfully migrates without backup when skipBackup is true | No-backup mode | Success + rollback_available=false |
| fails migration and updates status to failed on error | Error handling | migration_status='failed' |
| successfully rolls back a migrated vhost | Rollback from archive | migration_status='validated' |
| fails rollback when rollback is not available | Rollback validation | Error: "No rollback available" |
| lists available rollback points | Archive listing | Array of archives with timestamps |
| returns empty array when no rollback points exist | Empty archive handling | Empty array |

### MigrateVhostCommand Tests

| Test Case | What It Tests | Expected Result |
|-----------|--------------|-----------------|
| requires vnode and vhost arguments | CLI validation | Exit code 1 + usage message |
| fails when vnode is not found | Vnode lookup | Error: "VNode not found" |
| fails when vhost is not found | Vhost lookup | Error: "VHost not found" |
| shows dry-run migration plan | Dry-run mode | Migration plan displayed, no changes |
| shows current migration status | Status display | Current migration_status shown |
| migrates validated vhost successfully | Successful migration | Success message + summary |
| displays migration failure with error details | Error display | Error message + log output |
| migrates all validated vhosts with --all-validated | Batch migration | Summary table with counts |
| handles mixed success/failure in batch migration | Partial failures | Exit code 1 when failures occur |
| warns when using --no-backup flag | Safety warning | Warning about no rollback |

### RollbackVhostCommand Tests

| Test Case | What It Tests | Expected Result |
|-----------|--------------|-----------------|
| requires vnode and vhost arguments | CLI validation | Exit code 1 |
| fails when vnode is not found | Vnode lookup | Error: "VNode not found" |
| fails when vhost is not found | Vhost lookup | Error: "VHost not found" |
| lists available rollback points | Archive listing | Table of archives |
| shows message when no rollback points exist | No archives | Helpful message |
| fails rollback when rollback is not available | Rollback validation | Error + explanation |
| successfully rolls back a migrated vhost | Successful rollback | Success + status reset |
| displays rollback failure with error message | Error handling | Error message |
| shows warning about SSH directory restoration | Security warning | SSH warning displayed |
| fails when no backup archives are found | Archive validation | Error: "No backup archives found" |

---

## Manual Integration Tests

These tests require a real vnode and should be performed before production deployment.

### Prerequisites

1. **Test VNode Available**
   ```bash
   # Ensure test server is accessible
   ssh test-vnode "echo 'Connection OK'"
   ```

2. **Test VHost Created**
   ```bash
   # Create a test vhost with NS 1.0 structure
   ssh test-vnode "sudo /usr/local/bin/setup test.example.com"
   ```

3. **Discover Test VHost**
   ```bash
   php artisan fleet:discover test-vnode
   ```

### Integration Test 1: Single VHost Migration

**Objective:** Migrate a single vhost from NS 1.0 to NS 3.0

**Steps:**
```bash
# 1. Discover vhost
php artisan fleet:discover test-vnode

# 2. Validate vhost
php artisan validate:vhost test-vnode test.example.com --store

# 3. Check validation results
php artisan tinker
>>> $vhost = FleetVhost::where('domain', 'test.example.com')->first()
>>> $vhost->migration_status  // Should be 'validated'
>>> $vhost->migration_issues  // Check for warnings

# 4. Dry-run migration
php artisan migrate:vhost test-vnode test.example.com --dry-run

# 5. Execute migration
php artisan migrate:vhost test-vnode test.example.com

# 6. Verify migration
ssh test-vnode "ls -la /srv/test.example.com"
# Expected structure:
# .archive/
# msg/
# web/app/public/
# web/log/
# web/run/

# 7. Test web access
curl http://test.example.com
# Should return index.html or index.php

# 8. Verify database
php artisan tinker
>>> $vhost->fresh()->migration_status  // Should be 'migrated'
>>> $vhost->rollback_available         // Should be true
>>> $vhost->migration_backup_path      // Should contain path
```

**Expected Results:**
- ✅ Migration completes without errors
- ✅ Backup archive created in `.archive/`
- ✅ Directory structure matches NS 3.0 spec
- ✅ Web access works
- ✅ Database updated correctly

### Integration Test 2: Rollback

**Objective:** Test rollback functionality

**Steps:**
```bash
# 1. List available rollback points
php artisan rollback:vhost test-vnode test.example.com --list

# 2. Execute rollback
php artisan rollback:vhost test-vnode test.example.com

# 3. Verify rollback
ssh test-vnode "ls -la /srv/test.example.com"
# Expected: NS 1.0 structure restored (.ssh, bin, etc, var/)

# 4. Check database
php artisan tinker
>>> $vhost->fresh()->migration_status  // Should be 'validated'

# 5. Re-migrate after rollback
php artisan migrate:vhost test-vnode test.example.com
```

**Expected Results:**
- ✅ Rollback restores original structure
- ✅ migration_status reset to 'validated'
- ✅ Re-migration succeeds

### Integration Test 3: Batch Migration

**Objective:** Migrate multiple vhosts simultaneously

**Steps:**
```bash
# 1. Create multiple test vhosts
ssh test-vnode "
  sudo /usr/local/bin/setup batch1.test
  sudo /usr/local/bin/setup batch2.test
  sudo /usr/local/bin/setup batch3.test
"

# 2. Discover all vhosts
php artisan fleet:discover test-vnode

# 3. Validate all discovered vhosts
php artisan validate:vhost --all-discovered --store

# 4. Check how many are validated
php artisan tinker
>>> FleetVhost::where('migration_status', 'validated')->count()

# 5. Execute batch migration
php artisan migrate:vhost --all-validated

# 6. Review summary table
# Should show:
# - VHost | VNode | Status | Steps
# - All with ✅ Success

# 7. Verify all migrated
php artisan tinker
>>> FleetVhost::where('migration_status', 'migrated')->count()
```

**Expected Results:**
- ✅ All vhosts migrated successfully
- ✅ Summary table shows all successes
- ✅ No errors in migration logs

### Integration Test 4: Error Handling

**Objective:** Test migration failure and recovery

**Steps:**
```bash
# 1. Create vhost with insufficient disk space scenario
# (Manual setup required - fill disk to test)

# 2. Attempt migration
php artisan migrate:vhost test-vnode test-disk-full.com

# Expected: Migration fails with disk space error

# 3. Check database
php artisan tinker
>>> $vhost = FleetVhost::where('domain', 'test-disk-full.com')->first()
>>> $vhost->migration_status  // Should be 'failed'
>>> $vhost->migration_issues['migration_execution']['errors']

# 4. Fix disk space issue
# (Free up space)

# 5. Retry migration
php artisan migrate:vhost test-vnode test-disk-full.com

# Expected: Should succeed on retry
```

**Expected Results:**
- ✅ Failure handled gracefully
- ✅ Error details stored in database
- ✅ Retry succeeds after fix

---

## Test Data Setup

### Create Test VHost with NS 1.0 Structure

```bash
#!/bin/bash
# Script to create test vhost with NS 1.0 structure
# Run on test-vnode as root

DOMAIN="test.example.com"
UID=10001
USER="u${UID}"

# Create user
useradd -M -U -s /bin/bash -u $UID -d /srv/$DOMAIN -c "$DOMAIN" $USER

# Create NS 1.0 directory structure
mkdir -p /srv/$DOMAIN/{.ssh,bin,etc,var/{log,run,tmp},web,msg}

# Add SSH key (optional)
ssh-keygen -t ed25519 -f /srv/$DOMAIN/.ssh/id_ed25519 -N ""
cat /srv/$DOMAIN/.ssh/id_ed25519.pub > /srv/$DOMAIN/.ssh/authorized_keys

# Copy busybox
cp /bin/busybox /srv/$DOMAIN/bin/

# Create etc files
echo "$USER:x:$UID:$UID:$DOMAIN:/srv/$DOMAIN:/bin/bash" > /srv/$DOMAIN/etc/passwd
echo "$USER:x:$UID:" > /srv/$DOMAIN/etc/group

# Create web files
echo "<h1>$DOMAIN</h1>" > /srv/$DOMAIN/web/index.html
echo "<?php phpinfo();" > /srv/$DOMAIN/web/phpinfo.php

# Set permissions
chown -R $USER:$USER /srv/$DOMAIN
chmod 700 /srv/$DOMAIN/.ssh
chmod 600 /srv/$DOMAIN/.ssh/authorized_keys

echo "Test vhost created: $DOMAIN"
```

---

## Troubleshooting Tests

### Test Fails: "vsite_id NOT NULL constraint"

**Problem:** FleetVnode requires vsite_id

**Solution:** Ensure beforeEach creates FleetVsite first
```php
$this->vsite = FleetVsite::create([
    'name' => 'test-site',
    'slug' => 'test-site',
    'environment' => 'testing',
    'provider' => 'local',
    'status' => 'active',
]);
```

### Test Fails: "provider NOT NULL constraint"

**Problem:** FleetVsite/FleetVnode requires provider field

**Solution:** Add provider to create calls
```php
'provider' => 'local',
```

### Mock Not Working

**Problem:** Mockery expectations not matching

**Solution:** Check exact parameter matching
```php
// Use Mockery::on() for flexible matching
$this->mockRemoteExecution->shouldReceive('executeScript')
    ->once()
    ->with(
        Mockery::on(fn ($host) => $host === 'test-vnode'),
        Mockery::on(fn ($script) => str_contains($script, 'expected command')),
        Mockery::any(),
        Mockery::any(),
        Mockery::any()
    )
    ->andReturn([...]);
```

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Phase 4 Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: sqlite3, pdo_sqlite

      - name: Install Dependencies
        run: composer install

      - name: Run Migrations
        run: php artisan migrate --force

      - name: Run Phase 4 Tests
        run: php artisan test --filter=Migration

      - name: Upload Test Results
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: test-results
          path: storage/logs/
```

---

## Test Maintenance

### When to Update Tests

1. **Service Logic Changes** - Update MigrationExecutionServiceTest
2. **CLI Output Changes** - Update command tests
3. **New Features** - Add new test cases
4. **Bug Fixes** - Add regression tests

### Best Practices

- ✅ Keep tests isolated (use beforeEach)
- ✅ Mock external dependencies (SSH, filesystem)
- ✅ Test both success and failure paths
- ✅ Use descriptive test names
- ✅ Clean up test data (use transactions)

---

## Success Criteria

### Automated Tests

- ✅ All 25 tests passing
- ✅ No skipped tests
- ✅ Execution time under 10 seconds

### Integration Tests

- ✅ Single vhost migration succeeds
- ✅ Rollback restores original state
- ✅ Batch migration handles multiple vhosts
- ✅ Error handling prevents data loss

---

**Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)**
