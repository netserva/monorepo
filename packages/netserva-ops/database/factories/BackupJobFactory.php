<?php

declare(strict_types=1);

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\BackupJob;
use NetServa\Ops\Models\BackupRepository;

/**
 * @extends Factory<BackupJob>
 */
class BackupJobFactory extends Factory
{
    protected $model = BackupJob::class;

    public function definition(): array
    {
        return [
            'job_id' => 'job_'.fake()->unique()->uuid(),
            'name' => fake()->words(3, true).' Backup',
            'display_name' => fake()->words(3, true).' Backup Job',
            'description' => fake()->sentence(),
            'backup_repository_id' => BackupRepository::factory(),
            'target_host' => fake()->domainName(),
            'source_paths' => json_encode([
                '/var/www/'.fake()->word(),
                '/var/log/'.fake()->word(),
            ]),
            'destination_path' => '/backups/'.fake()->word(),
            'backup_type' => fake()->randomElement(['full', 'incremental', 'differential']),
            'exclude_patterns' => json_encode([
                '*.tmp',
                '*.log',
                '*.cache',
            ]),
            'compression' => fake()->randomElement(['gzip', 'bzip2', 'xz', 'none']),
            'enabled' => fake()->boolean(80),
            'schedule' => fake()->boolean(60) ? '0 2 * * *' : null,
            'status' => fake()->randomElement(['pending', 'active', 'completed', 'failed']),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'started_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 week') : null,
            'completed_at' => fake()->boolean(40) ? fake()->dateTimeBetween('-1 week') : null,
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'backup_size_bytes' => fake()->numberBetween(1024 * 1024, 10 * 1024 * 1024 * 1024),
            'output_log' => fake()->boolean(60) ? fake()->paragraph() : null,
            'error_log' => fake()->boolean(20) ? fake()->sentence() : null,
            'backup_filename' => fake()->boolean(60) ? 'backup_'.fake()->date().'.tar.gz' : null,
            'retention_enabled' => fake()->boolean(70),
            'retention_days' => fake()->numberBetween(7, 365),
            'initiated_by' => fake()->name(),
        ];
    }
}
