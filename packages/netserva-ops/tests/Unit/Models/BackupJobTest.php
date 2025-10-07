<?php

use NetServa\Ops\Models\BackupJob;
use NetServa\Ops\Models\BackupRepository;
use NetServa\Ops\Models\BackupSnapshot;

uses()
    ->group('unit', 'models', 'backup-job', 'priority-1');

it('can create a backup job', function () {
    $repository = createTestBackupRepository();

    $job = BackupJob::factory()->create([
        'name' => 'test-backup',
        'backup_type' => 'files',
        'target_host' => 'test.example.com',
        'source_paths' => ['/var/www', '/etc'],
        'destination_path' => 'backups/test',
        'enabled' => true,
        'backup_repository_id' => $repository->id,
    ]);

    expect($job)->toBeInstanceOf(BackupJob::class)
        ->and($job->name)->toBe('test-backup')
        ->and($job->backup_type)->toBeValidBackupType()
        ->and($job->target_host)->toBe('test.example.com')
        ->and($job->source_paths)->toBeArray()
        ->and($job->enabled)->toBeTrue()
        ->and($job->exists)->toBeTrue();
});

it('belongs to backup repository', function () {
    $repository = createTestBackupRepository();
    $job = BackupJob::factory()->create(['backup_repository_id' => $repository->id]);

    expect($job->backupRepository)->toBeInstanceOf(BackupRepository::class)
        ->and($job->backupRepository->id)->toBe($repository->id);
});

it('has many backup snapshots', function () {
    $job = createTestBackupJob();

    BackupSnapshot::factory()->count(3)->create([
        'backup_job_id' => $job->id,
    ]);

    expect($job->backupSnapshots)->toHaveCount(3)
        ->and($job->backupSnapshots->first())->toBeInstanceOf(BackupSnapshot::class);
});

it('can find active jobs only', function () {
    BackupJob::factory()->create(['enabled' => true]);
    BackupJob::factory()->create(['enabled' => false]);
    BackupJob::factory()->create(['enabled' => true]);

    $activeJobs = BackupJob::active()->get();

    expect($activeJobs)->toHaveCount(2)
        ->and($activeJobs->first()->enabled)->toBeTrue();
});

it('can find jobs by backup type', function () {
    BackupJob::factory()->create(['backup_type' => 'database']);
    BackupJob::factory()->create(['backup_type' => 'files']);
    BackupJob::factory()->create(['backup_type' => 'database']);

    $databaseJobs = BackupJob::byType('database')->get();

    expect($databaseJobs)->toHaveCount(2)
        ->and($databaseJobs->first()->backup_type)->toBe('database');
});

it('can check if job is running', function () {
    $job = createTestBackupJob();

    // Create running snapshot
    BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'running',
    ]);

    expect($job->isRunning())->toBeTrue();
});

it('can check if job can run', function () {
    $repository = createTestBackupRepository();
    $enabledJob = BackupJob::factory()->create([
        'enabled' => true,
        'backup_repository_id' => $repository->id,
    ]);

    $disabledJob = BackupJob::factory()->create([
        'enabled' => false,
        'backup_repository_id' => $repository->id,
    ]);

    expect($enabledJob->canRun())->toBeTrue()
        ->and($disabledJob->canRun())->toBeFalse();
});

it('can record execution success', function () {
    $job = createTestBackupJob(['status' => 'running']);

    $job->recordExecution(true, 300, 1024000);

    expect($job->fresh()->status)->toBe('completed')
        ->and($job->fresh()->duration_seconds)->toBe(300)
        ->and($job->fresh()->backup_size_bytes)->toBe(1024000)
        ->and($job->fresh()->completed_at)->not->toBeNull();
});

it('can record execution failure', function () {
    $job = createTestBackupJob(['status' => 'running']);

    $job->recordExecution(false, 120, null, 'Disk space full');

    expect($job->fresh()->status)->toBe('failed')
        ->and($job->fresh()->duration_seconds)->toBe(120)
        ->and($job->fresh()->completed_at)->not->toBeNull();
});

it('can get sources as array', function () {
    $job = BackupJob::factory()->create([
        'source_paths' => ['/var/www', '/etc', '/home'],
    ]);

    expect($job->sources)->toBeArray()
        ->and($job->sources)->toHaveCount(3)
        ->and($job->sources)->toContain('/var/www');
});

it('can get destination full path', function () {
    $repository = createTestBackupRepository(['storage_path' => '/backups']);
    $job = BackupJob::factory()->create([
        'destination_path' => 'jobs/test',
        'backup_repository_id' => $repository->id,
    ]);

    $fullPath = $job->getDestinationFullPath();

    expect($fullPath)->toBe('/backups/jobs/test');
});

it('can create snapshot', function () {
    $job = createTestBackupJob();

    $snapshot = $job->createSnapshot('incremental');

    expect($snapshot)->toBeInstanceOf(BackupSnapshot::class)
        ->and($snapshot->backup_job_id)->toBe($job->id)
        ->and($snapshot->backup_type)->toBe('incremental')
        ->and($snapshot->status)->toBe('pending')
        ->and($snapshot->snapshot_id)->toBeValidUuid();
});

it('can estimate backup size by type', function () {
    $databaseJob = BackupJob::factory()->create(['backup_type' => 'database']);
    $filesJob = BackupJob::factory()->create(['backup_type' => 'files']);
    $systemJob = BackupJob::factory()->create(['backup_type' => 'system']);

    expect($databaseJob->estimateBackupSize())->toBe(100 * 1024 * 1024)
        ->and($filesJob->estimateBackupSize())->toBe(1024 * 1024 * 1024)
        ->and($systemJob->estimateBackupSize())->toBe(50 * 1024 * 1024);
});

it('generates proper snapshot path', function () {
    $repository = createTestBackupRepository(['storage_path' => '/backups']);
    $job = BackupJob::factory()->create([
        'name' => 'web-backup',
        'destination_path' => 'web',
        'backup_repository_id' => $repository->id,
    ]);

    $snapshot = $job->createSnapshot();

    expect($snapshot->storage_path)->toContain('/backups/web/web-backup_')
        ->and($snapshot->storage_path)->toContain(now()->format('Y-m-d'));
});

it('can get backup type options', function () {
    $options = BackupJob::getBackupTypeOptions();

    expect($options)->toBeArray()
        ->and($options)->toHaveKey('database')
        ->and($options)->toHaveKey('files')
        ->and($options)->toHaveKey('system')
        ->and($options['database'])->toBe('Database Backup');
});

it('can get status color attribute', function () {
    $runningJob = BackupJob::factory()->create(['status' => 'running']);
    $failedJob = BackupJob::factory()->create(['status' => 'failed']);
    $completedJob = BackupJob::factory()->create(['status' => 'completed']);

    expect($runningJob->status_color)->toBe('info')
        ->and($failedJob->status_color)->toBe('danger')
        ->and($completedJob->status_color)->toBe('success');
});

it('has latest snapshot relationship', function () {
    $job = createTestBackupJob();

    $oldSnapshot = BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'created_at' => now()->subDays(2),
    ]);

    $latestSnapshot = BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'created_at' => now()->subDay(),
    ]);

    expect($job->latestSnapshot->id)->toBe($latestSnapshot->id);
});

it('has last successful snapshot relationship', function () {
    $job = createTestBackupJob();

    BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'failed',
        'created_at' => now()->subDay(),
    ]);

    $successfulSnapshot = BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'completed',
        'created_at' => now()->subDays(2),
    ]);

    expect($job->lastSuccessfulSnapshot->id)->toBe($successfulSnapshot->id);
});

it('can find running snapshots', function () {
    $job = createTestBackupJob();

    BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'running',
    ]);

    BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'pending',
    ]);

    BackupSnapshot::factory()->create([
        'backup_job_id' => $job->id,
        'status' => 'completed',
    ]);

    expect($job->runningSnapshots)->toHaveCount(2);
});
