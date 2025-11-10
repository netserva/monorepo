# NetServa DNS Quick Start Guide

**Complete 3-Tier DNS Management with Auto-PTR/FCrDNS** ✅

---

## Quick Command Reference

### 1. DNS Provider (Tier 1)
```bash
# Create provider
adddns homelab powerdns \
  --description="Homelab PowerDNS via SSH tunnel to GW" \
  --endpoint=http://192.168.1.1:8082 \
  --api-key=secret \
  --ssh-host=gw

# List providers
shdns

# Show provider (by name or ID)
shdns homelab --detailed
shdns 1 --detailed

# Update provider
chdns homelab --endpoint=http://192.168.1.2:8082

# Delete provider
deldns homelab
```

### 2. DNS Zone (Tier 2)
```bash
# Create zone (using provider name or ID)
addzone example.com homelab
addzone example.com 1

# Create with DNSSEC
addzone secure.com homelab --auto-dnssec

# List zones
shzone

# Show zone
shzone example.com --with-dnssec

# Update zone
chzone example.com --ttl=7200

# Delete zone (with records)
delzone example.com --cascade
```

### 3. DNS Record (Tier 3)
```bash
# A record
addrec A www example.com 192.168.1.100

# A record with auto-PTR (FCrDNS)
addrec A mail example.com 192.168.1.50 \
  --auto-ptr \
  --auto-create-ptr-zone

# AAAA (IPv6)
addrec AAAA www example.com 2001:db8::1

# MX (Mail)
addrec MX @ example.com mail.example.com --priority=10

# TXT (SPF)
addrec TXT @ example.com "v=spf1 a mx ~all"

# List records
shrec --zone=example.com

# Show record with PTR
shrec 123 --with-ptr

# Update record and PTR
chrec 123 --content=192.168.1.200 --update-ptr

# Delete record and PTR
delrec 123 --delete-ptr
```

---

## Mail Server Setup (Complete FCrDNS)

```bash
# 1. Create provider (if not exists)
adddns "Production DNS" powerdns \
  --endpoint=http://ns1.example.com:8081 \
  --api-key=your-api-key

# 2. Create zone
addzone example.com 1

# 3. Create mail server A record with auto-PTR
addrec A mail example.com 192.168.1.50 \
  --auto-ptr \
  --auto-create-ptr-zone

# 4. Add MX record
addrec MX @ example.com mail.example.com --priority=10

# 5. Add SPF record
addrec TXT @ example.com "v=spf1 a mx ip4:192.168.1.50 ~all"

# 6. Add DKIM record (get key from mail server)
addrec TXT default._domainkey example.com \
  "v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY_HERE"

# 7. Add DMARC record
addrec TXT _dmarc example.com \
  "v=DMARC1; p=quarantine; rua=mailto:admin@example.com"

# 8. Verify FCrDNS
shrec --content=192.168.1.50 --with-ptr

# ✅ Mail server ready!
```

---

## Common Workflows

### Create Simple Website DNS
```bash
addzone mysite.com 1
addrec A @ mysite.com 192.168.1.100
addrec A www mysite.com 192.168.1.100
addrec CNAME ftp mysite.com mysite.com
```

### Create Split-Horizon DNS (Internal/External)
```bash
# Internal provider
adddns "Internal DNS" powerdns \
  --endpoint=http://192.168.1.1:8081 \
  --api-key=internal-key

# External provider
adddns "External DNS" cloudflare \
  --api-key=cf-key \
  --email=admin@example.com

# Same zone, different providers
addzone internal.local 1  # Internal
addzone example.com 2      # External

# Internal records
addrec A app internal.local 192.168.1.10

# External records
addrec A app example.com 203.0.113.10
```

### Migrate Zone Between Providers
```bash
# 1. Create zone on new provider
addzone example.com 2

# 2. Copy records (manual or scripted)
shrec --zone=example.com --json > records.json

# 3. Update SOA and NS
chzone example.com --nameservers=ns1.newprovider.com,ns2.newprovider.com

# 4. Test new zone
shzone example.com --test

# 5. Delete old zone
delzone example.com --cascade  # On old provider
```

---

## All Available Commands

### Provider Commands (4)
- `adddns` - Create DNS provider
- `shdns` - Show/list providers
- `chdns` - Update provider
- `deldns` - Delete provider

### Zone Commands (4)
- `addzone` - Create DNS zone
- `shzone` - Show/list zones
- `chzone` - Update zone
- `delzone` - Delete zone

### Record Commands (4)
- `addrec` - Create DNS record
- `shrec` - Show/list records
- `chrec` - Update record
- `delrec` - Delete record

**Total: 12 CRUD commands**

---

## Common Options

### All Commands Support
- `--dry-run` - Preview without making changes
- `--json` - JSON output
- `--help` - Show help

### Safety Features
- Confirmation prompts for destructive operations
- Duplicate detection
- Content validation
- Cascade/force options with warnings

### Interactive Mode
All commands have interactive prompts if arguments are missing:
```bash
addrec          # Will prompt for type, name, zone, content
addzone         # Will prompt for zone, provider
adddns          # Will prompt for name, type, endpoint, etc.
```

---

## FCrDNS (Forward-Confirmed Reverse DNS)

### What is FCrDNS?
FCrDNS ensures forward and reverse DNS match:
- Forward: `mail.example.com` → `192.168.1.50`
- Reverse: `192.168.1.50` → `mail.example.com`

### Why is it important?
- **Required** for many mail servers (Gmail, Outlook, etc.)
- Prevents spam classification
- Improves email deliverability

### How to use Auto-PTR
```bash
# Create A record with auto-PTR
addrec A mail example.com 192.168.1.50 --auto-ptr

# If PTR zone doesn't exist, auto-create it
addrec A mail example.com 192.168.1.50 \
  --auto-ptr \
  --auto-create-ptr-zone

# Verify FCrDNS
shrec --content=192.168.1.50 --with-ptr

# Update both A and PTR
chrec <record-id> --content=192.168.1.51 --update-ptr

# Delete both A and PTR
delrec <record-id> --delete-ptr
```

---

## Troubleshooting

### Command not found
```bash
# List all DNS commands
php artisan list | grep -E "(dns|zone|provider)"

# Clear cache
php artisan optimize:clear
```

### Provider connection failed
```bash
# Test provider connection
shdns 1 --test

# Check provider details
shdns 1 --detailed

# Update endpoint
chdns 1 --endpoint=http://correct-host:8081
```

### Zone sync issues
```bash
# Sync zone from remote
shzone example.com --sync

# View zone metadata
shzone example.com --with-metadata
```

### PTR record issues
```bash
# Check if PTR zone exists
shzone | grep "in-addr.arpa"

# Create PTR zone manually
addzone 1.168.192.in-addr.arpa. 1

# Then create A record with auto-PTR
addrec A mail example.com 192.168.1.50 --auto-ptr
```

---

## Tips & Best Practices

### 1. Always use --dry-run first
```bash
addrec A www example.com 192.168.1.100 --dry-run
# ✅ Review, then run without --dry-run
```

### 2. Use descriptive provider names
```bash
adddns "Production PowerDNS (ns1.example.com)" powerdns ...
# Better than: adddns "PowerDNS" powerdns ...
```

### 3. Enable DNSSEC for production
```bash
addzone example.com 1 --auto-dnssec
```

### 4. Always configure FCrDNS for mail servers
```bash
addrec A mail example.com 192.168.1.50 --auto-ptr --auto-create-ptr-zone
```

### 5. Document changes with comments
```bash
addrec A www example.com 192.168.1.100 --comment="Production web server"
```

### 6. Use filters to find records quickly
```bash
shrec --zone=example.com --type=A
shrec --content=192.168.1.100
shrec --search=mail
```

---

## Quick Reference Card

```
┌─────────────────────────────────────────────────────────┐
│  NetServa DNS Commands - Quick Reference                │
├─────────────────────────────────────────────────────────┤
│  PROVIDER: add/sh/ch/del + dns                          │
│  ZONE:     add/sh/ch/del + zone                         │
│  RECORD:   add/sh/ch/del + rec                          │
├─────────────────────────────────────────────────────────┤
│  Create A record with FCrDNS:                           │
│  addrec A mail example.com 192.168.1.50 --auto-ptr      │
│                                                          │
│  List all records in zone:                              │
│  shrec --zone=example.com                               │
│                                                          │
│  Update record content:                                 │
│  chrec <id> --content=<new-value>                       │
│                                                          │
│  Delete record with PTR:                                │
│  delrec <id> --delete-ptr                               │
└─────────────────────────────────────────────────────────┘
```

---

## Documentation

For complete documentation, see:
- `PHASES_1-2-3_COMPLETE.md` - Complete implementation summary
- `PHASE_1_COMPLETE.md` - Provider commands
- `PHASE_2_COMPLETE.md` - Zone commands
- `PHASE_3_COMPLETE.md` - Record commands + FCrDNS
- `DNS_COMMAND_ARCHITECTURE.md` - Architecture design

---

**Last Updated:** 2025-10-10
**Status:** ✅ Production Ready
