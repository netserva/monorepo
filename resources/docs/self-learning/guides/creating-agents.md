# Guide: Creating Effective Agents

**Purpose**: Learn how to create autonomous problem-solving agents that capture institutional knowledge

## What is an Agent?

An **agent** is an AI prompt that encapsulates domain expertise, decision-making patterns, and problem-solving approaches for a specific category of infrastructure tasks.

**Think of an agent as:**
- A domain expert consultant you can invoke anytime
- A structured approach to a class of problems
- A living document that improves over time
- A way to capture "how you think" about a problem domain

## When to Create an Agent

✅ **Create an agent when:**
- You solved a novel problem that may recur
- Task requires autonomous decision-making
- Multiple steps with contextual branching needed
- You want AI to handle edge cases automatically
- Problem domain has patterns worth capturing

❌ **Don't create an agent when:**
- Simple linear procedure (use runbook instead)
- One-off task with no reuse value
- Problem is fully documented elsewhere

## Agent Creation Workflow

### 1. Solve the Problem First

Don't create an agent before solving the problem! Work through the issue naturally with Claude Code first. You'll discover:
- What tools were most useful
- What decision points mattered
- What edge cases came up
- What worked and what didn't

### 2. Create Journal Entry

After solving the problem, use `/snapshot` to capture:
- Timeline of discovery and resolution
- Commands executed
- Decisions made and why
- Challenges encountered

### 3. Extract Agent from Journal

Use `/agent <category/name>` to create agent template, then populate from journal:
- **Role**: What kind of expert is this?
- **Context**: Domain knowledge needed
- **Decision Framework**: How to think through problems
- **Common Patterns**: Reusable solutions
- **Edge Cases**: Gotchas and how to handle them

### 4. Sanitize Sensitive Data

Replace all real infrastructure details with placeholders:
```
Real: sx ns1rn 'mysql -e "SELECT * FROM powerdns.domains"'
Agent: sx master_server 'mysql -e "SELECT * FROM database_name.domains"'

Real: 203.25.238.5
Agent: master_ip

Real: cooloola.net
Agent: domain.com
```

### 5. Test and Refine

On the next similar problem:
1. Use the agent via Task tool
2. Note what worked and what didn't
3. Update agent with improvements
4. Add new edge cases discovered

## Agent Structure Deep Dive

### Role and Context

**Bad (too vague):**
```markdown
## Role
You are a system administrator who fixes things.
```

**Good (specific domain expertise):**
```markdown
## Role
You are a PowerDNS zone replication specialist. Your goal is to diagnose and fix zone transfer issues between MASTER and SLAVE nameservers, ensuring SOA serial synchronization and proper AXFR configuration.

## Context
PowerDNS supports three zone types:
- NATIVE: No replication (legacy mode)
- MASTER: Source for zone transfers
- SLAVE: Receives updates via AXFR

Zone transfers require:
- Master: allow-axfr-ips and also-notify configuration
- Network: Port 53 TCP connectivity between servers
- Slave: Correct master IP in database
```

### Decision Framework

**Bad (no guidance):**
```markdown
## Decision Framework
Check if things are broken and fix them.
```

**Good (structured approach):**
```markdown
## Decision Framework

When diagnosing zone replication issues:
1. First check zone types (NATIVE zones can't replicate)
   - Query: `SELECT name, type FROM domains WHERE name='domain.com'`
   - If NATIVE: Must convert to MASTER first
2. Then verify master configuration
   - Check /etc/powerdns/pdns.conf for allow-axfr-ips
   - Verify also-notify includes slave IPs
3. Test network connectivity
   - Run: `nc -vz master_ip 53` from slave
   - If blocked: Add firewall rule
4. Check slave configuration
   - Verify master IP in slave database
   - Trigger manual AXFR: `pdnsutil retrieve-slave-zone`
5. Compare SOA serials
   - Master: `dig @localhost domain.com SOA +short`
   - Slave: `dig @localhost domain.com SOA +short`
   - If mismatched: Investigate replication failure
```

### Common Patterns

**Bad (no specifics):**
```markdown
### Pattern 1: Fix Replication
**When**: Replication is broken
**Action**: Fix it
```

**Good (actionable procedure):**
```markdown
### Pattern 1: NATIVE to MASTER Conversion
**When**: Zone type is NATIVE (check: `SELECT type FROM domains WHERE name='domain.com'`)
**Action**:
```bash
# Convert zone type
sx master_server 'mysql -e "UPDATE powerdns.domains SET type=\"MASTER\" WHERE name=\"domain.com\";"'

# Restart PowerDNS to apply
sx master_server 'sc reload powerdns'
```
**Verify**:
```bash
sx master_server 'mysql -e "SELECT name, type FROM powerdns.domains WHERE name=\"domain.com\";"'
# Should show: domain.com | MASTER
```
**Rollback**: Keep backup query ready:
```bash
sx master_server 'mysql -e "UPDATE powerdns.domains SET type=\"NATIVE\" WHERE name=\"domain.com\";"'
```
```

### Edge Cases

**Bad (generic):**
```markdown
### Case 1: Something fails
**Handle**: Try again or check logs
```

**Good (specific scenario):**
```markdown
### Case 1: AXFR Timeout
**Symptom**: `pdnsutil retrieve-slave-zone` hangs for 30+ seconds, then fails
**Cause**: Firewall blocking port 53 TCP between master and slave
**Handle**:
1. Test connectivity: `sx slave 'nc -vz master_ip 53'`
2. If blocked, add firewall rule on master:
   `sx master 'iptables -A INPUT -p tcp --dport 53 -s slave_ip -j ACCEPT'`
3. Verify: `sx slave 'nc -vz master_ip 53'` should show "open"
4. Retry: `sx slave 'pdnsutil retrieve-slave-zone domain.com master_ip'`
**Prevention**: Document firewall rules in infrastructure notes
```

## Best Practices

### DO:

1. **Be Specific**: "PowerDNS zone replication" not "DNS problems"
2. **Include Commands**: Show exact commands, not "configure the server"
3. **Explain Why**: Decision rationale, not just what to do
4. **Document Rollback**: Every action needs an undo plan
5. **Use Placeholders**: Never hardcode IPs, hostnames, domains
6. **Link Related Resources**: Reference runbooks, journals, docs
7. **Define Success**: Clear criteria for when agent has succeeded
8. **Update Regularly**: Add edge cases as you encounter them

### DON'T:

1. **Don't Be Generic**: "Check the logs" is useless without specifics
2. **Don't Skip Context**: Agent needs domain knowledge to make decisions
3. **Don't Hardcode Secrets**: Use placeholders for all sensitive data
4. **Don't Create Too Broad**: One domain per agent (not "fix everything")
5. **Don't Forget Safety**: Always include backup/rollback procedures
6. **Don't Write and Forget**: Agents should evolve with experience

## Examples

### Example 1: DNS Zone Migrator

**Scope**: Migrating PowerDNS zones between nameservers
**Key Patterns**: NATIVE→MASTER conversion, SLAVE configuration, AXFR verification
**Edge Cases**: Firewall blocks, SOA serial mismatches, cache poisoning
**Success**: Zone replicates correctly, SOA serials match
**See**: `examples/agent-dns-zone-migrator.md`

### Example 2: Mail Server DKIM Rotator

**Scope**: Rotating DKIM keys for email domains
**Key Patterns**: Generate keys, update OpenDKIM config, publish DNS records
**Edge Cases**: Key length mismatches, DNS propagation delays, signature failures
**Success**: Emails signed with new key, old key deprecated gracefully

### Example 3: SSL Certificate Manager

**Scope**: Renewing and deploying SSL certificates
**Key Patterns**: ACME challenge, certificate installation, service reload
**Edge Cases**: DNS-01 failures, rate limits, certificate chain issues
**Success**: Valid certificate installed, HTTPS working, no mixed content

## Agent vs Runbook Decision Tree

```
Is the problem novel or complex?
├─ Yes: Create Agent
│  └─ Agent discovers patterns
│     └─ Agent creates Runbook for repeatable parts
│
└─ No: Is it a well-defined procedure?
   ├─ Yes: Create Runbook (skip agent)
   └─ No: Document in Journal only
```

**Example Scenarios:**

| Task | Create Agent? | Create Runbook? | Why |
|------|---------------|-----------------|-----|
| Fix DNS replication | ✅ Yes | ✅ Yes (agent creates) | Complex diagnosis, repeatable fix |
| Rotate DKIM keys | ❌ No | ✅ Yes | Well-defined procedure, no branching |
| Debug performance issue | ✅ Yes | ❌ No | Highly contextual, hard to script |
| Restart web server | ❌ No | ✅ Yes | Simple procedure, no decisions |

## Organizing Agents

### Directory Structure

```
~/.ns/.claude/agents/
├── dns/
│   ├── zone-migrator.md
│   ├── replication-fixer.md
│   └── dnssec-manager.md
├── mail/
│   ├── dkim-rotator.md
│   ├── spam-filter-tuner.md
│   └── mailbox-migrator.md
├── security/
│   ├── ssl-cert-manager.md
│   ├── firewall-auditor.md
│   └── intrusion-responder.md
└── infrastructure/
    ├── vm-provisioner.md
    ├── backup-restorer.md
    └── disaster-recovery.md
```

### Naming Conventions

**Format**: `category/action-domain.md`

**Good names:**
- `dns/zone-migrator.md` (action + domain)
- `mail/dkim-rotator.md` (action + domain)
- `security/ssl-cert-manager.md` (action + domain)

**Bad names:**
- `fix-dns.md` (too vague)
- `agent1.md` (not descriptive)
- `the-thing-that-fixes-email.md` (too informal)

## Maintaining Agents

### After Each Use

1. **Did agent succeed?**
   - Yes: Note what worked well
   - No: Document what failed and why

2. **Were there edge cases?**
   - Add to Edge Cases section
   - Include symptoms, cause, fix

3. **Did you learn something?**
   - Update Context with new knowledge
   - Refine Decision Framework

4. **Was there a repeatable procedure?**
   - Create or update related runbook
   - Link in Related Resources section

### Monthly Review

- Archive agents not used in 6+ months
- Merge similar agents if overlap exists
- Extract common patterns into reusable components
- Update with new tool or platform changes

## Integration with Runbooks

Agents can **create runbooks** for repeatable procedures:

```markdown
## Post-Completion

After successfully solving the problem:
1. If procedure is repeatable, create runbook:
   - File: `~/.ns/.claude/runbooks/dns/migrate-zone.md`
   - Include: Exact commands, verification steps, rollback
2. Update agent with link to runbook:
   - **Runbook**: `~/.ns/.claude/runbooks/dns/migrate-zone.md`
```

**Workflow:**
1. Agent solves novel problem (exploration, decisions)
2. Agent identifies repeatable procedure
3. Agent creates runbook (distilled steps)
4. Next time: Use runbook for simple cases, agent for complex

## Common Mistakes

### Mistake 1: Too Broad

**Bad**: "System Administrator Agent" that does everything

**Good**: Separate agents for each domain:
- `dns/zone-migrator.md`
- `mail/dkim-rotator.md`
- `security/ssl-cert-manager.md`

### Mistake 2: Too Specific

**Bad**: "Fix cooloola.net DKIM on sca agent"

**Good**: "Mail DKIM configuration agent" that works for any domain

### Mistake 3: No Decision Logic

**Bad**: Agent that just lists commands

**Good**: Agent that explains when to use each command based on symptoms

### Mistake 4: Hardcoded Infrastructure

**Bad**:
```markdown
sx ns1rn 'mysql -e "UPDATE powerdns.domains..."'
```

**Good**:
```markdown
sx master_server 'mysql -e "UPDATE database_name.domains..."'
```

### Mistake 5: No Safety Checks

**Bad**: Agent makes changes immediately without verification

**Good**: Agent backs up, checks prerequisites, verifies after each step

## Next Steps

1. Review `examples/agent-dns-zone-migrator.md` for real-world example
2. Read `templates/agent-template.md` for copy-paste starting point
3. Create your first agent after solving a complex problem
4. Use `/agent <category/name>` command to generate template
5. Test agent on similar problems and refine

## Summary

**Good agents are:**
- Domain-specific (not general-purpose)
- Decision-aware (not just command lists)
- Safety-conscious (backup and rollback)
- Living documents (updated with experience)
- Sanitized (placeholders for sensitive data)

**Remember**: An agent captures **how to think** about a problem domain, not just **what commands to run**. For command sequences, use runbooks instead.
