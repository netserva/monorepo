# PowerDNS DNS-01 ACME Certificate Automation - Final Solution

## Problem Summary
PBS (Proxmox Backup Server) certificate renewals were failing with DNS-01 validation because PowerDNS SOA serial wasn't incrementing after API updates, causing secondary nameservers (ns2, ns3) to ignore NOTIFY messages.

## Root Cause
PowerDNS 4.9.7 with gmysql backend requires **explicit per-zone metadata** to enable automatic SOA serial incrementing for API updates. Without this metadata, the SOA serial remains static when records are added/modified via API, breaking the DNS NOTIFY/AXFR mechanism.

## The Solution

### 1. PowerDNS Primary Server (ns1gc) Configuration

#### Set SOA-EDIT-API Metadata
```bash
pdnsutil set-meta goldcoast.org SOA-EDIT-API DEFAULT
pdnsutil set-meta goldcoast.org SOA-EDIT-DNSUPDATE DEFAULT
pdnsutil set-meta goldcoast.org NOTIFY-DNSUPDATE 1
```

**Verify:**
```bash
pdnsutil show-zone goldcoast.org | grep -A5 Metadata
```

**Expected output:**
```
Metadata items:
	NOTIFY-DNSUPDATE	1
	SOA-EDIT-API	DEFAULT
	SOA-EDIT-DNSUPDATE	DEFAULT
```

#### Valid SOA-EDIT-API Values
- `DEFAULT` - Increments serial using YYYYMMDDXX format ✅
- `INCREASE` - Simple numeric increment ✅
- `EPOCH` - Unix timestamp ✅
- `SOA-EDIT` - Uses value from SOA-EDIT metadata ✅
- `SOA-EDIT-INCREASE` - Combination ✅

**Invalid values (only for SOA-EDIT, not SOA-EDIT-API):**
- ❌ `INCEPTION-INCREMENT` - Causes "unknown" error
- ❌ `INCREMENT-WEEKS` - Causes "unknown" error

### 2. PowerDNS Configuration (/etc/powerdns/pdns.conf)

```ini
# API Configuration for ACME DNS-01
webserver=yes
webserver-address=0.0.0.0
webserver-port=8081
webserver-allow-from=127.0.0.1,::1,192.168.1.0/24,120.88.117.136
api=yes
api-key=pdns_api_ns1gc_d5eeaf3798da9ba265c6415720de444c

# NOTIFY configuration for ns2 and ns3
also-notify=175.45.182.28,103.16.131.18
only-notify=0.0.0.0/0
```

### 3. PBS Servers Configuration

#### File: /etc/proxmox-backup/node.cfg
```ini
acme: account=GoldcoastORG
acmedomain0: pbs4.goldcoast.org,plugin=PowerDNS
email-from: pbs4@goldcoast.org
```

#### File: /etc/proxmox-backup/acme/plugins.cfg
```
dns: PowerDNS
	api pdns
	data UEROU19Vcmw9aHR0cDovL25zMS5nb2xkY29hc3Qub3JnOjgwODEKUEROU19TZXJ2ZXJJZD1sb2NhbGhvc3QKUEROU19Ub2tlbj1wZG5zX2FwaV9uczFnY19kNWVlYWYzNzk4ZGE5YmEyNjVjNjQxNTcyMGRlNDQ0YwpQRE5TX1R0bD02MAo=
```

**Decoded data:**
```
PDNS_Url=http://ns1.goldcoast.org:8081
PDNS_ServerId=localhost
PDNS_Token=pdns_api_ns1gc_d5eeaf3798da9ba265c6415720de444c
PDNS_Ttl=60
```

#### File: /usr/share/proxmox-acme/proxmox-acme (patched)
**Modified _load_plugin_config() function to hardcode PowerDNS credentials:**
```bash
_load_plugin_config() {
  export PDNS_Url="http://ns1.goldcoast.org:8081"
  export PDNS_ServerId="localhost"
  export PDNS_Token="pdns_api_ns1gc_d5eeaf3798da9ba265c6415720de444c"
  export PDNS_Ttl="60"
  while IFS= read -r line; do
    ADDR=(${line/=/ })
    key="${ADDR[0]}"
    value="${ADDR[1]}"
    if [ -n "$key" ]; then
      export "$key"="$value"
    fi
  done
}
```

**Note:** This patch is needed because plugin data from plugins.cfg isn't properly passed to dns_pdns.sh.

## How It Works

1. **PBS initiates certificate renewal:**
   ```bash
   proxmox-backup-manager acme cert order --force
   ```

2. **dns_pdns.sh adds ACME challenge TXT record via PowerDNS API:**
   - API PATCH request to `/api/v1/servers/localhost/zones/goldcoast.org`
   - PowerDNS automatically increments SOA serial (SOA-EDIT-API = DEFAULT)
   - New serial format: YYYYMMDDXX (e.g., 2025100401, 2025100402)

3. **dns_pdns.sh triggers NOTIFY:**
   - Calls `notify_slaves()` function
   - API PUT request to `/api/v1/servers/localhost/zones/goldcoast.org/notify`
   - PowerDNS sends NOTIFY to ns2 and ns3

4. **Secondary nameservers (ns2, ns3) receive NOTIFY:**
   - Compare SOA serial with their cached version
   - Detect change and initiate AXFR zone transfer
   - Update their zones with new TXT record

5. **ACME validation succeeds:**
   - Let's Encrypt queries _acme-challenge.pbs4.goldcoast.org
   - Gets response from ns2/ns3 (geographically distributed)
   - Validates TXT record matches expected value
   - Issues certificate

6. **Cleanup:**
   - dns_pdns.sh removes TXT record via API
   - SOA serial increments again
   - NOTIFY triggers zone transfer
   - Secondaries remove TXT record

## Verification

### Test SOA Incrementing
```bash
# Get current SOA
dig @ns1.goldcoast.org goldcoast.org SOA +short

# Add test TXT record via API
curl -X PATCH "http://ns1.goldcoast.org:8081/api/v1/servers/localhost/zones/goldcoast.org" \
  -H "X-API-Key: pdns_api_ns1gc_d5eeaf3798da9ba265c6415720de444c" \
  -H "Content-Type: application/json" \
  -d '{"rrsets":[{"changetype":"REPLACE","name":"_test.goldcoast.org.","type":"TXT","ttl":60,"records":[{"content":"\"test\"","disabled":false}]}]}'

# Verify SOA incremented
dig @ns1.goldcoast.org goldcoast.org SOA +short

# Trigger NOTIFY
curl -X PUT "http://ns1.goldcoast.org:8081/api/v1/servers/localhost/zones/goldcoast.org/notify" \
  -H "X-API-Key: pdns_api_ns1gc_d5eeaf3798da9ba265c6415720de444c"

# Verify secondaries received update
sleep 5
dig @ns2.goldcoast.org goldcoast.org SOA +short
dig @ns3.goldcoast.org goldcoast.org SOA +short
```

### Test Certificate Renewal
```bash
# PBS4
ssh pbs4 "proxmox-backup-manager cert info | grep 'Not After'"
ssh pbs4 "proxmox-backup-manager acme cert order --force"

# PBS3
ssh pbs3 "proxmox-backup-manager cert info | grep 'Not After'"
ssh pbs3 "proxmox-backup-manager acme cert order --force"

# PBS2
ssh pbs2 "proxmox-backup-manager cert info | grep 'Not After'"
ssh pbs2 "proxmox-backup-manager acme cert order --force"
```

## Post-Renewal: Update PBS Fingerprints in PVE Storage

**CRITICAL:** After renewing PBS certificates, you **must** update the SSL fingerprints in PVE cluster storage configuration. Otherwise, PBS datastores will become inactive with fingerprint verification errors.

### Why This Is Needed
When PBS certificates are renewed, the SSL certificate fingerprint changes. Proxmox VE uses certificate fingerprints to verify the authenticity of PBS connections. Without updating the fingerprint, you'll see errors like:

```
TASK ERROR: could not activate storage 'pbs2': pbs2: error fetching datastores -
fingerprint '82:B6:...' not verified, abort!
```

### Step 1: Get New Fingerprints from PBS Servers

```bash
# Get new fingerprints from all PBS servers
for pbs in pbs2 pbs3 pbs4; do
  echo "=== $pbs new fingerprint ==="
  ssh $pbs "proxmox-backup-manager cert info | grep Fingerprint"
done
```

**Example output:**
```
=== pbs2 new fingerprint ===
Fingerprint (sha256): 82:b6:40:b0:13:cd:c5:10:7a:b2:1f:9f:dd:de:7c:15:9e:c9:85:6c:b1:c9:08:21:f0:62:ea:d3:0d:23:14:ae

=== pbs3 new fingerprint ===
Fingerprint (sha256): fe:ab:d7:d8:c4:dd:46:3e:ac:b1:eb:e8:95:39:7b:60:81:a3:6a:bb:8e:9a:95:34:e5:ad:41:7a:fd:43:46:1f

=== pbs4 new fingerprint ===
Fingerprint (sha256): c2:71:f1:89:ba:63:2b:ab:6f:25:54:9a:05:a2:24:13:45:52:5b:82:85:9c:09:0e:31:e8:f8:9e:dd:10:5b:8a
```

### Step 2: Update PVE Storage Configuration

The storage configuration is shared across the PVE cluster at `/etc/pve/storage.cfg`. Update it on **any one node** (changes propagate automatically via cluster filesystem):

```bash
# SSH to any PVE cluster node
ssh pve2

# Backup current config
cp /etc/pve/storage.cfg /etc/pve/storage.cfg.backup.$(date +%Y%m%d_%H%M%S)

# Update fingerprints (use actual values from Step 1)
# For pbs2
sed -i '/^pbs: pbs2/,/^$/ s/fingerprint .*/fingerprint 82:b6:40:b0:13:cd:c5:10:7a:b2:1f:9f:dd:de:7c:15:9e:c9:85:6c:b1:c9:08:21:f0:62:ea:d3:0d:23:14:ae/' /etc/pve/storage.cfg

# For pbs3
sed -i '/^pbs: pbs3/,/^$/ s/fingerprint .*/fingerprint fe:ab:d7:d8:c4:dd:46:3e:ac:b1:eb:e8:95:39:7b:60:81:a3:6a:bb:8e:9a:95:34:e5:ad:41:7a:fd:43:46:1f/' /etc/pve/storage.cfg

# For pbs4
sed -i '/^pbs: pbs4/,/^$/ s/fingerprint .*/fingerprint c2:71:f1:89:ba:63:2b:ab:6f:25:54:9a:05:a2:24:13:45:52:5b:82:85:9c:09:0e:31:e8:f8:9e:dd:10:5b:8a/' /etc/pve/storage.cfg
```

**Automated script method:**
```bash
cat << 'EOF' > /tmp/update_pbs_fingerprints.sh
#!/bin/bash
# Update PBS fingerprints in /etc/pve/storage.cfg

STORAGE_CFG="/etc/pve/storage.cfg"

# Backup
cp "$STORAGE_CFG" "${STORAGE_CFG}.backup.$(date +%Y%m%d_%H%M%S)"

# Update pbs2 fingerprint
sed -i '/^pbs: pbs2/,/^$/ s/fingerprint .*/fingerprint 82:b6:40:b0:13:cd:c5:10:7a:b2:1f:9f:dd:de:7c:15:9e:c9:85:6c:b1:c9:08:21:f0:62:ea:d3:0d:23:14:ae/' "$STORAGE_CFG"

# Update pbs3 fingerprint
sed -i '/^pbs: pbs3/,/^$/ s/fingerprint .*/fingerprint fe:ab:d7:d8:c4:dd:46:3e:ac:b1:eb:e8:95:39:7b:60:81:a3:6a:bb:8e:9a:95:34:e5:ad:41:7a:fd:43:46:1f/' "$STORAGE_CFG"

# Update pbs4 fingerprint
sed -i '/^pbs: pbs4/,/^$/ s/fingerprint .*/fingerprint c2:71:f1:89:ba:63:2b:ab:6f:25:54:9a:05:a2:24:13:45:52:5b:82:85:9c:09:0e:31:e8:f8:9e:dd:10:5b:8a/' "$STORAGE_CFG"

echo "Updated PBS fingerprints in $STORAGE_CFG"
EOF

chmod +x /tmp/update_pbs_fingerprints.sh
/tmp/update_pbs_fingerprints.sh
```

### Step 3: Verify PBS Storage Is Active

Check that all PBS datastores are now active across all PVE cluster nodes:

```bash
# Check on all PVE nodes
for host in pve2 pve3 pve4; do
  echo "=== $host PBS storage status ==="
  ssh $host "pvesm status | grep pbs"
done
```

**Expected output (all should show "active"):**
```
=== pve2 PBS storage status ===
pbs2              pbs     active      3771031808      1116365952      2654665856   29.60%
pbs3              pbs     active      3771032192      1147404672      2623627520   30.43%
pbs4              pbs     active      4089446400      1678517504      2410928896   41.05%

=== pve3 PBS storage status ===
pbs2              pbs     active      3771031808      1116365952      2654665856   29.60%
pbs3              pbs     active      3771032192      1147404672      2623627520   30.43%
pbs4              pbs     active      4089446400      1678517504      2410928896   41.05%

=== pve4 PBS storage status ===
pbs2              pbs     active      3771031808      1116365952      2654665856   29.60%
pbs3              pbs     active      3771032192      1147404672      2623627520   30.43%
pbs4              pbs     active      4089446400      1678517504      2410928896   41.05%
```

### Step 4: Test Datastore Access

Verify you can list backups from a PBS datastore:

```bash
# Test pbs2 datastore access
ssh pve2 "pvesm list pbs2 | head -5"
```

**Expected output:**
```
Volid                                   Format  Type               Size VMID
pbs2:backup/ct/100/2025-08-14T02:15:02Z pbs-ct  backup       1483095308 100
pbs2:backup/ct/100/2025-09-28T08:10:00Z pbs-ct  backup       2325116999 100
pbs2:backup/ct/100/2025-10-03T08:10:01Z pbs-ct  backup       2324692180 100
```

### Alternative: GUI Method

You can also update fingerprints via the Proxmox VE web interface:

1. Navigate to **Datacenter → Storage**
2. Select the PBS storage (e.g., `pbs2`)
3. Click **Edit**
4. Paste the new fingerprint in the **Fingerprint** field
5. Click **OK**
6. Repeat for other PBS datastores (pbs3, pbs4)

### Important Notes

- **Cluster filesystem:** `/etc/pve/storage.cfg` is shared across the cluster. Changes on one node propagate to all nodes automatically (usually within seconds).
- **No service restart needed:** Changes take effect immediately.
- **Backup first:** Always backup `storage.cfg` before making changes.
- **Certificate renewal automation:** Consider creating a script to update fingerprints automatically after PBS certificate renewals.

## Current Certificate Status (2025-10-04)

### PBS Servers
- **PBS2:** Expires Jan 2 01:15:14 2026 GMT ✅
- **PBS3:** Expires Jan 2 01:14:54 2026 GMT ✅
- **PBS4:** Expires Jan 2 01:14:26 2026 GMT ✅

### PVE Servers
- **PVE2:** Expires Jan 2 01:57:45 2026 GMT ✅
- **PVE3:** Expires Jan 2 01:57:22 2026 GMT ✅
- **PVE4:** Expires Jan 2 01:56:53 2026 GMT ✅

All renewed automatically without manual intervention!

### PBS Datastore Fingerprints (Updated 2025-10-04)
- **pbs2:** `82:b6:40:b0:13:cd:c5:10:7a:b2:1f:9f:dd:de:7c:15:9e:c9:85:6c:b1:c9:08:21:f0:62:ea:d3:0d:23:14:ae` ✅
- **pbs3:** `fe:ab:d7:d8:c4:dd:46:3e:ac:b1:eb:e8:95:39:7b:60:81:a3:6a:bb:8e:9a:95:34:e5:ad:41:7a:fd:43:46:1f` ✅
- **pbs4:** `c2:71:f1:89:ba:63:2b:ab:6f:25:54:9a:05:a2:24:13:45:52:5b:82:85:9c:09:0e:31:e8:f8:9e:dd:10:5b:8a` ✅

## Key Lessons Learned

1. **INCEPTION-INCREMENT is NOT valid for SOA-EDIT-API** - Only valid for SOA-EDIT
2. **Per-zone metadata is REQUIRED** - No global default configuration exists
3. **SOA-EDIT-API = DEFAULT works perfectly** - YYYYMMDDXX format ideal for date tracking
4. **notify_slaves() function is critical** - dns_pdns.sh already has it, just needs working SOA increment
5. **Manual zone transfers were unnecessary** - The real fix was proper metadata configuration

## References
- PowerDNS Documentation: https://doc.powerdns.com/authoritative/dnsupdate.html
- RFC 1996: DNS NOTIFY - https://datatracker.ietf.org/doc/html/rfc1996
- RFC 2136: DNS UPDATE - https://datatracker.ietf.org/doc/html/rfc2136
- acme.sh PowerDNS plugin: https://github.com/acmesh-official/acme.sh/wiki/dnsapi#dns_pdns
