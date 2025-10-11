# NetServa Core Package - Context

**Core models, services, and database architecture for NetServa 3.0**

---

## Package Purpose

`netserva-core` provides foundation functionality:
- Core models (FleetVHost, FleetVNode, VConf)
- Database migrations
- Base service provider
- Shared interfaces and traits

**All other plugins depend on netserva-core**

---

## Model Patterns

### Database-First Architecture (ADR-0001)

**CRITICAL:** ALL configuration in database, NEVER files

```php
// ✅ CORRECT: Read from vconfs table
$webPath = $vhost->vconf('WPATH');

// ❌ WRONG: Read from file
$webPath = file_get_contents('/srv/.env');
```

### VConf Pattern

**Every vhost has 54+ configuration variables as separate rows:**

```php
// Create/update vconf
$vhost->setVconf('WPATH', '/srv/example.com/web');

// Read vconf
$webPath = $vhost->vconf('WPATH');  // Returns string or null

// Get all vconfs
$allVconfs = $vhost->vconfs; // HasMany relationship
```

**Naming:** Exactly 5 uppercase characters (BR-003)
- Examples: `VHOST`, `WPATH`, `DPASS`, `UUSER`
- Categories: paths, credentials, settings, ssl, mail, dns

### Model Conventions

**Keep models thin:**
```php
class FleetVHost extends Model
{
    // ✅ DO: Relationships, scopes, accessors
    public function vnode(): BelongsTo { }
    public function scopeActive(Builder $query): void { }
    public function getWebPathAttribute(): string { }

    // ❌ DON'T: Business logic, SSH, complex operations
    // Use services for business logic
}
```

**Use factories for tests:**
```php
FleetVHost::factory()->create(['status' => 'active']);
VConf::factory()->create(['name' => 'WPATH', 'value' => '/srv/web']);
```

---

## Service Layer Patterns

**Location:** `packages/netserva-core/src/Services/`

**Pattern:** Inject dependencies, return structured results

```php
class VHostManagementService
{
    public function __construct(
        protected RemoteExecutionService $remoteExecution
    ) {}

    public function performAction(FleetVHost $vhost): Result
    {
        // Business logic here
        return Result::success($vhost, 'Action completed');
    }
}
```

**DO NOT:**
- Include presentation logic (views, JSON formatting)
- Execute direct database queries (use models)
- Handle HTTP requests (use controllers)

---

## Relationship Patterns

### Standard Relationships

```php
// FleetVHost → FleetVNode (many to one)
public function vnode(): BelongsTo
{
    return $this->belongsTo(FleetVNode::class, 'fleet_vnode_id');
}

// FleetVHost → VConf (one to many)
public function vconfs(): HasMany
{
    return $this->hasMany(VConf::class, 'fleet_vhost_id');
}

// Eager loading (prevent N+1)
$vhosts = FleetVHost::with('vnode', 'vconfs')->get();
```

### Query Scopes

```php
// Scope: Active vhosts only
public function scopeActive(Builder $query): void
{
    $query->where('status', 'active');
}

// Usage
FleetVHost::active()->get();
```

---

## Migration Conventions

**Follow Laravel 12 standards:**
```php
Schema::create('fleet_vhosts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fleet_vnode_id')->constrained('fleet_vnodes');
    $table->string('domain');
    $table->string('status')->default('pending');

    // BR-001: Unique domain per vnode
    $table->unique(['fleet_vnode_id', 'domain'], 'unique_domain_per_vnode');

    $table->timestamps();
    $table->softDeletes();  // BR-015: 90-day retention
});
```

**When modifying columns:**
- Include ALL previous attributes (or they'll be dropped)
- Use separate migration for each logical change
- Test rollback works correctly

---

## Testing Core Models

**Location:** `packages/netserva-core/tests/Unit/Models/`

```php
describe('FleetVHost', function () {
    it('belongs to vnode', function () {
        $vnode = FleetVNode::factory()->create();
        $vhost = FleetVHost::factory()->create([
            'fleet_vnode_id' => $vnode->id
        ]);

        expect($vhost->vnode)->toBeInstanceOf(FleetVNode::class);
    });

    it('has many vconfs', function () {
        $vhost = FleetVHost::factory()->create();
        VConf::factory()->count(5)->create([
            'fleet_vhost_id' => $vhost->id
        ]);

        expect($vhost->vconfs)->toHaveCount(5);
    });

    it('filters active vhosts (scope)', function () {
        FleetVHost::factory()->create(['status' => 'active']);
        FleetVHost::factory()->create(['status' => 'suspended']);

        expect(FleetVHost::active()->count())->toBe(1);
    });
});
```

---

## Business Rules Enforced

**Core package enforces these business rules:**

- **BR-001:** Unique domain per vnode (database constraint)
- **BR-002:** Active vnode required (validated in actions)
- **BR-003:** VConf 5-character naming (database column, model validation)
- **BR-015:** 90-day soft delete retention (SoftDeletes trait)

**Reference:** `docs/business-rules/`

---

## Common Patterns

### Get VHost by Domain and VNode

```php
$vhost = FleetVHost::where('domain', $domain)
    ->whereHas('vnode', fn($q) => $q->where('name', $vnodeName))
    ->firstOrFail();
```

### Initialize Default VConfs

```php
// After creating vhost
$defaults = config('netserva.vconf_defaults');

foreach ($defaults as $name => $config) {
    VConf::create([
        'fleet_vhost_id' => $vhost->id,
        'name' => $name,
        'value' => $config['default'],
        'category' => $config['category'],
        'is_sensitive' => $config['is_sensitive'] ?? false,
    ]);
}
```

### Safe VConf Access

```php
// Returns null if not found (safe)
$webPath = $vhost->vconf('WPATH') ?? '/default/path';

// Throw exception if missing (strict)
$webPath = $vhost->vconf('WPATH')
    ?? throw new \RuntimeException('WPATH not configured');
```

---

## Package Development

**Service Provider:** `packages/netserva-core/src/Providers/NetServaCoreServiceProvider.php`

```php
class NetServaCoreServiceProvider extends BaseNsServiceProvider
{
    public function boot(): void
    {
        // Load migrations automatically
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Command classes
            ]);
        }
    }
}
```

**Auto-discovery:** Service provider auto-registered via `composer.json`

---

**Complete documentation:** `resources/docs/architecture/service-action-patterns.md`

**Version:** 1.0.0 (2025-10-08)
