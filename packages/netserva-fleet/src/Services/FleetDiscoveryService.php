<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Dns\Exceptions\DnsValidationException;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\FcrDnsValidationService;
use NetServa\Dns\Services\PowerDnsService;
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

    protected NetServaConfigurationService $configService;

    protected FcrDnsValidationService $dnsValidation;

    protected PowerDnsService $powerDnsService;

    public function __construct(
        NetServaConfigurationService $configService,
        FcrDnsValidationService $dnsValidation,
        PowerDnsService $powerDnsService
    ) {
        $this->sshTimeout = config('fleet.discovery.ssh_timeout', 30);
        $this->configService = $configService;
        $this->dnsValidation = $dnsValidation;
        $this->powerDnsService = $powerDnsService;
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
     *
     * @param FleetVNode $vnode The VNode to discover
     * @param bool $skipVhostDiscovery Skip automatic vhost discovery (for legacy import mode)
     * @param bool $forceNoDns Emergency override - skip DNS validation
     * @param bool $autoDns Automatically create DNS records if missing
     * @return bool Success status
     */
    public function discoverVNode(FleetVNode $vnode, bool $skipVhostDiscovery = false, bool $forceNoDns = false, bool $autoDns = false): bool
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
                // Discover and store FQDN during discovery
                $this->discoverAndStoreFqdn($vnode, $forceNoDns, $autoDns);

                $vnode->recordDiscoverySuccess($discoveredData);

                // Also discover vhosts for compute nodes (unless skip flag is set)
                if (!$skipVhostDiscovery && in_array($vnode->role, ['compute', 'mixed'])) {
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
     * Validate and store FQDN for VNode using FCrDNS
     *
     * NetServa 3.0 Policy: DNS (A + PTR records) MUST exist before vnode initialization
     * This is required for:
     * - Email server deliverability (FCrDNS)
     * - SSL certificate issuance (Let's Encrypt)
     * - Production server identification
     *
     * Will NOT overwrite manually-set FQDNs (if already validated)
     *
     * @param FleetVNode $vnode
     * @param bool $forceNoDns Emergency override flag (use with caution)
     * @param bool $autoDns Automatically create DNS records if missing
     * @throws DnsValidationException if FCrDNS validation fails
     */
    protected function discoverAndStoreFqdn(FleetVNode $vnode, bool $forceNoDns = false, bool $autoDns = false): void
    {
        try {
            // Emergency override: Skip DNS validation (logs warning)
            if ($forceNoDns) {
                Log::warning('DNS validation SKIPPED (emergency mode)', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                ]);
                $vnode->update(['email_capable' => false]);
                return;
            }

            // Get server IP address
            $ip = $this->getServerIp($vnode);
            if (!$ip) {
                throw new DnsValidationException("Cannot determine IP address for {$vnode->name}");
            }

            // If FQDN already set, validate it with FCrDNS
            if ($vnode->fqdn && $this->isValidFqdn($vnode->fqdn)) {
                Log::info('Validating existing FQDN with FCrDNS', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                    'ip' => $ip,
                ]);

                $result = $this->dnsValidation->validate($vnode->fqdn, $ip);

                if ($result->passed()) {
                    Log::info('FCrDNS validation PASSED for existing FQDN', [
                        'vnode' => $vnode->name,
                        'fqdn' => $vnode->fqdn,
                    ]);
                    $vnode->update(['email_capable' => true]);
                    return;
                }

                // Existing FQDN failed validation
                Log::error('Existing FQDN failed FCrDNS validation', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                    'errors' => $result->getErrors(),
                ]);

                // Auto-create DNS records if requested
                if ($autoDns) {
                    Log::info('Attempting to auto-create DNS records', [
                        'vnode' => $vnode->name,
                        'fqdn' => $vnode->fqdn,
                        'ip' => $ip,
                    ]);

                    if ($this->createDnsRecords($vnode->fqdn, $ip)) {
                        // Wait for propagation and re-validate
                        if ($this->dnsValidation->waitForPropagation($vnode->fqdn, $ip, 30)) {
                            $vnode->update([
                                'email_capable' => true,
                                'fcrdns_validated_at' => now(),
                            ]);
                            return;
                        }
                    }
                }

                throw DnsValidationException::fromValidationResult($result);
            }

            // No FQDN set: Try to detect from PTR record
            Log::info('No FQDN set, attempting PTR lookup', [
                'vnode' => $vnode->name,
                'ip' => $ip,
            ]);

            $reverseFqdn = gethostbyaddr($ip);
            if ($reverseFqdn === $ip || !$this->isValidFqdn($reverseFqdn)) {
                throw new DnsValidationException(
                    "No PTR record found for {$ip}. Please set up DNS before adding vnode."
                );
            }

            // Validate FCrDNS for discovered FQDN
            $result = $this->dnsValidation->validate($reverseFqdn, $ip);

            if (!$result->passed()) {
                Log::error('FCrDNS validation failed for discovered FQDN', [
                    'vnode' => $vnode->name,
                    'discovered_fqdn' => $reverseFqdn,
                    'errors' => $result->getErrors(),
                ]);

                throw DnsValidationException::fromValidationResult($result);
            }

            // FCrDNS passed - store FQDN
            $vnode->update([
                'fqdn' => strtolower($reverseFqdn),
                'email_capable' => true,
            ]);

            Log::info('FQDN discovered via FCrDNS and stored', [
                'vnode' => $vnode->name,
                'fqdn' => $reverseFqdn,
                'ip' => $ip,
            ]);

        } catch (DnsValidationException $e) {
            // Re-throw DNS validation exceptions
            throw $e;
        } catch (Exception $e) {
            Log::error('FQDN validation failed with exception', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);

            throw new DnsValidationException(
                "FQDN validation failed for {$vnode->name}: {$e->getMessage()}",
                null,
                0,
                $e
            );
        }
    }

    /**
     * Get server IP address from remote server
     */
    protected function getServerIp(FleetVNode $vnode): ?string
    {
        // Try to get from database first
        if ($vnode->ip_address) {
            return $vnode->ip_address;
        }

        // Query remote server for primary IPv4
        $ip = $this->executeSshCommand(
            $vnode->sshHost,
            "ip -4 route get 1.1.1.1 | awk '/src/ {print \$7}'"
        );

        if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return trim($ip);
        }

        return null;
    }

    /**
     * Validate if hostname is a proper FQDN
     */
    protected function isValidFqdn(?string $hostname): bool
    {
        if (empty($hostname) || ! str_contains($hostname, '.')) {
            return false;
        }

        return (bool) preg_match(
            '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i',
            $hostname
        );
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

    /**
     * Auto-create DNS records (A + PTR) for FCrDNS
     *
     * @param  string  $fqdn  Fully qualified domain name
     * @param  string  $ip  IPv4 address
     * @return bool Success status
     */
    protected function createDnsRecords(string $fqdn, string $ip): bool
    {
        try {
            // Get primary PowerDNS provider
            $provider = DnsProvider::where('type', 'powerdns')
                ->where('active', true)
                ->first();

            if (! $provider) {
                Log::error('No active PowerDNS provider found for auto-DNS');

                return false;
            }

            Log::info('Creating FCrDNS records via PowerDNS', [
                'provider' => $provider->name,
                'fqdn' => $fqdn,
                'ip' => $ip,
            ]);

            $result = $this->powerDnsService->createFCrDNSRecords($provider, $fqdn, $ip);

            if ($result['success']) {
                Log::info('FCrDNS records created successfully', [
                    'fqdn' => $fqdn,
                    'ip' => $ip,
                ]);

                return true;
            }

            Log::error('Failed to create FCrDNS records', [
                'fqdn' => $fqdn,
                'ip' => $ip,
                'error' => $result['message'],
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception while creating DNS records', [
                'fqdn' => $fqdn,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
