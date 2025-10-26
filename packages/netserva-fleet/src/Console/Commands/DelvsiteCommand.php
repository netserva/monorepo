<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVSite;

use function Laravel\Prompts\confirm;

/**
 * Delete VSite Command (NetServa 3.0 CRUD: DELETE)
 *
 * Deletes a vsite (with safety checks)
 */
class DelvsiteCommand extends Command
{
    protected $signature = 'delvsite
                            {name : VSite name to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a vsite (NetServa 3.0 CRUD: Delete)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');

        $vsite = FleetVSite::where('name', $name)->with('vnodes.vhosts')->first();

        if (! $vsite) {
            $this->error("VSite not found: {$name}");

            return Command::FAILURE;
        }

        // Safety check: warn if vsite has vnodes
        $vnodeCount = $vsite->vnodes->count();
        $vhostCount = $vsite->vnodes->sum(fn ($vnode) => $vnode->vhosts->count());

        if ($vnodeCount > 0) {
            $this->warn("⚠️  This vsite has {$vnodeCount} vnode(s) and {$vhostCount} vhost(s)!");
            $this->newLine();
            $this->line('VNodes that will be affected:');
            foreach ($vsite->vnodes as $vnode) {
                $this->line("  • {$vnode->name} ({$vnode->vhosts->count()} vhosts)");
            }
            $this->newLine();
        }

        // Confirmation
        if (! $force) {
            $confirmed = confirm(
                label: "Delete vsite '{$name}'?",
                default: false,
                yes: 'Yes, delete it',
                no: 'No, cancel'
            );

            if (! $confirmed) {
                $this->info('Deletion cancelled.');

                return Command::SUCCESS;
            }
        }

        // Delete vsite
        $vsite->delete();

        $this->info("✓ VSite deleted: {$name}");

        if ($vnodeCount > 0) {
            $this->warn('Note: Associated vnodes and vhosts may need cleanup. Check with: shvnode');
        }

        return Command::SUCCESS;
    }
}
