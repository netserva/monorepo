<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;

/**
 * Add Venue Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new physical location or cloud provider venue
 */
class AddvenueCommand extends Command
{
    protected $signature = 'addvenue
                            {name : Venue name (e.g., binarylane-sydney)}
                            {provider : Provider name (e.g., binarylane, aws, local)}
                            {--location= : Location/region (e.g., sydney, us-east-1)}
                            {--description= : Optional description}';

    protected $description = 'Add a new venue (physical location/cloud provider) (NetServa 3.0 CRUD: Create)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $provider = $this->argument('provider');
        $location = $this->option('location');
        $description = $this->option('description');

        // Check if venue already exists
        $existing = FleetVenue::where('name', $name)->first();
        if ($existing) {
            $this->error("Venue '{$name}' already exists.");

            return Command::FAILURE;
        }

        $this->info("Creating venue: {$name}");

        // Create venue
        $venue = FleetVenue::create([
            'name' => $name,
            'provider' => $provider,
            'location' => $location,
            'description' => $description,
            'is_active' => true,
        ]);

        $this->info('✓ Venue created successfully');
        $this->newLine();
        $this->line('Venue Details:');
        $this->line("  Name: {$venue->name}");
        $this->line("  Provider: {$venue->provider}");
        if ($location) {
            $this->line("  Location: {$location}");
        }
        if ($description) {
            $this->line("  Description: {$description}");
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line("  1. Create a vsite: addvsite {$name} <vsite-name> <technology>");
        $this->line('  2. View venues: shvenue');

        return Command::SUCCESS;
    }
}
