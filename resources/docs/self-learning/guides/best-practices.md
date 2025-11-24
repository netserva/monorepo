# Best Practices: Self-Learning Knowledge System

**Purpose**: Guidelines for maximizing the value of your institutional knowledge capture

## Core Principles

### 1. Never Solve the Same Problem Twice

**Goal**: Every problem solution should either:
- Update existing knowledge artifacts, OR
- Create new artifacts for future reference

**Bad workflow:**
```
Problem → Solve → Forget → Repeat next time
```

**Good workflow:**
```
Problem → Solve → Document (agent/runbook/journal) → Reference next time
```

### 2. Compound Your Knowledge

**Principle**: Each session builds on previous sessions

**How:**
- New agents reference existing runbooks
- New runbooks extract from agent experiences
- Journals link to related sessions
- Each artifact improves over time

**Example progression:**
```
Session 1: Novel DNS problem
  → Creates: Agent (dns/zone-migrator)

Session 2: Use agent, discover pattern
  → Updates: Agent with new edge case
  → Creates: Runbook (fix-zone-replication)

Session 3: Use runbook, find optimization
  → Updates: Runbook with faster approach
  → Journal documents the improvement

Session 4: Similar problem, different context
  → Uses: Runbook (15 min vs 2 hours first time)
  → Success documented in runbook metadata
```

### 3. Always Be Sanitizing

**Rule**: NEVER commit real infrastructure details

**What to sanitize:**
- Server IPs: `203.25.238.5` → `master_ip`
- Hostnames: `ns1rn.renta.net` → `master_server`
- Domains: `cooloola.net` → `domain.com`
- Databases: `powerdns` → `database_name`
- Credentials: NEVER INCLUDE (use variables)
- API keys: NEVER INCLUDE (use env vars)

**Where to sanitize:**
- Agents (contain infrastructure context)
- Runbooks (contain exact commands)
- Journals (contain session details)

**What stays private:**
- Everything in `.claude/agents/`
- Everything in `.claude/runbooks/`
- Everything in `.claude/journal/`

**What's public:**
- Everything in `resources/docs/self-learning/`
- Templates and examples (already sanitized)
- Guides and methodology

## The Knowledge Triad: When to Use Each

### Decision Matrix

| Scenario | Agent | Runbook | Journal | Why |
|----------|-------|---------|---------|-----|
| Novel problem, unknown approach | ✅ | ❌ | ✅ | Need exploration + record |
| Solved problem, repeatable steps | ❌ | ✅ | ✅ | Known procedure + audit trail |
| Complex problem, multiple paths | ✅ | ❌ | ✅ | Need decisions + context |
| Simple procedure, one path | ❌ | ✅ | Maybe | Just need steps |
| Troubleshooting session | ✅ | ❌ | ✅ | Exploration required |
| Routine maintenance | ❌ | ✅ | Maybe | Follow checklist |
| Emergency incident | Both | Both | ✅ | Use runbook, agent assists |

### The Learning Cycle

```
┌─────────────────────────────────────────────────────────┐
│ 1. NOVEL PROBLEM ARISES                                 │
│    "DNS replication broken, never seen this before"     │
└────────────────┬────────────────────────────────────────┘
                 ↓
┌─────────────────────────────────────────────────────────┐
│ 2. USE AGENT (autonomous exploration)                   │
│    Agent: dns/zone-migrator                             │
│    - Diagnoses issue                                     │
│    - Tries solutions                                     │
│    - Handles edge cases                                  │
└────────────────┬────────────────────────────────────────┘
                 ↓
┌─────────────────────────────────────────────────────────┐
│ 3. AGENT SOLVES PROBLEM                                 │
│    Root cause: NATIVE zones don't support AXFR          │
│    Solution: Convert to MASTER/SLAVE                     │
└────────────────┬────────────────────────────────────────┘
                 ↓
┌─────────────────────────────────────────────────────────┐
│ 4. AGENT CREATES/UPDATES RUNBOOK                        │
│    Runbook: dns/fix-powerdns-zone-replication.md       │
│    - Distills procedure from agent experience           │
│    - Exact commands with verification                    │
│    - Rollback steps                                      │
└────────────────┬────────────────────────────────────────┘
                 ↓
┌─────────────────────────────────────────────────────────┐
│ 5. USE /snapshot → JOURNAL                              │
│    Journal: 2025-11-24_dns-zone-replication-fix.md     │
│    - Historical narrative                                │
│    - Decisions made                                      │
│    - Links to agent + runbook                           │
└────────────────┬────────────────────────────────────────┘
                 ↓
┌─────────────────────────────────────────────────────────┐
│ NEXT SIMILAR PROBLEM                                    │
│ ├─ Simple case? → Follow runbook (15 min)              │
│ └─ Complex case? → Use agent (references runbook)      │
└─────────────────────────────────────────────────────────┘
```

## Artifact Quality Standards

### Agent Quality Checklist

✅ **Good agent has:**
- [ ] Clear role and domain expertise
- [ ] Structured decision framework (not just commands)
- [ ] Specific patterns with verification steps
- [ ] Edge cases with handling procedures
- [ ] Safety checks and rollback plans
- [ ] Links to related runbooks and journals
- [ ] All sensitive data sanitized
- [ ] Success criteria clearly defined

❌ **Poor agent has:**
- [ ] Generic "fix everything" scope
- [ ] Just lists commands without context
- [ ] No decision logic or branching
- [ ] Hardcoded IPs or hostnames
- [ ] No rollback procedures
- [ ] No verification steps

**Example comparison:**

**Bad agent:**
```markdown
# Agent: DNS Fixer

## Role
You fix DNS problems.

## What to Do
Run these commands:
sx ns1rn 'mysql -e "UPDATE domains SET type=\"MASTER\";"'
```

**Good agent:**
```markdown
# Agent: PowerDNS Zone Replication Specialist

## Role
You diagnose and fix zone transfer issues between MASTER and SLAVE nameservers.

## Decision Framework
When encountering replication failure:
1. Check zone type (NATIVE can't replicate)
2. If NATIVE, convert to MASTER
3. Verify master configuration (allow-axfr-ips)
4. Test network (port 53 TCP)
5. Configure slaves with master IP
6. Trigger AXFR and verify SOA serials match

## Pattern 1: NATIVE to MASTER Conversion
**When**: Zone type is NATIVE
**Action**: sx master_server 'mysql -e "UPDATE database.domains..."'
**Verify**: Check type changed
**Rollback**: Revert to NATIVE if needed
```

### Runbook Quality Checklist

✅ **Good runbook has:**
- [ ] Metadata (created, last used, success rate)
- [ ] Clear symptoms for when to use it
- [ ] Explicit prerequisites checklist
- [ ] Safety checks and backups
- [ ] Numbered steps with exact commands
- [ ] Verification checkpoint after each step
- [ ] Expected output shown
- [ ] Rollback procedure for each step
- [ ] Common issues with solutions
- [ ] Variables documented
- [ ] Time estimates

❌ **Poor runbook has:**
- [ ] Vague "when to use" section
- [ ] Assumed prerequisites
- [ ] Commands without context (which server?)
- [ ] No verification steps
- [ ] No expected output
- [ ] No rollback plan
- [ ] Generic troubleshooting

**Example comparison:**

**Bad runbook:**
```markdown
# Runbook: Fix DNS

## Steps
1. Update database
2. Restart service
3. Check if it works
```

**Good runbook:**
```markdown
# Runbook: Fix PowerDNS Zone Replication

**Created**: 2025-11-24
**Success Rate**: 4/5 (80%)
**Average Time**: 35 minutes

## When to Use
**Symptoms:** Zone transfers failing, SOA serials mismatched
**Root Cause:** NATIVE zones don't support AXFR

## Step 1: Convert NATIVE → MASTER
```bash
sx master_server 'mysql -e "UPDATE powerdns.domains SET type=\"MASTER\" WHERE type=\"NATIVE\";"'
```

**✅ Checkpoint:** Verify conversion
```bash
sx master_server 'mysql -e "SELECT COUNT(*) FROM powerdns.domains WHERE type=\"MASTER\";"'
```

**Expected Output:**
```
COUNT(*) = 237
```

**If this fails:** Check database permissions...
```

### Journal Quality Checklist

✅ **Good journal has:**
- [ ] Executive summary (2-3 sentences)
- [ ] Timeline with timestamps
- [ ] Root cause analysis
- [ ] Decisions made with rationale
- [ ] Key insights and lessons
- [ ] Links to artifacts created
- [ ] Next steps clearly defined
- [ ] All sensitive data sanitized

❌ **Poor journal has:**
- [ ] Just a command dump
- [ ] No context or reasoning
- [ ] No decisions documented
- [ ] No lessons learned
- [ ] Hardcoded infrastructure details

## Maintenance Rhythms

### After Each Session

**Immediate (before ending session):**
1. Use `/snapshot` to create journal
2. Update relevant runbooks with:
   - Last Used date
   - Success Rate
   - New issues discovered
3. Update relevant agents with:
   - New edge cases
   - Refined patterns

**Within 24 hours:**
1. Review journal for completeness
2. Extract reusable patterns
3. Create new runbooks if needed
4. Link related artifacts

### Weekly Review (15 minutes)

**Focus**: Recent work and patterns

- [ ] Review week's journals
- [ ] Identify recurring problems (candidates for runbooks)
- [ ] Update agent/runbook success rates
- [ ] Note knowledge gaps

### Monthly Review (1 hour)

**Focus**: System health and optimization

- [ ] Archive agents/runbooks not used in 6+ months
- [ ] Merge similar/overlapping agents
- [ ] Extract common patterns into reusable components
- [ ] Update for tool/platform changes
- [ ] Review success rates (flag failures for improvement)

### Quarterly Review (2 hours)

**Focus**: Strategic knowledge management

- [ ] Identify knowledge gaps (problems without agents/runbooks)
- [ ] Evaluate effectiveness (time saved, problems prevented)
- [ ] Plan agent development priorities
- [ ] Share sanitized knowledge with team
- [ ] Update methodology based on lessons learned

## Security and Privacy

### What Must Stay Private

**NEVER commit to public repos:**
- `.claude/agents/` (contain infrastructure context)
- `.claude/runbooks/` (contain exact server commands)
- `.claude/journal/` (contain session history)
- `.claude/active-context.md` (contains current state)
- `.claude/project-timeline.md` (contains project details)

**Why:** These contain:
- Real server IPs and hostnames
- Network topology details
- Database credentials (in commands)
- Security procedures
- Infrastructure weaknesses

### What Can Be Public

**Safe to commit:**
- `resources/docs/self-learning/` (methodology, templates, guides)
- Sanitized examples with placeholders
- Templates with generic structure
- Best practices documentation

### Sanitization Checklist

Before sharing ANY content:
- [ ] Replace all IPs with `server_ip`, `master_ip`, etc.
- [ ] Replace hostnames with `server.example.com`
- [ ] Replace real domains with `domain.com`
- [ ] Replace database names with `database_name`
- [ ] Remove ALL credentials (never use placeholders for passwords)
- [ ] Replace API keys with `API_KEY` placeholder
- [ ] Replace paths with generic `/path/to/resource`
- [ ] Review output examples for sensitive data

### .gitignore Verification

**Ensure these lines in `.gitignore`:**
```
# Claude Code Knowledge System - ALL PRIVATE
.claude/agents/
.claude/journal/
.claude/runbooks/
.claude/active-context.md
.claude/project-timeline.md
```

**Test:**
```bash
# Verify gitignore is working
git status | grep -E '\.claude/(agents|journal|runbooks)'
# Should return nothing!
```

## Performance Metrics

### Track These Metrics

**Time Saved:**
- First time solving problem: X hours
- Using runbook: Y minutes
- Time saved: X - Y

**Success Rate:**
- Runbook success rate: X/Y uses
- Agent success rate: solving problems
- Knowledge reuse rate: new vs existing artifacts

**Knowledge Growth:**
- Agents created: Count over time
- Runbooks created: Count over time
- Journal entries: Count over time
- Agent refinements: Updates per artifact

### Example Metrics

**Scenario**: DNS Zone Replication Problem

**First Time (Nov 24):**
- Investigation: 2 hours
- Solution: 1 hour
- Total: 3 hours
- Artifacts created: Agent, Runbook, Journal

**Second Time (Dec 15):**
- Use runbook: 18 minutes
- Success: Yes
- Time saved: 2h 42min (90% reduction!)

**Third Time (Jan 10):**
- Use runbook: 35 minutes (firewall issue encountered)
- Success: Yes (added firewall to Common Issues)
- Time saved: 2h 25min (81% reduction)

**ROI Calculation:**
```
Initial investment: 3 hours (solve + document)
Time saved: 2.67 + 2.42 = 5.09 hours
ROI: 70% return in just 2 uses!
```

## Common Pitfalls

### Pitfall 1: Creating Too Early

**Problem**: Writing runbook before understanding problem

**Solution**: Solve problem first, THEN document

**Example:**
- ❌ Write runbook based on assumptions
- ✅ Solve problem, document what actually worked

### Pitfall 2: Too Generic

**Problem**: Agents that try to do everything

**Solution**: One domain per agent

**Example:**
- ❌ "System Administration Agent"
- ✅ "PowerDNS Zone Replication Specialist"

### Pitfall 3: Too Specific

**Problem**: Runbooks tied to one server/domain

**Solution**: Use placeholders for all variables

**Example:**
- ❌ `sx ns1rn 'mysql powerdns...'`
- ✅ `sx master_server 'mysql database_name...'`

### Pitfall 4: No Maintenance

**Problem**: Creating artifacts then never updating them

**Solution**: Update after every use

**Example:**
```markdown
**Last Used**: 2025-11-24
**Success Rate**: 1/1
```
→ Update after next use:
```markdown
**Last Used**: 2025-12-15
**Success Rate**: 2/2
```

### Pitfall 5: Forgetting to Journal

**Problem**: Solving problems without creating journal entries

**Solution**: ALWAYS use `/snapshot` after significant work

**When to journal:**
- ✅ After solving any problem
- ✅ After debugging session
- ✅ After implementing new feature
- ✅ After incident response
- ❌ Don't skip because "it was quick" (still valuable!)

### Pitfall 6: Hardcoded Secrets

**Problem**: Including passwords or API keys in artifacts

**Solution**: NEVER include credentials

**Example:**
- ❌ `mysql -uroot -pSecretPass123`
- ✅ `mysql -e "SELECT..."` (auth handled by sx)
- ✅ `API_KEY=\${API_KEY}` (reference env var)

## Integration Patterns

### Pattern 1: Agent → Runbook → Journal

**Use case**: Novel problem becomes routine procedure

**Flow:**
1. Agent solves novel problem (exploration)
2. Agent creates runbook (distilled procedure)
3. Journal documents session (historical record)

**Example:** First DNS replication issue

### Pattern 2: Runbook → Agent (escalation)

**Use case**: Runbook fails, need autonomous help

**Flow:**
1. Follow runbook (normal procedure)
2. Runbook fails at step 4 (unexpected edge case)
3. Invoke agent for complex troubleshooting
4. Agent solves, updates runbook with new issue

**Example:** Zone transfer fails due to unexpected firewall

### Pattern 3: Journal → Agent (learning)

**Use case**: Multiple journals reveal pattern

**Flow:**
1. Solve problem A, journal it
2. Solve problem B (similar), journal it
3. Solve problem C (similar), journal it
4. Create agent capturing the pattern

**Example:** Three mail server issues reveal DKIM pattern

### Pattern 4: Agent + Runbook (collaboration)

**Use case**: Complex problem with some routine steps

**Flow:**
1. Agent diagnoses complex issue
2. Agent says "For step 5, use runbook X"
3. Follow runbook for mechanical steps
4. Agent resumes for complex parts

**Example:** DNS migration with routine zone transfer

## Team Collaboration

### Knowledge Sharing

**Public artifacts** (sanitized):
- Share methodology in `resources/docs/self-learning/`
- Share templates for team to use
- Share examples (placeholders only)

**Private artifacts** (never shared publicly):
- Keep `.claude/` directory local
- Share specific agents/runbooks within team only
- Always sanitize before sharing outside team

### Onboarding New Team Members

**Provide:**
1. `resources/docs/self-learning/README.md` (overview)
2. `guides/` directory (how to create artifacts)
3. `templates/` directory (starting points)
4. Selected sanitized examples

**Don't provide:**
- Real `.claude/agents/` (infrastructure-specific)
- Real `.claude/runbooks/` (contain exact commands)
- Real `.claude/journal/` (historical details)

**Instead, teach them to:**
- Create their own agents as they solve problems
- Use `/agent` and `/runbook` commands
- Build their own knowledge base

### Collaborative Improvement

**Pattern**: Shared learning loop

1. Person A solves problem, creates agent
2. Person B uses agent, encounters edge case
3. Person B updates agent with solution
4. Person C benefits from refined agent

**Result**: Agent improves with each use across team

## Summary

**The self-learning system works when:**
1. You ALWAYS create journal after significant work (`/snapshot`)
2. You create agents for novel problems (capture thinking)
3. You create runbooks for repeatable procedures (capture steps)
4. You update artifacts after every use (compound knowledge)
5. You sanitize ALL private data (never commit infrastructure details)

**Success looks like:**
- Time to solve problems decreases over time
- Knowledge base grows steadily
- Recurring problems become automated
- Team can self-serve from knowledge artifacts
- New team members ramp up faster

**Remember:** The goal is to **never solve the same problem twice**. Every solution should create or improve artifacts that make the next similar problem trivial.
