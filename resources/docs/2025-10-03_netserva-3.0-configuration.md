# NetServa 3.0 Configuration Guide

**Version:** 3.0 (90% Canonical - Subject to Evolution)
**Last Updated:** 2025-10-03
**Status:** Production Reference

---

## Table of Contents

1. [Introduction & Philosophy](#introduction--philosophy)
2. [Supported Operating Systems](#supported-operating-systems)
3. [GitHub Repositories](#github-repositories)
4. [Workstation Directory Structure](#workstation-directory-structure)
5. [Remote Server Structure](#remote-server-structure)
6. [System Users & SSH Chroot](#system-users--ssh-chroot)
7. [Application Stack](#application-stack)
8. [Configuration Layout](#configuration-layout)
9. [SSL Certificates (acme.sh)](#ssl-certificates-acmesh)
10. [ACME Challenge & Autoconfig](#acme-challenge--autoconfig)
11. [DNS Infrastructure](#dns-infrastructure)
12. [Service Providers](#service-providers)
13. [Management Architecture](#management-architecture)
14. [Configuration Examples](#configuration-examples)

---

## Introduction & Philosophy

NetServa 3.0 is a **centrally-managed, domain-based server infrastructure** that differs from traditional Linux server layouts. Instead of scattered configurations across `/var/www`, `/etc/dovecot/conf.d/`, and `/var/vmail`, NetServa organizes everything by domain under `/srv/domain.com/`.

**Core Principles:**

- **Centralized Management**: All management from `~/.ns/` workstation - NO custom scripts on remote servers
- **Domain-Based Organization**: Each domain has complete isolation under `/srv/domain.com/`
- **Single-File Configs**: Dovecot uses one file, Nginx uses variables, PHP-FPM uses shared configs
- **User Isolation**: Each domain gets its own user (u1001, u1002...) with SSH chroot
- **Minimal Remote Footprint**: Servers only have `~/.rc/` utilities (rcm, sshm, _shrc)

**This document is 90% canonical with 10% allowance for evolution and optimization.**

---

## Supported Operating Systems

### Production Environments

**Primary: Debian 13 (Trixie)**
- Full-featured mail server (Postfix + Dovecot + Rspamd)
- Complete web stack (Nginx + PHP 8.4 + MariaDB)
- PowerDNS authoritative DNS

**Lightweight: Alpine Linux**
- Minimal footprint containers/VMs
- apk package manager
- Same directory structure

**Routers: OpenWRT**
- Requires bash + `~/.ns` installed
- Limited mail capabilities
- Web and DNS services

---

## GitHub Repositories

**Shell Utilities (rc):**
```
https://github.com/[user]/rc
```
- `rcm` - Resource/config manager
- `sshm` - SSH host manager
- `_shrc` - Shell functions and aliases

**Central Management (ns):**
```
https://github.com/[user]/ns
```
- Laravel 12 + Filament 4 web interface
- CLI management commands
- Infrastructure orchestration

---

## Workstation Directory Structure

### ~/.rc/ - Shell Utilities
```
~/.rc/
├── rcm             # Resource/configuration manager
├── sshm            # SSH host/key manager
├── _shrc           # Shell functions and aliases
├── _myrc           # User customization
├── LICENSE
└── README.md
```

**Purpose:** Foundational shell utilities used on both workstation and remote servers.

### ~/.ns/ - Central Management
```
~/.ns/
├── app/            # Laravel application code
├── packages/       # Filament plugin packages
│   ├── netserva-cli/
│   ├── netserva-core/
│   ├── netserva-fleet/
│   └── [other packages]
├── bin/            # Management commands (migrate, setup-host, ns)
├── config/         # Laravel configuration
├── database/       # Migrations and seeds
├── public/         # Web interface entry point
├── resources/      # Views and assets
├── routes/         # API and web routes
├── storage/        # Logs, cache, sessions
├── tests/          # Pest test suite
├── vendor/         # Composer dependencies
├── artisan         # Laravel CLI
├── composer.json
└── package.json
```

**Purpose:** Complete infrastructure management interface - both CLI and web.

### ~/.ssh/ - SSH 3.0 Layout
```
~/.ssh/
├── config          # Main SSH config (includes hosts/*)
├── hosts/          # Individual host configs
│   ├── motd
│   ├── nsorg
│   ├── mgo
│   └── [70+ hosts]
├── keys/           # SSH private keys
│   ├── lan
│   ├── auditlab
│   ├── disha
│   └── [other keys]
├── mux/            # ControlMaster socket directory
├── authorized_keys
└── known_hosts
```

**Main Config Example (~/.ssh/config):**
```ssh
# NetServa SSH Configuration
Ciphers aes128-ctr,aes192-ctr,aes256-ctr,aes128-gcm@openssh.com,aes256-gcm@openssh.com

# Include individual host configurations
Include ~/.ssh/hosts/*

# Global settings
Host *
  TCPKeepAlive yes
  ServerAliveInterval 30
  IdentitiesOnly yes
  User root
  ForwardAgent yes
  AddKeysToAgent yes
  ControlMaster auto
  ControlPath ~/.ssh/mux/%h_%p_%r
  ControlPersist 10m
```

**Host Config Example (~/.ssh/hosts/motd):**
```ssh
Host motd
  Hostname 192.168.1.250
  Port 22
  IdentityFile ~/.ssh/keys/lan
```

---

## Remote Server Structure

### /root/.rc/ - Minimal Utilities
```
/root/.rc/
├── rcm             # Synced from workstation
├── sshm            # Synced from workstation
└── _shrc           # Synced from workstation
```

**Critical:** Remote servers have NO custom management scripts - only minimal utilities synced from workstation.

### /srv/ - Domain-Based Organization
```
/srv/
├── domain.com/              # User domain (u1001:www-data)
│   ├── bin/                 # Domain-specific scripts
│   ├── etc/                 # Domain configuration files
│   ├── msg/                 # Mail storage (Postfix/Dovecot)
│   │   └── user@domain/
│   │       ├── Maildir/    # IMAP mail folders
│   │       │   ├── cur/
│   │       │   ├── new/
│   │       │   └── tmp/
│   │       ├── sieve/      # Mail filtering scripts
│   │       └── .spamprobe/ # Spam learning database
│   └── web/
│       ├── app/            # Application code
│       │   └── public/     # Nginx webroot
│       ├── log/            # Application logs
│       │   ├── access.log
│       │   ├── error.log
│       │   └── php-errors.log
│       ├── run/            # Runtime files
│       │   └── fpm.sock    # PHP-FPM socket
│       └── tmp/            # Temporary/session files
│
└── mail.domain.com/         # Mail domain (sysadm:sysadm)
    └── [same structure as above]
```

**Key Differences from Traditional:**
- `/var/www/` → `/srv/domain.com/web/app/public/`
- `/var/vmail/` → `/srv/domain.com/msg/`
- `/etc/dovecot/conf.d/*.conf` → `/etc/dovecot/dovecot.conf` (single file)
- Multiple PHP-FPM pools → Domain pools with shared config

---

## System Users & SSH Chroot

### User Structure
```
sysadm:1000:1000   # Primary mail domain
  Home: /srv/mail.domain.com
  Shell: /bin/bash
  Purpose: Main mail server administration

u1001:1001:1001    # First user domain
  Home: /srv/domain.com
  Shell: /bin/sh
  Purpose: Isolated web/mail hosting (CHROOTED)

u1002:1002:1002    # Second user domain
  Home: /srv/another.com
  Shell: /bin/sh
  Purpose: Isolated web/mail hosting (CHROOTED)
```

**Pattern:** Users start at u1001 and increment. Each gets their own domain directory.

### SSH Chroot Configuration

**/etc/ssh/sshd_config:**
```ssh
# Chroot all u* users to their home directory
Match User u*
  ChrootDirectory %h
  X11Forwarding no
  AllowTcpForwarding no

# Chroot all b* users (backup/batch users)
Match User b*
  ChrootDirectory %h
  X11Forwarding no
  AllowTcpForwarding no

# Modern security - using default port 22 with firewalld
```

**Purpose:** Complete isolation for each domain user - they can only access their own `/srv/domain.com/` directory.

---

## Application Stack

### Mail Server Options

#### Option A: Full-Featured (Rspamd)
```
✓ Postfix (SMTP)
✓ Dovecot 2.4.1 (IMAP/LMTP)
✓ Rspamd (spam filtering, DKIM signing)
✗ OpenDKIM - NOT needed with Rspamd
✗ OpenDMARC - NOT needed with Rspamd
```

#### Option B: Lightweight (SpamProbe)
```
✓ Postfix (SMTP)
✓ Dovecot 2.4.1 (IMAP/LMTP)
✓ SpamProbe (lightweight Bayesian filter)
✓ OpenDKIM (REQUIRED for DKIM signing)
✓ OpenDMARC (REQUIRED for DMARC validation)
```

### Required Packages

**Web Stack:**
- nginx
- php8.4-fpm
- php8.4-cli
- php8.4-bcmath, php8.4-curl, php8.4-gd, php8.4-gmp
- php8.4-igbinary, php8.4-imagick, php8.4-intl
- php8.4-mbstring, php8.4-mysql, php8.4-opcache
- php8.4-redis, php8.4-soap, php8.4-sqlite3
- php8.4-xml, php8.4-zip

**Database:**
- mariadb-server
- mariadb-client

**DNS:**
- pdns (PowerDNS - authoritative)
- pdns-backend-mysql

**SSL:**
- acme.sh (installed to /usr/share/acme.sh/)

**System Utilities:**
- rsyslog (logging)
- logrotate (log rotation)
- cron (scheduling)
- rsync (file synchronization)
- nano (text editor)

---

## Configuration Layout

### Nginx Custom Structure

```
/etc/nginx/
├── nginx.conf              # Main configuration
├── common.conf             # Shared vhost settings (uses $host)
├── php.conf                # PHP-FPM configuration (uses $host)
├── headers.conf            # Security headers
├── hcp.conf                # Control panel config
├── fastcgi.conf            # FastCGI parameters
├── sites-available/
│   └── [templates]
└── sites-enabled/
    ├── _default            # ACME challenge handler
    ├── mail.domain.com
    └── domain.com
```

**common.conf** (uses $host variable):
```nginx
# .sh/etc/_etc_nginx_common.conf
root                            /srv/$host/web/app/public;
index                           index.html index.php;
error_page                      500 502 503 504 /50x.html;
location                        = /50x.html { root /usr/share/nginx/html; }
location                        = /robots.txt { access_log off; log_not_found off; }
location                        = /favicon.ico { access_log off; log_not_found off; }
location                        ~ /\.well-known/ { allow all; }
location                        ~ /\. { deny all; access_log off; log_not_found off; }
location                        / { try_files $uri $uri/ /index.php$is_args$args; }
include                         /etc/nginx/php.conf;
```

**php.conf** (uses $host variable):
```nginx
# .sh/etc/_etc_nginx_php.conf
location                        ~ ^(.+\.php)(.*)$ {
    try_files                   $uri $uri/ index.php$is_args$args =404;
    fastcgi_split_path_info     ^(.+\.php)(/.+)$;
    fastcgi_pass                unix:/srv/$host/web/run/fpm.sock;
    fastcgi_index               index.php;
    include                     fastcgi_params;
    include                     /etc/nginx/headers.conf;
    fastcgi_read_timeout        300;
    fastcgi_param               PATH_INFO $fastcgi_path_info;
    fastcgi_param               SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param               SERVER_NAME $host;
}
```

### PHP-FPM Custom Structure

```
/etc/php/8.4/fpm/
├── php.ini
├── pool.d/
│   ├── domain.com.conf         # 3-line pool config
│   └── mail.domain.com.conf
└── common.conf                  # Shared pool settings (uses $pool)
```

**domain.com.conf** (minimal - 3 lines):
```ini
[domain.com]
user = u1001
group = www-data
include = /etc/php/8.4/fpm/common.conf
```

**common.conf** (uses $pool variable):
```ini
; .sh/etc/_etc_php_8.4_fpm_common.conf
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
listen = /srv/$pool/web/run/fpm.sock

env[HOSTNAME] = $pool
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /srv/$pool/web/tmp
env[TMPDIR] = /srv/$pool/web/tmp

pm = ondemand
pm.max_children = 100
pm.process_idle_timeout = 10s
pm.max_requests = 500

ping.path = /fpm-ping
pm.status_path = /fpm-status

php_value[upload_max_filesize] = 1280M
php_value[post_max_size] = 1280M
php_value[max_execution_time] = 300
php_value[date.timezone] = Australia/Sydney
php_value[error_log] = /srv/$pool/web/log/php-errors.log
php_value[upload_tmp_dir] = /srv/$pool/web/tmp
php_value[session.save_path] = /srv/$pool/web/tmp
```

### Dovecot Single-File Configuration

**CRITICAL:** Dovecot uses ONE configuration file - NO `/etc/dovecot/conf.d/` directory structure.

```
/etc/dovecot/
├── dovecot.conf        # SINGLE FILE (~180-285 lines)
├── dh.pem              # Diffie-Hellman parameters
└── sieve/              # Global Sieve scripts
    ├── global.sieve
    ├── retrain-as-ham.sieve
    └── retrain-as-spam.sieve
```

**dovecot.conf structure:**
```
# Dovecot 2.4.1 Single-File Configuration
dovecot_config_version = 2.4.1

# SSL configuration
ssl = required
ssl_min_protocol = TLSv1.2
ssl_server_cert_file = /etc/ssl/mail.domain.com/mail.domain.com.crt
ssl_server_key_file = /etc/ssl/mail.domain.com/mail.domain.com.key

# Logging to syslog
log_path = syslog
syslog_facility = mail

# Authentication & user database
passdb { driver = sql; args = /etc/dovecot/dovecot-sql.conf }
userdb { driver = sql; args = /etc/dovecot/dovecot-sql.conf }

# Mail location
mail_location = maildir:/srv/%d/msg/%n/Maildir

# Protocols
protocols = imap lmtp sieve

# Sieve configuration
sieve_global_script_after = /etc/dovecot/sieve/global.sieve
```

---

## SSL Certificates (acme.sh)

### Installation
```bash
# System-wide installation
/usr/share/acme.sh/acme.sh --install \
  --home /usr/share/acme.sh \
  --config-home /etc/acme.sh \
  --cert-home /etc/acme.sh/certs

# Symlink to /usr/bin
ln -s /usr/share/acme.sh/acme.sh /usr/bin/acme.sh
```

### Directory Structure
```
/etc/ssl/
├── certs/
│   ├── domain.com.crt          # Certificate only
│   └── domain.com.fullchain.crt # Certificate + intermediates
└── private/
    └── domain.com.key           # Private key (600 permissions)
```

### Certificate Issuance & Deployment
```bash
# Issue certificate
acme.sh --issue -d domain.com -w /srv/domain.com/web/app/public

# Deploy to /etc/ssl with automatic reload
acme.sh --install-cert -d domain.com \
  --fullchain-file /etc/ssl/certs/domain.com.fullchain.crt \
  --key-file /etc/ssl/private/domain.com.key \
  --reloadcmd "systemctl reload nginx postfix dovecot"
```

**Key Points:**
- Always use `--fullchain-file` for intermediate certificates
- Deploy to `/etc/ssl/{certs,private}/` NOT acme.sh directories
- Single `--reloadcmd` reloads all SSL-dependent services

### Automation
```cron
# /etc/cron.d/acme or crontab -l
3 21 * * * /usr/bin/acme.sh --cron > /dev/null
```

---

## ACME Challenge & Autoconfig

### _default Virtual Host

**/etc/nginx/sites-enabled/_default** handles ACME challenges and mail autoconfiguration for ALL domains:

```nginx
# Default ACME challenge handler for all domains
server {
    listen                      80;
    server_name                 _;

    # ACME challenge - redirect to primary domain
    location                    ^~ /.well-known/acme-challenge/ {
        root /srv/mail.domain.com/web/app/public;
        break;
    }

    # Mail client autoconfiguration (Thunderbird, Outlook)
    location                    ~* (/.well-known/autoconfig/|/mail/config-v1.1.xml|/autodiscover/autodiscover.xml) {
        root                    /srv/mail.domain.com/web/app/public/.well-known/;
        try_files               /autodiscover.php =404;
        fastcgi_pass            unix:/srv/mail.domain.com/web/run/fpm.sock;
        include                 fastcgi.conf;
        fastcgi_param           SERVER_ADDR "";
        fastcgi_param           REMOTE_ADDR $http_x_real_ip;
        break;
    }

    # Redirect all other HTTP to HTTPS
    location                    / {
        return 301 https://$host$request_uri;
    }
}
```

**Purpose:**
- Centralized ACME challenge handling for all domains
- Mail client autoconfiguration (autoconfig.xml, autodiscover.xml)
- HTTP to HTTPS redirect

---

## DNS Infrastructure

### Authoritative Name Servers

**goldcoast.org DNS Servers:**
```
ns1.goldcoast.org  →  119.42.55.148
ns2.goldcoast.org  →  175.45.182.28
ns3.goldcoast.org  →  103.16.131.18
```

**Local LAN DNS:**
```
gw.goldcoast.org   →  192.168.1.1 (router/gateway)
```

### DNS Software Stack

**Primary:** PowerDNS (pdns)
- Authoritative DNS server
- MySQL backend for zone storage
- API for dynamic updates
- DNSSEC support

**Secondary:** Cloudflare
- Some domains use Cloudflare DNS
- CDN and DDoS protection
- API integration for automated updates

**Template Zone:** goldcoast.org
- Complete DNS record set example
- A, AAAA, MX, TXT, CNAME, NS records
- SPF, DKIM, DMARC configuration
- CAA records for SSL validation

### Typical DNS Record Structure
```
domain.com.              IN  A      192.168.1.250
mail.domain.com.         IN  A      192.168.1.250
www.domain.com.          IN  CNAME  domain.com.

domain.com.              IN  MX  10 mail.domain.com.

domain.com.              IN  TXT    "v=spf1 mx -all"
_dmarc.domain.com.       IN  TXT    "v=DMARC1; p=reject; rua=mailto:postmaster@domain.com"
default._domainkey       IN  TXT    "v=DKIM1; k=rsa; p=..."

domain.com.              IN  CAA    0 issue "letsencrypt.org"
domain.com.              IN  CAA    0 issuewild "letsencrypt.org"
```

---

## Service Providers

### Infrastructure Providers

**VPS Hosting:**
- **Primary:** BinaryLane (Australia)
- Sydney, Melbourne, Brisbane datacenters
- API integration for provisioning

**Domain Registrar:**
- **Primary:** Synergy Wholesale
- .com.au, .com, .net, .org domains
- API for DNS management

**SSL Certificates:**
- Let's Encrypt (via acme.sh)
- Free, automated, 90-day certificates
- Automatic renewal via cron

**DNS Hosting:**
- Self-hosted PowerDNS (primary)
- Cloudflare (secondary/some domains)
- ns{1,2,3}.goldcoast.org authoritative servers

---

## Management Architecture

### Centralized Workstation Control

**All infrastructure management originates from the workstation at `~/.ns/`**

```
┌─────────────────────────────────────┐
│  Workstation (~/.ns/)               │
│                                     │
│  ┌───────────────────────────────┐ │
│  │ Laravel + Filament Web UI     │ │
│  │ http://localhost:8888         │ │
│  └───────────────────────────────┘ │
│                                     │
│  ┌───────────────────────────────┐ │
│  │ CLI Commands                  │ │
│  │ • migrate assess/execute      │ │
│  │ • setup-host domain.com       │ │
│  │ • ns mount/ssh/status         │ │
│  │ • sshm create/list/connect    │ │
│  └───────────────────────────────┘ │
│                                     │
│       ↓ SSH Connection ↓           │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Remote Server (/root/.rc/)         │
│                                     │
│  ┌───────────────────────────────┐ │
│  │ Minimal Utilities Only:       │ │
│  │ • rcm  (resource manager)     │ │
│  │ • sshm (SSH manager)          │ │
│  │ • _shrc (shell functions)     │ │
│  └───────────────────────────────┘ │
│                                     │
│  NO custom management scripts       │
│  NO local configuration state       │
│  Stateless - managed via SSH        │
└─────────────────────────────────────┘
```

### Key Management Commands

**Infrastructure Management:**
```bash
# Assess server for migration
migrate ns2 assess

# Execute migration
migrate ns2 execute

# Setup new host
setup-host domain.com

# Mount remote filesystem
ns mount ns2

# SSH to host
ns ssh ns2

# Infrastructure status
ns status
```

**SSH Management:**
```bash
# Create new SSH host
sshm create motd 192.168.1.250 22 root lan

# Generate SSH key
sshm key_create lan

# List hosts
sshm list

# Connect to host
sshm connect motd
```

---

## Configuration Examples

### SSH 3.0 Host Configuration

**~/.ssh/hosts/motd:**
```ssh
Host motd
  Hostname 192.168.1.250
  Port 22
  IdentityFile ~/.ssh/keys/lan
```

**~/.ssh/hosts/nsorg:**
```ssh
Host nsorg
  Hostname 103.16.131.18
  Port 9
  IdentityFile ~/.ssh/keys/ns
```

### Nginx Virtual Host

**sites-enabled/domain.com:**
```nginx
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name domain.com www.domain.com;

    ssl_certificate /etc/ssl/certs/domain.com.fullchain.crt;
    ssl_certificate_key /etc/ssl/private/domain.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    include /etc/nginx/common.conf;
}
```

### PHP-FPM Pool

**pool.d/domain.com.conf:**
```ini
[domain.com]
user = u1001
group = www-data
include = /etc/php/8.4/fpm/common.conf
```

### Postfix Virtual Transport

**main.cf:**
```
virtual_mailbox_base =
virtual_transport = lmtp:unix:private/dovecot-lmtp
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-alias-maps.cf
```

### Dovecot LMTP

**dovecot.conf:**
```
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}

protocol lmtp {
  mail_plugins = $mail_plugins sieve
  postmaster_address = postmaster@%d
}
```

### Rsyslog Mail Logging

**/etc/rsyslog.d/30-mail.conf:**
```
# Postfix and Dovecot to /var/log/mail.log
if $programname == "postfix" then /var/log/mail.log
& stop

if $programname == "dovecot" then /var/log/mail.log
& stop
```

### Logrotate Mail Logs

**/etc/logrotate.d/rsyslog:**
```
/var/log/mail.log {
    rotate 4
    weekly
    missingok
    notifempty
    compress
    delaycompress
    sharedscripts
    postrotate
        /usr/lib/rsyslog/rsyslog-rotate
    endscript
}
```

### Cron Jobs

**/etc/cron.d/netserva:**
```
# Cleanup old sessions
11 * * * * root find /srv/*/web/tmp -name 'sess_*' -type f -cmin '+240' -delete

# Expunge old Junk mail
10 0 * * * root doveadm expunge -A mailbox Junk savedbefore 7d

# Expunge old Trash mail
20 0 * * * root doveadm expunge -A mailbox Trash savedbefore 7d

# SSL certificate renewal
3 21 * * * root /usr/bin/acme.sh --cron > /dev/null
```

---

## Evolution & Extensions

**Document Status:** Version 3.0 - 90% Canonical

**This configuration represents the stable, production-tested NetServa 3.0 architecture.** While the core structure is locked down, the following areas remain subject to refinement:

### Core (90% - Stable)
- Directory structure (`/srv/domain.com/`)
- User system (sysadm, u1001+)
- SSH chroot configuration
- Single-file Dovecot config
- Nginx/PHP-FPM variable-based configs
- acme.sh deployment structure
- Workstation management model

### Evolution Areas (10%)
- Additional Filament packages
- Enhanced automation scripts
- Performance optimizations
- Security hardening updates
- Support for new OS versions
- Integration with additional providers

### Version Control
- **Repository:** https://github.com/[user]/ns
- **Issues/Features:** GitHub Issues
- **Pull Requests:** For proposed changes
- **Documentation:** This file is the canonical reference

### Contributing
All modifications should:
1. Preserve core directory structure
2. Maintain backward compatibility
3. Update this documentation
4. Include test coverage
5. Follow existing conventions

---

**End of NetServa 3.0 Configuration Guide**

*For support, issues, or feature requests, visit: https://github.com/[user]/ns*
