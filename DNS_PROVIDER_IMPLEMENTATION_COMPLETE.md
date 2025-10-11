# DNS Provider Architecture - Implementation Complete

**Date:** 2025-10-10
**Status:** ✅ COMPLETE - Ready for configuration
**Architecture:** Multi-level inheritance with nullable foreign keys

---

## What Was Implemented

### 1. Database Schema ✅

**Two migrations created and applied:**

#### Migration 1: `fleet_vnodes.dns_provider_id`
```php
$table->foreignId('dns_provider_id')
    ->nullable()
    ->after('fcrdns_validated_at')
    ->constrained('dns_providers')
    ->nullOnDelete()
    ->comment('DNS provider for this vnode (null = use default provider)');
```

#### Migration 2: `fleet_vhosts.dns_provider_id`
```php
$table->foreignId('dns_provider_id')
    ->nullable()
    ->after('domain')
    ->constrained('dns_providers')
    ->nullOnDelete()
    ->comment('DNS provider for this vhost (null = inherit from vnode)');
```

**Benefits:**
- ✅ Referential integrity enforced by database
- ✅ Cascading deletes handled (`nullOnDelete`)
- ✅ Indexed for query performance
- ✅ Nullable allows inheritance pattern

---

### 2. Model Updates ✅

#### FleetVNode Model

**Added:**
- `dns_provider_id` to fillable array
- `dnsProvider()` relationship method
- `getEffectiveDnsProvider()` - inheritance logic
- `canManageDns()` - DNS capability check
- `getDnsProviderType()` - get provider type
- `usesPowerDns()` - PowerDNS check
- `usesCloudflare()` - Cloudflare check

**Location:** `packages/netserva-fleet/src/Models/FleetVNode.php`

#### FleetVHost Model

**Added:**
- `dns_provider_id` to fillable array
- `dnsProvider()` relationship method
- `getEffectiveDnsProvider()` - inheritance logic with vnode fallback
- `canManageDns()` - DNS capability check
- `getDnsZone()` - extract zone from domain
- `getDnsSubdomain()` - extract subdomain
- `getDnsProviderType()` - get provider type
- `usesPowerDns()` - PowerDNS check
- `usesCloudflare()` - Cloudflare check
- `hasExplicitDnsProvider()` - check if explicitly set
- `inheritsDnsProvider()` - check if inherited

**Location:** `packages/netserva-fleet/src/Models/FleetVHost.php`

---

## Inheritance Architecture

### Three-Tier Resolution

```
┌─────────────────────────────────────────────────────────┐
│  Resolution Order for DNS Provider                      │
├─────────────────────────────────────────────────────────┤
│  1. Explicit Assignment (dns_provider_id not null)      │
│  2. Inherit from VNode (for VHosts)                     │
│  3. Default from Config (dns-manager.default_provider_id)│
│  4. Auto-select PowerDNS (if enabled)                   │
│  5. null (no provider)                                  │
└─────────────────────────────────────────────────────────┘
```

### Example Flow

**Scenario 1: VHost with explicit provider**
```php
$vhost->dns_provider_id = 5;  // Cloudflare
$vnode->dns_provider_id = 3;  // PowerDNS

$provider = $vhost->getEffectiveDnsProvider();
// Returns: Cloudflare (ID 5) - explicit wins
```

**Scenario 2: VHost inherits from VNode**
```php
$vhost->dns_provider_id = null;  // Not set
$vnode->dns_provider_id = 3;     // PowerDNS

$provider = $vhost->getEffectiveDnsProvider();
// Returns: PowerDNS (ID 3) - inherited from vnode
```

**Scenario 3: Both use default**
```php
$vhost->dns_provider_id = null;
$vnode->dns_provider_id = null;
config('dns-manager.default_provider_id') = 1;

$provider = $vhost->getEffectiveDnsProvider();
// Returns: Provider ID 1 (from config)
```

**Scenario 4: Auto-select PowerDNS**
```php
$vhost->dns_provider_id = null;
$vnode->dns_provider_id = null;
config('dns-manager.default_provider_id') = null;
config('dns-manager.auto_select_powerdns') = true;

$provider = $vhost->getEffectiveDnsProvider();
// Returns: First active PowerDNS provider (by sort_order)
```

---

## Usage Examples

### Check DNS Capability

```php
$vnode = FleetVNode::find(1);

if ($vnode->canManageDns()) {
    echo "DNS management available";
    echo "Provider: " . $vnode->getEffectiveDnsProvider()->name;
} else {
    echo "No DNS provider configured";
}
```

### Get Provider Type

```php
$vhost = FleetVHost::find(1);

if ($vhost->usesPowerDns()) {
    // Use PowerDNS-specific API
    $service = app(PowerDnsService::class);
    $provider = $vhost->getEffectiveDnsProvider();
    $service->createFCrDNSRecords($provider, $fqdn, $ip);
}

if ($vhost->usesCloudflare()) {
    // Use Cloudflare-specific API
    $service = app(CloudflareService::class);
    $provider = $vhost->getEffectiveDnsProvider();
    $service->createDnsRecords($provider, $domain, $ip);
}
```

### Extract DNS Information

```php
$vhost = FleetVHost::where('domain', 'api.example.com')->first();

echo "Domain: " . $vhost->domain;           // api.example.com
echo "Zone: " . $vhost->getDnsZone();        // example.com
echo "Subdomain: " . $vhost->getDnsSubdomain(); // api
```

### Query by Provider

```php
// Find all vhosts explicitly using PowerDNS provider ID 3
$vhosts = FleetVHost::where('dns_provider_id', 3)->get();

// Find all vhosts that can manage DNS (includes inherited)
$vhosts = FleetVHost::with(['dnsProvider', 'vnode.dnsProvider'])
    ->get()
    ->filter(fn($vhost) => $vhost->canManageDns());
```

### Check Inheritance

```php
$vhost = FleetVHost::find(1);

if ($vhost->hasExplicitDnsProvider()) {
    echo "Using explicitly assigned provider";
} elseif ($vhost->inheritsDnsProvider()) {
    echo "Inheriting from vnode: " . $vhost->vnode->dnsProvider->name;
} else {
    echo "Using default provider";
}
```

---

## Next Steps

### 1. Create DNS Provider (Required)

**Option A: Via Filament UI** (when UI is updated)
- Navigate to DNS → Providers
- Click "New Provider"
- Fill in details:
  - Name: "Main PowerDNS"
  - Type: powerdns
  - Active: Yes
  - Connection Config: {...}

**Option B: Via Tinker**

```php
php artisan tinker

>>> use NetServa\Dns\Models\DnsProvider;
>>> $provider = DnsProvider::create([
...     'name' => 'Main PowerDNS',
...     'type' => 'powerdns',
...     'active' => true,
...     'connection_config' => [
...         'api_endpoint' => 'http://localhost:8081',
...         'api_key' => 'your-api-key-here',
...         'ssh_host' => 'ns1.goldcoast.org',  // For SSH tunnel
...         'api_port' => 8081,
...     ],
... ]);
>>> $provider->id;  // Note the ID
```

### 2. Configure Default Provider (Optional)

**Method 1: Environment variable**
```bash
# Add to .env
DNS_DEFAULT_PROVIDER_ID=1
```

**Method 2: Config file**
```php
// config/dns-manager.php
return [
    'default_provider_id' => 1,  // Set to your provider ID
    'auto_select_powerdns' => true,
];
```

**Method 3: Mark provider as default** (requires DB update)
```php
php artisan tinker
>>> $provider = DnsProvider::find(1);
>>> $provider->update(['is_default' => true]);
```

### 3. Assign to Existing VNodes (Optional)

```php
php artisan tinker

>>> use NetServa\Fleet\Models\FleetVNode;
>>> use NetServa\Dns\Models\DnsProvider;

>>> $provider = DnsProvider::first();
>>> FleetVNode::where('name', 'markc')->update(['dns_provider_id' => $provider->id]);

// Or update all vnodes
>>> FleetVNode::query()->update(['dns_provider_id' => $provider->id]);
```

### 4. Update Filament Resources

**Add DNS provider select to FleetVNodeResource:**

```php
// packages/netserva-fleet/src/Filament/Resources/FleetVNodeResource.php

use NetServa\Dns\Models\DnsProvider;

Forms\Components\Select::make('dns_provider_id')
    ->label('DNS Provider')
    ->relationship('dnsProvider', 'name')
    ->searchable()
    ->preload()
    ->nullable()
    ->helperText('DNS provider for this server (leave empty to use default)')
    ->columnSpanFull(),
```

**Add DNS provider select to FleetVHostResource:**

```php
// packages/netserva-fleet/src/Filament/Resources/FleetVHostResource.php

Forms\Components\Select::make('dns_provider_id')
    ->label('DNS Provider')
    ->relationship('dnsProvider', 'name')
    ->searchable()
    ->preload()
    ->nullable()
    ->helperText('Override DNS provider (leave empty to inherit from vnode)')
    ->hint(function ($record) {
        if ($record && !$record->dns_provider_id) {
            $inherited = $record->getEffectiveDnsProvider();
            return $inherited ? "Inheriting: {$inherited->name}" : "No provider";
        }
        return null;
    })
    ->columnSpanFull(),
```

### 5. Update FleetDiscoveryService

**Modify to use effective DNS provider:**

```php
// packages/netserva-fleet/src/Services/FleetDiscoveryService.php

public function discoverAndStoreFqdn(FleetVNode $vnode): void
{
    $fqdn = $this->detectFqdn($vnode);
    $ip = $this->detectPublicIp($vnode);

    // Get effective DNS provider
    $dnsProvider = $vnode->getEffectiveDnsProvider();

    if (!$dnsProvider) {
        Log::warning('No DNS provider configured for vnode', [
            'vnode' => $vnode->name,
            'fqdn' => $fqdn,
        ]);
        $vnode->update([
            'fqdn' => $fqdn,
            'email_capable' => false,
        ]);
        return;
    }

    // Use the provider for DNS operations
    $result = $this->powerDnsService->createFCrDNSRecords(
        $dnsProvider,  // Use vnode's effective provider
        $fqdn,
        $ip
    );

    // ... rest of the logic
}
```

---

## Testing

### Manual Testing

```bash
# 1. Create DNS provider
php artisan tinker
>>> $provider = NetServa\Dns\Models\DnsProvider::create([
...     'name' => 'Test PowerDNS',
...     'type' => 'powerdns',
...     'active' => true,
...     'connection_config' => ['api_endpoint' => 'http://localhost:8081'],
... ]);

# 2. Test vnode assignment
>>> $vnode = NetServa\Fleet\Models\FleetVNode::first();
>>> $vnode->update(['dns_provider_id' => $provider->id]);
>>> $vnode->canManageDns();  // Should return true
>>> $vnode->getEffectiveDnsProvider()->name;  // Should return 'Test PowerDNS'

# 3. Test vhost inheritance
>>> $vhost = NetServa\Fleet\Models\FleetVHost::first();
>>> $vhost->getEffectiveDnsProvider()->name;  // Should inherit from vnode

# 4. Test vhost override
>>> $provider2 = NetServa\Dns\Models\DnsProvider::create([
...     'name' => 'Cloudflare',
...     'type' => 'cloudflare',
...     'active' => true,
... ]);
>>> $vhost->update(['dns_provider_id' => $provider2->id]);
>>> $vhost->getEffectiveDnsProvider()->name;  // Should return 'Cloudflare'
```

### Unit Tests (To Be Written)

```php
// tests/Unit/FleetVNodeDnsProviderTest.php

test('vnode uses explicit DNS provider', function () {
    $provider = DnsProvider::factory()->create();
    $vnode = FleetVNode::factory()->create(['dns_provider_id' => $provider->id]);

    expect($vnode->getEffectiveDnsProvider()->id)->toBe($provider->id);
});

test('vnode inherits default DNS provider', function () {
    config(['dns-manager.default_provider_id' => 1]);
    $provider = DnsProvider::factory()->create(['id' => 1]);
    $vnode = FleetVNode::factory()->create(['dns_provider_id' => null]);

    expect($vnode->getEffectiveDnsProvider()->id)->toBe(1);
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

## Database Schema

### Current State

```sql
-- fleet_vnodes
ALTER TABLE fleet_vnodes ADD COLUMN dns_provider_id BIGINT UNSIGNED NULL;
ALTER TABLE fleet_vnodes ADD FOREIGN KEY (dns_provider_id) REFERENCES dns_providers(id) ON DELETE SET NULL;
CREATE INDEX fleet_vnodes_dns_provider_id_index ON fleet_vnodes (dns_provider_id);

-- fleet_vhosts
ALTER TABLE fleet_vhosts ADD COLUMN dns_provider_id BIGINT UNSIGNED NULL;
ALTER TABLE fleet_vhosts ADD FOREIGN KEY (dns_provider_id) REFERENCES dns_providers(id) ON DELETE SET NULL;
CREATE INDEX fleet_vhosts_dns_provider_id_index ON fleet_vhosts (dns_provider_id);
```

### Verify Schema

```bash
php artisan tinker --execute="
\$schema = DB::select('SELECT sql FROM sqlite_master WHERE name=\"fleet_vnodes\"');
echo \$schema[0]->sql;
"
```

---

## Files Modified

### Created
1. ✅ `packages/netserva-fleet/database/migrations/2025_10_10_130000_add_dns_provider_to_fleet_vnodes_table.php`
2. ✅ `packages/netserva-fleet/database/migrations/2025_10_10_130001_add_dns_provider_to_fleet_vhosts_table.php`
3. ✅ `resources/docs/architecture/DNS_PROVIDER_ARCHITECTURE.md`
4. ✅ `DNS_PROVIDER_IMPLEMENTATION_COMPLETE.md`

### Modified
1. ✅ `packages/netserva-fleet/src/Models/FleetVNode.php`
   - Added dns_provider_id to fillable
   - Added dnsProvider() relationship
   - Added getEffectiveDnsProvider() method
   - Added DNS helper methods

2. ✅ `packages/netserva-fleet/src/Models/FleetVHost.php`
   - Added dns_provider_id to fillable
   - Added dnsProvider() relationship
   - Added getEffectiveDnsProvider() method
   - Added DNS helper methods (zone extraction, etc.)

---

## Summary

### ✅ COMPLETE

**Architecture:** Multi-level DNS provider association with inheritance
**Database:** Two nullable foreign keys with cascading deletes
**Models:** Full relationship support with helper methods
**Inheritance:** Three-tier resolution (explicit → vnode → default)

### 🔧 TODO (Next Phase)

1. Create DNS provider via Filament or Tinker
2. Update Filament resources with DNS provider selects
3. Update FleetDiscoveryService to use effective provider
4. Write unit tests for inheritance logic
5. Add DNS provider health checks
6. Create provider management UI

**Status:** Ready for DNS provider configuration and testing! 🎉
