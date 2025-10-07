<?php

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;
use NetServa\Dns\Services\DnsProviderService;

uses()
    ->group('performance', 'dns-provider', 'priority-5');

beforeEach(function () {
    $this->provider = DnsProvider::factory()->create(['type' => 'cloudflare']);
    $this->service = new DnsProviderService;
});

it('can handle bulk zone creation efficiently', function () {
    $zoneCount = 100;
    $startTime = microtime(true);

    // Create zones in batches
    $zones = collect();
    for ($i = 0; $i < $zoneCount; $i++) {
        $zones->push([
            'domain' => "test-{$i}.example.com",
            'dns_provider_id' => $this->provider->id,
            'ttl' => 3600,
            'is_active' => true,
        ]);
    }

    DnsZone::insert($zones->toArray());

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(5.0) // Should complete in under 5 seconds
        ->and(DnsZone::count())->toBe($zoneCount);
});

it('can handle bulk record creation efficiently', function () {
    $zone = DnsZone::factory()->create(['dns_provider_id' => $this->provider->id]);
    $recordCount = 1000;
    $startTime = microtime(true);

    // Create records in batches
    $records = collect();
    for ($i = 0; $i < $recordCount; $i++) {
        $ipOctet = ($i % 254) + 1;
        $records->push([
            'dns_zone_id' => $zone->id,
            'type' => 'A',
            'name' => "host-{$i}.{$zone->domain}",
            'content' => "192.168.1.{$ipOctet}",
            'ttl' => 300,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DnsRecord::insert($records->toArray());

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(10.0) // Should complete in under 10 seconds
        ->and(DnsRecord::count())->toBe($recordCount);
});

it('can query large zone datasets efficiently', function () {
    $zone = DnsZone::factory()->create(['dns_provider_id' => $this->provider->id]);

    // Create 5000 records
    DnsRecord::factory()->count(5000)->create(['dns_zone_id' => $zone->id]);

    $startTime = microtime(true);

    // Query all records for the zone
    $records = $zone->records()->get();

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(1.0) // Should query in under 1 second
        ->and($records)->toHaveCount(5000);
});

it('can perform zone search efficiently with large dataset', function () {
    // Create 1000 zones
    $zones = collect();
    for ($i = 0; $i < 1000; $i++) {
        $zones->push([
            'domain' => "search-test-{$i}.example.com",
            'dns_provider_id' => $this->provider->id,
            'ttl' => 3600,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    DnsZone::insert($zones->toArray());

    $startTime = microtime(true);

    // Search for specific zones
    $results = DnsZone::where('domain', 'like', '%test-5%')->get();

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(0.5) // Should search in under 0.5 seconds
        ->and($results->count())->toBeGreaterThan(0);
});

it('can sync multiple zones from provider efficiently', function () {
    // Mock provider API responses for 50 zones
    $mockZones = collect();
    for ($i = 0; $i < 50; $i++) {
        $mockZones->push([
            'id' => "cf-zone-{$i}",
            'name' => "sync-test-{$i}.com",
            'status' => 'active',
        ]);
    }

    Http::fake([
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => $mockZones->toArray(),
        ], 200),
        'api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response([
            'success' => true,
            'result' => [],
        ], 200),
    ]);

    $startTime = microtime(true);

    $result = $this->service->syncZonesFromProvider($this->provider);

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(30.0) // Should sync 50 zones in under 30 seconds
        ->and($result['synced_count'])->toBe(50);
});

it('can handle concurrent zone operations', function () {
    $zones = DnsZone::factory()->count(10)->create(['dns_provider_id' => $this->provider->id]);

    $startTime = microtime(true);

    // Simulate concurrent operations
    $promises = collect();
    foreach ($zones as $zone) {
        $promises->push(function () use ($zone) {
            // Simulate some zone processing
            $zone->load('records');
            $zone->update(['last_sync_at' => now()]);

            return $zone;
        });
    }

    // Execute operations
    $results = collect($promises)->map(fn ($promise) => $promise());

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(2.0) // Should handle 10 operations in under 2 seconds
        ->and($results)->toHaveCount(10);
});

it('can generate zone configuration efficiently for large zones', function () {
    $zone = DnsZone::factory()->create(['dns_provider_id' => $this->provider->id]);

    // Create 1000 records of various types
    DnsRecord::factory()->count(500)->create([
        'dns_zone_id' => $zone->id,
        'type' => 'A',
    ]);

    DnsRecord::factory()->count(300)->create([
        'dns_zone_id' => $zone->id,
        'type' => 'AAAA',
    ]);

    DnsRecord::factory()->count(100)->create([
        'dns_zone_id' => $zone->id,
        'type' => 'CNAME',
    ]);

    DnsRecord::factory()->count(100)->create([
        'dns_zone_id' => $zone->id,
        'type' => 'MX',
    ]);

    $startTime = microtime(true);

    $bindConfig = $zone->generateBindConfig();

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(3.0) // Should generate config in under 3 seconds
        ->and($bindConfig)->toContain('$TTL')
        ->and($bindConfig)->toContain('SOA')
        ->and(substr_count($bindConfig, "\n"))->toBeGreaterThan(1000); // Should have all records
});

it('can validate large batches of DNS records efficiently', function () {
    $zone = DnsZone::factory()->create(['dns_provider_id' => $this->provider->id]);

    // Create test data for validation
    $recordData = collect();
    for ($i = 0; $i < 500; $i++) {
        $recordData->push([
            'type' => 'A',
            'name' => "test-{$i}.{$zone->domain}",
            'content' => '192.168.'.floor($i / 254).'.'.($i % 254 + 1),
            'ttl' => 300,
        ]);
    }

    $startTime = microtime(true);

    // Validate all records
    $validRecords = $recordData->filter(function ($record) {
        return filter_var($record['content'], FILTER_VALIDATE_IP) !== false;
    });

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(1.0) // Should validate 500 records in under 1 second
        ->and($validRecords)->toHaveCount(500);
});

it('measures memory usage during large operations', function () {
    $initialMemory = memory_get_usage();

    // Create a large zone with many records
    $zone = DnsZone::factory()->create(['dns_provider_id' => $this->provider->id]);
    DnsRecord::factory()->count(2000)->create(['dns_zone_id' => $zone->id]);

    // Load zone with all records
    $zoneWithRecords = DnsZone::with('records')->find($zone->id);

    $finalMemory = memory_get_usage();
    $memoryUsed = ($finalMemory - $initialMemory) / 1024 / 1024; // Convert to MB

    expect($memoryUsed)->toBeLessThan(50) // Should use less than 50MB
        ->and($zoneWithRecords->records)->toHaveCount(2000);
});
