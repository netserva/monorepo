<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;

/**
 * Change Venue Command (NetServa 3.0 CRUD: UPDATE)
 *
 * Updates venue configuration
 */
class ChvenueCommand extends Command
{
    protected $signature = 'chvenue
                            {name : Venue name to update}
                            {--provider= : Update provider name}
                            {--location= : Update location/region}
                            {--description= : Update description}
                            {--active= : Set active status (1=active, 0=inactive)}';

    protected $description = 'Change venue configuration (NetServa 3.0 CRUD: Update)';

    public function handle(): int
    {
        $name = $this->argument('name');

        $venue = FleetVenue::where('name', $name)->first();

        if (! $venue) {
            $this->error("Venue not found: {$name}");
            $this->info('Available venues: '.FleetVenue::pluck('name')->implode(', '));

            return Command::FAILURE;
        }

        $updated = false;
        $changes = [];

        if ($provider = $this->option('provider')) {
            $venue->provider = $provider;
            $changes[] = "provider: {$provider}";
            $updated = true;
        }

        if ($location = $this->option('location')) {
            $venue->location = $location;
            $changes[] = "location: {$location}";
            $updated = true;
        }

        if ($description = $this->option('description')) {
            $venue->description = $description;
            $changes[] = "description: {$description}";
            $updated = true;
        }

        if (($active = $this->option('active')) !== null) {
            $venue->is_active = (bool) $active;
            $changes[] = 'status: '.($venue->is_active ? 'active' : 'inactive');
            $updated = true;
        }

        if (! $updated) {
            $this->warn('No changes specified. Use --provider, --location, --description, or --active options.');

            return Command::FAILURE;
        }

        $venue->save();

        $this->info('✓ Venue updated successfully');
        $this->newLine();
        $this->line('Changes applied:');
        foreach ($changes as $change) {
            $this->line("  • {$change}");
        }

        return Command::SUCCESS;
    }
}
