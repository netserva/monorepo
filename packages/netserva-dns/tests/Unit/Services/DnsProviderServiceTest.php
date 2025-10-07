<?php

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;
use NetServa\Dns\Services\DnsProviderService;

uses()
    ->group('unit', 'services', 'dns-provider-service', 'priority-1');

beforeEach(function () {
    $this->service = new DnsProviderService;
});

it('can create provider with validation', function () {
    $data = [
        'name' => 'test-cloudflare',
        'type' => 'cloudflare',
        'api_token' => 'test-token-123',
        'api_email' => 'test@example.com',
        'is_active' => true,
    ];

    $provider = $this->service->createProvider($data);

    expect($provider)->toBeInstanceOf(DnsProvider::class)
        ->and($provider->name)->toBe('test-cloudflare')
        ->and($provider->type)->toBe('cloudflare')
        ->and($provider->is_active)->toBeTrue();
});

it('validates provider configuration before creation', function () {
    $invalidData = [
        'name' => '',
        'type' => 'invalid-type',
        'api_token' => '',
    ];

    expect(fn () => $this->service->createProvider($invalidData))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('can test provider connectivity', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'test-token',
    ]);

    Http::fake([
        'api.cloudflare.com/*' => Http::response([
            'success' => true,
            'result' => ['id' => 'test-token-id'],
        ], 200),
    ]);

    $result = $this->service->testProviderConnection($provider);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result)->toHaveKey('response_time')
        ->and($result['response_time'])->toBeGreaterThan(0);
});

it('handles connectivity test failures', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'invalid-token',
    ]);

    Http::fake([
        'api.cloudflare.com/*' => Http::response([], 401),
    ]);

    $result = $this->service->testProviderConnection($provider);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('error')
        ->and($result['error'])->toContain('Authentication failed');
});

it('can sync zones from provider', function () {
    $provider = DnsProvider::factory()->create(['type' => 'cloudflare']);

    Http::fake([
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'zone-123',
                    'name' => 'example.com',
                    'status' => 'active',
                ],
                [
                    'id' => 'zone-456',
                    'name' => 'test.org',
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $result = $this->service->syncZonesFromProvider($provider);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('synced_count')
        ->and($result['synced_count'])->toBe(2)
        ->and($result)->toHaveKey('zones')
        ->and($result['zones'])->toHaveCount(2);
});

it('can get provider statistics', function () {
    $provider = DnsProvider::factory()->create();

    DnsZone::factory()->count(5)->create([
        'dns_provider_id' => $provider->id,
        'is_active' => true,
    ]);

    DnsZone::factory()->count(2)->create([
        'dns_provider_id' => $provider->id,
        'is_active' => false,
    ]);

    $stats = $this->service->getProviderStatistics($provider);

    expect($stats)->toBeArray()
        ->and($stats['total_zones'])->toBe(7)
        ->and($stats['active_zones'])->toBe(5)
        ->and($stats['inactive_zones'])->toBe(2)
        ->and($stats)->toHaveKey('last_sync_at')
        ->and($stats)->toHaveKey('health_status');
});

it('can validate zone data before creation', function () {
    $validZoneData = [
        'domain' => 'example.com',
        'type' => 'NATIVE',
        'ttl' => 3600,
    ];

    $invalidZoneData = [
        'domain' => 'invalid..domain',
        'type' => 'UNKNOWN',
        'ttl' => -1,
    ];

    expect($this->service->validateZoneData($validZoneData))->toBeTrue();

    expect(fn () => $this->service->validateZoneData($invalidZoneData))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('can bulk update provider zones', function () {
    $provider = DnsProvider::factory()->create();

    $zones = DnsZone::factory()->count(3)->create([
        'dns_provider_id' => $provider->id,
    ]);

    $updateData = [
        'ttl' => 7200,
        'is_active' => false,
    ];

    $result = $this->service->bulkUpdateZones($zones->pluck('id')->toArray(), $updateData);

    expect($result)->toBeArray()
        ->and($result['updated_count'])->toBe(3);

    // Verify updates were applied
    $zones->each(function ($zone) {
        expect($zone->fresh()->ttl)->toBe(7200)
            ->and($zone->fresh()->is_active)->toBeFalse();
    });
});

it('can detect configuration drift', function () {
    $provider = DnsProvider::factory()->create(['type' => 'cloudflare']);

    // Create local zone
    $localZone = DnsZone::factory()->create([
        'dns_provider_id' => $provider->id,
        'domain' => 'example.com',
    ]);

    // Mock remote zones (missing the local one)
    Http::fake([
        'api.cloudflare.com/client/v4/zones*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'zone-remote',
                    'name' => 'different.com',
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $drift = $this->service->detectConfigurationDrift($provider);

    expect($drift)->toBeArray()
        ->and($drift)->toHaveKey('local_only')
        ->and($drift)->toHaveKey('remote_only')
        ->and($drift['local_only'])->toContain('example.com')
        ->and($drift['remote_only'])->toContain('different.com');
});

it('can handle provider rate limiting', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'rate_limit_per_minute' => 10,
    ]);

    // Mock rate limit response
    Http::fake([
        'api.cloudflare.com/*' => Http::sequence()
            ->push(['errors' => [['code' => 10013]]], 429)
            ->push(['success' => true, 'result' => []], 200),
    ]);

    $result = $this->service->testProviderConnection($provider);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result)->toHaveKey('rate_limited')
        ->and($result['rate_limited'])->toBeTrue();
});

it('can backup provider configuration', function () {
    $provider = DnsProvider::factory()->create();

    DnsZone::factory()->count(2)->create([
        'dns_provider_id' => $provider->id,
    ]);

    $backup = $this->service->backupProviderConfiguration($provider);

    expect($backup)->toBeArray()
        ->and($backup)->toHaveKey('provider')
        ->and($backup)->toHaveKey('zones')
        ->and($backup['zones'])->toHaveCount(2)
        ->and($backup)->toHaveKey('timestamp');
});

it('can restore from backup', function () {
    $provider = DnsProvider::factory()->create();

    $backupData = [
        'provider' => [
            'name' => 'restored-provider',
            'type' => 'cloudflare',
        ],
        'zones' => [
            ['domain' => 'restored1.com', 'type' => 'NATIVE'],
            ['domain' => 'restored2.com', 'type' => 'NATIVE'],
        ],
        'timestamp' => now()->toISOString(),
    ];

    $result = $this->service->restoreFromBackup($provider, $backupData);

    expect($result)->toBeArray()
        ->and($result['restored_zones'])->toBe(2)
        ->and(DnsZone::where('dns_provider_id', $provider->id)->count())->toBe(2);
});

it('can monitor provider health', function () {
    $provider = DnsProvider::factory()->create();

    $health = $this->service->monitorProviderHealth($provider);

    expect($health)->toBeArray()
        ->and($health)->toHaveKey('status')
        ->and($health)->toHaveKey('response_time')
        ->and($health)->toHaveKey('error_rate')
        ->and($health)->toHaveKey('last_check_at');
});
