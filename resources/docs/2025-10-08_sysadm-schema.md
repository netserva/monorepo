# NetServa 3.0 sysadm Database Schema

**Last Updated:** 2025-10-08
**Version:** 3.0
**Canonical Source:** [`sysadm.dbml`](../../sysadm.dbml)

## Overview

The `sysadm` database is the **runtime configuration database** for NetServa 3.0 mail servers deployed on remote systems. This is separate from the central workstation's Laravel database which manages infrastructure metadata.

## Architecture Context

### Central Workstation (NetServa Platform)
```
~/.ns/
├── database/database.sqlite          # Laravel app database
│   ├── fleet_vnodes                  # Server/VNode registry
│   ├── fleet_vhosts                  # Domain/VHost registry
│   └── [other infrastructure tables]
├── sysadm.dbml                       # Canonical mail schema (THIS DOC)
└── resources/docs/
    ├── SYSADM-SCHEMA.md             # This documentation
    ├── netserva-schema-sqlite.sql   # Generated from DBML
    └── netserva-schema-mariadb.sql  # Generated from DBML
```

### Remote Server (Mail Server Runtime)
```
/var/lib/sqlite/sysadm/sysadm.db      # SQLite deployment
    OR
MySQL sysadm database                  # MySQL/MariaDB deployment

/srv/{domain}/                         # Mail storage (NetServa 3.0)
├── mail/{user}/Maildir/              # Mailbox files
├── msg/{user}/                       # Alternative: message storage
└── web/                              # Web files (optional)
```

## Purpose & Scope

### What sysadm Database Stores
- **Virtual mail domains** - Hosting configuration for domains
- **Email mailboxes** - User accounts with passwords and quotas
- **Email aliases** - Forwarding rules and catch-alls

### What it Does NOT Store
- **Infrastructure metadata** - That's in `~/.ns/database/database.sqlite` (fleet_vnodes, fleet_vhosts)
- **SSH configurations** - That's in `~/.ssh/hosts/` files managed by SshConfigService
- **Environment variables** - That's in `fleet_vhosts.environment_vars` JSON column (database-first)
- **Credentials** - Generated on-demand, stored in database or deployment configs

## Real-World Example: markc VNode

**Central Workstation Database:**
```sql
-- In ~/.ns/database/database.sqlite

-- VNode (Server) Registry
SELECT * FROM fleet_vnodes WHERE name = 'markc';
-- Result: id=1, name='markc', ssh_host='markc', ...

-- VHost (Domain) Registry
SELECT * FROM fleet_vhosts WHERE vnode_id = 1;
-- Results:
--   domain='markc.goldcoast.org', vnode_id=1
--   domain='example.com', vnode_id=1
```

**Remote Server (markc) sysadm Database:**
```sql
-- On remote server: /var/lib/sqlite/sysadm/sysadm.db

-- Mail Domains
SELECT * FROM domains WHERE domain = 'markc.goldcoast.org';
-- domain: markc.goldcoast.org
-- uid: 1001 (u1001)
-- gid: 33 (www-data)
-- maxquota: 0 (unlimited)
-- active: 1

-- Mailboxes
SELECT * FROM mailboxes WHERE domain = 'markc.goldcoast.org';
-- username: admin@markc.goldcoast.org
-- password: {BLF-CRYPT}$2y$...
-- maildir: markc.goldcoast.org/mail/admin/
-- quota: 524288000 (500MB)

-- Aliases
SELECT * FROM aliases WHERE domain = 'markc.goldcoast.org';
-- address: postmaster@markc.goldcoast.org
-- goto: admin@markc.goldcoast.org
```

## Schema Tables

### 1. domains
Virtual mail domains with filesystem ownership.

**Purpose:** Define which domains the mail server handles and their storage configuration.

**Fields:**
- `domain` (PK, VARCHAR 255) - Domain name (e.g., markc.goldcoast.org)
- `uid` (INT) - Unix user ID for filesystem ownership (e.g., 1001 for u1001)
- `gid` (INT) - Unix group ID for filesystem ownership (e.g., 33 for www-data)
- `maxquota` (BIGINT) - Maximum domain quota in bytes (0 = unlimited)
- `active` (TINYINT) - Active flag (1=active, 0=disabled)
- `created_at` (TIMESTAMP) - Creation timestamp
- `updated_at` (TIMESTAMP) - Last update timestamp

**Example:**
```sql
INSERT INTO domains (domain, uid, gid, maxquota, active, created_at, updated_at)
VALUES ('markc.goldcoast.org', 1001, 33, 0, 1, NOW(), NOW());
```

### 2. mailboxes
Virtual mailboxes for email users.

**Purpose:** Define email accounts, passwords, and quotas.

**Fields:**
- `username` (PK, VARCHAR 255) - Full email address (e.g., admin@markc.goldcoast.org)
- `password` (VARCHAR 255) - Encrypted password hash (BLF-CRYPT, SHA256-CRYPT)
- `maildir` (VARCHAR 255) - Maildir path (e.g., markc.goldcoast.org/mail/admin/)
- `domain` (VARCHAR 255) - Domain name (redundant for performance)
- `uid` (INT) - Unix ownership (redundant for performance)
- `gid` (INT) - Unix ownership (redundant for performance)
- `quota` (BIGINT) - Mailbox quota in bytes (default: 524288000 = 500MB)
- `active` (TINYINT) - Active flag
- `created_at` (TIMESTAMP) - Creation timestamp
- `updated_at` (TIMESTAMP) - Last update timestamp

**Design Note:** Redundant `domain`/`uid`/`gid` fields enable zero-JOIN queries for maximum Postfix/Dovecot performance.

**Example:**
```sql
INSERT INTO mailboxes (username, password, maildir, domain, uid, gid, quota, active, created_at, updated_at)
VALUES (
    'admin@markc.goldcoast.org',
    '{BLF-CRYPT}$2y$05$...',
    'markc.goldcoast.org/mail/admin/',
    'markc.goldcoast.org',
    1001,
    33,
    524288000,
    1,
    NOW(),
    NOW()
);
```

### 3. aliases
Email aliases and forwarding rules.

**Purpose:** Define email forwarding, catch-alls, and mailing lists.

**Fields:**
- `address` (PK, VARCHAR 255) - Email address or wildcard (e.g., @markc.goldcoast.org for catch-all)
- `goto` (TEXT) - Destination email(s), comma-separated for multiple
- `domain` (VARCHAR 255) - Domain name (redundant for performance)
- `active` (TINYINT) - Active flag
- `created_at` (TIMESTAMP) - Creation timestamp
- `updated_at` (TIMESTAMP) - Last update timestamp

**Examples:**
```sql
-- Simple alias
INSERT INTO aliases (address, goto, domain, active, created_at, updated_at)
VALUES ('postmaster@markc.goldcoast.org', 'admin@markc.goldcoast.org', 'markc.goldcoast.org', 1, NOW(), NOW());

-- Catch-all
INSERT INTO aliases (address, goto, domain, active, created_at, updated_at)
VALUES ('@markc.goldcoast.org', 'admin@markc.goldcoast.org', 'markc.goldcoast.org', 1, NOW(), NOW());

-- Mailing list (multiple destinations)
INSERT INTO aliases (address, goto, domain, active, created_at, updated_at)
VALUES ('team@markc.goldcoast.org', 'user1@example.com,user2@example.com', 'markc.goldcoast.org', 1, NOW(), NOW());
```

## Database Deployment Options

### Option 1: SQLite (Recommended for Single-Server)
**Location:** `/var/lib/sqlite/sysadm/sysadm.db`

**Advantages:**
- No separate database server needed
- Excellent performance for single-server deployments
- Zero configuration
- Automatic file-based backups

**Disadvantages:**
- Single-server only (no clustering)
- No network access (local only)

**Setup:**
```bash
# Create directory
mkdir -p /var/lib/sqlite/sysadm

# Import schema
sqlite3 /var/lib/sqlite/sysadm/sysadm.db < ~/.ns/resources/docs/netserva-schema-sqlite.sql

# Set permissions
chown -R dovecot:dovecot /var/lib/sqlite/sysadm
chmod 640 /var/lib/sqlite/sysadm/sysadm.db
```

### Option 2: MySQL/MariaDB (For Multi-Server or High-Volume)
**Database:** `sysadm` (or `mail_{domain}` for multi-tenant)

**Advantages:**
- Multi-server support (clustering)
- Better for high-volume environments
- Advanced features (replication, partitioning)
- Network access for remote management

**Disadvantages:**
- Requires separate database server
- More complex configuration
- Additional resource overhead

**Setup:**
```bash
# Create database
mysql -e "CREATE DATABASE sysadm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql sysadm < ~/.ns/resources/docs/netserva-schema-mariadb.sql

# Create database users
mysql -e "
CREATE USER 'postfix'@'localhost' IDENTIFIED BY '$(openssl rand -base64 12)';
CREATE USER 'dovecot'@'localhost' IDENTIFIED BY '$(openssl rand -base64 12)';
CREATE USER 'netserva'@'localhost' IDENTIFIED BY '$(openssl rand -base64 12)';

GRANT SELECT ON sysadm.* TO 'postfix'@'localhost';
GRANT SELECT ON sysadm.mailboxes TO 'dovecot'@'localhost';
GRANT ALL ON sysadm.* TO 'netserva'@'localhost';

FLUSH PRIVILEGES;
"
```

## Integration with Postfix/Dovecot

### Postfix Configuration
**Files:** `/etc/postfix/mysql-*.cf` or `/etc/postfix/sqlite-*.cf`

**Example: Virtual Domains (SQLite)**
```conf
# /etc/postfix/sqlite-virtual-domains.cf
dbpath = /var/lib/sqlite/sysadm/sysadm.db
query = SELECT domain FROM domains WHERE domain='%s' AND active=1
```

**Example: Virtual Mailboxes (SQLite)**
```conf
# /etc/postfix/sqlite-virtual-mailboxes.cf
dbpath = /var/lib/sqlite/sysadm/sysadm.db
query = SELECT maildir FROM mailboxes WHERE username='%s' AND active=1
```

**Example: Virtual Aliases (SQLite)**
```conf
# /etc/postfix/sqlite-virtual-aliases.cf
dbpath = /var/lib/sqlite/sysadm/sysadm.db
query = SELECT goto FROM aliases WHERE address='%s' AND active=1
```

### Dovecot Configuration
**File:** `/etc/dovecot/dovecot-sql.conf.ext`

**Example (SQLite):**
```conf
driver = sqlite
connect = /var/lib/sqlite/sysadm/sysadm.db

password_query = \
  SELECT username as user, password \
  FROM mailboxes \
  WHERE username = '%u' AND active = 1

user_query = \
  SELECT \
    maildir as home, \
    uid, \
    gid \
  FROM mailboxes \
  WHERE username = '%u' AND active = 1

iterate_query = \
  SELECT username as user \
  FROM mailboxes \
  WHERE active = 1
```

## Password Generation

Always use strong password hashing for mailboxes.

### Using doveadm
```bash
# Generate BLF-CRYPT hash (recommended - strongest)
doveadm pw -s BLF-CRYPT
# Example output: {BLF-CRYPT}$2y$05$randomsalt$hashedpassword...

# Generate SHA256-CRYPT hash (good alternative)
doveadm pw -s SHA256-CRYPT
# Example output: {SHA256-CRYPT}$5$rounds=5000$randomsalt$hashedpassword...
```

### Using PHP (Laravel)
```php
// NetServa uses Laravel's password hashing
use Illuminate\Support\Facades\Hash;

$password = Hash::make($plainPassword);
// Stores as bcrypt: $2y$12$...
```

**Never use:**
- PLAIN passwords
- MD5 hashes
- Weak encryption schemes

## NetServa 3.0 Management Commands

NetServa Platform provides Laravel commands to manage sysadm database:

```bash
# Add virtual host (creates domain entry)
php artisan addvhost markc markc.goldcoast.org

# Add mailbox (creates mailbox entry)
php artisan addvmail markc markc.goldcoast.org admin@markc.goldcoast.org

# Show virtual hosts
php artisan shvhost markc

# Change permissions
php artisan chperms markc markc.goldcoast.org
```

These commands use `RemoteExecutionService` to execute SQL commands on remote servers via SSH.

## Converting DBML to SQL

### Method 1: Online Converter
Visit: https://dbml.dbdiagram.io/
1. Paste `sysadm.dbml` contents
2. Export to MySQL or PostgreSQL
3. Manually adjust for SQLite if needed

### Method 2: CLI Tool (dbml-cli)
```bash
# Install
npm install -g @dbml/cli

# Generate MySQL
dbml2sql sysadm.dbml --mysql -o resources/docs/netserva-schema-mariadb.sql

# Generate PostgreSQL
dbml2sql sysadm.dbml --postgres -o resources/docs/netserva-schema-postgres.sql

# For SQLite, use MySQL output and manually adjust
```

### Method 3: Use Pre-Generated Files
```bash
# Pre-generated SQL files available:
~/.ns/resources/docs/netserva-schema-sqlite.sql
~/.ns/resources/docs/netserva-schema-mariadb.sql
```

## Migration from Legacy NetServa

### From NetServa 2.x (vmails table)
```sql
-- Rename table
RENAME TABLE vmails TO mailboxes;

-- Rename columns if needed
ALTER TABLE mailboxes CHANGE COLUMN user username VARCHAR(255);

-- Add Laravel timestamps
ALTER TABLE mailboxes
  ADD COLUMN created_at TIMESTAMP NULL,
  ADD COLUMN updated_at TIMESTAMP NULL;

-- Add indexes
CREATE INDEX idx_mailbox_domain ON mailboxes(domain);
CREATE INDEX idx_mailbox_active ON mailboxes(active);
```

### From Old Directory Structure
```bash
# Old: /home/u/domain.com/
# New: /srv/domain.com/

# Update maildir paths in database
UPDATE mailboxes
SET maildir = REPLACE(maildir, '/home/u/', '/srv/')
WHERE maildir LIKE '/home/u/%';
```

## Security Best Practices

### 1. Database User Privileges
Follow principle of least privilege:

```sql
-- Postfix: Read-only access to lookup tables
GRANT SELECT ON sysadm.domains TO 'postfix'@'localhost';
GRANT SELECT ON sysadm.mailboxes TO 'postfix'@'localhost';
GRANT SELECT ON sysadm.aliases TO 'postfix'@'localhost';

-- Dovecot: Read-only access to mailboxes only
GRANT SELECT ON sysadm.mailboxes TO 'dovecot'@'localhost';

-- NetServa Management: Full access for administrative tasks
GRANT ALL ON sysadm.* TO 'netserva'@'localhost';
```

### 2. Password Hashing
- **Required:** BLF-CRYPT or SHA256-CRYPT minimum
- **Never:** PLAIN, MD5, weak schemes

### 3. Network Access
- **Default:** Bind database to localhost (127.0.0.1)
- **Multi-server:** Use encrypted connections (SSL/TLS)
- **Firewall:** Restrict database port access

### 4. File Permissions (SQLite)
```bash
# Database file
chown dovecot:dovecot /var/lib/sqlite/sysadm/sysadm.db
chmod 640 /var/lib/sqlite/sysadm/sysadm.db

# Directory
chmod 750 /var/lib/sqlite/sysadm
```

### 5. Regular Backups
```bash
# SQLite
sqlite3 /var/lib/sqlite/sysadm/sysadm.db .dump > sysadm-backup-$(date +%Y%m%d).sql

# MySQL
mysqldump sysadm > sysadm-backup-$(date +%Y%m%d).sql
```

## Troubleshooting

### Check Database Connectivity
```bash
# SQLite
sqlite3 /var/lib/sqlite/sysadm/sysadm.db "SELECT * FROM domains;"

# MySQL
mysql -u postfix -p sysadm -e "SELECT * FROM domains;"
```

### Verify Postfix Integration
```bash
# Test domain lookup
postmap -q "markc.goldcoast.org" sqlite:/etc/postfix/sqlite-virtual-domains.cf

# Test mailbox lookup
postmap -q "admin@markc.goldcoast.org" sqlite:/etc/postfix/sqlite-virtual-mailboxes.cf

# Test alias lookup
postmap -q "postmaster@markc.goldcoast.org" sqlite:/etc/postfix/sqlite-virtual-aliases.cf
```

### Verify Dovecot Integration
```bash
# Test authentication
doveadm auth test admin@markc.goldcoast.org password123

# List all mailboxes
doveadm user '*'

# Check user info
doveadm user admin@markc.goldcoast.org
```

## Version History

- **v3.0** (2025-10-08)
  - Updated for NetServa 3.0 Platform
  - Database-first architecture with Laravel
  - Real-world examples from markc vnode
  - Removed legacy path references
  - Enhanced security documentation

- **v1.0** (2025-10-04)
  - Initial canonical DBML schema
  - MySQL/MariaDB and SQLite compatibility
  - Laravel timestamp support
  - Performance optimizations (zero-JOIN design)

## Future Enhancements

Potential additions (maintain backward compatibility):

- **Mailbox statistics:** Add `last_login_at`, `message_count` fields
- **Quota tracking:** Add `quota_used` field with triggers
- **Alias metrics:** Add `forwarded_count`, `last_used_at`
- **Multi-tenancy:** Add `customer_id` for reseller deployments
- **Archive flags:** Add `is_archived` for mailbox archival
- **DKIM support:** Add `dkim_selector`, `dkim_private_key` to domains table

All enhancements must be additive (no breaking changes to existing deployments).

## References

- **DBML Specification:** https://dbml.dbdiagram.io/docs/
- **Postfix Virtual Setup:** http://www.postfix.org/VIRTUAL_README.html
- **Dovecot SQL:** https://doc.dovecot.org/configuration_manual/authentication/sql/
- **NetServa Documentation:** `~/.ns/resources/docs/`
- **NetServa 3.0 Config:** [NETSERVA-3.0-CONFIGURATION.md](NETSERVA-3.0-CONFIGURATION.md)

---

**Maintainer:** NetServa Platform Team
**License:** MIT
**Repository:** https://github.com/markc/ns
