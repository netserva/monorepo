# Annie - CalDAV/CardDAV Sync Fix

**Date:** 2025-12-04
**Devices:** s76 (main laptop), gram (alt laptop)
**Server:** msi (Nextcloud at solidus.cloud)

## Environment

### s76 Laptop (Main)
- OS: CachyOS (Arch-based)
- Thunderbird: v145
- Profile path: `/home/annie/.thunderbird/iens582z.default/`
- User: annie (lowercase home dir, but Nextcloud user is "Annie")

### gram Laptop (Alt)
- OS: CachyOS
- Thunderbird: v145
- Profile path: `/home/Annie/.thunderbird/` (uppercase home dir)

### Server (msi)
- Nextcloud at https://solidus.cloud
- Data dir: `/home/u/solidus.cloud/var/www/`
- Logs: `/home/u/solidus.cloud/var/www/data/nextcloud.log`
- PHP logs: `/home/u/solidus.cloud/var/log/php-errors.log`
- Config: `/home/u/solidus.cloud/var/www/html/config/config.php`

## Problem

Annie unable to sync calendars via Thunderbird on both laptops. Getting 401 authentication failures and 429 rate limiting errors.

## Root Causes Found

### 1. CardDAV Username Case Mismatch (Primary Issue)
- Nextcloud username: `Annie` (capital A)
- CalDAV entries in Thunderbird: `Annie` (correct)
- CardDAV entries in Thunderbird: `annie` (lowercase - WRONG!)

Thunderbird stores CardDAV settings with `ldap_2.servers.*` prefix (historical naming, not actual LDAP). The address book entries had lowercase username while calendar entries had correct case.

### 2. Wrong Stored Passwords
Both laptops had old/incorrect passwords stored in Thunderbird's `logins.json`.

### 3. Rate Limiting
Failed auth attempts triggered Nextcloud's brute force protection, blocking further attempts.

## Fixes Applied

### Fix 1: Username Case (s76)
```bash
# In prefs.js, changed CardDAV usernames from annie to Annie
sed -i "s/carddav.username\", \"annie\"/carddav.username\", \"Annie\"/g" /home/annie/.thunderbird/iens582z.default/prefs.js
```

### Fix 2: Remove Stored Passwords (both laptops)
Killed Thunderbird, then removed solidus.cloud entries from logins.json:
```python
import json
with open("logins.json", "r") as f:
    data = json.load(f)
data["logins"] = [l for l in data["logins"] if "solidus.cloud" not in l.get("hostname", "")]
with open("logins.json", "w") as f:
    json.dump(data, f)
```

### Fix 3: Reset Nextcloud Password
```bash
# On msi server
sudo -u www-data php /home/u/solidus.cloud/var/www/html/occ user:resetpassword Annie
# New password: ebujN4x7qwVyemqA
```

### Fix 4: Clear Rate Limiting
```bash
# Disable rate limiting temporarily in config.php
'ratelimit.protection.enabled' => false,

# Flush Redis
redis-cli FLUSHALL

# Reset brute force for specific IPs
sudo -u www-data php occ security:bruteforce:reset 206.83.118.111
sudo -u www-data php occ security:bruteforce:reset 1.145.121.253
```

## Calendars Configured
- Personal
- Charles & Annie
- Solidus

## Key Learnings

1. **Nextcloud usernames are case-sensitive** - "Annie" != "annie"
2. **Thunderbird CardDAV uses `ldap_2.servers.*` prefix** - Don't be confused, it's not LDAP
3. **Check both CalDAV AND CardDAV settings** - They can have different usernames stored
4. **Rate limiting can mask the real problem** - Disable temporarily to debug auth issues

## Pending Investigation

- **Mysterious lockup on s76** - Journal persistence confirmed working (11 boots of history)
- Boot logs available via `journalctl -b -1` etc.

## HTTP Response Codes Reference
- 207: Multi-Status (CalDAV success)
- 201: Created (new event)
- 204: Deleted
- 401: Auth failed
- 429: Rate limited
- 500: Server error

## Quick Debug Commands
```bash
# Check Nextcloud logs
sx msi 'tail -50 /home/u/solidus.cloud/var/www/data/nextcloud.log'

# Check nginx access for CalDAV
sx msi 'tail -50 /var/log/nginx/access.log | grep solidus'

# List Thunderbird profiles
sx s76 'ls -la /home/annie/.thunderbird/'
sx gram 'ls -la /home/Annie/.thunderbird/'
```
