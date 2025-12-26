# Stats - Server Usage Reporting

Unified server usage statistics collection and reporting system for NetServa 3.0.

## Changelog

| Date | Change |
|------|--------|
| 2025-12-26 | Initial implementation on mrn with daily/weekly/monthly reports |

## Overview

`stats` collects daily metrics from mail, web, network, and system sources, stores them in SQLite, and generates formatted reports with trend analysis.

## Installation

Currently installed on: **mrn** (mail.renta.net)

```
/usr/local/bin/stats                    # CLI
/usr/local/lib/serverstats/             # Libraries and collectors
/var/lib/serverstats/stats.db           # SQLite database
/etc/cron.d/stats                       # Cron jobs
/var/log/stats.log                      # Log file
```

## Runbook: Setup on New Server

**Time:** ~15 minutes

### Prerequisites
```bash
apt install sqlite3 vnstat jq bc
```

### 1. Copy scripts from existing server
```bash
scp -r mrn:/usr/local/lib/serverstats /usr/local/lib/
scp mrn:/usr/local/bin/stats /usr/local/bin/
chmod +x /usr/local/bin/stats
```

### 2. Initialize database
```bash
mkdir -p /var/lib/serverstats
stats init
```

### 3. Configure sieve logging (if mail server)
Add to `/etc/dovecot/sieve/global.sieve`:
```sieve
require ["vnd.dovecot.debug"];
debug_log "DELIVERY: ${lhs}@${rhs} -> Inbox (${SCORE})";
```

Add to retrain sieve scripts:
```sieve
debug_log "TRAIN: ${user} -> good|spam";
```

Compile and restart:
```bash
sievec /etc/dovecot/sieve/*.sieve
systemctl restart dovecot
```

### 4. Set up cron
```bash
cat > /etc/cron.d/stats << 'EOF'
10 0 * * * root /usr/local/bin/stats collect >> /var/log/stats.log 2>&1
15 0 * * * root /usr/local/bin/stats report --email >> /var/log/stats.log 2>&1
30 0 * * 1 root /usr/local/bin/stats report weekly --email >> /var/log/stats.log 2>&1
0 1 1 * * root /usr/local/bin/stats report monthly --email >> /var/log/stats.log 2>&1
EOF
```

### 5. Test
```bash
stats collect --date=$(date +%Y-%m-%d)
stats report
stats report --email
```

### 6. Update REPORT_EMAIL
Edit `/usr/local/lib/serverstats/lib/common.sh`:
```bash
export REPORT_EMAIL="your@email.com"
```

---

## CLI Usage

```bash
# Collection
stats collect                    # All collectors for yesterday
stats collect mail spam          # Specific collectors only
stats collect --date=2025-12-25  # Specific date

# Reports
stats report                     # Daily report to stdout
stats report --email             # Email daily report
stats report weekly              # Weekly report (last 7 days)
stats report weekly --email      # Email weekly report
stats report monthly             # Monthly report (last month)
stats report monthly --month=2025-11  # Specific month

# Queries
stats query spam                 # Recent spam stats
stats query web                  # Recent web stats
stats query mail                 # Recent mail stats
stats query network              # Recent network stats
stats query system               # Recent system stats

# Maintenance
stats init                       # Verify database
stats prune 90                   # Keep only 90 days
```

## Collectors

| Collector | Source | Metrics |
|-----------|--------|---------|
| mail | `/var/log/mail.log` | sent, bounced, deferred, rejected, auth_failures, connections |
| spam | `/var/log/mail.log` (sieve DEBUG) | inbox/junk/trash per mailbox, training events |
| web | `/var/log/nginx/access.log` | requests, bytes, status codes per vhost |
| network | `vnstat --json` | rx/tx bytes per interface |
| system | `df`, `free`, `/proc/loadavg` | disk, memory, load, uptime |

## Cron Schedule

| Time | Task |
|------|------|
| 00:10 daily | Collect yesterday's stats |
| 00:15 daily | Email daily report |
| 00:30 Monday | Email weekly report |
| 01:00 1st | Email monthly report |
| 02:00 quarterly | Prune data older than 365 days |

## Database Schema

### Tables
- `daily_mail` - Postfix delivery stats per day
- `daily_spam` - Spam filtering stats per mailbox per day
- `daily_web` - Nginx stats per vhost per day
- `daily_network` - Network I/O per interface per day
- `daily_system` - System resources per day

### Views
- `weekly_mail`, `weekly_spam`, `weekly_web` - 7-day aggregates
- `monthly_mail`, `monthly_spam` - 30-day aggregates
- `summary_spam`, `summary_web` - Daily totals

## Report Formats

### Daily Report
```
═══════════════════════════════════════════════════════════════════════════
              SERVER USAGE REPORT - mail.renta.net
              Period: 2025-12-26 (Daily)    Uptime: 42 days
═══════════════════════════════════════════════════════════════════════════

📧 MAIL DELIVERY                                          vs 7-day avg
  Sent:        186  ▲ +12%      Bounced:       25       Deferred:    57

🛡️ SPAM FILTERING                                        Effectiveness: 85%
  Inbox:       38 (88%)    Junk:       4 (9%)    Trash:    1 (2%)
  Training:  +2 good  -1 spam

🌐 WEB TRAFFIC
  Requests:  197730       Bandwidth:   814.7 MB       Vhosts: 486

📊 NETWORK I/O
  ens18:   ↓ 2.4 GB        ↑ 1.8 GB

💾 SYSTEM RESOURCES
  Disk:      586 GB /   985 GB  ████████████░░░░░░░░  62%
  Memory:   3221 MB / 64202 MB  █░░░░░░░░░░░░░░░░░░░  5%
```

### Weekly Report
Includes:
- Week totals vs previous week
- Top 5 mailboxes with spam counts
- Top 10 websites
- Daily breakdown table

### Monthly Report
Includes:
- Month totals vs previous month
- Top 10 mailboxes
- Top 15 websites
- Weekly breakdown table
- System min/max/avg stats

## Dependencies

- SQLite3
- vnstat (for network stats)
- jq (for JSON parsing)
- bc (for calculations)
- mail (for email reports)

## Sieve Integration

Stats relies on DEBUG logging in Dovecot sieve scripts:

```sieve
# In global.sieve - logs deliveries
debug_log "DELIVERY: user@domain -> Inbox|Junk|Trash (SCORE)";

# In retrain-as-ham/spam.sieve - logs training
debug_log "TRAIN: user@domain -> good|spam";
```

## Future Enhancements

- [ ] Weekly/monthly trend graphs (ASCII art)
- [ ] Alert thresholds (e.g., auth failures > 5000)
- [ ] Per-domain spam effectiveness reports
- [ ] Disk usage trends and predictions
- [ ] Web error analysis (top 404s, 500s)
- [ ] Geographic IP analysis for auth failures
