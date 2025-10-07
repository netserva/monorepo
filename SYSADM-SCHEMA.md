# NetServa 3.0 sysadm Database Schema

**Single Source of Truth:** [`sysadm.dbml`](sysadm.dbml)

## Overview

The `sysadm` database is the **runtime configuration database** for NetServa 3.0 mail servers deployed on remote systems. This is separate from the central workstation's Laravel database which manages infrastructure metadata.

## Purpose & Scope

**What it stores:**
- Virtual mail domains
- Email mailboxes (users)
- Email aliases and forwarding rules

**What it does NOT store:**
- Infrastructure metadata (that's in `~/.ns/www/database/database.sqlite` on the workstation)
- SSH configurations (that's in `~/.ns/ssh/` and `~/.ns/etc/netserva.db`)
- Credentials (that's in `~/.ns/var/$SHOST/$VHOST` environment files)

## Database Locations by Deployment

### Production Servers (MySQL/MariaDB)
```
Database: sysadm (or mail_{domain})
Location: MySQL/MariaDB server
Access: /etc/postfix/mysql-*.cf
        /etc/dovecot/dovecot-sql.conf.ext
```

### Lightweight Servers (SQLite)
```
Database: /var/lib/sqlite/sysadm/sysadm.db
Access: /etc/postfix/sqlite-*.cf
        /etc/dovecot/user-sqlite.conf
```

## Schema Tables

### 1. domains
Virtual mail domains with filesystem ownership.

**Fields:**
- `domain` (PK) - Domain name (e.g., example.com)
- `uid` - Unix user ID for filesystem ownership
- `gid` - Unix group ID for filesystem ownership
- `maxquota` - Maximum domain quota in bytes (0 = unlimited)
- `active` - Active flag (1=active, 0=disabled)
- `created_at`, `updated_at` - Laravel timestamps

### 2. mailboxes
Virtual mailboxes for email users.

**Fields:**
- `username` (PK) - Full email address (e.g., user@example.com)
- `password` - Encrypted password hash (SHA256-CRYPT, BLF-CRYPT)
- `maildir` - Maildir path (e.g., example.com/mail/user/)
- `domain` - Domain name (redundant for performance)
- `uid`, `gid` - Unix ownership (redundant for performance)
- `quota` - Mailbox quota in bytes (default: 500MB)
- `active` - Active flag
- `created_at`, `updated_at` - Laravel timestamps

**Design Note:** Redundant domain/uid/gid fields enable zero-JOIN queries for maximum Postfix/Dovecot performance.

### 3. aliases
Email aliases and forwarding rules.

**Fields:**
- `address` (PK) - Email address or wildcard (e.g., user@example.com, @example.com)
- `goto` - Destination email(s), comma-separated for multiple
- `domain` - Domain name (redundant for performance)
- `active` - Active flag
- `created_at`, `updated_at` - Laravel timestamps

## Converting DBML to SQL

### Option 1: Online Converter
Visit: https://dbml.dbdiagram.io/
- Paste `sysadm.dbml` contents
- Export to MySQL or PostgreSQL
- Manually adjust for SQLite if needed

### Option 2: CLI Tool (dbml-cli)
```bash
# Install
npm install -g @dbml/cli

# Generate MySQL
dbml2sql sysadm.dbml --mysql -o sysadm-mysql.sql

# Generate PostgreSQL
dbml2sql sysadm.dbml --postgres -o sysadm-postgres.sql

# For SQLite, use MySQL output and manually adjust
```

### Option 3: Use Existing SQL Files
Pre-generated SQL files are available:
- `resources/docs/netserva-schema-sqlite.sql` - SQLite version
- `resources/docs/netserva-schema-mariadb.sql` - MySQL/MariaDB version

## Deployment Workflow

### New Server Setup

1. **Choose database engine** (MySQL or SQLite)
2. **Create database:**
   ```bash
   # MySQL
   mysql -e "CREATE DATABASE sysadm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

   # SQLite
   mkdir -p /var/lib/sqlite/sysadm
   ```

3. **Import schema:**
   ```bash
   # MySQL
   mysql sysadm < resources/docs/netserva-schema-mariadb.sql

   # SQLite
   sqlite3 /var/lib/sqlite/sysadm/sysadm.db < resources/docs/netserva-schema-sqlite.sql
   ```

4. **Create database users:**
   ```sql
   -- MySQL: Postfix (read-only)
   CREATE USER 'postfix'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT SELECT ON sysadm.* TO 'postfix'@'localhost';

   -- MySQL: Dovecot (read-only)
   CREATE USER 'dovecot'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT SELECT ON sysadm.mailboxes TO 'dovecot'@'localhost';

   -- MySQL: NetServa management (full access)
   CREATE USER 'netserva'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL ON sysadm.* TO 'netserva'@'localhost';
   ```

5. **Configure Postfix/Dovecot:**
   - Update connection strings in `/etc/postfix/*.cf`
   - Update connection strings in `/etc/dovecot/*.conf`

### Migrating Existing Server

If migrating from old NetServa 2.x schema with `vmails` table:

```sql
-- Rename table
RENAME TABLE vmails TO mailboxes;

-- Rename columns (if needed)
ALTER TABLE mailboxes CHANGE COLUMN user username VARCHAR(255);

-- Add missing columns
ALTER TABLE mailboxes ADD COLUMN created_at TIMESTAMP NULL;
ALTER TABLE mailboxes ADD COLUMN updated_at TIMESTAMP NULL;

-- Add indexes
CREATE INDEX idx_mailbox_domain ON mailboxes(domain);
CREATE INDEX idx_mailbox_active ON mailboxes(active);
```

## Current Server Status

### ✓ nsorg (netserva.org) - COMPLIANT
- **Database:** SQLite at `/var/lib/sqlite/sysadm/sysadm.db`
- **Schema:** Uses canonical `domains, mailboxes, aliases` tables
- **Status:** Fully compatible with sysadm.dbml

### ✗ motd (mail.motd.com) - NEEDS MIGRATION
- **Database:** MySQL `mail_motd_com`
- **Schema:** Uses legacy `vmails` table
- **Action Required:** Rename `vmails` to `mailboxes`, update column names

### ? mgo (mail.goldcoast.org) - UNKNOWN
- **Status:** Database schema not documented
- **Action Required:** Verify schema and document

## Password Generation

Always use strong password hashing:

```bash
# Generate SHA256-CRYPT hash
doveadm pw -s SHA256-CRYPT

# Generate BLF-CRYPT hash (recommended)
doveadm pw -s BLF-CRYPT

# Example output:
# {SHA256-CRYPT}$5$rounds=5000$randomsalt$hashedpassword...
```

**Never use:**
- PLAIN passwords
- MD5 hashes
- Weak encryption schemes

## Security Best Practices

1. **Database Users:** Follow principle of least privilege
   - Postfix: SELECT only on domains, mailboxes, aliases
   - Dovecot: SELECT only on mailboxes
   - Management: Full access only where needed

2. **Password Hashing:** Use SHA256-CRYPT or BLF-CRYPT minimum

3. **Network Access:** Bind database to localhost unless clustering

4. **Regular Backups:** Include database in backup schedules

5. **Audit Logging:** Track all schema changes and user modifications

## Integration with NetServa Infrastructure

### Central Workstation (NS Standard)
```
~/.ns/
├── sysadm.dbml                          # Canonical schema (THIS FILE)
├── resources/docs/
│   ├── netserva-schema-sqlite.sql      # Generated from DBML
│   └── netserva-schema-mariadb.sql     # Generated from DBML
└── www/database/database.sqlite        # Laravel app DB (different purpose)
```

### Remote Server (NetServa 3.0 Runtime)
```
/var/lib/sqlite/sysadm/sysadm.db        # SQLite deployment
    OR
MySQL sysadm database                    # MySQL/MariaDB deployment

/var/ns/{domain}/                        # Mail storage
├── mail/{user}/Maildir/                # Mailbox files (referenced by sysadm.mailboxes)
├── home/{user}/                        # User home directories
└── var/
    ├── log/                            # Domain logs
    └── www/                            # Web files (optional)
```

## Version History

- **v1.0** (2025-10-04)
  - Initial canonical DBML schema
  - MySQL/MariaDB and SQLite compatibility
  - Laravel timestamp support
  - Performance optimizations (zero-JOIN design)

## Future Enhancements

Potential additions (maintain backward compatibility):

- **Mailbox stats:** Add last_login_at, message_count fields
- **Quota tracking:** Add quota_used field with triggers
- **Alias metrics:** Add forwarded_count, last_used_at
- **Multi-tenancy:** Add customer_id for reseller deployments
- **Archive flags:** Add is_archived for mailbox archival

All enhancements must be additive (no breaking changes to existing deployments).

## References

- **DBML Specification:** https://dbml.dbdiagram.io/docs/
- **Postfix Virtual Setup:** http://www.postfix.org/VIRTUAL_README.html
- **Dovecot SQL:** https://doc.dovecot.org/configuration_manual/authentication/sql/
- **NetServa Documentation:** `~/.ns/resources/docs/`

---

**Maintainer:** NetServa Infrastructure Team
**Last Updated:** 2025-10-04
**License:** MIT
