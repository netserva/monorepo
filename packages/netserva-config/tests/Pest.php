<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Config Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-config')->in(__DIR__);
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

expect()->extend('toBeValidConfigFormat', function () {
    return $this->toBeIn(['json', 'yaml', 'ini', 'env']);
});

function createTestConfigTemplate(array $attributes = []): \NetServa\Config\Models\ConfigTemplate
{
    return \NetServa\Config\Models\ConfigTemplate::factory()->create(array_merge([
        'name' => 'test-template',
        'format' => 'json',
        'is_active' => true,
    ], $attributes));
}
