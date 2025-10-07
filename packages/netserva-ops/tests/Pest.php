<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration for NetServa Ops Package
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\NetServaTestHelpers;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    NetServaTestHelpers::class
)->in('Unit', 'Feature', 'Integration');

uses()->group('netserva-ops')->in(__DIR__);
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

expect()->extend('toBeValidUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

expect()->extend('toBeValidBackupType', function () {
    return $this->toBeIn(['database', 'files', 'system']);
});

function createTestBackupRepository(array $attributes = []): \NetServa\Ops\Models\BackupRepository
{
    return \NetServa\Ops\Models\BackupRepository::factory()->create(array_merge([
        'name' => 'test-repository',
        'storage_type' => 'local',
        'storage_path' => '/tmp/test-backups',
        'is_active' => true,
    ], $attributes));
}

function createTestBackupJob(array $attributes = []): \NetServa\Ops\Models\BackupJob
{
    return \NetServa\Ops\Models\BackupJob::factory()->create(array_merge([
        'name' => 'test-backup',
        'backup_type' => 'files',
        'enabled' => true,
        'status' => 'inactive',
    ], $attributes));
}

function createTestIncident(array $attributes = []): \NetServa\Ops\Models\Incident
{
    return \NetServa\Ops\Models\Incident::factory()->create(array_merge([
        'title' => 'Test incident',
        'status' => 'investigating',
        'severity' => 'medium',
    ], $attributes));
}
