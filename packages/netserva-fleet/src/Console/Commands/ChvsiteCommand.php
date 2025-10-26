<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVSite;

/**
 * Change VSite Command (NetServa 3.0 CRUD: UPDATE)
 *
 * Updates vsite configuration
 */
class ChvsiteCommand extends Command
{
    protected $signature = 'chvsite
                            {name : VSite name to update}
                            {--technology= : Update technology type}
                            {--description= : Update description}';

    protected $description = 'Change vsite configuration (NetServa 3.0 CRUD: Update)';

    public function handle(): int
    {
        $name = $this->argument('name');

        $vsite = FleetVSite::where('name', $name)->first();

        if (! $vsite) {
            $this->error("VSite not found: {$name}");
            $this->info('Available vsites: '.FleetVSite::pluck('name')->implode(', '));

            return Command::FAILURE;
        }

        $updated = false;
        $changes = [];

        if ($technology = $this->option('technology')) {
            $oldTech = $vsite->technology;
            $vsite->technology = $technology;
            $vsite->capabilities = FleetVSite::getDefaultCapabilities($technology);
            $changes[] = "technology: {$oldTech} → {$technology}";
            $updated = true;
        }

        if ($description = $this->option('description')) {
            $vsite->description = $description;
            $changes[] = "description: {$description}";
            $updated = true;
        }

        if (! $updated) {
            $this->warn('No changes specified. Use --technology or --description options.');

            return Command::FAILURE;
        }

        $vsite->save();

        $this->info('✓ VSite updated successfully');
        $this->newLine();
        $this->line('Changes applied:');
        foreach ($changes as $change) {
            $this->line("  • {$change}");
        }

        return Command::SUCCESS;
    }
}
