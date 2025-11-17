<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\FleetDiscoveryService;
use NetServa\SSH\Models\SshHost;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Interactive VNode Discovery Command
 *
 * Discovers infrastructure nodes within a VSite
 */
class DiscoverVNodesCommand extends Command
{
    protected $signature = 'fleet:discover-vnodes
                            {--vsite= : VSite name to discover nodes for}
                            {--manual : Manual node entry instead of automatic discovery}';

    protected $description = 'Discover infrastructure nodes (servers/hosts) within a VSite';

    public function __construct(
        protected FleetDiscoveryService $discoveryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        info('🖥️  NetServa Fleet VNode Discovery');

        // Get or select VSite
        $vsiteName = $this->option('vsite');
        if (! $vsiteName) {
            $vsites = FleetVsite::active()->pluck('name', 'name')->toArray();

            if (empty($vsites)) {
                error('No VSites found. Run: php artisan fleet:discover-vsites first');

                return self::FAILURE;
            }

            $vsiteName = select(
                label: 'Select VSite to discover nodes for',
                options: $vsites
            );
        }

        $vsite = FleetVsite::where('name', $vsiteName)->first();
        if (! $vsite) {
            error("VSite '{$vsiteName}' not found!");

            return self::FAILURE;
        }

        info("Discovering nodes for VSite: {$vsite->name} ({$vsite->technology})");

        if ($this->option('manual')) {
            return $this->manualNodeDiscovery($vsite);
        }

        return match ($vsite->technology) {
            'proxmox' => $this->discoverProxmoxNodes($vsite),
            'incus' => $this->discoverIncusNodes($vsite),
            default => $this->manualNodeDiscovery($vsite),
        };
    }

    /**
     * Discover Proxmox nodes
     */
    protected function discoverProxmoxNodes(FleetVsite $vsite): int
    {
        info('🔍 Discovering Proxmox nodes...');

        if (! $vsite->api_endpoint) {
            warning('No API endpoint configured. Switching to manual discovery.');

            return $this->manualNodeDiscovery($vsite);
        }

        // For now, manual discovery - API integration can be added later
        info('API-based discovery coming soon. Using manual discovery for now.');

        return $this->manualNodeDiscovery($vsite);
    }

    /**
     * Discover Incus nodes
     */
    protected function discoverIncusNodes(FleetVsite $vsite): int
    {
        info('🔍 Discovering Incus nodes...');

        // Incus typically runs on a single host, so we'll ask for it
        return $this->manualNodeDiscovery($vsite);
    }

    /**
     * Manual node discovery with interactive prompts
     */
    protected function manualNodeDiscovery(FleetVsite $vsite): int
    {
        info('📝 Manual node discovery');
        info('Add infrastructure nodes one by one');

        $nodes = [];

        do {
            $node = $this->promptForNode($vsite);
            if ($node) {
                $nodes[] = $node;

                if (! confirm('Add another node?', default: false)) {
                    break;
                }
            }
        } while ($node);

        if (empty($nodes)) {
            warning('No nodes added.');

            return self::SUCCESS;
        }

        // Show summary
        info('Node Summary:');
        table(
            ['Name', 'Role', 'IP Address', 'SSH Host'],
            array_map(fn ($node) => [
                $node['name'],
                $node['role'],
                $node['ip_address'] ?? 'Not set',
                $node['ssh_host'] ?? 'Not linked',
            ], $nodes)
        );

        if (! confirm('Create these nodes?', default: true)) {
            warning('Node creation cancelled.');

            return self::SUCCESS;
        }

        // Create nodes
        $created = 0;
        foreach ($nodes as $nodeData) {
            try {
                FleetVnode::create($nodeData);
                $created++;
            } catch (\Exception $e) {
                error("Failed to create node {$nodeData['name']}: {$e->getMessage()}");
            }
        }

        info("✅ Created {$created} nodes successfully!");

        if ($created > 0) {
            info('🔧 Next steps:');
            info('   1. Configure SSH access for each node');
            info('   2. Run: php artisan fleet:discover-vhosts --vnode=<node-name>');
            info('   3. Test discovery with: php artisan addfleet <vnode>');
        }

        return self::SUCCESS;
    }

    /**
     * Prompt for individual node details
     */
    protected function promptForNode(FleetVsite $vsite): ?array
    {
        $name = text(
            label: 'Node name',
            placeholder: 'e.g., ns1gc, haproxy, mysql-01',
            hint: 'Short, descriptive identifier',
            required: true
        );

        // Check if already exists
        if (FleetVnode::where('name', $name)->exists()) {
            error("Node '{$name}' already exists!");

            return null;
        }

        $role = select(
            label: 'Node role',
            options: [
                'compute' => 'Compute (runs VMs/containers)',
                'network' => 'Network (routers, load balancers)',
                'storage' => 'Storage (NAS, backup)',
                'mixed' => 'Mixed (multiple roles)',
            ],
            default: 'compute'
        );

        $environment = select(
            label: 'Environment',
            options: [
                'production' => 'Production',
                'staging' => 'Staging',
                'development' => 'Development',
            ],
            default: 'production'
        );

        $ipAddress = text(
            label: 'IP address (optional)',
            placeholder: '192.168.1.100'
        );

        $description = text(
            label: 'Description (optional)',
            placeholder: 'Brief description of this node'
        );

        // SSH host linking
        $linkSsh = confirm(
            label: 'Link to existing SSH host?',
            default: true,
            hint: 'Required for automatic discovery'
        );

        $sshHostId = null;
        if ($linkSsh) {
            $sshHosts = SshHost::pluck('hostname', 'id')->toArray();

            if (empty($sshHosts)) {
                warning('No SSH hosts found. You can link one later.');
            } else {
                $sshHostId = select(
                    label: 'Select SSH host',
                    options: ['none' => 'None'] + $sshHosts,
                    default: 'none'
                );

                $sshHostId = $sshHostId === 'none' ? null : $sshHostId;
            }
        }

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'vsite_id' => $vsite->id,
            'ssh_host_id' => $sshHostId,
            'role' => $role,
            'environment' => $environment,
            'ip_address' => $ipAddress ?: null,
            'description' => $description ?: null,
            'discovery_method' => 'manual',
            'status' => 'active',
            'is_active' => true,
        ];
    }
}
