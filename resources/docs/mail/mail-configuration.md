# NetServa Mail Configuration

## Overview

NetServa provides comprehensive mail server functionality using Postfix (SMTP) and Dovecot (IMAP/POP3) with support for multiple mailbox formats, spam filtering, and sieve scripting. All mail data is stored under the new `/var/ns/$VHOST/mail/` directory structure.

**Recommended Setup**: NetServa uses a **lean dovecot configuration** with a single `dovecot.conf` file (no conf.d/ directory) for maximum simplicity and maintainability. See [Dovecot Lean Setup Guide](dovecot-lean-setup.md) for details.

## Directory Structure Changes

### Legacy Structure (deprecated)
```
/home/u/$VHOST/home/
├── Maildir/                    # Maildir mailboxes
├── .spamprobe/                 # SpamProbe database (hidden)
└── .sieve/                     # Sieve scripts (hidden)
```

### New Structure
```
/var/ns/$VHOST/mail/           # MPATH - Mail storage root
├── Maildir/                    # Maildir format mailboxes
├── mdbox/                      # mdbox format mailboxes (alternative)
├── sieve/                      # Sieve filtering scripts (no dot prefix)
├── spamprobe/                  # SpamProbe database (no dot prefix)
└── logs/                       # Mail-specific logs
```

## Mailbox Formats

### Maildir Format (Default)

Dovecot Maildir is the default mailbox format, storing each email as a separate file:

```
/var/ns/example.com/mail/Maildir/
├── cur/                        # Current messages
├── new/                        # New messages  
├── tmp/                        # Temporary files
├── .INBOX.Sent/               # Sent folder
│   ├── cur/
│   ├── new/
│   └── tmp/
├── .INBOX.Drafts/             # Drafts folder
├── .INBOX.Trash/              # Trash folder
├── .INBOX.Spam/               # Spam folder
└── .INBOX.Archive/            # Archive folder
```

**Advantages:**
- Each email is a separate file
- Resistant to corruption
- Easy backup and migration
- Good performance for small to medium mailboxes

**Configuration:**
```
# dovecot.conf
mail_location = maildir:/var/ns/%d/mail/Maildir
```

### mdbox Format (Alternative)

Dovecot mdbox format stores multiple emails in indexed database files:

```
/var/ns/example.com/mail/mdbox/
├── mailboxes/                  # Mailbox metadata
│   ├── INBOX/
│   ├── Sent/
│   └── Drafts/
├── storage/                    # Email content storage
│   ├── m.1
│   ├── m.2
│   └── ...
└── indexes/                    # Search indexes
    ├── INBOX/
    ├── Sent/
    └── Drafts/
```

**Advantages:**
- Better performance for large mailboxes
- Efficient storage utilization
- Built-in compression support
- Excellent search performance

**Configuration:**
```
# dovecot.conf
mail_location = mdbox:/var/ns/%d/mail/mdbox
```

## Sieve Filtering

### Directory Structure

Sieve scripts are stored in `/var/ns/$VHOST/mail/sieve/` (note: no dot prefix):

```
/var/ns/example.com/mail/sieve/
├── default.sieve               # Default sieve script
├── spam-filter.sieve           # Spam filtering rules
├── vacation.sieve              # Vacation responder
├── custom-rules.sieve          # Custom filtering rules
├── default.svbin               # Compiled default script
└── spam-filter.svbin           # Compiled spam script
```

### Basic Sieve Configuration

**Default Sieve Script (`default.sieve`):**
```sieve
require ["fileinto", "mailbox"];

# Spam filtering
if header :contains "X-Spam-Flag" "YES" {
    fileinto :create "Spam";
    stop;
}

# Sort mailing lists
if header :contains "List-Id" "netserva" {
    fileinto :create "Lists/NetServa";
    stop;
}

# Default delivery to INBOX
fileinto "INBOX";
```

**Spam Filter Script (`spam-filter.sieve`):**
```sieve
require ["fileinto", "variables", "environment"];

# SpamProbe integration
if environment :matches "spamprobe" "*" {
    set "spam_score" "${1}";
    
    if string :value "ge" :comparator "i;ascii-numeric" "${spam_score}" "0.9" {
        fileinto :create "Spam";
        stop;
    }
}

# Additional spam rules
if anyof (
    header :contains "subject" ["[SPAM]", "***SPAM***"],
    header :contains "X-Spam-Status" "Yes"
) {
    fileinto :create "Spam";
    stop;
}
```

### Dovecot Sieve Configuration

**Main Configuration (`dovecot.conf`):**
```
# Sieve configuration
protocol lda {
    mail_plugins = $mail_plugins sieve
}

protocol lmtp {
    mail_plugins = $mail_plugins sieve
}

plugin {
    sieve = /var/ns/%d/mail/sieve/default.sieve
    sieve_dir = /var/ns/%d/mail/sieve/
    sieve_before = /var/ns/%d/mail/sieve/spam-filter.sieve
    sieve_global_dir = /etc/dovecot/sieve/global/
}
```

## Spam Filtering

### SpamProbe Integration

SpamProbe database is stored in `/var/ns/$VHOST/mail/spamprobe/` (note: no dot prefix):

```
/var/ns/example.com/mail/spamprobe/
├── spam.db                     # Spam training database
├── good.db                     # Ham training database  
├── config                      # SpamProbe configuration
├── spamprobe.log              # Activity log
└── whitelist.txt              # Manual whitelist
```

**SpamProbe Configuration (`config`):**
```
# Database files
spam_db=/var/ns/example.com/mail/spamprobe/spam.db
good_db=/var/ns/example.com/mail/spamprobe/good.db

# Scoring thresholds
spam_threshold=0.9
good_threshold=0.1

# Training settings
auto_train=true
auto_train_spam_threshold=0.95
auto_train_good_threshold=0.05
```

**Integration with Sieve:**
```sieve
require ["fileinto", "environment", "vnd.dovecot.execute"];

# SpamProbe scoring
if environment :matches "spamprobe" "*" {
    if execute :pipe "spamprobe" ["-d", "/var/ns/%d/mail/spamprobe", "receive"] {
        if header :contains "X-SpamProbe" "SPAM" {
            fileinto :create "Spam";
            stop;
        }
    }
}
```

### Rspamd Integration (Alternative)

For systems using Rspamd instead of SpamProbe:

**Rspamd Sieve Integration:**
```sieve
require ["fileinto", "relational", "comparator-i;ascii-numeric"];

# Rspamd spam filtering
if header :value "ge" :comparator "i;ascii-numeric" "X-Spam-Score" "5" {
    fileinto :create "Spam";
    stop;
}

if header :contains "X-Spam-Action" "reject" {
    fileinto :create "Spam";
    stop;
}
```

## Postfix Configuration

### Virtual Domain Setup

**Main Configuration (`main.cf`):**
```
# Virtual domain settings
virtual_mailbox_domains = proxy:mysql:/etc/postfix/mysql-mailbox-domains.cf
virtual_mailbox_maps = proxy:mysql:/etc/postfix/mysql-mailbox-maps.cf
virtual_alias_maps = proxy:mysql:/etc/postfix/mysql-alias-maps.cf

# Mailbox location (updated for new structure)
virtual_mailbox_base = /var/ns
virtual_uid_maps = static:1000
virtual_gid_maps = static:1000

# Maildir delivery
virtual_transport = virtual
virtual_mailbox_maps = hash:/etc/postfix/vmailbox
```

**MySQL Mailbox Maps (`mysql-mailbox-maps.cf`):**
```
hosts = localhost
user = postfix
password = your_password
dbname = mail
query = SELECT CONCAT(domain,'/',user,'/mail/Maildir/') FROM users WHERE email='%s' AND active = 1
```

### LMTP Delivery to Dovecot

**Master Configuration (`master.cf`):**
```
# Dovecot LMTP delivery
dovecot   unix  -       n       n       -       -       pipe
  flags=DRhu user=vmail:vmail argv=/usr/lib/dovecot/dovecot-lda
  -f ${sender} -d ${user}@${nexthop} -a ${recipient}
```

**LMTP Configuration (`dovecot.conf`):**
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
}
```

## DKIM and DMARC

### DKIM Configuration

**OpenDKIM Setup:**
```
# /etc/opendkim.conf
Domain                  example.com
KeyFile                 /var/ns/example.com/mail/dkim/default.private
Selector                default
Socket                  inet:8891@localhost
```

**Key Generation:**
```bash
# Generate DKIM keys
mkdir -p /var/ns/example.com/mail/dkim/
opendkim-genkey -D /var/ns/example.com/mail/dkim/ -d example.com -s default
chown -R opendkim:opendkim /var/ns/example.com/mail/dkim/
```

### DMARC Policy

**DNS TXT Record:**
```
_dmarc.example.com. IN TXT "v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com; ruf=mailto:dmarc@example.com; sp=quarantine; adkim=r; aspf=r;"
```

## User Management

### User Database

**MySQL Schema:**
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    domain VARCHAR(100) NOT NULL,
    maildir VARCHAR(255) NOT NULL,
    quota BIGINT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE aliases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    domain VARCHAR(100) NOT NULL,
    active TINYINT(1) DEFAULT 1
);
```

**User Creation Example:**
```sql
INSERT INTO users (email, password, domain, maildir, quota) VALUES (
    'user@example.com',
    '{SSHA256}hashedpassword',
    'example.com', 
    'example.com/user/mail/Maildir/',
    1000000000
);
```

### Quota Management

**Dovecot Quota Configuration:**
```
# dovecot.conf
plugin {
    quota = maildir:User quota
    quota_rule = *:storage=1GB
    quota_rule2 = Trash:storage=+100M
    quota_warning = storage=95%% quota-warning 95 %u
    quota_warning2 = storage=80%% quota-warning 80 %u
}

service quota-warning {
    executable = script /usr/local/bin/quota-warning.sh
    user = vmail
    unix_listener quota-warning {
        user = vmail
    }
}
```

## Backup and Migration

### Mailbox Backup

**Maildir Backup:**
```bash
# Backup Maildir format
tar -czf /backup/example.com-mail-$(date +%Y%m%d).tar.gz \
    -C /var/ns/example.com/mail/ .

# Incremental backup with rsync
rsync -av --delete /var/ns/example.com/mail/ \
    backup-server:/backups/example.com/mail/
```

**mdbox Backup:**
```bash
# Backup mdbox format  
doveadm backup -u user@example.com \
    mdbox:/backup/example.com/mail/mdbox/

# Full mailbox export
doveadm backup -A \
    mdbox:/backup/all-mailboxes/
```

### Migration Tools

**Maildir to mdbox:**
```bash
# Convert existing Maildir to mdbox
doveadm backup -u user@example.com \
    maildir:/var/ns/example.com/mail/Maildir \
    mdbox:/var/ns/example.com/mail/mdbox
```

**IMAP Migration:**
```bash
# Migrate from external IMAP server
imapsync --host1 old.server.com --user1 user@example.com --password1 pass1 \
         --host2 localhost --user2 user@example.com --password2 pass2 \
         --automap --create_folder_old
```

## Monitoring and Logs

### Log Locations

```
/var/log/mail.log               # Main mail log
/var/log/dovecot.log           # Dovecot specific log
/var/log/postfix.log           # Postfix specific log
/var/ns/example.com/mail/logs/ # Domain-specific logs
```

### Performance Monitoring

**Dovecot Stats:**
```bash
# Check mailbox statistics
doveadm stats dump

# User statistics  
doveadm stats dump user=user@example.com

# Check quota usage
doveadm quota get -u user@example.com
```

**Postfix Monitoring:**
```bash
# Queue status
postqueue -p

# Mail statistics
pflogsumm /var/log/mail.log

# Real-time monitoring
tail -f /var/log/mail.log | grep example.com
```

This mail configuration provides a robust, scalable foundation for hosting email services with modern storage formats, effective spam filtering, and comprehensive management tools.