# Service Layer and Action Patterns for Laravel

**NetServa 3.0 architectural patterns for maintaining thin controllers and organized business logic**

---

## Architectural Philosophy

**Core Principle:** Separate business logic from presentation and data access layers

**Three-Layer Architecture:**

```
┌─────────────────────────────────────┐
│  Presentation Layer                 │
│  - Filament Resources               │
│  - CLI Commands                     │
│  - Controllers (minimal)            │
│  - Form Requests (validation)       │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Business Logic Layer               │
│  - Service Classes                  │
│  - Action Classes                   │
│  - Business Rules                   │
│  - Event Handlers                   │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│  Data Access Layer                  │
│  - Models (relationships)           │
│  - Repositories (if complex)        │
│  - Database Queries                 │
│  - External API clients             │
└─────────────────────────────────────┘
```

**Benefits:**
- **Testability** - Business logic isolated and easily testable
- **Reusability** - Same logic works in CLI + web + API
- **Maintainability** - Clear boundaries and responsibilities
- **Scalability** - Easy to refactor and optimize

---

## Service Layer Pattern

### When to Use Services

**Use services for:**
- Complex workflows with multiple steps
- Operations coordinating multiple actions
- Business logic used in multiple contexts (CLI + web)
- Transaction management across multiple models
- Event dispatching and orchestration

**Example: VHost Provisioning Service**

Complex workflow requiring:
- VHost creation in database
- Environment variable initialization (54 vconfs)
- Remote server configuration via SSH
- Nginx config generation and reload
- SSL certificate request
- Verification and rollback on failure

### Service Structure

**Location:** `app/Services/` (core) or `packages/plugin-name/src/Services/` (plugins)

**Naming:** `{Domain}Service.php` (e.g., `VHostProvisioningService`, `ApplicationDetectionService`)

**Template:**

```php
<?php

namespace App\Services;

use App\Models\FleetVHost;
use App\Models\VConf;
use App\Events\VHostProvisioned;
use NetServa\Cli\Services\RemoteExecutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VHost Provisioning Service
 *
 * Handles complete vhost provisioning workflow including:
 * - Database record creation
 * - Environment variable initialization
 * - Remote server configuration
 * - Nginx setup and SSL certificates
 *
 * RESPONSIBILITIES:
 * - Coordinate multiple actions (creation, config, verification)
 * - Manage transactions (rollback on failure)
 * - Dispatch events (VHostProvisioned, VHostFailed)
 * - Handle logging and error reporting
 *
 * NOT RESPONSIBLE FOR:
 * - Direct database queries (use models)
 * - SSH script execution (use RemoteExecutionService)
 * - Config template rendering (use ConfigService)
 * - Certificate management (use AcmeService)
 *
 * USAGE:
 * ```php
 * $service = app(VHostProvisioningService::class);
 * $result = $service->provision(
 *     vnode: $vnode,
 *     domain: 'example.com',
 *     options: ['php_version' => '8.4', 'ssl' => true]
 * );
 * ```
 */
class VHostProvisioningService
{
    public function __construct(
        protected RemoteExecutionService $remoteExecution,
        protected ConfigGenerationService $configService,
        protected AcmeService $acmeService,
    ) {}

    /**
     * Provision new vhost with complete configuration
     *
     * @param FleetVNode $vnode Server to provision on
     * @param string $domain Domain name for vhost
     * @param array $options Configuration options (php_version, ssl, etc.)
     * @return ProvisioningResult Success/failure with details
     * @throws ProvisioningException On critical failures
     */
    public function provision(
        FleetVNode $vnode,
        string $domain,
        array $options = []
    ): ProvisioningResult {
        Log::info('Starting vhost provisioning', [
            'vnode' => $vnode->name,
            'domain' => $domain,
            'options' => $options,
        ]);

        return DB::transaction(function () use ($vnode, $domain, $options) {
            try {
                // Step 1: Create database record
                $vhost = $this->createVHost($vnode, $domain, $options);

                // Step 2: Initialize environment variables
                $this->initializeVConfs($vhost, $options);

                // Step 3: Configure remote server
                $this->configureRemoteServer($vhost);

                // Step 4: Request SSL certificate (if enabled)
                if ($options['ssl'] ?? true) {
                    $this->requestSslCertificate($vhost);
                }

                // Step 5: Verify configuration
                $this->verifyProvisioning($vhost);

                // Step 6: Mark as active
                $vhost->update(['status' => 'active']);

                // Step 7: Dispatch event
                event(new VHostProvisioned($vhost));

                Log::info('VHost provisioned successfully', ['vhost_id' => $vhost->id]);

                return ProvisioningResult::success($vhost, 'VHost provisioned successfully');

            } catch (\Exception $e) {
                Log::error('VHost provisioning failed', [
                    'error' => $e->getMessage(),
                    'vnode' => $vnode->name,
                    'domain' => $domain,
                ]);

                // Rollback happens automatically via DB::transaction
                throw new ProvisioningException(
                    "Failed to provision {$domain}: {$e->getMessage()}",
                    previous: $e
                );
            }
        });
    }

    /**
     * Create VHost database record with initial configuration
     */
    protected function createVHost(
        FleetVNode $vnode,
        string $domain,
        array $options
    ): FleetVHost {
        return FleetVHost::create([
            'fleet_vnode_id' => $vnode->id,
            'domain' => $domain,
            'status' => 'provisioning',
            'php_version' => $options['php_version'] ?? '8.4',
            'web_root' => $options['web_root'] ?? "/srv/{$domain}/web",
        ]);
    }

    /**
     * Initialize 54 environment variables in vconfs table
     */
    protected function initializeVConfs(FleetVHost $vhost, array $options): void
    {
        $defaults = config('netserva.vconf_defaults');

        foreach ($defaults as $name => $config) {
            VConf::create([
                'fleet_vhost_id' => $vhost->id,
                'name' => $name,
                'value' => $options[$name] ?? $config['default'],
                'category' => $config['category'],
                'is_sensitive' => $config['is_sensitive'] ?? false,
            ]);
        }
    }

    /**
     * Configure nginx, PHP-FPM on remote server
     */
    protected function configureRemoteServer(FleetVHost $vhost): void
    {
        // Generate nginx config
        $nginxConfig = $this->configService->generateNginxConfig($vhost);

        // Execute configuration script on remote server
        $result = $this->remoteExecution->executeScriptWithVhost(
            host: $vhost->vnode->name,
            vhost: $vhost,
            script: <<<'BASH'
                #!/bin/bash
                set -euo pipefail

                # Create directory structure
                mkdir -p "$WPATH" "$UPATH/log"
                chown "$UUSER:www-data" "$WPATH"

                # Write nginx config
                cat > "/etc/nginx/sites-available/$VHOST" <<'NGINX_EOF'
                {$nginxConfig}
                NGINX_EOF

                # Enable site
                ln -sf "/etc/nginx/sites-available/$VHOST" "/etc/nginx/sites-enabled/"

                # Test and reload nginx
                nginx -t && systemctl reload nginx

                echo "Server configured successfully"
                BASH,
            asRoot: true
        );

        if (!$result['success']) {
            throw new \RuntimeException("Remote configuration failed: {$result['error']}");
        }
    }

    // Additional methods: requestSslCertificate(), verifyProvisioning(), etc.
}
```

### Service Best Practices

**DO:**
- Inject dependencies via constructor (use DI container)
- Return structured result objects (not just bool)
- Use database transactions for multi-step operations
- Log important steps and errors
- Dispatch events for side effects
- Throw specific exceptions (not generic Exception)
- Document responsibilities clearly in PHPDoc

**DON'T:**
- Execute direct database queries (use models)
- Include presentation logic (views, JSON formatting)
- Handle HTTP requests directly (use controllers)
- Mix multiple domain concerns in one service
- Create services for simple CRUD (use models directly)

---

## Action Pattern

### When to Use Actions

**Use actions for:**
- Single-responsibility operations
- Reusable business logic chunks
- Steps within service workflows
- Simple operations that need testing
- Clear, focused responsibilities

**Example: Create Customer Action**

Single focused operation:
- Validate customer data
- Create database record
- Send welcome email
- Return created customer

### Action Structure

**Location:** `app/Actions/` organized by domain: `Actions/Customers/`, `Actions/Orders/`

**Naming:** `{Verb}{Noun}Action.php` (e.g., `CreateCustomerAction`, `ProcessOrderAction`)

**Template:**

```php
<?php

namespace App\Actions\FleetVHosts;

use App\Models\FleetVHost;
use App\Models\FleetVNode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create FleetVHost Action
 *
 * Single responsibility: Create VHost database record with validation
 *
 * DOES:
 * - Validate input data
 * - Check for duplicate domains on vnode
 * - Create VHost record
 * - Return created model
 *
 * DOES NOT:
 * - Configure remote server (use VHostProvisioningService)
 * - Initialize vconfs (use InitializeVConfsAction)
 * - Send notifications (caller's responsibility)
 */
class CreateFleetVHostAction
{
    /**
     * Execute the action
     *
     * @param FleetVNode $vnode Server for this vhost
     * @param string $domain Domain name
     * @param array $data Additional configuration
     * @return FleetVHost Created vhost record
     * @throws ValidationException If validation fails
     */
    public function execute(FleetVNode $vnode, string $domain, array $data = []): FleetVHost
    {
        // Validate input
        $validated = Validator::make(
            array_merge(['domain' => $domain], $data),
            [
                'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-\.]+$/'],
                'php_version' => ['nullable', 'string', 'in:8.1,8.2,8.3,8.4'],
                'web_root' => ['nullable', 'string', 'max:500'],
            ],
            [
                'domain.regex' => 'Domain must contain only lowercase letters, numbers, dots, and hyphens',
            ]
        )->validate();

        // Business rule: Check for duplicate domain on this vnode
        if ($this->domainExistsOnVNode($vnode, $domain)) {
            throw ValidationException::withMessages([
                'domain' => ["Domain {$domain} already exists on vnode {$vnode->name}"]
            ]);
        }

        // Create VHost record
        return FleetVHost::create([
            'fleet_vnode_id' => $vnode->id,
            'domain' => $validated['domain'],
            'php_version' => $validated['php_version'] ?? '8.4',
            'web_root' => $validated['web_root'] ?? "/srv/{$domain}/web",
            'status' => 'pending',
        ]);
    }

    /**
     * Check if domain already exists on vnode
     */
    protected function domainExistsOnVNode(FleetVNode $vnode, string $domain): bool
    {
        return FleetVHost::where('fleet_vnode_id', $vnode->id)
            ->where('domain', $domain)
            ->exists();
    }
}
```

### Action Best Practices

**DO:**
- One public `execute()` method
- Clear method signature with types
- Return useful data (model, DTO, result object)
- Validate input explicitly
- Document what action DOES and DOES NOT do
- Keep focused on single operation

**DON'T:**
- Orchestrate multiple complex operations (use service)
- Handle HTTP requests (use controllers)
- Query multiple unrelated models
- Include side effects without documenting
- Make actions stateful (should be pure operations)

---

## Services vs Actions Decision Guide

```
┌─────────────────────────────────────────────────┐
│ Need to orchestrate multiple steps?             │
│   YES → Service                                  │
│   NO  → Continue                                 │
└─────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────┐
│ Need database transactions?                     │
│   YES → Service                                  │
│   NO  → Continue                                 │
└─────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────┐
│ Dispatching events or coordinating side effects?│
│   YES → Service                                  │
│   NO  → Continue                                 │
└─────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────┐
│ Single-focused operation?                       │
│   YES → Action                                   │
│   NO  → Reconsider architecture                 │
└─────────────────────────────────────────────────┘
```

**Examples:**

| Scenario | Pattern | Reasoning |
|----------|---------|-----------|
| Create customer with welcome email | Action | Single operation, clear responsibility |
| Complete order checkout (payment, inventory, shipping) | Service | Multiple coordinated steps with transaction |
| Send password reset email | Action | Focused single task |
| Provision vhost (create + configure + verify) | Service | Complex multi-step workflow |
| Calculate order total | Action or Model Method | Simple calculation, could be either |
| Import 1000 customers from CSV | Service | Orchestration, batching, error handling |

---

## Keeping Models Thin

### Model Responsibilities

**Models SHOULD contain:**
- Relationships (hasMany, belongsTo, etc.)
- Query scopes (scopeActive, scopePublished)
- Accessors and mutators (getTotalAttribute, setPasswordAttribute)
- Casts and dates
- Attribute casting
- Basic data integrity (validation rules for reference)

**Models SHOULD NOT contain:**
- Complex business logic
- Multi-model orchestration
- External API calls
- File system operations
- Email sending
- Heavy computation

### Good Model Example

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * FleetVHost Model
 *
 * Represents a virtual host (domain) on a vnode (server)
 *
 * RELATIONSHIPS:
 * - belongsTo FleetVNode (parent server)
 * - hasMany VConf (54+ environment variables)
 * - hasMany VServ (services: nginx, php, mail, etc.)
 *
 * BUSINESS RULES:
 * - Domain must be unique per vnode (database constraint)
 * - Requires active vnode (enforced in service layer)
 * - Soft deletes with 90-day retention (BR-042)
 */
class FleetVHost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fleet_vnode_id',
        'domain',
        'php_version',
        'web_root',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * VNode (server) this vhost belongs to
     */
    public function vnode(): BelongsTo
    {
        return $this->belongsTo(FleetVNode::class, 'fleet_vnode_id');
    }

    /**
     * Configuration variables (54+ per vhost)
     *
     * Each vconf is separate row with name/value/category
     * Access via: $vhost->vconf('WPATH')
     * Set via: $vhost->setVconf('WPATH', '/srv/example.com/web')
     */
    public function vconfs(): HasMany
    {
        return $this->hasMany(VConf::class, 'fleet_vhost_id');
    }

    /**
     * Services running for this vhost (nginx, php-fpm, etc.)
     */
    public function vservs(): HasMany
    {
        return $this->hasMany(VServ::class, 'fleet_vhost_id');
    }

    /**
     * Scope: Active vhosts only
     *
     * Excludes suspended, failed, and deleting statuses
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Scope: By PHP version
     */
    public function scopePhpVersion(Builder $query, string $version): void
    {
        $query->where('php_version', $version);
    }

    /**
     * Get specific vconf value by name
     *
     * @param string $name 5-char vconf name (WPATH, DPASS, etc.)
     * @return string|null Value or null if not found
     */
    public function vconf(string $name): ?string
    {
        return $this->vconfs()
            ->where('name', $name)
            ->value('value');
    }

    /**
     * Set vconf value (create or update)
     *
     * @param string $name 5-char vconf name
     * @param string $value New value
     * @param bool $isSensitive Whether to mask in display
     */
    public function setVconf(string $name, string $value, bool $isSensitive = false): void
    {
        $this->vconfs()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'is_sensitive' => $isSensitive]
        );
    }

    /**
     * Accessor: Full web path from vconfs
     *
     * Example: /srv/example.com/web
     */
    public function getWebPathAttribute(): string
    {
        return $this->vconf('WPATH') ?? $this->web_root;
    }

    /**
     * Check if vhost is fully provisioned and operational
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if vhost can be modified
     */
    public function isModifiable(): bool
    {
        return in_array($this->status, ['active', 'suspended']);
    }
}
```

**What's missing (intentionally):**
- Provisioning logic (in VHostProvisioningService)
- SSH execution (in RemoteExecutionService)
- Config generation (in ConfigGenerationService)
- Complex validation (in form requests)
- Event dispatching (in services)

---

## NetServa-Specific Patterns

### Remote Execution via Services

```php
// ✅ GOOD: Service handles remote execution
class VHostManagementService
{
    public function fixPermissions(FleetVHost $vhost): Result
    {
        return $this->remoteExecution->executeScriptWithVhost(
            host: $vhost->vnode->name,
            vhost: $vhost,
            script: <<<'BASH'
                chown -R "$UUSER:www-data" "$WPATH"
                find "$WPATH" -type d -exec chmod 755 {} \;
                find "$WPATH" -type f -exec chmod 644 {} \;
                BASH
        );
    }
}

// ❌ BAD: Model contains SSH logic
class FleetVHost extends Model
{
    public function fixPermissions()
    {
        // Models shouldn't execute SSH commands
        $ssh = new SSH2($this->vnode->ip);
        $ssh->exec("chown -R user:group /srv/{$this->domain}");
    }
}
```

### Database-First Configuration

```php
// ✅ GOOD: Service reads from vconfs table
class NginxConfigService
{
    public function generateConfig(FleetVHost $vhost): string
    {
        $wpath = $vhost->vconf('WPATH');
        $phpVersion = $vhost->php_version;

        return view('nginx.vhost', compact('wpath', 'phpVersion'))->render();
    }
}

// ❌ BAD: Reading from files
class NginxConfigService
{
    public function generateConfig(FleetVHost $vhost): string
    {
        $envFile = "/srv/{$vhost->domain}/.env";
        // Never read config from files - use database!
    }
}
```

---

**Version:** 1.0.0 (2025-10-08)
**NetServa Platform:** 3.0
**License:** MIT (1995-2025)
