<?php

namespace NetServa\Core\DataObjects;

use Illuminate\Console\Command;
use NetServa\Core\Models\SetupJob;

/**
 * Setup Job Data Transfer Object
 *
 * Standardizes data structure for setup/deployment jobs across
 * console commands and Filament forms.
 */
readonly class SetupJobData
{
    public function __construct(
        public string $jobName,
        public int $templateId,
        public string $targetHost,
        public ?array $configuration = null,
        public ?string $description = null,
        public bool $dryRun = false,
        public ?int $priority = 0,
    ) {}

    /**
     * Create from console command input
     */
    public static function fromConsoleInput(Command $command, int $templateId): self
    {
        return new self(
            jobName: $command->option('name') ?? "Setup: {$command->argument('host')}",
            templateId: $templateId,
            targetHost: $command->argument('host'),
            configuration: $command->option('config') ? json_decode($command->option('config'), true) : null,
            description: $command->option('description'),
            dryRun: $command->option('dry-run') ?? false,
        );
    }

    /**
     * Create from Filament form data
     */
    public static function fromFilamentForm(array $data): self
    {
        return new self(
            jobName: $data['job_name'],
            templateId: $data['template_id'],
            targetHost: $data['target_host'],
            configuration: $data['configuration'] ?? null,
            description: $data['description'] ?? null,
            dryRun: $data['dry_run'] ?? false,
            priority: $data['priority'] ?? 0,
        );
    }

    /**
     * Create from SetupJob model
     */
    public static function fromModel(SetupJob $job): self
    {
        return new self(
            jobName: $job->job_name,
            templateId: $job->template_id,
            targetHost: $job->target_host,
            configuration: $job->configuration,
            description: $job->description,
            dryRun: $job->dry_run,
            priority: $job->priority,
        );
    }

    /**
     * Convert to array for validation or storage
     */
    public function toArray(): array
    {
        return [
            'job_name' => $this->jobName,
            'template_id' => $this->templateId,
            'target_host' => $this->targetHost,
            'configuration' => $this->configuration,
            'description' => $this->description,
            'dry_run' => $this->dryRun,
            'priority' => $this->priority,
            'status' => 'pending',
            'progress' => 0,
        ];
    }

    /**
     * Merge with template defaults
     */
    public function mergeWithDefaults(array $templateDefaults): array
    {
        return array_merge($templateDefaults, $this->configuration ?? []);
    }
}
