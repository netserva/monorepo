# Guide: Writing Effective Runbooks

**Purpose**: Learn how to create clear, executable, step-by-step procedures that anyone can follow

## What is a Runbook?

A **runbook** is a prescriptive, step-by-step procedure for completing a specific infrastructure task. Unlike agents (which make autonomous decisions), runbooks are **checklists with exact commands** designed for mechanical execution.

**Think of a runbook as:**
- A recipe with precise measurements and steps
- Emergency procedures you can follow under pressure
- Training material for junior team members
- Institutional knowledge in executable form

## When to Create a Runbook

✅ **Create a runbook when:**
- Task is well-defined and repeatable
- Exact command sequence matters
- Training humans (or yourself later)
- Quick reference needed
- Simple shell automation desired
- Emergency procedures needed

❌ **Don't create a runbook when:**
- Problem is novel or complex (use agent instead)
- Current state unknown (need exploration first)
- Multiple valid approaches exist
- Requires contextual decisions at each step

## Runbook Creation Workflow

### 1. Execute the Procedure

Don't write a runbook from imagination! Actually perform the task while documenting:
- Every command you run
- What output you see
- What you verify at each step
- Where you could have made mistakes
- How long each step takes

### 2. Capture Commands

As you work, save commands to a file:
```bash
# Keep a log file open during work
echo "sx master 'mysql -e \"SELECT COUNT(*) FROM domains;\"'" >> /tmp/commands.log
```

Or use `/runbook <category/name>` after session to extract from history.

### 3. Add Verification Checkpoints

After each command, add:
- ✅ **Checkpoint**: What to verify before proceeding
- **Expected Output**: What success looks like
- **If this fails**: Troubleshooting steps

### 4. Test the Runbook

Give it to someone else (or use it yourself later):
- Can they follow it without asking questions?
- Are commands copy/pasteable?
- Do verification steps catch errors?
- Is rollback procedure clear?

### 5. Refine and Maintain

After each use:
- Update **Last Used** date
- Increment **Success Rate**
- Add newly discovered edge cases
- Improve unclear steps

## Runbook Structure Deep Dive

### Title and Metadata

**Good metadata example:**
```markdown
# Runbook: Fix PowerDNS Zone Replication

**Created**: 2025-11-24
**Last Used**: 2025-11-24 (cooloola.net DKIM issue)
**Success Rate**: 4/5 (80%)
**Average Time**: 35 minutes
**Difficulty**: Moderate
```

**Why this matters:**
- **Created**: Shows age/maturity of runbook
- **Last Used**: Indicates if it's current or stale
- **Success Rate**: Builds confidence (or warns of issues)
- **Average Time**: Sets expectations
- **Difficulty**: Helps choose right person for task

### When to Use This Runbook

**Bad (too vague):**
```markdown
## When to Use
Use this when DNS is broken.
```

**Good (specific symptoms):**
```markdown
## When to Use This Runbook

**Symptoms:**
- Zone transfers (AXFR) not working between nameservers
- Slave nameservers serving stale or outdated data
- SOA serial numbers differ across nameservers
- DNS records not updating on slave servers

**Root Cause:**
PowerDNS NATIVE zones do not support AXFR. Slaves never receive updates.

**Scope:**
This runbook converts NATIVE zones to MASTER/SLAVE and establishes replication.
```

### Prerequisites Checklist

**Bad (assumed):**
```markdown
## Prerequisites
You need access to the servers.
```

**Good (explicit checklist):**
```markdown
## Prerequisites

**Required Access:**
- [ ] SSH access to: master nameserver, slave1, slave2
- [ ] Database access to: powerdns database (MySQL)
- [ ] Permissions: Read/write on /etc/powerdns/pdns.conf

**Required Information:**
- [ ] IP addresses of all nameservers
- [ ] Domain names affected
- [ ] Current SOA serial numbers

**Tools/Dependencies:**
- [ ] PowerDNS 4.x installed
- [ ] MySQL client version 8.x
- [ ] Network connectivity on port 53 TCP
```

### Safety Checks

**Bad (no backup):**
```markdown
## Safety Checks
Make sure you know what you're doing.
```

**Good (explicit backup commands):**
```markdown
## Safety Checks

**Before Starting:**
```bash
# Backup zone data
sx master 'pdnsutil list-zone domain.com > /tmp/domain.com.backup'

# Verify current state
sx master 'mysql -e "SELECT name, type, COUNT(*) FROM powerdns.domains GROUP BY type;"'

# Check SOA serials
for server in master slave1 slave2; do
  echo "=== $server ==="
  sx $server 'dig @localhost domain.com SOA +short'
done
```

**Expected Output:**
```
=== master ===
domain.com. hostmaster.domain.com. 2025080205 ...
=== slave1 ===
domain.com. hostmaster.domain.com. 2025080201 ...  (stale!)
```
```

### Procedure Steps

**Bad step (no verification):**
```markdown
### Step 1: Update database
```bash
sx master 'mysql -e "UPDATE domains SET type=\"MASTER\";"'
```
```

**Good step (complete with checkpoint):**
```markdown
### Step 1: Convert NATIVE → MASTER

```bash
sx master 'mysql -e "UPDATE powerdns.domains SET type = \"MASTER\" WHERE type = \"NATIVE\";"'
```

**✅ Checkpoint:** Verify conversion succeeded

**How to verify:**
```bash
sx master 'mysql -e "SELECT COUNT(*) as master_zones FROM powerdns.domains WHERE type = \"MASTER\";"'
```

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
- Verify database connection (try simple SELECT first)
- Check syntax for your specific database backend
- Try converting a single zone first as test:
  `sx master 'mysql -e "UPDATE powerdns.domains SET type=\"MASTER\" WHERE name=\"test.com\";"'`
```

### Verification & Testing

**Bad (no final checks):**
```markdown
## Verification
Check if it works.
```

**Good (comprehensive testing):**
```markdown
## Verification & Testing

**Final Checks:**
```bash
# 1. Verify zone count matches on all servers
sx master 'mysql -e "SELECT COUNT(*) FROM powerdns.domains WHERE type=\"MASTER\";"'
sx slave1 'mysql -e "SELECT COUNT(*) FROM powerdns.domains WHERE type=\"SLAVE\";"'

# 2. Verify SOA serials match
for server in master slave1 slave2; do
  echo "=== $server ==="
  sx $server 'dig @localhost domain.com SOA +short | awk "{print \$3}"'
done

# 3. Check PowerDNS logs for errors
sx master 'tail -50 /var/log/pdns.log | grep -i error'

# 4. Test from public DNS
dig @8.8.8.8 domain.com SOA +short
```

**Success Criteria:**
- [ ] All NATIVE zones converted to MASTER (count matches)
- [ ] All slave servers have matching zone count
- [ ] SOA serials identical across all nameservers
- [ ] No errors in PowerDNS logs
- [ ] Public DNS resolvers serve current data (not cached stale)
- [ ] Email authentication (DKIM/SPF) working if applicable
```

### Rollback Procedure

**Bad (no undo plan):**
```markdown
## Rollback
If it breaks, good luck!
```

**Good (step-by-step reversal):**
```markdown
## Rollback Procedure

**If Step 1 fails (NATIVE → MASTER conversion):**
1. Revert conversion:
   ```bash
   sx master 'mysql -e "UPDATE powerdns.domains SET type = \"NATIVE\" WHERE type = \"MASTER\" AND name = \"domain.com\";"'
   ```
2. Verify rollback:
   ```bash
   sx master 'mysql -e "SELECT name, type FROM powerdns.domains WHERE name = \"domain.com\";"'
   # Should show: domain.com | NATIVE
   ```
3. No service restart needed (NATIVE zones don't replicate)

**If Step 3 fails (Slave configuration):**
1. Remove slave zones:
   ```bash
   sx slave1 'mysql -e "DELETE FROM powerdns.domains WHERE type = \"SLAVE\" AND name = \"domain.com\";"'
   ```
2. Verify removal:
   ```bash
   sx slave1 'mysql -e "SELECT name FROM powerdns.domains WHERE name = \"domain.com\";"'
   # Should return empty set
   ```
3. Master remains unaffected (safe to retry later)

**If Step 5 fails (Zone transfer):**
1. Zone transfer failure is non-destructive (read-only operation)
2. Check network connectivity: `sx slave1 'nc -vz master_ip 53'`
3. Check master config: `sx master 'grep allow-axfr-ips /etc/powerdns/pdns.conf'`
4. Retry manually: `sx slave1 'pdnsutil retrieve-slave-zone domain.com master_ip'`

**Emergency Full Rollback:**
```bash
# Restore from backup if needed
sx master 'mysql powerdns < /tmp/domain.com.backup'

# Restart PowerDNS
sx master 'sc reload powerdns'

# Verify restoration
sx master 'pdnsutil list-zone domain.com | head -10'
```
```

### Common Issues

**Bad (generic troubleshooting):**
```markdown
### Issue: It doesn't work
**Fix**: Check the logs and try again
```

**Good (specific scenario with solution):**
```markdown
### Issue 1: AXFR Timeout or Connection Refused

**Symptom**: `pdnsutil retrieve-slave-zone` hangs for 30+ seconds, then returns:
```
Error: Unable to retrieve zone from master
```

**Cause**: Firewall blocking port 53 TCP between master and slave

**Diagnosis**:
```bash
# Test connectivity from slave to master
sx slave1 'nc -vz master_ip 53'
# If blocked, you'll see: Connection timed out
```

**Fix**:
```bash
# Add firewall rule on master to allow slave
sx master 'iptables -A INPUT -p tcp --dport 53 -s slave_ip -j ACCEPT'

# Verify rule was added
sx master 'iptables -L INPUT -n | grep 53'

# Test connectivity again
sx slave1 'nc -vz master_ip 53'
# Should show: Connection to master_ip 53 port [tcp/*] succeeded!

# Retry zone transfer
sx slave1 'pdnsutil retrieve-slave-zone domain.com master_ip'
```

**Prevention**: Document firewall rules in infrastructure repository

**Time Lost**: 10-15 minutes (if not documented)
```

## Best Practices

### Command Formatting

**Bad:**
```markdown
Run this command on the server:
UPDATE domains SET type='MASTER' WHERE type='NATIVE';
```

**Good:**
```markdown
```bash
sx master 'mysql -e "UPDATE powerdns.domains SET type = \"MASTER\" WHERE type = \"NATIVE\";"'
```
```

**Why:**
- Syntax highlighted code blocks
- Full context (which server, which database)
- Copy/pasteable with proper escaping
- Shows exactly how command was run (via `sx`)

### Expected Output

**Bad:**
```markdown
The command will return some results.
```

**Good:**
```markdown
**Expected Output:**
```
+--------------+
| master_zones |
+--------------+
|          237 |
+--------------+
```

**Why:**
- Shows exactly what success looks like
- Helps identify when something goes wrong
- Builds confidence that you're on the right track

### Variable Placeholders

**Bad (hardcoded):**
```bash
sx ns1rn 'mysql -usysadm -pSecretPass123 sysadm -e "SELECT * FROM domains;"'
```

**Good (placeholders):**
```bash
sx master_server 'mysql -e "SELECT * FROM database_name.domains;"'
```

**Variables to Document:**
```markdown
## Variables Used

**This runbook uses placeholders:**
- `master_server` = your master nameserver hostname
- `slave_server` = your slave nameserver hostname
- `database_name` = your PowerDNS database name (often `powerdns` or `pdns`)
- `domain.com` = the actual domain you're working with
- `master_ip` = IP address of master server
- `slave_ip` = IP address of slave server

**Always substitute real values when executing!**
```

### Time Estimates

**Good time breakdown:**
```markdown
## Execution Time

- **Simple case** (single domain, no issues): 15 minutes
- **Complex case** (multiple domains, firewall issues): 45 minutes
- **Emergency situation** (rollback required): 60 minutes

**Time by step:**
1. Prerequisites and safety checks: 5 min
2. Database updates: 3 min
3. Configuration changes: 5 min
4. Zone transfers: 10-20 min (depends on zone count/size)
5. Verification: 5 min
6. Documentation: 2 min
```

## Runbook Maintenance

### After Each Use

**Update the following:**
1. **Last Used** date and context
2. **Success Rate** (increment successes or failures)
3. **Average Time** (refine estimate based on actual)
4. **Common Issues** (add new problems encountered)
5. **Unclear steps** (clarify anything confusing)

**Example update:**
```markdown
**Last Used**: 2025-11-25 (domain.com migration - SUCCESS)
**Success Rate**: 5/5 (100%)  ← was 4/5
**Average Time**: 32 minutes  ← was 35 minutes
```

### Monthly Review

- Archive runbooks not used in 6+ months (or mark as deprecated)
- Update commands if tools/versions changed
- Merge similar runbooks if overlap exists
- Extract common snippets to reusable library
- Check for outdated screenshots or output examples

### Deprecation

When a runbook becomes obsolete:
```markdown
# ⚠️ DEPRECATED: Fix PowerDNS Zone Replication

**Deprecated**: 2025-12-01
**Reason**: PowerDNS 5.0 handles this automatically
**Replacement**: New automation in PowerDNS 5.x SuperMaster feature

[Keep original content for historical reference]
```

## Organizing Runbooks

### Directory Structure

```
~/.ns/.claude/runbooks/
├── dns/
│   ├── fix-powerdns-zone-replication.md
│   ├── migrate-zone-to-new-nameserver.md
│   ├── rotate-dkim-keys.md
│   └── emergency-dns-rollback.md
├── mail/
│   ├── configure-opendkim.md
│   ├── fix-postfix-authentication.md
│   └── restore-mailbox-from-backup.md
├── security/
│   ├── rotate-ssl-certificates.md
│   ├── audit-user-access.md
│   └── patch-critical-vulnerability.md
└── emergency/
    ├── restore-from-backup.md
    ├── failover-to-secondary.md
    └── rollback-deployment.md
```

### Naming Conventions

**Format**: `action-domain.md`

**Good names:**
- `fix-powerdns-zone-replication.md` (action + specific issue)
- `rotate-ssl-certificates.md` (action + what)
- `migrate-zone-to-new-nameserver.md` (action + destination)

**Bad names:**
- `dns-runbook-1.md` (not descriptive)
- `the-thing-we-do-for-email.md` (too informal)
- `runbook.md` (generic)

## Integration with Agents

Agents can create runbooks:

**Agent discovers repeatable procedure:**
```markdown
## Post-Completion

After solving this problem, I've identified a repeatable procedure.
Creating runbook: `~/.ns/.claude/runbooks/dns/fix-zone-replication.md`

This runbook can be used next time for the mechanical steps, while I remain
available for novel problems or edge cases that require autonomous decision-making.
```

**Runbook references agent:**
```markdown
## Related Documentation

- **Agent**: `.claude/agents/dns/zone-migrator.md` (for complex migrations)
- **Journal**: `.claude/journal/2025-11-24_dns-fix.md` (original session)
```

**Decision tree:**
```
DNS replication problem detected
├─ Simple case (known scenario) → Use Runbook (15 min)
└─ Complex case (novel/unknown) → Use Agent (explores, then updates runbook)
```

## Common Mistakes

### Mistake 1: No Verification Steps

**Bad:**
```markdown
### Step 1: Update database
```bash
sx master 'mysql -e "UPDATE domains SET type=\"MASTER\";"'
```

### Step 2: Configure slaves
...
```

**Good:**
```markdown
### Step 1: Update database
```bash
sx master 'mysql -e "UPDATE powerdns.domains SET type=\"MASTER\" WHERE type=\"NATIVE\";"'
```

**✅ Checkpoint:** Verify all NATIVE zones are now MASTER
```bash
sx master 'mysql -e "SELECT type, COUNT(*) FROM powerdns.domains GROUP BY type;"'
```

**Expected:** Should show MASTER count increased, NATIVE count = 0

### Step 2: Configure slaves
...
```

### Mistake 2: Assumed Prerequisites

**Bad:**
```markdown
## Prerequisites
Make sure you have access to the servers.
```

**Good:**
```markdown
## Prerequisites

**Verify before starting:**
```bash
# Test SSH access
sx master 'hostname'  # Should return: master.example.com
sx slave1 'hostname'  # Should return: slave1.example.com

# Test database access
sx master 'mysql -e "SELECT VERSION();"'  # Should return MySQL version

# Test PowerDNS
sx master 'pdnsutil version'  # Should return PowerDNS version
```

**If any fail, stop and resolve before proceeding.**
```

### Mistake 3: No Rollback Plan

**Bad:**
```markdown
## Rollback Procedure
Restore from backup if needed.
```

**Good:**
```markdown
## Rollback Procedure

**If Step 1 fails:**
1. Revert with: `sx master 'mysql -e "UPDATE powerdns.domains SET type = \"NATIVE\"..."'`
2. Verify: `sx master 'mysql -e "SELECT type FROM powerdns.domains LIMIT 5;"'`
3. Document failure in Common Issues section

**If Step 2 fails:**
[Specific rollback for step 2]

**Emergency full restoration:**
```bash
sx master 'mysql powerdns < /backups/powerdns-$(date +%Y%m%d).sql'
```
```

### Mistake 4: Vague Troubleshooting

**Bad:**
```markdown
### Issue: Command fails
**Fix**: Check logs and try again
```

**Good:**
```markdown
### Issue 1: MySQL Access Denied

**Symptom**: Command returns:
```
ERROR 1045 (28000): Access denied for user 'root'@'localhost'
```

**Cause**: Insufficient database permissions

**Diagnosis**:
```bash
sx master 'mysql -e "SHOW GRANTS FOR CURRENT_USER();"'
```

**Fix**:
```bash
# If using sx, credentials handled automatically
sx master 'mysql -e "SELECT COUNT(*) FROM powerdns.domains;"'

# Otherwise, grant permissions
sx master 'mysql -uroot -p -e "GRANT ALL ON powerdns.* TO sysadm@localhost;"'
```
```

### Mistake 5: Hardcoded Infrastructure

**Bad:**
```bash
sx ns1rn 'mysql -e "UPDATE powerdns.domains SET type=\"MASTER\";"'
```

**Good:**
```bash
sx master_server 'mysql -e "UPDATE database_name.domains SET type=\"MASTER\";"'
```

With documentation:
```markdown
**Variables:**
- `master_server`: Your master nameserver hostname
- `database_name`: Your PowerDNS database name
```

## Testing Your Runbook

### Self-Test Checklist

- [ ] Can I copy/paste commands directly?
- [ ] Do I know what success looks like at each step?
- [ ] Is there a verification checkpoint after each step?
- [ ] Can I rollback if something fails?
- [ ] Are all variables clearly documented?
- [ ] Would someone else understand this?
- [ ] Did I test it on a non-production system?

### Peer Review

Give runbook to someone who wasn't involved:
- Can they understand what it does?
- Can they follow it without questions?
- Do they know when to stop if something's wrong?
- Can they estimate how long it will take?

### Production Test

On first production use:
- Note actual time vs estimated time
- Document any surprises or gotchas
- Add missing verification steps
- Refine unclear instructions
- Update success rate

## Summary

**Good runbooks are:**
- **Specific**: Exact commands, not general guidance
- **Verifiable**: Checkpoint after each step
- **Reversible**: Clear rollback procedure
- **Maintainable**: Updated after each use
- **Sanitized**: Placeholders for sensitive data

**Remember**: A runbook is a recipe. Someone following it mechanically should get the expected result every time. If it requires decision-making or exploration, you need an agent instead.
