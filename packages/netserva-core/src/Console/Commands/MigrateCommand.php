<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\MigrationService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;

class MigrateCommand extends Command
{
    protected $signature = 'ns:migrate 
                            {action? : Migration action (status, sync, run, cleanup, rollback)}
                            {host? : Target server hostname}
                            {--dry-run : Show what would be done without executing}
                            {--force : Skip confirmation prompts}
                            {--backup-dir= : Custom backup directory location}';

    protected $description = 'NetServa server migration and management operations';

    public function handle(MigrationService $migrationService): int
    {
        $command = $this->argument('action');
        $host = $this->argument('host');

        // Interactive command selection if not provided
        if (! $command) {
            $command = select(
                'What migration operation would you like to perform?',
                [
                    'status' => 'Check system and service status',
                    'sync' => 'Sync shell environment to remote server',
                    'run' => 'Execute migration operations',
                    'cleanup' => 'Clean up legacy systems after migration',
                    'rollback' => 'Restore system from backup (not yet implemented)',
                ],
                'status'
            );
        }

        // Interactive host selection if not provided
        if (! $host && in_array($command, ['status', 'sync', 'run', 'cleanup', 'rollback'])) {
            $availableHosts = $migrationService->getAvailableHosts();

            if ($availableHosts->isEmpty()) {
                $this->error('❌ No SSH hosts configured. Please add hosts first using ssh manager.');

                return 1;
            }

            $host = select(
                'Which server would you like to migrate?',
                $availableHosts->pluck('description', 'host')->toArray()
            );
        }

        // Validate required parameters
        if (! $host && in_array($command, ['status', 'sync', 'run', 'cleanup', 'rollback'])) {
            $this->error('❌ Host parameter is required for this command');

            return 1;
        }

        // Set options on the migration service
        $migrationService->setOptions([
            'dry_run' => $this->option('dry-run'),
            'force' => $this->option('force'),
            'backup_dir' => $this->option('backup-dir'),
        ]);

        try {
            $this->info("🚀 Executing migration command: {$command}".($host ? " on {$host}" : ''));

            $result = match ($command) {
                'status' => $this->handleStatus($migrationService, $host),
                'sync' => $this->handleSync($migrationService, $host),
                'run' => $this->handleRun($migrationService, $host),
                'cleanup' => $this->handleCleanup($migrationService, $host),
                'rollback' => $this->handleRollback($migrationService, $host),
                default => $this->error("❌ Unknown command: {$command}") || false
            };

            return $result ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed: {$e->getMessage()}");

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        }
    }

    protected function handleStatus(MigrationService $migrationService, string $host): bool
    {
        $this->info("📊 Checking status of {$host}...");

        $status = $migrationService->checkStatus($host);

        $this->displayStatus($status);

        return true;
    }

    protected function handleSync(MigrationService $migrationService, string $host): bool
    {
        if (! $this->option('force')) {
            $confirmed = confirm("Sync shell environment to {$host}?", true);
            if (! $confirmed) {
                $this->info('❌ Sync cancelled');

                return false;
            }
        }

        $this->info("🔄 Syncing shell environment to {$host}...");

        $steps = [
            'Preparing shell environment' => fn () => $migrationService->prepareEnvironment($host),
            'Uploading files' => fn () => $migrationService->uploadShellFiles($host),
            'Setting permissions' => fn () => $migrationService->setPermissions($host),
            'Verifying sync' => fn () => $migrationService->verifySync($host),
        ];

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            progress(
                label: 'Syncing environment...',
                steps: $steps
            );
        } else {
            // Execute steps directly in testing environment
            foreach ($steps as $label => $step) {
                $result = $step();
                if ($result === false) {
                    $this->error("❌ Step failed: {$label}");

                    return false;
                }
            }
        }

        return true;
    }

    protected function handleRun(MigrationService $migrationService, string $host): bool
    {
        $this->info("🏃 Running migration operations on {$host}...");

        if (! $this->option('force')) {
            $confirmed = confirm("Execute migration on {$host}? This will make system changes.", false);
            if (! $confirmed) {
                $this->info('❌ Migration cancelled');

                return false;
            }
        }

        $steps = [
            'Assessing system state' => fn () => $migrationService->assessSystem($host),
            'Creating backup' => fn () => $migrationService->createBackup($host),
            'Syncing environment' => fn () => $migrationService->syncEnvironment($host),
            'Migrating configurations' => fn () => $migrationService->migrateConfigurations($host),
            'Updating services' => fn () => $migrationService->updateServices($host),
            'Verifying migration' => fn () => $migrationService->verifyMigration($host),
        ];

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            progress(
                label: 'Running migration...',
                steps: $steps
            );
        } else {
            // Execute steps directly in testing environment
            foreach ($steps as $label => $step) {
                $result = $step();
                if ($result === false) {
                    $this->error("❌ Step failed: {$label}");

                    return false;
                }
            }
        }

        return true;
    }

    protected function handleCleanup(MigrationService $migrationService, string $host): bool
    {
        $this->warn("🧹 This will clean up legacy systems on {$host}");

        if (! $this->option('force')) {
            $confirmed = confirm("Clean up legacy systems on {$host}? This cannot be undone.", false);
            if (! $confirmed) {
                $this->info('❌ Cleanup cancelled');

                return false;
            }
        }

        $steps = [
            'Removing legacy files' => fn () => $migrationService->removeLegacyFiles($host),
            'Cleaning old configurations' => fn () => $migrationService->cleanOldConfigs($host),
            'Updating system' => fn () => $migrationService->updateSystem($host),
        ];

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            progress(
                label: 'Cleaning up...',
                steps: $steps
            );
        } else {
            // Execute steps directly in testing environment
            foreach ($steps as $label => $step) {
                $result = $step();
                if ($result === false) {
                    $this->error("❌ Step failed: {$label}");

                    return false;
                }
            }
        }

        return true;
    }

    protected function handleRollback(MigrationService $migrationService, string $host): bool
    {
        $this->error('❌ Rollback functionality not yet implemented');
        $this->info('This feature will restore the system from a previous backup');

        return false;
    }

    protected function displayStatus(array $status): void
    {
        $this->newLine();
        $this->line('<fg=blue>📊 System Status Report</>');
        $this->line(str_repeat('─', 50));

        foreach ($status as $category => $items) {
            $this->line("<fg=yellow>{$category}:</>");

            foreach ($items as $item => $result) {
                $emoji = $result['status'] === 'ok' ? '✅' : ($result['status'] === 'warning' ? '⚠️' : '❌');
                $this->line("  {$emoji} {$item}: {$result['message']}");
            }

            $this->newLine();
        }
    }
}
