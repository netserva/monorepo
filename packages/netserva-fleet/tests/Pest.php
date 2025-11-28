<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Fleet Package
|--------------------------------------------------------------------------
|
| This file configures Pest for the NetServa Fleet package tests.
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

uses()->group('netserva-fleet')->in(__DIR__);
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('integration')->in('Integration');
uses()->group('commands')->in('Feature/Commands');
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

    // Fleet-specific environment
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

expect()->extend('toBeValidVhost', function () {
    return $this->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*\.([a-z]{2,}\.)*[a-z]{2,}$/i');
});

expect()->extend('toBeValidVnode', function () {
    return $this->toMatch('/^[a-zA-Z0-9\-]+$/');
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function createTestFleetInfrastructure(array $attributes = []): array
{
    $venue = \NetServa\Fleet\Models\FleetVenue::factory()->create([
        'name' => $attributes['venue_name'] ?? 'test-datacenter',
    ]);

    $vsite = \NetServa\Fleet\Models\FleetVsite::factory()->create([
        'venue_id' => $venue->id,
        'name' => $attributes['vsite_name'] ?? 'test-vsite',
    ]);

    $vnode = \NetServa\Fleet\Models\FleetVnode::factory()->create([
        'vsite_id' => $vsite->id,
        'name' => $attributes['vnode_name'] ?? 'test-vnode',
    ]);

    return [
        'venue' => $venue,
        'vsite' => $vsite,
        'vnode' => $vnode,
    ];
}

function createTestVhost(array $attributes = []): \NetServa\Fleet\Models\FleetVhost
{
    $infra = createTestFleetInfrastructure();

    return \NetServa\Fleet\Models\FleetVhost::factory()->create(array_merge([
        'vnode_id' => $infra['vnode']->id,
        'domain' => 'test.example.com',
    ], $attributes));
}

function mockSuccessfulSshConnection(string $host = 'test-server'): void
{
    \Illuminate\Support\Facades\Process::fake([
        "ssh $host *" => \Illuminate\Support\Facades\Process::result('SSH connection successful', '', 0),
        "ssh -T $host" => \Illuminate\Support\Facades\Process::result('Connection test passed', '', 0),
    ]);
}
