# NetServa Self-Learning System

**Public Documentation** - Methodology and templates for building institutional knowledge

## Overview

NetServa uses a **three-part knowledge system** that captures and compounds expertise over time:

```
~/.ns/.claude/              # Private (gitignored - contains real infrastructure)
├── agents/                 # 🤖 Autonomous problem solvers
├── runbooks/               # 📋 Step-by-step procedures
└── journal/                # 📖 Historical records
```

## The Knowledge Triad

| Type | Purpose | Created By | Used By |
|------|---------|------------|---------|
| **Agent** | Autonomous problem solver | Human or `/agent` | Claude Code (Task tool) |
| **Runbook** | Mechanical procedure | Agent or `/runbook` | Human or Agent |
| **Journal** | Historical narrative | `/snapshot` | Future reference |

## The Learning Cycle

```
1. Novel Problem Arises
   ↓
2. Use Agent (autonomous exploration)
   ↓
3. Agent Solves Problem
   ↓
4. Agent Creates/Updates Runbook (distilled procedure)
   ↓
5. Use /snapshot → Journal (historical record)

Next Similar Problem:
├─ Simple? → Follow Runbook (15 min)
└─ Complex? → Use Agent (references Runbook + adapts)
```

## Slash Commands

### `/snapshot` - Create Journal Entry
**When**: After solving a problem or completing a feature

**What it does:**
- Analyzes conversation for key decisions
- Extracts code changes and challenges
- Creates timestamped entry in `.claude/journal/`
- Updates active context

**Example:**
```bash
User: /snapshot
Claude: Created journal entry: 2025-11-24_dns-zone-replication-fix.md
```

### `/agent <category/name>` - Create New Agent
**When**: After solving a novel problem that should be automated

**What it does:**
- Creates agent template in `.claude/agents/<category>/`
- Pre-fills with context from current session
- Includes tools, constraints, and best practices

**Example:**
```bash
User: /agent dns/zone-migrator
Claude: Created agent: .claude/agents/dns/zone-migrator.md
        Template includes context from today's DNS migration work.
```

### `/runbook <category/name>` - Create Runbook
**When**: After completing a procedure that should be repeatable

**What it does:**
- Extracts commands executed during session
- Creates runbook template in `.claude/runbooks/<category>/`
- Includes prerequisites, steps, rollback procedure

**Example:**
```bash
User: /runbook dns/fix-zone-replication
Claude: Created runbook: .claude/runbooks/dns/fix-zone-replication.md
        Captured 8 commands from today's session.
```

### `/guide` - Open Methodology Docs
**When**: Need reference for best practices

**What it does:**
- Opens this documentation
- Shows templates and examples
- Explains when to use each artifact type

## Directory Structure

```
~/.ns/
├── .claude/                           # Private (gitignored)
│   ├── agents/
│   │   ├── dns/
│   │   │   ├── zone-migrator.md
│   │   │   └── normalizer.md
│   │   ├── mail/
│   │   ├── security/
│   │   └── fleet/
│   │
│   ├── runbooks/
│   │   ├── dns/
│   │   │   ├── fix-zone-replication.md
│   │   │   └── migrate-zone.md
│   │   ├── mail/
│   │   └── security/
│   │
│   └── journal/
│       ├── 2025-11-24_dns-fix.md
│       └── 2025-11-23_ssl-setup.md
│
└── resources/docs/self-learning/      # Public (committed)
    ├── README.md                       # This file
    ├── guides/
    │   ├── creating-agents.md
    │   ├── writing-runbooks.md
    │   └── best-practices.md
    ├── templates/
    │   ├── agent-template.md
    │   ├── runbook-template.md
    │   └── journal-template.md
    └── examples/
        ├── agent-example-sanitized.md
        └── runbook-example-sanitized.md
```

## When to Create Each Artifact

### Create an Agent When:
✅ Solved a novel problem that may recur
✅ Task requires autonomous decision-making
✅ Multiple steps with contextual branching
✅ Want AI to handle edge cases automatically

❌ Don't create if: Simple linear procedure (use runbook instead)

### Create a Runbook When:
✅ Procedure is well-defined and repeatable
✅ Exact command sequence matters
✅ Want copy/paste execution (human or script)
✅ Training documentation needed

❌ Don't create if: Highly variable context (use agent instead)

### Create a Journal When:
✅ After significant debugging session
✅ Made architectural decisions
✅ Solved problem with lessons learned
✅ Need audit trail of what was done

✅ Always create after any substantial work!

## Getting Started

### 1. First Time Solving a Problem
Just work through it naturally with Claude Code.

### 2. After Solution
Run `/snapshot` to capture what happened.

### 3. Extract Reusable Knowledge
- If autonomous workflow: `/agent <name>`
- If repeatable procedure: `/runbook <name>`
- Both reference the journal entry

### 4. Next Similar Problem
- Check `.claude/runbooks/` first
- If no runbook, check `.claude/agents/`
- If neither, solve and create new artifacts

## Examples

See `examples/` directory for:
- Sanitized agent prompts (placeholders instead of real IPs)
- Sanitized runbook procedures (generalized commands)
- Example journal entries (redacted sensitive data)

## Best Practices

### Agent Best Practices
- Start with clear objective statement
- List available tools explicitly
- Include common edge cases
- Reference relevant runbooks
- Never hardcode IPs (use variables)

### Runbook Best Practices
- Include verification checkpoint after each step
- Document rollback procedure
- Track success rate (X/Y executions)
- Update after each use
- Add common issues as encountered

### Journal Best Practices
- Focus on "why" not just "what"
- Include decisions and tradeoffs
- Link to relevant files with line numbers
- List next steps at end
- Keep technical details concise

## Security Note

⚠️ **Never commit `.claude/` directory to git!**

The `.claude/` directory contains:
- Real server IPs and hostnames
- Database credentials and commands
- Infrastructure topology details
- Security-sensitive procedures

Always use placeholders in public documentation:
- `master_server` not `203.25.238.5`
- `database_name` not `powerdns`
- `domain.com` not `cooloola.net`

## Further Reading

- `guides/creating-agents.md` - Agent development guide
- `guides/writing-runbooks.md` - Runbook authoring guide
- `guides/best-practices.md` - Quality standards
- `templates/` - Copy-paste templates
- `examples/` - Sanitized real-world examples

---

**Remember:** The goal is to **never solve the same problem twice**. Every solution should either update existing knowledge or create new artifacts for future use.
