# DNS Command Architecture - Complete CRUD Hierarchy

**Status:** 📋 Planning Document
**Date:** 2025-10-10
**Package:** `netserva-dns`

---

## Architecture Overview

NetServa DNS management follows a **3-tier hierarchy** with shared service layer:

```
┌─────────────────────────────────────────────────────────────────┐
│                    DNS PROVIDER LAYER                           │
│  (Infrastructure: PowerDNS servers, Cloudflare accounts, etc.)  │
├─────────────────────────────────────────────────────────────────┤
│  Commands: adddnsprovider, shdnsprovider, chdnsprovider,       │
│            deldnsprovider                                       │
│  Model: DnsProvider (dns_providers table)                      │
│  Filament: DnsProviderResource ✅                              │
└─────────────────────────────────────────────────────────────────┘
                          ↓ contains
┌─────────────────────────────────────────────────────────────────┐
│                       DNS ZONE LAYER                            │
│  (DNS Zones: example.com, local.dev, reverse zones, etc.)      │
├─────────────────────────────────────────────────────────────────┤
│  Commands: addzone, shzone, chzone, delzone                    │
│  Model: DnsZone (dns_zones table)                              │
│  Filament: DnsZoneResource (exists)                            │
└─────────────────────────────────────────────────────────────────┘
                          ↓ contains
┌─────────────────────────────────────────────────────────────────┐
│                     DNS RECORD LAYER                            │
│  (DNS Records: A, AAAA, CNAME, MX, PTR, TXT, etc.)             │
├─────────────────────────────────────────────────────────────────┤
│  Commands: adddns, shdns, chdns, deldns                        │
│  Model: DnsRecord (dns_records table)                          │
│  Filament: DnsRecordResource (exists)                          │
└─────────────────────────────────────────────────────────────────┘

                    SHARED SERVICES LAYER
┌─────────────────────────────────────────────────────────────────┐
│  PowerDnsService, CloudflareService, Route53Service            │
│  FcrDnsValidationService, SshTunnelService                     │
│  Shared by both CLI commands and Filament UI                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Command Naming Convention

**Pattern:** `<action><resource>` (NO dashes, all lowercase)

**Actions:**
- `add` - Create new resource (C in CRUD)
- `sh` - Show/list resources (R in CRUD)
- `ch` - Change/update resource (U in CRUD)
- `del` - Delete resource (D in CRUD)

**Resources:**
- `dnsprovider` - DNS provider infrastructure
- `zone` - DNS zone (domain)
- `dns` - DNS record (A, CNAME, MX, etc.)

**Examples:**
- `adddnsprovider` NOT `add-dns-provider` or `dns:provider:add`
- `shzone` NOT `show-zone` or `zone:list`
- `chdns` NOT `change-dns` or `dns:update`

**Rationale:** Matches existing NetServa pattern (`addvhost`, `shvhost`, `chvhost`, `delvhost`)

---

## Tier 1: DNS Provider Commands

**Purpose:** Manage DNS provider infrastructure (PowerDNS servers, Cloudflare accounts, AWS Route53, etc.)

### 1.1 `adddnsprovider` - Create DNS Provider

**Signature:**
```bash
adddnsprovider <name> <type> [options]
```

**Arguments:**
- `name` - Provider name (e.g., "Homelab PowerDNS", "Cloudflare Production")
- `type` - Provider type (powerdns, cloudflare, route53, digitalocean, linode, hetzner, custom)

**Options:**
- `--endpoint=URL` - API endpoint (PowerDNS: http://192.168.1.1:8081)
- `--api-key=KEY` - API authentication key
- `--api-secret=SECRET` - API secret (for Cloudflare/Route53)
- `--ssh-host=HOST` - SSH host for tunnel access (PowerDNS remote)
- `--port=PORT` - API port (default: 8081 for PowerDNS)
- `--timeout=SEC` - Request timeout in seconds (default: 30)
- `--rate-limit=NUM` - Max requests per minute (default: 100)
- `--version=VER` - Provider version (e.g., "4.8.0")
- `--priority=NUM` - Sort order/priority (default: 0)
- `--inactive` - Create as inactive (default: active)
- `--email=EMAIL` - Account email (Cloudflare)
- `--region=REGION` - AWS region (Route53, default: us-east-1)
- `--access-key=KEY` - AWS access key ID (Route53)
- `--secret-key=KEY` - AWS secret access key (Route53)
- `--dry-run` - Show what would be created without creating it

**Examples:**
```bash
# PowerDNS on local server (direct connection)
adddnsprovider "Homelab PowerDNS" powerdns \
    --endpoint=http://192.168.1.1:8081 \
    --api-key=your-api-key \
    --version=4.8.0

# PowerDNS on remote server (SSH tunnel)
adddnsprovider "Remote PowerDNS" powerdns \
    --ssh-host=ns1.example.com \
    --endpoint=http://localhost:8081 \
    --api-key=your-api-key \
    --port=8081

# Cloudflare account
adddnsprovider "Cloudflare Prod" cloudflare \
    --api-key=cloudflare-global-key \
    --api-secret=cloudflare-secret \
    --email=admin@example.com

# AWS Route53
adddnsprovider "AWS Route53" route53 \
    --access-key=AKIAIOSFODNN7EXAMPLE \
    --secret-key=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY \
    --region=us-east-1

# Dry run to preview
adddnsprovider "Test Provider" powerdns \
    --endpoint=http://localhost:8081 \
    --api-key=test-key \
    --dry-run
```

**Output:**
```
🚀 Creating DNS Provider: Homelab PowerDNS
   Type: PowerDNS
   Endpoint: http://192.168.1.1:8081
   Port: 8081
   Timeout: 30s
   Rate Limit: 100 req/min

✅ DNS Provider created successfully
   ID: 1
   Name: Homelab PowerDNS
   Status: Active

🔍 Testing connection...
✅ Connection successful
   Server: PowerDNS Authoritative Server 4.8.0
   Zones: 12

💡 Next steps:
   - Assign to venue: See FleetVenueResource in Filament
   - Create zone: addzone example.com 1
   - View provider: shdnsprovider 1
```

**Service Method:**
```php
DnsProviderManagementService::createProvider(
    name: string,
    type: string,
    connectionConfig: array,
    options: array
): Result
```

---

### 1.2 `shdnsprovider` - Show DNS Provider(s)

**Signature:**
```bash
shdnsprovider [provider] [options]
```

**Arguments:**
- `provider` - Provider ID or name (optional, shows all if omitted)

**Options:**
- `--type=TYPE` - Filter by type (powerdns, cloudflare, etc.)
- `--active` - Show only active providers
- `--inactive` - Show only inactive providers
- `--with-zones` - Include zone count
- `--with-usage` - Show usage by venues/vsites/vnodes/vhosts
- `--json` - Output as JSON
- `--verbose` - Show detailed configuration

**Examples:**
```bash
# Show all providers (table format)
shdnsprovider

# Show specific provider by ID
shdnsprovider 1

# Show specific provider by name
shdnsprovider "Homelab PowerDNS"

# Show only active PowerDNS providers
shdnsprovider --type=powerdns --active

# Show with zone counts
shdnsprovider --with-zones

# Show with full usage details
shdnsprovider 1 --with-usage --verbose

# JSON output for scripting
shdnsprovider --json
```

**Output (table):**
```
DNS Providers
┌────┬──────────────────────┬───────────┬────────┬──────────────────────────────────┬───────┐
│ ID │ Name                 │ Type      │ Active │ Connection                       │ Zones │
├────┼──────────────────────┼───────────┼────────┼──────────────────────────────────┼───────┤
│ 1  │ Homelab PowerDNS     │ PowerDNS  │ ✅     │ http://192.168.1.1:8081          │ 12    │
│ 2  │ Cloudflare Prod      │ Cloudflare│ ✅     │ Email: admin@example.com         │ 45    │
│ 3  │ AWS Route53          │ Route53   │ ✅     │ Region: us-east-1                │ 8     │
│ 4  │ Backup PowerDNS      │ PowerDNS  │ ❌     │ SSH: ns2.example.com → :8081     │ 0     │
└────┴──────────────────────┴───────────┴────────┴──────────────────────────────────┴───────┘
```

**Output (single provider, verbose):**
```
DNS Provider: Homelab PowerDNS (ID: 1)
════════════════════════════════════════════════════════════════

Type:         PowerDNS
Active:       ✅ Yes
Version:      4.8.0
Priority:     0

Connection Configuration:
  Endpoint:   http://192.168.1.1:8081
  Port:       8081
  Timeout:    30s
  Rate Limit: 100 req/min

Statistics:
  Zones:      12
  Records:    234
  Last Sync:  2025-10-10 14:30:22

Usage:
  Venues:     1 (Homelab)
  VSites:     3 (Incus-Local, Proxmox-Cluster, Standalone)
  VNodes:     8
  VHosts:     45

Health:
  Status:     ✅ Healthy
  Last Check: 2025-10-10 14:35:10
  Response:   45ms
```

**Service Method:**
```php
DnsProviderManagementService::listProviders(filters: array): Collection
DnsProviderManagementService::showProvider(id: int|string, options: array): Result
```

---

### 1.3 `chdnsprovider` - Change DNS Provider

**Signature:**
```bash
chdnsprovider <provider> [options]
```

**Arguments:**
- `provider` - Provider ID or name

**Options:**
- `--name=NAME` - Change provider name
- `--endpoint=URL` - Update API endpoint
- `--api-key=KEY` - Update API key
- `--api-secret=SECRET` - Update API secret
- `--ssh-host=HOST` - Update SSH host
- `--port=PORT` - Update API port
- `--timeout=SEC` - Update timeout
- `--rate-limit=NUM` - Update rate limit
- `--version=VER` - Update version
- `--priority=NUM` - Update priority
- `--activate` - Set to active
- `--deactivate` - Set to inactive
- `--test-connection` - Test connection after update
- `--dry-run` - Show what would change without changing it

**Examples:**
```bash
# Update API key
chdnsprovider 1 --api-key=new-api-key

# Update endpoint and test
chdnsprovider "Homelab PowerDNS" \
    --endpoint=http://192.168.1.2:8081 \
    --test-connection

# Change name and priority
chdnsprovider 1 \
    --name="Primary PowerDNS" \
    --priority=10

# Deactivate provider
chdnsprovider "Backup PowerDNS" --deactivate

# Dry run
chdnsprovider 1 --endpoint=http://new-host:8081 --dry-run
```

**Output:**
```
🔧 Updating DNS Provider: Homelab PowerDNS (ID: 1)

Changes:
  Endpoint: http://192.168.1.1:8081 → http://192.168.1.2:8081

✅ DNS Provider updated successfully

🔍 Testing connection...
✅ Connection successful
   Server: PowerDNS Authoritative Server 4.8.0
   Response time: 38ms
```

**Service Method:**
```php
DnsProviderManagementService::updateProvider(
    id: int|string,
    updates: array,
    options: array
): Result
```

---

### 1.4 `deldnsprovider` - Delete DNS Provider

**Signature:**
```bash
deldnsprovider <provider> [options]
```

**Arguments:**
- `provider` - Provider ID or name

**Options:**
- `--force` - Delete even if zones exist or in use
- `--cascade` - Also delete all zones and records
- `--reassign=PROVIDER_ID` - Reassign zones to another provider
- `--dry-run` - Show what would be deleted without deleting

**Examples:**
```bash
# Delete unused provider (safe)
deldnsprovider 4

# Delete with cascade (removes all zones/records)
deldnsprovider "Old PowerDNS" --cascade

# Reassign zones to another provider before deleting
deldnsprovider 2 --reassign=1

# Force delete even if in use (dangerous!)
deldnsprovider 3 --force

# Dry run to preview
deldnsprovider 1 --dry-run
```

**Output:**
```
⚠️  Deleting DNS Provider: Backup PowerDNS (ID: 4)

Impact Analysis:
  Zones:      0
  Records:    0
  Venues:     0
  VSites:     0
  VNodes:     0
  VHosts:     0

✅ Safe to delete

Confirm deletion? (yes/no) [no]: yes

✅ DNS Provider deleted successfully
```

**Output (with zones/usage):**
```
⚠️  Deleting DNS Provider: Homelab PowerDNS (ID: 1)

❌ Cannot delete - provider is in use:
   Zones:      12
   Records:    234
   Venues:     1 (Homelab)
   VNodes:     8
   VHosts:     45

💡 Options:
   1. Reassign zones: deldnsprovider 1 --reassign=2
   2. Cascade delete: deldnsprovider 1 --cascade (DANGEROUS)
   3. Force delete:   deldnsprovider 1 --force (VERY DANGEROUS)
```

**Service Method:**
```php
DnsProviderManagementService::deleteProvider(
    id: int|string,
    options: array
): Result
```

---

## Tier 2: DNS Zone Commands

**Purpose:** Manage DNS zones (domains) within a DNS provider

### 2.1 `addzone` - Create DNS Zone

**Signature:**
```bash
addzone <zone> <provider> [options]
```

**Arguments:**
- `zone` - Zone name (e.g., example.com, local.dev, 1.168.192.in-addr.arpa)
- `provider` - DNS provider ID or name

**Options:**
- `--type=TYPE` - Zone type (primary, secondary, forward, default: primary)
- `--ttl=SECONDS` - Default TTL (default: 3600)
- `--nameservers=NS1,NS2` - Nameservers (comma-separated)
- `--soa-email=EMAIL` - SOA email address
- `--dnssec` - Enable DNSSEC
- `--auto-ptr` - Auto-create PTR records for reverse zones
- `--description=TEXT` - Zone description
- `--inactive` - Create as inactive (default: active)
- `--dry-run` - Show what would be created

**Examples:**
```bash
# Basic zone creation
addzone example.com 1

# Zone with DNSSEC enabled
addzone example.com "Homelab PowerDNS" --dnssec

# Reverse zone with auto-PTR
addzone 1.168.192.in-addr.arpa 1 --auto-ptr

# Custom TTL and nameservers
addzone local.dev 1 \
    --ttl=300 \
    --nameservers=ns1.local.dev,ns2.local.dev \
    --soa-email=admin@local.dev

# Secondary zone
addzone backup.example.com 2 --type=secondary

# Dry run
addzone test.example.com 1 --dry-run
```

**Output:**
```
🚀 Creating DNS Zone: example.com
   Provider: Homelab PowerDNS (ID: 1)
   Type: Primary
   TTL: 3600s
   DNSSEC: Disabled

✅ DNS Zone created successfully
   ID: 15
   Zone: example.com
   Status: Active
   Records: 5 (SOA, NS x2, A, AAAA)

💡 Next steps:
   - Add A record:     adddns A www.example.com 15 192.168.1.100
   - Add MX record:    adddns MX example.com 15 10 mail.example.com
   - View zone:        shzone 15
   - Enable DNSSEC:    chzone 15 --dnssec
```

**Service Method:**
```php
DnsZoneManagementService::createZone(
    zone: string,
    providerId: int|string,
    options: array
): Result
```

---

### 2.2 `shzone` - Show DNS Zone(s)

**Signature:**
```bash
shzone [zone] [options]
```

**Arguments:**
- `zone` - Zone ID or name (optional, shows all if omitted)

**Options:**
- `--provider=PROVIDER` - Filter by provider ID/name
- `--type=TYPE` - Filter by type (primary, secondary)
- `--active` - Show only active zones
- `--inactive` - Show only inactive zones
- `--with-records` - Include record count
- `--with-dnssec` - Show DNSSEC status
- `--json` - Output as JSON
- `--verbose` - Show detailed configuration

**Examples:**
```bash
# Show all zones
shzone

# Show specific zone by ID
shzone 15

# Show specific zone by name
shzone example.com

# Show zones for specific provider
shzone --provider=1

# Show with record counts
shzone --with-records

# Show DNSSEC status
shzone --with-dnssec

# Verbose output
shzone example.com --verbose

# JSON output
shzone --json
```

**Output (table):**
```
DNS Zones
┌────┬────────────────────────────┬──────────────────────┬─────────┬────────┬─────────┐
│ ID │ Zone                       │ Provider             │ Type    │ Active │ Records │
├────┼────────────────────────────┼──────────────────────┼─────────┼────────┼─────────┤
│ 15 │ example.com                │ Homelab PowerDNS     │ Primary │ ✅     │ 12      │
│ 16 │ local.dev                  │ Homelab PowerDNS     │ Primary │ ✅     │ 8       │
│ 17 │ 1.168.192.in-addr.arpa     │ Homelab PowerDNS     │ Primary │ ✅     │ 45      │
│ 18 │ goldcoast.org              │ Cloudflare Prod      │ Primary │ ✅     │ 234     │
└────┴────────────────────────────┴──────────────────────┴─────────┴────────┴─────────┘
```

**Output (single zone, verbose):**
```
DNS Zone: example.com (ID: 15)
════════════════════════════════════════════════════════════════

Provider:     Homelab PowerDNS (ID: 1)
Type:         Primary
Active:       ✅ Yes
TTL:          3600s

SOA Record:
  Primary NS:  ns1.example.com
  Email:       admin.example.com
  Serial:      2025101001
  Refresh:     3600
  Retry:       1800
  Expire:      604800
  Minimum:     300

Nameservers:
  ns1.example.com
  ns2.example.com

DNSSEC:
  Enabled:     ❌ No
  Keys:        0

Statistics:
  Records:     12
  A:           5
  AAAA:        2
  CNAME:       3
  MX:          2

Last Modified: 2025-10-10 14:45:30
```

**Service Method:**
```php
DnsZoneManagementService::listZones(filters: array): Collection
DnsZoneManagementService::showZone(id: int|string, options: array): Result
```

---

### 2.3 `chzone` - Change DNS Zone

**Signature:**
```bash
chzone <zone> [options]
```

**Arguments:**
- `zone` - Zone ID or name

**Options:**
- `--ttl=SECONDS` - Update default TTL
- `--nameservers=NS1,NS2` - Update nameservers
- `--soa-email=EMAIL` - Update SOA email
- `--dnssec` - Enable DNSSEC
- `--no-dnssec` - Disable DNSSEC
- `--activate` - Set to active
- `--deactivate` - Set to inactive
- `--description=TEXT` - Update description
- `--provider=PROVIDER_ID` - Move to different provider
- `--dry-run` - Show what would change

**Examples:**
```bash
# Update TTL
chzone 15 --ttl=7200

# Enable DNSSEC
chzone example.com --dnssec

# Update nameservers
chzone 15 --nameservers=ns1.example.com,ns2.example.com,ns3.example.com

# Move zone to different provider
chzone example.com --provider=2

# Deactivate zone
chzone "old.example.com" --deactivate

# Dry run
chzone 15 --dnssec --dry-run
```

**Output:**
```
🔧 Updating DNS Zone: example.com (ID: 15)

Changes:
  TTL: 3600s → 7200s
  DNSSEC: Disabled → Enabled

✅ DNS Zone updated successfully

🔐 Generating DNSSEC keys...
✅ KSK generated (ID: 1)
✅ ZSK generated (ID: 2)
✅ Zone signed

💡 Next steps:
   - View DNSSEC status: shzone 15 --with-dnssec
   - Get DS records: shdns DNSKEY example.com 15
```

**Service Method:**
```php
DnsZoneManagementService::updateZone(
    id: int|string,
    updates: array,
    options: array
): Result
```

---

### 2.4 `delzone` - Delete DNS Zone

**Signature:**
```bash
delzone <zone> [options]
```

**Arguments:**
- `zone` - Zone ID or name

**Options:**
- `--cascade` - Also delete all DNS records
- `--force` - Delete even if records exist
- `--dry-run` - Show what would be deleted

**Examples:**
```bash
# Delete empty zone (safe)
delzone 20

# Delete zone with all records
delzone example.com --cascade

# Force delete
delzone 15 --force

# Dry run
delzone example.com --dry-run
```

**Output:**
```
⚠️  Deleting DNS Zone: example.com (ID: 15)

Impact Analysis:
  Records:    12
  A:          5
  AAAA:       2
  CNAME:      3
  MX:         2

❌ Cannot delete - zone has records

💡 Options:
   1. Delete with records: delzone 15 --cascade
   2. Force delete:        delzone 15 --force
```

**Service Method:**
```php
DnsZoneManagementService::deleteZone(
    id: int|string,
    options: array
): Result
```

---

## Tier 3: DNS Record Commands

**Purpose:** Manage DNS records (A, AAAA, CNAME, MX, PTR, TXT, etc.) within zones

### 3.1 `adddns` - Create DNS Record

**Signature:**
```bash
adddns <type> <name> <zone> <content> [options]
```

**Arguments:**
- `type` - Record type (A, AAAA, CNAME, MX, PTR, TXT, NS, SRV, CAA, etc.)
- `name` - Record name (e.g., www, mail, @, *.subdomain)
- `zone` - Zone ID or name
- `content` - Record content (IP, hostname, text, etc.)

**Options:**
- `--ttl=SECONDS` - TTL (default: zone default)
- `--priority=NUM` - Priority (for MX, SRV)
- `--weight=NUM` - Weight (for SRV)
- `--port=NUM` - Port (for SRV)
- `--disabled` - Create as disabled (default: enabled)
- `--comment=TEXT` - Record comment/note
- `--auto-ptr` - Auto-create PTR for A/AAAA records
- `--dry-run` - Show what would be created

**Examples:**
```bash
# A record
adddns A www example.com 192.168.1.100

# A record with custom TTL
adddns A www example.com 192.168.1.100 --ttl=300

# AAAA record (IPv6)
adddns AAAA www example.com 2001:db8::1

# CNAME record
adddns CNAME mail example.com mailserver.example.com

# MX record with priority
adddns MX @ example.com mail.example.com --priority=10

# PTR record (reverse DNS)
adddns PTR 100 1.168.192.in-addr.arpa www.example.com

# A record with auto-PTR creation
adddns A mail example.com 192.168.1.50 --auto-ptr

# TXT record (SPF)
adddns TXT @ example.com "v=spf1 mx a ip4:192.168.1.0/24 -all"

# Wildcard record
adddns A "*.subdomain" example.com 192.168.1.200

# SRV record
adddns SRV _http._tcp example.com web.example.com \
    --priority=10 --weight=5 --port=80

# CAA record (Certificate Authority Authorization)
adddns CAA @ example.com "0 issue letsencrypt.org"

# Dry run
adddns A test example.com 192.168.1.99 --dry-run
```

**Output:**
```
🚀 Creating DNS Record
   Type: A
   Name: www.example.com
   Zone: example.com (ID: 15)
   Content: 192.168.1.100
   TTL: 3600s

✅ DNS Record created successfully
   ID: 234
   FQDN: www.example.com
   Type: A
   Content: 192.168.1.100
   Status: Enabled

💡 Next steps:
   - Verify propagation: dig @192.168.1.1 www.example.com
   - View record: shdns 234
   - Test FCrDNS: dns:verify www.example.com 192.168.1.100
```

**Output (with auto-PTR):**
```
🚀 Creating DNS Record
   Type: A
   Name: mail.example.com
   Zone: example.com (ID: 15)
   Content: 192.168.1.50
   TTL: 3600s
   Auto-PTR: Enabled

✅ DNS Record created successfully
   ID: 235
   FQDN: mail.example.com
   Type: A
   Content: 192.168.1.50

🔄 Creating PTR record...
✅ PTR Record created successfully
   ID: 236
   Zone: 1.168.192.in-addr.arpa (ID: 17)
   Name: 50.1.168.192.in-addr.arpa
   Content: mail.example.com

✅ FCrDNS Complete
   Forward (A):   mail.example.com → 192.168.1.50
   Reverse (PTR): 192.168.1.50 → mail.example.com
   Status: ✅ Valid for email delivery
```

**Service Method:**
```php
DnsRecordManagementService::createRecord(
    type: string,
    name: string,
    zoneId: int|string,
    content: string,
    options: array
): Result
```

---

### 3.2 `shdns` - Show DNS Record(s)

**Signature:**
```bash
shdns [record] [options]
```

**Arguments:**
- `record` - Record ID, name, or search pattern (optional)

**Options:**
- `--zone=ZONE` - Filter by zone ID/name
- `--type=TYPE` - Filter by record type (A, AAAA, CNAME, etc.)
- `--name=NAME` - Filter by record name
- `--content=CONTENT` - Filter by content (IP, hostname, etc.)
- `--enabled` - Show only enabled records
- `--disabled` - Show only disabled records
- `--json` - Output as JSON
- `--verbose` - Show detailed information
- `--verify` - Test DNS resolution (live query)

**Examples:**
```bash
# Show all records
shdns

# Show specific record by ID
shdns 234

# Show records for zone
shdns --zone=example.com

# Show A records only
shdns --zone=example.com --type=A

# Show records matching name pattern
shdns --name=www --zone=example.com

# Show MX records
shdns --type=MX --zone=example.com

# Show records with specific IP
shdns --content=192.168.1.100

# Verbose output
shdns 234 --verbose

# Verify DNS resolution
shdns www.example.com --verify

# JSON output
shdns --zone=example.com --json
```

**Output (table):**
```
DNS Records (example.com)
┌─────┬──────┬─────────────────────────┬───────────────────────────┬──────┬─────────┐
│ ID  │ Type │ Name                    │ Content                   │ TTL  │ Enabled │
├─────┼──────┼─────────────────────────┼───────────────────────────┼──────┼─────────┤
│ 230 │ A    │ @                       │ 192.168.1.100             │ 3600 │ ✅      │
│ 231 │ A    │ www                     │ 192.168.1.100             │ 3600 │ ✅      │
│ 232 │ AAAA │ www                     │ 2001:db8::1               │ 3600 │ ✅      │
│ 233 │ CNAME│ mail                    │ mailserver.example.com    │ 3600 │ ✅      │
│ 234 │ MX   │ @                       │ 10 mail.example.com       │ 3600 │ ✅      │
│ 235 │ TXT  │ @                       │ v=spf1 mx a -all          │ 3600 │ ✅      │
└─────┴──────┴─────────────────────────┴───────────────────────────┴──────┴─────────┘
```

**Output (single record, verbose):**
```
DNS Record: www.example.com (ID: 234)
════════════════════════════════════════════════════════════════

Zone:         example.com (ID: 15)
Provider:     Homelab PowerDNS (ID: 1)
Type:         A
Name:         www
FQDN:         www.example.com
Content:      192.168.1.100
TTL:          3600s
Enabled:      ✅ Yes
Comment:      Web server primary IP

Created:      2025-10-10 10:30:15
Modified:     2025-10-10 14:20:42

🔍 DNS Resolution Test:
   Query:     dig @192.168.1.1 www.example.com A
   Result:    192.168.1.100
   Status:    ✅ Resolves correctly
   Latency:   12ms
```

**Service Method:**
```php
DnsRecordManagementService::listRecords(filters: array): Collection
DnsRecordManagementService::showRecord(id: int|string, options: array): Result
```

---

### 3.3 `chdns` - Change DNS Record

**Signature:**
```bash
chdns <record> [options]
```

**Arguments:**
- `record` - Record ID or FQDN

**Options:**
- `--content=CONTENT` - Update record content
- `--ttl=SECONDS` - Update TTL
- `--priority=NUM` - Update priority (MX, SRV)
- `--weight=NUM` - Update weight (SRV)
- `--port=NUM` - Update port (SRV)
- `--enable` - Enable record
- `--disable` - Disable record
- `--comment=TEXT` - Update comment
- `--dry-run` - Show what would change

**Examples:**
```bash
# Update record content (change IP)
chdns 234 --content=192.168.1.101

# Update TTL
chdns www.example.com --ttl=300

# Update MX priority
chdns 235 --priority=20

# Disable record
chdns www.example.com --disable

# Enable record
chdns 234 --enable

# Update comment
chdns 234 --comment="Web server load balancer IP"

# Multiple changes
chdns www.example.com --content=192.168.1.102 --ttl=600

# Dry run
chdns 234 --content=192.168.1.200 --dry-run
```

**Output:**
```
🔧 Updating DNS Record: www.example.com (ID: 234)

Changes:
  Content: 192.168.1.100 → 192.168.1.101
  TTL: 3600s → 300s

✅ DNS Record updated successfully

🔍 Verifying DNS propagation...
✅ Resolves correctly
   Query result: 192.168.1.101

💡 Note: Changes may take up to 5 minutes to propagate
```

**Service Method:**
```php
DnsRecordManagementService::updateRecord(
    id: int|string,
    updates: array,
    options: array
): Result
```

---

### 3.4 `deldns` - Delete DNS Record

**Signature:**
```bash
deldns <record> [options]
```

**Arguments:**
- `record` - Record ID or FQDN

**Options:**
- `--type=TYPE` - Specify type if using FQDN (for disambiguation)
- `--force` - Skip confirmation
- `--dry-run` - Show what would be deleted

**Examples:**
```bash
# Delete by ID
deldns 234

# Delete by FQDN
deldns www.example.com

# Delete specific type (if multiple records with same name)
deldns www.example.com --type=A

# Force delete without confirmation
deldns 234 --force

# Dry run
deldns www.example.com --dry-run
```

**Output:**
```
⚠️  Deleting DNS Record

Record Details:
  ID:       234
  Type:     A
  FQDN:     www.example.com
  Zone:     example.com
  Content:  192.168.1.100

Confirm deletion? (yes/no) [no]: yes

✅ DNS Record deleted successfully

🔍 Verifying DNS resolution...
⚠️  Record no longer resolves (expected after deletion)
```

**Service Method:**
```php
DnsRecordManagementService::deleteRecord(
    id: int|string,
    options: array
): Result
```

---

## Shared Service Architecture

All commands (DNS Provider, Zone, Record) share the same service layer as the Filament UI:

```
┌─────────────────────────────────────────────────────────────────┐
│                     CLI COMMANDS LAYER                          │
│  adddnsprovider, shdnsprovider, chdnsprovider, deldnsprovider  │
│  addzone, shzone, chzone, delzone                              │
│  adddns, shdns, chdns, deldns                                  │
└───────────────────────┬─────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────────┐
│                   FILAMENT UI LAYER                             │
│  DnsProviderResource, DnsZoneResource, DnsRecordResource       │
└───────────────────────┬─────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────────┐
│                    SERVICE LAYER (SHARED)                       │
│  DnsProviderManagementService - Provider CRUD + health checks   │
│  DnsZoneManagementService - Zone CRUD + DNSSEC                 │
│  DnsRecordManagementService - Record CRUD + FCrDNS            │
│  PowerDnsService - PowerDNS API client                         │
│  CloudflareService - Cloudflare API client                     │
│  Route53Service - AWS Route53 API client                       │
│  FcrDnsValidationService - Forward/Reverse DNS validation      │
│  SshTunnelService - SSH tunnel management                      │
└───────────────────────┬─────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────────┐
│                      MODEL LAYER                                │
│  DnsProvider, DnsZone, DnsRecord                               │
│  FleetVenue, FleetVSite, FleetVNode, FleetVHost (relationships)│
└─────────────────────────────────────────────────────────────────┘
```

---

## Command Registration

**File:** `packages/netserva-dns/src/NetServaDnsServiceProvider.php`

```php
public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            // Tier 1: DNS Provider Management
            Console\Commands\AddDnsProviderCommand::class,
            Console\Commands\ShowDnsProviderCommand::class,
            Console\Commands\ChangeDnsProviderCommand::class,
            Console\Commands\DeleteDnsProviderCommand::class,

            // Tier 2: DNS Zone Management
            Console\Commands\AddZoneCommand::class,
            Console\Commands\ShowZoneCommand::class,
            Console\Commands\ChangeZoneCommand::class,
            Console\Commands\DeleteZoneCommand::class,

            // Tier 3: DNS Record Management
            Console\Commands\AddDnsCommand::class,
            Console\Commands\ShowDnsCommand::class,
            Console\Commands\ChangeDnsCommand::class,
            Console\Commands\DeleteDnsCommand::class,

            // Utilities (existing)
            Console\Commands\DnsVerifyCommand::class,
            Console\Commands\PowerDnsCommand::class,
            Console\Commands\PowerDnsManagementCommand::class,
        ]);
    }
}
```

---

## Workflow Examples

### Example 1: Complete DNS Setup for New Domain

```bash
# 1. Create DNS provider (if not exists)
adddnsprovider "Homelab PowerDNS" powerdns \
    --endpoint=http://192.168.1.1:8081 \
    --api-key=secret-key

# 2. Create zone
addzone example.com 1 --dnssec

# 3. Add DNS records
adddns A @ example.com 192.168.1.100
adddns A www example.com 192.168.1.100
adddns AAAA www example.com 2001:db8::1
adddns MX @ example.com mail.example.com --priority=10
adddns A mail example.com 192.168.1.50 --auto-ptr
adddns TXT @ example.com "v=spf1 mx a ip4:192.168.1.0/24 -all"
adddns TXT _dmarc example.com "v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com"

# 4. Verify FCrDNS for mail server
dns:verify mail.example.com 192.168.1.50

# 5. View complete zone
shzone example.com --verbose
```

### Example 2: Migrate Zone Between Providers

```bash
# 1. Export zone from old provider
shzone example.com --json > /tmp/example.com.json

# 2. Create zone on new provider
addzone example.com 2

# 3. Import records (bulk operation)
# TODO: Implement bulk import from JSON

# 4. Verify migration
shzone example.com --zone=2 --verbose

# 5. Update zone to new provider
chzone example.com --provider=2

# 6. Delete old zone
delzone example.com --zone=1 --cascade
```

### Example 3: Email Server Setup (FCrDNS)

```bash
# 1. Add mail server A record with auto-PTR
adddns A mail example.com 192.168.1.50 --auto-ptr

# 2. Verify FCrDNS
dns:verify mail.example.com 192.168.1.50

# 3. Add MX record
adddns MX @ example.com mail.example.com --priority=10

# 4. Add SPF record
adddns TXT @ example.com "v=spf1 mx a ip4:192.168.1.0/24 -all"

# 5. Add DKIM record
adddns TXT default._domainkey example.com "v=DKIM1; k=rsa; p=MIGfMA0G..."

# 6. Add DMARC record
adddns TXT _dmarc example.com "v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com"

# 7. View all email-related records
shdns --zone=example.com --type=MX
shdns --zone=example.com --type=TXT
```

---

## Implementation Priority

### Phase 1: DNS Provider Commands (Week 1)
1. ✅ Review existing `DnsProvider` model and relationships
2. Create `DnsProviderManagementService`
3. Create `AddDnsProviderCommand`
4. Create `ShowDnsProviderCommand`
5. Create `ChangeDnsProviderCommand`
6. Create `DeleteDnsProviderCommand`
7. Write Pest tests

### Phase 2: DNS Zone Commands (Week 2)
1. Review existing `DnsZone` model
2. Create `DnsZoneManagementService`
3. Create `AddZoneCommand`
4. Create `ShowZoneCommand`
5. Create `ChangeZoneCommand`
6. Create `DeleteZoneCommand`
7. Write Pest tests

### Phase 3: DNS Record Commands (Week 3)
1. Review existing `DnsRecord` model
2. Create `DnsRecordManagementService`
3. Create `AddDnsCommand`
4. Create `ShowDnsCommand`
5. Create `ChangeDnsCommand`
6. Create `DeleteDnsCommand`
7. Implement auto-PTR functionality
8. Write Pest tests

### Phase 4: Integration & Documentation (Week 4)
1. Integration testing (all tiers working together)
2. FCrDNS workflow validation
3. Documentation updates
4. User guide creation
5. Video tutorials (optional)

---

## Testing Strategy

**Test Coverage:**
- Unit tests for services (80%+ coverage)
- Feature tests for commands (100% coverage)
- Integration tests for workflows (key scenarios)

**Example Test (Pest 4.0):**

```php
describe('AddDnsProviderCommand', function () {
    it('creates PowerDNS provider successfully', function () {
        $this->artisan('adddnsprovider', [
            'name' => 'Test PowerDNS',
            'type' => 'powerdns',
            '--endpoint' => 'http://localhost:8081',
            '--api-key' => 'test-key',
        ])->assertSuccessful();

        expect(DnsProvider::where('name', 'Test PowerDNS')->exists())->toBeTrue();
    });

    it('validates required arguments', function () {
        $this->artisan('adddnsprovider')
            ->assertFailed();
    });

    it('tests connection after creation', function () {
        // Mock PowerDnsService::testConnection()
        $this->mock(PowerDnsService::class)
            ->shouldReceive('testConnection')
            ->andReturn(['success' => true, 'version' => '4.8.0']);

        $this->artisan('adddnsprovider', [
            'name' => 'Test PowerDNS',
            'type' => 'powerdns',
            '--endpoint' => 'http://localhost:8081',
            '--api-key' => 'test-key',
        ])->assertSuccessful()
          ->expectsOutput('✅ Connection successful');
    });
});
```

---

## Documentation Files to Create

1. **DNS_PROVIDER_COMMANDS.md** - Complete reference for provider commands
2. **DNS_ZONE_COMMANDS.md** - Complete reference for zone commands
3. **DNS_RECORD_COMMANDS.md** - Complete reference for record commands
4. **DNS_WORKFLOWS.md** - Common workflows and examples
5. **DNS_FCRDNS_GUIDE.md** - Email server setup with FCrDNS
6. **DNS_SPLIT_HORIZON_GUIDE.md** - Homelab split-horizon DNS setup

---

**Version:** 1.0.0
**Status:** 📋 Planning Complete - Ready for Implementation
**Next Step:** Create `DnsProviderManagementService` and Phase 1 commands
