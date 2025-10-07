<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa IPAM Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-ipam')->in(__DIR__);
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

expect()->extend('toBeValidCidr', function () {
    return $this->toMatch('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/');
});

expect()->extend('toBeValidIpv6', function () {
    return $this->toMatch('/^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/');
});

function createTestIpNetwork(array $attributes = []): \NetServa\Ipam\Models\IpNetwork
{
    return \NetServa\Ipam\Models\IpNetwork::factory()->create(array_merge([
        'name' => 'test-network',
        'cidr' => '192.168.1.0/24',
        'status' => 'active',
    ], $attributes));
}

function createTestIpAddress(array $attributes = []): \NetServa\Ipam\Models\IpAddress
{
    return \NetServa\Ipam\Models\IpAddress::factory()->create(array_merge([
        'ip_address' => '192.168.1.100',
        'status' => 'allocated',
    ], $attributes));
}
