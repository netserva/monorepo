# Proven Workflows for AI-Assisted Development

**Battle-tested development patterns for Laravel + Filament with Claude Code**

---

## Overview

Successful AI-assisted development requires structured workflows that balance automation with human oversight. The most effective pattern combines:

1. **Manual scaffolding** (foundations right the first time)
2. **AI-assisted planning** (detailed step-by-step strategy)
3. **Automated execution** (rapid implementation with monitoring)
4. **Mandatory review** (maintain code ownership and quality)

**Key Insight:** The 30 minutes spent planning saves hours of refactoring when AI misunderstands requirements.

---

## Four-Phase Development Workflow

### Phase 1: Scaffold (Manual Setup)

**DO manually with official Laravel commands:**

```bash
# Project initialization
laravel new netserva-project
cd netserva-project

# Core scaffolding
php artisan make:model FleetVHost -mfs
php artisan make:model VConf -mf
php artisan make:controller FleetVHostController --resource
php artisan make:request StoreFleetVHostRequest
php artisan make:policy FleetVHostPolicy
```

**WHY manual:**
- AI tools sometimes generate outdated templates
- Official commands include latest framework features
- Ensures proper file locations and naming
- Avoids configuration drift

**THEN use AI for:**
- Filling in implementation details
- Writing comprehensive tests
- Creating form schemas
- Implementing business logic

### Phase 2: Plan (AI-Assisted Design)

**Create comprehensive context snapshot:**

```bash
# Option 1: Using Repomix (recommended)
npx repomix --output context.xml

# Option 2: Manual file collection
cat CLAUDE.md > context.txt
find app/Models -name "*.php" >> context.txt
find routes -name "*.php" >> context.txt
```

**Feed to Claude with structured prompt:**

```
I have a Laravel 12 + Filament 4.1 project. Here's the current codebase:

<context>
[paste context.xml or attach file]
</context>

Task: Implement VHost auto-configuration feature

Requirements:
- Detect application type (Laravel/WordPress/Static)
- Select optimal PHP version based on framework requirements
- Generate nginx config from templates
- Request SSL certificate via ACME
- Store all config in vconfs database table
- Use RemoteExecutionService::executeScript() for SSH operations
- Follow CLI convention: autoconfig <vnode> <vhost>
- Comprehensive Pest 4.0 test coverage

Please create a detailed implementation plan with:
1. Step-by-step breakdown with specific files
2. Code examples for key methods
3. Artisan commands to run
4. Test cases to write
5. Potential gotchas and edge cases

Save the plan to docs/implementation/vhost-autoconfig.md
```

**AI generates implementation plan:**

```markdown
# Implementation Plan: VHost Auto-Configuration

## Phase 1: Detection Service (Est: 2 hours)
- [ ] Create `app/Services/ApplicationDetectionService.php`
  - detectFramework(string $path): string
  - detectPhpRequirements(string $framework): array
- [ ] Tests: `tests/Unit/Services/ApplicationDetectionServiceTest.php`

## Phase 2: PHP Version Selection (Est: 1 hour)
- [ ] Create `app/Services/PhpVersionSelectorService.php`
  - selectOptimalVersion(array $requirements, array $available): string
- [ ] Tests: `tests/Unit/Services/PhpVersionSelectorServiceTest.php`

[... detailed plan continues ...]
```

**Review and approve plan BEFORE implementation:**
- Check file locations match conventions
- Verify approach aligns with architecture
- Identify missing requirements
- Adjust estimates and priorities

### Phase 3: Execute (Automated Implementation)

**Copy approved plan into Claude Code with auto-run:**

```
Please implement the plan from docs/implementation/vhost-autoconfig.md

Key reminders:
- Use RemoteExecutionService::executeScript() for SSH (NOT executeAsRoot)
- Store ALL config in vconfs table (NOT files)
- CLI signature: `autoconfig {vnode} {vhost} {--option=}`
- Write Pest tests for EVERY service method
- Run vendor/bin/pint after changes
- Update implementation plan checkboxes as you complete tasks

Start with Phase 1: Detection Service
```

**Monitor progress actively:**
- Watch file changes in real-time
- Interrupt (Cmd+Shift+Backspace) when needed
- Provide specific corrections: "Use `executeScript()` not `executeAsRoot()`"
- Don't let AI continue if direction is wrong

**Incremental commits:**
```bash
# After each completed phase
git add app/Services/ApplicationDetectionService.php tests/
git commit -m "feat: Add application detection service with tests"

# Update implementation plan
git add docs/implementation/vhost-autoconfig.md
git commit -m "docs: Mark Phase 1 complete in implementation plan"
```

### Phase 4: Monitor and Review (Quality Assurance)

**CRITICAL: Review ALL changes through Git, not just AI diffs**

```bash
# See full context of changes
git diff --staged

# Review each file individually
git diff app/Services/ApplicationDetectionService.php

# Check test coverage
php artisan test --coverage
```

**Review checklist:**
- [ ] Code follows NetServa conventions (CLAUDE.md)
- [ ] Uses database-first architecture (vconfs table)
- [ ] SSH execution uses executeScript() heredoc pattern
- [ ] CLI follows positional args convention
- [ ] Comprehensive Pest 4.0 tests included
- [ ] Tests actually pass (run multiple times)
- [ ] Laravel Pint formatting applied
- [ ] No hardcoded credentials or file-based config
- [ ] Business logic in services, not resources
- [ ] Authorization via policies

**Testing workflow:**
```bash
# Run related tests first (fast feedback)
php artisan test --filter=ApplicationDetection

# Run full suite after review
php artisan test

# Run multiple times to catch flaky tests
php artisan test && php artisan test && php artisan test
```

**Stage and commit validated changes:**
```bash
# Stage files that look correct
git add app/Services/ApplicationDetectionService.php
git add tests/Unit/Services/ApplicationDetectionServiceTest.php

# Commit incrementally
git commit -m "feat: Add application detection with Laravel/WordPress/Static support"

# Continue reviewing and committing
```

**Update project knowledge:**
```bash
# If AI made mistakes repeatedly, update CLAUDE.md
# Example: AI kept using executeAsRoot instead of executeScript

echo "## Remote SSH Execution
ALWAYS use RemoteExecutionService::executeScript() heredoc pattern
NEVER use executeAsRoot() - deprecated and removed" >> CLAUDE.md

# Commit the learning
git add CLAUDE.md
git commit -m "docs: Add SSH execution pattern to CLAUDE.md"
```

---

## Feature Onboarding Workflow

### Design Document First

**Location:** `docs/design/feature-name.md`
**Purpose:** Align vision between human and AI BEFORE coding

**Template:**

```markdown
# Feature: VHost Auto-Configuration

## Goals
1. Automatically detect application framework type
2. Configure optimal PHP version for detected framework
3. Generate nginx config without manual intervention
4. Request and install SSL certificate
5. Complete entire process in < 30 seconds

## Constraints
- Must work with existing vconfs database schema (no new tables)
- Cannot require manual SSH key setup (use phpseclib pattern)
- Must handle DNS propagation delays gracefully
- Should support rollback on any failure

## Preferences
- Laravel Prompts for beautiful CLI interactions
- Progress bars for long-running operations
- Confirmations before destructive actions
- Detailed logging for debugging

## Non-Goals (Future Features)
- Multi-server orchestration (v3.1 roadmap)
- Custom PHP compilation (use system packages only)
- Application deployment automation (separate feature)
- Database migration automation (too dangerous)

## Success Criteria
- Laravel app detected and configured in < 15 seconds
- WordPress detected and configured in < 20 seconds
- SSL certificate obtained without user intervention
- Zero manual nginx config editing required
- 100% Pest test coverage
```

**Review with AI:**
```
Please read docs/design/vhost-autoconfig.md

Based on this design document:
1. Are there any missing requirements?
2. Do any goals conflict with NetServa architecture?
3. Are the constraints realistic?
4. What potential challenges do you foresee?

After discussion, create detailed implementation plan.
```

### Implementation Plan Generation

**Prompt for detailed planning:**

```
Create a detailed implementation plan for VHost auto-configuration feature.

Requirements from design doc: docs/design/vhost-autoconfig.md
Current architecture: CLAUDE.md
Related systems: docs/SSH_EXECUTION_ARCHITECTURE.md

Implementation plan should include:
1. Service classes needed (with method signatures)
2. CLI command structure
3. Filament action integration
4. Database schema impact (if any)
5. Test coverage strategy
6. Estimated time per phase
7. Dependencies and prerequisites
8. Potential risks and mitigations

Save plan to: docs/implementation/vhost-autoconfig.md
Format with checkboxes for progress tracking
```

### Iterative Implementation

**Start small, build incrementally:**

```bash
# Phase 1: Core detection service (no SSH yet)
- Implement detection logic
- Write comprehensive tests
- Verify tests pass
- Commit

# Phase 2: PHP version selection (still no SSH)
- Implement version selector
- Test with various requirements
- Commit

# Phase 3: SSH integration (now use RemoteExecutionService)
- Integrate detection with SSH
- Mock SSH in tests
- Test on real vnode
- Commit

# Phase 4: Nginx config generation
# Phase 5: SSL certificate automation
# Phase 6: CLI command
# Phase 7: Filament action
# Phase 8: Integration tests
```

**Benefits of incremental approach:**
- Easier to review small changes
- Faster test feedback cycles
- Can course-correct quickly
- Clear commit history
- Easier to debug issues

---

## Test-Driven Development with AI

### Write Tests First (or Immediately After)

**The Pattern:**
```
1. AI implements feature
2. YOU write test outline (what to test)
3. AI implements tests
4. Run tests (usually fail first time)
5. AI fixes implementation
6. Tests pass
7. Review and commit
```

**Example workflow:**

```
Claude, implement ApplicationDetectionService with these methods:
- detectFramework(string $path): string
- detectPhpRequirements(string $framework): array

Then write comprehensive Pest tests covering:
- Laravel detection via composer.json
- WordPress detection via wp-config.php
- Static HTML fallback
- Missing directory handling
- Invalid path errors
- Edge cases (empty directories, permission errors)
```

**AI generates implementation + tests:**

```php
// app/Services/ApplicationDetectionService.php
class ApplicationDetectionService
{
    public function detectFramework(string $path): string
    {
        // Implementation
    }
}

// tests/Unit/Services/ApplicationDetectionServiceTest.php
it('detects Laravel via composer.json', function () {
    // Test implementation
});
```

**Run tests immediately:**
```bash
php artisan test --filter=ApplicationDetection

# Tests fail? AI fixes
# Tests pass? Review and commit
```

### Test Lifecycle Management

**Every feature change requires:**

```bash
# Before making changes
php artisan test --filter=RelatedFeature  # Ensure baseline passes

# After AI implements feature
php artisan test --filter=NewFeature      # Run new tests

# After fixes and review
php artisan test                          # Full suite

# Before commit
php artisan test && php artisan test      # Run twice (catch flaky tests)
```

**Test mutation (advanced quality check):**
```bash
# Verify tests actually catch bugs
php artisan test --mutate

# If mutations survive, tests need improvement
```

---

## Handling Refactors and Breaking Changes

### Plan Mode for Exploration

**Use plan mode for read-only analysis:**

```bash
# Start Claude in plan mode
claude --permission-mode plan
```

**Explore before committing:**
```
Please analyze the current SSH execution patterns across the codebase.

Find all uses of:
- executeAsRoot()
- executeAsUser()
- exec()

For each usage, determine:
1. Can it be converted to executeScript() heredoc pattern?
2. What are the risks?
3. What tests need updating?

Create migration plan but DO NOT make changes yet.
```

**Review plan, then execute:**
```
# Exit plan mode
# Start normal Claude session

Please implement the migration plan from docs/migration/ssh-execution-refactor.md

Work on one package at a time:
1. packages/netserva-cli first (has most usages)
2. Update tests for that package
3. Verify tests pass
4. Commit
5. Move to next package

Interrupt if anything looks risky.
```

### Specialized Sub-Agents

**For complex refactors, use multiple focused agents:**

**Agent 1: Laravel Specialist**
```
Focus: Update controllers, models, migrations
Constraints: Follow Laravel 12 conventions
Verify: With search-docs before changes
```

**Agent 2: Standards Enforcer**
```
Focus: Review against CLAUDE.md and ADRs
Constraints: Flag violations, suggest fixes
Verify: All changes match architectural guidelines
```

**Agent 3: Test Specialist**
```
Focus: Generate comprehensive test coverage
Constraints: Mock SSH, use factories
Verify: All tests pass, good coverage
```

**Benefits:**
- Each agent has clear responsibility
- Reduces context pollution
- Maintains focus on specific concerns
- Easier to review specialized changes

---

## Technical Debt Management

### Custom Debt Audit Command

**Create `.claude/commands/debt-audit.md`:**

```markdown
Perform technical debt audit of NetServa codebase.

Analyze for:

## Code Duplication
- Find repeated logic (>10 lines similar)
- Suggest extraction to services
- Prioritize by duplication count

## Missing Test Coverage
- Identify untested services
- Highlight untested edge cases
- List files with < 80% coverage

## Outdated Dependencies
- Check composer.json for old versions
- Flag security vulnerabilities
- Suggest upgrade path

## Performance Bottlenecks
- Identify N+1 queries
- Find missing indexes
- Highlight slow operations (> 100ms)

## Security Issues
- Find hardcoded credentials
- Identify SQL injection risks
- Check for CSRF vulnerabilities

Generate report: docs/technical-debt/audit-YYYYMMDD.md
Prioritize by: Critical → High → Medium → Low
Include: Remediation recommendations for top 10 issues
```

**Run quarterly:**
```bash
claude /debt-audit

# Review report
cat docs/technical-debt/audit-20251008.md

# Plan remediation sprints
```

### Tracking Debt Trends

**Compare audits over time:**
```bash
# Current audit
docs/technical-debt/audit-20251008.md

# Previous audit
docs/technical-debt/audit-20250708.md

# Trends to watch
- Total issues increasing/decreasing
- Test coverage improving/degrading
- New security issues
- Dependency freshness
```

---

## Context Management During Development

### Clear Between Features

```
✅ Feature complete → /clear → Start new feature
✅ Bug fixed → /clear → Next bug
✅ Tests passing → /clear → New implementation
```

### Maintain During Complex Work

```
❌ Don't clear during multi-file refactor
❌ Don't clear during debugging session
❌ Don't clear mid-implementation
```

### Recover from Context Loss

**If session crashes or context cleared:**

1. **Read implementation plan:**
```bash
cat docs/implementation/current-feature.md
```

2. **Check recent commits:**
```bash
git log --oneline -10
git diff HEAD~5
```

3. **Resume with focused prompt:**
```
Continuing VHost auto-configuration implementation.

Current progress (from docs/implementation/vhost-autoconfig.md):
- ✅ Phase 1: Detection service complete
- ✅ Phase 2: PHP version selector complete
- ⏳ Phase 3: SSH integration in progress

Next task: Integrate ApplicationDetectionService with RemoteExecutionService

Requirements:
- Use executeScript() heredoc pattern
- Mock SSH in tests
- Handle connection failures gracefully

Please continue implementation.
```

---

## Quality Assurance Checklist

### Before AI Implementation

- [ ] Design document created and reviewed
- [ ] Implementation plan generated and approved
- [ ] Existing tests passing (baseline)
- [ ] CLAUDE.md up to date with relevant conventions
- [ ] Related ADRs reviewed

### During Implementation

- [ ] Monitor AI progress actively
- [ ] Interrupt when direction seems wrong
- [ ] Provide specific corrections, not vague feedback
- [ ] Update implementation plan checkboxes
- [ ] Commit incrementally per phase

### After Implementation

- [ ] Review ALL changes through Git (not just AI diffs)
- [ ] Verify tests actually pass (run multiple times)
- [ ] Check Laravel Pint formatting applied
- [ ] Confirm no hardcoded credentials
- [ ] Validate database-first architecture followed
- [ ] Test on real environment (not just unit tests)
- [ ] Update CLAUDE.md if AI made repeated mistakes
- [ ] Create ADR for significant decisions

### Before Merge/Deploy

- [ ] Full test suite passes
- [ ] Integration tests pass
- [ ] No broken functionality in related features
- [ ] Documentation updated
- [ ] Implementation plan archived/completed
- [ ] Technical debt noted if any shortcuts taken

---

## Quick Reference

### Essential Commands

```bash
# Planning
npx repomix --output context.xml        # Create context snapshot
claude --permission-mode plan           # Explore without changes

# Development
php artisan test --filter=TestName      # Run specific tests
vendor/bin/pint --dirty                 # Format changed files
php artisan test --coverage             # Check coverage

# Review
git diff --staged                       # Review all changes
git add -p                              # Stage interactively
git log --oneline -10                   # Recent commits

# Context management
/clear                                  # Clear context between features
claude --continue                       # Resume last session
```

### File Locations

```
docs/design/           # Design documents (goals, constraints)
docs/implementation/   # Implementation plans (checkbox tracking)
docs/technical-debt/   # Debt audit reports
.claude/commands/      # Custom slash commands
```

---

**Next Steps:**
1. Review architecture/service-action-patterns.md for Laravel patterns
2. Check architecture/business-logic-documentation.md for domain docs
3. Explore .claude/commands/ for workflow automation
4. Read docs/adr/ for architectural decision process

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
