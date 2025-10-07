<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Services\CustomerHubAutomationService;

class ManageCustomerHubsCommand extends Command
{
    protected $signature = 'wireguard:manage-customers
                          action : Action to perform (provision|scale|status|cleanup)
                          --customer-id= : Customer ID for specific operations
                          --hub= : Hub ID or name for specific operations
                          --dry-run : Show what would be done without executing
                          --force : Force operations that would normally prompt';

    protected $description = 'Manage customer hub environments and automation';

    public function handle(CustomerHubAutomationService $automationService): int
    {
        $this->info('🏢 WireGuard Customer Hub Management');

        $action = $this->argument('action');

        return match ($action) {
            'provision' => $this->provisionCustomerEnvironment($automationService),
            'scale' => $this->scaleCustomerEnvironment($automationService),
            'status' => $this->showCustomerStatus($automationService),
            'cleanup' => $this->cleanupCustomerEnvironment($automationService),
            default => $this->showAvailableActions()
        };
    }

    private function provisionCustomerEnvironment(CustomerHubAutomationService $automationService): int
    {
        $this->info('🚀 Customer Environment Provisioning');

        $customerId = $this->option('customer-id') ?: $this->ask('📝 Customer ID');

        // Collect customer configuration
        $customerConfig = $this->collectCustomerConfiguration($customerId);

        if ($this->option('dry-run')) {
            $this->info('🔍 Dry Run - Configuration that would be used:');
            $this->displayCustomerConfig($customerConfig);

            return 0;
        }

        // Confirm provisioning
        if (! $this->option('force')) {
            $confirmed = $this->confirm('Provision customer environment for '.$customerConfig['name'].'?');
            if (! $confirmed) {
                $this->info('Cancelled by user');

                return 0;
            }
        }

        $this->info('🏗️ Starting customer environment provisioning...');

        $results = $automationService->provisionCustomerEnvironment($customerConfig);

        if ($results['success']) {
            $this->info('✅ Customer environment provisioned successfully!');
            $this->displayProvisioningResults($results);

            return 0;
        } else {
            $this->error('❌ Failed to provision customer environment');
            foreach ($results['errors'] as $error) {
                $this->error("  • $error");
            }

            return 1;
        }
    }

    private function scaleCustomerEnvironment(CustomerHubAutomationService $automationService): int
    {
        $this->info('📈 Customer Environment Scaling');

        $hub = $this->selectCustomerHub();
        if (! $hub) {
            return 1;
        }

        $this->info("🔍 Analyzing scaling needs for: $hub->name");

        $scalingResults = $automationService->autoScaleCustomerEnvironment($hub);

        if (isset($scalingResults['error'])) {
            $this->error('❌ Scaling failed: '.$scalingResults['error']);

            return 1;
        }

        $this->displayScalingResults($scalingResults);

        return 0;
    }

    private function showCustomerStatus(CustomerHubAutomationService $automationService): int
    {
        $this->info('📊 Customer Hub Status Overview');

        $customerHubs = WireguardHub::where('hub_type', 'customer')
            ->where('status', 'active')
            ->get();

        if ($customerHubs->isEmpty()) {
            $this->info('ℹ️ No customer hubs found');

            return 0;
        }

        // Overview table
        $this->displayCustomerOverview($customerHubs);

        // Detailed status for specific customer if requested
        if ($customerId = $this->option('customer-id')) {
            $customerHub = $customerHubs->where('customer_id', $customerId)->first();
            if ($customerHub) {
                $this->displayDetailedCustomerStatus($customerHub);
            } else {
                $this->warn('⚠️ No hub found for customer ID: '.$customerId);
            }
        }

        return 0;
    }

    private function cleanupCustomerEnvironment(CustomerHubAutomationService $automationService): int
    {
        $this->info('🧹 Customer Environment Cleanup');

        $hub = $this->selectCustomerHub();
        if (! $hub) {
            return 1;
        }

        if (! $this->option('force')) {
            $this->warn('⚠️ This will permanently delete customer hub '.$hub->name.' and all associated spokes!');
            $confirmed = $this->confirm('Are you sure you want to proceed?');
            if (! $confirmed) {
                $this->info('Cancelled by user');

                return 0;
            }
        }

        $this->info('🗑️ Cleaning up customer environment: '.$hub->name);

        try {
            // Delete all spokes first
            $spokeCount = $hub->spokes()->count();
            $hub->spokes()->delete();
            $this->info('  ✅ Deleted '.$spokeCount.' spokes');

            // Delete the hub
            $hub->delete();
            $this->info('  ✅ Deleted customer hub');

            $this->info('🎉 Customer environment cleanup complete!');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Cleanup failed: '.$e->getMessage());

            return 1;
        }
    }

    private function collectCustomerConfiguration(string $customerId): array
    {
        $config = [
            'customer_id' => $customerId,
            'name' => $this->ask('📝 Customer hub name', 'customer-'.$customerId),
            'description' => $this->ask('📝 Description', 'Customer hub for '.$customerId),
            'tier' => $this->choice('🏆 Customer tier', ['standard', 'premium', 'enterprise'], 'standard'),
            'bandwidth_limit' => (int) $this->ask('📊 Bandwidth limit (Mbps)', '100'),
            'max_spokes' => (int) $this->ask('🔗 Maximum spokes', '50'),
            'internet_access' => $this->confirm('🌐 Allow internet access?', true),
            'allow_management' => $this->confirm('🔧 Allow management access?', false),
            'auto_provisioning' => $this->confirm('🤖 Enable auto-provisioning?', true),
            'isolation_level' => $this->choice('🔒 Isolation level', ['strict', 'moderate', 'minimal'], 'strict'),
        ];

        // SSH host selection
        $sshHosts = SshHost::all();
        if ($sshHosts->isEmpty()) {
            $this->error('❌ No SSH hosts configured. Please configure SSH hosts first.');
            exit(1);
        }

        $choices = $sshHosts->mapWithKeys(fn ($host) => [
            $host->id => $host->hostname.' ('.$host->host.')',
        ])->toArray();

        $config['ssh_host_id'] = $this->choice('🖥️ Select SSH host for customer hub', $choices);

        // Initial spokes configuration
        if ($this->confirm('➕ Configure initial spokes?', false)) {
            $config['initial_spokes'] = $this->collectInitialSpokes();
        }

        // Advanced options
        if ($this->confirm('⚙️ Configure advanced options?', false)) {
            $config = array_merge($config, $this->collectAdvancedOptions());
        }

        return $config;
    }

    private function collectInitialSpokes(): array
    {
        $spokes = [];
        $addMore = true;

        while ($addMore && count($spokes) < 10) { // Limit to 10 for initial setup
            $this->info('🔗 Configuring spoke '.(count($spokes) + 1));

            $spoke = [
                'name' => $this->ask('📝 Spoke name'),
                'description' => $this->ask('📝 Description', 'Customer spoke'),
                'device_type' => $this->choice('📱 Device type', ['server', 'workstation', 'mobile', 'iot'], 'server'),
                'os' => $this->choice('💻 Operating system', ['linux', 'windows', 'macos', 'android', 'ios'], 'linux'),
                'bandwidth_limit' => (int) $this->ask('📊 Bandwidth limit (Mbps)', '10'),
                'internet_access' => $this->confirm('🌐 Internet access?', true),
                'auto_connect' => $this->confirm('🔄 Auto-connect?', true),
                'purpose' => $this->ask('🎯 Purpose/role', 'general'),
            ];

            // Optional SSH host for automatic deployment
            if ($this->confirm('🖥️ Configure SSH host for automatic deployment?', false)) {
                $sshHosts = SshHost::all();
                $choices = $sshHosts->mapWithKeys(fn ($host) => [
                    $host->id => $host->hostname.' ('.$host->host.')',
                ])->toArray();
                $choices['skip'] = 'Skip - manual deployment';

                $sshHostId = $this->choice('Select SSH host', $choices);
                if ($sshHostId !== 'skip') {
                    $spoke['ssh_host_id'] = $sshHostId;
                }
            }

            $spokes[] = $spoke;
            $addMore = $this->confirm('➕ Add another spoke?', false);
        }

        return $spokes;
    }

    private function collectAdvancedOptions(): array
    {
        return [
            'key_rotation_days' => (int) $this->ask('🔑 Key rotation interval (days)', '90'),
            'max_session_hours' => (int) $this->ask('⏱️ Max session duration (hours)', '24'),
            'max_concurrent' => (int) $this->ask('👥 Max concurrent connections', '10'),
            'idle_timeout' => (int) $this->ask('💤 Idle timeout (minutes)', '30'),
            'billing_integration' => $this->confirm('💰 Enable billing integration?', false),
            'contact' => $this->ask('📧 Customer contact email', null),
            'dns_servers' => explode(',', $this->ask('🌐 DNS servers (comma-separated)', '1.1.1.1,8.8.8.8')),
        ];
    }

    private function selectCustomerHub(): ?WireguardHub
    {
        if ($hubIdentifier = $this->option('hub')) {
            // Try to find by ID or name
            $hub = WireguardHub::where('hub_type', 'customer')
                ->where(function ($query) use ($hubIdentifier) {
                    $query->where('id', $hubIdentifier)
                        ->orWhere('name', $hubIdentifier);
                })
                ->first();

            if (! $hub) {
                $this->error('❌ Customer hub not found: '.$hubIdentifier);

                return null;
            }

            return $hub;
        }

        $customerHubs = WireguardHub::where('hub_type', 'customer')
            ->where('status', 'active')
            ->get();

        if ($customerHubs->isEmpty()) {
            $this->error('❌ No customer hubs found');

            return null;
        }

        $choices = $customerHubs->mapWithKeys(fn ($hub) => [
            $hub->id => $hub->name.' (Customer: '.$hub->customer_id.')',
        ])->toArray();

        $hubId = $this->choice('🏢 Select customer hub', $choices);

        return $customerHubs->find($hubId);
    }

    private function displayCustomerConfig(array $config): void
    {
        $this->table(
            ['Setting', 'Value'],
            collect($config)->map(fn ($value, $key) => [
                $key,
                is_array($value) ? json_encode($value) : (string) $value,
            ])->toArray()
        );
    }

    private function displayProvisioningResults(array $results): void
    {
        $this->info('📋 Provisioning Results:');

        if ($hub = $results['hub']) {
            $this->info('🏢 Hub: '.$hub->name.' (ID: '.$hub->id.')');
            $this->info('🌐 Network: '.$hub->network_cidr);
            $this->info('🔌 Port: '.$hub->listen_port);
        }

        if (! empty($results['spokes'])) {
            $this->info('🔗 Created '.count($results['spokes']).' spokes:');
            foreach ($results['spokes'] as $spoke) {
                $this->info('  • '.$spoke->name.' ('.$spoke->allocated_ip.')');
            }
        }

        if ($results['monitoring_setup']) {
            $this->info('📊 Monitoring: Configured');
        }
    }

    private function displayScalingResults(array $results): void
    {
        $this->info('📈 Scaling Analysis Results:');

        if (! empty($results['actions_taken'])) {
            $this->info('🔧 Actions Taken:');
            foreach ($results['actions_taken'] as $action) {
                $this->info('  ✅ '.$action);
            }
        } else {
            $this->info('ℹ️ No scaling actions needed');
        }

        if (! empty($results['recommendations'])) {
            $this->info('💡 Recommendations:');
            foreach ($results['recommendations'] as $recommendation) {
                $this->info('  • '.$recommendation);
            }
        }

        if (! empty($results['current_metrics'])) {
            $this->info('📊 Current Metrics:');
            foreach ($results['current_metrics'] as $metric => $value) {
                $this->info('  • '.$metric.': '.$value);
            }
        }
    }

    private function displayCustomerOverview($customerHubs): void
    {
        $tableData = [];

        foreach ($customerHubs as $hub) {
            $spokeCount = $hub->spokes()->count();
            $activeSpokes = $hub->spokes()->where('status', 'active')->count();
            $healthStatus = match ($hub->health_status) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'critical' => '❌',
                default => '❓'
            };

            $tableData[] = [
                $hub->customer_id,
                $hub->name,
                $hub->network_cidr,
                $activeSpokes.'/'.$spokeCount,
                $healthStatus,
                $hub->bandwidth_limit_mbps.' Mbps',
                $hub->created_at->format('Y-m-d'),
            ];
        }

        $this->table(
            ['Customer ID', 'Hub Name', 'Network', 'Spokes', 'Health', 'Bandwidth', 'Created'],
            $tableData
        );
    }

    private function displayDetailedCustomerStatus(WireguardHub $hub): void
    {
        $this->info('🏢 Detailed Status for: '.$hub->name);

        $spokes = $hub->spokes;
        $connections = $hub->connections()->where('connection_status', 'connected');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Customer ID', $hub->customer_id],
                ['Network CIDR', $hub->network_cidr],
                ['Health Status', $hub->health_status],
                ['Total Spokes', $spokes->count()],
                ['Active Spokes', $spokes->where('status', 'active')->count()],
                ['Connected Spokes', $connections->count()],
                ['Bandwidth Limit', $hub->bandwidth_limit_mbps.' Mbps'],
                ['Max Peers', $hub->max_peers],
                ['Isolation Enabled', $hub->customer_isolation ? 'Yes' : 'No'],
                ['Auto Provisioning', $hub->auto_peer_provisioning ? 'Yes' : 'No'],
                ['Last Deployed', $hub->last_deployed_at?->format('Y-m-d H:i:s') ?: 'Never'],
            ]
        );

        if ($spokes->isNotEmpty()) {
            $this->info('🔗 Spokes:');
            $spokeData = [];
            foreach ($spokes as $spoke) {
                $spokeData[] = [
                    $spoke->name,
                    $spoke->allocated_ip,
                    $spoke->status,
                    $spoke->device_type,
                    $spoke->last_seen?->format('H:i:s') ?: 'Never',
                ];
            }
            $this->table(['Name', 'IP', 'Status', 'Type', 'Last Seen'], $spokeData);
        }
    }

    private function showAvailableActions(): int
    {
        $this->error('❌ Invalid action specified');
        $this->line('');
        $this->info('Available actions:');
        $this->info('  provision  - Provision new customer environment');
        $this->info('  scale      - Analyze and scale existing customer environment');
        $this->info('  status     - Show customer hub status and metrics');
        $this->info('  cleanup    - Remove customer environment and all resources');
        $this->line('');
        $this->info('Examples:');
        $this->info('  php artisan wireguard:manage-customers provision --customer-id=CUST001');
        $this->info('  php artisan wireguard:manage-customers status');
        $this->info('  php artisan wireguard:manage-customers scale --hub=customer-hub-1');
        $this->info('  php artisan wireguard:manage-customers cleanup --customer-id=CUST001');

        return 1;
    }
}
