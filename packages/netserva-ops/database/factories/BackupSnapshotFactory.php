<?php

declare(strict_types=1);

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\BackupJob;
use NetServa\Ops\Models\BackupRepository;
use NetServa\Ops\Models\BackupSnapshot;

/**
 * @extends Factory<BackupSnapshot>
 */
class BackupSnapshotFactory extends Factory
{
    protected $model = BackupSnapshot::class;

    public function definition(): array
    {
        // Use hardcoded enum values instead of model constants
        $statuses = ['pending', 'running', 'completed', 'failed', 'partial', 'cancelled'];
        $backupTypes = ['full', 'incremental', 'differential'];
        $triggerTypes = ['manual', 'scheduled', 'api', 'webhook'];

        $originalSize = fake()->numberBetween(1024 * 1024, 10 * 1024 * 1024 * 1024); // 1MB to 10GB

        return [
            'snapshot_id' => 'snapshot_'.fake()->unique()->uuid(),
            'name' => fake()->words(3, true).' Snapshot',
            'backup_job_id' => BackupJob::factory(),
            'backup_repository_id' => BackupRepository::factory(),
            'status' => fake()->randomElement($statuses),
            'backup_type' => fake()->randomElement($backupTypes),
            'started_at' => fake()->dateTimeBetween('-1 week'),
            'completed_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 week') : null,
            'runtime_seconds' => fake()->numberBetween(60, 7200), // 1 minute to 2 hours
            'storage_path' => '/backups/'.fake()->word().'/'.fake()->date().'_backup.tar.gz',
            'storage_filename' => 'backup_'.fake()->date().'_'.fake()->randomNumber(4).'.tar.gz',
            'original_size_bytes' => $originalSize,
            'stored_size_bytes' => $originalSize,
            'checksum_value' => fake()->sha256(),
            'is_encrypted' => fake()->boolean(60),
            'parent_snapshot_id' => fake()->boolean(20) ? null : null, // Will be set by relationships
            'error_message' => fake()->boolean(20) ? fake()->sentence() : null,
            'created_by' => fake()->randomElement(['system', 'admin', fake()->name()]),
            'trigger_type' => fake()->randomElement($triggerTypes),
        ];
    }
}
