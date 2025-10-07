<?php

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;
use NetServa\Dns\Services\CloudFlareService;
use NetServa\Dns\Services\DnsProviderService;

uses()
    ->group('integration', 'dns-management', 'priority-3');

beforeEach(function () {
    $this->provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'test-token-123',
    ]);

    $this->zone = DnsZone::factory()->create([
        'domain' => 'test.example.com',
        'dns_provider_id' => $this->provider->id,
    ]);
});

it('can create zone with records end-to-end', function () {
    // Mock CloudFlare API responses
    Http::fake([
        'api.cloudflare.com/client/v4/zones' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'cf-zone-123',
                'name' => 'test.example.com',
                'status' => 'active',
            ],
        ], 200),
        'api.cloudflare.com/client/v4/zones/*/dns_records' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'cf-record-123',
                'type' => 'A',
                'name' => 'test.example.com',
                'content' => '192.168.1.100',
            ],
        ], 200),
    ]);

    $dnsService = new DnsProviderService;
    $cloudflareService = new CloudFlareService($this->provider);

    // Create zone in CloudFlare
    $zoneResult = $cloudflareService->createZone($this->zone->domain);
    expect($zoneResult['success'])->toBeTrue();

    // Update zone with external ID
    $this->zone->update(['external_zone_id' => $zoneResult['result']['id']]);

    // Create A record
    $record = DnsRecord::factory()->create([
        'dns_zone_id' => $this->zone->id,
        'type' => 'A',
        'name' => 'test.example.com',
        'content' => '192.168.1.100',
    ]);

    // Sync record to provider
    $recordResult = $cloudflareService->createRecord($record);
    expect($recordResult['success'])->toBeTrue();

    // Update record with external ID
    $record->update(['external_record_id' => $recordResult['result']['id']]);

    // Verify final state
    expect($this->zone->fresh())
        ->external_zone_id->toBe('cf-zone-123')
        ->and($record->fresh())
        ->external_record_id->toBe('cf-record-123');
});

it('can sync zones from provider to local database', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'cf-zone-1',
                    'name' => 'example.com',
                    'status' => 'active',
                ],
                [
                    'id' => 'cf-zone-2',
                    'name' => 'test.org',
                    'status' => 'active',
                ],
            ],
        ], 200),
        'api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'cf-record-1',
                    'type' => 'A',
                    'name' => 'example.com',
                    'content' => '192.168.1.100',
                ],
                [
                    'id' => 'cf-record-2',
                    'type' => 'MX',
                    'name' => 'example.com',
                    'content' => 'mail.example.com',
                    'priority' => 10,
                ],
            ],
        ], 200),
    ]);

    $dnsService = new DnsProviderService;

    // Sync zones from provider
    $result = $dnsService->syncZonesFromProvider($this->provider);

    expect($result['synced_count'])->toBe(2);

    // Verify zones were created
    $this->assertDatabaseHas('dns_zones', [
        'domain' => 'example.com',
        'external_zone_id' => 'cf-zone-1',
        'dns_provider_id' => $this->provider->id,
    ]);

    $this->assertDatabaseHas('dns_zones', [
        'domain' => 'test.org',
        'external_zone_id' => 'cf-zone-2',
        'dns_provider_id' => $this->provider->id,
    ]);

    // Verify records were created for the zones
    $exampleZone = DnsZone::where('domain', 'example.com')->first();
    expect($exampleZone->records)->toHaveCount(2);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $exampleZone->id,
        'type' => 'A',
        'content' => '192.168.1.100',
        'external_record_id' => 'cf-record-1',
    ]);

    $this->assertDatabaseHas('dns_records', [
        'dns_zone_id' => $exampleZone->id,
        'type' => 'MX',
        'content' => 'mail.example.com',
        'priority' => 10,
        'external_record_id' => 'cf-record-2',
    ]);
});

it('can detect and resolve configuration drift', function () {
    // Create local records
    $aRecord = DnsRecord::factory()->create([
        'dns_zone_id' => $this->zone->id,
        'type' => 'A',
        'name' => 'test.example.com',
        'content' => '192.168.1.100',
        'external_record_id' => 'local-only-record',
    ]);

    // Mock remote records (different from local)
    Http::fake([
        'api.cloudflare.com/client/v4/zones/*/dns_records*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'remote-only-record',
                    'type' => 'CNAME',
                    'name' => 'www.test.example.com',
                    'content' => 'test.example.com',
                ],
            ],
        ], 200),
    ]);

    $dnsService = new DnsProviderService;

    // Detect drift
    $drift = $dnsService->detectConfigurationDrift($this->provider);

    expect($drift)->toHaveKey('local_only')
        ->and($drift)->toHaveKey('remote_only')
        ->and($drift['local_only'])->toContain('test.example.com (A)')
        ->and($drift['remote_only'])->toContain('www.test.example.com (CNAME)');
});

it('can handle provider rate limiting gracefully', function () {
    // Mock rate limit response followed by success
    Http::fake([
        'api.cloudflare.com/client/v4/zones' => Http::sequence()
            ->push(['errors' => [['code' => 10013]]], 429)
            ->push(['success' => true, 'result' => ['id' => 'zone-123']], 200),
    ]);

    $cloudflareService = new CloudFlareService($this->provider);

    // Should handle rate limit and retry
    $result = $cloudflareService->createZone('rate-limit-test.com');

    expect($result['success'])->toBeTrue()
        ->and($result['result']['id'])->toBe('zone-123');

    // Should have made 2 requests (first rate limited, second successful)
    Http::assertSentCount(2);
});

it('can perform full backup and restore cycle', function () {
    // Create additional records for comprehensive backup
    DnsRecord::factory()->count(5)->create([
        'dns_zone_id' => $this->zone->id,
    ]);

    $dnsService = new DnsProviderService;

    // Create backup
    $backup = $dnsService->backupProviderConfiguration($this->provider);

    expect($backup)->toHaveKey('provider')
        ->and($backup)->toHaveKey('zones')
        ->and($backup)->toHaveKey('timestamp')
        ->and($backup['zones'])->toHaveCount(1)
        ->and($backup['zones'][0]['records'])->toHaveCount(5);

    // Clear existing data
    DnsRecord::where('dns_zone_id', $this->zone->id)->delete();
    $this->zone->delete();

    // Verify data is gone
    expect(DnsZone::where('dns_provider_id', $this->provider->id)->count())->toBe(0);

    // Restore from backup
    $result = $dnsService->restoreFromBackup($this->provider, $backup);

    expect($result['restored_zones'])->toBe(1)
        ->and($result['restored_records'])->toBe(5);

    // Verify restoration
    $restoredZone = DnsZone::where('domain', 'test.example.com')->first();
    expect($restoredZone)->not->toBeNull()
        ->and($restoredZone->records)->toHaveCount(5);
});

it('can monitor provider health across multiple operations', function () {
    Http::fake([
        '*' => Http::response(['success' => true], 200, ['X-Response-Time' => '150']),
    ]);

    $dnsService = new DnsProviderService;

    // Perform multiple operations to generate health data
    $health1 = $dnsService->monitorProviderHealth($this->provider);
    $health2 = $dnsService->monitorProviderHealth($this->provider);
    $health3 = $dnsService->monitorProviderHealth($this->provider);

    // Each health check should have required fields
    foreach ([$health1, $health2, $health3] as $health) {
        expect($health)->toHaveKey('status')
            ->and($health)->toHaveKey('response_time')
            ->and($health)->toHaveKey('error_rate')
            ->and($health)->toHaveKey('last_check_at')
            ->and($health['status'])->toBeIn(['healthy', 'warning', 'error'])
            ->and($health['response_time'])->toBeGreaterThan(0);
    }
});
