<?php

use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

uses()
    ->group('unit', 'models', 'dns-zone', 'priority-1');

it('can create a dns zone', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'example.com',
        'type' => 'NATIVE',
    ]);

    expect($zone)->toBeInstanceOf(DnsZone::class)
        ->and($zone->domain)->toBe('example.com')
        ->and($zone->type)->toBe('NATIVE')
        ->and($zone->exists)->toBeTrue();
});

it('validates domain format', function () {
    $zone = DnsZone::factory()->make([
        'domain' => 'example.com',
    ]);

    expect($zone->domain)->toBeValidDomain();
});

it('generates SOA record automatically', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'test.example.com',
    ]);

    expect($zone->soa_record)->not->toBeNull()
        ->and($zone->soa_record)->toContain('test.example.com')
        ->and($zone->soa_record)->toContain('hostmaster.test.example.com');
});

it('increments serial number on update', function () {
    $zone = DnsZone::factory()->create();
    $originalSerial = $zone->serial;

    $zone->update(['dnssec' => true]);

    expect($zone->fresh()->serial)->toBeGreaterThan($originalSerial);
});

it('has many dns records relationship', function () {
    $zone = DnsZone::factory()->create();

    DnsRecord::factory()->count(3)->create([
        'zone_id' => $zone->id,
    ]);

    expect($zone->records)->toHaveCount(3)
        ->and($zone->records->first())->toBeInstanceOf(DnsRecord::class);
});

it('belongs to dns provider', function () {
    $provider = DnsProvider::factory()->create();
    $zone = DnsZone::factory()->create([
        'dns_provider_id' => $provider->id,
    ]);

    expect($zone->provider)->toBeInstanceOf(DnsProvider::class)
        ->and($zone->provider->id)->toBe($provider->id);
});

it('can find zones by domain', function () {
    DnsZone::factory()->create(['domain' => 'example.com']);
    DnsZone::factory()->create(['domain' => 'test.org']);

    $zones = DnsZone::byDomain('example.com')->get();

    expect($zones)->toHaveCount(1)
        ->and($zones->first()->domain)->toBe('example.com');
});

it('can find active zones only', function () {
    DnsZone::factory()->create(['is_active' => true]);
    DnsZone::factory()->create(['is_active' => false]);

    $activeZones = DnsZone::active()->get();

    expect($activeZones)->toHaveCount(1)
        ->and($activeZones->first()->is_active)->toBeTrue();
});

it('can export to bind format', function () {
    $zone = DnsZone::factory()->create([
        'domain' => 'example.com',
    ]);

    DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'www.example.com',
        'type' => 'A',
        'content' => '192.168.1.100',
    ]);

    $bindFormat = $zone->toBindFormat();

    expect($bindFormat)->toContain('$ORIGIN example.com.')
        ->and($bindFormat)->toContain('www.example.com')
        ->and($bindFormat)->toContain('192.168.1.100');
});

it('validates zone type is supported', function () {
    $zone = DnsZone::factory()->make([
        'type' => 'INVALID_TYPE',
    ]);

    expect(fn () => $zone->save())
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('prevents duplicate domains', function () {
    DnsZone::factory()->create(['domain' => 'example.com']);

    expect(fn () => DnsZone::factory()->create(['domain' => 'example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can calculate zone size', function () {
    $zone = DnsZone::factory()->create();

    DnsRecord::factory()->count(5)->create([
        'zone_id' => $zone->id,
    ]);

    expect($zone->recordCount())->toBe(5);
});

it('can check if dnssec is enabled', function () {
    $zoneWithDnssec = DnsZone::factory()->create(['dnssec' => true]);
    $zoneWithoutDnssec = DnsZone::factory()->create(['dnssec' => false]);

    expect($zoneWithDnssec->isDnssecEnabled())->toBeTrue()
        ->and($zoneWithoutDnssec->isDnssecEnabled())->toBeFalse();
});

it('can get zone statistics', function () {
    $zone = DnsZone::factory()->create();

    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'A']);
    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'A']);
    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'MX']);

    $stats = $zone->getStatistics();

    expect($stats)->toHaveKey('total_records')
        ->and($stats['total_records'])->toBe(3)
        ->and($stats['record_types'])->toHaveKey('A')
        ->and($stats['record_types']['A'])->toBe(2)
        ->and($stats['record_types'])->toHaveKey('MX')
        ->and($stats['record_types']['MX'])->toBe(1);
});
