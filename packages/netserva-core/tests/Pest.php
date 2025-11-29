<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa CLI Package
|--------------------------------------------------------------------------
|
| This file configures Pest for the NetServa CLI package tests.
| Tests are organized by type: Unit, Feature, Integration
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Test Groups
|--------------------------------------------------------------------------
*/

uses()->group('netserva-cli')->in(__DIR__);
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('integration')->in('Integration');
uses()->group('models')->in('Unit/Models');
uses()->group('services')->in('Unit/Services');
uses()->group('commands')->in('Feature/Commands');
uses()->group('console')->in('Feature/Console');
uses()->group('filament')->in('Feature/Filament');

/*
|--------------------------------------------------------------------------
| Global Test Setup
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->setUpNetServaEnvironment();
    $this->mockAllProcesses();
    $this->mockExternalApis();
    $this->clearTestCaches();

    // CLI-specific environment
    putenv('BINARYLANE_API_TOKEN=test-bl-token-123');
    putenv('NETSERVA_CONTEXT_SERVER=test-server');
});

afterEach(function () {
    $this->cleanupTempDirectories();
});

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidSshHost', function () {
    return $this->toMatch('/^[a-zA-Z0-9\-\.]+$/');
});

expect()->extend('toBeValidVhost', function () {
    return $this->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*\.([a-z]{2,}\.)*[a-z]{2,}$/i');
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function createTestVhostConfiguration(array $attributes = []): \NetServa\Core\Models\VhostConfiguration
{
    return \NetServa\Core\Models\VhostConfiguration::factory()->create(array_merge([
        'vhost' => 'test.example.com',
        'vnode' => 'test-server',
        'configuration' => [
            'VHOST' => 'test.example.com',
            'VNODE' => 'test-server',
            'IP4_0' => '192.168.100.10',
        ],
    ], $attributes));
}

function createTestMigrationJob(array $attributes = []): \NetServa\Core\Models\MigrationJob
{
    return \NetServa\Core\Models\MigrationJob::factory()->create(array_merge([
        'source_host' => 'old-server',
        'target_host' => 'new-server',
        'vhost' => 'migrate.example.com',
        'status' => 'pending',
    ], $attributes));
}

function createTestSetupJob(array $attributes = []): \NetServa\Core\Models\SetupJob
{
    return \NetServa\Core\Models\SetupJob::factory()->create(array_merge([
        'name' => 'test-setup',
        'status' => 'pending',
        'server_id' => 'test-server',
    ], $attributes));
}

function mockSuccessfulSshConnection(string $host = 'test-server'): void
{
    \Illuminate\Support\Facades\Process::fake([
        "ssh $host *" => \Illuminate\Support\Facades\Process::result('SSH connection successful', '', 0),
        "ssh -T $host" => \Illuminate\Support\Facades\Process::result('Connection test passed', '', 0),
    ]);
}

function mockBinaryLaneApi(): void
{
    \Illuminate\Support\Facades\Http::fake([
        'api.binarylane.com.au/v2/*' => \Illuminate\Support\Facades\Http::response([
            'servers' => [
                ['id' => 1, 'name' => 'test-server', 'status' => 'running'],
                ['id' => 2, 'name' => 'prod-server', 'status' => 'running'],
            ],
            'account' => ['email' => 'test@example.com'],
        ], 200),
    ]);
}
