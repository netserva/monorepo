# Runbook: Fix PowerDNS Zone Replication

**Created**: 2025-11-24
**Last Used**: 2025-11-24 (cooloola.net DKIM issue)
**Success Rate**: 1/1 (100%)
**Average Time**: 45 minutes
**Difficulty**: Moderate

## When to Use This Runbook

**Symptoms:**
- Zone transfers (AXFR) not working between nameservers
- Slave nameservers serving stale or outdated data
- SOA serial numbers differ across nameservers
- DNS records not updating on slave servers
- Email authentication failures due to old DKIM keys

**Root Cause:**
PowerDNS NATIVE zones do not support AXFR (zone transfers). Slave servers never receive updates from master, leading to data divergence.

**Scope:**
This runbook converts NATIVE zones to MASTER/SLAVE architecture and establishes proper zone replication.

## Prerequisites

**Required Access:**
- [ ] SSH access to: master nameserver, all slave nameservers
- [ ] Database access to: PowerDNS database (MySQL or SQLite)
- [ ] Permissions: Read/write on PowerDNS configuration files

**Required Information:**
- [ ] List of all nameservers (master and slaves)
- [ ] IP addresses of all nameservers
- [ ] Database type (MySQL or SQLite) on each server
- [ ] Domain names affected by replication issue

**Tools/Dependencies:**
- [ ] PowerDNS installed on all nameservers
- [ ] MySQL client or SQLite3 (depending on backend)
- [ ] `pdnsutil` command available
- [ ] Network connectivity between master and slaves (port 53 TCP)

## Safety Checks

**Before Starting:**
```bash
# Backup zone data from master
sx master_server 'pdnsutil list-zone domain.com > /tmp/domain.com.backup'

# Verify current zone types
sx master_server 'mysql -e "SELECT name, type FROM powerdns.domains LIMIT 10;"'

# Check current SOA serials on all servers
sx master_server 'dig @localhost domain.com SOA +short'
sx slave1_server 'dig @localhost domain.com SOA +short'
sx slave2_server 'dig @localhost domain.com SOA +short'
```

**Expected Output:**
```
master: domain.com. hostmaster.domain.com. 2025080205 10800 3600 604800 3600
slave1: domain.com. hostmaster.domain.com. 2025080201 10800 3600 604800 3600  (stale!)
slave2: domain.com. hostmaster.domain.com. 2025080201 10800 3600 604800 3600  (stale!)
```

## Procedure

### Step 1: Convert NATIVE → MASTER on Source

```bash
# For MySQL backend
sx master_server 'mysql -e "UPDATE powerdns.domains SET type = \"MASTER\" WHERE type = \"NATIVE\";"'

# Verify conversion
sx master_server 'mysql -e "SELECT COUNT(*) as master_zones FROM powerdns.domains WHERE type = \"MASTER\";"'
```

**✅ Checkpoint:** Confirm all NATIVE zones are now MASTER

**Expected Output:**
```
+--------------+
| master_zones |
+--------------+
|          237 |
+--------------+
```

**If this fails:**
- Check database permissions (needs UPDATE privilege)
- Verify syntax for your database backend
- Try converting one zone first as test

---

### Step 2: Configure Master for Zone Transfers

```bash
# Edit PowerDNS configuration on master
sx master_server 'cat >> /etc/powerdns/pdns.conf << EOF
allow-axfr-ips=slave1_ip,slave2_ip,slave3_ip
also-notify=slave1_ip,slave2_ip,slave3_ip
EOF'

# Restart PowerDNS to apply changes
sx master_server 'sc reload powerdns'
```

**✅ Checkpoint:** Verify PowerDNS restarted successfully

**Expected Output:**
```
Reloading powerdns... done
```

---

### Step 3: Get List of Zones from Master

```bash
# Get all zones that should be replicated
sx master_server 'mysql -e "SELECT name FROM powerdns.domains WHERE type = \"MASTER\" ORDER BY name;"' > /tmp/zones.txt

# Count zones
wc -l /tmp/zones.txt
```

**✅ Checkpoint:** Confirm zone count matches expectation

**Expected Output:**
```
237 /tmp/zones.txt
```

---

### Step 4: Configure Slaves (MySQL Backend)

```bash
# Remove any existing slave zones (clean start)
sx slave1_server 'mysql -e "DELETE FROM powerdns.domains WHERE type = \"SLAVE\";"'

# Add zones as SLAVE type
while read domain; do
  sx slave1_server "mysql -e \"INSERT INTO powerdns.domains (name, type, master) VALUES ('$domain', 'SLAVE', 'master_ip');\""
done < /tmp/zones.txt

# Verify slave zones created
sx slave1_server 'mysql -e "SELECT COUNT(*) FROM powerdns.domains WHERE type = \"SLAVE\";"'
```

**✅ Checkpoint:** Confirm slave zone count matches master zone count

**Expected Output:**
```
+----------+
| COUNT(*) |
+----------+
|      237 |
+----------+
```

---

### Step 5: Configure Slaves (SQLite Backend)

```bash
# For SQLite-based slaves
sx slave2_server 'sqlite3 /var/lib/powerdns/powerdns.sqlite3 "DELETE FROM domains WHERE type = \"SLAVE\";"'

# Add zones as SLAVE
while read domain; do
  sx slave2_server "sqlite3 /var/lib/powerdns/powerdns.sqlite3 \"INSERT INTO domains (name, type, master) VALUES ('$domain', 'SLAVE', 'master_ip');\""
done < /tmp/zones.txt

# Verify
sx slave2_server 'sqlite3 /var/lib/powerdns/powerdns.sqlite3 "SELECT COUNT(*) FROM domains WHERE type = \"SLAVE\";"'
```

**✅ Checkpoint:** SQLite slave has correct zone count

---

### Step 6: Trigger Zone Transfers

```bash
# Trigger AXFR for all zones on slave1
while read domain; do
  sx slave1_server "pdnsutil retrieve-slave-zone $domain master_ip"
done < /tmp/zones.txt

# Wait for transfers to complete (may take several minutes for many zones)
sleep 30

# Verify SOA serial matches master
sx master_server 'dig @localhost domain.com SOA +short | awk "{print \$3}"'
sx slave1_server 'dig @localhost domain.com SOA +short | awk "{print \$3}"'
```

**✅ Checkpoint:** SOA serials match between master and slave

**Expected Output:**
```
master: 2025080205
slave1: 2025080205  ✓ (now in sync!)
```

---

### Step 7: Remove Stale Zones from Old Nameservers (Critical!)

```bash
# Identify old/decommissioned nameservers
# Remove zones entirely to force DNS resolver cache refresh

sx old_ns1 'mysql -e "DELETE FROM pdns.domains WHERE name = \"domain.com\";"'
sx old_ns2 'mysql -e "DELETE FROM pdns.domains WHERE name = \"domain.com\";"'

# Verify removal (should return REFUSED or NXDOMAIN)
sx old_ns1 'dig @localhost domain.com SOA'
```

**✅ Checkpoint:** Old nameservers no longer serve the zone

**Expected Output:**
```
;; Got answer:
;; ->>HEADER<<- opcode: QUERY, status: REFUSED, id: 12345
```

**Why this is critical:** Stale zones on old nameservers can poison DNS resolver caches (8.8.8.8, 1.1.1.1). Removing them forces NXDOMAIN responses, causing resolvers to immediately retry at correct servers.

---

### Step 8: Verify from Public Resolvers

```bash
# Test resolution from Google DNS
dig @8.8.8.8 domain.com SOA +short

# Test resolution from Cloudflare DNS
dig @1.1.1.1 domain.com SOA +short

# Check DKIM key (if email-related)
dig @8.8.8.8 default._domainkey.domain.com TXT +short
```

**✅ Checkpoint:** Public resolvers return current data

**Expected Output:**
```
domain.com. hostmaster.domain.com. 2025080205 10800 3600 604800 3600  ✓ (latest serial!)
```

## Verification & Testing

**Final Checks:**
```bash
# Verify all slaves have correct zone count
sx slave1_server 'mysql -e "SELECT COUNT(*) FROM powerdns.domains WHERE type = \"SLAVE\";"'
sx slave2_server 'sqlite3 /var/lib/powerdns/powerdns.sqlite3 "SELECT COUNT(*) FROM domains WHERE type = \"SLAVE\";"'

# Check PowerDNS logs for errors
sx master_server 'tail -50 /var/log/pdns.log | grep -i error'
sx slave1_server 'tail -50 /var/log/pdns.log | grep -i error'

# Test zone resolution on each nameserver
for ns in master_server slave1_server slave2_server; do
  echo "=== $ns ==="
  sx $ns 'dig @localhost domain.com SOA +short'
done
```

**Success Criteria:**
- [ ] All NATIVE zones converted to MASTER
- [ ] All slave servers have matching zone count
- [ ] SOA serials match across all nameservers
- [ ] No errors in PowerDNS logs
- [ ] Public DNS resolvers serve current data
- [ ] Email authentication (DKIM/SPF) working if applicable

## Rollback Procedure

**If Step 1 fails (NATIVE → MASTER conversion):**
1. Revert with:
   ```bash
   sx master_server 'mysql -e "UPDATE powerdns.domains SET type = \"NATIVE\" WHERE type = \"MASTER\";"'
   ```
2. Verify rollback:
   ```bash
   sx master_server 'mysql -e "SELECT type, COUNT(*) FROM powerdns.domains GROUP BY type;"'
   ```

**If Step 4-5 fails (Slave configuration):**
1. Clear slave zones:
   ```bash
   sx slave_server 'mysql -e "DELETE FROM powerdns.domains WHERE type = \"SLAVE\";"'
   ```
2. Restore from backup if needed:
   ```bash
   sx slave_server 'mysql powerdns < /tmp/backup.sql'
   ```

**If Step 7 fails (Zone removal from old NS):**
1. Restore zone on old nameserver:
   ```bash
   sx old_ns 'mysql powerdns < /tmp/domain.com.backup'
   ```
2. This is low-risk: worst case is stale data continues being served

## Common Issues

### Issue 1: AXFR Timeout or Connection Refused

**Symptom**: Zone transfer hangs or fails with "connection refused"

**Cause**: Firewall blocking port 53 TCP between master and slaves

**Fix**:
```bash
# Check connectivity
sx slave_server 'nc -vz master_ip 53'

# Add firewall rule on master
sx master_server 'iptables -A INPUT -p tcp --dport 53 -s slave_ip -j ACCEPT'

# Retry zone transfer
sx slave_server 'pdnsutil retrieve-slave-zone domain.com master_ip'
```

**Prevention**: Document firewall rules in infrastructure notes

---

### Issue 2: "Unknown zone" Error on Slave

**Symptom**: `pdnsutil retrieve-slave-zone` returns "Unknown zone"

**Cause**: Zone not yet created in slave database

**Fix**:
```bash
# Verify zone exists in slave database
sx slave_server 'mysql -e "SELECT name, type FROM powerdns.domains WHERE name = \"domain.com\";"'

# If missing, add it
sx slave_server 'mysql -e "INSERT INTO powerdns.domains (name, type, master) VALUES (\"domain.com\", \"SLAVE\", \"master_ip\");"'

# Retry transfer
sx slave_server 'pdnsutil retrieve-slave-zone domain.com master_ip'
```

**Prevention**: Always verify slave zone creation before triggering AXFR

---

### Issue 3: SOA Serial Still Mismatched After Transfer

**Symptom**: Slave SOA serial lower than master despite successful AXFR

**Cause**: PowerDNS cache not refreshed, or zone not notified

**Fix**:
```bash
# Force PowerDNS to reload zone
sx slave_server 'pdnsutil rectify-zone domain.com'

# Or restart PowerDNS entirely
sx slave_server 'sc reload powerdns'

# Check again
sx slave_server 'dig @localhost domain.com SOA +short'
```

**Prevention**: Always verify SOA serials after zone transfers

---

### Issue 4: Public Resolvers Still Returning Stale Data

**Symptom**: 8.8.8.8 or 1.1.1.1 serve outdated records even after fix

**Cause**: DNS resolver cache poisoning from old nameservers

**Fix**:
```bash
# Remove zone entirely from old nameservers (forces NXDOMAIN)
sx old_ns 'mysql -e "DELETE FROM powerdns.domains WHERE name = \"domain.com\";"'

# Wait 5-10 minutes for resolver cache refresh
sleep 600

# Verify from public resolver
dig @8.8.8.8 domain.com SOA +short
```

**Prevention**: Always remove stale zones from decommissioned nameservers

## Post-Completion

**Cleanup:**
- [ ] Remove temporary backup files: `rm /tmp/domain.com.backup /tmp/zones.txt`
- [ ] Clear any test zones created during troubleshooting

**Documentation:**
- [ ] Update this runbook with **Last Used** date
- [ ] Increment **Success Rate** (now 2/2)
- [ ] Add any new issues encountered to Common Issues section
- [ ] Create journal entry with `/snapshot`

**Notifications:**
- [ ] Notify team that zone replication is now working
- [ ] Update DNS documentation with new nameserver IPs
- [ ] Document any firewall rules added

## Related Documentation

- **Agent**: `.claude/agents/dns/zone-migrator.md`
- **Journal**: `.claude/journal/2025-11-24_dns-zone-replication-dkim-fix.md`
- **Technical Docs**: `resources/docs/powerdns-configuration.md`

## Variables Used

**This runbook uses placeholders for sensitive data:**
- `master_server` = actual master nameserver hostname
- `slave1_server` = actual slave 1 hostname
- `slave2_server` = actual slave 2 hostname (SQLite backend)
- `old_ns1` / `old_ns2` = decommissioned nameserver hostnames
- `master_ip` = actual IP address of master server
- `slave_ip` = actual IP address of slave server
- `domain.com` = actual domain name being fixed
- `powerdns` = actual database name (may be `pdns` or `sysadm`)
- `/var/lib/powerdns/powerdns.sqlite3` = actual SQLite database path

**Always substitute real values when executing!**

## Execution Time

- **Simple case** (single domain, no issues): 15 minutes
- **Complex case** (multiple domains, firewall issues): 45 minutes
- **Emergency situation** (with rollback): 60 minutes

## Notes

**Critical Lesson Learned:** Stale zones on old nameservers can interfere with DNS resolution even after registry delegation is updated. Always remove zones entirely from decommissioned servers to force NXDOMAIN responses, which trigger immediate cache refresh at public resolvers like Google DNS (8.8.8.8) and Cloudflare DNS (1.1.1.1).

**PowerDNS Zone Types:**
- **NATIVE**: No replication, standalone zone (legacy mode)
- **MASTER**: Source for zone transfers, sends NOTIFY to slaves
- **SLAVE**: Receives updates via AXFR from master

**Zone Transfer Mechanics:**
1. Master increments SOA serial
2. Master sends NOTIFY to slaves (via `also-notify`)
3. Slaves request AXFR from master (via `allow-axfr-ips`)
4. Master transfers entire zone to slave
5. Slave updates local database with new records

---

**Remember**: Update this runbook after each use! Add new issues, refine steps, update timing estimates.
