<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\MigrationExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

/**
 * Rollback VHost Command - NetServa 3.0
 *
 * Rollback migrated vhost to pre-migration state from backup archive
 *
 * Usage:
 *   php artisan rollback:vhost <vnode> <vhost>
 *   php artisan rollback:vhost markc example.com
 *   php artisan rollback:vhost markc example.com --list
 *   php artisan rollback:vhost markc example.com --archive=pre-migration-20251009.tar.gz
 */
class RollbackVhostCommand extends Command
{
    protected $signature = 'rollback:vhost
                          {vnode : VNode name}
                          {vhost : VHost domain}
                          {--list : List available rollback points}
                          {--archive= : Specific archive file to restore from}
                          {--force : Skip confirmation prompt}';

    protected $description = 'Rollback migrated vhost to pre-migration state';

    protected MigrationExecutionService $migrationService;

    public function __construct(MigrationExecutionService $migrationService)
    {
        parent::__construct();
        $this->migrationService = $migrationService;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $vhostDomain = $this->argument('vhost');

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

        // List mode
        if ($this->option('list')) {
            return $this->listRollbackPoints($vhost);
        }

        // Execute rollback
        return $this->executeRollback($vhost);
    }

    /**
     * List available rollback points
     */
    protected function listRollbackPoints(FleetVhost $vhost): int
    {
        $this->info("📋 Available Rollback Points for {$vhost->domain}");
        $this->newLine();

        $result = $this->migrationService->listRollbackPoints($vhost);

        if (! $result['success']) {
            $this->error("❌ Failed to list rollback points: {$result['error']}");

            return 1;
        }

        if (empty($result['rollback_points'])) {
            $this->warn('No rollback points found.');
            $this->newLine();
            $this->line('Rollback archives are created in .archive/ directory during migration.');
            $this->line('If this vhost was migrated with --no-backup, no rollback is possible.');

            return 0;
        }

        $rows = [];
        foreach ($result['rollback_points'] as $index => $point) {
            $rows[] = [
                '#' => $index + 1,
                'Archive File' => $point['filename'],
                'Created' => $point['created_at'],
            ];
        }

        table(
            headers: ['#', 'Archive File', 'Created'],
            rows: $rows
        );

        $this->newLine();
        $this->info('To rollback to a specific archive:');
        $this->line("  php artisan rollback:vhost {$vhost->vnode->name} {$vhost->domain} --archive=FILENAME");

        return 0;
    }

    /**
     * Execute rollback
     */
    protected function executeRollback(FleetVhost $vhost): int
    {
        $this->info("🔄 Rollback VHost: {$vhost->domain} on {$vhost->vnode->name}");
        $this->newLine();

        // Check if rollback is available
        if (! $vhost->rollback_available) {
            $this->error('❌ No rollback available for this vhost');
            $this->newLine();
            $this->line('Possible reasons:');
            $this->line('  • VHost was migrated with --no-backup flag');
            $this->line('  • VHost was never migrated (migration_status: '.$vhost->migration_status.')');
            $this->line('  • Backup archives were manually deleted');

            return 1;
        }

        // Get rollback points
        $result = $this->migrationService->listRollbackPoints($vhost);

        if (! $result['success'] || empty($result['rollback_points'])) {
            $this->error('❌ No backup archives found');

            return 1;
        }

        $archiveFile = $this->option('archive');

        // Interactive archive selection if not specified
        if (! $archiveFile && count($result['rollback_points']) > 1) {
            $this->line('📋 Multiple rollback points available:');
            $this->newLine();

            $choices = [];
            foreach ($result['rollback_points'] as $point) {
                $choices[$point['path']] = "{$point['filename']} (created {$point['created_at']})";
            }

            $archiveFile = select(
                label: 'Select rollback point',
                options: $choices,
                default: array_key_first($choices)
            );
        } elseif (! $archiveFile) {
            // Use the only available archive
            $archiveFile = $result['rollback_points'][0]['path'];
        } else {
            // Resolve relative archive name to full path
            $vars = $vhost->getAllEnvVars();
            $UPATH = $vars['UPATH'];
            $archiveFile = "{$UPATH}/.archive/{$archiveFile}";
        }

        // Show rollback plan
        $this->newLine();
        $this->line('📋 Rollback Plan:');
        $this->line("  • Archive: {$archiveFile}");
        $this->line('  • Target Status: validated (ready for re-migration)');
        $this->newLine();
        $this->warn('⚠️  Warning: This will restore SSH directories (.ssh, bin, etc)');
        $this->warn('   Current web-centric structure will be modified.');
        $this->newLine();

        // Confirmation (unless --force)
        if (! $this->option('force')) {
            $confirmed = confirm(
                label: 'Proceed with rollback?',
                default: false,
                hint: 'Services will be restarted during rollback'
            );

            if (! $confirmed) {
                $this->info('Rollback cancelled.');

                return 0;
            }
        }

        // Execute rollback
        $this->newLine();
        $this->info('🚀 Starting rollback...');
        $this->newLine();

        $result = $this->migrationService->rollbackVhost($vhost, $archiveFile);

        if (! $result['success']) {
            $this->error("❌ Rollback failed: {$result['error']}");

            return 1;
        }

        // Display success
        $this->newLine();
        $this->info('✅ Rollback completed successfully!');
        $this->newLine();
        $this->line('📊 Rollback Summary:');
        $this->line("  • VHost: {$vhost->domain}");
        $this->line('  • Status: validated (ready for re-migration)');
        $this->line("  • Archive Restored: {$result['archive_restored']}");
        $this->newLine();
        $this->line('ℹ️  VHost has been restored to pre-migration state.');
        $this->line('   You can re-run migration with: php artisan migrate:vhost '.$vhost->vnode->name.' '.$vhost->domain);
        $this->newLine();

        return 0;
    }
}
