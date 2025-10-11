<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VhostManagementService;

/**
 * Delete VHost Command
 *
 * Follows NetServa CRUD pattern: delvhost (not "ns vhost delete")
 * Usage: delvhost <vnode> <vhost>
 * Example: delvhost markc markc.goldcoast.org
 */
class DelvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'delvhost {vnode : SSH host/VNode identifier} {vhost : Domain name to delete} {--force : Skip confirmation} {--dry-run : Show what would be done}';

    protected $description = 'Delete a virtual host (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    public function __construct(VhostManagementService $vhostService)
    {
        parent::__construct();
        $this->vhostService = $vhostService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters from positional arguments
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Show what we're about to do
            $this->line("🗑️  Deleting VHost: <fg=yellow>{$VHOST}</> from server <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Delete VHost {$VHOST} from {$VNODE}", [
                    "Load config from vconfs table (database-first)",
                    "SSH to {$VNODE} and execute cleanup via heredoc script",
                    'Remove user, directories, database on remote',
                    'Remove SSL certificate on remote',
                    'Remove nginx, PHP-FPM configuration on remote',
                    "Soft-delete fleet_vhosts record (cascades to vconfs)",
                ]);

                return 0;
            }

            // Safety confirmation (unless --force)
            if (! $this->option('force')) {
                if (! $this->confirm("⚠️  Are you sure you want to delete VHost {$VHOST}? This cannot be undone.")) {
                    $this->info('🛑 Deletion cancelled');

                    return 0;
                }
            }

            // Delete the VHost using NetServa service
            $result = $this->vhostService->deleteVhost($VNODE, $VHOST);

            if ($result['success']) {
                $this->info("✅ VHost {$VHOST} deleted successfully from {$VNODE}");

                // Add to command history
                $this->context->addToHistory("delvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to delete VHost {$VHOST} from {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }
}
