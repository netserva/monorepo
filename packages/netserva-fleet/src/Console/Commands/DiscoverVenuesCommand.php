<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVenue;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Interactive Venue Discovery Command
 *
 * Helps users register new venues (geographic/logical locations)
 */
class DiscoverVenuesCommand extends Command
{
    protected $signature = 'fleet:discover-venues {--name= : Venue name (e.g., aws-us-east-1, homelab-garage)}';

    protected $description = 'Interactively discover and register Venues (geographic/logical locations)';

    public function handle(): int
    {
        info('🌍  NetServa Fleet Venue Discovery');
        info('Register a new venue (physical or cloud location)');

        $name = $this->option('name') ?: text(
            label: 'Venue name',
            placeholder: 'e.g., aws-us-east-1, homelab-garage, hetzner-fsn1',
            hint: 'Use format: provider-region or location-description',
            required: true
        );

        // Check if already exists
        if (FleetVenue::where('name', $name)->exists()) {
            error("Venue '{$name}' already exists!");

            return self::FAILURE;
        }

        // Parse name components or ask for them
        $components = $this->parseVenueName($name);

        $provider = $components['provider'] ?: select(
            label: 'Infrastructure provider',
            options: [
                'aws' => 'Amazon Web Services (AWS)',
                'azure' => 'Microsoft Azure',
                'gcp' => 'Google Cloud Platform (GCP)',
                'digitalocean' => 'DigitalOcean',
                'linode' => 'Linode/Akamai',
                'vultr' => 'Vultr',
                'hetzner' => 'Hetzner',
                'binarylane' => 'BinaryLane',
                'homelab' => 'Home Lab',
                'local' => 'Local Infrastructure',
                'datacenter' => 'Physical Datacenter',
                'colo' => 'Colocation Facility',
                'other' => 'Other Provider',
            ],
            default: 'local'
        );

        $location = $components['location'] ?: text(
            label: 'Location/City',
            placeholder: 'e.g., Sydney, Frankfurt, garage, office',
            hint: 'Physical location or descriptive name',
            required: true
        );

        $region = text(
            label: 'Region (optional)',
            placeholder: 'e.g., us-east-1, eu-west-2, ap-southeast-2',
            hint: 'Cloud region code or geographic identifier'
        );

        $description = text(
            label: 'Description (optional)',
            placeholder: 'Brief description of this venue'
        );

        // API credentials (optional for cloud providers)
        $needsCredentials = false;
        if (in_array($provider, ['aws', 'azure', 'gcp', 'digitalocean', 'linode', 'vultr', 'hetzner', 'binarylane'])) {
            $needsCredentials = confirm(
                label: 'Store API credentials for this venue?',
                default: false,
                hint: 'You can add these later via the web interface'
            );
        }

        $credentials = null;
        if ($needsCredentials) {
            $credentials = $this->collectProviderCredentials($provider);
        }

        // Metadata
        $metadata = [
            'venue_type' => in_array($provider, ['aws', 'azure', 'gcp', 'digitalocean', 'linode', 'vultr', 'hetzner', 'binarylane']) ? 'cloud' : 'on-premise',
            'discovered_at' => now()->toISOString(),
        ];

        // Show summary
        info('Venue Configuration Summary:');
        $this->table(['Property', 'Value'], [
            ['Name', $name],
            ['Provider', ucfirst($provider)],
            ['Location', $location],
            ['Region', $region ?: 'Not specified'],
            ['Description', $description ?: 'None'],
            ['Type', $metadata['venue_type']],
            ['Has Credentials', $credentials ? 'Yes' : 'No'],
        ]);

        if (! confirm('Create this venue?', default: true)) {
            warning('Venue creation cancelled.');

            return self::SUCCESS;
        }

        try {
            $venue = FleetVenue::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'provider' => $provider,
                'location' => $location,
                'region' => $region ?: null,
                'description' => $description ?: null,
                'credentials' => $credentials,
                'metadata' => $metadata,
                'is_active' => true,
            ]);

            info("✅ Venue '{$venue->name}' created successfully!");
            info('🔧 Next steps:');
            info('   1. Run: php artisan fleet:discover-vsites (add infrastructure sites to this venue)');
            info('   2. Run: php artisan fleet:discover-vnodes (discover compute nodes)');
            info('   3. Run: php artisan fleet:discover-vhosts (discover virtual hosts)');

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to create venue: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Parse Venue name into components
     */
    protected function parseVenueName(string $name): array
    {
        $parts = explode('-', $name);

        // Try to detect common patterns
        $provider = null;
        $location = null;

        if (count($parts) >= 2) {
            // First part is likely provider
            $provider = in_array($parts[0], [
                'aws', 'azure', 'gcp', 'digitalocean', 'linode', 'vultr',
                'hetzner', 'binarylane', 'homelab', 'local', 'datacenter',
            ]) ? $parts[0] : null;

            // Rest is location
            $location = $provider ? implode('-', array_slice($parts, 1)) : implode('-', $parts);
        }

        return [
            'provider' => $provider,
            'location' => $location,
        ];
    }

    /**
     * Collect provider-specific credentials
     */
    protected function collectProviderCredentials(string $provider): array
    {
        return match ($provider) {
            'aws' => [
                'access_key_id' => text(
                    label: 'AWS Access Key ID',
                    required: true
                ),
                'secret_access_key' => $this->secret('AWS Secret Access Key'),
                'default_region' => text(
                    label: 'Default Region',
                    placeholder: 'us-east-1',
                    default: 'us-east-1'
                ),
            ],
            'digitalocean' => [
                'api_token' => $this->secret('DigitalOcean API Token'),
            ],
            'hetzner' => [
                'api_token' => $this->secret('Hetzner Cloud API Token'),
            ],
            'linode' => [
                'api_token' => $this->secret('Linode API Token'),
            ],
            'vultr' => [
                'api_key' => $this->secret('Vultr API Key'),
            ],
            'binarylane' => [
                'api_token' => $this->secret('BinaryLane API Token'),
            ],
            'azure' => [
                'tenant_id' => text(
                    label: 'Azure Tenant ID',
                    required: true
                ),
                'client_id' => text(
                    label: 'Azure Client ID',
                    required: true
                ),
                'client_secret' => $this->secret('Azure Client Secret'),
            ],
            'gcp' => [
                'project_id' => text(
                    label: 'GCP Project ID',
                    required: true
                ),
                'service_account_json' => text(
                    label: 'Path to service account JSON file',
                    placeholder: '/path/to/service-account.json'
                ),
            ],
            default => [],
        };
    }
}
