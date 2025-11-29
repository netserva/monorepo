<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use NetServa\Core\Services\BinaryLaneApiException;
use NetServa\Core\Services\BinaryLaneConfigurationException;
use NetServa\Core\Services\BinaryLaneRateLimitException;
use NetServa\Core\Services\BinaryLaneService;
use NetServa\Core\Services\BinaryLaneValidationException;

uses()
    ->group('unit', 'services', 'netserva-cli', 'binary-lane', 'priority-2');

beforeEach(function () {
    Cache::flush();
    Http::fake();
});

it('initializes with null client when API token is missing', function () {
    // Set missing API token
    putenv('BINARYLANE_API_TOKEN=');

    $service = new BinaryLaneService;

    expect($service)->toBeInstanceOf(BinaryLaneService::class);
});

it('throws configuration exception when making requests without API token', function () {
    putenv('BINARYLANE_API_TOKEN=');

    $service = new BinaryLaneService;

    $service->listServers();
})->throws(BinaryLaneConfigurationException::class, 'BinaryLane API token not configured');

it('initializes HTTP client when API token is provided', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/account' => Http::response(['account' => ['email' => 'test@example.com']], 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->testConnection();

    expect($result['success'])->toBeTrue();
    expect($result['account']['email'])->toBe('test@example.com');
});

it('can list servers successfully', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockServers = [
        'servers' => [
            [
                'id' => 123,
                'name' => 'test-server',
                'status' => 'active',
                'networks' => [
                    'v4' => [['ip_address' => '192.168.1.100']],
                ],
                'size' => ['slug' => 'std-1vcpu'],
                'region' => ['slug' => 'syd'],
            ],
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response($mockServers, 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->listServers();

    expect($result)->toBe($mockServers);
    expect($result['servers'])->toHaveCount(1);
    expect($result['servers'][0]['name'])->toBe('test-server');
});

it('caches server list results', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockServers = ['servers' => [['id' => 123, 'name' => 'test']]];

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response($mockServers, 200),
    ]);

    $service = new BinaryLaneService;

    // First call should hit API
    $result1 = $service->listServers();
    // Second call should use cache
    $result2 = $service->listServers();

    expect($result1)->toBe($result2);
    Http::assertSentTimes(fn ($request) => $request->url() === 'https://api.binarylane.com.au/v2/servers', 1);
});

it('can get individual server details', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockServer = [
        'server' => [
            'id' => 123,
            'name' => 'test-server',
            'status' => 'active',
            'networks' => ['v4' => [['ip_address' => '192.168.1.100']]],
            'created_at' => '2024-01-01T10:00:00Z',
            'image' => ['name' => 'Ubuntu 22.04'],
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response($mockServer, 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->getServer('123');

    expect($result)->toBe($mockServer);
    expect($result['server']['id'])->toBe(123);
});

it('can list available sizes', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockSizes = [
        'sizes' => [
            [
                'slug' => 'std-1vcpu',
                'vcpus' => 1,
                'memory' => 1024,
                'disk' => 25,
                'price_hourly' => 0.021,
                'price_monthly' => 15.00,
            ],
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/sizes' => Http::response($mockSizes, 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->listSizes();

    expect($result)->toBe($mockSizes);
    expect($result['sizes'][0]['slug'])->toBe('std-1vcpu');
});

it('can list available images', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockImages = [
        'images' => [
            [
                'slug' => 'ubuntu-22-04',
                'name' => 'Ubuntu 22.04 LTS',
                'type' => 'base_image',
                'status' => 'available',
            ],
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/images' => Http::response($mockImages, 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->listImages();

    expect($result)->toBe($mockImages);
    expect($result['images'][0]['name'])->toBe('Ubuntu 22.04 LTS');
});

it('can list available regions', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $mockRegions = [
        'regions' => [
            [
                'slug' => 'syd',
                'name' => 'Sydney',
                'available' => true,
            ],
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/regions' => Http::response($mockRegions, 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->listRegions();

    expect($result)->toBe($mockRegions);
    expect($result['regions'][0]['slug'])->toBe('syd');
});

it('can create a new server', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $createData = [
        'name' => 'test-server',
        'size' => 'std-1vcpu',
        'image' => 'ubuntu-22-04',
        'region' => 'syd',
    ];

    $mockResponse = [
        'server' => [
            'id' => 456,
            'name' => 'test-server',
            'status' => 'new',
        ],
    ];

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response($mockResponse, 201),
    ]);

    $service = new BinaryLaneService;
    $result = $service->createServer($createData);

    expect($result)->toBe($mockResponse);
    expect($result['server']['name'])->toBe('test-server');

    Http::assertSent(function ($request) use ($createData) {
        return $request->method() === 'POST' &&
               $request->url() === 'https://api.binarylane.com.au/v2/servers' &&
               $request->data() === $createData;
    });
});

it('validates server creation data', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $service = new BinaryLaneService;

    $service->createServer(['name' => 'test']); // Missing required fields
})->throws(BinaryLaneValidationException::class, 'Missing required field');

it('validates server name format', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    $service = new BinaryLaneService;

    $service->createServer([
        'name' => '-invalid-name-',
        'size' => 'std-1vcpu',
        'image' => 'ubuntu-22-04',
        'region' => 'syd',
    ]);
})->throws(BinaryLaneValidationException::class, 'Invalid server name format');

it('accepts valid server names', function ($validName) {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['server' => ['id' => 123]], 201),
    ]);

    $service = new BinaryLaneService;

    $result = $service->createServer([
        'name' => $validName,
        'size' => 'std-1vcpu',
        'image' => 'ubuntu-22-04',
        'region' => 'syd',
    ]);

    expect($result['server']['id'])->toBe(123);
})->with([
    'test-server',
    'my.server.com',
    'server123',
    'a',
    'production-web-01',
]);

it('can delete a server', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response([], 204),
    ]);

    $service = new BinaryLaneService;
    $result = $service->deleteServer('123');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' &&
               $request->url() === 'https://api.binarylane.com.au/v2/servers/123';
    });
});

it('handles rate limiting with exponential backoff', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::sequence()
            ->push(['error' => 'Rate limit exceeded'], 429, ['retry-after' => '60'])
            ->push(['error' => 'Rate limit exceeded'], 429, ['retry-after' => '60'])
            ->push(['error' => 'Rate limit exceeded'], 429, ['retry-after' => '60']),
    ]);

    $service = new BinaryLaneService;

    $service->listServers();
})->throws(BinaryLaneRateLimitException::class);

it('handles API errors gracefully', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response([
            'message' => 'Invalid request',
            'errors' => ['Server limit exceeded'],
        ], 400),
    ]);

    $service = new BinaryLaneService;

    $service->listServers();
})->throws(BinaryLaneApiException::class, 'BinaryLane API error (400): Server limit exceeded');

it('clears cache after server creation', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    // Pre-populate cache
    Cache::put('binarylane:servers', ['servers' => []], 300);
    expect(Cache::has('binarylane:servers'))->toBeTrue();

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['server' => ['id' => 123]], 201),
    ]);

    $service = new BinaryLaneService;
    $service->createServer([
        'name' => 'test',
        'size' => 'std-1vcpu',
        'image' => 'ubuntu-22-04',
        'region' => 'syd',
    ]);

    expect(Cache::has('binarylane:servers'))->toBeFalse();
});

it('formats server data for display correctly', function () {
    $service = new BinaryLaneService;

    $serverData = [
        'id' => 123,
        'name' => 'test-server',
        'status' => 'active',
        'networks' => [
            'v4' => [['ip_address' => '192.168.1.100']],
        ],
        'size' => ['slug' => 'std-1vcpu'],
        'region' => ['slug' => 'syd'],
    ];

    $formatted = $service->formatServerForDisplay($serverData);

    expect($formatted)->toEqual([
        'id' => 123,
        'name' => 'test-server',
        'ip' => '192.168.1.100',
        'status' => 'active',
        'size' => 'std-1vcpu',
        'region' => 'syd',
    ]);
});

it('formats size data for display correctly', function () {
    $service = new BinaryLaneService;

    $sizeData = [
        'slug' => 'std-2vcpu',
        'vcpus' => 2,
        'memory' => 2048,
        'disk' => 50,
        'price_hourly' => 0.042,
        'price_monthly' => 30.00,
    ];

    $formatted = $service->formatSizeForDisplay($sizeData);

    expect($formatted)->toEqual([
        'slug' => 'std-2vcpu',
        'vcpus' => 2,
        'memory' => '2GB',
        'disk' => '50GB',
        'hourly' => '$0.042',
        'monthly' => '$30',
    ]);
});

it('formats image data for display correctly', function () {
    $service = new BinaryLaneService;

    $imageData = [
        'slug' => 'ubuntu-22-04',
        'name' => 'Ubuntu 22.04 LTS Server',
        'type' => 'base_image',
        'status' => 'available',
    ];

    $formatted = $service->formatImageForDisplay($imageData);

    expect($formatted)->toEqual([
        'slug' => 'ubuntu-22-04',
        'name' => 'Ubuntu 22.04 LTS Se...',
        'type' => 'base_image',
        'status' => 'available',
    ]);
});

it('formats region data for display correctly', function () {
    $service = new BinaryLaneService;

    $regionData = [
        'slug' => 'syd',
        'name' => 'Sydney, Australia',
        'available' => true,
    ];

    $formatted = $service->formatRegionForDisplay($regionData);

    expect($formatted)->toEqual([
        'slug' => 'syd',
        'name' => 'Sydney, Australia',
        'location' => 'Sydney, Australia',
        'available' => 'Yes',
    ]);
});

it('tests connection successfully', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/account' => Http::response([
            'account' => ['email' => 'test@example.com'],
        ], 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->testConnection();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('BinaryLane API connection successful');
    expect($result['account']['email'])->toBe('test@example.com');
});

it('handles test connection failure', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/account' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $service = new BinaryLaneService;
    $result = $service->testConnection();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('BinaryLane API connection failed');
});

it('includes proper user agent in requests', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['servers' => []], 200),
    ]);

    $service = new BinaryLaneService;
    $service->listServers();

    Http::assertSent(function ($request) {
        return $request->hasHeader('User-Agent', 'NetServa-Platform/3.0 (BinaryLane-API-Client)');
    });
});

it('handles empty server list gracefully', function () {
    putenv('BINARYLANE_API_TOKEN=test-token-123');

    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['servers' => []], 200),
    ]);

    $service = new BinaryLaneService;
    $result = $service->listServers();

    expect($result['servers'])->toBeArray();
    expect($result['servers'])->toHaveCount(0);
});

it('handles missing IP address in server data', function () {
    $service = new BinaryLaneService;

    $serverData = [
        'id' => 123,
        'name' => 'test-server',
        'status' => 'new',
        'networks' => [], // No IP assigned yet
        'size' => ['slug' => 'std-1vcpu'],
        'region' => ['slug' => 'syd'],
    ];

    $formatted = $service->formatServerForDisplay($serverData);

    expect($formatted['ip'])->toBe('N/A');
});

afterEach(function () {
    putenv('BINARYLANE_API_TOKEN'); // Clear environment variable
});
