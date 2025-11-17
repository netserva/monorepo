<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Add VSite Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new virtualization/containerization platform
 */
class AddvsiteCommand extends Command
{
    protected $signature = 'addvsite
                            {venue : Venue name where vsite will be created}
                            {name : VSite name (e.g., proxmox-prod, incus-dev)}
                            {technology : Technology type (incus, proxmox, kubernetes, docker, hardware, native)}
                            {--description= : Optional description}';

    protected $description = 'Add a new vsite (virtualization platform) (NetServa 3.0 CRUD: Create)';

    public function handle(): int
    {
        $venueName = $this->argument('venue');
        $name = $this->argument('name');
        $technology = $this->argument('technology');
        $description = $this->option('description');

        // Validate venue exists
        $venue = FleetVenue::where('name', $venueName)->first();
        if (! $venue) {
            $this->error("Venue not found: {$venueName}");
            $this->info('Available venues: '.FleetVenue::pluck('name')->implode(', '));
            $this->warn("Create venue first: addvenue {$venueName} <provider> --location=<location>");

            return Command::FAILURE;
        }

        // Check if vsite already exists
        $existing = FleetVsite::where('name', $name)->first();
        if ($existing) {
            $this->error("VSite '{$name}' already exists.");

            return Command::FAILURE;
        }

        // Validate technology
        $validTechnologies = ['incus', 'proxmox', 'kubernetes', 'docker', 'hardware', 'native', 'wireguard'];
        if (! in_array($technology, $validTechnologies)) {
            $this->warn("Unknown technology: {$technology}");
            $this->info('Valid options: '.implode(', ', $validTechnologies));
        }

        $this->info("Creating vsite: {$name} on venue {$venueName}");

        // Get default capabilities for technology
        $capabilities = FleetVsite::getDefaultCapabilities($technology);

        // Create vsite
        $vsite = FleetVsite::create([
            'venue_id' => $venue->id,
            'name' => $name,
            'provider' => $venue->provider,  // Inherit from venue
            'technology' => $technology,
            'capabilities' => $capabilities,
            'description' => $description,
        ]);

        $this->info('✓ VSite created successfully');
        $this->newLine();
        $this->line('VSite Details:');
        $this->line("  Name: {$vsite->name}");
        $this->line("  Venue: {$venue->name}");
        $this->line("  Technology: {$technology}");
        if ($description) {
            $this->line("  Description: {$description}");
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Add SSH host: addssh <vnode-name> <hostname>');
        $this->line("  2. Create vnode: addvnode {$name} <vnode-name> <ssh-host>");
        $this->line('  3. View vsites: shvsite');

        return Command::SUCCESS;
    }
}
