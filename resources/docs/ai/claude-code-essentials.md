# Claude Code Essentials for NetServa 3.0

**Core patterns and practices for AI-assisted development with Claude Code CLI**

---

## Overview

Claude Code transforms development through a sophisticated memory system that maintains context across sessions. Success requires:

1. **Structured project information** as concise documentation (CLAUDE.md files)
2. **Proactive context management** to stay within token limits
3. **Test-driven workflows** with mandatory code review
4. **Hierarchical memory system** for focused, efficient context loading

**Token Budget:** 200K tokens standard (1M available via extended context)
**Real-world Impact:** 50-70% faster initial development with proper context management

---

## Memory System Architecture

### Four-Tier Hierarchical Memory

**1. Enterprise Policy (System-wide)**
- Location: System directories
- Purpose: Organization-wide standards
- Scope: All projects, all users

**2. User Memory (Personal)**
- Location: `~/.claude/CLAUDE.md`
- Purpose: Personal preferences across all projects
- Scope: Your development style, tools, shortcuts

**3. Project Memory (Team-shared)**
- Location: `./CLAUDE.md` (committed to git)
- Purpose: Team conventions, architecture decisions
- Scope: This project, shared with team

**4. Local Project Memory (Personal overrides)**
- Location: `./CLAUDE.local.md` (gitignored)
- Purpose: Personal project preferences
- Scope: This project, not shared

### Loading Strategy

**Recursive upward search** from current working directory:
```
Current: ~/projects/netserva/.ns/packages/netserva-core/src/Models/
Loads:  ~/.claude/CLAUDE.md                        ← User memory
        ~/projects/netserva/.ns/CLAUDE.md          ← Project root
        ~/projects/netserva/.ns/packages/netserva-core/CLAUDE.md  ← Package-specific
```

**On-demand loading** - only loads CLAUDE.md files in your working path
**Focused context** - doesn't load unrelated subsystem documentation

---

## Context Management Strategy

### Critical Rules

**1. Use /clear Frequently, NOT /compact**
- Clear context between distinct tasks
- Prevents bloat and maintains focus
- Reduces token costs dramatically
- /compact degrades quality and takes 60+ seconds

**2. Monitor Context Usage**
- Watch indicator (bottom right of interface)
- Act at **70% capacity** before degradation
- Context includes: conversation, files read, tool outputs, CLAUDE.md files

**3. Session Persistence**
- History stored in `~/.claude/projects/.../[session-id].jsonl`
- Resume with `claude --continue` (most recent) or `claude --resume [id]`
- Background shells persist across sessions
- File contexts remain in memory

### When to Clear Context

✅ **Clear when:**
- Starting a new, unrelated feature
- Switching between subsystems
- Approaching 70% capacity
- After completing a major task

❌ **Don't clear when:**
- In middle of complex debugging
- Have important conversation context needed
- About to use information from earlier in session

---

## CLAUDE.md File Guidelines

### Critical Principles

**Keep concise:** 100-200 lines maximum (gets prepended to EVERY prompt)
**Be specific:** "Use 2-space indentation for TS; 4-space for Python" NOT "Use consistent indentation"
**Avoid redundancy:** Folder named "components" doesn't need explanation

### Essential Sections

```markdown
# Project Name

## Tech Stack
- Framework: Laravel 12
- Admin: Filament 4.1
- Database: MySQL 8.0 (prod) / SQLite (dev)
- Testing: Pest 4.0

## Architecture
- Database-first: All vhost config in `vconfs` table
- Remote SSH: Use `RemoteExecutionService::executeScript()` heredoc pattern
- Platform schema: venue → vsite → vnode → vhost + vconf → vserv

## CLI Conventions
- Positional args: `<command> <vnode> <vhost> [options]`
- NO --vnode or --shost flags
- ALL commands follow this pattern

## Critical Commands
php artisan fleet:discover --vnode=markc
php artisan test --filter=TestName
vendor/bin/pint --dirty

## Do NOT
- Never hardcode credentials (use vconfs table)
- Never copy scripts to remote servers (execute from workstation)
- Never use file-based config (use database)
- Never skip tests (100% coverage required)

## Laravel Boost Usage
- ALWAYS use `search-docs` before implementing features
- Use `tinker` for debugging, not verification scripts
- Check `database-schema` before migrations
```

### Hierarchical Organization

**Root CLAUDE.md** - General conventions, tech stack, critical patterns
**Subsystem CLAUDE.md** - Specific to that domain:

```
packages/netserva-core/CLAUDE.md     ← Core models, services
packages/netserva-cli/CLAUDE.md      ← CLI command patterns
app/Filament/CLAUDE.md               ← Filament v4.1 resource conventions
tests/CLAUDE.md                      ← Testing patterns, mocking strategies
```

---

## Laravel Boost MCP Integration

### Essential Tools

**Before Implementation:**
- `search-docs` - ALWAYS check before coding Laravel features
- `database-schema` - Verify schema before creating migrations
- `list-artisan-commands` - Check available options
- `list-routes` - Verify routes before adding

**During Development:**
- `tinker` - PHP debugging in Laravel context
- `database-query` - Read-only data queries
- `browser-logs` - Frontend debugging

**Documentation Queries:**
- Use multiple broad queries: `['rate limiting', 'routing rate limiting', 'routing']`
- Don't include package names: `test resource table` NOT `filament 4 test resource table`
- Let Boost filter by installed versions automatically

---

## Session Learning Pattern

### End-of-Session Prompt

Use this at end of complex sessions:

> "If during this session you learned something new about the project, I corrected you on an implementation detail, you struggled to find information, or you lost track of project structure, add those learnings to the appropriate CLAUDE.md file."

**Results:**
- Mistakes become permanent knowledge
- Reduces repeated corrections
- Improves AI accuracy over time

**Organization by component:**
- UI learnings → `apps/heatsense-ui/CLAUDE.md`
- Infrastructure → `cdk/CLAUDE.md`
- Core services → `packages/netserva-core/CLAUDE.md`

---

## Implementation Tracking

### Persistent Memory Alternative

**Problem:** Sessions crash, context clears, team handoffs lose continuity
**Solution:** Markdown-based implementation plans

**Workflow:**

1. **Create design doc** - `docs/design/feature-name.md` with goals, constraints
2. **Generate implementation plan** - `docs/implementation/feature-name.md` with checkboxes
3. **Update as you work** - Check off completed items
4. **Survives everything** - Text file readable by humans and AI

**Benefits:**
- Survives crashes and session clears
- Enables team handoffs
- Acts as documentation
- No special tools required

---

## Key Metrics

**Context Window:**
- Standard: 200K tokens (~150K words or 75K lines of code)
- Extended: 1M tokens (2x input cost, 1.5x output cost beyond 200K)
- Performance degrades significantly at 70-95% capacity

**Real-world Performance:**
- 50-70% faster initial feature development
- 80-90% less manual boilerplate
- Test generation: hours → minutes
- **Requires mandatory code review** for quality

**Token Consumption:**
- Architecture explanations: 500-1000 tokens
- Conventions: 200-500 tokens
- Patterns: 300-800 tokens
- **Proper CLAUDE.md eliminates this repetition**

---

## Common Pitfalls

### Context Overload
❌ Including too much irrelevant context
✅ Use /clear frequently, hierarchical CLAUDE.md

### Inadequate Testing
❌ AI code that looks perfect but breaks
✅ TDD approach, test immediately after implementation

### Loss of Code Ownership
❌ Not understanding AI-generated code
✅ Mandatory review, read through Git diffs, keep AI comments initially

### Inconsistent Style
❌ AI generates code not matching conventions
✅ Comprehensive CLAUDE.md with specific examples

### Security Concerns
❌ Sharing sensitive code without approval
✅ Explicit team policies, add sensitive files to excludes

---

## Quick Reference

### Essential Commands
```bash
claude                          # Start new session
claude --continue              # Resume most recent
claude --resume [id]           # Resume specific session
/clear                         # Clear context (use frequently)
/help                          # Get help
```

### File Locations
```
~/.claude/CLAUDE.md            # User preferences
./CLAUDE.md                    # Project conventions (git)
./CLAUDE.local.md              # Personal overrides (gitignored)
./.claude/commands/            # Custom slash commands
docs/adr/                      # Architectural Decision Records
docs/implementation/           # Implementation plans with progress tracking
```

### Best Practices Checklist
- [ ] CLAUDE.md under 200 lines with specific examples
- [ ] Use /clear between distinct tasks
- [ ] Monitor context indicator, act at 70%
- [ ] Search docs before implementing Laravel features
- [ ] Write tests immediately after implementation
- [ ] Review all AI changes through Git
- [ ] Update CLAUDE.md when correcting AI mistakes
- [ ] Document decisions in ADRs
- [ ] Track implementation in markdown files

---

**Next Steps:**
1. Read `memory-management.md` for deep context optimization
2. Review `proven-workflows.md` for development patterns
3. Check `documentation-standards.md` for AI-readable docs
4. Explore `.claude/commands/` for workflow automation

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
