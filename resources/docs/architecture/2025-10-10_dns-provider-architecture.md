# DNS Provider Architecture - Comprehensive Design

**Date:** 2025-10-10
**Status:** 🎯 Architectural Proposal
**Complexity:** High - Multi-provider, multi-level relationship design

---

## Question Summary

**How to add DNS service field to every vnode and vhost that requires DNS resolution from a certain provider?**

**Requirements:**
- Support multiple DNS providers (PowerDNS, Cloudflare, Route53, etc.)
- Store API access details securely
- Associate providers at multiple levels (vnode, vhost)
- Handle provider-specific configurations
- Support inheritance and overrides

---

## Architecture Analysis

### Current State

**Existing Infrastructure:**
```
dns_providers (netserva-dns)
├── id
├── type (powerdns, cloudflare, route53)
├── name
├── connection_config (JSON)
├── active
└── timestamps

fleet_vnodes (netserva-fleet)
├── id
├── name
├── fqdn (added in Phase 4)
├── email_capable (added in Phase 4)
└── ssh_host_id (nullable FK)

fleet_vhosts (netserva-fleet)
├── id
├── domain
├── vnode_id (FK)
└── timestamps
```

**Missing:**
- No link between vnodes/vhosts and DNS providers
- No inheritance mechanism
- No default provider selection

---

## Proposed Solution: Multi-Level DNS Provider Association

### Design Principles

1. **Database-First** - All DNS provider associations in database
2. **Inheritance Pattern** - VHost inherits from VNode, VNode inherits from default
3. **Explicit Override** - Allow explicit provider assignment at any level
4. **Nullable Foreign Keys** - Optional relationships (not all vnodes/vhosts need DNS)
5. **Provider Polymorphism** - Support multiple provider types seamlessly

### Architecture: Three-Tier Hierarchy

```
Default Provider (Application-wide)
    └── VNode Provider (Per-server)
            └── VHost Provider (Per-virtual-host)
```

**Inheritance Logic:**
- VHost checks `dns_provider_id` → if null, inherit from VNode
- VNode checks `dns_provider_id` → if null, use default provider
- Default provider configured in `config/dns-manager.php`

---

## Implementation Plan

### Option A: Foreign Key Associations (RECOMMENDED)

**Pros:**
- ✅ Database constraints enforce referential integrity
- ✅ Eloquent relationships work natively
- ✅ Easy to query ("show all vhosts using provider X")
- ✅ Migration path clear
- ✅ Filament UI auto-generates relationship fields

**Cons:**
- ⚠️ Requires two migrations (vnodes + vhosts)
- ⚠️ Nullable FKs mean orphaned records possible

#### Migration 1: Add dns_provider_id to fleet_vnodes

```php
Schema::table('fleet_vnodes', function (Blueprint $table) {
    $table->foreignId('dns_provider_id')
        ->nullable()
        ->after('email_capable')
        ->constrained('dns_providers')
        ->nullOnDelete()
        ->comment('DNS provider for this vnode (null = use default)');

    $table->index('dns_provider_id');
});
```

#### Migration 2: Add dns_provider_id to fleet_vhosts

```php
Schema::table('fleet_vhosts', function (Blueprint $table) {
    $table->foreignId('dns_provider_id')
        ->nullable()
        ->after('domain')
        ->constrained('dns_providers')
        ->nullOnDelete()
        ->comment('DNS provider for this vhost (null = inherit from vnode)');

    $table->index('dns_provider_id');
});
```

#### Model Methods (FleetVNode)

```php
namespace NetServa\Fleet\Models;

use NetServa\Dns\Models\DnsProvider;

class FleetVNode extends Model
{
    // Relationship
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class);
    }

    // Get effective DNS provider (with inheritance)
    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        // Explicit assignment
        if ($this->dns_provider_id) {
            return $this->dnsProvider;
        }

        // Fall back to default from config
        $defaultId = config('dns-manager.default_provider_id');

        return $defaultId ? DnsProvider::find($defaultId) : null;
    }

    // Check if vnode can manage DNS
    public function canManageDns(): bool
    {
        return $this->getEffectiveDnsProvider() !== null;
    }
}
```

#### Model Methods (FleetVHost)

```php
namespace NetServa\Fleet\Models;

use NetServa\Dns\Models\DnsProvider;

class FleetVHost extends Model
{
    // Relationship
    public function dnsProvider(): BelongsTo
    {
        return $this->belongsTo(DnsProvider::class);
    }

    // Get effective DNS provider (with inheritance)
    public function getEffectiveDnsProvider(): ?DnsProvider
    {
        // 1. Explicit vhost assignment
        if ($this->dns_provider_id) {
            return $this->dnsProvider;
        }

        // 2. Inherit from vnode
        if ($this->vnode) {
            return $this->vnode->getEffectiveDnsProvider();
        }

        // 3. Fall back to default from config
        $defaultId = config('dns-manager.default_provider_id');

        return $defaultId ? DnsProvider::find($defaultId) : null;
    }

    // Check if vhost can manage DNS
    public function canManageDns(): bool
    {
        return $this->getEffectiveDnsProvider() !== null;
    }

    // Get DNS zone for this vhost
    public function getDnsZone(): ?string
    {
        // Extract zone from domain
        // example.com → example.com
        // sub.example.com → example.com
        $parts = explode('.', $this->domain);

        if (count($parts) >= 2) {
            return implode('.', array_slice($parts, -2));
        }

        return $this->domain;
    }
}
```

#### Configuration (config/dns-manager.php)

```php
return [
    // Default DNS provider ID (null = no default)
    'default_provider_id' => env('DNS_DEFAULT_PROVIDER_ID', null),

    // Fallback to first active PowerDNS provider if default not set
    'auto_select_powerdns' => env('DNS_AUTO_SELECT_POWERDNS', true),

    // Provider type preferences (when auto-selecting)
    'provider_preference' => [
        'powerdns',
        'cloudflare',
        'route53',
    ],
];
```

---

### Option B: Polymorphic Relationships (ADVANCED)

**Use Case:** If you want to support multiple DNS providers per vhost/vnode

**Pros:**
- ✅ Multiple providers per entity
- ✅ Flexible provider switching
- ✅ Provider-specific metadata

**Cons:**
- ⚠️ More complex queries
- ⚠️ Harder to understand
- ⚠️ Overhead for simple use cases

**Verdict:** ❌ **NOT RECOMMENDED** - Overly complex for the use case

---

### Option C: JSON Field (ANTI-PATTERN)

**Example:**
```php
$table->json('dns_config')->nullable();
// Stores: {"provider_id": 1, "zone": "example.com"}
```

**Verdict:** ❌ **STRONGLY NOT RECOMMENDED**
- Violates database-first architecture
- No referential integrity
- Can't use Eloquent relationships
- Harder to query
- No foreign key constraints

---

## Recommended Implementation: Option A

### Phase 1: Database Schema (Migrations)

**Create two migrations:**

1. `2025_10_10_130000_add_dns_provider_to_fleet_vnodes_table.php`
2. `2025_10_10_130001_add_dns_provider_to_fleet_vhosts_table.php`

**Run:**
```bash
php artisan make:migration add_dns_provider_to_fleet_vnodes_table --path=packages/netserva-fleet/database/migrations
php artisan make:migration add_dns_provider_to_fleet_vhosts_table --path=packages/netserva-fleet/database/migrations
```

### Phase 2: Model Updates

**Update models:**
- `packages/netserva-fleet/src/Models/FleetVNode.php`
- `packages/netserva-fleet/src/Models/FleetVHost.php`

**Add:**
- Relationship methods
- `getEffectiveDnsProvider()` method
- `canManageDns()` helper

### Phase 3: Service Layer Updates

**Update services to use DNS providers:**

```php
// FleetDiscoveryService
public function discoverAndStoreFqdn(FleetVNode $vnode): void
{
    $fqdn = $this->detectFqdn($vnode);
    $ip = $this->detectPublicIp($vnode);

    // Get effective DNS provider
    $dnsProvider = $vnode->getEffectiveDnsProvider();

    if (!$dnsProvider) {
        Log::warning('No DNS provider configured for vnode', [
            'vnode' => $vnode->name
        ]);
        $vnode->update(['fqdn' => $fqdn, 'email_capable' => false]);
        return;
    }

    // Use the provider
    $result = $this->powerDnsService->createFCrDNSRecords(
        $dnsProvider,  // Use vnode's provider
        $fqdn,
        $ip
    );
}
```

### Phase 4: Filament UI Updates

**Add DNS provider selects to resources:**

```php
// FleetVNodeResource (Filament)
Forms\Components\Select::make('dns_provider_id')
    ->label('DNS Provider')
    ->relationship('dnsProvider', 'name')
    ->searchable()
    ->preload()
    ->nullable()
    ->helperText('DNS provider for this server (leave empty to use default)')
    ->columnSpanFull(),
```

### Phase 5: Default Provider Management

**Add default provider selection to DNS settings:**

```php
// DnsProviderResource (Filament)
Forms\Components\Toggle::make('is_default')
    ->label('Default Provider')
    ->helperText('Use this as the default DNS provider for new vnodes/vhosts')
    ->afterStateUpdated(function ($state, $record) {
        if ($state) {
            // Unset other default providers
            DnsProvider::where('id', '!=', $record->id)
                ->update(['is_default' => false]);
        }
    }),
```

---

## Usage Examples

### Example 1: Create VNode with Specific DNS Provider

```php
$provider = DnsProvider::where('type', 'powerdns')->first();

$vnode = FleetVNode::create([
    'name' => 'ns1',
    'vsite_id' => $vsite->id,
    'dns_provider_id' => $provider->id,  // Explicit assignment
]);

// Check DNS capability
if ($vnode->canManageDns()) {
    // Provision DNS records
}
```

### Example 2: VHost Inherits from VNode

```php
$vnode = FleetVNode::find(1);  // Has dns_provider_id = 5

$vhost = FleetVHost::create([
    'domain' => 'example.com',
    'vnode_id' => $vnode->id,
    'dns_provider_id' => null,  // Inherit from vnode
]);

$provider = $vhost->getEffectiveDnsProvider();
// Returns: DnsProvider with id=5 (inherited from vnode)
```

### Example 3: VHost Overrides VNode

```php
$vnode = FleetVNode::find(1);  // Has dns_provider_id = 5 (PowerDNS)
$cloudflare = DnsProvider::where('type', 'cloudflare')->first();  // id = 7

$vhost = FleetVHost::create([
    'domain' => 'cdn.example.com',
    'vnode_id' => $vnode->id,
    'dns_provider_id' => $cloudflare->id,  // Explicit override
]);

$provider = $vhost->getEffectiveDnsProvider();
// Returns: DnsProvider with id=7 (Cloudflare - overridden)
```

### Example 4: Query All VHosts Using Specific Provider

```php
// Direct assignment
$vhosts = FleetVHost::where('dns_provider_id', $providerId)->get();

// Including inherited (requires custom scope)
$vhosts = FleetVHost::with(['vnode.dnsProvider'])
    ->get()
    ->filter(fn($vhost) =>
        $vhost->getEffectiveDnsProvider()?->id === $providerId
    );
```

---

## Advanced Features

### Auto-Assign DNS Provider on VNode Creation

```php
// FleetVNode Model
protected static function booted(): void
{
    static::creating(function (FleetVNode $vnode) {
        // Auto-assign default provider if not set
        if (!$vnode->dns_provider_id && config('dns-manager.auto_select_powerdns')) {
            $default = DnsProvider::active()
                ->where('type', 'powerdns')
                ->orderBy('sort_order')
                ->first();

            if ($default) {
                $vnode->dns_provider_id = $default->id;
            }
        }
    });
}
```

### DNS Provider Health Checking

```php
// Add to DnsProvider model
public function testConnection(): array
{
    try {
        $client = $this->getClient();
        $result = $client->testConnection();

        $this->update([
            'last_checked_at' => now(),
            'is_reachable' => $result,
        ]);

        return [
            'success' => $result,
            'provider' => $this->name,
            'type' => $this->type,
        ];
    } catch (\Exception $e) {
        $this->update([
            'last_checked_at' => now(),
            'is_reachable' => false,
            'last_error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

### Provider-Specific Zone Management

```php
// FleetVHost method
public function ensureDnsRecords(string $ip): array
{
    $provider = $this->getEffectiveDnsProvider();

    if (!$provider) {
        return ['success' => false, 'error' => 'No DNS provider configured'];
    }

    // Provider-specific handling
    return match ($provider->type) {
        'powerdns' => $this->ensurePowerDnsRecords($provider, $ip),
        'cloudflare' => $this->ensureCloudflareRecords($provider, $ip),
        'route53' => $this->ensureRoute53Records($provider, $ip),
        default => ['success' => false, 'error' => 'Unsupported provider type'],
    };
}
```

---

## Migration Path

### Step 1: Create Migrations

```bash
cd /home/markc/.ns

# Create migrations
php artisan make:migration add_dns_provider_to_fleet_vnodes_table \
    --path=packages/netserva-fleet/database/migrations

php artisan make:migration add_dns_provider_to_fleet_vhosts_table \
    --path=packages/netserva-fleet/database/migrations
```

### Step 2: Run Migrations

```bash
php artisan migrate
```

### Step 3: Configure Default Provider

```bash
# In Filament UI:
# 1. Go to DNS Providers
# 2. Select your PowerDNS provider
# 3. Mark as "Default Provider"

# Or via tinker:
php artisan tinker
>>> $provider = \NetServa\Dns\Models\DnsProvider::where('type', 'powerdns')->first();
>>> $provider->update(['is_default' => true]);
```

### Step 4: Update Existing VNodes (Optional)

```php
// Assign default provider to existing vnodes
php artisan tinker
>>> $provider = \NetServa\Dns\Models\DnsProvider::where('is_default', true)->first();
>>> \NetServa\Fleet\Models\FleetVNode::whereNull('dns_provider_id')
...     ->update(['dns_provider_id' => $provider->id]);
```

---

## Testing Strategy

### Unit Tests

```php
test('vnode inherits default DNS provider', function () {
    $provider = DnsProvider::factory()->create(['is_default' => true]);
    $vnode = FleetVNode::factory()->create(['dns_provider_id' => null]);

    expect($vnode->getEffectiveDnsProvider()->id)->toBe($provider->id);
});

test('vhost inherits from vnode', function () {
    $provider = DnsProvider::factory()->create();
    $vnode = FleetVNode::factory()->create(['dns_provider_id' => $provider->id]);
    $vhost = FleetVHost::factory()->create([
        'vnode_id' => $vnode->id,
        'dns_provider_id' => null,
    ]);

    expect($vhost->getEffectiveDnsProvider()->id)->toBe($provider->id);
});

test('vhost can override vnode provider', function () {
    $vnodeProvider = DnsProvider::factory()->create();
    $vhostProvider = DnsProvider::factory()->create();

    $vnode = FleetVNode::factory()->create(['dns_provider_id' => $vnodeProvider->id]);
    $vhost = FleetVHost::factory()->create([
        'vnode_id' => $vnode->id,
        'dns_provider_id' => $vhostProvider->id,
    ]);

    expect($vhost->getEffectiveDnsProvider()->id)->toBe($vhostProvider->id);
});
```

---

## Security Considerations

### API Credentials Storage

**Current:** Stored in `dns_providers.connection_config` JSON field

**Recommendations:**
1. ✅ Already encrypted at rest (Laravel encryption)
2. ⚠️ Consider moving to `secrets` table for audit trail
3. ✅ Use Laravel's `encrypted` cast for extra protection

**Enhanced Security:**

```php
// DnsProvider model
protected $casts = [
    'connection_config' => 'encrypted:array',  // Encrypted JSON
];
```

### Access Control

```php
// Filament Policy
public function viewDnsCredentials(User $user, DnsProvider $provider): bool
{
    return $user->hasRole('admin') || $user->hasRole('dns-admin');
}

public function updateDnsProvider(User $user, DnsProvider $provider): bool
{
    return $user->can('manage-dns-providers');
}
```

---

## Performance Considerations

### Eager Loading

```php
// Bad: N+1 queries
$vhosts = FleetVHost::all();
foreach ($vhosts as $vhost) {
    $provider = $vhost->getEffectiveDnsProvider();  // N queries!
}

// Good: Eager load
$vhosts = FleetVHost::with([
    'dnsProvider',
    'vnode.dnsProvider'
])->get();

foreach ($vhosts as $vhost) {
    $provider = $vhost->getEffectiveDnsProvider();  // No extra queries
}
```

### Caching Provider Resolution

```php
// FleetVHost model
protected ?DnsProvider $effectiveProviderCache = null;

public function getEffectiveDnsProvider(): ?DnsProvider
{
    if ($this->effectiveProviderCache !== null) {
        return $this->effectiveProviderCache;
    }

    // Resolution logic...

    return $this->effectiveProviderCache = $provider;
}
```

---

## Summary: The Best Way

### ✅ RECOMMENDED APPROACH

**1. Foreign Key Associations (Option A)**
- Add `dns_provider_id` to both `fleet_vnodes` and `fleet_vhosts` tables
- Nullable foreign keys with `nullOnDelete`
- Index for query performance

**2. Inheritance Pattern**
- VHost → VNode → Default (config)
- Implemented via `getEffectiveDnsProvider()` method
- Clear, predictable resolution order

**3. Configuration-Based Default**
- Store default provider ID in config
- Fallback to first active PowerDNS provider
- Allow per-environment defaults

**4. Filament UI Integration**
- Select dropdown for DNS provider
- "Inherit from VNode" option (nullable)
- Visual indicator of effective provider

### 📋 Implementation Checklist

- [ ] Create vnodes migration (add dns_provider_id)
- [ ] Create vhosts migration (add dns_provider_id)
- [ ] Update FleetVNode model (relationship + getEffectiveDnsProvider)
- [ ] Update FleetVHost model (relationship + getEffectiveDnsProvider)
- [ ] Update FleetDiscoveryService (use effective provider)
- [ ] Update Filament resources (add provider selects)
- [ ] Add default provider configuration
- [ ] Write unit tests
- [ ] Update documentation

### 🎯 Benefits

- ✅ Database-first architecture maintained
- ✅ Multi-provider support (PowerDNS, Cloudflare, Route53)
- ✅ Flexible inheritance with explicit overrides
- ✅ Easy to query and report
- ✅ Filament UI auto-generates forms
- ✅ Type-safe with Eloquent relationships
- ✅ Scalable to thousands of vnodes/vhosts

---

**Ready to implement?** Start with migrations in Phase 1.
