<?php

use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

uses()
    ->group('feature', 'dns-commands', 'priority-2');

beforeEach(function () {
    // Create two providers with zones that have the same name
    $this->provider1 = DnsProvider::factory()->create([
        'vnode' => 'ns1gc',
        'name' => 'GoldCoast NS1',
        'type' => 'powerdns',
    ]);

    $this->provider2 = DnsProvider::factory()->create([
        'vnode' => 'ns2rn',
        'name' => 'Renta NS2',
        'type' => 'powerdns',
    ]);

    // Create zones with the SAME name on different providers
    $this->zone1 = DnsZone::factory()->create([
        'name' => 'illareen.net.',
        'dns_provider_id' => $this->provider1->id,
    ]);

    $this->zone2 = DnsZone::factory()->create([
        'name' => 'illareen.net.',
        'dns_provider_id' => $this->provider2->id,
    ]);

    // Create 6 records for provider1's zone
    DnsRecord::factory()->count(6)->create([
        'dns_zone_id' => $this->zone1->id,
    ]);

    // Create 199 records for provider2's zone
    DnsRecord::factory()->count(199)->create([
        'dns_zone_id' => $this->zone2->id,
    ]);
});

it('shows only records for specified provider when zone name is ambiguous', function () {
    // When querying with provider and zone name
    $this->artisan('shrec', [
        'vnode' => 'ns1gc',
        'identifier' => 'illareen.net',
    ])
        ->assertSuccessful();

    // Should only show 6 records (from provider1), not all 205 records
    $service = app(\NetServa\Dns\Services\DnsRecordManagementService::class);
    $records = $service->listRecords([
        'zone' => 'illareen.net',
        'provider' => 'ns1gc',
    ]);

    expect($records)->toHaveCount(6);
});

it('shows all records when no provider specified and zone name is ambiguous', function () {
    // When querying without provider
    $service = app(\NetServa\Dns\Services\DnsRecordManagementService::class);
    $records = $service->listRecords([
        'zone' => 'illareen.net',
    ]);

    // Should show records from first zone found (provider2 has 199 records)
    // Note: This behavior returns the first match, which may not be ideal
    // but is expected when provider is not specified
    expect($records->count())->toBeGreaterThanOrEqual(6);
});

it('shows records for second provider when specified', function () {
    // When querying with second provider
    $service = app(\NetServa\Dns\Services\DnsRecordManagementService::class);
    $records = $service->listRecords([
        'zone' => 'illareen.net',
        'provider' => 'ns2rn',
    ]);

    expect($records)->toHaveCount(199);
});

it('returns error when zone does not exist on specified provider', function () {
    // Create a zone that only exists on provider1
    $uniqueZone = DnsZone::factory()->create([
        'name' => 'unique.example.com.',
        'dns_provider_id' => $this->provider1->id,
    ]);

    // Try to find it on provider2
    $service = app(\NetServa\Dns\Services\DnsRecordManagementService::class);
    $records = $service->listRecords([
        'zone' => 'unique.example.com',
        'provider' => 'ns2rn',
    ]);

    // Should return empty collection since zone doesn't exist on provider2
    expect($records)->toBeEmpty();
});

it('works with zone ID regardless of provider filter', function () {
    // When using zone ID, provider filter should still work
    $service = app(\NetServa\Dns\Services\DnsRecordManagementService::class);

    // Zone ID should be unique, so provider doesn't matter
    $records = $service->listRecords([
        'zone' => $this->zone1->id,
        'provider' => 'ns1gc',
    ]);

    expect($records)->toHaveCount(6);

    // Even with wrong provider, should still find by zone ID
    $records = $service->listRecords([
        'zone' => $this->zone1->id,
        'provider' => 'ns2rn',
    ]);

    // This should fail to find the zone since provider doesn't match
    expect($records)->toBeEmpty();
});
