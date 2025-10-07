# Dovecot Lean Setup Guide

This document describes the recommended lean dovecot configuration for NetServa mail servers using a single configuration file approach.

## Overview

The leanest and most maintainable dovecot setup uses:
- **Single configuration file**: `/etc/dovecot/dovecot.conf` (no conf.d/ directory)
- **Minimal sieve directory**: `/etc/dovecot/sieve/` with compiled scripts
- **Direct spamprobe integration**: Symlinked spamprobe binary for sieve scripts

## Directory Structure

```
/etc/dovecot/
├── dovecot.conf                    # Single configuration file
├── dh.pem                         # Diffie-Hellman parameters
└── sieve/                         # Sieve scripts directory
    ├── global.sieve               # Global sieve script
    ├── global.svbin              # Compiled global script
    ├── retrain-as-good.sieve     # Ham retraining (FROM Junk)
    ├── retrain-as-good.svbin     # Compiled ham script
    ├── retrain-as-ham.sieve      # Ham training (TO Junk-Ham)
    ├── retrain-as-ham.svbin      # Compiled ham script
    ├── retrain-as-spam.sieve     # Spam training (TO Junk)
    ├── retrain-as-spam.svbin     # Compiled spam script
    └── spamprobe -> /usr/bin/spamprobe*  # Symlink to spamprobe binary
```

## Configuration Philosophy

### Single File Approach
- Eliminates complexity of multiple configuration files
- Easier to backup, version control, and troubleshoot
- All settings in one location for complete system view
- Avoids conf.d/ directory fragmentation

### Benefits
1. **Simplicity**: One file to maintain and understand
2. **Portability**: Easy to copy between servers
3. **Debugging**: All configuration visible in single location
4. **Version Control**: Single file to track changes
5. **Migration**: Simple to backup and restore

## Key Configuration Sections

### SSL Configuration
```
ssl = required
ssl_min_protocol = TLSv1.2
ssl_cipher_list = HIGH:!aNULL:!MD5
ssl_server_cert_file = /root/.acme.sh/_MAILHOST_ecc/fullchain.cer
ssl_server_key_file = /root/.acme.sh/_MAILHOST_ecc/_MAILHOST_.key
```

### Database Integration
```
mysql localhost {
  dbname = sysadm
  password = _DB_PASSWORD_
  user = sysadm
}
```

### Sieve Configuration
```
sieve_execute_bin_dir = /etc/dovecot/sieve
sieve_plugins = sieve_imapsieve sieve_extprograms
sieve_pipe_bin_dir = /usr/bin
```

### IMAPSieve Integration
```
protocol imap {
  mail_plugins = imap_sieve
}
```

## Sieve Scripts for Spam Training

### Spam Training (TO Junk folder)
**File**: `/etc/dovecot/sieve/retrain-as-spam.sieve`
```sieve
require ["vnd.dovecot.pipe"];
pipe :copy "spamprobe" ["-c", "-d", "spamprobe", "spam"];
```

### Ham Training (FROM Junk folder)
**File**: `/etc/dovecot/sieve/retrain-as-good.sieve`
```sieve
require ["vnd.dovecot.pipe"];
pipe :copy "spamprobe" ["-c", "-d", "spamprobe", "good"];
```

### Ham Training (TO Junk-Ham folder)
**File**: `/etc/dovecot/sieve/retrain-as-ham.sieve`
```sieve
require ["vnd.dovecot.pipe"];
pipe :copy "spamprobe" ["-c", "-d", "spamprobe", "good"];
```

## Mailbox Configuration

### Namespace with IMAPSieve
```
namespace inbox {
  inbox = yes
  prefix = 
  separator = /
  
  mailbox Junk {
    auto = subscribe
    special_use = "\\Junk"
    sieve_script retrain_spam {
      cause = copy move append
      path = /etc/dovecot/sieve/retrain-as-spam.sieve
      type = before
    }
    sieve_script retrain_ham {
      cause = delete move
      path = /etc/dovecot/sieve/retrain-as-good.sieve
      type = after
    }
  }
  
  mailbox "Junk-Ham" {
    auto = subscribe
    sieve_script retrain_ham {
      cause = copy move append
      path = /etc/dovecot/sieve/retrain-as-ham.sieve
      type = before
    }
  }
}
```

## Spamprobe Integration

### Symlink Setup
```bash
cd /etc/dovecot/sieve
ln -s /usr/bin/spamprobe spamprobe
```

### Directory Structure
Spamprobe databases are stored in user home directories:
```
/var/ns/_DOMAIN_/mail/_USER_/spamprobe/    # New NetServa structure
/var/ns/_DOMAIN_/home/_USER_/spamprobe/   # Legacy structure
```

## Compilation and Deployment

### Sieve Script Compilation
Dovecot automatically compiles `.sieve` files to `.svbin` when needed. Manual compilation:
```bash
sievec /etc/dovecot/sieve/retrain-as-spam.sieve
```

### Service Management
```bash
# Restart dovecot after configuration changes
systemctl restart dovecot

# Check status
systemctl status dovecot

# View logs
tail -f /var/log/dovecot.log
```

## Troubleshooting

### Common Issues
1. **Sieve scripts not executing**: Check sieve_execute_bin_dir permissions
2. **Spamprobe not found**: Verify symlink in /etc/dovecot/sieve/
3. **Database connection errors**: Check mysql credentials and permissions
4. **SSL certificate errors**: Verify certificate paths and permissions

### Log Analysis
```bash
# Monitor sieve execution
grep sieve /var/log/dovecot.log

# Check spam training activity  
grep spamprobe /var/log/dovecot.log

# Authentication debugging
grep auth /var/log/dovecot.log
```

## Migration from conf.d/

If migrating from a conf.d/ setup:
1. Backup existing configuration: `cp -r /etc/dovecot /etc/dovecot.backup`
2. Consolidate all conf.d/*.conf files into single dovecot.conf
3. Remove conf.d/ directory: `rm -rf /etc/dovecot/conf.d/`
4. Test configuration: `dovecot -n`
5. Restart service: `systemctl restart dovecot`

## Security Considerations

- SSL certificates managed per-server for portability
- Database passwords should be unique per installation
- Sieve scripts execute with dovecot privileges
- Regular spamprobe database cleanup recommended

## Maintenance

### Regular Tasks
1. **Certificate renewal**: Automatic via acme.sh
2. **Spamprobe cleanup**: Use `cleanspam` script monthly
3. **Log rotation**: Configured via systemd/logrotate
4. **Database backups**: Include sysadm database in backups

### Performance Monitoring
- Monitor dovecot stats on port 9900
- Track sieve execution metrics
- Monitor database query performance
- Watch spamprobe database sizes

## See Also
- [Mail Server Autoconfig](mail-server-autoconfig.md)
- [Bayes Spam Learning Configuration](bayes-spam-learning-configuration.md)
- [cleanspam(1)](../man/cleanspam.md) - Spamprobe database cleanup utility