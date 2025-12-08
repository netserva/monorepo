<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Add VSite Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new virtualization/containerization platform
 * VSite is now the top of the hierarchy: VSite → VNode → VHost
 */
class AddvsiteCommand extends Command
{
    protected $signature = 'addvsite
                            {name : VSite name (e.g., proxmox-prod, incus-dev)}
                            {provider : Provider (local, binarylane, vultr, customer, etc.)}
                            {technology : Technology type (incus, proxmox, kubernetes, docker, hardware, native)}
                            {--location= : Geographic location}
                            {--description= : Optional description}';

    protected $description = 'Add a new vsite (infrastructure site) (NetServa 3.0 CRUD: Create)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $provider = $this->argument('provider');
        $technology = $this->argument('technology');
        $location = $this->option('location');
        $description = $this->option('description');

        // Check if vsite already exists
        $existing = FleetVsite::where('name', $name)->first();
        if ($existing) {
            $this->error("VSite '{$name}' already exists.");

            return Command::FAILURE;
        }

        // Validate technology
        $validTechnologies = ['incus', 'proxmox', 'kubernetes', 'docker', 'hardware', 'native', 'wireguard', 'vps'];
        if (! in_array($technology, $validTechnologies)) {
            $this->warn("Unknown technology: {$technology}");
            $this->info('Valid options: '.implode(', ', $validTechnologies));
        }

        // Validate provider
        $validProviders = ['local', 'binarylane', 'vultr', 'digitalocean', 'aws', 'azure', 'gcp', 'customer', 'other'];
        if (! in_array($provider, $validProviders)) {
            $this->warn("Unknown provider: {$provider}");
            $this->info('Valid options: '.implode(', ', $validProviders));
        }

        $this->info("Creating vsite: {$name}");

        // Get default capabilities for technology
        $capabilities = FleetVsite::getDefaultCapabilities($technology);

        // Create vsite
        $vsite = FleetVsite::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'provider' => $provider,
            'technology' => $technology,
            'location' => $location,
            'capabilities' => $capabilities,
            'description' => $description,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->info('✓ VSite created successfully');
        $this->newLine();
        $this->line('VSite Details:');
        $this->line("  Name: {$vsite->name}");
        $this->line("  Provider: {$provider}");
        $this->line("  Technology: {$technology}");
        if ($location) {
            $this->line("  Location: {$location}");
        }
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
