<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Services\FleetDiscoveryService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Interactive VHost Discovery Command
 *
 * Discovers virtual hosts/instances within a VNode
 */
class DiscoverVHostsCommand extends Command
{
    protected $signature = 'fleet:discover-vhosts
                            {--vnode= : VNode name to discover hosts for}
                            {--all : Discover for all vnodes}
                            {--manual : Manual entry instead of automatic discovery}';

    protected $description = 'Discover virtual hosts/instances within infrastructure nodes';

    public function __construct(
        protected FleetDiscoveryService $discoveryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        info('🌐 NetServa Fleet VHost Discovery');

        if ($this->option('all')) {
            return $this->discoverAllVHosts();
        }

        // Get or select VNode
        $vnodeName = $this->option('vnode');
        if (! $vnodeName) {
            $vnodes = FleetVNode::active()
                ->with('vsite')
                ->get()
                ->mapWithKeys(fn ($vnode) => [$vnode->name => "{$vnode->name} ({$vnode->vsite->name})"])
                ->toArray();

            if (empty($vnodes)) {
                error('No VNodes found. Run: php artisan fleet:discover-vnodes first');

                return self::FAILURE;
            }

            $vnodeName = select(
                label: 'Select VNode to discover hosts for',
                options: $vnodes
            );

            // Extract just the name part
            $vnodeName = explode(' ', $vnodeName)[0];
        }

        $vnode = FleetVNode::where('name', $vnodeName)->with('vsite')->first();
        if (! $vnode) {
            error("VNode '{$vnodeName}' not found!");

            return self::FAILURE;
        }

        info("Discovering VHosts for: {$vnode->name} ({$vnode->vsite->technology})");

        if ($this->option('manual')) {
            return $this->manualVHostDiscovery($vnode);
        }

        return $this->automaticVHostDiscovery($vnode);
    }

    /**
     * Discover VHosts for all VNodes
     */
    protected function discoverAllVHosts(): int
    {
        $vnodes = FleetVNode::active()->with('vsite')->get();

        if ($vnodes->isEmpty()) {
            error('No VNodes found.');

            return self::FAILURE;
        }

        info("Discovering VHosts for {$vnodes->count()} VNodes...");

        $results = ['successful' => 0, 'failed' => 0, 'errors' => []];

        progress(
            label: 'Discovering VHosts',
            steps: $vnodes,
            callback: function ($vnode, $progress) use (&$results) {
                $progress->label("Discovering: {$vnode->name}");

                try {
                    if ($this->discoveryService->discoverVNode($vnode)) {
                        $results['successful']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "{$vnode->name}: {$e->getMessage()}";
                }
            }
        );

        info("✅ Discovery complete: {$results['successful']} successful, {$results['failed']} failed");

        if (! empty($results['errors'])) {
            warning('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Automatic VHost discovery using SSH
     */
    protected function automaticVHostDiscovery(FleetVNode $vnode): int
    {
        if (! $vnode->ssh_host_id) {
            warning('No SSH host configured for this VNode.');

            if (confirm('Use manual discovery instead?', default: true)) {
                return $this->manualVHostDiscovery($vnode);
            }

            return self::FAILURE;
        }

        info('🔍 Performing automatic discovery via SSH...');

        try {
            $success = $this->discoveryService->discoverVNode($vnode);

            if ($success) {
                $vhosts = $vnode->vhosts()->get();

                info("✅ Discovery successful! Found {$vhosts->count()} VHosts:");

                if ($vhosts->isNotEmpty()) {
                    table(
                        ['Domain', 'Type', 'Status', 'Services'],
                        $vhosts->map(fn ($vh) => [
                            $vh->domain,
                            $vh->instance_type ?? 'unknown',
                            $vh->status,
                            is_array($vh->services) ? implode(', ', $vh->services) : 'none',
                        ])->toArray()
                    );
                }

                info('🔧 Next steps:');
                info('   • Review discovered VHosts in the web interface');
                info('   • Configure environment variables for each VHost');
                info('   • Set up monitoring and alerts');

                return self::SUCCESS;
            } else {
                error('Discovery failed. Check SSH connectivity and permissions.');

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            error("Discovery error: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Manual VHost discovery with interactive prompts
     */
    protected function manualVHostDiscovery(FleetVNode $vnode): int
    {
        info('📝 Manual VHost discovery');
        info("Add virtual hosts/instances for: {$vnode->name}");

        $vhosts = [];

        do {
            $vhost = $this->promptForVHost($vnode);
            if ($vhost) {
                $vhosts[] = $vhost;

                if (! confirm('Add another VHost?', default: false)) {
                    break;
                }
            }
        } while ($vhost);

        if (empty($vhosts)) {
            warning('No VHosts added.');

            return self::SUCCESS;
        }

        // Show summary
        info('VHost Summary:');
        table(
            ['Domain', 'Type', 'IP Address', 'Services'],
            array_map(fn ($vh) => [
                $vh['domain'],
                $vh['instance_type'] ?? 'vps',
                $vh['ip_addresses'][0] ?? 'Not set',
                is_array($vh['services']) ? implode(', ', $vh['services']) : 'none',
            ], $vhosts)
        );

        if (! confirm('Create these VHosts?', default: true)) {
            warning('VHost creation cancelled.');

            return self::SUCCESS;
        }

        // Create VHosts
        $created = 0;
        foreach ($vhosts as $vhostData) {
            try {
                FleetVHost::create($vhostData);
                $created++;
            } catch (\Exception $e) {
                error("Failed to create VHost {$vhostData['domain']}: {$e->getMessage()}");
            }
        }

        info("✅ Created {$created} VHosts successfully!");

        return self::SUCCESS;
    }

    /**
     * Prompt for individual VHost details
     */
    protected function promptForVHost(FleetVNode $vnode): ?array
    {
        $domain = text(
            label: 'Domain/hostname',
            placeholder: 'e.g., example.com, mail.domain.org, ns1.provider.net',
            hint: 'Primary domain or service identifier',
            required: true
        );

        // Check if already exists for this vnode
        if (FleetVHost::where('vnode_id', $vnode->id)->where('domain', $domain)->exists()) {
            error("VHost '{$domain}' already exists on this VNode!");

            return null;
        }

        $instanceType = select(
            label: 'Instance type',
            options: [
                'vm' => 'Virtual Machine',
                'ct' => 'Container (LXC)',
                'lxc' => 'LXC Container',
                'docker' => 'Docker Container',
                'vps' => 'VPS Instance',
                'hardware' => 'Physical Server',
            ],
            default: match ($vnode->vsite->technology) {
                'proxmox' => 'vm',
                'incus' => 'lxc',
                'docker' => 'docker',
                default => 'vps',
            }
        );

        $instanceId = text(
            label: 'Instance ID (optional)',
            placeholder: 'VM ID, container name, etc.',
            hint: 'Provider-specific identifier'
        );

        // Resource specifications
        $askResources = confirm(
            label: 'Specify resources (CPU, memory, disk)?',
            default: false
        );

        $cpuCores = null;
        $memoryMb = null;
        $diskGb = null;

        if ($askResources) {
            $cpuCores = (int) text(
                label: 'CPU cores',
                placeholder: '2',
                default: '2'
            );

            $memoryMb = (int) text(
                label: 'Memory (MB)',
                placeholder: '2048',
                default: '2048'
            );

            $diskGb = (int) text(
                label: 'Disk (GB)',
                placeholder: '20',
                default: '20'
            );
        }

        // IP addresses
        $ipInput = text(
            label: 'IP addresses (optional)',
            placeholder: '192.168.1.100, 10.0.0.5',
            hint: 'Comma-separated list'
        );

        $ipAddresses = $ipInput ? array_map('trim', explode(',', $ipInput)) : [];

        // Services
        $servicesInput = text(
            label: 'Services (optional)',
            placeholder: 'nginx, mysql, postfix, dovecot',
            hint: 'Comma-separated list of running services'
        );

        $services = $servicesInput ? array_map('trim', explode(',', $servicesInput)) : [];

        $description = text(
            label: 'Description (optional)',
            placeholder: 'Brief description of this VHost'
        );

        return [
            'domain' => $domain,
            'slug' => str($domain)->slug(),
            'vnode_id' => $vnode->id,
            'instance_type' => $instanceType,
            'instance_id' => $instanceId ?: null,
            'cpu_cores' => $cpuCores,
            'memory_mb' => $memoryMb,
            'disk_gb' => $diskGb,
            'ip_addresses' => $ipAddresses,
            'services' => $services,
            'environment_vars' => [], // Will be populated later
            'description' => $description ?: null,
            'status' => 'active',
            'is_active' => true,
            'last_discovered_at' => now(),
        ];
    }
}
