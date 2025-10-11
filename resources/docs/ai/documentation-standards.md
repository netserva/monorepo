# Documentation Standards for AI-Assisted Development

**How to write documentation that Claude Code actually understands and uses effectively**

---

## Core Principle

**AI-readable documentation != Human-readable documentation**

**Human preference:** Narrative paragraphs with context and explanation
**AI optimization:** Concise bullet points with specific examples

**Goal:** Maximum information density with minimum token consumption

---

## CLAUDE.md Writing Standards

### Critical Constraints

**100-200 lines maximum**
- Content prepended to EVERY prompt in session
- 200 lines ≈ 1,000-2,000 tokens per request
- Multiply by 50 requests in active session = 50K-100K tokens consumed

**Ruthlessly specific**
- Generic advice wastes tokens and confuses AI
- Specific examples create clear expectations
- Project-specific patterns prevent hallucination

### Structure Template

```markdown
# Project Name

## Tech Stack
Framework: Laravel 12
Admin Panel: Filament 4.1
Database: MySQL 8.0 (prod) / SQLite (dev)
Testing: Pest 4.0
PHP: 8.4

## Critical Architecture Rules
1. Database-first: ALL vhost config in `vconfs` table - NEVER files
2. Remote SSH: Use `RemoteExecutionService::executeScript()` heredoc pattern
3. CLI conventions: `<command> <vnode> <vhost> [options]` - NO flags
4. Execution pattern: FROM workstation TO servers - NEVER copy scripts

## Essential Commands
php artisan fleet:discover --vnode=markc
php artisan test --filter=TestName
vendor/bin/pint --dirty

## Do NOT
- Never hardcode credentials (use vconfs table)
- Never skip tests (100% coverage mandatory)
- Never use file-based config (database only)

## Testing
- Pest 4.0 required for all features
- Mock SSH connections (use RemoteExecutionService fake)
- Test CLI + web interfaces
- Run tests before every commit
```

### Effective vs Ineffective Examples

#### ❌ Generic (Wastes tokens, doesn't guide behavior)

```markdown
## Code Style
- Write clean, maintainable code
- Follow best practices for Laravel
- Use consistent formatting
- Add helpful comments
- Keep functions small and focused
```

**Problems:**
- No specific guidance (what is "clean"?)
- AI already knows generic best practices
- Doesn't prevent project-specific mistakes

#### ✅ Specific (Guides AI behavior, prevents errors)

```markdown
## Code Style
Run `vendor/bin/pint --dirty` before commits (Laravel Pint)
Use PHP 8 constructor promotion: `public function __construct(public GitHub $github) {}`
Return types required: `protected function isAccessible(User $user): bool`
NO empty `__construct()` methods with zero parameters
```

**Benefits:**
- Exact expectations with examples
- References tools that exist in project
- Specific anti-patterns to avoid
- AI can verify compliance

#### ❌ Redundant (States the obvious)

```markdown
## Directory Structure
app/Models/          # Contains Eloquent models
app/Services/        # Contains service classes
app/Console/         # Contains Artisan commands
packages/            # Contains plugin packages
```

**Problems:**
- Directory names are self-explanatory
- Wastes precious token budget
- Doesn't add value

#### ✅ Non-obvious (Highlights special conventions)

```markdown
## Directory Structure
packages/netserva-*/tests/   # Plugin tests (NOT root tests/)
app/Filament/Schemas/        # Filament v4 schemas (moved from Components/)
resources/docs/private/      # Gitignored real configs (NOT committed)
```

**Benefits:**
- Highlights project-specific patterns
- Prevents mistakes (where to put tests?)
- Points to recent changes (Filament v4 migration)

---

## Business Logic Documentation

### Business Rules Catalog

**Location:** `docs/business-rules/` (or inline in model/service docblocks)
**Purpose:** Encode domain knowledge that AI must respect

**Template:**

```markdown
# Business Rules: Fleet VHost Management

## BR-001: Unique Domain Per VNode
**Rule:** Each domain can exist only once per vnode
**Rationale:** DNS conflicts, nginx server_name collisions
**Implementation:** `fleet_vhosts` unique constraint `(vnode_id, domain)`
**Related:** BR-003 (Cross-VNode Domains)

**Valid:**
- vnode `markc` + domain `example.com` → OK
- vnode `backup` + domain `example.com` → OK (different vnode)

**Invalid:**
- vnode `markc` + domain `example.com` + duplicate → ERROR

**Tests:** `tests/Feature/FleetVHost/UniqueDomainPerVNodeTest.php`

## BR-002: VHost Requires Active VNode
**Rule:** Cannot create vhost on inactive or disabled vnode
**Rationale:** Prevents deployments to unavailable infrastructure
**Implementation:** `FleetVHost::create()` checks `vnode->is_active === true`
**Related:** BR-005 (VNode Health Checks)

**Valid:**
```php
$vnode = FleetVNode::where('is_active', true)->first();
$vhost = FleetVHost::create(['vnode_id' => $vnode->id, ...]);
```

**Invalid:**
```php
$vnode = FleetVNode::where('is_active', false)->first();
$vhost = FleetVHost::create(['vnode_id' => $vnode->id, ...]);  // Exception
```

**Tests:** `tests/Feature/FleetVHost/ActiveVNodeRequiredTest.php`

## BR-003: Email Domain Validation
**Rule:** Email domains must have valid MX records OR be in local domains list
**Rationale:** Prevent typos in customer email addresses
**Implementation:** `EmailDomainValidator` service
**Related:** BR-008 (DNS Verification)

**Valid:**
- `user@gmail.com` → MX records exist
- `user@localtest.me` → In allowed_local_domains config

**Invalid:**
- `user@gmial.com` → Typo, no MX records
- `user@localhost` → Not in allowed list

**Tests:** `tests/Unit/Validators/EmailDomainValidatorTest.php`
```

### State Machine Documentation

**For complex workflows with state transitions:**

```markdown
# State Machine: VHost Provisioning

## Valid States
- `pending` - Created, not yet provisioned
- `provisioning` - Actively being configured on vnode
- `active` - Fully configured and operational
- `suspended` - Temporarily disabled
- `failed` - Provisioning error occurred
- `deleting` - Marked for deletion
- `deleted` - Soft deleted (retention period)

## Valid Transitions

pending → provisioning (auto, when provision job starts)
pending → failed (if validation fails before provisioning)

provisioning → active (successful provision)
provisioning → failed (provision error)

active → suspended (manual admin action)
active → deleting (deletion requested)

suspended → active (reactivation)
suspended → deleting (deletion while suspended)

failed → provisioning (retry provision)
failed → deleting (abandon failed vhost)

deleting → deleted (deletion confirmed)

## Forbidden Transitions

deleted → * (irreversible)
active → pending (cannot un-provision)
provisioning → suspended (must complete or fail first)

## Implementation
`app/Models/FleetVHost.php` - Uses `spatie/laravel-model-states`
Transitions enforced by `FleetVHostStateMachine` class

## Tests
`tests/Feature/FleetVHost/StateTransitionsTest.php`
- Tests all valid transitions
- Tests forbidden transitions raise exceptions
- Tests state-specific behaviors
```

### Decision Rationale (Inline)

**When code has non-obvious business logic:**

```php
/**
 * Calculate vhost disk quota based on plan tier.
 *
 * Business Rule BR-012: Quota Multipliers
 * - Basic tier: 1GB base
 * - Pro tier: 5GB base (5x multiplier due to larger databases)
 * - Enterprise: 20GB base (4x pro, not 20x basic, see ADR-0023)
 *
 * Note: Enterprise multiplier is 4x pro (not 20x basic) because pro tier
 * already includes database overhead. See pricing model in ADR-0023.
 *
 * @param string $tier Plan tier (basic|pro|enterprise)
 * @return int Quota in bytes
 */
protected function calculateDiskQuota(string $tier): int
{
    return match($tier) {
        'basic' => 1 * 1024 * 1024 * 1024,      // 1GB
        'pro' => 5 * 1024 * 1024 * 1024,        // 5GB
        'enterprise' => 20 * 1024 * 1024 * 1024, // 20GB
    };
}
```

**Benefits:**
- AI understands **why** not just **what**
- Prevents "helpful" refactoring that breaks business rules
- Links to ADR for deeper context
- Future developers understand reasoning

---

## Model Documentation

### Relationship Documentation

```php
/**
 * VHost Configuration Variables (54+ per vhost)
 *
 * Each vconf is a separate row with:
 * - name: 5-char variable (WPATH, DPASS, etc.)
 * - value: Variable value
 * - category: paths|credentials|settings|ssl|mail|dns
 * - is_sensitive: Password masking in display
 *
 * Access via: $vhost->vconf('WPATH')
 * Set via: $vhost->setVconf('WPATH', '/srv/example.com/web')
 *
 * See: docs/VHOST-VARIABLES.md for complete list
 */
public function vconfs(): HasMany
{
    return $this->hasMany(VConf::class, 'fleet_vhost_id');
}
```

### Scope Documentation

```php
/**
 * Scope: Active VHosts Only
 *
 * Excludes soft-deleted and suspended vhosts.
 * Use `withInactive()` to include suspended.
 * Use `withTrashed()` to include soft-deleted.
 *
 * Example:
 * FleetVHost::active()->get()  // Only active
 * FleetVHost::withInactive()->get()  // Active + suspended
 * FleetVHost::withTrashed()->get()  // Everything including deleted
 */
public function scopeActive(Builder $query): void
{
    $query->where('status', 'active');
}
```

---

## Service Class Documentation

### Service Purpose and Responsibilities

```php
/**
 * Remote Execution Service
 *
 * Executes bash scripts on remote VNodes via SSH.
 *
 * RESPONSIBILITIES:
 * - Manage SSH connections to vnodes
 * - Execute bash scripts using heredoc pattern
 * - Inject environment variables from vconfs
 * - Handle errors and return structured results
 *
 * NOT RESPONSIBLE FOR:
 * - Business logic (belongs in actions/services)
 * - Config generation (use config services)
 * - Database operations (use repositories)
 *
 * USAGE:
 * // Simple script execution
 * $result = $this->remoteExecution->executeScript(
 *     host: 'markc',
 *     script: <<<'BASH'
 *         mkdir -p /tmp/test
 *         echo "Success"
 *     BASH
 * );
 *
 * // With VHost environment variables
 * $result = $this->remoteExecution->executeScriptWithVhost(
 *     host: 'markc',
 *     vhost: $vhost,
 *     script: <<<'BASH'
 *         cd "$WPATH"  # Environment vars auto-injected
 *         echo "Web path: $WPATH"
 *     BASH
 * );
 *
 * See: docs/SSH_EXECUTION_ARCHITECTURE.md
 */
class RemoteExecutionService
{
    // ...
}
```

---

## Testing Documentation

### Test Suite Organization

```php
/**
 * FleetVHost Feature Tests
 *
 * COVERAGE:
 * - CRUD operations via Filament resources
 * - CLI commands (addvhost, chvhost, delvhost, shvhost)
 * - VConf management
 * - State transitions
 * - Business rule enforcement
 *
 * MOCKING:
 * - SSH connections ALWAYS mocked (use RemoteExecutionService::fake())
 * - External APIs mocked (DNS, ACME)
 * - Filesystem operations use temp directories
 *
 * PATTERNS:
 * - Use FleetVHost::factory() for test data
 * - Use RefreshDatabase trait
 * - Test both happy paths AND error conditions
 * - Verify database state after operations
 */
```

---

## Documentation Anti-Patterns

### ❌ Explaining Laravel Framework Features

**Don't:**
```markdown
## Eloquent ORM
Laravel's Eloquent ORM provides an ActiveRecord implementation for working with databases.
Each database table has a corresponding Model for interacting with that table.
Models allow you to query data and insert new records.
```

**Why not:**
- AI already knows Laravel fundamentals
- Wastes token budget
- Doesn't help with project-specific patterns

### ❌ Tutorial-Style Explanations

**Don't:**
```markdown
## Creating a New Command

First, you'll want to use the artisan command to generate a new command file:
1. Run `php artisan make:command YourCommand`
2. Open the generated file in your editor
3. Update the signature and description
4. Implement the handle() method
5. Test your command by running it
```

**Why not:**
- AI knows how to create Laravel commands
- Step-by-step not needed for AI
- Focus on PROJECT-SPECIFIC conventions instead

**Do:**
```markdown
## CLI Command Conventions
ALL commands: `<command> <vnode> <vhost> [options]` (positional args, NO --flags)
Signature: `addvhost {vnode} {vhost} {--option=}`
Use Laravel Prompts for interactive confirmations
Call RemoteExecutionService for SSH operations
MUST include Pest tests in packages/*/tests/
```

### ❌ Copy-Pasted Framework Documentation

**Don't:**
```markdown
## Filament Forms

Filament forms are built using a fluent API. You can add fields like this:
Forms\Components\TextInput::make('name')
    ->required()
    ->maxLength(255)
```

**Why not:**
- AI has access to Filament docs via Laravel Boost
- Should use `search-docs` instead
- CLAUDE.md should focus on YOUR conventions

**Do:**
```markdown
## Filament Resource Patterns
Resources in `app/Filament/Resources/` auto-discovered
Use `search-docs` BEFORE implementing Filament features
Schemas in `Schemas/` subdirectory (Filament v4.1)
ALWAYS include authorization via policies
Test with livewire() assertions (see testing_strategy.md)
```

---

## Inline Code Documentation

### When to Add Comments

**DO add comments for:**
- Non-obvious business rules
- Complex algorithms
- Performance optimizations
- Workarounds for external bugs
- Security-critical sections
- State machine transitions
- Magic numbers with business meaning

**DON'T add comments for:**
- Self-explanatory code
- Method names that describe behavior
- Standard Laravel patterns
- Obvious variable assignments

### Examples

**❌ Unnecessary:**
```php
// Get the user
$user = User::find($id);

// Check if user is admin
if ($user->isAdmin()) {
    // Return admin dashboard
    return view('admin.dashboard');
}
```

**✅ Valuable:**
```php
// BR-015: Only pro+ tiers can create more than 5 vhosts per vnode
// Basic tier users hit limit and must upgrade
if ($vnode->vhosts()->count() >= 5 && $user->tier === 'basic') {
    throw new VHostLimitExceededException(
        'Basic tier limited to 5 vhosts per vnode. Upgrade to Pro for unlimited.'
    );
}

// Performance: Eager load vconfs to avoid N+1
// Expected: 1 query for vhosts + 1 query for all vconfs
// Without: 1 query for vhosts + N queries for each vhost's vconfs
$vhosts = FleetVHost::with('vconfs')->get();
```

---

## Documentation Checklist

### Root CLAUDE.md
- [ ] Under 200 lines total
- [ ] Tech stack with versions
- [ ] Critical architecture decisions
- [ ] Mandatory conventions
- [ ] Essential commands
- [ ] Clear "Do NOT" section
- [ ] Laravel Boost usage guidelines

### Subsystem CLAUDE.md
- [ ] Subsystem-specific patterns
- [ ] Integration points
- [ ] Testing strategies
- [ ] Known limitations
- [ ] References to detailed docs

### Business Rules
- [ ] BR-### identifier
- [ ] Rule statement (what)
- [ ] Rationale (why)
- [ ] Implementation (where/how)
- [ ] Valid examples
- [ ] Invalid examples
- [ ] Test references

### Model Documentation
- [ ] PHPDoc for relationships
- [ ] Scope documentation
- [ ] Business rule references
- [ ] Array shape type hints
- [ ] Accessor/mutator explanations

### Service Documentation
- [ ] Responsibilities
- [ ] NOT responsible for (boundaries)
- [ ] Usage examples
- [ ] Dependencies
- [ ] References to architecture docs

---

**Next Steps:**
1. Audit existing CLAUDE.md files against these standards
2. Create business rules catalog in docs/business-rules/
3. Add relationship documentation to models
4. Review proven-workflows.md for development patterns

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
