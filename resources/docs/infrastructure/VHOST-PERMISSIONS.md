# NetServa 3.0 VHost Permissions Architecture

## Overview

NetServa 3.0 uses a carefully designed permission structure that balances security with nginx/PHP-FPM access requirements. The critical insight: **the top-level `/srv/vhost` directory MUST be owned by the vhost user with setgid bit** for nginx (running as `www-data`) to access ACME challenge files and serve web content.

## Permission Structure

### Top-Level Directory: `/srv/vhost`

**Requirements:**
- Owner: vhost user (e.g., `u1001` or `sysadm`)
- Group: `www-data`
- Permissions: `02750` (drwxr-s---)
- **Critical:** Setgid bit (2) ensures new files inherit `www-data` group

**Why this matters:**
```bash
# WRONG - nginx cannot traverse directory
drwxr-xr-x  root     www-data  /srv/goldcoast.org

# CORRECT - nginx can traverse, setgid ensures file inheritance
drwxr-s---  u1001    www-data  /srv/goldcoast.org
```

Without proper top-level permissions, nginx returns **403 Forbidden** even if subdirectories have correct permissions.

### Permission Breakdown: 02750

```
0 2 7 5 0
│ │ │ │ └─ Other: no permissions (---)
│ │ │ └─── Group: read + execute (r-x)
│ │ └───── Owner: read + write + execute (rwx)
│ └─────── Setgid bit: files inherit group ownership
└───────── Special bits field
```

**Setgid Behavior:**
- Files created in directory inherit `www-data` group
- Ensures nginx (www-data) can read files
- Owner can still control file permissions

## Directory Tree Structure

```
/srv/
└── goldcoast.org/           # 02750  u1001:www-data  (TOP-LEVEL: CRITICAL)
    ├── .ssh/                # 00700  u1001:u1001     (user-only access)
    ├── .gnupg/              # 00700  u1001:u1001     (user-only access)
    ├── .rc/                 # 02750  u1001:www-data
    │   ├── bin/             # 00700  u1001:u1001     (scripts)
    │   └── www/             # 00700  u1001:u1001     (web scripts)
    ├── msg/                 # 00750  u1001:u1001     (mail only)
    └── web/                 # 00755  u1001:www-data  (web server needs execute)
        ├── app/             # 02750  u1001:www-data
        │   └── public/      # 02750  u1001:www-data  (webroot)
        │       ├── .well-known/
        │       │   └── acme-challenge/  # 02750  u1001:www-data  (Let's Encrypt)
        │       ├── index.php            # 00640  u1001:www-data
        │       └── lib/
        │           └── sh/              # 00700  root:root (security)
        │               ├── script.sh    # 00700  root:root
        │               └── index.html   # 00640  root:www-data (web-readable)
        ├── log/             # 02770  u1001:www-data  (setgid for log rotation)
        │   ├── access.log   # 00660  u1001:www-data
        │   ├── cache.log    # 00660  u1001:www-data
        │   └── error.log    # 00660  u1001:www-data
        ├── run/             # 02750  u1001:www-data  (PHP-FPM sockets)
        │   └── fpm.sock     # 00660  u1001:www-data
        └── tmp/             # 02750  u1001:www-data  (temp files)
```

## Chroot Security Model

### UID-Based Chroot Decision

```bash
if [ "$U_UID" -gt 1000 ]; then
    # Multi-tenant server: root-owned top-level for chroot security
    chown 0:0 "$upath"
    chmod 755 "$upath"
else
    # System service (e.g., mail): user-owned top-level
    chown "$U_UID:www-data" "$upath"
    chmod 02750 "$upath"
fi
```

**NetServa 3.0 Default:**
- **All vhosts use UID ≤ 1012**, so top-level is user-owned
- Provides direct user access without chroot restrictions
- Simpler permission model for OpenWrt/Alpine deployment

**Legacy NetServa 1.0 Behavior:**
- UIDs > 1000 triggered root-owned chroot
- Provided additional isolation for untrusted users
- Can be re-enabled if needed for multi-tenant environments

## ACME Challenge Requirements

Let's Encrypt HTTP-01 challenge requires nginx to serve files from `/.well-known/acme-challenge/`:

```nginx
# _default.conf - HTTP server for ACME challenges
server {
    listen 0.0.0.0:80 default_server;
    server_name _;

    location ^~ /.well-known/acme-challenge/ {
        root /srv/$host/web/app/public;  # Uses $host variable for multi-vhost
    }

    location / {
        return 301 https://$host$request_uri;
    }
}
```

**Permission Chain for ACME:**
```
nginx (www-data) needs to access:
/srv/goldcoast.org/                          # 02750 u1001:www-data (GROUP execute)
  └─ web/                                     # 00755 u1001:www-data (WORLD execute)
      └─ app/                                 # 02750 u1001:www-data (GROUP execute)
          └─ public/                          # 02750 u1001:www-data (GROUP execute)
              └─ .well-known/                 # 02750 u1001:www-data (GROUP execute)
                  └─ acme-challenge/          # 02750 u1001:www-data (GROUP execute)
                      └─ test-file.txt        # 00640 u1001:www-data (GROUP read)
```

**Key insight:** Nginx must have **execute permission on EVERY directory** in the path. Group membership (`www-data`) provides this access.

## Mail Server Special Case

Mail servers (Postfix/Dovecot) use the `sysadm` user instead of `u1000`:

```bash
# Mail vhost special case
UPATH=/srv/mail.goldcoast.org
UUSER=sysadm
U_UID=1000
U_GID=1000

# Permissions
drwxr-s---  sysadm   www-data  /srv/mail.goldcoast.org
drwxr-s---  sysadm   www-data  /srv/mail.goldcoast.org/web
```

**Why sysadm?**
- Historical NetServa convention for system mail services
- UID 1000 reserved for system administrator
- Mail database (`/srv/.local/sqlite/sysadm.db`) owned by sysadm

## Common Permission Issues

### Issue 1: 403 Forbidden on ACME Challenges

**Symptoms:**
```bash
curl http://goldcoast.org/.well-known/acme-challenge/test.txt
→ 403 Forbidden
```

**Diagnosis:**
```bash
# Check if www-data can traverse the path
su -s /bin/sh www-data -c "ls /srv/goldcoast.org/web/app/public/.well-known/acme-challenge/"
→ Permission denied
```

**Root Cause:** Top-level `/srv/goldcoast.org` owned by `root` instead of vhost user.

**Solution:**
```bash
chown u1001:www-data /srv/goldcoast.org
chmod 02750 /srv/goldcoast.org
```

### Issue 2: Missing Setgid Bit

**Symptoms:**
- New files don't inherit `www-data` group
- Nginx can't read newly created files

**Diagnosis:**
```bash
ls -ld /srv/goldcoast.org/web/app/public
→ drwxr-x---  u1001  u1001  /srv/goldcoast.org/web/app/public  # Missing setgid
```

**Solution:**
```bash
# Add setgid bit (2 prefix)
chmod 02750 /srv/goldcoast.org/web/app/public
find /srv/goldcoast.org/web/app/public -type d -exec chmod 02750 {} +
```

### Issue 3: Wrong Owner on Top-Level

**Symptoms:**
- User can't write to their own directory
- chperms fails with "permission denied"

**Diagnosis:**
```bash
ls -ld /srv/goldcoast.org
→ drwxr-xr-x  root  www-data  /srv/goldcoast.org  # Should be u1001
```

**Solution:**
```bash
chown -R u1001:www-data /srv/goldcoast.org
```

## PHP-FPM Socket Permissions

PHP-FPM creates socket files in `/srv/vhost/web/run/`:

```bash
# PHP-FPM pool configuration
[goldcoast.org]
user = 1001
group = 1001
listen = /srv/goldcoast.org/web/run/fpm.sock
listen.owner = 1001
listen.group = www-data
listen.mode = 0660
```

**Socket file:**
```bash
srw-rw----  u1001  www-data  /srv/goldcoast.org/web/run/fpm.sock
```

**Nginx fastcgi_pass:**
```nginx
location ~ \.php$ {
    fastcgi_pass unix:/srv/goldcoast.org/web/run/fpm.sock;
    # nginx (www-data) connects to socket owned by u1001:www-data
}
```

## Automated Permission Management

### chperms Command

The `chperms` command automatically fixes permissions for a vhost:

```bash
# Fix single vhost
php artisan chperms gw goldcoast.org

# Fix all vhosts on server
php artisan chperms gw --all

# Dry-run to preview changes
php artisan chperms gw goldcoast.org --dry-run
```

**What chperms does:**
1. Sets top-level `/srv/vhost` to `02750 user:www-data`
2. Applies setgid bit to all web directories
3. Creates missing directories (msg, web/log, web/run, web/tmp)
4. Fixes `.ssh` and `.gnupg` permissions (700/600)
5. Makes shell scripts executable (700)
6. Sets web logs to `660 user:www-data`
7. Secures sensitive files (configuration.php → 400)

### Migration Script

When migrating existing vhosts, use this pattern:

```bash
#!/bin/bash
# Fix permissions for all vhosts after migration

VHOSTS="goldcoast.org motd.com netserva.com ..."

for vhost in $VHOSTS; do
    uid=$(getent passwd "u${vhost#*.}" | cut -d: -f3)
    user="u${uid}"

    # Fix top-level first (critical!)
    chown "$user:www-data" "/srv/$vhost"
    chmod 02750 "/srv/$vhost"

    # Fix all subdirectories
    find "/srv/$vhost" -type d -exec chown "$user:www-data" {} \;
    find "/srv/$vhost" -type d -exec chmod 02750 {} \;

    # Fix all files
    find "/srv/$vhost" -type f -exec chown "$user:www-data" {} \;
    find "/srv/$vhost" -type f -exec chmod 0640 {} \;
done
```

## Security Considerations

### Why Not 755 for Top-Level?

```bash
# INSECURE - world-readable
drwxr-xr-x  u1001  www-data  /srv/goldcoast.org

# SECURE - only owner and www-data group
drwxr-s---  u1001  www-data  /srv/goldcoast.org
```

**Benefits of 02750:**
- No world-readable access to vhost files
- Only nginx (www-data group) can read
- User retains full control
- Setgid ensures consistent group ownership

### Root-Owned Scripts

Scripts in `web/app/public/lib/sh/` are root-owned for security:

```bash
# Server management scripts should NOT be user-editable
drwx------  root  root  /srv/goldcoast.org/web/app/public/lib/sh/
-rwx------  root  root  /srv/goldcoast.org/web/app/public/lib/sh/backup.sh

# Exception: index.html is web-readable to prevent directory listing
-rw-r-----  root  www-data  /srv/goldcoast.org/web/app/public/lib/sh/index.html
```

## Platform-Specific Notes

### OpenWrt

- Uses BusyBox `find` (limited options)
- No `stat` command (use `ls -l` instead)
- Nginx runs as `www-data` (UID 33, GID 33)
- PHP-FPM runs as pool user (UID 1001+)

### Alpine Linux

- Uses OpenRC instead of systemd
- Nginx runs as `www-data` or `nginx` depending on package
- Musl libc instead of glibc (permissions work identically)

### Debian/Ubuntu

- Full GNU coreutils available
- Systemd service management
- Standard Nginx/PHP-FPM packages

## Verification Commands

```bash
# Check top-level ownership and setgid
ls -ld /srv/goldcoast.org

# Check full directory tree
ls -lR /srv/goldcoast.org/web/app/public

# Test nginx access as www-data
su -s /bin/sh www-data -c "cat /srv/goldcoast.org/web/app/public/.well-known/acme-challenge/test.txt"

# Verify setgid inheritance
touch /srv/goldcoast.org/web/app/public/test-file
ls -l /srv/goldcoast.org/web/app/public/test-file
# Should show: -rw-r----- u1001 www-data (group inherited)

# Check ACME challenge via HTTP
curl -v http://goldcoast.org/.well-known/acme-challenge/test.txt
```

## References

- [VHOST-VARIABLES.md](../VHOST-VARIABLES.md) - Environment variable reference
- [OPENWRT-NGINX-VHOSTS.md](OPENWRT-NGINX-VHOSTS.md) - OpenWrt nginx setup
- [SSH_EXECUTION_ARCHITECTURE.md](../SSH_EXECUTION_ARCHITECTURE.md) - Remote execution patterns
- [ChpermsCommand.php](../../packages/netserva-cli/src/Console/Commands/ChpermsCommand.php) - Permission automation
