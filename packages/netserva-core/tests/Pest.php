<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Core Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-core')->in(__DIR__);
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('models')->in('Unit/Models');
uses()->group('services')->in('Unit/Services');

beforeEach(function () {
    $this->setUpNetServaEnvironment();
    $this->mockAllProcesses();
    $this->clearTestCaches();
});

afterEach(function () {
    $this->cleanupTempDirectories();
});

function createTestServer(array $attributes = []): \NetServa\Core\Models\Server
{
    return \NetServa\Core\Models\Server::factory()->create(array_merge([
        'name' => 'test-server',
        'hostname' => 'test.example.com',
        'ip_address' => '192.168.100.10',
        'status' => 'active',
    ], $attributes));
}

function createTestVhost(array $attributes = []): \NetServa\Core\Models\Vhost
{
    return \NetServa\Core\Models\Vhost::factory()->create(array_merge([
        'domain' => 'test.example.com',
        'server_id' => createTestServer()->id,
        'status' => 'active',
    ], $attributes));
}
