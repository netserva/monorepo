<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa WireGuard Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-wg')->in(__DIR__);
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('models')->in('Unit/Models');
uses()->group('services')->in('Unit/Services');
uses()->group('commands')->in('Feature/Commands');

beforeEach(function () {
    $this->setUpNetServaEnvironment();
    $this->mockAllProcesses();
    $this->clearTestCaches();
});

afterEach(function () {
    $this->cleanupTempDirectories();
});

expect()->extend('toBeValidWireguardKey', function () {
    return $this->toMatch('/^[A-Za-z0-9+\/]{43}=$/');
});

expect()->extend('toBeValidWireguardIp', function () {
    return $this->toMatch('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/');
});

function createTestWireguardServer(array $attributes = []): \NetServa\Wg\Models\WireguardServer
{
    return \NetServa\Wg\Models\WireguardServer::factory()->create(array_merge([
        'name' => 'test-wg-server',
        'listen_port' => 51820,
        'status' => 'active',
    ], $attributes));
}

function createTestWireguardPeer(array $attributes = []): \NetServa\Wg\Models\WireguardPeer
{
    return \NetServa\Wg\Models\WireguardPeer::factory()->create(array_merge([
        'name' => 'test-peer',
        'status' => 'active',
    ], $attributes));
}
