<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVSite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Interactive VSite Discovery Command
 *
 * Helps users register new infrastructure sites (providers/environments)
 */
class DiscoverVSitesCommand extends Command
{
    protected $signature = 'fleet:discover-vsites {--name= : VSite name (e.g., prod-proxmox)} {--venue= : Venue to add this VSite to}';

    protected $description = 'Interactively discover and register VSites (infrastructure clusters)';

    public function handle(): int
    {
        info('🏗️  NetServa Fleet VSite Discovery');
        info('Register a new infrastructure site (cluster/group within a venue)');

        // Get or select Venue
        $venueName = $this->option('venue');
        if (! $venueName) {
            $venues = FleetVenue::active()->pluck('name', 'id')->toArray();

            if (empty($venues)) {
                error('No venues found. Run: php artisan fleet:discover-venues first');

                return self::FAILURE;
            }

            $venueId = select(
                label: 'Select venue for this VSite',
                options: $venues
            );
        } else {
            $venue = FleetVenue::where('name', $venueName)->first();
            if (! $venue) {
                error("Venue '{$venueName}' not found!");

                return self::FAILURE;
            }
            $venueId = $venue->id;
        }

        $venue = FleetVenue::find($venueId);

        $name = $this->option('name') ?: text(
            label: 'VSite name',
            placeholder: 'e.g., prod-proxmox, staging-k8s, dev-docker',
            hint: 'Infrastructure cluster/group name',
            required: true
        );

        // Check if already exists
        if (FleetVSite::where('name', $name)->exists()) {
            error("VSite '{$name}' already exists!");

            return self::FAILURE;
        }

        // Parse name components or ask for them
        $components = $this->parseVSiteName($name);

        $technology = $components['technology'] ?: select(
            label: 'Technology platform',
            options: [
                'proxmox' => 'Proxmox VE (VMs + containers)',
                'incus' => 'Incus (LXC containers)',
                'vps' => 'VPS instances',
                'hardware' => 'Bare metal servers',
                'docker' => 'Docker containers',
                'kubernetes' => 'Kubernetes cluster',
                'router' => 'Network routers/switches',
                'mixed' => 'Mixed technologies',
            ],
            default: 'proxmox'
        );

        $description = text(
            label: 'Description (optional)',
            placeholder: 'Brief description of this infrastructure site'
        );

        // API configuration (optional)
        $hasApi = confirm(
            label: 'Does this infrastructure have an API?',
            default: in_array($technology, ['proxmox', 'incus'])
        );

        $apiEndpoint = null;
        $apiCredentials = null;

        if ($hasApi) {
            $apiEndpoint = text(
                label: 'API endpoint URL',
                placeholder: 'https://proxmox.homelab.local:8006/api2/json',
                hint: 'Full URL to the management API'
            );

            $needsCredentials = confirm(
                label: 'Store API credentials now?',
                default: false,
                hint: 'You can add these later via the web interface'
            );

            if ($needsCredentials) {
                $username = text(
                    label: 'API username',
                    placeholder: 'root@pam'
                );

                $password = $this->secret('API password or token');

                $apiCredentials = [
                    'username' => $username,
                    'password' => $password,
                    'stored_at' => now()->toISOString(),
                ];
            }
        }

        // Capabilities based on technology
        $capabilities = $this->getCapabilitiesForTechnology($technology);

        // Show summary
        info('VSite Configuration Summary:');
        $this->table(['Property', 'Value'], [
            ['Venue', $venue->name],
            ['Name', $name],
            ['Technology', $technology],
            ['Description', $description ?: 'None'],
            ['API Endpoint', $apiEndpoint ?: 'None'],
            ['Has Credentials', $apiCredentials ? 'Yes' : 'No'],
            ['Capabilities', implode(', ', $capabilities)],
        ]);

        if (! confirm('Create this VSite?', default: true)) {
            warning('VSite creation cancelled.');

            return self::SUCCESS;
        }

        try {
            $vsite = FleetVSite::create([
                'venue_id' => $venue->id,
                'name' => $name,
                'slug' => str($name)->slug(),
                'provider' => $venue->provider,
                'technology' => $technology,
                'location' => $venue->location,
                'description' => $description,
                'api_endpoint' => $apiEndpoint,
                'api_credentials' => $apiCredentials ? encrypt(json_encode($apiCredentials)) : null,
                'capabilities' => $capabilities,
                'status' => 'active',
                'is_active' => true,
            ]);

            info("✅ VSite '{$vsite->name}' created successfully!");
            info('🔧 Next steps:');
            info("   1. Run: php artisan fleet:discover-vnodes --vsite={$vsite->name}");
            info('   2. Configure SSH access for management nodes');
            info('   3. Discover VHosts on compute nodes');

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to create VSite: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Parse VSite name into components
     */
    protected function parseVSiteName(string $name): array
    {
        $parts = explode('-', $name);

        // Try to detect technology from name
        $technology = null;
        foreach (['proxmox', 'incus', 'vps', 'hardware', 'docker', 'kubernetes', 'k8s', 'router'] as $tech) {
            if (in_array($tech, $parts)) {
                $technology = $tech === 'k8s' ? 'kubernetes' : $tech;
                break;
            }
        }

        return [
            'technology' => $technology,
        ];
    }

    /**
     * Get default capabilities for technology
     */
    protected function getCapabilitiesForTechnology(string $technology): array
    {
        return match ($technology) {
            'proxmox' => ['vm_management', 'container_management', 'storage_management', 'network_management', 'backup'],
            'incus' => ['container_management', 'image_management', 'network_management', 'storage_management'],
            'vps' => ['instance_management', 'network_configuration'],
            'hardware' => ['server_management', 'network_configuration'],
            'docker' => ['container_management', 'image_management'],
            'kubernetes' => ['pod_management', 'service_management', 'ingress_management'],
            'router' => ['network_management', 'firewall_management'],
            'mixed' => ['vm_management', 'container_management', 'network_management'],
            default => ['basic_management'],
        };
    }
}
