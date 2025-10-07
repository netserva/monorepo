# NetServa 3.0 Platform Setup Guide

**Last Updated:** 2025-10-08
**Version:** 3.0.0
**Example VNode:** markc (Debian 13 Trixie)
**Example VHost:** markc.goldcoast.org

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Prerequisites](#prerequisites)
4. [Server Setup](#server-setup)
5. [NetServa Management](#netserva-management)
6. [VHost Configuration](#vhost-configuration)
7. [Service Stack](#service-stack)
8. [Verification](#verification)
9. [Troubleshooting](#troubleshooting)

---

## Overview

NetServa 3.0 Platform is a **database-first** infrastructure management system built on Laravel 12 + Filament 4.0. All configuration is stored in the central workstation's Laravel database, with remote execution via SSH to manage servers.

### Key Principles

- **Centralized Management:** All commands run from workstation (`~/.ns/`)
- **Database-First:** Configuration in Laravel database, not flat files
- **Remote Execution:** SSH-based management (phpseclib)
- **NetServa 3.0 Standards:** `/srv/` directory layout, standardized naming
- **54 Environment Variables:** Complete VHost configuration

---

## Architecture

### Central Workstation (NetServa Platform)
```
~/.ns/
├── database/database.sqlite          # Laravel app database
│   ├── fleet_vnodes                  # Server registry
│   ├── fleet_vhosts                  # Domain/VHost registry
│   │   └── environment_vars (JSON)   # 54 config variables per vhost
│   └── [other infrastructure tables]
├── artisan                           # Laravel CLI
└── packages/                         # 11 NetServa plugins
```

### Remote Server (markc Example)
```
Hostname: markc.goldcoast.org
IP: 192.168.1.227
OS: Debian 13 (Trixie)

/srv/markc.goldcoast.org/            # VHost directory
├── bin/                             # Custom scripts
├── etc/                             # Local configuration
├── msg/                             # Mail storage (Maildir)
├── web/                             # Web root
│   ├── app/public/                  # Document root
│   ├── log/                         # Nginx/PHP logs
│   ├── run/                         # PHP-FPM socket
│   └── tmp/                         # PHP temp files
└── [environment_vars in database]   # Not stored on disk
```

---

## Prerequisites

### Workstation Requirements
- **OS:** Linux (Arch/Debian/Ubuntu)
- **NetServa Platform:** `~/.ns/` (Laravel 12 application)
- **SSH Access:** Key-based authentication configured
- **Database:** SQLite (`~/.ns/database/database.sqlite`)

### Remote Server Requirements
- **OS:** Debian 11+, Alpine Linux, or Ubuntu
- **SSH:** Root access with key-based auth
- **Minimal Install:** NetServa will install required packages
- **Network:** Static IP or DHCP reservation

---

## Server Setup

### 1. Create Remote Server

**Option A: Incus Container**
```bash
# Launch Debian 13 container
incus launch images:debian/13/cloud markc

# Set hostname
incus exec markc -- hostnamectl set-hostname markc.goldcoast.org

# Configure network (if needed)
incus exec markc -- ip addr show
# Note the IP address (e.g., 192.168.1.227)
```

**Option B: VPS Provider**
```bash
# Create VPS via provider (BinaryLane, DigitalOcean, etc.)
# NetServa includes BinaryLane integration:
cd ~/.ns
php artisan binarylane:create markc --size=nanode-1 --region=syd
```

**Option C: Physical Server**
```bash
# Install Debian/Ubuntu minimal
# Configure static IP
# Enable SSH root access with key
```

### 2. Configure SSH Access

**Add to SSH configuration:**
```bash
# Create SSH host file
cat > ~/.ssh/hosts/markc <<'EOF'
Host markc
  Hostname 192.168.1.227
  Port 22
  User root
  IdentityFile ~/.ssh/keys/lan
  StrictHostKeyChecking accept-new
EOF

chmod 600 ~/.ssh/hosts/markc
```

**Test connection:**
```bash
ssh markc 'hostname && whoami'
# Output: markc.goldcoast.org
#         root
```

### 3. Initial System Configuration

```bash
# Via SSH (manual approach)
ssh markc '
  apt-get update
  apt-get upgrade -y
  hostnamectl set-hostname markc.goldcoast.org
  echo "127.0.1.1 markc.goldcoast.org markc" >> /etc/hosts
'

# OR via NetServa (automated approach - coming soon)
php artisan setup markc --os=debian --hostname=markc.goldcoast.org
```

---

## NetServa Management

### VNode Discovery

Before managing a server, discover its infrastructure:

```bash
# Discover VNode and all VHosts
cd ~/.ns
php artisan fleet:discover --vnode=markc

# This populates database:
# - fleet_vnodes (server metadata)
# - fleet_vhosts (domains on server)
# - environment_vars (54 variables per vhost)
```

**Result:**
```
VNode discovered: markc
VHosts found: 2
  - markc.goldcoast.org
  - mail.markc.goldcoast.org
```

### VHost Management Commands

All NetServa commands follow this pattern:
```bash
<command> <vnode> <vhost> [options]
```

**Create VHost:**
```bash
php artisan addvhost markc example.com
# Creates:
# - User (u1007)
# - Directory structure (/srv/example.com/)
# - Database entry in fleet_vhosts
# - Initializes 54 environment variables
```

**Show VHost:**
```bash
php artisan shvhost markc markc.goldcoast.org
# Displays:
# - VHost metadata
# - User information (u1006)
# - Directory paths
# - Service status
```

**Update VHost:**
```bash
php artisan chvhost markc markc.goldcoast.org --php-version=8.4
# Updates PHP-FPM pool configuration
```

**Delete VHost:**
```bash
php artisan delvhost markc markc.goldcoast.org
# Removes:
# - Database entries
# - User account
# - Directory structure (with confirmation)
```

### Permission Management

```bash
# Fix filesystem permissions
php artisan chperms markc markc.goldcoast.org

# This sets:
# - Owner: u1006:www-data (or appropriate UID)
# - Directories: 755
# - Files: 644
# - Web directories: 770 (log, run, tmp)
```

---

## VHost Configuration

### 54 Environment Variables

Every VHost has 54 configuration variables stored in `fleet_vhosts.environment_vars` JSON column.

**View configuration:**
```bash
php artisan shvconf markc markc.goldcoast.org
```

**Real Output (markc.goldcoast.org):**
```bash
ADMIN='sysadm'
AHOST='markc.local'
AMAIL='admin@markc.goldcoast.org'
ANAME='System Administrator'
APASS='xxxxxxxxxxxx'              # Password obfuscated
A_GID='1000'
A_UID='1000'
BPATH='/home/backups'
CIMAP='/etc/dovecot'
CSMTP='/etc/postfix'
C_DNS='/etc/powerdns'
C_FPM='/etc/php/8.2/fpm'
C_SQL='/etc/mysql'
C_SSL='/etc/ssl'
C_WEB='/etc/nginx'
DBMYS='/var/lib/mysql'
DBSQL='/var/lib/sqlite'
DHOST='localhost'
DNAME='markc_goldcoast_org'
DPASS='xxxxxxxxxxxx'              # Password obfuscated
DPATH='/var/lib/sqlite/sysadm/sysadm.db'
DPORT='3306'
DTYPE='mysql'
DUSER='u1006'
EPASS='xxxxxxxxxxxx'              # Password obfuscated
EXMYS='mariadb -BN sysadm'
EXSQL='sqlite3 /var/lib/sqlite/sysadm/sysadm.db'
HDOMN='goldcoast.org'
HNAME='markc'
IP4_0='192.168.1.227'
MHOST='markc.goldcoast.org'
MPATH='/srv/markc.goldcoast.org/msg'
OSMIR='deb.debian.org'
OSREL='trixie'
OSTYP='debian'
SQCMD='mariadb -BN sysadm'
SQDNS='mariadb -BN pdns'
TAREA='Australia'
TCITY='Sydney'
UPASS='xxxxxxxxxxxx'              # Password obfuscated
UPATH='/srv/markc.goldcoast.org'
UUSER='u1006'
U_GID='1006'
U_SHL='/bin/sh'
U_UID='1006'
VHOST='markc.goldcoast.org'
VNODE='markc'
VPATH='/srv'
VUSER='admin'
V_PHP='8.2'
WPASS='xxxxxxxxxxxx'              # Password obfuscated
WPATH='/srv/markc.goldcoast.org/web'
WPUSR='fnulqc'
WUGID='www-data'
```

### Configuration Management

**Initialize configuration:**
```bash
php artisan addvconf markc example.com
# Creates 54 default variables in database
```

**Update variable:**
```bash
php artisan chvconf markc example.com V_PHP 8.4
# Updates PHP version variable
```

**Delete variable:**
```bash
php artisan delvconf markc example.com V_PHP
# Removes specific variable (resets to default)
```

**Source configuration in bash:**
```bash
# For remote script execution
source <(php artisan shvconf markc markc.goldcoast.org)
echo $UPATH  # /srv/markc.goldcoast.org
echo $WPATH  # /srv/markc.goldcoast.org/web
```

### Variable Categories

1. **Admin Variables** (ADMIN, AHOST, AMAIL, ANAME, APASS, A_UID, A_GID)
2. **Backup** (BPATH)
3. **Config Paths** (CIMAP, CSMTP, C_DNS, C_FPM, C_SQL, C_SSL, C_WEB)
4. **Database** (DBMYS, DBSQL, DHOST, DNAME, DPASS, DPATH, DPORT, DTYPE, DUSER, EXMYS, EXSQL, SQCMD, SQDNS)
5. **Domain** (HDOMN, HNAME, MHOST, VHOST, VNODE)
6. **Mail** (MPATH, EPASS)
7. **Networking** (IP4_0)
8. **OS** (OSMIR, OSREL, OSTYP)
9. **Paths** (UPATH, VPATH, WPATH, MPATH)
10. **Timezone** (TAREA, TCITY)
11. **User** (UUSER, UPASS, U_UID, U_GID, U_SHL)
12. **Version** (V_PHP)
13. **Web** (WPASS, WPUSR, WUGID)

---

## Service Stack

### Web Stack (Nginx + PHP-FPM)

**Nginx Configuration:**
```bash
# NetServa uses variable-based configs
# /etc/nginx/common.conf uses $host variable
root /srv/$host/web/app/public;

# /etc/nginx/php.conf uses $host variable
fastcgi_pass unix:/srv/$host/web/run/fpm.sock;
```

**PHP-FPM Configuration:**
```bash
# NetServa uses variable-based pool configs
# /etc/php/8.4/fpm/common.conf uses $pool variable
listen = /srv/$pool/web/run/fpm.sock;

# Each domain gets its own pool:
# /etc/php/8.4/fpm/pool.d/markc.goldcoast.org.conf
[markc.goldcoast.org]
user = u1006
group = www-data
include = /etc/php/8.4/fpm/common.conf
```

### Mail Stack (Postfix + Dovecot)

**Database-Driven Configuration:**
```bash
# Postfix uses MySQL lookups to sysadm database
# /etc/postfix/mysql-virtual-domains.cf
query = SELECT domain FROM domains WHERE domain='%s' AND active=1

# Dovecot uses SQL auth
# /etc/dovecot/dovecot-sql.conf.ext
password_query = SELECT password FROM mailboxes WHERE username='%u' AND active=1
user_query = SELECT home, uid, gid, quota FROM mailboxes WHERE username='%u'
```

**Mail Storage:**
```
/srv/markc.goldcoast.org/msg/admin/Maildir/
├── cur/    # Current messages
├── new/    # New messages
└── tmp/    # Temporary files
```

### DNS Stack (PowerDNS)

**MySQL Backend:**
```bash
# PowerDNS uses MySQL for zone storage
# Database: powerdns
# Tables: domains, records
```

**API for ACME DNS-01:**
```bash
# PowerDNS API enabled on port 8081
# Used for automated Let's Encrypt DNS-01 challenges
```

### Database (MariaDB / SQLite)

**sysadm Database:**
- Purpose: Mail server runtime data
- Tables: domains, mailboxes, aliases
- See: [SYSADM-SCHEMA.md](SYSADM-SCHEMA.md)

**powerdns Database:**
- Purpose: DNS zone data
- Tables: domains, records, comments, etc.

---

## Verification

### Test VHost Configuration

```bash
# Check VHost exists in database
php artisan shvhost markc markc.goldcoast.org

# Verify environment variables
php artisan shvconf markc markc.goldcoast.org

# Check remote directory structure
ssh markc "ls -la /srv/markc.goldcoast.org/"
```

### Test Services

**Web Server:**
```bash
# Test HTTP
curl -I http://markc.goldcoast.org

# Test HTTPS
curl -I https://markc.goldcoast.org
```

**Mail Server:**
```bash
# Test SMTP
telnet markc.goldcoast.org 587

# Test IMAP
openssl s_client -connect markc.goldcoast.org:993
```

**DNS:**
```bash
# Test domain resolution
dig markc.goldcoast.org +short
# Output: 192.168.1.227

# Test mail MX record
dig MX goldcoast.org +short
# Output: 10 mail.goldcoast.org.
```

### Test Permissions

```bash
# Run permission check
php artisan chperms markc markc.goldcoast.org

# Verify on remote server
ssh markc "
  ls -ld /srv/markc.goldcoast.org/
  ls -l /srv/markc.goldcoast.org/web/
  ls -l /srv/markc.goldcoast.org/web/run/
"
```

---

## Troubleshooting

### Common Issues

**1. SSH Connection Failed**
```bash
# Test SSH connectivity
ssh -v markc

# Check SSH config
cat ~/.ssh/hosts/markc

# Verify key permissions
ls -la ~/.ssh/keys/
```

**2. VHost Not Found in Database**
```bash
# Re-discover VNode
php artisan fleet:discover --vnode=markc

# Check database directly
cd ~/.ns
php artisan tinker --execute="
  \$vhost = \NetServa\Fleet\Models\FleetVHost::where('domain', 'markc.goldcoast.org')->first();
  echo \$vhost ? 'Found' : 'Not found';
"
```

**3. Environment Variables Not Set**
```bash
# Initialize configuration
php artisan addvconf markc markc.goldcoast.org

# Verify variables exist
php artisan shvconf markc markc.goldcoast.org --table
```

**4. Permission Issues**
```bash
# Fix all permissions
php artisan chperms markc markc.goldcoast.org

# Check specific directory
ssh markc "stat /srv/markc.goldcoast.org/web/run/"
```

**5. PHP-FPM Socket Not Found**
```bash
# Check socket exists
ssh markc "ls -la /srv/markc.goldcoast.org/web/run/fpm.sock"

# Restart PHP-FPM
ssh markc "systemctl restart php8.2-fpm"

# Check logs
ssh markc "tail -f /srv/markc.goldcoast.org/web/log/php-errors.log"
```

### Diagnostic Commands

```bash
# View all VHosts on VNode
php artisan shvhost markc --list

# Check service status on remote server
ssh markc "systemctl status nginx php8.2-fpm mariadb postfix dovecot pdns"

# View Laravel logs
tail -f ~/.ns/storage/logs/laravel.log

# Test remote execution
php artisan tinker --execute="
  use NetServa\Cli\Services\RemoteExecutionService;
  \$remote = app(RemoteExecutionService::class);
  \$result = \$remote->exec('markc', 'hostname');
  echo \$result['output'];
"
```

### Log Locations

**Workstation:**
- Laravel logs: `~/.ns/storage/logs/laravel.log`
- Artisan output: Console

**Remote Server:**
- Nginx access: `/srv/markc.goldcoast.org/web/log/access.log`
- Nginx error: `/srv/markc.goldcoast.org/web/log/error.log`
- PHP-FPM errors: `/srv/markc.goldcoast.org/web/log/php-errors.log`
- Mail: `/var/log/mail.log`
- System: `/var/log/syslog`

---

## Advanced Topics

### Remote Script Execution

NetServa uses `RemoteExecutionService->executeScript()` for complex operations:

```php
use NetServa\Cli\Services\RemoteExecutionService;

$remote = app(RemoteExecutionService::class);
$result = $remote->executeScript(
    host: 'markc',
    script: <<<'BASH'
        #!/bin/bash
        set -euo pipefail

        # Your bash script here
        echo "Hello from $HOSTNAME"
        ls -la /srv/
        BASH,
    asRoot: true
);

if ($result['success']) {
    echo $result['output'];
} else {
    echo "Error: " . $result['error'];
}
```

### Environment Variable Injection

When using VHost methods, environment variables are automatically available:

```php
$result = $remote->executeScriptWithVhost(
    host: 'markc',
    vhost: $vhost,  // FleetVHost model
    script: <<<'BASH'
        #!/bin/bash
        # All 54 variables available as $VHOST, $WPATH, etc.
        cd "$WPATH"
        echo "Working in: $WPATH"
        echo "User: $UUSER ($U_UID:$U_GID)"
        BASH
);
```

### Custom Setup Scripts

```bash
# Create custom setup script for specific stack
php artisan setup markc \
    --type=wordpress \
    --domain=blog.example.com \
    --php=8.4 \
    --ssl=letsencrypt
```

---

## Reference Documentation

- **[NETSERVA-3.0-CONFIGURATION.md](NETSERVA-3.0-CONFIGURATION.md)** - Complete NS 3.0 architecture
- **[SYSADM-SCHEMA.md](SYSADM-SCHEMA.md)** - Mail database schema
- **[SSH_EXECUTION_ARCHITECTURE.md](../../docs/SSH_EXECUTION_ARCHITECTURE.md)** - Remote execution patterns
- **[VHOST-VARIABLES.md](../../docs/VHOST-VARIABLES.md)** - Complete variable reference

## Quick Reference

### Essential Commands

```bash
# Discover infrastructure
php artisan fleet:discover --vnode=markc

# VHost management (CRUD)
php artisan addvhost markc example.com
php artisan shvhost markc example.com
php artisan chvhost markc example.com --php=8.4
php artisan delvhost markc example.com

# Configuration management
php artisan shvconf markc example.com
php artisan addvconf markc example.com
php artisan chvconf markc example.com V_PHP 8.4
php artisan delvconf markc example.com V_PHP

# Utilities
php artisan chperms markc example.com
php artisan addvmail markc example.com user@example.com
```

### Directory Structure

```
/srv/example.com/              # VHost root
├── bin/                       # Custom scripts
├── etc/                       # Local config
├── msg/                       # Mail (Maildir)
│   └── user/Maildir/
├── web/                       # Web root
│   ├── app/public/           # Document root
│   ├── log/                  # Nginx/PHP logs
│   ├── run/                  # PHP-FPM socket
│   └── tmp/                  # PHP temp
```

### User/Group Structure

- **System Users:** sysadm (1000), www-data (33)
- **Domain Users:** u1001, u1002, u1003... (sequential)
- **Groups:** Match UID (u1001 in group 1001)
- **Web Group:** www-data (shared for all domains)

---

**End of NetServa 3.0 Setup Guide**

**Version:** 3.0.0
**Last Updated:** 2025-10-08
**Maintainer:** NetServa Platform Team
**License:** MIT
**Repository:** https://github.com/markc/ns
