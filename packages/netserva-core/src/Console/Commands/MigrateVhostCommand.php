<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\table;

/**
 * Migrate VHost Command - NetServa 3.0
 *
 * Executes structural migration from NetServa 1.0 to 3.0 architecture
 *
 * Usage:
 *   php artisan migrate:vhost <vnode> <vhost>
 *   php artisan migrate:vhost markc example.com
 *   php artisan migrate:vhost --all-validated
 *   php artisan migrate:vhost markc example.com --dry-run
 *   php artisan migrate:vhost markc example.com --no-backup --force
 */
class MigrateVhostCommand extends Command
{
    protected $signature = 'migrate:vhost
                          {vnode? : VNode name}
                          {vhost? : VHost domain}
                          {--all-validated : Migrate all validated vhosts}
                          {--dry-run : Show migration plan without executing}
                          {--no-backup : Skip backup creation (dangerous!)}
                          {--force : Skip confirmation prompts}';

    protected $description = 'Migrate vhost from NetServa 1.0 to 3.0 structure';

    protected MigrationExecutionService $migrationService;

    public function __construct(MigrationExecutionService $migrationService)
    {
        parent::__construct();
        $this->migrationService = $migrationService;
    }

    public function handle(): int
    {
        if ($this->option('all-validated')) {
            return $this->migrateAllValidated();
        }

        $vnodeName = $this->argument('vnode');
        $vhostDomain = $this->argument('vhost');

        if (! $vnodeName || ! $vhostDomain) {
            $this->error('Please provide vnode and vhost arguments, or use --all-validated flag');
            $this->line('');
            $this->line('Usage:');
            $this->line('  php artisan migrate:vhost <vnode> <vhost>');
            $this->line('  php artisan migrate:vhost markc example.com');
            $this->line('  php artisan migrate:vhost --all-validated');

            return 1;
        }

        return $this->migrateSingleVhost($vnodeName, $vhostDomain);
    }

    /**
     * Migrate a single vhost
     */
    protected function migrateSingleVhost(string $vnodeName, string $vhostDomain): int
    {
        $this->info("🔄 Migrating VHost: {$vhostDomain} on {$vnodeName}");

        // Find vnode
        $vnode = FleetVnode::where('name', $vnodeName)->first();
        if (! $vnode) {
            $this->error("❌ VNode not found: {$vnodeName}");

            return 1;
        }

        // Find vhost
        $vhost = FleetVhost::where('domain', $vhostDomain)
            ->where('vnode_id', $vnode->id)
            ->first();

        if (! $vhost) {
            $this->error("❌ VHost not found: {$vhostDomain}");

            return 1;
        }

        // Show current status
        $this->newLine();
        $this->line('📊 Current Status:');
        $this->line("  • Migration Status: {$vhost->migration_status}");
        $this->line("  • VHost Status: {$vhost->status}");
        $this->newLine();

        // Dry-run mode
        if ($this->option('dry-run')) {
            return $this->showDryRun($vhost);
        }

        // Confirmation (unless --force)
        if (! $this->option('force')) {
            $skipBackup = $this->option('no-backup');

            if ($skipBackup) {
                $this->warn('⚠️  --no-backup flag detected! No rollback will be available.');
                $this->newLine();
            }

            $confirmed = confirm(
                label: 'Proceed with migration?',
                default: false,
                hint: $skipBackup ? 'No backup will be created!' : 'A backup archive will be created first'
            );

            if (! $confirmed) {
                $this->info('Migration cancelled.');

                return 0;
            }
        }

        // Execute migration
        $this->newLine();
        $this->info('🚀 Starting migration...');
        $this->newLine();

        $result = $this->migrationService->migrateVhost(
            $vhost,
            skipBackup: $this->option('no-backup')
        );

        if (! $result['success']) {
            $this->error("❌ Migration failed: {$result['error']}");
            $this->newLine();

            if (isset($result['migration_log'])) {
                $this->displayMigrationLog($result['migration_log']);
            }

            return 1;
        }

        // Display success
        $this->displayMigrationSuccess($vhost, $result);

        return 0;
    }

    /**
     * Migrate all validated vhosts
     */
    protected function migrateAllValidated(): int
    {
        $this->info('🔄 Migrating all validated vhosts');
        $this->newLine();

        $vhosts = FleetVhost::where('migration_status', 'validated')
            ->with('vnode')
            ->get();

        if ($vhosts->isEmpty()) {
            $this->warn('No validated vhosts found');

            return 0;
        }

        $this->info("Found {$vhosts->count()} validated vhosts");
        $this->newLine();

        // Confirmation (unless --force)
        if (! $this->option('force')) {
            $skipBackup = $this->option('no-backup');

            if ($skipBackup) {
                $this->warn('⚠️  --no-backup flag detected! No rollback will be available for ANY vhost.');
                $this->newLine();
            }

            $confirmed = confirm(
                label: "Migrate {$vhosts->count()} vhosts?",
                default: false,
                hint: $skipBackup ? 'No backups will be created!' : 'Backup archives will be created'
            );

            if (! $confirmed) {
                $this->info('Migration cancelled.');

                return 0;
            }
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($vhosts as $vhost) {
            $this->line("Migrating: {$vhost->domain} on {$vhost->vnode->name}...");

            $result = $this->migrationService->migrateVhost(
                $vhost,
                skipBackup: $this->option('no-backup')
            );

            $status = $result['success'] ? '✅ Success' : '❌ Failed';
            $stepCount = isset($result['migration_log']['steps_completed'])
                ? count($result['migration_log']['steps_completed'])
                : 0;

            $results[] = [
                'VHost' => $vhost->domain,
                'VNode' => $vhost->vnode->name,
                'Status' => $status,
                'Steps' => $stepCount,
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
                $this->error("  Error: {$result['error']}");
            }
        }

        $this->newLine();
        table(
            headers: ['VHost', 'VNode', 'Status', 'Steps'],
            rows: $results
        );

        $this->newLine();
        $this->info('📊 Migration Summary:');
        $this->line("  • Total: {$vhosts->count()}");
        $this->line("  • Success: {$successCount}");
        $this->line("  • Failed: {$failureCount}");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Show dry-run migration plan
     */
    protected function showDryRun(FleetVhost $vhost): int
    {
        $this->info('🔍 Dry-Run Mode: Migration Plan');
        $this->newLine();

        $vars = $vhost->getAllEnvVars();

        $this->line('📋 Migration Steps:');
        $this->newLine();

        $steps = [
            ['Step', 'Action', 'Details'],
            ['1', 'Pre-flight Checks', 'Verify migration status, disk space'],
            ['2', 'Backup Creation', $this->option('no-backup') ? '⚠️  SKIPPED' : "Archive to {$vars['UPATH']}/.archive/"],
            ['3', 'Structural Migration', 'Move var/log→web/log, var/run→web/run, web/*→web/app/public'],
            ['4', 'Permission Update', 'Set web-centric permissions (755/750)'],
            ['5', 'Service Reload', 'Reload nginx, php-fpm'],
            ['6', 'Verification', 'Check directories exist, index file present'],
            ['7', 'Database Update', 'Set migration_status: migrated'],
        ];

        table(
            headers: array_shift($steps),
            rows: $steps
        );

        $this->newLine();
        $this->line('📁 Expected Directory Structure After Migration:');
        $this->newLine();
        $this->line("{$vars['UPATH']}/");
        $this->line('├── .archive/          ← Backup archives');
        $this->line('├── msg/               ← Mail storage (unchanged)');
        $this->line('└── web/               ← Web root (restructured)');
        $this->line('    ├── app/');
        $this->line('    │   └── public/    ← Web files moved here');
        $this->line('    ├── log/           ← Moved from var/log');
        $this->line('    └── run/           ← Moved from var/run');

        $this->newLine();
        $this->info('ℹ️  This was a dry-run. No changes were made.');
        $this->line('Remove --dry-run flag to execute migration.');

        return 0;
    }

    /**
     * Display migration success
     */
    protected function displayMigrationSuccess(FleetVhost $vhost, array $result): void
    {
        $this->newLine();
        $this->info('✅ Migration completed successfully!');
        $this->newLine();

        $log = $result['migration_log'];

        $this->line('📊 Migration Summary:');
        $this->line("  • VHost: {$vhost->domain}");
        $this->line('  • Status: migrated');
        $this->line('  • Steps Completed: '.count($log['steps_completed']));
        $this->line('  • Duration: '.($this->calculateDuration($log) ?? 'N/A'));

        if (isset($log['backup_archive'])) {
            $this->newLine();
            $this->line("💾 Backup Archive: {$log['backup_archive']}");
            $this->line('   Rollback available: php artisan rollback:vhost '.$vhost->vnode->name.' '.$vhost->domain);
        }

        if (! empty($log['warnings'])) {
            $this->newLine();
            $this->warn('⚠️  Warnings:');
            foreach ($log['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }

        if (! empty($log['structural_changes'])) {
            $this->newLine();
            $this->line('🔧 Structural Changes:');
            foreach ($log['structural_changes'] as $change) {
                $this->line('  • '.str_replace('_', ' ', $change));
            }
        }

        $this->newLine();
    }

    /**
     * Display migration log on failure
     */
    protected function displayMigrationLog(array $log): void
    {
        $this->line('📋 Migration Log:');
        $this->newLine();

        if (! empty($log['steps_completed'])) {
            $this->line('✅ Steps Completed:');
            foreach ($log['steps_completed'] as $step) {
                $this->line('  • '.str_replace('_', ' ', $step));
            }
            $this->newLine();
        }

        if (! empty($log['errors'])) {
            $this->error('❌ Errors:');
            foreach ($log['errors'] as $error) {
                $this->line("  • {$error}");
            }
            $this->newLine();
        }

        if (! empty($log['warnings'])) {
            $this->warn('⚠️  Warnings:');
            foreach ($log['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
            $this->newLine();
        }
    }

    /**
     * Calculate migration duration from log
     */
    protected function calculateDuration(array $log): ?string
    {
        if (! isset($log['started_at']) || ! isset($log['completed_at'])) {
            return null;
        }

        $start = \Carbon\Carbon::parse($log['started_at']);
        $end = \Carbon\Carbon::parse($log['completed_at']);

        return $start->diffForHumans($end, true);
    }
}
