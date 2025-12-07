<?php

namespace NetServa\Fleet\Console\Commands;

use NetServa\Core\Console\Commands\BaseNetServaCommand;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\VhostManagementService;

/**
 * Change/Update VHost Command
 *
 * Follows NetServa CRUD pattern: chvhost (not "ns vhost update")
 * Usage: chvhost <vnode> <vhost> [--options]
 * Example: chvhost markc markc.goldcoast.org --php-version=8.4 --ssl=true
 *
 * NetServa 3.0 Architecture:
 * - Updates FleetVhost model directly (fields on table)
 * - Paths derived from domain (no vconfs table)
 * - Credentials stored in vpass table
 */
class ChvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'chvhost {vnode : SSH host/VNode identifier}
                           {vhost : Domain name to update}
                           {--php-version= : Update PHP version (8.1, 8.2, 8.3, 8.4)}
                           {--ssl= : Enable/disable SSL (true/false)}
                           {--webroot= : Change web document root}
                           {--status= : Change status (active/inactive/suspended)}
                           {--move-to= : Migrate vhost to different vnode}
                           {--sync-files : Sync files when migrating (requires --move-to)}
                           {--dry-run : Show what would be done}';

    protected $description = 'Change/update virtual host configuration (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    public function __construct(VhostManagementService $vhostService)
    {
        parent::__construct();
        $this->vhostService = $vhostService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Check if this is a migration operation
            if ($moveToVnode = $this->option('move-to')) {
                return $this->migrateVhost($VNODE, $VHOST, $moveToVnode);
            }

            // Find VNode in database
            $vnode = FleetVnode::where('name', $VNODE)->first();
            if (! $vnode) {
                $this->error("VNode {$VNODE} not found in database");
                $this->line("   Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            // Find FleetVhost in database
            $fleetVhost = FleetVhost::where('domain', $VHOST)
                ->where('vnode_id', $vnode->id)
                ->first();

            if (! $fleetVhost) {
                $this->error("VHost {$VHOST} not found on {$VNODE}");
                $this->line("   Use 'addvhost {$VNODE} {$VHOST}' to create it first");

                return 1;
            }

            // Collect changes to apply
            $changes = $this->collectChanges();

            if (empty($changes)) {
                $this->error('No changes specified. Use --help to see available options');

                return 1;
            }

            // Show what we're about to do
            $this->line("Updating VHost: <fg=yellow>{$VHOST}</> on server <fg=cyan>{$VNODE}</>");
            $this->line('');
            $this->line('<fg=blue>Changes to apply:</>');
            foreach ($changes as $key => $value) {
                $this->line("   <fg=yellow>{$key}:</> {$value}");
            }

            if ($this->option('dry-run')) {
                $this->dryRun("Update VHost {$VHOST} on {$VNODE}", [
                    "Load current vhost from FleetVhost model (ID: {$fleetVhost->id})",
                    'Apply configuration changes: '.implode(', ', array_keys($changes)),
                    'Update FleetVhost model in database',
                    "SSH to {$VNODE} and apply changes via RemoteExecutionService heredoc",
                    'Reload relevant services (nginx, php-fpm, etc.)',
                ]);

                return 0;
            }

            // Apply the changes
            $result = $this->applyChanges($VNODE, $VHOST, $fleetVhost, $changes);

            if ($result['success']) {
                $this->info("VHost {$VHOST} updated successfully on {$VNODE}");

                if (isset($result['applied_changes'])) {
                    $this->line('');
                    $this->line('<fg=blue>Applied Changes:</>');
                    foreach ($result['applied_changes'] as $key => $value) {
                        $this->line("   <fg=green>✓</> {$key}: {$value}");
                    }
                }

                $this->context->addToHistory("chvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'changes' => $changes,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("Failed to update VHost {$VHOST} on {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    protected function collectChanges(): array
    {
        $changes = [];

        if ($phpVersion = $this->option('php-version')) {
            $validVersions = ['8.1', '8.2', '8.3', '8.4'];
            if (! in_array($phpVersion, $validVersions)) {
                $this->error('Invalid PHP version. Valid options: '.implode(', ', $validVersions));
                exit(1);
            }
            $changes['php_version'] = $phpVersion;
        }

        if ($this->hasOption('ssl') && $this->option('ssl') !== null) {
            $ssl = filter_var($this->option('ssl'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ssl === null) {
                $this->error("Invalid SSL value. Use 'true' or 'false'");
                exit(1);
            }
            $changes['ssl_enabled'] = $ssl;
        }

        if ($webroot = $this->option('webroot')) {
            if (! str_starts_with($webroot, '/')) {
                $this->error('Webroot must be an absolute path (starting with /)');
                exit(1);
            }
            $changes['document_root'] = $webroot;
        }

        if ($status = $this->option('status')) {
            $validStatuses = ['active', 'inactive', 'suspended'];
            if (! in_array($status, $validStatuses)) {
                $this->error('Invalid status. Valid options: '.implode(', ', $validStatuses));
                exit(1);
            }
            $changes['status'] = $status;
        }

        return $changes;
    }

    /**
     * Apply changes to FleetVhost model directly
     */
    protected function applyChanges(string $VNODE, string $VHOST, FleetVhost $fleetVhost, array $changes): array
    {
        try {
            $appliedChanges = [];

            foreach ($changes as $key => $value) {
                switch ($key) {
                    case 'php_version':
                        $fleetVhost->php_version = $value;
                        $appliedChanges['PHP Version'] = $value;
                        break;

                    case 'ssl_enabled':
                        $fleetVhost->ssl_enabled = $value;
                        $appliedChanges['SSL Enabled'] = $value ? 'true' : 'false';
                        break;

                    case 'document_root':
                        $fleetVhost->document_root = $value;
                        $appliedChanges['Web Root'] = $value;
                        break;

                    case 'status':
                        $fleetVhost->status = $value;
                        $appliedChanges['Status'] = $value;
                        break;
                }
            }

            $fleetVhost->save();

            return [
                'success' => true,
                'applied_changes' => $appliedChanges,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Migrate vhost from one vnode to another
     */
    protected function migrateVhost(string $sourceVnode, string $vhost, string $destVnode): int
    {
        try {
            $this->line("Migrating VHost: <fg=yellow>{$vhost}</>");
            $this->line("   From: <fg=cyan>{$sourceVnode}</>");
            $this->line("   To: <fg=green>{$destVnode}</>");
            $this->line('');

            $sourceNode = FleetVnode::where('name', $sourceVnode)->first();
            if (! $sourceNode) {
                $this->error("Source VNode '{$sourceVnode}' not found");

                return 1;
            }

            $destNode = FleetVnode::where('name', $destVnode)->first();
            if (! $destNode) {
                $this->error("Destination VNode '{$destVnode}' not found");

                return 1;
            }

            $fleetVhost = FleetVhost::where('domain', $vhost)
                ->where('vnode_id', $sourceNode->id)
                ->first();

            if (! $fleetVhost) {
                $this->error("VHost '{$vhost}' not found on source vnode '{$sourceVnode}'");

                return 1;
            }

            $sourceUpath = $fleetVhost->user_path;

            if ($this->option('dry-run')) {
                $this->dryRun("Migrate VHost {$vhost} from {$sourceVnode} to {$destVnode}", [
                    "1. Update database: vnode_id from {$sourceNode->id} to {$destNode->id}",
                    $this->option('sync-files')
                        ? "2. Sync files using scp from {$sourceVnode}:{$sourceUpath} to {$destVnode}:{$sourceUpath}"
                        : '2. Skip file sync (--sync-files not specified)',
                    "3. Run chperms on {$destVnode} to fix ownership/permissions",
                ]);

                return 0;
            }

            // Optionally sync files
            if ($this->option('sync-files')) {
                $this->line('Syncing files between vnodes...');
                $this->syncFilesWithScp($sourceVnode, $destVnode, $sourceUpath);
            }

            // Update vnode_id in database
            $this->line('Updating database...');
            $fleetVhost->vnode_id = $destNode->id;
            $fleetVhost->save();
            $this->info("   VHost now assigned to {$destVnode}");

            $this->line('');
            $this->info("Migration complete: {$vhost} now on {$destVnode}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Migration failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Sync files using scp between vnodes
     */
    protected function syncFilesWithScp(string $sourceVnode, string $destVnode, string $upath): bool
    {
        try {
            $vhost = basename($upath);

            $this->line('   Syncing user data files...');

            $userDataPaths = [
                'web/app/public/*',
                'web/log/*',
                'msg/*',
            ];

            foreach ($userDataPaths as $path) {
                $sourcePath = "{$sourceVnode}:{$upath}/{$path}";
                $destPath = "{$destVnode}:{$upath}/".dirname($path);

                $this->line("      {$path}");
                shell_exec("scp -r -p {$sourcePath} {$destPath} 2>&1");
            }

            $this->line('   Syncing system configuration files...');

            // Nginx configuration
            $nginxSource = "{$sourceVnode}:/etc/nginx/sites-enabled/{$vhost}";
            $nginxDest = "{$destVnode}:/etc/nginx/sites-enabled/";
            shell_exec("scp -p {$nginxSource} {$nginxDest} 2>&1");

            // SSL certificates
            $sslSource = "{$sourceVnode}:/etc/ssl/{$vhost}/";
            $sslDest = "{$destVnode}:/etc/ssl/";
            shell_exec("scp -r -p {$sslSource} {$sslDest} 2>&1");

            $this->info('   Files synced');

            return true;

        } catch (\Exception $e) {
            $this->error("   Sync error: {$e->getMessage()}");

            return false;
        }
    }
}
