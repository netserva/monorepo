# NetServa Mail Server Template for Alpine Linux

## Overview
This template documents the complete configuration for a NetServa-compliant mail server on Alpine Linux using SQLite, Dovecot 2.4.1, and Postfix.

## Key Components

### 1. Package Requirements
```bash
# Core mail packages
apk add postfix postfix-openrc postfix-sqlite
apk add dovecot dovecot-lmtpd dovecot-sqlite dovecot-pigeonhole-plugin

# Note: postfix-sqlite creates /etc/postfix/dynamicmaps.cf.d/sqlite.apk-new
# Must rename to 'sqlite' for activation
```

### 2. Database Structure (NetServa Hardlink Pattern)

#### Directory Layout
```
/var/lib/sqlite/
├── dovecot/      # drwx------ dovecot:dovecot
│   └── sysadm.db # -rw-r--r-- (hardlink, 644)
├── postfix/      # drwx------ postfix:postfix  
│   └── sysadm.db # -rw-r--r-- (hardlink, 644)
└── sysadm/       # drwx------ sysadm:sysadm
    └── sysadm.db # -rw-r--r-- (main file, 644)
```

#### Key Principle
- Main database has 644 permissions (world-readable)
- Service directories have 700 permissions (owner-only access)
- Each service accesses through its own protected directory
- All three files are hardlinks to same inode

### 3. Dovecot Configuration (/etc/dovecot/dovecot.conf)

```conf
dovecot_config_version = 2.4.1
dovecot_storage_version = 2.4.0

# SSL Configuration for Dovecot 2.4.1
ssl = required
ssl_min_protocol = TLSv1.2
ssl_server_cert_file = /root/.acme.sh/mail.example.org_ecc/fullchain.cer
ssl_server_key_file = /root/.acme.sh/mail.example.org_ecc/mail.example.org.key

log_path = /var/log/dovecot.log
auth_mechanisms = plain login
protocols = imap lmtp

sql_driver = sqlite
sqlite_path = /var/lib/sqlite/dovecot/sysadm.db

# Password database
passdb regular_auth {
  driver = sql
  sql_query = SELECT password FROM vmails WHERE user = "%{user}" AND active = 1
}

# User database  
userdb vmails_userdb {
  driver = sql
  sql_query = SELECT home, uid, gid, "maildir:" || home || "/Maildir" AS mail FROM vmails WHERE user = "%{user}" AND active = 1
  sql_iterate_query = SELECT user FROM vmails WHERE active = 1
}

# Services
service auth {
  unix_listener /var/spool/postfix/private/auth {
    group = postfix
    mode = 0660
    user = postfix
  }
  unix_listener auth-userdb {
    mode = 0777
  }
}

service auth-worker {
  user = dovecot
}

service imap-login {
  inet_listener imap {
    port = 0
  }
  inet_listener imaps {
    port = 993
  }
}

service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    group = postfix
    mode = 0660
    user = postfix
  }
}

# Mailbox namespace
namespace inbox {
  inbox = yes
  prefix = 
  separator = .
  mailbox Sent {
    auto = subscribe
    special_use = "\\Sent"
  }
  mailbox Junk {
    auto = subscribe
    special_use = "\\Junk"
  }
  mailbox Drafts {
    auto = subscribe
    special_use = "\\Drafts"
  }
  mailbox Trash {
    auto = subscribe
    special_use = "\\Trash"
  }
  mailbox Archive {
    auto = subscribe
    special_use = "\\Archive"
  }
}

protocol imap {
  mail_max_userip_connections = 20
}

protocol lmtp {
  postmaster_address = postmaster@mail.example.org
}
```

### 4. Postfix SQLite Configuration

#### Enable SQLite Support
```bash
# Install package
apk add postfix-sqlite

# Activate SQLite dynamic map
mv /etc/postfix/dynamicmaps.cf.d/sqlite.apk-new /etc/postfix/dynamicmaps.cf.d/sqlite

# Restart postfix
rc-service postfix restart
```

#### SQLite Lookup Tables

**/etc/postfix/sqlite-virtual-mailbox-domains.cf**
```
dbpath = /var/lib/sqlite/postfix/sysadm.db
query = SELECT 1 FROM vhosts WHERE domain='%s' AND active=1
```

**/etc/postfix/sqlite-virtual-mailbox-maps.cf**
```
dbpath = /var/lib/sqlite/postfix/sysadm.db
query = SELECT 1 FROM vmails WHERE user='%s' AND active=1
```

**/etc/postfix/sqlite-virtual-alias-maps.cf**
```
dbpath = /var/lib/sqlite/postfix/sysadm.db
query = SELECT target FROM valias WHERE source='%s' AND active=1
```

#### Postfix main.cf Virtual Configuration
```bash
# Set virtual mail parameters
postconf -e "virtual_mailbox_domains = sqlite:/etc/postfix/sqlite-virtual-mailbox-domains.cf"
postconf -e "virtual_mailbox_maps = sqlite:/etc/postfix/sqlite-virtual-mailbox-maps.cf"
postconf -e "virtual_alias_maps = sqlite:/etc/postfix/sqlite-virtual-alias-maps.cf"
postconf -e "virtual_transport = lmtp:unix:private/dovecot-lmtp"

# SASL authentication via Dovecot
postconf -e "smtpd_sasl_type = dovecot"
postconf -e "smtpd_sasl_path = private/auth"
postconf -e "smtpd_sasl_auth_enable = yes"
postconf -e "smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination"
```

### 5. Database Schema (SQLite)

```sql
-- vmails table (Laravel-compliant)
CREATE TABLE vmails (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aid int(11) NOT NULL DEFAULT '1',
  hid int(11) NOT NULL DEFAULT '1',
  gid int(11) NOT NULL DEFAULT '1000',
  uid int(11) NOT NULL DEFAULT '1000',
  active tinyint(1) NOT NULL DEFAULT '1',
  quota bigint(20) NOT NULL DEFAULT '500000000',
  user varchar(63) NOT NULL DEFAULT '',
  home varchar(127) NOT NULL DEFAULT '',
  password varchar(127) NOT NULL DEFAULT '',
  proxy tinyint(1) NOT NULL DEFAULT '0',
  updated datetime NOT NULL DEFAULT '2018-01-01 00:00:01',
  created datetime NOT NULL DEFAULT '2018-01-01 00:00:01'
);

-- vhosts table
CREATE TABLE vhosts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aid int(11) NOT NULL DEFAULT '1',
  domain varchar(127) NOT NULL DEFAULT '',
  uname varchar(63) NOT NULL DEFAULT '',
  uid int(11) NOT NULL DEFAULT '1000',
  gid int(11) NOT NULL DEFAULT '1000',
  active int(11) NOT NULL DEFAULT '1',
  created datetime NOT NULL DEFAULT '2018-01-01 00:00:01',
  updated datetime NOT NULL DEFAULT '2018-01-01 00:00:01'
);

-- valias table
CREATE TABLE valias (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aid int(11) NOT NULL DEFAULT '1',
  hid int(11) NOT NULL DEFAULT '1',
  active tinyint(1) NOT NULL DEFAULT '1',
  source varchar(127) NOT NULL DEFAULT '',
  target varchar(255) NOT NULL DEFAULT '',
  updated datetime NOT NULL DEFAULT '2018-01-01 00:00:01',
  created datetime NOT NULL DEFAULT '2018-01-01 00:00:01'
);
```

### 6. NetServa Directory Structure

```
/srv/
└── example.org/
    └── # Removed: home/{user}/ directory
        └── admin/
            └── Maildir/
                ├── cur/
                ├── new/
                └── tmp/
```

## Verification Commands

```bash
# Check SQLite support in postfix
postconf -m | grep sqlite

# Test domain lookup
postmap -q example.org sqlite:/etc/postfix/sqlite-virtual-mailbox-domains.cf

# Test mailbox lookup
postmap -q admin@example.org sqlite:/etc/postfix/sqlite-virtual-mailbox-maps.cf

# Test dovecot user lookup
doveadm user admin@example.org

# Send test email
echo "Test" | mail -s "Test" admin@example.org

# Check mail delivery
tail -f /var/log/messages | grep -E "postfix|dovecot"
```

## Common Issues and Solutions

### 1. SQLite Not Available in Postfix
**Issue**: `postmap: warning: unsupported dictionary type: sqlite`
**Solution**: 
```bash
# Install package
apk add postfix-sqlite
# Rename config file
mv /etc/postfix/dynamicmaps.cf.d/sqlite.apk-new /etc/postfix/dynamicmaps.cf.d/sqlite
# Restart postfix
rc-service postfix restart
```

### 2. Database Permission Errors
**Issue**: `Permission denied (euid=90 egid=102 missing +r perm)`
**Solution**: 
- Ensure database file has 644 permissions
- Use hardlinks with service-specific ownership
- Add service user to sysadm group if needed

### 3. Dovecot 2.4.1 Configuration Syntax
**Issue**: `Unknown setting: ssl_cert` or `passdb { } is missing section name`
**Solution**: 
- Use `ssl_server_cert_file` instead of `ssl_cert`
- Name all passdb/userdb sections (e.g., `passdb regular_auth`)
- First line must be `dovecot_config_version = 2.4.1`

## Alpine-Specific Notes

1. **Service Management**: Use OpenRC (`rc-service` not `systemctl`)
2. **Package Management**: Use `apk add` not `apt install`
3. **Config Files**: Alpine packages may install as `.apk-new` to preserve existing configs
4. **User IDs**: Check that service users exist (dovecot, postfix, vmail)
5. **Logging**: Check `/var/log/messages` for mail logs

## Security Considerations

- Database file has 644 permissions but protected by directory permissions
- Each service accesses database through its own 700-permission directory
- SSL/TLS certificates managed by acme.sh
- SASL authentication through Dovecot
- Virtual domains prevent local system user exploitation

---

*This template is based on the successful configuration of nsorg (mail.netserva.org) running Alpine Linux with Dovecot 2.4.1 and Postfix 3.10.3*