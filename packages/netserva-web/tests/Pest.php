<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Web Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-web')->in(__DIR__);
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

expect()->extend('toBeValidSslStatus', function () {
    return $this->toBeIn(['pending', 'issued', 'expired', 'revoked', 'failed']);
});

expect()->extend('toBeValidWebServerType', function () {
    return $this->toBeIn(['nginx', 'apache', 'lightttpd', 'caddy']);
});

expect()->extend('toBeValidApplicationType', function () {
    return $this->toBeIn(['laravel', 'wordpress', 'drupal', 'static', 'custom']);
});

function createTestVirtualHost(array $attributes = []): \NetServa\Web\Models\VirtualHost
{
    return \NetServa\Web\Models\VirtualHost::factory()->create(array_merge([
        'domain' => 'test.example.com',
        'document_root' => '/srv/test.example.com/web/app/public',
        'status' => 'active',
    ], $attributes));
}

function createTestSslCertificate(array $attributes = []): \NetServa\Web\Models\SslCertificate
{
    return \NetServa\Web\Models\SslCertificate::factory()->create(array_merge([
        'domain' => 'test.example.com',
        'status' => 'issued',
        'provider' => 'letsencrypt',
    ], $attributes));
}

function createTestWebServer(array $attributes = []): \NetServa\Web\Models\WebServer
{
    return \NetServa\Web\Models\WebServer::factory()->create(array_merge([
        'name' => 'test-web-server',
        'server_type' => 'nginx',
        'status' => 'active',
    ], $attributes));
}
