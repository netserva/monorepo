<?php

declare(strict_types=1);

namespace NetServa\Ops\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NetServa\Ops\Models\BackupRepository;

/**
 * @extends Factory<BackupRepository>
 */
class BackupRepositoryFactory extends Factory
{
    protected $model = BackupRepository::class;

    public function definition(): array
    {
        // Use hardcoded enum values instead of model constants
        $storageDrivers = ['local', 's3', 'sftp', 'restic', 'google', 'azure', 'backblaze'];

        return [
            'name' => fake()->words(2, true).' Repository',
            'slug' => fake()->slug(),
            'description' => fake()->sentence(),
            'storage_driver' => fake()->randomElement($storageDrivers),
            'storage_path' => '/backups/'.fake()->word(),
            'is_active' => fake()->boolean(85),
            'is_default' => fake()->boolean(20),
            'retention_days' => fake()->numberBetween(7, 365),
            'encryption_enabled' => fake()->boolean(60),

            // Statistics fields (only ones that remain)
            'total_size_bytes' => fake()->numberBetween(0, 500 * 1024 * 1024 * 1024), // Up to 500GB
            'total_snapshots' => fake()->numberBetween(0, 1000),
            'last_backup_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-1 week') : null,
        ];
    }
}
