<?php

use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;

uses()
    ->group('unit', 'models', 'dns-provider', 'priority-1');

it('can create a dns provider', function () {
    $provider = DnsProvider::factory()->create([
        'name' => 'test-cloudflare',
        'type' => 'cloudflare',
        'api_token' => 'test-token-123',
        'is_active' => true,
    ]);

    expect($provider)->toBeInstanceOf(DnsProvider::class)
        ->and($provider->name)->toBe('test-cloudflare')
        ->and($provider->type)->toBe('cloudflare')
        ->and($provider->is_active)->toBeTrue()
        ->and($provider->exists)->toBeTrue();
});

it('encrypts api credentials', function () {
    $provider = DnsProvider::factory()->create([
        'api_token' => 'secret-api-token-123',
    ]);

    // The stored value should be encrypted
    expect($provider->getAttributes()['api_token'])->not->toBe('secret-api-token-123')
        // But when accessed normally, it should be decrypted
        ->and($provider->api_token)->toBe('secret-api-token-123');
});

it('has many dns zones relationship', function () {
    $provider = DnsProvider::factory()->create();

    DnsZone::factory()->count(3)->create([
        'dns_provider_id' => $provider->id,
    ]);

    expect($provider->zones)->toHaveCount(3)
        ->and($provider->zones->first())->toBeInstanceOf(DnsZone::class);
});

it('can find active providers only', function () {
    DnsProvider::factory()->create(['is_active' => true]);
    DnsProvider::factory()->create(['is_active' => false]);

    $activeProviders = DnsProvider::active()->get();

    expect($activeProviders)->toHaveCount(1)
        ->and($activeProviders->first()->is_active)->toBeTrue();
});

it('can find providers by type', function () {
    DnsProvider::factory()->create(['type' => 'cloudflare']);
    DnsProvider::factory()->create(['type' => 'powerdns']);
    DnsProvider::factory()->create(['type' => 'cloudflare']);

    $cloudflareProviders = DnsProvider::byType('cloudflare')->get();

    expect($cloudflareProviders)->toHaveCount(2)
        ->and($cloudflareProviders->first()->type)->toBe('cloudflare');
});

it('validates provider type is supported', function () {
    $provider = DnsProvider::factory()->make([
        'type' => 'unsupported-provider',
    ]);

    expect(fn () => $provider->save())
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('can test connection', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'test-token',
    ]);

    $result = $provider->testConnection();

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('success')
        ->and($result['success'])->toBeTrue();
});

it('handles connection failure gracefully', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'invalid-token',
    ]);

    // Mock HTTP failure
    \Illuminate\Support\Facades\Http::fake([
        'api.cloudflare.com/*' => \Illuminate\Support\Facades\Http::response([], 401),
    ]);

    $result = $provider->testConnection();

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('success')
        ->and($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('error');
});

it('can get provider configuration', function () {
    $provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'test-token',
        'api_email' => 'test@example.com',
    ]);

    $config = $provider->getConfiguration();

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('type', 'cloudflare')
        ->and($config)->toHaveKey('api_token', 'test-token')
        ->and($config)->toHaveKey('api_email', 'test@example.com');
});

it('masks sensitive data in configuration export', function () {
    $provider = DnsProvider::factory()->create([
        'api_token' => 'secret-token-123456',
    ]);

    $safeConfig = $provider->getSafeConfiguration();

    expect($safeConfig['api_token'])->toBe('***...3456')
        ->and($safeConfig['api_token'])->not->toBe('secret-token-123456');
});

it('can calculate zone count', function () {
    $provider = DnsProvider::factory()->create();

    DnsZone::factory()->count(5)->create([
        'dns_provider_id' => $provider->id,
    ]);

    expect($provider->zoneCount())->toBe(5);
});

it('can get provider statistics', function () {
    $provider = DnsProvider::factory()->create();

    DnsZone::factory()->count(3)->create([
        'dns_provider_id' => $provider->id,
        'is_active' => true,
    ]);

    DnsZone::factory()->count(2)->create([
        'dns_provider_id' => $provider->id,
        'is_active' => false,
    ]);

    $stats = $provider->getStatistics();

    expect($stats)->toHaveKey('total_zones')
        ->and($stats['total_zones'])->toBe(5)
        ->and($stats)->toHaveKey('active_zones')
        ->and($stats['active_zones'])->toBe(3)
        ->and($stats)->toHaveKey('inactive_zones')
        ->and($stats['inactive_zones'])->toBe(2);
});

it('can check if provider supports dnssec', function () {
    $cloudflareProvider = DnsProvider::factory()->create(['type' => 'cloudflare']);
    $powerdnsProvider = DnsProvider::factory()->create(['type' => 'powerdns']);

    expect($cloudflareProvider->supportsDnssec())->toBeTrue()
        ->and($powerdnsProvider->supportsDnssec())->toBeTrue();
});

it('can update last sync timestamp', function () {
    $provider = DnsProvider::factory()->create([
        'last_sync_at' => null,
    ]);

    $provider->updateLastSync();

    expect($provider->fresh()->last_sync_at)->not->toBeNull()
        ->and($provider->fresh()->last_sync_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can check if sync is overdue', function () {
    $recentProvider = DnsProvider::factory()->create([
        'last_sync_at' => now()->subMinutes(30),
        'sync_interval' => 60, // minutes
    ]);

    $overdueProvider = DnsProvider::factory()->create([
        'last_sync_at' => now()->subHours(2),
        'sync_interval' => 60, // minutes
    ]);

    expect($recentProvider->isSyncOverdue())->toBeFalse()
        ->and($overdueProvider->isSyncOverdue())->toBeTrue();
});

it('prevents duplicate provider names', function () {
    DnsProvider::factory()->create(['name' => 'my-cloudflare']);

    expect(fn () => DnsProvider::factory()->create(['name' => 'my-cloudflare']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
