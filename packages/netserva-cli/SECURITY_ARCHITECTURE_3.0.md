# NetServa 3.0 Security Architecture - Web-Centric, No Customer SSH

## Executive Summary

NetServa 3.0 implements **security-by-default** by removing customer SSH/SCP access entirely. The new architecture is **web-centric only**, eliminating the SSH chroot attack vector that was rarely used by customers.

**Decision Date:** 2025-10-09
**Rationale:** Security by default - single SSH access for root only, firewall-restricted to NetServa central management system

---

## Architecture Changes

### NetServa 1.0 (Legacy) - SSH Chroot Per Customer
```
/srv/{domain}/
├── .ssh/authorized_keys    ← SSH keys for u10?? customer login
├── bin/busybox             ← Chroot shell utilities
├── etc/passwd              ← Chroot user database
├── etc/group               ← Chroot group database
├── var/log/                ← Service logs
├── var/run/                ← Runtime files
├── var/tmp/                ← Temporary files
├── web/                    ← Web root
└── msg/                    ← Mail storage
```

**Security Issues:**
- SSH chroot exposed per-customer attack vector
- Rarely used by customers (low value, high risk)
- Complex permission management
- Increased attack surface

### NetServa 3.0 (Current) - Web-Centric Only
```
/srv/{domain}/
├── msg/                    ← Mail storage
└── web/                    ← Web root only
    ├── app/
    │   └── public/         ← Public web files
    ├── log/                ← Application logs (750 permissions)
    └── run/                ← Runtime files (750 permissions)
```

**Security Benefits:**
- **No customer SSH access** - eliminates chroot attack vector
- **Firewall-restricted root SSH** - only accessible from NetServa management system
- **Web-centric permissions** - simplified ownership model
- **Reduced attack surface** - no shell utilities, no SSH keys per customer
- **Manual override available** - SSH can be enabled case-by-case if truly needed

---

## Implementation Changes

### 1. Provisioning Template: `addvhost-v3.0.sh.blade.php`

**Removed Steps (4-9):**
- ❌ SSH access setup (`.ssh/authorized_keys`)
- ❌ Chroot symlink (`home/u/{domain}`)
- ❌ Busybox installation (`bin/`)
- ❌ User profile (`.profile`)
- ❌ etc/passwd creation
- ❌ etc/group creation

**New Step 3: Directory Structure**
```bash
# NetServa 3.0: /srv/{domain}/{msg,web}
# Web subdirs: /srv/{domain}/web/{app,log,run,app/public}
mkdir -p {{ $MPATH }}
mkdir -p {{ $WPATH }}/{app/public,log,run}
```

**New Step 6: Permissions**
```bash
chown -R {{ $UUSER }}:{{ $WUGID }} {{ $UPATH }}
chmod 755 {{ $UPATH }}
chmod 755 {{ $WPATH }}
chmod 755 {{ $WPATH }}/app
chmod 755 {{ $WPATH }}/app/public
chmod 750 {{ $WPATH }}/log    # Restrictive
chmod 750 {{ $WPATH }}/run    # Restrictive
```

### 2. Validation Service: `VhostValidationService.php`

**Removed Checks:**
- ❌ SSH authorized_keys file existence
- ❌ .ssh directory permissions (700)
- ❌ authorized_keys permissions (600)
- ❌ Chroot subdirectories (.ssh, bin, etc, var/*)

**New Checks:**
```php
// Directory Structure - Web-centric only
$criticalSubdirs = ['app', 'log', 'run', 'app/public'];

// Security - Web permissions only
'web directory' => '755',
'log directory' => '750',
'run directory' => '750',
```

### 3. Management Service: `VhostManagementService.php`

**Updated Docblocks:**
```php
/**
 * NetServa 3.0 Template-Based Approach (Security-by-Default):
 * - Web-centric architecture: NO customer SSH/SCP access by default
 * - Includes: User creation, web directories (msg, web/{app,log,run,app/public}),
 *   PHP-FPM pools, nginx config, web files, permissions
 */
```

**Updated Fallback Script:**
```bash
# NetServa 3.0: Web-centric provisioning (no SSH)
mkdir -p {$MPATH}
mkdir -p {$WPATH}/{app/public,log,run}
chmod 755 {$UPATH} {$WPATH} {$WPATH}/app {$WPATH}/app/public
chmod 750 {$WPATH}/log {$WPATH}/run
```

---

## Migration Impact

### Existing VHosts (NetServa 1.0 → 3.0)
- **Discovery Phase:** Legacy SSH directories detected but not required
- **Validation Phase:** Warnings issued for missing web directories
- **Migration Phase:** SSH directories left intact (not removed)
- **New VHosts:** Created with web-centric structure only

### Manual SSH Enablement (If Requested)
If a customer absolutely requires SSH/SCP access:
1. Manually create `.ssh` directory: `mkdir -p /srv/{domain}/.ssh`
2. Set proper permissions: `chmod 700 /srv/{domain}/.ssh`
3. Add authorized_keys: `touch /srv/{domain}/.ssh/authorized_keys && chmod 600`
4. Update firewall rules to allow customer IP
5. Document in `vconfs` table: `SSH_ENABLED=true`

---

## Files Modified

### Core Services
- ✅ `/packages/netserva-cli/resources/scripts/vhost/addvhost-v3.0.sh.blade.php`
- ✅ `/packages/netserva-cli/src/Services/VhostValidationService.php`
- ✅ `/packages/netserva-cli/src/Services/VhostManagementService.php`

### Tests
- ⚠️ `/packages/netserva-cli/tests/Feature/VhostValidationServiceTest.php`
  - Already uses web-centric structure (WPATH, MPATH)
  - Requires FleetVNode/FleetVSite factories to be created

---

## Security Compliance

### Validation Categories (Updated)
1. **User & Permissions** - Correct UID/GID, proper ownership ✅
2. **Directory Structure** - Web-centric paths (msg, web/{app,log,run,app/public}) ✅
3. **Configuration Files** - PHP-FPM, nginx configurations ✅
4. **Database Consistency** - vconfs match remote reality ✅
5. **Service Health** - nginx, php-fpm running ✅
6. **Security** - Web directory permissions (no SSH checks) ✅

### Firewall Configuration
```bash
# Allow root SSH from NetServa management system only
ufw allow from 203.0.113.10 to any port 22
ufw deny 22
```

---

## Future Enhancements

1. **Web File Manager** - Browser-based file management (if SSH disabled)
2. **SFTP Alternative** - Restricted SFTP-only accounts (no shell)
3. **Deployment Keys** - Git deploy keys for CI/CD (read-only)
4. **Monitoring Dashboard** - Real-time file/directory browser in Filament UI

---

## References

- Original User Request: "Security by default meaning only single ssh for root using firewall rules to only allow the NetServa central management system access so let's remove all customer SSH/SCP access and **IF** it's requested then can be manually enabled on a case be case basis."
- NetServa 3.0 Coding Style: `resources/docs/NetServa_3.0_Coding_Style.md`
- VHost Variables Reference: `resources/docs/VHOST-VARIABLES.md`

---

**Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)**
