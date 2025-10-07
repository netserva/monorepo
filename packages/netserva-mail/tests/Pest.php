<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Mail Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-mail')->in(__DIR__);
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

expect()->extend('toBeValidEmail', function () {
    return $this->toMatch('/^[^\s@]+@[^\s@]+\.[^\s@]+$/');
});

function createTestMailDomain(array $attributes = []): \NetServa\Mail\Models\MailDomain
{
    return \NetServa\Mail\Models\MailDomain::factory()->create(array_merge([
        'domain' => 'example.com',
        'is_active' => true,
    ], $attributes));
}

function createTestMailbox(array $attributes = []): \NetServa\Mail\Models\Mailbox
{
    return \NetServa\Mail\Models\Mailbox::factory()->create(array_merge([
        'email' => 'test@example.com',
        'is_active' => true,
    ], $attributes));
}
