<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVSite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * VSite CRUD Command
 *
 * Full CRUD operations for VSites matching Filament admin panel
 */
class FleetVSiteCommand extends Command
{
    protected $signature = 'fleet:vsite
                            {action : Action to perform (list|show|create|edit|delete)}
                            {id? : VSite ID or name for show/edit/delete actions}';

    protected $description = 'Manage VSites (Infrastructure Sites) - CRUD operations';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listVSites(),
            'show' => $this->showVSite(),
            'create' => $this->createVSite(),
            'edit' => $this->editVSite(),
            'delete' => $this->deleteVSite(),
            default => $this->showUsage(),
        };
    }

    protected function listVSites(): int
    {
        $vsites = FleetVSite::withCount('vnodes')->get();

        if ($vsites->isEmpty()) {
            info('No VSites found.');

            return self::SUCCESS;
        }

        table(
            ['ID', 'Name', 'Provider', 'Technology', 'Location', 'Nodes', 'Status', 'API'],
            $vsites->map(fn ($vs) => [
                $vs->id,
                $vs->name,
                $vs->provider,
                $vs->technology,
                $vs->location ?? '-',
                $vs->vnodes_count,
                $vs->status,
                $vs->api_endpoint ? '✓' : '✗',
            ])->toArray()
        );

        return self::SUCCESS;
    }

    protected function showVSite(): int
    {
        $vsite = $this->getVSite();
        if (! $vsite) {
            return self::FAILURE;
        }

        info("VSite Details: {$vsite->name}");

        table(['Property', 'Value'], [
            ['ID', $vsite->id],
            ['Name', $vsite->name],
            ['Slug', $vsite->slug],
            ['Provider', $vsite->provider],
            ['Technology', $vsite->technology],
            ['Location', $vsite->location ?? 'Not set'],
            ['Description', $vsite->description ?? 'None'],
            ['API Endpoint', $vsite->api_endpoint ?? 'Not configured'],
            ['Has Credentials', $vsite->api_credentials ? 'Yes' : 'No'],
            ['Capabilities', implode(', ', $vsite->capabilities ?? [])],
            ['Status', $vsite->status],
            ['Active', $vsite->is_active ? 'Yes' : 'No'],
            ['Created', $vsite->created_at->format('Y-m-d H:i:s')],
            ['Updated', $vsite->updated_at->format('Y-m-d H:i:s')],
        ]);

        // Show related VNodes
        $vnodes = $vsite->vnodes()->withCount('vhosts')->get();
        if ($vnodes->isNotEmpty()) {
            info("\nVNodes in this VSite:");
            table(
                ['ID', 'Name', 'Role', 'Environment', 'VHosts', 'Status'],
                $vnodes->map(fn ($vn) => [
                    $vn->id,
                    $vn->name,
                    $vn->role,
                    $vn->environment,
                    $vn->vhosts_count,
                    $vn->status,
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }

    protected function createVSite(): int
    {
        info('Creating new VSite');

        $name = text(
            label: 'VSite name',
            placeholder: 'e.g., aws-east-production',
            required: true,
            validate: fn ($value) => FleetVSite::where('name', $value)->exists()
                ? 'VSite with this name already exists'
                : null
        );

        $provider = select(
            label: 'Provider',
            options: [
                'local' => 'Local (homelab, office)',
                'aws' => 'Amazon Web Services',
                'gcp' => 'Google Cloud Platform',
                'azure' => 'Microsoft Azure',
                'digitalocean' => 'DigitalOcean',
                'linode' => 'Linode/Akamai',
                'vultr' => 'Vultr',
                'hetzner' => 'Hetzner',
                'binarylane' => 'BinaryLane',
                'customer' => 'Customer infrastructure',
                'other' => 'Other',
            ]
        );

        $technology = select(
            label: 'Technology',
            options: [
                'proxmox' => 'Proxmox VE',
                'incus' => 'Incus (LXC)',
                'docker' => 'Docker',
                'kubernetes' => 'Kubernetes',
                'vps' => 'VPS instances',
                'hardware' => 'Bare metal',
                'vmware' => 'VMware',
                'hyper-v' => 'Hyper-V',
                'mixed' => 'Mixed technologies',
            ]
        );

        $location = text('Location (optional)', 'e.g., sydney, datacenter-1');
        $description = text('Description (optional)');

        $apiEndpoint = null;
        $apiCredentials = null;

        if (confirm('Configure API access?', default: false)) {
            $apiEndpoint = text('API endpoint URL', 'https://api.example.com');

            if (confirm('Store API credentials now?', default: false)) {
                $credentialsType = select(
                    'Credentials type',
                    ['token' => 'API Token', 'password' => 'Username/Password', 'key' => 'API Key']
                );

                $creds = ['type' => $credentialsType, 'created_at' => now()->toISOString()];

                switch ($credentialsType) {
                    case 'token':
                        $creds['user'] = text('Username');
                        $creds['token_id'] = text('Token ID');
                        $creds['token_secret'] = $this->secret('Token Secret');
                        break;
                    case 'password':
                        $creds['username'] = text('Username');
                        $creds['password'] = $this->secret('Password');
                        break;
                    case 'key':
                        $creds['api_key'] = $this->secret('API Key');
                        break;
                }

                $apiCredentials = encrypt(json_encode($creds));
            }
        }

        $capabilities = $this->getCapabilitiesForTechnology($technology);

        try {
            $vsite = FleetVSite::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'provider' => $provider,
                'technology' => $technology,
                'location' => $location ?: null,
                'description' => $description ?: null,
                'api_endpoint' => $apiEndpoint,
                'api_credentials' => $apiCredentials,
                'capabilities' => $capabilities,
                'status' => 'active',
                'is_active' => true,
            ]);

            info("✅ VSite '{$vsite->name}' created successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to create VSite: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function editVSite(): int
    {
        $vsite = $this->getVSite();
        if (! $vsite) {
            return self::FAILURE;
        }

        info("Editing VSite: {$vsite->name}");

        $updates = [];

        if (confirm("Update name? (current: {$vsite->name})", default: false)) {
            $updates['name'] = text('New name', default: $vsite->name, required: true);
        }

        if (confirm("Update provider? (current: {$vsite->provider})", default: false)) {
            $updates['provider'] = select('Provider', [
                'local' => 'Local', 'aws' => 'AWS', 'gcp' => 'GCP', 'azure' => 'Azure',
                'digitalocean' => 'DigitalOcean', 'vultr' => 'Vultr', 'other' => 'Other',
            ], default: $vsite->provider);
        }

        if (confirm("Update technology? (current: {$vsite->technology})", default: false)) {
            $updates['technology'] = select('Technology', [
                'proxmox' => 'Proxmox', 'incus' => 'Incus', 'docker' => 'Docker',
                'kubernetes' => 'Kubernetes', 'vps' => 'VPS', 'hardware' => 'Hardware',
            ], default: $vsite->technology);
        }

        if (confirm('Update location? (current: '.($vsite->location ?? 'none').')', default: false)) {
            $updates['location'] = text('Location', default: $vsite->location);
        }

        if (confirm('Update description?', default: false)) {
            $updates['description'] = text('Description', default: $vsite->description);
        }

        if (confirm('Update API endpoint? (current: '.($vsite->api_endpoint ?? 'none').')', default: false)) {
            $updates['api_endpoint'] = text('API endpoint', default: $vsite->api_endpoint);
        }

        $updates['status'] = select('Status', [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'maintenance' => 'Maintenance',
        ], default: $vsite->status);

        $updates['is_active'] = confirm('Is active?', default: $vsite->is_active);

        if (empty($updates)) {
            info('No changes made.');

            return self::SUCCESS;
        }

        try {
            $vsite->update($updates);
            info("✅ VSite '{$vsite->name}' updated successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to update VSite: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function deleteVSite(): int
    {
        $vsite = $this->getVSite();
        if (! $vsite) {
            return self::FAILURE;
        }

        $vnodeCount = $vsite->vnodes()->count();

        if ($vnodeCount > 0) {
            warning("This VSite has {$vnodeCount} VNodes. Deleting will also delete all VNodes and VHosts!");
        }

        if (! confirm("Are you sure you want to delete VSite '{$vsite->name}'?", default: false)) {
            info('Deletion cancelled.');

            return self::SUCCESS;
        }

        try {
            $name = $vsite->name;
            $vsite->delete();
            info("✅ VSite '{$name}' deleted successfully!");

            return self::SUCCESS;
        } catch (\Exception $e) {
            error("Failed to delete VSite: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function getVSite(): ?FleetVSite
    {
        $id = $this->argument('id');
        if (! $id) {
            error('ID or name required for this action.');

            return null;
        }

        $vsite = FleetVSite::where('id', $id)->orWhere('name', $id)->first();
        if (! $vsite) {
            error("VSite with ID or name '{$id}' not found.");

            return null;
        }

        return $vsite;
    }

    protected function getCapabilitiesForTechnology(string $technology): array
    {
        return match ($technology) {
            'proxmox' => ['vm_management', 'container_management', 'storage_management', 'network_management', 'backup'],
            'incus' => ['container_management', 'image_management', 'network_management', 'storage_management'],
            'docker' => ['container_management', 'image_management', 'network_management'],
            'kubernetes' => ['pod_management', 'service_management', 'ingress_management', 'storage_management'],
            'vps' => ['instance_management', 'network_configuration'],
            'hardware' => ['server_management', 'network_configuration'],
            default => ['basic_management'],
        };
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:vsite {action} {id?}');
        info('');
        info('Actions:');
        info('  list              List all VSites');
        info('  show {id}         Show VSite details');
        info('  create            Create new VSite');
        info('  edit {id}         Edit existing VSite');
        info('  delete {id}       Delete VSite');
        info('');
        info('Examples:');
        info('  php artisan fleet:vsite list');
        info('  php artisan fleet:vsite show 1');
        info('  php artisan fleet:vsite create');
        info('  php artisan fleet:vsite edit goldcoast-proxmox-datacenter');
        info('  php artisan fleet:vsite delete 1');

        return self::SUCCESS;
    }
}
