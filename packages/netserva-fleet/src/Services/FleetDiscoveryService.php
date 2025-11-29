<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Dns\Exceptions\DnsValidationException;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\FcrDnsValidationService;
use NetServa\Dns\Services\PowerDnsService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

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

        $vnodes = FleetVnode::needsScanning()->with(['sshHost', 'vsite'])->get();

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
     * @param  FleetVnode  $vnode  The VNode to discover
     * @param  bool  $skipVhostDiscovery  Skip automatic vhost discovery (for legacy import mode)
     * @param  bool  $forceNoDns  Emergency override - skip DNS validation
     * @param  bool  $autoDns  Automatically create DNS records if missing
     * @return bool Success status
     */
    public function discoverVNode(FleetVnode $vnode, bool $skipVhostDiscovery = false, bool $forceNoDns = false, bool $autoDns = false): bool
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

                // Discover and store database configuration (AFTER recordDiscoverySuccess to avoid being overwritten)
                $this->discoverAndStoreDatabaseConfig($vnode);

                // Also discover vhosts for compute nodes (unless skip flag is set)
                if (! $skipVhostDiscovery && in_array($vnode->role, ['compute', 'mixed'])) {
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
    protected function discoverComputeNode(FleetVnode $vnode): ?array
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
    protected function discoverNetworkNode(FleetVnode $vnode): ?array
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
    protected function discoverStorageNode(FleetVnode $vnode): ?array
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
    protected function discoverMixedNode(FleetVnode $vnode): ?array
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
    protected function discoverVHosts(FleetVnode $vnode): void
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

        // Escape double quotes and backslashes in the command
        $escapedCommand = str_replace(['\\', '"'], ['\\\\', '\\"'], $command);

        $sshCommand = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=%d -o StrictHostKeyChecking=no %s@%s -p %d "%s" 2>/dev/null',
            $this->sshTimeout,
            $sshHost->user,
            $sshHost->hostname,
            $sshHost->port,
            $escapedCommand
        );

        $result = Process::timeout($this->sshTimeout + 5)->run($sshCommand);

        return $result->successful() ? trim($result->output()) : null;
    }

    /**
     * Parse command output and extract relevant data
     */
    protected function parseCommandOutput(string $command, string $output, array &$data): void
    {
        if (str_contains($command, 'os-release')) {
            // Parse ID="openwrt" or ID=alpine format (for OSTYP compatibility)
            if (preg_match('/ID="?([a-z0-9_-]+)"?/i', trim($output), $matches)) {
                $data['operating_system'] = strtolower($matches[1]);
            }
        } elseif (str_contains($command, 'uname -a')) {
            $data['kernel_version'] = trim($output);
        } elseif (str_contains($command, 'processor')) {
            $data['cpu_cores'] = $this->parseCpuCores($output);
        } elseif (str_contains($command, 'free')) {
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
     * Input is in KB, convert to MB
     */
    protected function parseMemory(string $output): int
    {
        $kb = (int) trim($output);

        return $kb > 0 ? (int) round($kb / 1024) : 0;
    }

    /**
     * Parse disk space from df command
     */
    protected function parseDisk(string $output): int
    {
        // Extract numeric value from size string like "20G" or "119.4G"
        if (preg_match('/([\d.]+)G/', $output, $matches)) {
            return (int) round((float) $matches[1]);
        }

        if (preg_match('/([\d.]+)M/', $output, $matches)) {
            return (int) round((float) $matches[1] / 1024);
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
    protected function discoverIncusInstances(FleetVnode $vnode): void
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
    protected function discoverProxmoxInstances(FleetVnode $vnode): void
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
    protected function parseProxmoxOutput(FleetVnode $vnode, string $output, string $type): void
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
    protected function discoverNativeVHosts(FleetVnode $vnode): void
    {
        // Query remote server for domains in /srv/
        $output = $this->executeSshCommand(
            $vnode->sshHost,
            'cd /srv && /bin/ls -1 -d */ 2>/dev/null | sed '."'s|/||'"
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
                try {
                    $this->createOrUpdateVHost($vnode, [
                        'domain' => $domain,
                        'instance_type' => 'native',
                        'status' => 'active',
                    ]);
                } catch (Exception $e) {
                    // Log but continue with other vhosts (e.g., duplicate slug constraint)
                    Log::debug("Failed to create/update vhost {$domain} on {$vnode->name}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Create or update VHost
     */
    protected function createOrUpdateVHost(FleetVnode $vnode, array $data): void
    {
        $vhost = FleetVhost::where('vnode_id', $vnode->id)
            ->where('domain', $data['domain'])
            ->first();

        if ($vhost) {
            $vhost->update($data);
            $vhost->last_discovered_at = now();
            $vhost->save();
        } else {
            $data['vnode_id'] = $vnode->id;
            $data['last_discovered_at'] = now();
            FleetVhost::create($data);
        }
    }

    /**
     * Validate and store FQDN for VNode using FCrDNS with /etc/hosts fallback
     *
     * NetServa 3.0 Policy: DNS (A + PTR records) SHOULD exist for production servers
     *
     * Resolution Strategy:
     * 1. ✅ Check FCrDNS first (A + PTR records) - PREFERRED for production
     * 2. ⚠️ Fallback to /etc/hosts configuration if DNS unavailable
     * 3. ❌ Fail if neither FCrDNS nor /etc/hosts can be configured
     *
     * FCrDNS Required For:
     * - Email server deliverability (marks vnode as email_capable)
     * - SSL certificate issuance (Let's Encrypt)
     * - Production server identification
     *
     * @param  bool  $forceNoDns  Emergency override flag (use with caution)
     * @param  bool  $autoDns  Automatically create DNS records if missing
     *
     * @throws DnsValidationException if both FCrDNS and /etc/hosts fallback fail
     */
    protected function discoverAndStoreFqdn(FleetVnode $vnode, bool $forceNoDns = false, bool $autoDns = false): void
    {
        try {
            // Emergency override: Skip DNS validation entirely
            if ($forceNoDns) {
                Log::warning('DNS validation SKIPPED (emergency mode)', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                ]);
                $vnode->update(['email_capable' => false]);

                // Still configure /etc/hosts for basic hostname resolution
                $this->configureEtcHostsFallback($vnode);

                return;
            }

            // Get server IP address
            $ip = $this->getServerIp($vnode);
            if (! $ip) {
                Log::warning("Cannot determine IP address for {$vnode->name}, falling back to /etc/hosts");
                $this->configureEtcHostsFallback($vnode);

                return;
            }

            // STEP 1: Check if FCrDNS is already configured and valid
            if ($vnode->fqdn && $this->isValidFqdn($vnode->fqdn)) {
                Log::info('✅ Checking FCrDNS for existing FQDN', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                    'ip' => $ip,
                ]);

                $result = $this->dnsValidation->validate($vnode->fqdn, $ip);

                if ($result->passed()) {
                    Log::info('✅ FCrDNS validation PASSED', [
                        'vnode' => $vnode->name,
                        'fqdn' => $vnode->fqdn,
                    ]);
                    $vnode->update([
                        'email_capable' => true,
                        'fcrdns_validated_at' => now(),
                    ]);

                    return;
                }

                // FCrDNS failed - try auto-creation if requested
                Log::warning('⚠️  FCrDNS validation FAILED for existing FQDN', [
                    'vnode' => $vnode->name,
                    'fqdn' => $vnode->fqdn,
                    'errors' => $result->getErrors(),
                ]);

                if ($autoDns) {
                    Log::info('🔧 Attempting to auto-create DNS records', [
                        'vnode' => $vnode->name,
                        'fqdn' => $vnode->fqdn,
                        'ip' => $ip,
                    ]);

                    if ($this->createDnsRecords($vnode->fqdn, $ip)) {
                        if ($this->dnsValidation->waitForPropagation($vnode->fqdn, $ip, 30)) {
                            Log::info('✅ FCrDNS records created and propagated');
                            $vnode->update([
                                'email_capable' => true,
                                'fcrdns_validated_at' => now(),
                            ]);

                            return;
                        }
                    }
                    Log::warning('⚠️  Auto-DNS creation failed, falling back to /etc/hosts');
                }

                // FCrDNS failed and auto-DNS didn't work - use /etc/hosts fallback
                $this->configureEtcHostsFallback($vnode);

                return;
            }

            // STEP 2: No FQDN set - Try to discover from PTR record
            Log::info('🔍 No FQDN set, attempting PTR lookup', [
                'vnode' => $vnode->name,
                'ip' => $ip,
            ]);

            $reverseFqdn = $this->getPtrRecord($ip);
            if ($reverseFqdn && $this->isValidFqdn($reverseFqdn)) {
                // Validate FCrDNS for discovered FQDN
                $result = $this->dnsValidation->validate($reverseFqdn, $ip);

                if ($result->passed()) {
                    Log::info('✅ FCrDNS discovered via PTR and validated', [
                        'vnode' => $vnode->name,
                        'fqdn' => $reverseFqdn,
                        'ip' => $ip,
                    ]);

                    $vnode->update([
                        'fqdn' => strtolower($reverseFqdn),
                        'email_capable' => true,
                        'fcrdns_validated_at' => now(),
                    ]);

                    return;
                }

                Log::warning('⚠️  PTR record found but FCrDNS validation failed', [
                    'vnode' => $vnode->name,
                    'discovered_fqdn' => $reverseFqdn,
                    'errors' => $result->getErrors(),
                ]);
            } else {
                Log::info('ℹ️  No PTR record found for {ip}', ['ip' => $ip]);
            }

            // STEP 3: No FCrDNS available - Configure /etc/hosts fallback
            Log::info('⚠️  FCrDNS not available, configuring /etc/hosts fallback', [
                'vnode' => $vnode->name,
            ]);

            $this->configureEtcHostsFallback($vnode);

        } catch (DnsValidationException $e) {
            // DNS validation failed - use /etc/hosts fallback
            Log::warning('⚠️  DNS validation exception, falling back to /etc/hosts', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);

            $this->configureEtcHostsFallback($vnode);
        } catch (Exception $e) {
            Log::error('⚠️  FQDN validation failed with exception, falling back to /etc/hosts', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);

            // Last resort: configure /etc/hosts
            $this->configureEtcHostsFallback($vnode);
        }
    }

    /**
     * Configure /etc/hosts as fallback when FCrDNS is not available
     *
     * This ensures `hostname -f` works even without DNS records
     * Useful for:
     * - Development/staging environments
     * - Emergency vnode initialization
     * - Offline/private networks
     *
     * NOTE: Vnode will NOT be email_capable without FCrDNS
     */
    protected function configureEtcHostsFallback(FleetVnode $vnode): void
    {
        try {
            // Determine FQDN (use existing or generate from vnode name)
            $fqdn = $vnode->fqdn;
            if (! $fqdn || ! $this->isValidFqdn($fqdn)) {
                // Generate FQDN from vnode name + default domain
                $defaultDomain = config('netserva.default_domain', 'netserva.local');
                $fqdn = "{$vnode->name}.{$defaultDomain}";
            }

            $hostname = explode('.', $fqdn)[0];
            $ip = $this->getServerIp($vnode) ?? '127.0.1.1';

            Log::info('🔧 Configuring /etc/hosts for FQDN resolution', [
                'vnode' => $vnode->name,
                'fqdn' => $fqdn,
                'hostname' => $hostname,
                'ip' => $ip,
            ]);

            // Create /etc/hosts configuration script
            $script = $this->generateEtcHostsScript($fqdn, $hostname, $ip);

            // Execute on remote server
            $result = $this->executeSshCommand($vnode->sshHost, $script);

            if ($result !== null && str_contains($result, 'SUCCESS')) {
                Log::info('✅ /etc/hosts configured successfully', [
                    'vnode' => $vnode->name,
                    'fqdn' => $fqdn,
                ]);

                // Update vnode (NOT email_capable without FCrDNS)
                $vnode->update([
                    'fqdn' => strtolower($fqdn),
                    'email_capable' => false,
                ]);

                // Verify hostname -f works
                $hostnameCheck = $this->executeSshCommand($vnode->sshHost, 'hostname -f');
                if ($hostnameCheck && trim($hostnameCheck) === $fqdn) {
                    Log::info('✅ Verified: hostname -f returns correct FQDN', [
                        'vnode' => $vnode->name,
                        'hostname_f' => $hostnameCheck,
                    ]);
                } else {
                    Log::warning('⚠️  hostname -f verification failed', [
                        'vnode' => $vnode->name,
                        'expected' => $fqdn,
                        'actual' => $hostnameCheck,
                    ]);
                }
            } else {
                Log::error('❌ Failed to configure /etc/hosts', [
                    'vnode' => $vnode->name,
                    'result' => $result,
                ]);
            }
        } catch (Exception $e) {
            Log::error('❌ /etc/hosts fallback configuration failed', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate /etc/hosts configuration script
     *
     * Creates a universal script that works on Alpine, Debian, and OpenWrt
     *
     * @param  string  $fqdn  Fully qualified domain name
     * @param  string  $hostname  Short hostname
     * @param  string  $ip  Primary IP address (or 127.0.1.1 for loopback)
     * @return string Shell script to configure /etc/hosts
     */
    protected function generateEtcHostsScript(string $fqdn, string $hostname, string $ip): string
    {
        // Escape variables for safe heredoc usage
        $safeFqdn = escapeshellarg($fqdn);
        $safeHostname = escapeshellarg($hostname);
        $safeIp = escapeshellarg($ip);

        // Use 127.0.1.1 for local resolution (Debian/Ubuntu convention)
        $localIp = ($ip === '127.0.1.1' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE))
            ? '127.0.1.1'
            : $ip;

        return <<<BASH
#!/bin/sh
# NetServa 3.0: Configure /etc/hosts for FQDN resolution
# Generated: $(date)

# Backup existing /etc/hosts
cp /etc/hosts /etc/hosts.backup.\$(date +%Y%m%d_%H%M%S) 2>/dev/null || true

# Create new /etc/hosts
cat > /etc/hosts << 'HOSTS_EOF'
# IPv4
127.0.0.1       localhost.localdomain localhost
{$localIp}      {$fqdn} {$hostname}

# IPv6
::1             localhost ip6-localhost ip6-loopback
fe00::0         ip6-localnet
ff00::0         ip6-mcastprefix
ff02::1         ip6-allnodes
ff02::2         ip6-allrouters
HOSTS_EOF

# Set hostname files (works on Alpine and Debian)
echo {$safeHostname} > /etc/hostname
hostname -F /etc/hostname 2>/dev/null || hostname {$safeHostname}

# Verify configuration
if hostname -f 2>/dev/null | grep -q {$safeFqdn}; then
    echo "SUCCESS: hostname -f returns {$fqdn}"
    exit 0
else
    echo "WARNING: hostname -f verification failed"
    hostname -f 2>&1 || echo "hostname -f command failed"
    exit 1
fi
BASH;
    }

    /**
     * Get server IP address from remote server
     */
    protected function getServerIp(FleetVnode $vnode): ?string
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

        // Fallback to SSH hostname if it's an IP address
        if ($vnode->sshHost && filter_var($vnode->sshHost->hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $vnode->sshHost->hostname;
        }

        return null;
    }

    /**
     * Get PTR record for IP address using dig
     */
    protected function getPtrRecord(string $ip): ?string
    {
        // Get primary DNS server from resolvectl (192.168.1.1 for homelab)
        $dnsServer = $this->getPrimaryDnsServer();

        // Use dig for PTR lookup with explicit DNS server
        $digCommand = $dnsServer
            ? "dig +short -x {$ip} @{$dnsServer}"
            : "dig +short -x {$ip}";

        $result = Process::timeout(10)->run($digCommand);

        if ($result->successful() && ! empty(trim($result->output()))) {
            // Remove trailing dot from FQDN
            return rtrim(trim($result->output()), '.');
        }

        return null;
    }

    /**
     * Get primary DNS server from system configuration
     */
    protected function getPrimaryDnsServer(): ?string
    {
        // Try resolvectl first (systemd-resolved) - get only first DNS server
        $result = Process::timeout(5)->run('resolvectl status | grep "Current DNS Server" | awk \'{print $NF}\' | head -1');

        if ($result->successful() && ! empty(trim($result->output()))) {
            $dns = trim($result->output());
            if (filter_var($dns, FILTER_VALIDATE_IP)) {
                return $dns;
            }
        }

        // Fallback to /etc/resolv.conf
        $result = Process::timeout(5)->run('grep -m1 "^nameserver" /etc/resolv.conf | awk \'{print $2}\'');

        if ($result->successful() && ! empty(trim($result->output()))) {
            $dns = trim($result->output());
            // Skip 127.0.0.53 (systemd-resolved stub)
            if (filter_var($dns, FILTER_VALIDATE_IP) && $dns !== '127.0.0.53') {
                return $dns;
            }
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
    public function testSshConnection(FleetVnode $vnode): array
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

    /**
     * Discover and store mail database configuration
     *
     * Reads the database path from Postfix/Dovecot configuration files
     * Precedence: Postfix sqlite config > Dovecot SQL config > MySQL detection
     */
    protected function discoverAndStoreDatabaseConfig(FleetVnode $vnode): void
    {
        try {
            // Try Postfix SQLite config first
            $postfixConfig = $this->executeSshCommand(
                $vnode->sshHost,
                "grep '^dbpath' /etc/postfix/sqlite-virtual-alias-maps.cf 2>/dev/null | head -1"
            );

            if ($postfixConfig && ! empty(trim($postfixConfig))) {
                // Parse "dbpath = /path/to/db.db" format
                if (preg_match('/^dbpath\s*=\s*(.+)$/', trim($postfixConfig), $matches)) {
                    $dbPath = trim($matches[1]);
                    Log::info('Discovered mail database from Postfix config', [
                        'vnode' => $vnode->name,
                        'path' => $dbPath,
                    ]);

                    $result = $vnode->update([
                        'mail_db_path' => $dbPath,
                        'database_type' => 'sqlite',
                    ]);

                    Log::info('Updated vnode with mail database path', [
                        'vnode' => $vnode->name,
                        'result' => $result,
                        'mail_db_path' => $vnode->fresh()->mail_db_path,
                    ]);

                    return;
                }
            }

            // Try Dovecot SQL config
            $dovecotConfig = $this->executeSshCommand(
                $vnode->sshHost,
                "grep '^connect' /etc/dovecot/dovecot-sql.conf.ext 2>/dev/null | head -1"
            );

            if ($dovecotConfig && ! empty(trim($dovecotConfig))) {
                // Parse Dovecot connect string (e.g., "connect = dbname=sysadm user=root")
                if (preg_match('/dbname=(\w+)/', $dovecotConfig, $matches)) {
                    Log::info('Discovered MySQL mail database from Dovecot config', [
                        'vnode' => $vnode->name,
                        'database' => $matches[1],
                    ]);

                    $vnode->update([
                        'mail_db_path' => "mysql://{$matches[1]}",
                        'database_type' => 'mysql',
                    ]);

                    return;
                }
            }

            // Check for MySQL
            $mysqlCheck = $this->executeSshCommand(
                $vnode->sshHost,
                "test -d /var/lib/mysql && echo 'EXISTS'"
            );

            if ($mysqlCheck && trim($mysqlCheck) === 'EXISTS') {
                Log::info('Detected MySQL (database path not determined)', [
                    'vnode' => $vnode->name,
                ]);

                $vnode->update([
                    'database_type' => 'mysql',
                ]);

                return;
            }

            Log::debug('No mail database configuration detected', [
                'vnode' => $vnode->name,
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to discover mail database configuration', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
