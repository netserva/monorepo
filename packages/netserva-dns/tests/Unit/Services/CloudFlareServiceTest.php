<?php

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\CloudFlareService;

uses()
    ->group('unit', 'services', 'cloudflare', 'priority-1');

beforeEach(function () {
    $this->provider = DnsProvider::factory()->create([
        'type' => 'cloudflare',
        'api_token' => 'test-cf-token-123',
        'api_email' => 'test@example.com',
    ]);

    $this->service = new CloudFlareService($this->provider);
});

it('can initialize with provider', function () {
    expect($this->service)->toBeInstanceOf(CloudFlareService::class);
});

it('can test connection successfully', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'test-token-id',
                'status' => 'active',
            ],
        ], 200),
    ]);

    $result = $this->service->testConnection();

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result)->toHaveKey('token_info');
});

it('handles connection failure', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/user/tokens/verify' => Http::response([
            'success' => false,
            'errors' => [['message' => 'Invalid token']],
        ], 401),
    ]);

    $result = $this->service->testConnection();

    expect($result)->toBeArray()
        ->and($result['success'])->toBeFalse()
        ->and($result)->toHaveKey('error');
});

it('can list zones', function () {
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
                    'name' => 'test.com',
                    'status' => 'active',
                ],
            ],
            'result_info' => [
                'page' => 1,
                'per_page' => 50,
                'count' => 2,
                'total_count' => 2,
            ],
        ], 200),
    ]);

    $zones = $this->service->listZones();

    expect($zones)->toBeArray()
        ->and($zones)->toHaveCount(2)
        ->and($zones[0])->toHaveKey('id', 'zone-123')
        ->and($zones[0])->toHaveKey('name', 'example.com');
});

it('can create zone', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'new-zone-123',
                'name' => 'newdomain.com',
                'status' => 'pending',
            ],
        ], 200),
    ]);

    $result = $this->service->createZone('newdomain.com');

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['zone'])->toHaveKey('id', 'new-zone-123')
        ->and($result['zone'])->toHaveKey('name', 'newdomain.com');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones' &&
               $request->method() === 'POST' &&
               $request->data()['name'] === 'newdomain.com';
    });
});

it('can delete zone', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123' => Http::response([
            'success' => true,
            'result' => ['id' => 'zone-123'],
        ], 200),
    ]);

    $result = $this->service->deleteZone('zone-123');

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-123' &&
               $request->method() === 'DELETE';
    });
});

it('can list dns records', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/dns_records*' => Http::response([
            'success' => true,
            'result' => [
                [
                    'id' => 'record-123',
                    'type' => 'A',
                    'name' => 'www.example.com',
                    'content' => '192.168.1.100',
                    'ttl' => 3600,
                ],
                [
                    'id' => 'record-456',
                    'type' => 'MX',
                    'name' => 'example.com',
                    'content' => 'mail.example.com',
                    'priority' => 10,
                ],
            ],
        ], 200),
    ]);

    $records = $this->service->listDnsRecords('zone-123');

    expect($records)->toBeArray()
        ->and($records)->toHaveCount(2)
        ->and($records[0])->toHaveKey('type', 'A')
        ->and($records[1])->toHaveKey('type', 'MX');
});

it('can create dns record', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/dns_records' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'new-record-123',
                'type' => 'A',
                'name' => 'api.example.com',
                'content' => '192.168.1.200',
            ],
        ], 200),
    ]);

    $recordData = [
        'type' => 'A',
        'name' => 'api.example.com',
        'content' => '192.168.1.200',
        'ttl' => 3600,
    ];

    $result = $this->service->createDnsRecord('zone-123', $recordData);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['record'])->toHaveKey('id', 'new-record-123');
});

it('can update dns record', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/dns_records/record-123' => Http::response([
            'success' => true,
            'result' => [
                'id' => 'record-123',
                'type' => 'A',
                'name' => 'api.example.com',
                'content' => '192.168.1.250',
            ],
        ], 200),
    ]);

    $updateData = ['content' => '192.168.1.250'];

    $result = $this->service->updateDnsRecord('zone-123', 'record-123', $updateData);

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['record']['content'])->toBe('192.168.1.250');
});

it('can delete dns record', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/dns_records/record-123' => Http::response([
            'success' => true,
            'result' => ['id' => 'record-123'],
        ], 200),
    ]);

    $result = $this->service->deleteDnsRecord('zone-123', 'record-123');

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue();
});

it('handles rate limiting with retry', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones*' => Http::sequence()
            ->push(['errors' => [['code' => 10013]]], 429)
            ->push([
                'success' => true,
                'result' => [['id' => 'zone-123', 'name' => 'example.com']],
            ], 200),
    ]);

    $zones = $this->service->listZones();

    expect($zones)->toBeArray()
        ->and($zones)->toHaveCount(1);

    // Should have made 2 requests (first failed, second succeeded)
    Http::assertSentCount(2);
});

it('can enable dnssec', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/dnssec' => Http::response([
            'success' => true,
            'result' => [
                'status' => 'active',
                'flags' => 257,
                'algorithm' => 13,
                'key_type' => 2,
            ],
        ], 200),
    ]);

    $result = $this->service->enableDnssec('zone-123');

    expect($result)->toBeArray()
        ->and($result['success'])->toBeTrue()
        ->and($result['dnssec']['status'])->toBe('active');
});

it('can get zone analytics', function () {
    Http::fake([
        'api.cloudflare.com/client/v4/zones/zone-123/analytics/dashboard*' => Http::response([
            'success' => true,
            'result' => [
                'totals' => [
                    'requests' => ['all' => 1000],
                    'bandwidth' => ['all' => 50000000],
                ],
            ],
        ], 200),
    ]);

    $analytics = $this->service->getZoneAnalytics('zone-123');

    expect($analytics)->toBeArray()
        ->and($analytics['totals']['requests']['all'])->toBe(1000)
        ->and($analytics['totals']['bandwidth']['all'])->toBe(50000000);
});
