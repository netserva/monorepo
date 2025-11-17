<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;

use function Laravel\Prompts\confirm;

/**
 * Delete VNode Command (NetServa 3.0 CRUD: DELETE)
 *
 * Deletes a vnode (with safety checks)
 */
class DelvnodeCommand extends Command
{
    protected $signature = 'delvnode
                            {name : VNode name to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a vnode (NetServa 3.0 CRUD: Delete)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');

        $vnode = FleetVnode::where('name', $name)->with('vhosts')->first();

        if (! $vnode) {
            $this->error("VNode not found: {$name}");

            return Command::FAILURE;
        }

        // Safety check: warn if vnode has vhosts
        $vhostCount = $vnode->vhosts->count();

        if ($vhostCount > 0) {
            $this->warn("⚠️  This vnode has {$vhostCount} vhost(s)!");
            $this->newLine();
            $this->line('VHosts that will be affected:');
            foreach ($vnode->vhosts->take(10) as $vhost) {
                $this->line("  • {$vhost->domain}");
            }
            if ($vhostCount > 10) {
                $this->line('  ... and '.($vhostCount - 10).' more');
            }
            $this->newLine();
        }

        // Confirmation
        if (! $force) {
            $confirmed = confirm(
                label: "Delete vnode '{$name}'?",
                default: false,
                yes: 'Yes, delete it',
                no: 'No, cancel'
            );

            if (! $confirmed) {
                $this->info('Deletion cancelled.');

                return Command::SUCCESS;
            }
        }

        // Delete vnode
        $vnode->delete();

        $this->info("✓ VNode deleted: {$name}");

        if ($vhostCount > 0) {
            $this->warn('Note: Associated vhosts may need cleanup. Check with: shvhost');
        }

        return Command::SUCCESS;
    }
}
