<?php

namespace NetServa\Core\DataObjects;

use Illuminate\Console\Command;
use NetServa\Core\Models\MigrationJob;

/**
 * Migration Job Data Transfer Object
 *
 * Standardizes data structure for migration jobs across
 * console commands and Filament forms.
 */
readonly class MigrationJobData
{
    public function __construct(
        public string $sourceServer,
        public string $targetServer,
        public string $domain,
        public string $jobName,
        public string $migrationType = 'full',
        public ?string $description = null,
        public bool $dryRun = false,
        public bool $stepBackup = true,
        public bool $stepCleanup = true,
        public ?array $configuration = null,
        public ?int $sshHostId = null,
    ) {}

    /**
     * Create from console command input
     */
    public static function fromConsoleInput(Command $command): self
    {
        return new self(
            sourceServer: $command->argument('source'),
            targetServer: $command->argument('target'),
            domain: $command->argument('domain'),
            jobName: $command->option('name') ?? "Migration: {$command->argument('domain')}",
            migrationType: $command->option('type') ?? 'full',
            description: $command->option('description'),
            dryRun: $command->option('dry-run') ?? false,
            stepBackup: ! $command->option('no-backup'),
            stepCleanup: ! $command->option('no-cleanup'),
            configuration: $command->option('config') ? json_decode($command->option('config'), true) : null,
        );
    }

    /**
     * Create from Filament form data
     */
    public static function fromFilamentForm(array $data): self
    {
        return new self(
            sourceServer: $data['source_server'],
            targetServer: $data['target_server'],
            domain: $data['domain'],
            jobName: $data['job_name'],
            migrationType: $data['migration_type'] ?? 'full',
            description: $data['description'] ?? null,
            dryRun: $data['dry_run'] ?? false,
            stepBackup: $data['step_backup'] ?? true,
            stepCleanup: $data['step_cleanup'] ?? true,
            configuration: $data['configuration'] ?? null,
            sshHostId: $data['ssh_host_id'] ?? null,
        );
    }

    /**
     * Create from MigrationJob model
     */
    public static function fromModel(MigrationJob $job): self
    {
        return new self(
            sourceServer: $job->source_server,
            targetServer: $job->target_server,
            domain: $job->domain,
            jobName: $job->job_name,
            migrationType: $job->migration_type,
            description: $job->description,
            dryRun: $job->dry_run,
            stepBackup: $job->step_backup,
            stepCleanup: $job->step_cleanup,
            configuration: $job->configuration,
            sshHostId: $job->ssh_host_id,
        );
    }

    /**
     * Convert to array for validation or storage
     */
    public function toArray(): array
    {
        return [
            'source_server' => $this->sourceServer,
            'target_server' => $this->targetServer,
            'domain' => $this->domain,
            'job_name' => $this->jobName,
            'migration_type' => $this->migrationType,
            'description' => $this->description,
            'dry_run' => $this->dryRun,
            'step_backup' => $this->stepBackup,
            'step_cleanup' => $this->stepCleanup,
            'configuration' => $this->configuration,
            'ssh_host_id' => $this->sshHostId,
            'status' => 'pending',
            'progress' => 0,
        ];
    }

    /**
     * Get migration steps based on type
     */
    public function getSteps(): array
    {
        $steps = match ($this->migrationType) {
            'full' => [
                'backup' => 'Backup source data',
                'database' => 'Migrate database',
                'files' => 'Migrate files',
                'config' => 'Migrate configuration',
                'verify' => 'Verify migration',
                'cleanup' => 'Cleanup temporary files',
            ],
            'database-only' => [
                'backup' => 'Backup database',
                'database' => 'Migrate database',
                'verify' => 'Verify database migration',
            ],
            'files-only' => [
                'backup' => 'Backup files',
                'files' => 'Migrate files',
                'verify' => 'Verify file migration',
            ],
            default => [],
        };

        // Remove optional steps based on configuration
        if (! $this->stepBackup) {
            unset($steps['backup']);
        }

        if (! $this->stepCleanup) {
            unset($steps['cleanup']);
        }

        return $steps;
    }

    /**
     * Check if migration is valid
     */
    public function isValid(): bool
    {
        return $this->sourceServer !== $this->targetServer
            && ! empty($this->domain)
            && ! empty($this->jobName);
    }
}
