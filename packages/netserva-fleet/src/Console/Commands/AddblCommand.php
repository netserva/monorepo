<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * Add BinaryLane Server Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new BinaryLane VPS server and optionally registers it as a VNode
 */
class AddblCommand extends Command
{
    protected $signature = 'addbl
                            {name : Server hostname (e.g., ns4.spiderweb.com.au)}
                            {size : VPS size slug (e.g., std-min, std-2vcpu)}
                            {image : OS image slug (e.g., debian-13, ubuntu-24-04)}
                            {region : Region slug (e.g., syd, mel, bne)}
                            {--ssh-key=* : SSH key IDs to add (required, use bl:keys to list)}
                            {--ipv6 : Enable IPv6}
                            {--vpc= : VPC ID (optional, use bl:vpcs to list)}
                            {--user-data= : Cloud-init user data file path}
                            {--backups : Enable daily backups}
                            {--vsite= : VSite to associate VNode with}
                            {--no-sync : Do not create VNode/SSH host}
                            {--wait : Wait for server to become active}
                            {--dry-run : Show what would be created}';

    protected $description = 'Add a new BinaryLane server (NetServa 3.0 CRUD: Create)';

    public function handle(BinaryLaneService $service): int
    {
        $name = $this->argument('name');
        $size = $this->argument('size');
        $image = $this->argument('image');
        $region = $this->argument('region');
        $sshKeys = $this->option('ssh-key');
        $ipv6 = $this->option('ipv6');
        $vpcId = $this->option('vpc');
        $userDataFile = $this->option('user-data');
        $backups = $this->option('backups');
        $vsiteName = $this->option('vsite');
        $noSync = $this->option('no-sync');
        $wait = $this->option('wait');
        $dryRun = $this->option('dry-run');

        // Validate SSH keys are provided
        if (empty($sshKeys)) {
            $this->error('At least one --ssh-key is required.');
            $this->info('Use bl:keys to list available SSH keys.');
            $this->newLine();
            $this->line('Example: addbl myserver std-min debian-13 syd --ssh-key=12345');

            return Command::FAILURE;
        }

        // Get token
        $token = $this->resolveToken($vsiteName);
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);

        // Read user-data if provided
        $userData = null;
        if ($userDataFile) {
            if (! file_exists($userDataFile)) {
                $this->error("User data file not found: {$userDataFile}");

                return Command::FAILURE;
            }
            $userData = file_get_contents($userDataFile);
        }

        // Build server data
        $serverData = [
            'name' => $name,
            'size' => $size,
            'image' => $image,
            'region' => $region,
            'ssh_keys' => array_map('intval', $sshKeys),
            'ipv6' => $ipv6,
            'vpc_id' => $vpcId ? (int) $vpcId : null,
            'user_data' => $userData,
            'backups' => $backups,
        ];

        $this->info("Creating BinaryLane server: {$name}");
        $this->line("  Size: {$size}");
        $this->line("  Image: {$image}");
        $this->line("  Region: {$region}");
        $this->line('  SSH Keys: '.implode(', ', $sshKeys));
        if ($ipv6) {
            $this->line('  IPv6: Enabled');
        }
        if ($vpcId) {
            $this->line("  VPC: {$vpcId}");
        }
        if ($backups) {
            $this->line('  Backups: Enabled');
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN - No server will be created');
            $this->newLine();
            $this->line('Would perform:');
            $this->line('  1. POST /v2/servers with above configuration');
            if (! $noSync) {
                $this->line('  2. Create SshHost record with discovered IP');
                $this->line('  3. Create FleetVnode record linked to VSite');
            }

            return Command::SUCCESS;
        }

        $this->newLine();

        try {
            $result = $service->createServer($serverData);
            $server = $result['server'];
            $actionId = $result['action']['id'] ?? null;

            $this->info('Server created successfully!');
            $this->line("  ID: {$server['id']}");
            $this->line("  Status: {$server['status']}");

            // Wait for server to be active if requested
            if ($wait && $actionId) {
                $this->newLine();
                $this->info('Waiting for server to become active...');

                $maxAttempts = 60; // 5 minutes
                $attempt = 0;

                while ($attempt < $maxAttempts) {
                    sleep(5);
                    $attempt++;

                    $server = $service->getServer($server['id']);

                    if ($server['status'] === 'active' && $server['ipv4']) {
                        $this->info('Server is now active!');
                        break;
                    }

                    $this->line("  Status: {$server['status']} (attempt {$attempt}/{$maxAttempts})");
                }

                if ($server['status'] !== 'active') {
                    $this->warn('Server did not become active within timeout. Check BinaryLane panel.');
                }
            }

            // Refresh server data to get IP
            if (! $wait) {
                sleep(3); // Brief pause to allow IP assignment
                $server = $service->getServer($server['id']);
            }

            $this->newLine();
            $this->info('Server Details:');
            $this->line('  IPv4: '.($server['ipv4'] ?? 'Pending...'));
            if ($server['ipv6']) {
                $this->line("  IPv6: {$server['ipv6']}");
            }

            // Create VNode and SSH host if not disabled
            if (! $noSync && $server['ipv4']) {
                $this->newLine();
                $this->createVNodeAndSshHost($server, $vsiteName);
            } elseif (! $noSync && ! $server['ipv4']) {
                $this->newLine();
                $this->warn('Server IP not yet assigned. Run bl:sync later to create VNode.');
            }

            $this->newLine();
            $this->info('Next steps:');
            if (! $server['ipv4']) {
                $this->line("  1. Wait for IP: shbl {$server['id']}");
                $this->line('  2. Sync to VNode: bl:sync');
            } else {
                $this->line("  1. SSH to server: ssh root@{$server['ipv4']}");
                $this->line("  2. View details: shbl {$server['id']}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create server: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function createVNodeAndSshHost(array $server, ?string $vsiteName): void
    {
        $this->info('Creating VNode and SSH host...');

        // Find or create VSite
        $vsite = null;
        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)->first();
        }

        if (! $vsite) {
            // Try to find default BinaryLane VSite
            $vsite = FleetVsite::where('provider', 'binarylane')->first();
        }

        if (! $vsite) {
            $this->warn('No BinaryLane VSite found. Creating default VSite...');
            $vsite = FleetVsite::create([
                'name' => 'BinaryLane Sydney',
                'slug' => 'binarylane-sydney',
                'provider' => 'binarylane',
                'technology' => 'vps',
                'location' => 'sydney',
                'api_endpoint' => 'https://api.binarylane.com.au/v2',
                'capabilities' => ['compute', 'networking', 'api_managed'],
            ]);
            $this->info("  Created VSite: {$vsite->name}");
        }

        // Create SSH Host
        $sshHostName = str_replace('.', '-', $server['name']);
        $existingSshHost = SshHost::where('host', $sshHostName)->first();

        if ($existingSshHost) {
            $this->line("  SSH Host already exists: {$sshHostName}");
            $sshHost = $existingSshHost;
        } else {
            $sshHost = SshHost::create([
                'host' => $sshHostName,
                'hostname' => $server['ipv4'],
                'user' => config('fleet.binarylane.default_ssh_user', 'root'),
                'port' => config('fleet.binarylane.default_ssh_port', 22),
                'is_active' => true,
            ]);
            $this->info("  Created SSH Host: {$sshHost->host} -> {$sshHost->hostname}");
        }

        // Create VNode
        $vnodeName = explode('.', $server['name'])[0]; // Use first part as vnode name
        $existingVnode = FleetVnode::where('name', $vnodeName)->first();

        if ($existingVnode) {
            // Update existing VNode
            $existingVnode->update([
                'ip_address' => $server['ipv4'],
                'bl_server_id' => $server['id'],
                'bl_size_slug' => $server['size_slug'],
                'bl_region' => $server['region_slug'],
                'bl_image' => $server['image_slug'],
                'bl_synced_at' => now(),
                'memory_mb' => $server['memory_mb'],
                'cpu_cores' => $server['vcpus'],
                'disk_gb' => $server['disk_gb'],
                'status' => 'active',
                'last_discovered_at' => now(),
            ]);
            $this->info("  Updated VNode: {$existingVnode->name}");
        } else {
            $vnode = FleetVnode::create([
                'name' => $vnodeName,
                'slug' => str($vnodeName)->slug(),
                'fqdn' => $server['name'],
                'vsite_id' => $vsite->id,
                'ssh_host_id' => $sshHost->id,
                'ip_address' => $server['ipv4'],
                'role' => 'compute',
                'environment' => 'production',
                'discovery_method' => 'api',
                'bl_server_id' => $server['id'],
                'bl_size_slug' => $server['size_slug'],
                'bl_region' => $server['region_slug'],
                'bl_image' => $server['image_slug'],
                'bl_synced_at' => now(),
                'memory_mb' => $server['memory_mb'],
                'cpu_cores' => $server['vcpus'],
                'disk_gb' => $server['disk_gb'],
                'status' => 'active',
                'is_active' => true,
                'last_discovered_at' => now(),
            ]);
            $this->info("  Created VNode: {$vnode->name} in VSite {$vsite->name}");
        }
    }

    protected function resolveToken(?string $vsiteName): ?string
    {
        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)
                ->where('provider', 'binarylane')
                ->first();

            if ($vsite) {
                $credentials = $vsite->api_credentials;

                return $credentials['token'] ?? null;
            }
        }

        $token = config('fleet.binarylane.api_token');
        if ($token) {
            return $token;
        }

        $this->error('No BinaryLane API token configured.');
        $this->info('Set BINARYLANE_API_TOKEN in .env or use --vsite option');

        return null;
    }
}
