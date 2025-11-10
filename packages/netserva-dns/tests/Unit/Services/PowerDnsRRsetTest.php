<?php

use Illuminate\Support\Facades\Http;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\PowerDnsService;
use NetServa\Dns\Services\PowerDnsTunnelService;

uses()
    ->group('unit', 'dns', 'powerdns', 'rrset');

beforeEach(function () {
    $this->provider = DnsProvider::factory()->create([
        'type' => 'powerdns',
        'vnode' => 'test-pdns',
        'connection_config' => [
            'api_endpoint' => 'http://127.0.0.1:8081',
            'api_key' => 'test-api-key',
            'ssh_host' => null, // No SSH tunnel for unit tests
            'api_port' => 8081,
        ],
    ]);

    // Mock the tunnel service to use HTTP::fake() instead of real SSH
    $this->mock(PowerDnsTunnelService::class, function ($mock) {
        $mock->shouldReceive('apiCall')
            ->andReturnUsing(function ($provider, $endpoint, $method = 'GET', $data = []) {
                // Delegate to HTTP facade (which we fake in each test)
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-API-Key' => 'test-api-key',
                    'Content-Type' => 'application/json',
                ])->$method('http://127.0.0.1:8081/api/v1'.$endpoint, $data);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json() ?? [],
                        'status_code' => $response->status(),
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'HTTP '.$response->status(),
                    'status_code' => $response->status(),
                ];
            });
    });

    $this->service = app(PowerDnsService::class);
});

it('preserves existing records when adding to RRset', function () {
    // Mock zone with existing NS record
    Http::fake([
        '*/api/v1/servers/localhost/zones/example.com.' => Http::sequence()
            ->push([
                'rrsets' => [
                    [
                        'name' => 'example.com.',
                        'type' => 'NS',
                        'ttl' => 300,
                        'records' => [
                            ['content' => 'ns1.example.com.', 'disabled' => false],
                        ],
                    ],
                ],
            ], 200) // GET request
            ->push([], 204), // PATCH success
    ]);

    // Add second NS record
    $result = $this->service->createRecord($this->provider, 'example.com', [
        'name' => 'example.com.',
        'type' => 'NS',
        'content' => 'ns2.example.com.',
        'ttl' => 300,
    ]);

    expect($result['success'])->toBeTrue();

    // Verify PATCH was called with both records
    Http::assertSent(function ($request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $data = $request->data();
        if (! isset($data['rrsets'][0]['records'])) {
            return false;
        }

        $records = $data['rrsets'][0]['records'];

        // Should have 2 records: existing + new
        return count($records) === 2
            && collect($records)->contains(fn ($r) => $r['content'] === 'ns1.example.com.')
            && collect($records)->contains(fn ($r) => $r['content'] === 'ns2.example.com.');
    });
});

it('creates first record in RRset when none exist', function () {
    // Mock zone with no NS records
    Http::fake([
        '*/api/v1/servers/localhost/zones/example.com.' => Http::sequence()
            ->push([
                'rrsets' => [
                    [
                        'name' => 'example.com.',
                        'type' => 'A',
                        'ttl' => 300,
                        'records' => [
                            ['content' => '192.168.1.1', 'disabled' => false],
                        ],
                    ],
                ],
            ], 200)
            ->push([], 204), // PATCH success
    ]);

    // Add first NS record
    $result = $this->service->createRecord($this->provider, 'example.com', [
        'name' => 'example.com.',
        'type' => 'NS',
        'content' => 'ns1.example.com.',
        'ttl' => 300,
    ]);

    expect($result['success'])->toBeTrue();

    // Verify PATCH was called with single record
    Http::assertSent(function ($request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $data = $request->data();
        if (! isset($data['rrsets'][0]['records'])) {
            return false;
        }

        $records = $data['rrsets'][0]['records'];

        return count($records) === 1
            && $records[0]['content'] === 'ns1.example.com.';
    });
});

it('rejects duplicate records in RRset', function () {
    // Mock zone with existing NS record
    Http::fake([
        '*/api/v1/servers/localhost/zones/example.com.' => Http::response([
            'rrsets' => [
                [
                    'name' => 'example.com.',
                    'type' => 'NS',
                    'ttl' => 300,
                    'records' => [
                        ['content' => 'ns1.example.com.', 'disabled' => false],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Try to add duplicate NS record
    $result = $this->service->createRecord($this->provider, 'example.com', [
        'name' => 'example.com.',
        'type' => 'NS',
        'content' => 'ns1.example.com.',
        'ttl' => 300,
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('already exists');

    // Verify PATCH was NOT called
    Http::assertNotSent(function ($request) {
        return $request->method() === 'PATCH';
    });
});

it('preserves TTL from existing RRset when not specified', function () {
    // Mock zone with existing NS record with TTL 600
    Http::fake([
        '*/api/v1/servers/localhost/zones/example.com.' => Http::sequence()
            ->push([
                'rrsets' => [
                    [
                        'name' => 'example.com.',
                        'type' => 'NS',
                        'ttl' => 600,
                        'records' => [
                            ['content' => 'ns1.example.com.', 'disabled' => false],
                        ],
                    ],
                ],
            ], 200)
            ->push([], 204),
    ]);

    // Add second NS record without specifying TTL
    $result = $this->service->createRecord($this->provider, 'example.com', [
        'name' => 'example.com.',
        'type' => 'NS',
        'content' => 'ns2.example.com.',
        // No TTL specified
    ]);

    expect($result['success'])->toBeTrue();

    // Verify PATCH was called with existing TTL of 600
    Http::assertSent(function ($request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $data = $request->data();

        return isset($data['rrsets'][0]['ttl'])
            && $data['rrsets'][0]['ttl'] === 600;
    });
});

it('handles multiple A records for same name (round-robin)', function () {
    // Mock zone with existing A record
    Http::fake([
        '*/api/v1/servers/localhost/zones/www.example.com.' => Http::sequence()
            ->push([
                'rrsets' => [
                    [
                        'name' => 'www.example.com.',
                        'type' => 'A',
                        'ttl' => 300,
                        'records' => [
                            ['content' => '192.168.1.1', 'disabled' => false],
                        ],
                    ],
                ],
            ], 200)
            ->push([], 204),
    ]);

    // Add second A record for round-robin load balancing
    $result = $this->service->createRecord($this->provider, 'www.example.com', [
        'name' => 'www.example.com.',
        'type' => 'A',
        'content' => '192.168.1.2',
        'ttl' => 300,
    ]);

    expect($result['success'])->toBeTrue();

    // Verify both IPs are in the RRset
    Http::assertSent(function ($request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $data = $request->data();
        $records = $data['rrsets'][0]['records'] ?? [];

        return count($records) === 2
            && collect($records)->contains(fn ($r) => $r['content'] === '192.168.1.1')
            && collect($records)->contains(fn ($r) => $r['content'] === '192.168.1.2');
    });
});

it('preserves disabled state of existing records', function () {
    // Mock zone with disabled NS record
    Http::fake([
        '*/api/v1/servers/localhost/zones/example.com.' => Http::sequence()
            ->push([
                'rrsets' => [
                    [
                        'name' => 'example.com.',
                        'type' => 'NS',
                        'ttl' => 300,
                        'records' => [
                            ['content' => 'ns1.example.com.', 'disabled' => true],
                        ],
                    ],
                ],
            ], 200)
            ->push([], 204),
    ]);

    // Add second NS record
    $result = $this->service->createRecord($this->provider, 'example.com', [
        'name' => 'example.com.',
        'type' => 'NS',
        'content' => 'ns2.example.com.',
        'ttl' => 300,
    ]);

    expect($result['success'])->toBeTrue();

    // Verify existing record's disabled state is preserved
    Http::assertSent(function ($request) {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        $data = $request->data();
        $records = $data['rrsets'][0]['records'] ?? [];

        $existingRecord = collect($records)->firstWhere('content', 'ns1.example.com.');
        $newRecord = collect($records)->firstWhere('content', 'ns2.example.com.');

        return $existingRecord['disabled'] === true
            && $newRecord['disabled'] === false;
    });
});
