<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

/**
 * Fleet Discovery Service
 *
 * Handles SSH-based discovery of infrastructure nodes with graceful error handling
 */
class FleetDiscoveryService
{
    protected int $sshTimeout;

    public function __construct()
    {
        $this->sshTimeout = config('fleet.discovery.ssh_timeout', 30);
    }

    /**
     * Discover all infrastructure nodes that need scanning
     */
    public function discoverInfrastructure(): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $vnodes = FleetVNode::needsScanning()->with(['sshHost', 'vsite'])->get();

        foreach ($vnodes as $vnode) {
            $results['processed']++;

            try {
                $discovered = $this->discoverVNode($vnode);

                if ($discovered) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "VNode {$vnode->name}: {$e->getMessage()}";

                Log::warning("Fleet discovery failed for VNode {$vnode->name}", [
                    'vnode_id' => $vnode->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Discover a specific VNode
     */
    public function discoverVNode(FleetVNode $vnode): bool
    {
        if (! $vnode->hasSshAccess()) {
            $vnode->recordDiscoveryError('No SSH access configured');

            return false;
        }

        try {
            $discoveredData = match ($vnode->role) {
                'compute' => $this->discoverComputeNode($vnode),
                'network' => $this->discoverNetworkNode($vnode),
                'storage' => $this->discoverStorageNode($vnode),
                'mixed' => $this->discoverMixedNode($vnode),
                default => $this->discoverComputeNode($vnode),
            };

            if ($discoveredData) {
                $vnode->recordDiscoverySuccess($discoveredData);

                // Also discover vhosts for compute nodes
                if (in_array($vnode->role, ['compute', 'mixed'])) {
                    $this->discoverVHosts($vnode);
                }

                return true;
            }

            return false;
        } catch (Exception $e) {
            $vnode->recordDiscoveryError($e->getMessage());

            return false;
        }
    }

    /**
     * Discover compute node details
     */
    protected function discoverComputeNode(FleetVNode $vnode): ?array
    {
        $sshHost = $vnode->sshHost;
        $commands = config('fleet.discovery.discovery_commands.compute', []);

        $data = [];

        // Execute discovery commands with error handling
        foreach ($commands as $command) {
            try {
                $result = $this->executeSshCommand($sshHost, $command);

                if ($result === null) {
                    continue; // Command failed, skip
                }

                // Parse command output based on command type
                $this->parseCommandOutput($command, $result, $data);
            } catch (Exception $e) {
                // Log but continue with other commands
                Log::debug("Command failed on {$vnode->name}: {$command}", ['error' => $e->getMessage()]);
            }
        }

        // Only return data if we got some basic info
        return ! empty($data) ? $data : null;
    }

    /**
     * Discover network node details (simplified)
     */
    protected function discoverNetworkNode(FleetVNode $vnode): ?array
    {
        $sshHost = $vnode->sshHost;
        $commands = config('fleet.discovery.discovery_commands.network', []);

        $data = [];

        foreach ($commands as $command) {
            try {
                $result = $this->executeSshCommand($sshHost, $command);
                if ($result !== null) {
                    $this->parseCommandOutput($command, $result, $data);
                }
            } catch (Exception $e) {
                Log::debug("Network command failed on {$vnode->name}: {$command}");
            }
        }

        return ! empty($data) ? $data : null;
    }

    /**
     * Discover storage node details
     */
    protected function discoverStorageNode(FleetVNode $vnode): ?array
    {
        $sshHost = $vnode->sshHost;
        $commands = config('fleet.discovery.discovery_commands.storage', []);

        $data = [];

        foreach ($commands as $command) {
            try {
                $result = $this->executeSshCommand($sshHost, $command);
                if ($result !== null) {
                    $this->parseCommandOutput($command, $result, $data);
                }
            } catch (Exception $e) {
                Log::debug("Storage command failed on {$vnode->name}: {$command}");
            }
        }

        return ! empty($data) ? $data : null;
    }

    /**
     * Discover mixed node (all discovery types)
     */
    protected function discoverMixedNode(FleetVNode $vnode): ?array
    {
        $computeData = $this->discoverComputeNode($vnode) ?? [];
        $networkData = $this->discoverNetworkNode($vnode) ?? [];
        $storageData = $this->discoverStorageNode($vnode) ?? [];

        $data = array_merge($computeData, $networkData, $storageData);

        return ! empty($data) ? $data : null;
    }

    /**
     * Discover VHosts running on a compute node
     */
    protected function discoverVHosts(FleetVNode $vnode): void
    {
        // Check for container/VM instances based on the vsite technology
        $technology = $vnode->vsite->technology;

        try {
            switch ($technology) {
                case 'incus':
                    $this->discoverIncusInstances($vnode);
                    break;
                case 'proxmox':
                    $this->discoverProxmoxInstances($vnode);
                    break;
                default:
                    // For native VPS/hardware, discover from remote /srv/ directory
                    $this->discoverNativeVHosts($vnode);
                    break;
            }
        } catch (Exception $e) {
            Log::warning("VHost discovery failed for {$vnode->name}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Execute SSH command with error handling
     */
    protected function executeSshCommand($sshHost, string $command): ?string
    {
        if (! $sshHost) {
            return null;
        }

        $sshCommand = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=%d -o StrictHostKeyChecking=no %s@%s -p %d "%s" 2>/dev/null',
            $this->sshTimeout,
            $sshHost->user,
            $sshHost->hostname,
            $sshHost->port,
            $command  // Commands from config are trusted, double quotes sufficient
        );

        $result = Process::timeout($this->sshTimeout + 5)->run($sshCommand);

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Parse command output and extract relevant data
     */
    protected function parseCommandOutput(string $command, string $output, array &$data): void
    {
        if (str_contains($command, 'hostname -f')) {
            $data['operating_system'] = trim($output);
        } elseif (str_contains($command, 'uname -a')) {
            $data['kernel_version'] = trim($output);
        } elseif (str_contains($command, 'processor')) {
            $data['cpu_cores'] = $this->parseCpuCores($output);
        } elseif (str_contains($command, 'free -m')) {
            $data['memory_mb'] = $this->parseMemory($output);
        } elseif (str_contains($command, 'df -h')) {
            $data['disk_gb'] = $this->parseDisk($output);
        } elseif (str_contains($command, 'ps aux')) {
            $data['services'] = $this->parseServices($output);
        } elseif (str_contains($command, 'ip addr')) {
            $data['ip_address'] = $this->parseIpAddresses($output);
        }
    }

    /**
     * Parse CPU cores from processor count
     */
    protected function parseCpuCores(string $output): int
    {
        return (int) trim($output) ?: 1;
    }

    /**
     * Parse memory from free command output
     */
    protected function parseMemory(string $output): int
    {
        return (int) trim($output) ?: 0;
    }

    /**
     * Parse disk space from df command
     */
    protected function parseDisk(string $output): int
    {
        // Extract numeric value from size string like "20G"
        if (preg_match('/(\d+)G/', $output, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)M/', $output, $matches)) {
            return (int) ($matches[1] / 1024);
        }

        return 0;
    }

    /**
     * Parse services from ps output
     */
    protected function parseServices(string $output): array
    {
        $count = (int) trim($output);

        // For now, just store process count
        // In the future, we could parse actual service names
        return ['process_count' => $count];
    }

    /**
     * Parse IP addresses from ip addr output
     */
    protected function parseIpAddresses(string $output): ?string
    {
        // Extract first non-loopback IP
        if (preg_match('/inet (\d+\.\d+\.\d+\.\d+)\/\d+/', $output, $matches)) {
            $ip = $matches[1];
            if ($ip !== '127.0.0.1') {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Discover Incus instances
     */
    protected function discoverIncusInstances(FleetVNode $vnode): void
    {
        $output = $this->executeSshCommand($vnode->sshHost, 'incus list --format csv');

        if (! $output) {
            return;
        }

        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $instanceName = $parts[0];
                $status = $parts[1];

                $this->createOrUpdateVHost($vnode, [
                    'domain' => $instanceName,
                    'instance_type' => 'ct',
                    'status' => $status === 'RUNNING' ? 'active' : 'inactive',
                ]);
            }
        }
    }

    /**
     * Discover Proxmox instances
     */
    protected function discoverProxmoxInstances(FleetVNode $vnode): void
    {
        // Get list of VMs and containers
        $vmOutput = $this->executeSshCommand($vnode->sshHost, 'qm list');
        $ctOutput = $this->executeSshCommand($vnode->sshHost, 'pct list');

        if ($vmOutput) {
            $this->parseProxmoxOutput($vnode, $vmOutput, 'vm');
        }

        if ($ctOutput) {
            $this->parseProxmoxOutput($vnode, $ctOutput, 'ct');
        }
    }

    /**
     * Parse Proxmox command output
     */
    protected function parseProxmoxOutput(FleetVNode $vnode, string $output, string $type): void
    {
        $lines = explode("\n", trim($output));

        // Skip header line
        array_shift($lines);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 3) {
                $vmid = $parts[0];
                $name = $parts[1];
                $status = $parts[2];

                $this->createOrUpdateVHost($vnode, [
                    'domain' => $name ?: "vm-{$vmid}",
                    'instance_type' => $type,
                    'instance_id' => $vmid,
                    'status' => $status === 'running' ? 'active' : 'inactive',
                ]);
            }
        }
    }

    /**
     * Discover native VHosts from remote /srv/ directory
     */
    protected function discoverNativeVHosts(FleetVNode $vnode): void
    {
        // Query remote server for domains in /srv/
        $output = $this->executeSshCommand(
            $vnode->sshHost,
            'cd /srv && ls -1 -d */ 2>/dev/null | sed '."'s|/||'"
        );

        if (! $output) {
            return;
        }

        $domains = array_filter(explode("\n", trim($output)));

        foreach ($domains as $domain) {
            $domain = trim($domain);
            if (empty($domain)) {
                continue;
            }

            // Skip excluded patterns
            $excludePatterns = config('fleet.discovery.exclude_patterns', []);
            $skip = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $domain)) {
                    $skip = true;
                    break;
                }
            }

            if (! $skip) {
                $this->createOrUpdateVHost($vnode, [
                    'domain' => $domain,
                    'instance_type' => 'native',
                    'status' => 'active',
                ]);
            }
        }
    }

    /**
     * Create or update VHost
     */
    protected function createOrUpdateVHost(FleetVNode $vnode, array $data): void
    {
        $vhost = FleetVHost::where('vnode_id', $vnode->id)
            ->where('domain', $data['domain'])
            ->first();

        if ($vhost) {
            $vhost->update($data);
            $vhost->last_discovered_at = now();
            $vhost->save();
        } else {
            $data['vnode_id'] = $vnode->id;
            $data['last_discovered_at'] = now();
            FleetVHost::create($data);
        }
    }

    /**
     * Test SSH connection to a node
     */
    public function testSshConnection(FleetVNode $vnode): array
    {
        if (! $vnode->hasSshAccess()) {
            return [
                'success' => false,
                'error' => 'No SSH host configured',
            ];
        }

        try {
            $result = $this->executeSshCommand($vnode->sshHost, 'echo "test"');

            return [
                'success' => $result === 'test',
                'error' => $result === 'test' ? null : 'SSH command failed',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
