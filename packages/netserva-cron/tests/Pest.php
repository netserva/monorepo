<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Cron Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-cron')->in(__DIR__);
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

expect()->extend('toBeValidCronExpression', function () {
    // Basic cron validation - matches standard 5-field format
    return $this->toMatch('/^[*\/,\-0-9]+\s+[*\/,\-0-9]+\s+[*\/,\-0-9]+\s+[*\/,\-0-9]+\s+[*\/,\-0-9]+$/');
});

function createTestCronJob(array $attributes = []): \NetServa\Cron\Models\CronJob
{
    return \NetServa\Cron\Models\CronJob::factory()->create(array_merge([
        'name' => 'test-cron-job',
        'schedule' => '0 * * * *',
        'status' => 'active',
    ], $attributes));
}
