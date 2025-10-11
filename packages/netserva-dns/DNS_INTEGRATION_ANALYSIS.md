# NetServa DNS Package - Integration Analysis for FCrDNS Provisioning

**Date**: 2025-10-10
**Scope**: Review existing DNS capabilities and plan FCrDNS-first provisioning integration

---

## Executive Summary

The **netserva-dns** package already contains substantial DNS management infrastructure that can be leveraged for FCrDNS-first provisioning. Rather than creating new commands from scratch, we should:

1. **Enable existing commands** (currently disabled in service provider)
2. **Add FCrDNS verification command** (new)
3. **Integrate with fleet:discover** workflow
4. **Extend PowerDNS services** for record management

---

## Current Package Architecture

### Service Provider Status
**File**: `src/NetServaDnsServiceProvider.php:85-91`

```php
protected function registerCommands(): void
{
    if ($this->app->runningInConsole()) {
        // Commands temporarily disabled during migration
    }
}
```

**❌ BLOCKER**: All DNS commands are currently **disabled**

---

## Existing Commands (Ready to Enable)

### 1. PowerDnsCommand (`ns:powerdns`)
**File**: `src/Console/Commands/PowerDnsCommand.php`

**Capabilities**:
- `ns:powerdns test <provider>` - Test PowerDNS connectivity
- `ns:powerdns zones <provider>` - List all zones
- `ns:powerdns zones <provider> --zone=<name>` - Show zone details
- `ns:powerdns zones <provider> --create-zone=<name>` - Create zone
- `ns:powerdns stats <provider>` - Get server statistics
- `ns:powerdns flush <provider> [--domain=<name>]` - Flush cache
- `ns:powerdns tunnel <provider> [--status|--close]` - Manage SSH tunnels

**Strengths**:
- ✅ SSH tunnel management built-in
- ✅ Provider abstraction (works with multiple PowerDNS instances)
- ✅ Comprehensive error handling
- ✅ Laravel Prompts integration
- ✅ Table-formatted output

**Gaps for FCrDNS**:
- ❌ No A record creation
- ❌ No PTR record creation
- ❌ No FCrDNS validation
- ❌ No record deletion
- ❌ No record listing

---

### 2. PowerDnsManagementCommand (`dns:powerdns-management`)
**File**: `src/Console/Commands/PowerDnsManagementCommand.php`

**Capabilities**:
- Health checks
- DNSSEC management (key generation, activation, validation)
- Zone operations (rectify, export, AXFR, notify)
- Advanced statistics with caching
- Bulk zone import

**Usage Pattern**:
```bash
dns:powerdns-management <action> [provider] [zone] [options]
```

**Actions**: `health-check`, `advanced-stats`, `dnssec-*`, `zone-*`, `key-*`, `bulk-import`

**Strengths**:
- ✅ Modular action-based design
- ✅ Rich diagnostics
- ✅ DNSSEC support
- ✅ Production-ready error handling

**Not Relevant for FCrDNS**:
- DNSSEC features (overkill for basic provisioning)
- Bulk operations (provisioning is one-at-a-time)

---

### 3. DnssecCommand
**File**: `src/Console/Commands/DnssecCommand.php`
**Purpose**: Dedicated DNSSEC management
**Status**: Specialized, not needed for FCrDNS provisioning

---

### 4. CloudFlare Commands
**Files**:
- `CloudFlareZonesCommand.php`
- `CloudFlareRecordsCommand.php`

**Purpose**: CloudFlare DNS management
**Status**: Alternative provider, not primary focus

---

## Existing Services (Core Infrastructure)

### 1. FcrDnsValidationService ✅
**File**: `src/Services/FcrDnsValidationService.php`

**Already Implemented**:
- `validate(fqdn, ip): DnsValidationResult` - Full FCrDNS validation
- `passes(fqdn, ip): bool` - Quick validation
- `validateForwardDnsOnly(fqdn): bool` - A record check
- `waitForPropagation(fqdn, ip, maxWait): bool` - DNS propagation wait
- `getDnsDebugInfo(fqdn, ip): array` - Detailed troubleshooting

**Perfect for**: `dns:verify` command ✅

---

### 2. PowerDnsService
**File**: `src/Services/PowerDnsService.php`

**Current Capabilities**:
- Get/create/update/delete zones
- Get zone metadata
- Get zone cryptokeys
- Generate/manage DNSSEC keys
- Export zones (BIND format)
- Health checks
- Advanced statistics

**Missing for Record Management**:
- ❌ `createRecord(zone, recordData): array`
- ❌ `updateRecord(zone, recordId, data): array`
- ❌ `deleteRecord(zone, recordId): array`
- ❌ `listRecords(zone, filters): array`
- ❌ `createARecord(zone, name, ip, ttl): array`
- ❌ `createPTRRecord(ip, fqdn, ttl): array`
- ❌ `createBothRecords(fqdn, ip, ttl): array` ← **KEY METHOD**

**Recommendation**: Extend `PowerDnsService` with record management methods

---

### 3. PowerDnsTunnelService
**File**: `src/Services/PowerDnsTunnelService.php`

**Capabilities**:
- Manages SSH tunnels to remote PowerDNS servers
- `apiCall(provider, endpoint, method, data): array`
- `testConnection(provider): array`
- `getZones(provider): array`
- `getZone(provider, zoneName): array`
- `createZone(provider, zoneData): array`
- `getServerStats(provider): array`
- `flushCache(provider, domain): array`
- `getTunnelStatus(provider): array`
- `closeTunnel(provider): array`

**Perfect**: Infrastructure is complete ✅

---

### 4. DnsProviderService, DnsZoneService, DomainService
**Status**: Abstract layer for multi-provider support
**Usage**: Higher-level operations, useful for Filament UI integration

---

## Database Schema (Already Exists)

### Tables
1. **dns_providers** - PowerDNS/CloudFlare provider configurations
2. **dns_zones** - Zone cache (write-through to remote)
3. **dns_records** - Record cache (write-through to remote)

### Models with Write-Through Cache
**Pattern**: Database is a **cache**, remote PowerDNS is **source of truth**

```php
// DnsZone and DnsRecord models have:
createOnRemote(data): bool    // Create remote first, then cache
updateOnRemote(data): bool    // Update remote first, then cache
deleteOnRemote(): bool        // Delete remote first, then cache
syncFromRemote(): bool        // Pull latest from remote
isCacheStale(): bool          // Check if cache needs refresh
```

**Implication**: DNS operations are safe and reversible ✅

---

## Gap Analysis: What's Missing for FCrDNS Provisioning

### Critical Gaps

#### 1. DNS Record Management Commands
**Need**: CLI commands for A and PTR record creation

**Options**:
- **Option A**: Extend `PowerDnsCommand` with record subcommands
- **Option B**: Create new `DnsRecordCommand` (cleaner separation)
- **Option C**: Add to `PowerDnsManagementCommand` as actions

**Recommendation**: **Option B** - New `DnsRecordCommand`

**Rationale**:
- Clean separation of concerns
- Follows NetServa 3.0 command pattern: `dns:record:create`, `dns:record:delete`, `dns:record:list`
- Easier to test and maintain
- Matches mental model of "DNS record operations"

---

#### 2. PowerDnsService Record Methods
**Need**: Service methods for record CRUD operations

**Methods to Add**:
```php
// Basic record operations
createRecord(DnsProvider $provider, string $zoneName, array $recordData): array
updateRecord(DnsProvider $provider, string $zoneName, string $recordId, array $data): array
deleteRecord(DnsProvider $provider, string $zoneName, string $recordId): array
listRecords(DnsProvider $provider, string $zoneName, array $filters = []): array

// Convenience methods for FCrDNS
createARecord(DnsProvider $provider, string $zoneName, string $name, string $ip, int $ttl = 3600): array
createPTRRecord(DnsProvider $provider, string $reverseZone, string $ip, string $fqdn, int $ttl = 3600): array
createFCrDNSRecords(DnsProvider $provider, string $fqdn, string $ip, int $ttl = 3600): array

// Validation helpers
validateRecordExists(DnsProvider $provider, string $zoneName, string $name, string $type): bool
getRecordByName(DnsProvider $provider, string $zoneName, string $name, string $type): ?array
```

**Implementation**: Extend `packages/netserva-dns/src/Services/PowerDnsService.php`

---

#### 3. FCrDNS Verification Command
**Need**: `dns:verify` command wrapper around `FcrDnsValidationService`

**Command Signature**:
```bash
dns:verify <fqdn> <ip> [--wait] [--max-wait=30]
```

**Output**:
```
Verifying FCrDNS for markc.goldcoast.org → 192.168.1.100

✅ Forward DNS (A): markc.goldcoast.org → 192.168.1.100
✅ Reverse DNS (PTR): 192.168.1.100 → markc.goldcoast.org
✅ FCrDNS: Match confirmed

Result: PASS (email-capable server)
```

**Implementation**: New command using existing `FcrDnsValidationService`

---

## Recommended Implementation Plan

### Phase 1: Enable and Test Existing Commands (1 hour)

1. **Enable commands in service provider**:
   ```php
   // NetServaDnsServiceProvider.php
   protected function registerCommands(): void
   {
       if ($this->app->runningInConsole()) {
           $this->commands([
               Console\Commands\PowerDnsCommand::class,
               Console\Commands\PowerDnsManagementCommand::class,
               // Keep CloudFlare/DNSSEC commented for now
           ]);
       }
   }
   ```

2. **Test existing commands**:
   ```bash
   php artisan ns:powerdns test <provider-id>
   php artisan ns:powerdns zones <provider-id>
   ```

3. **Document current DNS provider setup** (if needed)

---

### Phase 2: Extend PowerDnsService with Record Management (2-3 hours)

**File**: `packages/netserva-dns/src/Services/PowerDnsService.php`

Add methods:
```php
/**
 * Create DNS record in zone
 */
public function createRecord(DnsProvider $provider, string $zoneName, array $recordData): array
{
    $rrsets = [[
        'name' => $recordData['name'],
        'type' => $recordData['type'],
        'ttl' => $recordData['ttl'] ?? 3600,
        'changetype' => 'REPLACE',
        'records' => [
            ['content' => $recordData['content'], 'disabled' => false]
        ]
    ]];

    $result = $this->tunnelService->apiCall(
        $provider,
        "/servers/localhost/zones/$zoneName",
        'PATCH',
        ['rrsets' => $rrsets]
    );

    if ($result['success']) {
        Log::info('DNS record created', [
            'zone' => $zoneName,
            'name' => $recordData['name'],
            'type' => $recordData['type'],
            'content' => $recordData['content'],
        ]);

        return [
            'success' => true,
            'message' => "Record created: {$recordData['name']} {$recordData['type']} {$recordData['content']}",
        ];
    }

    return [
        'success' => false,
        'message' => 'Failed to create record: ' . ($result['error'] ?? 'Unknown error'),
    ];
}

/**
 * Create both A and PTR records for FCrDNS
 */
public function createFCrDNSRecords(
    DnsProvider $provider,
    string $fqdn,
    string $ip,
    int $ttl = 3600
): array {
    // 1. Extract zone from FQDN
    $parts = explode('.', $fqdn);
    $hostname = array_shift($parts);
    $zone = implode('.', $parts) . '.';

    // 2. Create A record
    $aResult = $this->createRecord($provider, $zone, [
        'name' => $fqdn . '.',
        'type' => 'A',
        'content' => $ip,
        'ttl' => $ttl,
    ]);

    if (!$aResult['success']) {
        return $aResult;
    }

    // 3. Create PTR record (reverse zone)
    $reverseZone = $this->getReverseZone($ip);
    $ptrName = $this->getReverseName($ip);

    $ptrResult = $this->createRecord($provider, $reverseZone, [
        'name' => $ptrName,
        'type' => 'PTR',
        'content' => $fqdn . '.',
        'ttl' => $ttl,
    ]);

    if (!$ptrResult['success']) {
        return [
            'success' => false,
            'message' => "A record created but PTR failed: {$ptrResult['message']}",
            'partial' => true,
        ];
    }

    return [
        'success' => true,
        'message' => "FCrDNS records created: $fqdn ↔ $ip",
        'a_record' => $aResult,
        'ptr_record' => $ptrResult,
    ];
}

/**
 * Get reverse DNS zone for IP
 */
protected function getReverseZone(string $ip): string
{
    $octets = explode('.', $ip);
    return "{$octets[2]}.{$octets[1]}.{$octets[0]}.in-addr.arpa.";
}

/**
 * Get reverse DNS name for IP
 */
protected function getReverseName(string $ip): string
{
    $octets = explode('.', $ip);
    return "{$octets[3]}.{$octets[2]}.{$octets[1]}.{$octets[0]}.in-addr.arpa.";
}
```

---

### Phase 3: Create dns:verify Command (1 hour)

**File**: `packages/netserva-dns/src/Console/Commands/DnsVerifyCommand.php`

```php
<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\FcrDnsValidationService;

class DnsVerifyCommand extends Command
{
    protected $signature = 'dns:verify
                            {fqdn : Fully qualified domain name}
                            {ip : IP address}
                            {--wait : Wait for DNS propagation}
                            {--max-wait=30 : Maximum seconds to wait}';

    protected $description = 'Verify FCrDNS (Forward-Confirmed Reverse DNS) for a host';

    protected FcrDnsValidationService $dnsValidation;

    public function __construct(FcrDnsValidationService $dnsValidation)
    {
        parent::__construct();
        $this->dnsValidation = $dnsValidation;
    }

    public function handle(): int
    {
        $fqdn = $this->argument('fqdn');
        $ip = $this->argument('ip');
        $wait = $this->option('wait');
        $maxWait = (int) $this->option('max-wait');

        $this->info("Verifying FCrDNS for $fqdn → $ip");
        $this->newLine();

        if ($wait) {
            $this->line("Waiting for DNS propagation (max {$maxWait}s)...");
            if (!$this->dnsValidation->waitForPropagation($fqdn, $ip, $maxWait)) {
                $this->error('❌ DNS propagation timeout');
                $this->showDnsDebugInfo($fqdn, $ip);
                return self::FAILURE;
            }
        }

        $result = $this->dnsValidation->validate($fqdn, $ip);

        // Display results
        $this->displayResult('Forward DNS (A)', $result->hasForwardDns, $result->forwardIp);
        $this->displayResult('Reverse DNS (PTR)', $result->hasReverseDns, $result->reverseFqdn);
        $this->displayResult('FCrDNS Match', $result->hasFcrDns);

        $this->newLine();

        if ($result->hasFcrDns) {
            $this->info('✅ FCrDNS PASS - Server is email-capable');
            return self::SUCCESS;
        } else {
            $this->error('❌ FCrDNS FAIL - Server cannot send email');
            $this->newLine();

            foreach ($result->errors as $error) {
                $this->line("  • $error");
            }

            $this->newLine();
            $this->showDnsDebugInfo($fqdn, $ip);

            return self::FAILURE;
        }
    }

    protected function displayResult(string $label, bool $passed, ?string $value = null): void
    {
        $icon = $passed ? '✅' : '❌';
        $status = $passed ? 'PASS' : 'FAIL';

        if ($value) {
            $this->line("$icon $label: $status → $value");
        } else {
            $this->line("$icon $label: $status");
        }
    }

    protected function showDnsDebugInfo(string $fqdn, string $ip): void
    {
        $this->warn('Debug Information:');
        $info = $this->dnsValidation->getDnsDebugInfo($fqdn, $ip);
        $this->line(json_encode($info, JSON_PRETTY_PRINT));
    }
}
```

**Register in service provider**:
```php
$this->commands([
    Console\Commands\DnsVerifyCommand::class,
]);
```

---

### Phase 4: Integrate with fleet:discover (1 hour)

**File**: `packages/netserva-fleet/src/Console/Commands/FleetDiscoverCommand.php`

Add `--auto-dns` flag handling:

```php
protected function discoverSpecificVNode(FleetVNode $vnode): int
{
    // ... existing code ...

    if ($this->option('auto-dns') && !$result->hasFcrDns) {
        $this->info('Auto-creating DNS records...');

        // Call PowerDnsService to create records
        $dnsResult = app(PowerDnsService::class)->createFCrDNSRecords(
            $provider,
            $vnode->fqdn,
            $ip
        );

        if ($dnsResult['success']) {
            $this->info('✅ DNS records created');

            // Wait and re-validate
            if ($this->dnsValidation->waitForPropagation($vnode->fqdn, $ip, 30)) {
                $vnode->update(['email_capable' => true]);
            }
        }
    }
}
```

---

### Phase 5: Testing (2 hours)

**Test Files**:
1. `packages/netserva-dns/tests/Unit/FcrDnsValidationServiceTest.php` ✅ (exists)
2. `packages/netserva-dns/tests/Feature/DnsVerifyCommandTest.php` (new)
3. `packages/netserva-dns/tests/Feature/PowerDnsRecordManagementTest.php` (new)
4. `packages/netserva-fleet/tests/Feature/FleetDiscoverFCrDnsTest.php` (new)

---

## Command Reference (After Implementation)

### Existing (to enable)
```bash
# PowerDNS operations
ns:powerdns test <provider>
ns:powerdns zones <provider> [--zone=<name>]
ns:powerdns zones <provider> --create-zone=<name>
ns:powerdns stats <provider>
ns:powerdns flush <provider> [--domain=<name>]
ns:powerdns tunnel <provider> [--status|--close]

# Advanced management
dns:powerdns-management health-check [provider]
dns:powerdns-management zone-export [provider] <zone>
```

### New (to create)
```bash
# FCrDNS verification
dns:verify <fqdn> <ip> [--wait] [--max-wait=30]

# Fleet discovery with DNS
fleet:discover --vnode=<name> [--auto-dns] [--verify-dns-only]
```

---

## Integration with NetServa 3.0 Architecture

### DNS-First Workflow
```
1. Admin configures PowerDNS provider in Filament
   └─ DnsProvider model (already exists)

2. Admin creates forward zone (goldcoast.org)
   └─ ns:powerdns zones <provider> --create-zone=goldcoast.org

3. Admin creates reverse zone (1.168.192.in-addr.arpa)
   └─ ns:powerdns zones <provider> --create-zone=1.168.192.in-addr.arpa

4. System creates A + PTR records during fleet:discover
   └─ PowerDnsService::createFCrDNSRecords()

5. System validates FCrDNS
   └─ FcrDnsValidationService::validate()

6. VNode is created with email_capable flag
   └─ FleetVNode model
```

---

## File Structure Summary

```
packages/netserva-dns/
├── src/
│   ├── Console/Commands/
│   │   ├── PowerDnsCommand.php                 ✅ Exists (disabled)
│   │   ├── PowerDnsManagementCommand.php       ✅ Exists (disabled)
│   │   ├── DnsVerifyCommand.php                ❌ Create
│   │   ├── CloudFlare*.php                     ⚠️  Optional
│   │   └── DnssecCommand.php                   ⚠️  Optional
│   ├── Services/
│   │   ├── FcrDnsValidationService.php         ✅ Exists (perfect!)
│   │   ├── PowerDnsService.php                 ⚠️  Extend with record methods
│   │   ├── PowerDnsTunnelService.php           ✅ Exists (complete)
│   │   └── DnsProviderService.php              ✅ Exists
│   ├── Models/
│   │   ├── DnsProvider.php                     ✅ Exists
│   │   ├── DnsZone.php                         ✅ Exists
│   │   └── DnsRecord.php                       ✅ Exists
│   └── NetServaDnsServiceProvider.php          ⚠️  Enable commands
└── tests/
    ├── Unit/
    │   └── FcrDnsValidationServiceTest.php     ❌ Create
    └── Feature/
        ├── DnsVerifyCommandTest.php            ❌ Create
        └── PowerDnsRecordManagementTest.php    ❌ Create
```

---

## Next Actions (Priority Order)

### Immediate (Today)
1. ✅ Review existing netserva-dns package (DONE)
2. Enable PowerDnsCommand and PowerDnsManagementCommand in service provider
3. Test connectivity: `php artisan ns:powerdns test 1`

### Short-term (This Week)
4. Extend PowerDnsService with record management methods
5. Create DnsVerifyCommand
6. Register new command in service provider
7. Test manual FCrDNS verification: `php artisan dns:verify markc.goldcoast.org 192.168.1.100`

### Medium-term (Next Week)
8. Integrate `--auto-dns` into fleet:discover
9. Add database migration for `fleet_vnodes.email_capable`
10. Write comprehensive tests

---

## Benefits of This Approach

### Reuse Existing Infrastructure ✅
- Leverages 84 existing PHP files
- Utilizes proven SSH tunnel management
- Uses write-through cache pattern
- Follows NetServa 3.0 patterns

### Minimal New Code
- ~200 lines for record methods in PowerDnsService
- ~150 lines for DnsVerifyCommand
- ~50 lines for service provider changes
- ~100 lines for fleet:discover integration
- **Total: ~500 lines** vs creating everything from scratch (~2000+ lines)

### Production Ready
- Existing services are battle-tested
- Error handling already comprehensive
- Logging and debugging built-in
- CLI interface follows Laravel conventions

---

## Conclusion

**DO NOT create new DNS commands from scratch**.

The netserva-dns package has:
- ✅ Complete PowerDNS service layer
- ✅ SSH tunnel management
- ✅ FCrDNS validation service
- ✅ Database models with write-through cache
- ✅ Filament UI resources
- ⚠️  Commands disabled (easy fix)
- ❌ Missing record CRUD methods (easy to add)
- ❌ Missing dns:verify command (wrapper around existing service)

**Recommended path**: Enable → Extend → Integrate → Test

**Time estimate**: 8-10 hours total vs 40+ hours from scratch

**Risk**: Low (building on proven infrastructure)
