<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use NetServa\Core\Models\SshHost;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;
use NetServa\Wg\Services\DeploymentService;

class CreateSpokeCommand extends Command
{
    protected $signature = 'wireguard:create-spoke
                          name : Name for the spoke/client
                          hub? : Hub ID or name to connect to
                          --description= : Description for the spoke
                          --ssh-host= : SSH host ID for automatic deployment
                          --dns= : Custom DNS servers (comma-separated)
                          --download : Generate config for download instead of deployment
                          --deploy : Automatically deploy after creation
                          --interactive : Interactive mode for all options';

    protected $description = 'Create and optionally deploy a WireGuard spoke/client';

    public function handle(DeploymentService $deploymentService): int
    {
        $this->info('🔧 WireGuard Spoke Creation Tool');

        // Get spoke details
        $spokeData = $this->getSpokeData();

        if (! $spokeData) {
            $this->error('❌ Spoke creation cancelled');

            return 1;
        }

        // Create the spoke
        $spoke = $this->createSpoke($spokeData);

        if (! $spoke) {
            $this->error('❌ Failed to create spoke');

            return 1;
        }

        $this->info("✅ Created spoke: $spoke->name");
        $this->displaySpokeInfo($spoke);

        // Handle deployment or download
        if ($this->option('download') || (! $spoke->ssh_host_id && ! $this->option('deploy'))) {
            return $this->generateConfigForDownload($spoke, $deploymentService);
        }

        if ($this->option('deploy') || $this->confirm('🚀 Deploy spoke configuration now?')) {
            return $this->deploySpokeConfiguration($spoke, $deploymentService);
        }

        $this->info('💡 Run `php artisan wireguard:deploy-spoke '.$spoke->id.'` to deploy later');

        return 0;
    }

    private function getSpokeData(): ?array
    {
        $data = [];

        // Get spoke name
        $data['name'] = $this->argument('name');
        if (empty($data['name'])) {
            $data['name'] = $this->ask('📝 Spoke name');
        }

        if (WireguardSpoke::where('name', $data['name'])->exists()) {
            $this->error('❌ A spoke with name '.$data['name'].' already exists');

            return null;
        }

        // Get hub
        $hub = $this->selectHub();
        if (! $hub) {
            return null;
        }
        $data['hub'] = $hub;

        // Get description
        $data['description'] = $this->option('description') ??
            ($this->option('interactive') ? $this->ask('📝 Description (optional)') : '');

        // Get SSH host for deployment
        if (! $this->option('download')) {
            $data['ssh_host'] = $this->selectSshHost();
        }

        // Get DNS servers
        $data['dns_servers'] = $this->getDnsServers();

        return $data;
    }

    private function selectHub(): ?WireguardHub
    {
        $hubIdentifier = $this->argument('hub');

        if ($hubIdentifier) {
            $hub = is_numeric($hubIdentifier)
                ? WireguardHub::find($hubIdentifier)
                : WireguardHub::where('name', $hubIdentifier)->first();

            if (! $hub) {
                $this->error("❌ Hub '$hubIdentifier' not found");

                return null;
            }

            return $hub;
        }

        // Interactive selection
        $hubs = WireguardHub::where('status', 'active')->get();

        if ($hubs->isEmpty()) {
            $this->error('❌ No active hubs available');

            return null;
        }

        if ($hubs->count() === 1) {
            $hub = $hubs->first();
            $this->info("🎯 Using hub: $hub->name ($hub->hub_type)");

            return $hub;
        }

        $choices = $hubs->mapWithKeys(fn ($hub) => [
            $hub->id => "$hub->name ($hub->hub_type) - $hub->endpoint",
        ])->toArray();

        $selectedId = $this->choice('🎯 Select hub to connect to', $choices);

        return $hubs->find($selectedId);
    }

    private function selectSshHost(): ?SshHost
    {
        $sshHostId = $this->option('ssh-host');

        if ($sshHostId) {
            $sshHost = SshHost::find($sshHostId);
            if (! $sshHost) {
                $this->warn("⚠️ SSH host ID '$sshHostId' not found");
            } else {
                return $sshHost;
            }
        }

        if (! $this->option('interactive') && ! $this->confirm('🖥️ Deploy to remote server via SSH?')) {
            return null;
        }

        $sshHosts = SshHost::all();

        if ($sshHosts->isEmpty()) {
            $this->warn('⚠️ No SSH hosts configured. Configuration will be generated for download.');

            return null;
        }

        $choices = $sshHosts->mapWithKeys(fn ($host) => [
            $host->id => "$host->hostname ($host->host) - $host->user@$host->host:$host->port",
        ])->toArray();

        $choices['none'] = 'Generate for download only';

        $selected = $this->choice('🖥️ Select SSH host for deployment', $choices, 'none');

        return $selected === 'none' ? null : $sshHosts->find($selected);
    }

    private function getDnsServers(): ?array
    {
        $dnsString = $this->option('dns');

        if (! $dnsString && $this->option('interactive')) {
            $dnsString = $this->ask('🌐 Custom DNS servers (comma-separated, optional)');
        }

        if (empty($dnsString)) {
            return null;
        }

        $dnsServers = array_map('trim', explode(',', $dnsString));

        // Validate DNS servers
        foreach ($dnsServers as $dns) {
            if (! filter_var($dns, FILTER_VALIDATE_IP)) {
                $this->warn("⚠️ Invalid DNS server: $dns");

                return null;
            }
        }

        return $dnsServers;
    }

    private function createSpoke(array $data): ?WireguardSpoke
    {
        try {
            $hub = $data['hub'];

            // Generate keys
            $keyPair = WireguardSpoke::generateKeyPair();

            // Allocate IP
            $allocatedIp = $hub->allocateIpToSpoke();
            if (! $allocatedIp) {
                $this->error("❌ No available IP addresses in hub network $hub->network_cidr");

                return null;
            }

            $spoke = WireguardSpoke::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'wireguard_hub_id' => $hub->id,
                'ssh_host_id' => $data['ssh_host']?->id,
                'allocated_ip' => $allocatedIp,
                'interface_name' => 'wg-'.strtolower(str_replace(' ', '-', $data['name'])),
                'public_key' => $keyPair['public'],
                'private_key' => encrypt($keyPair['private']),
                'dns_servers' => $data['dns_servers'] ? json_encode($data['dns_servers']) : null,
                'status' => 'active',
                'deployment_status' => 'pending',
            ]);

            return $spoke;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create spoke: $e->getMessage()");

            return null;
        }
    }

    private function displaySpokeInfo(WireguardSpoke $spoke): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $spoke->name],
                ['Hub', $spoke->hub->name.' ('.$spoke->hub->hub_type.')'],
                ['Allocated IP', $spoke->allocated_ip],
                ['Interface', $spoke->interface_name],
                ['SSH Host', $spoke->sshHost?->hostname ?? 'Not configured'],
                ['DNS Servers', $spoke->dns_servers ? implode(', ', json_decode($spoke->dns_servers)) : 'Default'],
                ['Status', $spoke->status],
                ['Created', $spoke->created_at->format('Y-m-d H:i:s')],
            ]
        );
    }

    private function generateConfigForDownload(WireguardSpoke $spoke, DeploymentService $deploymentService): int
    {
        try {
            $this->info('📄 Generating configuration for download...');

            $success = $deploymentService->deploySpoke($spoke);

            if (! $success) {
                $this->error('❌ Failed to generate configuration');

                return 1;
            }

            $configPath = $spoke->config_file_path;
            $fullPath = Storage::disk('local')->path($configPath);

            $this->info('✅ Configuration generated successfully!');
            $this->info("📁 Config file: $fullPath");

            if ($this->confirm('👀 Display configuration content?')) {
                $this->line('');
                $this->info('--- WireGuard Configuration ---');
                $this->line(Storage::disk('local')->get($configPath));
                $this->info('--- End Configuration ---');
            }

            if ($this->confirm('📋 Copy config file path to clipboard?')) {
                $this->info("📋 Config path: $fullPath");
                $this->info('💡 Use this file with your WireGuard client');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to generate configuration: $e->getMessage()");

            return 1;
        }
    }

    private function deploySpokeConfiguration(WireguardSpoke $spoke, DeploymentService $deploymentService): int
    {
        if (! $spoke->ssh_host_id) {
            $this->error('❌ No SSH host configured for deployment');

            return 1;
        }

        try {
            $this->info("🚀 Deploying spoke configuration to $spoke->sshHost->hostname...");

            $success = $deploymentService->deploySpoke($spoke);

            if (! $success) {
                $this->error('❌ Deployment failed');

                return 1;
            }

            $this->info('✅ Spoke deployed successfully!');

            // Verify deployment
            if ($this->confirm('🔍 Run deployment verification?')) {
                $this->verifyDeployment($spoke);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Deployment failed: $e->getMessage()");

            return 1;
        }
    }

    private function verifyDeployment(WireguardSpoke $spoke): void
    {
        try {
            $this->info('🔍 Verifying deployment...');

            // Basic connectivity test
            $sshService = app(\NetServa\Core\Services\SshConnectionService::class);
            $connection = $sshService->getConnection($spoke->sshHost);

            // Check if interface exists
            $interfaceCheck = $sshService->executeCommand(
                $connection,
                "ip link show $spoke->interface_name 2>/dev/null && echo 'EXISTS' || echo 'NOT_FOUND'"
            );

            if (str_contains($interfaceCheck, 'EXISTS')) {
                $this->info("✅ Interface $spoke->interface_name is configured");

                // Check if interface is up
                $statusCheck = $sshService->executeCommand(
                    $connection,
                    "ip link show $spoke->interface_name | grep -q 'state UP' && echo 'UP' || echo 'DOWN'"
                );

                if (str_contains($statusCheck, 'UP')) {
                    $this->info("✅ Interface $spoke->interface_name is up");
                } else {
                    $this->warn("⚠️ Interface $spoke->interface_name is down");
                }

                // Check WireGuard status
                $wgStatus = $sshService->executeCommand(
                    $connection,
                    "wg show $spoke->interface_name 2>/dev/null || echo 'WG_ERROR'"
                );

                if (! str_contains($wgStatus, 'WG_ERROR')) {
                    $this->info('✅ WireGuard is running');

                    if ($this->option('verbose')) {
                        $this->line('WireGuard Status:');
                        $this->line($wgStatus);
                    }
                } else {
                    $this->warn('⚠️ WireGuard status could not be determined');
                }

            } else {
                $this->error("❌ Interface $spoke->interface_name not found");
            }

        } catch (\Exception $e) {
            $this->error("❌ Verification failed: $e->getMessage()");
        }
    }
}
