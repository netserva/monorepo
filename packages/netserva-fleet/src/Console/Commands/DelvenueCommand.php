<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;

use function Laravel\Prompts\confirm;

/**
 * Delete Venue Command (NetServa 3.0 CRUD: DELETE)
 *
 * Deletes a venue (with safety checks)
 */
class DelvenueCommand extends Command
{
    protected $signature = 'delvenue
                            {name : Venue name to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a venue (NetServa 3.0 CRUD: Delete)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');

        $venue = FleetVenue::where('name', $name)->with('vsites.vnodes')->first();

        if (! $venue) {
            $this->error("Venue not found: {$name}");

            return Command::FAILURE;
        }

        // Safety check: warn if venue has vsites
        $vsiteCount = $venue->vsites->count();
        $vnodeCount = $venue->vsites->sum(fn ($vsite) => $vsite->vnodes->count());

        if ($vsiteCount > 0) {
            $this->warn("⚠️  This venue has {$vsiteCount} vsite(s) and {$vnodeCount} vnode(s)!");
            $this->newLine();
            $this->line('VSites that will be affected:');
            foreach ($venue->vsites as $vsite) {
                $this->line("  • {$vsite->name} ({$vsite->vnodes->count()} vnodes)");
            }
            $this->newLine();
        }

        // Confirmation
        if (! $force) {
            $confirmed = confirm(
                label: "Delete venue '{$name}'?",
                default: false,
                yes: 'Yes, delete it',
                no: 'No, cancel'
            );

            if (! $confirmed) {
                $this->info('Deletion cancelled.');

                return Command::SUCCESS;
            }
        }

        // Delete venue (cascade will handle vsites/vnodes if configured)
        $venue->delete();

        $this->info("✓ Venue deleted: {$name}");

        if ($vsiteCount > 0) {
            $this->warn('Note: Associated vsites and vnodes may need cleanup. Check with: shvsite');
        }

        return Command::SUCCESS;
    }
}
