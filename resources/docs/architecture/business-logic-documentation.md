# Business Logic Documentation Standards

**How to document domain knowledge for NetServa 3.0 so AI understands your business rules**

---

## Overview

Business logic documentation captures domain knowledge that goes beyond code. This includes:

- Business rules and constraints
- State machines and workflows
- Domain-specific terminology
- Valid/invalid scenarios
- Decision rationale

**Purpose:** Enable AI to respect business logic when generating or modifying code

---

## Business Rules Catalog

### Template

**Location:** Create in `docs/business-rules/` or inline in model/service docblocks

```markdown
# Business Rules: {Domain Name}

## BR-{###}: {Rule Name}
**Rule:** {One-sentence statement of the rule}
**Rationale:** {Why this rule exists}
**Implementation:** {Where/how it's enforced in code}
**Related:** {Related business rules}

**Valid Examples:**
- {Example 1 of valid scenario}
- {Example 2 of valid scenario}

**Invalid Examples:**
- {Example 1 that violates rule}
- {Example 2 that violates rule}

**Tests:** {Path to test file}
**ADR:** {Link to architectural decision if applicable}
```

### NetServa Example: VHost Business Rules

```markdown
# Business Rules: Fleet VHost Management

## BR-001: Unique Domain Per VNode
**Rule:** Each domain can exist only once per vnode (server)
**Rationale:** Prevents DNS conflicts and nginx server_name collisions
**Implementation:** Database unique constraint on `(fleet_vnode_id, domain)` in `fleet_vhosts` table
**Related:** BR-003 (Cross-VNode Domains Allowed)

**Valid:**
- vnode `markc` + domain `example.com` → ✅ OK
- vnode `backup` + domain `example.com` → ✅ OK (different vnode)
- vnode `markc` + domain `test.example.com` → ✅ OK (different domain)

**Invalid:**
- vnode `markc` + domain `example.com` (already exists) → ❌ ERROR
- Attempting to create duplicate via CLI or Filament → ❌ Validation error

**Tests:** `tests/Feature/FleetVHost/UniqueDomainPerVNodeTest.php`
**Database:** `fleet_vhosts` table unique index `unique_domain_per_vnode (fleet_vnode_id, domain)`

---

## BR-002: VHost Requires Active VNode
**Rule:** Cannot create or modify vhosts on inactive or disabled vnodes
**Rationale:** Prevents deployments to unavailable infrastructure and configuration drift
**Implementation:** `FleetVHost::create()` and update operations check `vnode->is_active === true`
**Related:** BR-005 (VNode Health Checks), BR-012 (VHost Status Transitions)

**Valid:**
```php
$vnode = FleetVNode::where('is_active', true)->first();
$vhost = FleetVHost::create([
    'fleet_vnode_id' => $vnode->id,
    'domain' => 'example.com'
]);  // ✅ Success
```

**Invalid:**
```php
$vnode = FleetVNode::where('is_active', false)->first();
$vhost = FleetVHost::create([
    'fleet_vnode_id' => $vnode->id,
    'domain' => 'example.com'
]);  // ❌ Throws ActiveVNodeRequiredException
```

**Tests:** `tests/Feature/FleetVHost/ActiveVNodeRequiredTest.php`
**Implementation:** `app/Actions/FleetVHosts/CreateFleetVHostAction.php:32`

---

## BR-003: VConf 5-Character Naming
**Rule:** All vconf variable names MUST be exactly 5 characters
**Rationale:** Legacy NetServa 1.0/2.0 compatibility, bash variable convention, database column size
**Implementation:** Database `vconfs.name` VARCHAR(5), validation in VConf model
**Related:** BR-004 (VConf Categories), VHOST-VARIABLES.md for complete list

**Valid:**
- `VHOST` → ✅ Virtual host domain name
- `WPATH` → ✅ Web root path
- `DPASS` → ✅ Database password
- `DNAME` → ✅ Database name

**Invalid:**
- `WEB_PATH` → ❌ Too long (8 chars)
- `PATH` → ❌ Too short (4 chars)
- `vhost` → ❌ Lowercase (use VHOST)

**Tests:** `tests/Unit/Models/VConfTest.php::test_name_must_be_five_characters()`
**Reference:** `docs/VHOST-VARIABLES.md`

---

## BR-012: VHost Status State Machine
**Rule:** VHost status transitions follow defined state machine (see State Machines section)
**Rationale:** Prevents invalid state transitions and ensures data integrity
**Implementation:** `spatie/laravel-model-states` package, `app/States/VHostState/` directory
**Related:** BR-002 (Active VNode Required), BR-015 (Soft Delete Retention)

**Valid Transitions:**
- `pending` → `provisioning` → `active` (normal flow)
- `active` → `suspended` → `active` (suspension/reactivation)
- `failed` → `provisioning` (retry)
- `active` → `deleting` → `deleted` (deletion)

**Forbidden Transitions:**
- `deleted` → any status (irreversible)
- `active` → `pending` (cannot un-provision)
- `provisioning` → `suspended` (must complete first)

**Tests:** `tests/Feature/FleetVHost/StatusTransitionsTest.php`
**Implementation:** `app/States/VHostState/` state classes

---

## BR-015: Soft Delete 90-Day Retention
**Rule:** Deleted vhosts retain data for 90 days before permanent deletion
**Rationale:** Customer recovery window, compliance requirements, accidental deletion protection
**Implementation:** Laravel soft deletes, `deleted_at` timestamp, scheduled cleanup job
**Related:** BR-016 (VConf Cascade Delete), BR-017 (Backup Before Delete)

**Timeline:**
- Day 0: User deletes vhost → status='deleting' → soft delete
- Day 1-89: Recoverable via `FleetVHost::withTrashed()->restore()`
- Day 90: Permanent deletion via scheduled job

**Tests:** `tests/Feature/FleetVHost/SoftDeleteRetentionTest.php`
**Scheduled Job:** `app/Console/Commands/CleanupDeletedVHostsCommand.php`
```

---

## State Machine Documentation

### Purpose

Document complex workflows with explicit states and transitions to prevent AI from creating invalid state changes.

### Template

```markdown
# State Machine: {Feature Name}

## Valid States
- `state_name` - Description of what this state means
- `another_state` - When and why entity is in this state

## Valid Transitions

source_state → destination_state (trigger: what causes this)
source_state → alternate_state (error condition)

## Forbidden Transitions

state_a → state_b (reason why this is not allowed)

## Implementation
- Model: {Model class path}
- Package: {Package if using state machine library}
- Events: {Events dispatched on transitions}

## Business Rules
- {Related BR-### identifiers}

## Tests
- {Path to state transition tests}
```

### NetServa Example: VHost Provisioning

```markdown
# State Machine: VHost Provisioning Workflow

## Valid States

- `pending` - VHost created in database, not yet configured on server
- `provisioning` - Active configuration in progress (SSH operations running)
- `active` - Fully provisioned, nginx configured, SSL active, operational
- `suspended` - Temporarily disabled by admin (nginx config commented out)
- `failed` - Provisioning or operation failed, manual intervention needed
- `deleting` - Deletion initiated, cleanup in progress
- `deleted` - Soft deleted, recoverable for 90 days (BR-015)

## Valid Transitions

### Normal Provisioning Flow
pending → provisioning
- Trigger: Provisioning job started
- Action: Initiate SSH configuration
- Checks: VNode must be active (BR-002)

provisioning → active
- Trigger: All provisioning steps complete successfully
- Action: Dispatch VHostProvisioned event
- Verification: Nginx config valid, SSL certificate obtained

provisioning → failed
- Trigger: Any provisioning step fails
- Action: Log error, set failure reason
- Rollback: Database transaction reverted

### Suspension/Reactivation
active → suspended
- Trigger: Admin suspends vhost (manual action)
- Action: Comment out nginx config, reload nginx
- Reversible: Yes (can reactivate)

suspended → active
- Trigger: Admin reactivates
- Action: Uncomment nginx config, reload nginx

### Retry Failed Provisioning
failed → provisioning
- Trigger: Admin retries provisioning
- Action: Clear failure reason, restart provision job
- Note: May fail again if root cause not fixed

### Deletion Flow
active → deleting
suspended → deleting
failed → deleting
- Trigger: User deletes vhost
- Action: Initiate cleanup (remove nginx config, files)

deleting → deleted
- Trigger: Cleanup complete
- Action: Soft delete (set deleted_at timestamp)
- Retention: 90 days (BR-015)

## Forbidden Transitions

❌ deleted → any status
**Reason:** Soft delete is recoverable via restore(), but state must remain 'deleted'

❌ active → pending
**Reason:** Cannot "un-provision" - must delete and recreate

❌ provisioning → suspended
**Reason:** Must complete provisioning (success or failure) before suspension

❌ pending → active
**Reason:** Must go through provisioning steps, cannot skip

❌ deleted → active (without restore)
**Reason:** Must use restore() method, which handles state properly

## Implementation

**Package:** `spatie/laravel-model-states`
**Location:** `app/States/VHostState/`
**Base State:** `VHostState` abstract class
**States:**
- `Pending extends VHostState`
- `Provisioning extends VHostState`
- `Active extends VHostState`
- `Suspended extends VHostState`
- `Failed extends VHostState`
- `Deleting extends VHostState`
- `Deleted extends VHostState`

**Transitions:**
- `ToProvisioning` - pending → provisioning
- `ToActive` - provisioning → active
- `ToFailed` - provisioning → failed
- `ToSuspended` - active → suspended
- `ToDeleting` - active/suspended/failed → deleting
- `ToDeleted` - deleting → deleted

**Events Dispatched:**
- `VHostProvisioningStarted` - on → provisioning
- `VHostProvisioned` - on → active
- `VHostProvisioningFailed` - on → failed
- `VHostSuspended` - on → suspended
- `VHostReactivated` - on suspended → active
- `VHostDeleting` - on → deleting
- `VHostDeleted` - on → deleted (soft delete)

## Business Rules Referenced
- BR-001: Unique Domain Per VNode
- BR-002: Active VNode Required
- BR-012: Status State Machine (this document)
- BR-015: 90-Day Soft Delete Retention
- BR-017: Backup Before Delete

## Tests

**Unit Tests:**
- `tests/Unit/States/VHostStateTest.php` - State class tests

**Feature Tests:**
- `tests/Feature/FleetVHost/ProvisioningWorkflowTest.php` - Full provision flow
- `tests/Feature/FleetVHost/StatusTransitionsTest.php` - All valid transitions
- `tests/Feature/FleetVHost/ForbiddenTransitionsTest.php` - All forbidden transitions

**Example Test:**
```php
test('cannot transition from active to pending', function () {
    $vhost = FleetVHost::factory()->create(['status' => 'active']);

    expect(fn() => $vhost->status->transitionTo(Pending::class))
        ->toThrow(TransitionNotAllowed::class);
});
```
```

---

## Inline Code Documentation

### When Business Logic is in Code

**Use PHPDoc to reference business rules:**

```php
/**
 * Create new vhost on specified vnode
 *
 * Business Rules:
 * - BR-001: Domain must be unique per vnode
 * - BR-002: VNode must be active
 * - BR-003: VConf names must be 5 characters
 *
 * @param FleetVNode $vnode Server to create vhost on
 * @param string $domain Domain name (validated per BR-001)
 * @param array $options Additional configuration options
 * @return FleetVHost Created vhost in 'pending' status
 * @throws ActiveVNodeRequiredException if vnode inactive (BR-002)
 * @throws DuplicateDomainException if domain exists (BR-001)
 */
public function createVHost(FleetVNode $vnode, string $domain, array $options = []): FleetVHost
{
    // BR-002: Check vnode is active before creating vhost
    if (!$vnode->is_active) {
        throw new ActiveVNodeRequiredException(
            "Cannot create vhost on inactive vnode: {$vnode->name}"
        );
    }

    // BR-001: Check for duplicate domain on this vnode
    if ($this->domainExistsOnVNode($vnode, $domain)) {
        throw new DuplicateDomainException(
            "Domain {$domain} already exists on vnode {$vnode->name}"
        );
    }

    // Create vhost with initial pending status (BR-012 state machine)
    return FleetVHost::create([
        'fleet_vnode_id' => $vnode->id,
        'domain' => $domain,
        'status' => 'pending',  // BR-012: Initial state in provisioning workflow
    ]);
}
```

### Non-Obvious Business Logic

```php
/**
 * Calculate disk quota for vhost based on plan tier
 *
 * Business Rule BR-042: Quota Allocation
 * - Basic: 1GB (sufficient for static sites, small Laravel apps)
 * - Pro: 5GB (5x multiplier accounts for larger databases)
 * - Enterprise: 20GB (4x pro, not 20x basic - see ADR-0023)
 *
 * Note: Enterprise is 4x Pro (not 20x Basic) because Pro tier already
 * includes database overhead. Enterprise gets proportional increase.
 * See pricing model in ADR-0023 for justification.
 *
 * @param string $tier Plan tier (basic|pro|enterprise)
 * @return int Quota in bytes
 */
protected function calculateDiskQuota(string $tier): int
{
    return match($tier) {
        'basic' => 1 * 1024 ** 3,       // 1GB
        'pro' => 5 * 1024 ** 3,         // 5GB
        'enterprise' => 20 * 1024 ** 3,  // 20GB
        default => throw new \InvalidArgumentException("Unknown tier: {$tier}"),
    };
}
```

---

## Domain Terminology Glossary

### Purpose

Define project-specific terms that AI might not understand from context.

### NetServa Example

```markdown
# NetServa Domain Terminology

## Infrastructure Hierarchy

**venue** - Physical location or datacenter
- Example: `home-lab`, `sydney-dc`, `aws-ap-southeast-2`
- Purpose: Geographic organization of infrastructure

**vsite** - Logical grouping within venue
- Example: `production`, `staging`, `development`
- Purpose: Environment separation within same physical location

**vnode** - Virtual or physical server (SSH target)
- Example: `markc` (192.168.1.227), `backup`, `mail-server`
- Purpose: Actual server where vhosts are provisioned
- Note: "vnode" NOT "server" or "host" (historical NetServa term)

**vhost** - Virtual host, a domain/website on a vnode
- Example: `markc.goldcoast.org`, `example.com`
- Purpose: Individual website or service hosted on vnode
- Equivalent: Apache/Nginx virtual host

**vconf** - Configuration variable (5-char name)
- Example: `WPATH=/srv/example.com/web`, `DPASS=secretpass123`
- Purpose: Environment variables for vhost (54+ per vhost)
- Storage: Database table `vconfs`, NOT files

**vserv** - Service running for vhost
- Example: `nginx`, `php-fpm-8.4`, `postfix`, `dovecot`
- Purpose: Track which services are active for each vhost

## Command Terminology

**Positional args** - Required arguments in specific order
- Format: `command <vnode> <vhost> [options]`
- Example: `addvhost markc example.com --ssl`
- NOT: `addvhost --vnode=markc --vhost=example.com` (wrong)

**executeScript()** - Heredoc-based SSH execution pattern
- Purpose: Execute multi-line bash scripts on remote vnodes
- Alternative names: "heredoc pattern", "RemoteExecutionService pattern"
- Reference: docs/SSH_EXECUTION_ARCHITECTURE.md

## NetServa-Specific Conventions

**Database-first** - All configuration in database, NOT files
- Config stored: `vconfs` table with 54+ variables per vhost
- Files prohibited: No .env files, no config files on vnodes
- Rationale: Central management, version control, consistency

**Workstation pattern** - Execute FROM workstation TO vnodes via SSH
- All commands run: From `~/.ns/` on developer workstation
- Never: Copy scripts to remote vnodes and execute there
- Benefit: Central control, no script distribution needed
```

---

## Business Rule Documentation Checklist

### Essential Components

- [ ] **BR-### Identifier** - Unique, sequential numbering
- [ ] **One-sentence rule** - Clear statement of constraint
- [ ] **Rationale** - Why this rule exists
- [ ] **Implementation** - Where it's enforced (file:line if possible)
- [ ] **Valid examples** - At least 2 scenarios that work
- [ ] **Invalid examples** - At least 2 scenarios that fail
- [ ] **Test references** - Path to test file(s)
- [ ] **Related rules** - Links to other BR-### if connected

### Optional but Valuable

- [ ] ADR reference - Link to architectural decision
- [ ] Code examples - PHP/SQL showing implementation
- [ ] Database constraints - Schema enforcement
- [ ] Historical context - Why rule was added
- [ ] Exception cases - When rule doesn't apply
- [ ] Migration notes - How rule affects existing data

---

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
