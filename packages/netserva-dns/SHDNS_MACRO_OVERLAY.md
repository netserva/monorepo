# shdns Macro Overlay Implementation

**Status:** ✅ Complete
**Date:** 2025-10-11
**File:** `packages/netserva-dns/src/Console/Commands/ShowDnsCommand.php`

---

## Summary

Enhanced the `shdns` command to serve as a macro overlay that can display DNS providers, zones, and records using a unified interface. The command now delegates to `shzone` and `shrec` commands under the hood while maintaining clean separation of concerns.

---

## Changes Made

### 1. Cleaned Up Default Views

#### Provider List View

**Before:**
```
DNS Providers
+----+---------+----------+--------+-----------------+
| ID | Name    | Type     | Active | Connection      |
+----+---------+----------+--------+-----------------+
| 1  | homelab | Powerdns | ✅     | SSH: gw → :8082 |
+----+---------+----------+--------+-----------------+

Total: 1 provider(s)

💡 Use --all for more columns (description, zones, usage)
💡 View specific provider: shdnsprovider <id|name>
```

**After:**
```
+----+---------+----------+--------+-----------------+
| ID | Name    | Type     | Active | Connection      |
+----+---------+----------+--------+-----------------+
| 1  | homelab | Powerdns | ✅     | SSH: gw → :8082 |
+----+---------+----------+--------+-----------------+
```

- Removed "DNS Providers" header
- Removed footer totals and hints
- Only show detailed info with `--all` flag

#### Single Provider View

**Before:**
```
DNS Provider: Homelab PowerDNS via SSH tunnel to GW (homelab)
======================================================================

Zones:
+----+------------------------+--------+---------+
| ID | Name                   | Active | Records |
+----+------------------------+--------+---------+
| 2  | 1.168.192.in-addr.arpa | ✅     | 0       |
| 1  | goldcoast.org          | ✅     | 44      |
+----+------------------------+--------+---------+

💡 Use --all to see connection details and statistics
```

**After:**
```
+----+------------------------+--------+---------+
| ID | Name                   | Active | Records |
+----+------------------------+--------+---------+
| 2  | 1.168.192.in-addr.arpa | ✅     | 0       |
| 1  | goldcoast.org          | ✅     | 44      |
+----+------------------------+--------+---------+
```

- Removed provider name header and separator
- Removed "Zones:" label
- Removed footer hint
- Only show detailed info with `--all` flag

### 2. Added `--zones` Overlay

**Usage:**
```bash
# Show all zones across all providers
php artisan shdns --zones

# Show zones for a specific provider
php artisan shdns homelab --zones
```

**Implementation:**
- Delegates to `shzone` command
- Passes provider filter when specified
- Maintains clean separation - no duplicate code

### 3. Added `--records` Overlay

**Usage:**
```bash
# Show all records across all providers
php artisan shdns --records

# Show records for all zones in a specific provider
php artisan shdns homelab --records
```

**Implementation:**
- Delegates to `shrec` command for each zone
- Shows zone header for context
- Handles providers with no zones gracefully

### 4. Added Positional Zone Argument

**Usage:**
```bash
# Show records for a specific zone (NEW!)
shdns homelab motd.com
shdns homelab goldcoast.org
shdns 1 netserva.com
```

**Implementation:**
- Second positional argument accepts zone name
- Requires provider as first argument
- Delegates to `shrec` command
- Same output as `shrec motd.com`

**Rationale:**
Provides a consistent navigation pattern:
- `shdns` → List all providers
- `shdns homelab` → List zones for provider
- `shdns homelab motd.com` → List records for zone
- Mirrors the hierarchical structure: provider → zones → records

---

## Command Signature

```php
protected $signature = 'shdns
    {provider? : Provider ID or name (shows all if omitted)}
    {zone? : Zone name to show records for (requires provider)}
    {--type=* : Filter by type (powerdns, cloudflare, etc.)}
    {--active : Show only active providers}
    {--inactive : Show only inactive providers}
    {--with-zones : Include zone count}
    {--with-usage : Show usage by venues/vsites/vnodes/vhosts}
    {--test : Test connection}
    {--sync-remote : Show zones from remote server (live count)}
    {--import-zones : Import remote zones to local database}
    {--zones : Show zones for provider(s) using shzone under the hood}
    {--records : Show records for provider(s) using shrec under the hood}
    {--json : Output as JSON}
    {--all : Show detailed information (connection, statistics, health)}';
```

---

## Architecture Pattern

### Macro Overlay Approach

```
shdns (macro)
├── Default: List providers (clean view)
├── --zones → calls shzone
├── --records → calls shrec (for each zone)
└── --all: Full provider details
```

**Benefits:**
1. **DRY Principle:** No code duplication - reuses existing commands
2. **Separation of Concerns:** Each command maintains its own logic
3. **Flexibility:** Users can still use `shzone` and `shrec` directly
4. **Consistency:** All commands follow NetServa CRUD patterns

### Method Flow

```php
handle()
├── if zone argument → Show records for zone
│   ├── Verify provider exists
│   └── $this->call('shrec', [zone])
│
├── if --zones → showZonesOverlay()
│   └── $this->call('shzone', [filters])
│
├── if --records → showRecordsOverlay()
│   ├── Get provider via showProvider()
│   └── foreach zone → $this->call('shrec', [zone])
│
├── if provider specified → showSingleProvider()
└── else → showProviderList()
```

---

## Usage Examples

### Basic Usage

```bash
# Clean list of providers (no header/footer)
shdns

# Show all provider details
shdns --all

# Show specific provider (zones table)
shdns homelab
shdns 1

# Show records for a specific zone (NEW!)
shdns homelab motd.com
shdns homelab goldcoast.org
```

### Macro Overlays

```bash
# Show all zones across all providers
shdns --zones

# Show zones for specific provider
shdns homelab --zones

# Show all records
shdns --records

# Show records for specific provider's zones
shdns homelab --records
```

### Combined Filters

```bash
# Show zones for active providers
shdns --active --zones

# Show records for PowerDNS providers
shdns --type=powerdns --records
```

---

## Testing

All functionality tested and working:

```bash
✅ shdns                      # Clean default view (providers list)
✅ shdns --all                # Full details with footer
✅ shdns homelab              # Single provider (zones table)
✅ shdns 1                    # Single provider by ID
✅ shdns homelab motd.com     # Zone records (positional argument)
✅ shdns --zones              # All zones (overlay)
✅ shdns homelab --zones      # Provider-specific zones (overlay)
✅ shdns homelab --records    # Provider-specific records (overlay)
```

---

## Files Modified

- `packages/netserva-dns/src/Console/Commands/ShowDnsCommand.php`
  - Updated signature to add `{zone?}` positional argument
  - Updated `handle()` to support zone argument and route to overlay methods
  - Updated `showProviderList()` to remove header/footer from default view
  - Updated `showSingleProvider()` to remove header/footer from default view
  - Added `showZonesOverlay()` method for `--zones` flag
  - Added `showRecordsOverlay()` method for `--records` flag
  - Added zone validation when provider + zone specified

---

## Future Enhancements

Possible future improvements:

1. **Record Filtering:** Pass filters from `shdns --records` to `shrec`
   ```bash
   shdns homelab --records --type=A
   ```

2. **Aggregated Statistics:** Show total counts across all zones
   ```bash
   shdns homelab --records --summary
   ```

3. **Export Support:** Combine with existing JSON export
   ```bash
   shdns homelab --records --json > backup.json
   ```

---

## Design Philosophy

This implementation follows NetServa 3.0 principles:

- **Database-First:** All data from database, no file operations
- **Service Layer:** Uses `DnsProviderManagementService` for business logic
- **Command Delegation:** Macro commands delegate to specialized commands
- **Clean Output:** Minimal noise in default views, details on demand
- **Consistent Patterns:** Follows established CRUD command structure

---

## Related Commands

- `shzone` - Show DNS zones (called by `shdns --zones`)
- `shrec` - Show DNS records (called by `shdns --records`)
- `adddns` - Add DNS provider
- `chdns` - Change DNS provider
- `deldns` - Delete DNS provider

---

**Implementation Complete** ✅
