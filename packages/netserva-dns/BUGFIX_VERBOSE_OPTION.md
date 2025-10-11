# Bugfix: Reserved Laravel Option Conflicts

**Date:** 2025-10-10
**Issues:**
1. `An option named "verbose" already exists.`
2. `An option named "version" already exists.`

**Status:** ✅ Fixed

---

## Problem 1: --verbose Conflict

The `shdnsprovider` command failed with:
```
An option named "verbose" already exists.
```

## Root Cause

Laravel's base `Command` class provides built-in `-v|--verbose` and `-V|--version` options.

Our custom options conflicted with these built-in options.

## Problem 2: --version Conflict

The `adddnsprovider` command failed with:
```
An option named "version" already exists.
```

## Solutions

### Solution 1: Renamed --verbose to --detailed

**File:** `ShowDnsProviderCommand.php`

**Before:**
```php
{--verbose : Show detailed configuration}
```

**After:**
```php
{--detailed : Show detailed configuration}
```

**Changes:**
1. Line 29: Signature updated
2. Line 63: `$this->option('verbose')` → `$this->option('detailed')`
3. Line 346: Conditional check updated
4. Line 348: Help text updated

### Solution 2: Renamed --version to --provider-version

**Files:** `AddDnsProviderCommand.php`, `ChangeDnsProviderCommand.php`

**Before:**
```php
{--version= : Provider version (e.g., "4.8.0")}
```

**After:**
```php
{--provider-version= : Provider version (e.g., "4.8.0")}
```

**Changes in AddDnsProviderCommand.php:**
1. Line 34: Signature updated
2. Line 105: `$this->option('version')` → `$this->option('provider-version')`

**Changes in ChangeDnsProviderCommand.php:**
1. Line 32: Signature updated
2. Line 67-68: Option check and assignment updated
3. Line 121: Help text updated

## Verification

```bash
# shdnsprovider now works
php artisan shdnsprovider
# Output: No DNS providers found

# adddnsprovider now works
php artisan adddnsprovider "Test" powerdns --endpoint=http://localhost:8081 --api-key=test
# Output: Creates provider successfully

# Help shows correct options
php artisan shdnsprovider --help
# Shows: --detailed        Show detailed configuration

php artisan adddnsprovider --help
# Shows: --provider-version= : Provider version (e.g., "4.8.0")
```

## Best Practice: Reserved Laravel Options

**Avoid these reserved Laravel command options:**
- `-v|--verbose` - Verbosity level (quiet/normal/verbose/very verbose/debug)
- `-V|--version` - Display application version
- `-q|--quiet` - Suppress all output
- `-n|--no-interaction` - Disable interactive prompts
- `--env` - Environment name
- `--no-ansi` - Disable ANSI output
- `--ansi` - Force ANSI output

**Use descriptive alternatives:**
- `--detailed` instead of `--verbose`
- `--provider-version` instead of `--version`
- `--app-version` instead of `--version`
- `--full` instead of `--verbose`
- `--expanded` instead of `--verbose`

---

**Status:** ✅ Resolved
