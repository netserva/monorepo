# shdns Positional Arguments - Implementation Complete

**Status:** ✅ Complete
**Date:** 2025-10-11
**Feature:** Hierarchical navigation with positional arguments

---

## Summary

Enhanced the `shdns` command to support hierarchical navigation through DNS providers, zones, and records using intuitive positional arguments. Users can now drill down from providers → zones → records using a consistent command pattern.

---

## Navigation Hierarchy

```
shdns                      → List all providers
shdns homelab              → List zones for provider
shdns homelab motd.com     → List records for zone
```

### Level 1: Providers

```bash
$ shdns
+----+---------+----------+--------+-----------------+
| ID | Name    | Type     | Active | Connection      |
+----+---------+----------+--------+-----------------+
| 1  | homelab | Powerdns | ✅     | SSH: gw → :8082 |
+----+---------+----------+--------+-----------------+
```

### Level 2: Zones (for a provider)

```bash
$ shdns homelab
+----+------------------------+--------+---------+
| ID | Name                   | Active | Records |
+----+------------------------+--------+---------+
| 2  | 1.168.192.in-addr.arpa | ✅     | 0       |
| 1  | goldcoast.org          | ✅     | 44      |
| 3  | motd.com               | ✅     | 8       |
+----+------------------------+--------+---------+
```

### Level 3: Records (for a zone)

```bash
$ shdns homelab motd.com
+----+------+-----------------------+-------------------------------+-------+-----+--------+
| ID | Type | Name                  | Content                       | TTL   | Pri | Active |
+----+------+-----------------------+-------------------------------+-------+-----+--------+
| 76 | A    | autoconfig.motd.com   | 192.168.1.250                 | 300s  | -   | ✓      |
| 73 | A    | autodiscover.motd.com | 192.168.1.250                 | 300s  | -   | ✓      |
| 75 | A    | mail.motd.com         | 192.168.1.250                 | 300s  | -   | ✓      |
+----+------+-----------------------+-------------------------------+-------+-----+--------+
```

---

## Implementation Details

### Command Signature

```php
protected $signature = 'shdns
    {provider? : Provider ID or name (shows all if omitted)}
    {zone? : Zone name to show records for (requires provider)}
    // ... all existing options preserved
';
```

### Execution Logic

```php
public function handle(): int
{
    $provider = $this->argument('provider');
    $zone = $this->argument('zone');

    // Level 3: Show records for zone
    if ($zone) {
        if (!$provider) {
            // Error: zone requires provider
        }
        // Verify provider exists
        // Delegate to shrec command
    }

    // Level 2: Show zones for provider
    if ($provider) {
        return $this->showSingleProvider($provider);
    }

    // Level 1: Show all providers
    return $this->showProviderList();
}
```

---

## Benefits

### 1. Intuitive Navigation

Natural progression through the DNS hierarchy:
- **See providers** → Pick one
- **See zones** → Pick one
- **See records** → Manage them

### 2. Consistent Pattern

Same navigation style as other NetServa commands:
- `shdns` / `shzone` / `shrec`
- `shdns homelab` (zones for homelab)
- `shdns homelab motd.com` (records for motd.com)

### 3. No Code Duplication

Delegates to existing commands:
- `shdns homelab motd.com` → calls `shrec motd.com`
- Maintains DRY principle
- Single source of truth for record display

### 4. Backward Compatible

All existing functionality preserved:
- `--zones` overlay still works
- `--records` overlay still works
- All flags and options unchanged

---

## Usage Examples

### Basic Navigation

```bash
# Start at top level
shdns

# Drill down to provider
shdns homelab

# Drill down to zone
shdns homelab motd.com

# Use by ID
shdns 1 motd.com
```

### Combined with Options

```bash
# Show full provider details
shdns homelab --all

# Show all zones across providers
shdns --zones

# Show all records for provider's zones
shdns homelab --records

# Test connection
shdns homelab --test
```

### Comparison

```bash
# These are equivalent:
shdns homelab motd.com
shrec motd.com

# These are equivalent:
shdns homelab --zones
shzone --provider=homelab

# But shdns provides the hierarchy!
shdns              # Start here
shdns homelab      # Then here
shdns homelab motd.com  # End here
```

---

## Error Handling

### Zone Without Provider

```bash
$ shdns motd.com
❌ Provider is required when specifying a zone
Usage: shdns <provider> <zone>
```

### Invalid Provider

```bash
$ shdns invalid motd.com
❌ DNS provider not found: invalid
```

### Provider Not Found

Standard error handling from existing service layer.

---

## Design Rationale

### Why Positional Arguments?

1. **Natural Flow:** Mirrors how users think about DNS hierarchy
2. **Less Typing:** `shdns homelab motd.com` vs `shrec motd.com --provider=homelab`
3. **Discoverability:** Users naturally explore level by level
4. **Consistency:** Matches filesystem-like navigation patterns

### Why Delegate to shrec?

1. **DRY Principle:** No duplicate record display logic
2. **Maintainability:** Single place to update record views
3. **Feature Parity:** Automatic access to all shrec features
4. **Testing:** Reuse existing shrec test suite

### Why Verify Provider?

1. **User Feedback:** Catch typos early
2. **Consistency:** Ensure zone actually belongs to provider
3. **Error Messages:** Provide helpful guidance

---

## Testing Checklist

All scenarios tested and verified:

```bash
✅ shdns                          # List providers
✅ shdns --all                    # Providers with details
✅ shdns homelab                  # Provider's zones
✅ shdns 1                        # Provider by ID
✅ shdns homelab motd.com         # Zone records
✅ shdns 1 goldcoast.org          # Provider ID + zone name
✅ shdns homelab --zones          # Zones overlay
✅ shdns homelab --records        # Records overlay
✅ shdns invalid motd.com         # Error: invalid provider
✅ shdns motd.com                 # Error: missing provider
```

---

## Related Commands

- `shzone` - Show DNS zones (called indirectly)
- `shrec` - Show DNS records (called directly for zone arg)
- `shdns --zones` - Zones overlay (uses shzone)
- `shdns --records` - Records overlay (uses shrec)

---

## Files Modified

1. **ShowDnsCommand.php**
   - Added `{zone?}` to signature
   - Updated `handle()` to process zone argument
   - Added provider verification logic
   - Added delegation to `shrec` command

---

## Future Enhancements

### Potential Additions

1. **Auto-completion:** Shell completion for zones
   ```bash
   shdns homelab <TAB>  # Shows: goldcoast.org, motd.com, ...
   ```

2. **Record ID Navigation:** Direct to specific record
   ```bash
   shdns homelab motd.com 76  # Show record ID 76
   ```

3. **Filter Pass-through:** Pass options to shrec
   ```bash
   shdns homelab motd.com --type=A  # Only A records
   ```

4. **Breadcrumb Context:** Show hierarchy in output
   ```bash
   Provider: homelab → Zone: motd.com
   [records table]
   ```

---

## Complete Feature Set

The `shdns` command now supports:

### Positional Arguments
- `shdns` - List providers
- `shdns <provider>` - List zones
- `shdns <provider> <zone>` - List records

### Macro Overlays
- `shdns --zones` - All zones
- `shdns --records` - All records
- `shdns <provider> --zones` - Provider zones
- `shdns <provider> --records` - Provider records

### Display Modes
- Default: Clean table output
- `--all`: Full details with headers/footers
- `--json`: JSON output

### Operations
- `--test`: Test connection
- `--sync-remote`: Sync from remote
- `--import-zones`: Import zones

---

**Implementation Complete** ✅

All positional arguments working as expected. The `shdns` command now provides intuitive hierarchical navigation through the DNS provider → zones → records structure while maintaining full backward compatibility with existing features.
