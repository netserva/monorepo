<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa DNS Package
|--------------------------------------------------------------------------
|
| This file configures Pest for the NetServa DNS package tests.
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

uses()->group('netserva-dns')->in(__DIR__);
uses()->group('unit')->in('Unit');
uses()->group('feature')->in('Feature');
uses()->group('integration')->in('Integration');
uses()->group('models')->in('Unit/Models');
uses()->group('services')->in('Unit/Services');
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
});

afterEach(function () {
    $this->cleanupTempDirectories();
});

/*
|--------------------------------------------------------------------------
| Custom Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidDomain', function () {
    return $this->toMatch('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i');
});

expect()->extend('toBeValidIpv4', function () {
    return $this->toMatch('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/');
});

expect()->extend('toBeValidIpv6', function () {
    return $this->toMatch('/^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/');
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function createTestDnsZone(array $attributes = []): \NetServa\Dns\Models\DnsZone
{
    return \NetServa\Dns\Models\DnsZone::factory()->create(array_merge([
        'domain' => 'test.example.com',
        'type' => 'NATIVE',
        'dnssec' => false,
    ], $attributes));
}

function createTestDnsRecord(array $attributes = []): \NetServa\Dns\Models\DnsRecord
{
    return \NetServa\Dns\Models\DnsRecord::factory()->create(array_merge([
        'name' => 'www.test.example.com',
        'type' => 'A',
        'content' => '192.168.100.10',
        'ttl' => 3600,
    ], $attributes));
}

function createTestDnsProvider(array $attributes = []): \NetServa\Dns\Models\DnsProvider
{
    return \NetServa\Dns\Models\DnsProvider::factory()->create(array_merge([
        'name' => 'test-provider',
        'type' => 'cloudflare',
        'is_active' => true,
    ], $attributes));
}
