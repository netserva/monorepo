# Memory Management for Claude Code

**Advanced context optimization and CLAUDE.md strategy for NetServa 3.0**

---

## Context Window Economics

### Token Budget Reality

**Standard Claude Models:**
- **200,000 tokens** per session
- Roughly 150,000 words or 75,000 lines of code
- Performance **degrades significantly at 70-95% capacity**

**Extended Context (Claude Sonnet 4.5 API):**
- **1,000,000 tokens** available
- Costs: 2x for input, 1.5x for output beyond 200K threshold
- Same degradation pattern at high utilization

### What Consumes Context

**Every session includes:**
- Conversation messages and responses
- File contents read with Read tool
- Tool outputs (bash, grep, glob results)
- All loaded CLAUDE.md files (hierarchical)
- Command outputs and error messages

**Example consumption:**
- Large Laravel controller analysis: 3,000-5,000 tokens
- Conversation about architecture: 500-1,000 tokens
- CLAUDE.md files (all levels): 2,000-4,000 tokens
- Reading 10 related files: 10,000-20,000 tokens

---

## Strategic Context Management

### The /clear vs /compact Decision

**Use /clear (99% of the time):**

✅ **When:**
- Starting a new, distinct task
- Switching between subsystems
- Approaching 70% capacity
- Completed current feature/bug

✅ **Benefits:**
- Instant operation (< 1 second)
- Maintains AI quality and focus
- Reduces token costs
- Prevents context pollution

**Use /compact (rarely):**

⚠️ **When:**
- Must preserve current session context
- In middle of complex debugging session
- Have critical information from earlier conversation
- Can't afford to lose thread

⚠️ **Drawbacks:**
- Takes 60+ seconds to execute
- AI becomes "dumber" - loses context fidelity
- May forget specific file details
- Can drop narrative thread
- Quality degradation accumulates

### Proactive Clearing Strategy

**Clear between distinct tasks:**
```
✅ Feature complete → /clear → Start new feature
✅ Bug fixed → /clear → Next bug
✅ Refactor done → /clear → New refactor
✅ Tests passing → /clear → New test suite
```

**Keep context during:**
```
❌ Multi-step debugging (need conversation history)
❌ Complex refactor across files (need mental model)
❌ Following implementation plan (need strategy context)
```

### Monitoring Context Usage

**Interface indicator (bottom right):**
- Green (0-50%): Comfortable working room
- Yellow (50-70%): Monitor but continue
- Orange (70-85%): **Plan to clear soon**
- Red (85%+): **Clear immediately** or accept degradation

**Token consumption awareness:**
```bash
# These consume significant context:
Read tool on large files          # 2,000-5,000 tokens each
Grep with many results            # 500-2,000 tokens
Long conversations               # 1,000-3,000 tokens
Multiple file reads              # Compounds quickly
```

---

## CLAUDE.md Optimization

### The 100-200 Line Rule

**Why it matters:**
- CLAUDE.md content prepended to **every** prompt
- 200 lines ≈ 1,000-2,000 tokens per request
- Multiplied by every AI response in session
- Can consume 20K+ tokens in active session

**Optimization strategies:**

**1. Ruthless Conciseness**
```markdown
❌ Too verbose:
"When creating new vhosts, you should ensure that all configuration is stored in the database using the vconfs table, following our database-first architecture pattern."

✅ Concise:
Database-first: ALL vhost config/credentials in `vconfs` table - NEVER files
```

**2. Specific Over Generic**
```markdown
❌ Generic:
"Use consistent code formatting"

✅ Specific:
Run `vendor/bin/pint --dirty` before commits (Laravel Pint formatter)
```

**3. Remove Redundancy**
```markdown
❌ Redundant:
packages/netserva-core/    # Contains core functionality for NetServa

✅ Self-explanatory:
packages/netserva-core/    # (no explanation needed - name is clear)
```

### Hierarchical CLAUDE.md Architecture

**Goal:** Provide enriched context **only when working in that subsystem**

**Structure for NetServa:**

```
~/.ns/CLAUDE.md                                  ← Root: Essential rules, tech stack (150 lines)
│
├── packages/netserva-core/CLAUDE.md            ← Core: Model patterns, service conventions (80 lines)
├── packages/netserva-cli/CLAUDE.md             ← CLI: Command conventions, vnode/vhost patterns (60 lines)
├── packages/netserva-web/CLAUDE.md             ← Web: Nginx patterns, web server config (50 lines)
│
├── app/Filament/CLAUDE.md                      ← Filament: v4.1 resource patterns (100 lines)
│   └── app/Filament/Resources/FleetVHostResource/CLAUDE.md  ← Heavy business rules (40 lines)
│
└── tests/CLAUDE.md                             ← Testing: Mocking SSH, Pest patterns (70 lines)
```

**Loading behavior:**

```bash
# Working on CLI command
~/ns/packages/netserva-cli/src/Commands/AddVHostCommand.php
# Loads: ~/.ns/CLAUDE.md + packages/netserva-cli/CLAUDE.md
# Context: ~210 lines (Root 150 + CLI 60)

# Working on Filament resource
~/ns/app/Filament/Resources/FleetVHostResource.php
# Loads: ~/.ns/CLAUDE.md + app/Filament/CLAUDE.md
# Context: ~250 lines (Root 150 + Filament 100)

# Working on complex resource with business rules
~/ns/app/Filament/Resources/FleetVHostResource/Pages/EditFleetVHost.php
# Loads: ~/.ns/CLAUDE.md + app/Filament/CLAUDE.md + app/Filament/Resources/FleetVHostResource/CLAUDE.md
# Context: ~290 lines (Root 150 + Filament 100 + Resource 40)
```

### What Belongs in Each Level

**Root CLAUDE.md (project-wide):**
- Critical architecture decisions (database-first, SSH execution pattern)
- Tech stack (Laravel 12, Filament 4.1, Pest 4.0)
- Universal CLI conventions (positional args pattern)
- Mandatory workflows (testing, Pint formatting)
- Laravel Boost MCP tool usage

**Subsystem CLAUDE.md (package/directory-specific):**
- Package-specific patterns (service layer, action classes)
- Local file organization conventions
- Specialized testing approaches
- Domain-specific terminology
- Integration points with other subsystems

**Feature CLAUDE.md (complex resources/features):**
- Business rules specific to this feature
- State machines and valid transitions
- Complex validation logic
- Related tables and relationships
- Known edge cases and gotchas

---

## Implementation Tracking Systems

### Why Persistent Memory Matters

**Problem:**
- Sessions crash (connection issues, timeouts)
- Context cleared (new features, capacity limits)
- Team handoffs (another developer continues work)
- Token limits reached mid-feature

**Solution: Markdown-based Implementation Plans**

### Design Documents

**Location:** `docs/design/`
**Purpose:** High-level goals, constraints, preferences before implementation

**Template:**
```markdown
# Feature: VHost Auto-Configuration

## Goals
- Automatically detect optimal PHP version for domain
- Configure nginx based on detected application type
- Set up SSL certificates without manual intervention

## Constraints
- Must work with existing vconfs database schema
- Cannot require manual SSH key setup
- Must complete in < 30 seconds

## Preferences
- Laravel Prompts for interactive confirmation
- Rollback on any failure (transactions)
- Comprehensive Pest 4.0 test coverage

## Non-Goals
- Multi-server orchestration (future feature)
- Custom PHP compilation (use system packages)
```

### Implementation Plans

**Location:** `docs/implementation/`
**Purpose:** Step-by-step plan with checkbox progress tracking

**Template:**
```markdown
# Implementation Plan: VHost Auto-Configuration

**Status:** In Progress
**Started:** 2025-10-08
**Target Completion:** 2025-10-12

## Phase 1: Detection Service
- [x] Create `AutoConfigService` class
- [x] Implement Laravel detection (composer.json parser)
- [x] Implement WordPress detection (wp-config.php scanner)
- [ ] Implement static HTML detection (fallback)
- [x] Write comprehensive Pest tests for detection logic

## Phase 2: PHP Version Selection
- [x] Query available PHP versions from vnode
- [ ] Match framework requirements (Laravel min version)
- [ ] Select optimal version with fallback chain
- [ ] Test version selection across multiple scenarios

## Phase 3: Nginx Configuration
- [ ] Generate nginx config from templates
- [ ] Apply Laravel-specific nginx rules
- [ ] Configure FastCGI parameters for selected PHP version
- [ ] Test nginx config validation

## Phase 4: SSL Certificate Setup
- [ ] Integrate with existing ACME client
- [ ] Request certificate for domain
- [ ] Configure nginx SSL directives
- [ ] Test certificate renewal workflow

## Phase 5: Integration Testing
- [ ] End-to-end test: Laravel application
- [ ] End-to-end test: WordPress site
- [ ] End-to-end test: Static HTML
- [ ] Error handling: Invalid domain
- [ ] Error handling: SSL failure
- [ ] Error handling: PHP version unavailable

## Known Issues
- Issue #42: DNS propagation delays can cause SSL failures
  - Workaround: Retry with exponential backoff
- Issue #47: WordPress multisite detection needs improvement
  - TODO: Check wp-config.php for MULTISITE constant

## Decisions Made
- Using Laravel's config system over environment variables (ADR-0015)
- Selected phpseclib over SSH keys for simplicity (ADR-0002)
- Chose nginx over Apache (better performance, simpler config)

## Next Steps After Completion
1. Add to Filament admin panel (FleetVHostResource action)
2. Create CLI command: `php artisan vhost:autoconfig <vnode> <vhost>`
3. Update documentation in resources/docs/
4. Create ADR for auto-configuration strategy
```

**Benefits:**
- **Survives crashes** - Text file readable by anyone
- **Team handoffs** - Clear progress and context
- **Milestone tracking** - Visual progress with checkboxes
- **Decision record** - Captures why choices were made
- **Debugging aid** - Known issues documented inline

### Update Strategy

**As you work:**
```bash
# Complete a task
git commit -m "Implement Laravel detection in AutoConfigService"

# Update implementation plan
# Check off: [x] Implement Laravel detection
# Add notes about challenges or decisions
# Document any new issues discovered

# Continue to next task
```

**End of session:**
```bash
# Save progress in implementation plan
# Commit plan updates
git add docs/implementation/vhost-auto-config.md
git commit -m "Update implementation plan: Phase 1 complete"

# Next session: Read plan to resume context quickly
```

---

## Session Continuity

### Session Persistence

**Automatic storage:**
```
~/.claude/projects/netserva-3.0/[session-id].jsonl
```

**Resume commands:**
```bash
claude --continue              # Resume most recent session
claude --resume abc123         # Resume specific session by ID
```

**What persists:**
- Conversation history
- Background bash shells
- File contexts loaded
- Working directory state

**What doesn't persist:**
- In-memory analysis not written to files
- Temporary conclusions not documented
- Mental models not captured in CLAUDE.md

### Recovery Workflow

**After context clear or session end:**

1. **Quick context reload:**
```bash
# Read implementation plan
cat docs/implementation/current-feature.md

# Check recent commits
git log --oneline -10

# Review CLAUDE.md for project conventions
cat CLAUDE.md
```

2. **Resume with focused prompt:**
```
"I'm continuing work on VHost auto-configuration feature.
Please read docs/implementation/vhost-auto-config.md for current progress.
Next task: Implement static HTML detection with fallback logic.
Tests required for all detection methods."
```

3. **Update as you go:**
- Check off completed items in real-time
- Document new decisions immediately
- Capture issues as they're discovered

---

## Advanced Optimization Techniques

### Configuration Excludes

**Repomix configuration** (`.repomixrc.yaml`):
```yaml
exclude:
  - storage/**
  - .git/**
  - node_modules/**
  - vendor/**
  - public/build/**
  - bootstrap/cache/**
  - tests/Browser/screenshots/**

# Keep focused on source code
```

### Smart File Reading

**Instead of reading everything:**
```bash
# ❌ Wasteful - reads entire directory
Read all files in app/Models/

# ✅ Targeted - read specific files as needed
Read app/Models/FleetVHost.php
Read app/Models/VConf.php
```

**Use Grep before Read:**
```bash
# Find relevant files first
Grep "RemoteExecutionService" packages/

# Then read only the matches
Read packages/netserva-cli/src/Services/RemoteExecutionService.php
```

### Project Rules with Smart Globs

Apply rules only to matching files:
```json
{
  "glob": "packages/*/tests/**/*Test.php",
  "rule": "Always mock SSH connections in tests"
}
```

**Benefit:** Rule only loads when working on test files

---

## Anti-Patterns to Avoid

### ❌ Context Bloat

**Problem:**
```
Session includes:
- All 50 model files read "just in case"
- Entire conversation about unrelated feature
- Multiple failed debugging attempts
- Exploratory code not relevant to current task
```

**Solution:**
```
/clear frequently between tasks
Read files only when needed
Focus on current task context
```

### ❌ CLAUDE.md Bloat

**Problem:**
```markdown
# CLAUDE.md (800 lines)
- Detailed history of project evolution
- Examples of every possible pattern
- Explanations of obvious directory names
- Copy-pasted framework documentation
```

**Solution:**
```markdown
# CLAUDE.md (150 lines)
- Essential rules and patterns
- Project-specific conventions
- Critical "Do NOT" warnings
- Links to detailed docs for reference
```

### ❌ Compaction Addiction

**Problem:**
```
Session reaches 80% → /compact
30 minutes later → /compact again
1 hour later → /compact again
Result: AI "forgets" earlier decisions, quality degrades
```

**Solution:**
```
Session reaches 70% → /clear
Start fresh with implementation plan
Reload only essential context
```

---

## Quick Reference

### Context Management Checklist

- [ ] Monitor context indicator continuously
- [ ] Clear at 70% capacity (not 90%+)
- [ ] Use /clear between distinct tasks
- [ ] Avoid /compact unless absolutely necessary
- [ ] Read files on-demand, not speculatively
- [ ] Keep CLAUDE.md under 200 lines
- [ ] Use hierarchical CLAUDE.md for subsystems
- [ ] Track progress in markdown implementation plans

### Optimization Wins

**High Impact:**
- Keep root CLAUDE.md under 150 lines: **Saves 1K+ tokens per request**
- Use /clear instead of /compact: **Maintains AI quality**
- Read files on-demand: **Saves 10-20K tokens per session**

**Medium Impact:**
- Hierarchical CLAUDE.md: **Saves 500-1K tokens per request**
- Implementation plans: **Enables session recovery**
- Smart grep before read: **Saves 5-10K tokens**

**Low Impact but cumulative:**
- Exclude build artifacts: **Cleaner context**
- Project-specific rules with globs: **Focused guidance**
- Session learning prompts: **Improves over time**

---

**Next Steps:**
1. Audit current root CLAUDE.md (should be ~150 lines)
2. Create subsystem CLAUDE.md for packages/
3. Set up docs/implementation/ directory
4. Review documentation-standards.md for AI-readable docs

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
