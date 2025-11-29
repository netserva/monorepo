<?php

use Illuminate\Support\Facades\Http;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'binary-lane', 'priority-2');

beforeEach(function () {
    Http::fake();
    putenv('BINARYLANE_API_TOKEN=test-token-123');
});

it('displays help for invalid action', function () {
    $this->artisan('bl', ['action' => 'invalid'])
        ->expectsOutput('❌ Invalid action: invalid')
        ->expectsOutput('Available actions:')
        ->expectsOutput('  • list     - List all VPS instances')
        ->expectsOutput('  • create   - Create new VPS instance')
        ->assertExitCode(1);
});

it('can list servers successfully', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response([
            'servers' => [
                [
                    'id' => 123,
                    'name' => 'test-server',
                    'status' => 'active',
                    'networks' => ['v4' => [['ip_address' => '192.168.1.100']]],
                    'size' => ['slug' => 'std-1vcpu'],
                    'region' => ['slug' => 'syd'],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'list'])
        ->expectsOutput('📋 Listing BinaryLane servers...')
        ->assertExitCode(0);
});

it('handles empty server list', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['servers' => []], 200),
    ]);

    $this->artisan('bl', ['action' => 'list'])
        ->expectsOutput('✅ No servers found in your BinaryLane account')
        ->assertExitCode(0);
});

it('can show specific server details', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response([
            'server' => [
                'id' => 123,
                'name' => 'test-server',
                'status' => 'active',
                'networks' => ['v4' => [['ip_address' => '192.168.1.100']]],
                'size' => ['slug' => 'std-1vcpu'],
                'region' => ['slug' => 'syd'],
                'created_at' => '2024-01-01T10:00:00Z',
                'image' => ['name' => 'Ubuntu 22.04'],
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'show', 'server-id' => '123'])
        ->expectsOutput('🔍 Getting server details for ID: 123')
        ->expectsOutput('🖥️  Server Details')
        ->assertExitCode(0);
});

it('can list available sizes', function () {
    Http::fake([
        'api.binarylane.com.au/v2/sizes' => Http::response([
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
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'sizes'])
        ->expectsOutput('📊 Listing available VPS sizes...')
        ->assertExitCode(0);
});

it('can list available images', function () {
    Http::fake([
        'api.binarylane.com.au/v2/images' => Http::response([
            'images' => [
                [
                    'slug' => 'ubuntu-22-04',
                    'name' => 'Ubuntu 22.04 LTS',
                    'type' => 'base_image',
                    'status' => 'available',
                ],
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'images'])
        ->expectsOutput('💿 Listing available OS images...')
        ->assertExitCode(0);
});

it('can list available regions', function () {
    Http::fake([
        'api.binarylane.com.au/v2/regions' => Http::response([
            'regions' => [
                [
                    'slug' => 'syd',
                    'name' => 'Sydney',
                    'available' => true,
                ],
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'regions'])
        ->expectsOutput('🌏 Listing available regions...')
        ->assertExitCode(0);
});

it('can test API connection successfully', function () {
    Http::fake([
        'api.binarylane.com.au/v2/account' => Http::response([
            'account' => ['email' => 'test@example.com'],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'test'])
        ->expectsOutput('🔌 Testing BinaryLane API connection...')
        ->expectsOutput('✅ BinaryLane API connection successful')
        ->assertExitCode(0);
});

it('handles API connection failure', function () {
    Http::fake([
        'api.binarylane.com.au/v2/account' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $this->artisan('bl', ['action' => 'test'])
        ->expectsOutput('❌ BinaryLane API connection failed')
        ->assertExitCode(1);
});

it('displays configuration error with setup instructions', function () {
    putenv('BINARYLANE_API_TOKEN='); // Clear token

    $this->artisan('bl', ['action' => 'list'])
        ->expectsOutput('❌ Configuration Error: BinaryLane API token not configured')
        ->expectsOutput('💡 Setup Instructions:')
        ->expectsOutput('   1. Set BINARYLANE_API_TOKEN in your .env file')
        ->assertExitCode(1);
});

it('supports JSON output format', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response([
            'server' => [
                'id' => 123,
                'name' => 'test-server',
                'status' => 'active',
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'show', 'server-id' => '123', '--format' => 'json'])
        ->expectsOutputToContain('"id": 123')
        ->assertExitCode(0);
});

it('supports dry-run mode for create operation', function () {
    $this->artisan('bl', [
        'action' => 'create',
        '--name' => 'test-server',
        '--region' => 'syd',
        '--size' => 'std-1vcpu',
        '--image' => 'ubuntu-22-04',
        '--dry-run' => true,
    ])
        ->expectsOutput('🔍 DRY RUN: Create BinaryLane VPS')
        ->expectsOutput('   → Name: test-server')
        ->assertExitCode(0);
});

it('supports dry-run mode for delete operation', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response([
            'server' => [
                'id' => 123,
                'name' => 'test-server',
                'status' => 'active',
            ],
        ], 200),
    ]);

    $this->artisan('bl', [
        'action' => 'delete',
        'server-id' => '123',
        '--dry-run' => true,
    ])
        ->expectsOutput('🔍 DRY RUN: Delete BinaryLane VPS')
        ->expectsOutput('   → Server ID: 123')
        ->assertExitCode(0);
});

it('handles API errors gracefully', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response([
            'message' => 'Server limit exceeded',
        ], 400),
    ]);

    $this->artisan('bl', ['action' => 'list'])
        ->expectsOutput('❌ BinaryLane API Error:')
        ->assertExitCode(1);
});

it('validates server creation with all required parameters', function () {
    Http::fake([
        'api.binarylane.com.au/v2/sizes' => Http::response([
            'sizes' => [
                ['slug' => 'std-1vcpu', 'vcpus' => 1, 'memory' => 1024, 'disk' => 25, 'price_monthly' => 15],
            ],
        ], 200),
        'api.binarylane.com.au/v2/images' => Http::response([
            'images' => [
                ['slug' => 'ubuntu-22-04', 'name' => 'Ubuntu 22.04', 'status' => 'available'],
            ],
        ], 200),
        'api.binarylane.com.au/v2/regions' => Http::response([
            'regions' => [
                ['slug' => 'syd', 'name' => 'Sydney', 'available' => true],
            ],
        ], 200),
        'api.binarylane.com.au/v2/servers' => Http::response([
            'server' => ['id' => 456, 'name' => 'test-server', 'status' => 'new'],
        ], 201),
    ]);

    $this->artisan('bl', [
        'action' => 'create',
        '--name' => 'test-server',
        '--region' => 'syd',
        '--size' => 'std-1vcpu',
        '--image' => 'ubuntu-22-04',
    ])
        ->expectsConfirmation("Create VPS 'test-server' in syd with std-1vcpu?", 'yes')
        ->expectsOutput('✅ VPS creation initiated successfully!')
        ->assertExitCode(0);
});

it('handles server creation cancellation', function () {
    Http::fake([
        'api.binarylane.com.au/v2/sizes' => Http::response([
            'sizes' => [['slug' => 'std-1vcpu', 'vcpus' => 1, 'memory' => 1024, 'disk' => 25, 'price_monthly' => 15]],
        ], 200),
        'api.binarylane.com.au/v2/images' => Http::response([
            'images' => [['slug' => 'ubuntu-22-04', 'name' => 'Ubuntu 22.04', 'status' => 'available']],
        ], 200),
        'api.binarylane.com.au/v2/regions' => Http::response([
            'regions' => [['slug' => 'syd', 'name' => 'Sydney', 'available' => true]],
        ], 200),
    ]);

    $this->artisan('bl', [
        'action' => 'create',
        '--name' => 'test-server',
        '--region' => 'syd',
        '--size' => 'std-1vcpu',
        '--image' => 'ubuntu-22-04',
    ])
        ->expectsConfirmation("Create VPS 'test-server' in syd with std-1vcpu?", 'no')
        ->expectsOutput('❌ VPS creation cancelled')
        ->assertExitCode(0);
});

it('handles server deletion with confirmation', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::sequence()
            ->push(['server' => ['id' => 123, 'name' => 'test-server']], 200)
            ->push([], 204),
    ]);

    $this->artisan('bl', [
        'action' => 'delete',
        'server-id' => '123',
    ])
        ->expectsOutput('⚠️ This will PERMANENTLY DELETE the VPS: test-server')
        ->expectsConfirmation("Are you sure you want to delete 'test-server'?", 'yes')
        ->expectsOutput("✅ VPS 'test-server' has been deleted successfully")
        ->assertExitCode(0);
});

it('handles server deletion cancellation', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers/123' => Http::response([
            'server' => ['id' => 123, 'name' => 'test-server'],
        ], 200),
    ]);

    $this->artisan('bl', [
        'action' => 'delete',
        'server-id' => '123',
    ])
        ->expectsConfirmation("Are you sure you want to delete 'test-server'?", 'no')
        ->expectsOutput('❌ VPS deletion cancelled')
        ->assertExitCode(0);
});

it('handles verbose mode for context display', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['servers' => []], 200),
    ]);

    $this->artisan('bl', ['action' => 'list', '--verbose' => true])
        ->assertExitCode(0);
});

it('shows server selection when no server-id provided for show command', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response(['servers' => []], 200),
    ]);

    $this->artisan('bl', ['action' => 'show'])
        ->expectsOutput('❌ No servers found in your account')
        ->assertExitCode(1);
});

it('displays appropriate messages for empty resource lists', function () {
    Http::fake([
        'api.binarylane.com.au/v2/sizes' => Http::response(['sizes' => []], 200),
    ]);

    $this->artisan('bl', ['action' => 'sizes'])
        ->expectsOutput('⚠️ No sizes available')
        ->assertExitCode(0);
});

it('displays table format by default', function () {
    Http::fake([
        'api.binarylane.com.au/v2/servers' => Http::response([
            'servers' => [
                [
                    'id' => 123,
                    'name' => 'test-server',
                    'status' => 'active',
                    'networks' => ['v4' => [['ip_address' => '192.168.1.100']]],
                    'size' => ['slug' => 'std-1vcpu'],
                    'region' => ['slug' => 'syd'],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('bl', ['action' => 'list'])
        ->assertExitCode(0);

    // Table format is displayed by default (uses Laravel Prompts table function)
});

afterEach(function () {
    putenv('BINARYLANE_API_TOKEN'); // Clear environment variable
});
