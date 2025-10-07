<?php

use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;

uses()
    ->group('unit', 'models', 'dns-record', 'priority-1');

it('can create a dns record', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'www.test.example.com',
        'type' => 'A',
        'content' => '192.168.1.100',
        'ttl' => 3600,
    ]);

    expect($record)->toBeInstanceOf(DnsRecord::class)
        ->and($record->name)->toBe('www.test.example.com')
        ->and($record->type)->toBe('A')
        ->and($record->content)->toBe('192.168.1.100')
        ->and($record->ttl)->toBe(3600)
        ->and($record->exists)->toBeTrue();
});

it('belongs to a dns zone', function () {
    $zone = createTestDnsZone();
    $record = DnsRecord::factory()->create(['zone_id' => $zone->id]);

    expect($record->zone)->toBeInstanceOf(DnsZone::class)
        ->and($record->zone->id)->toBe($zone->id);
});

it('validates A record content is valid IPv4', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->make([
        'zone_id' => $zone->id,
        'type' => 'A',
        'content' => '192.168.1.100',
    ]);

    expect($record->content)->toBeValidIpv4();
});

it('validates AAAA record content is valid IPv6', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->make([
        'zone_id' => $zone->id,
        'type' => 'AAAA',
        'content' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
    ]);

    expect($record->content)->toBeValidIpv6();
});

it('validates CNAME record content is valid domain', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->make([
        'zone_id' => $zone->id,
        'type' => 'CNAME',
        'content' => 'target.example.com',
    ]);

    expect($record->content)->toBeValidDomain();
});

it('validates MX record has priority', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'type' => 'MX',
        'content' => '10 mail.example.com',
        'priority' => 10,
    ]);

    expect($record->priority)->toBe(10)
        ->and($record->content)->toContain('mail.example.com');
});

it('can find records by type', function () {
    $zone = createTestDnsZone();

    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'A']);
    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'A']);
    DnsRecord::factory()->create(['zone_id' => $zone->id, 'type' => 'MX']);

    $aRecords = DnsRecord::byType('A')->get();

    expect($aRecords)->toHaveCount(2)
        ->and($aRecords->first()->type)->toBe('A');
});

it('can find records by name', function () {
    $zone = createTestDnsZone();

    DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'www.test.example.com',
    ]);

    DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'mail.test.example.com',
    ]);

    $records = DnsRecord::byName('www.test.example.com')->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()->name)->toBe('www.test.example.com');
});

it('updates zone serial when record is created', function () {
    $zone = createTestDnsZone();
    $originalSerial = $zone->serial;

    DnsRecord::factory()->create(['zone_id' => $zone->id]);

    expect($zone->fresh()->serial)->toBeGreaterThan($originalSerial);
});

it('updates zone serial when record is updated', function () {
    $zone = createTestDnsZone();
    $record = DnsRecord::factory()->create(['zone_id' => $zone->id]);

    $originalSerial = $zone->fresh()->serial;

    $record->update(['content' => '192.168.1.200']);

    expect($zone->fresh()->serial)->toBeGreaterThan($originalSerial);
});

it('updates zone serial when record is deleted', function () {
    $zone = createTestDnsZone();
    $record = DnsRecord::factory()->create(['zone_id' => $zone->id]);

    $originalSerial = $zone->fresh()->serial;

    $record->delete();

    expect($zone->fresh()->serial)->toBeGreaterThan($originalSerial);
});

it('can format as bind record', function () {
    $zone = createTestDnsZone(['domain' => 'example.com']);

    $record = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'www.example.com',
        'type' => 'A',
        'content' => '192.168.1.100',
        'ttl' => 3600,
    ]);

    $bindFormat = $record->toBindFormat();

    expect($bindFormat)->toBe('www.example.com. 3600 IN A 192.168.1.100');
});

it('prevents creating CNAME with other record types for same name', function () {
    $zone = createTestDnsZone();

    DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'test.example.com',
        'type' => 'A',
        'content' => '192.168.1.100',
    ]);

    expect(fn () => DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'test.example.com',
        'type' => 'CNAME',
        'content' => 'target.example.com',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('can check if record is wildcard', function () {
    $zone = createTestDnsZone();

    $wildcardRecord = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => '*.example.com',
    ]);

    $normalRecord = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'www.example.com',
    ]);

    expect($wildcardRecord->isWildcard())->toBeTrue()
        ->and($normalRecord->isWildcard())->toBeFalse();
});

it('can validate TTL is within acceptable range', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->make([
        'zone_id' => $zone->id,
        'ttl' => 300,
    ]);

    expect($record->ttl)->toBeGreaterThanOrEqual(300)
        ->and($record->ttl)->toBeLessThanOrEqual(86400);
});

it('normalizes record name to lowercase', function () {
    $zone = createTestDnsZone();

    $record = DnsRecord::factory()->create([
        'zone_id' => $zone->id,
        'name' => 'WWW.EXAMPLE.COM',
    ]);

    expect($record->fresh()->name)->toBe('www.example.com');
});
