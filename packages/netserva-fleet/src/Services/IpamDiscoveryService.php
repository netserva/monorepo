<?php

declare(strict_types=1);

namespace NetServa\Fleet\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\IpAddress;
use NetServa\Fleet\Models\IpNetwork;

/**
 * IPAM Discovery Service
 *
 * Discovers IP networks and addresses from:
 * - Network scanning (ping sweep for live hosts)
 * - VNodes (fleet_vnodes.ip_address)
 * - SSH Hosts (ssh_hosts.hostname when it's an IP)
 *
 * Groups IPs into networks based on common prefixes and creates
 * IpNetwork and IpAddress records.
 */
class IpamDiscoveryService
{
    protected array $stats = [
        'networks_created' => 0,
        'networks_existing' => 0,
        'addresses_created' => 0,
        'addresses_updated' => 0,
        'addresses_skipped' => 0,
        'hosts_scanned' => 0,
        'hosts_alive' => 0,
    ];

    /**
     * Discover and import all IPs from fleet infrastructure
     */
    public function discoverFromFleet(): array
    {
        $this->stats = [
            'networks_created' => 0,
            'networks_existing' => 0,
            'addresses_created' => 0,
            'addresses_updated' => 0,
            'addresses_skipped' => 0,
        ];

        // Collect all IPs from vnodes and SSH hosts
        $discoveredIps = $this->collectIpsFromFleet();

        // Group IPs by network prefix (assume /24 for IPv4)
        $networkGroups = $this->groupIpsByNetwork($discoveredIps);

        // Create/update networks and addresses
        foreach ($networkGroups as $networkCidr => $ips) {
            $this->processNetwork($networkCidr, $ips);
        }

        return $this->stats;
    }

    /**
     * Discover IPs for a specific network only
     *
     * Scans SSH hosts and VNodes for IPs that fall within the given network's CIDR
     * and creates IpAddress records for matches.
     */
    public function discoverForNetwork(IpNetwork $network): array
    {
        $this->stats = [
            'networks_created' => 0,
            'networks_existing' => 0,
            'addresses_created' => 0,
            'addresses_updated' => 0,
            'addresses_skipped' => 0,
            'hosts_scanned' => 0,
            'hosts_alive' => 0,
        ];

        // Collect all IPs from fleet
        $allIps = $this->collectIpsFromFleet();

        // Filter to only IPs within this network's CIDR
        $matchingIps = $allIps->filter(fn ($ipInfo) => $network->containsIp($ipInfo['ip']));

        // Process each matching IP
        foreach ($matchingIps as $ipInfo) {
            $this->processAddress($network, $ipInfo);
        }

        return $this->stats;
    }

    /**
     * Scan network for live hosts using ping sweep
     *
     * Performs a parallel ping sweep of the network CIDR to discover
     * live hosts. Only works for IPv4 /24 or smaller networks.
     */
    public function scanNetwork(IpNetwork $network, int $maxHosts = 254): array
    {
        $this->stats = [
            'networks_created' => 0,
            'networks_existing' => 0,
            'addresses_created' => 0,
            'addresses_updated' => 0,
            'addresses_skipped' => 0,
            'hosts_scanned' => 0,
            'hosts_alive' => 0,
        ];

        // Only support IPv4 for now
        if ($network->ip_version !== '4') {
            Log::warning("IPAM: Network scan only supports IPv4, skipping {$network->cidr}");

            return $this->stats;
        }

        // Get network range
        $range = $this->getNetworkRange($network->cidr);
        if (! $range) {
            Log::error("IPAM: Could not parse network range for {$network->cidr}");

            return $this->stats;
        }

        // Limit scan size
        $hostCount = min($range['count'], $maxHosts);
        $this->stats['hosts_scanned'] = $hostCount;

        // Perform ping sweep
        $aliveHosts = $this->pingSweep($range['start'], $hostCount);
        $this->stats['hosts_alive'] = count($aliveHosts);

        // Process discovered hosts
        foreach ($aliveHosts as $ip) {
            $this->processScannedAddress($network, $ip);
        }

        Log::info("IPAM: Scanned {$network->cidr} - {$this->stats['hosts_alive']}/{$hostCount} hosts alive");

        return $this->stats;
    }

    /**
     * Get the start IP and host count for a network
     */
    protected function getNetworkRange(string $cidr): ?array
    {
        [$networkAddress, $prefix] = explode('/', $cidr);
        $prefix = (int) $prefix;

        if ($prefix < 16) {
            // Too large to scan
            return null;
        }

        $networkLong = ip2long($networkAddress);
        $hostBits = 32 - $prefix;
        $hostCount = pow(2, $hostBits) - 2; // Exclude network and broadcast

        // Start at first host (network + 1)
        $startIp = long2ip($networkLong + 1);

        return [
            'start' => $startIp,
            'count' => (int) $hostCount,
            'network' => $networkAddress,
        ];
    }

    /**
     * Perform parallel ping sweep using fping or fallback to sequential ping
     */
    protected function pingSweep(string $startIp, int $count): array
    {
        $aliveHosts = [];

        // Try fping first (fast parallel pings)
        if ($this->commandExists('fping')) {
            $aliveHosts = $this->fpingSweep($startIp, $count);
        } else {
            // Fallback to sequential ping (slower but always available)
            $aliveHosts = $this->sequentialPing($startIp, $count);
        }

        return $aliveHosts;
    }

    /**
     * Fast parallel ping using fping
     */
    protected function fpingSweep(string $startIp, int $count): array
    {
        $startLong = ip2long($startIp);
        $endIp = long2ip($startLong + $count - 1);

        // fping with range: -a = alive only, -g = generate range, -q = quiet
        $cmd = sprintf(
            'fping -a -g %s %s -q 2>/dev/null',
            escapeshellarg($startIp),
            escapeshellarg($endIp)
        );

        $output = [];
        exec($cmd, $output);

        return array_filter($output, fn ($ip) => $this->isValidIp(trim($ip)));
    }

    /**
     * Sequential ping fallback (slower but always works)
     */
    protected function sequentialPing(string $startIp, int $count): array
    {
        $aliveHosts = [];
        $startLong = ip2long($startIp);

        // Limit to avoid very long scans
        $count = min($count, 50);

        for ($i = 0; $i < $count; $i++) {
            $ip = long2ip($startLong + $i);

            // Quick ping: -c 1 = one packet, -W 1 = 1 second timeout
            $cmd = sprintf('ping -c 1 -W 1 %s >/dev/null 2>&1', escapeshellarg($ip));
            $result = null;
            system($cmd, $result);

            if ($result === 0) {
                $aliveHosts[] = $ip;
            }
        }

        return $aliveHosts;
    }

    /**
     * Process an address discovered by network scan
     */
    protected function processScannedAddress(IpNetwork $network, string $ip): void
    {
        $existing = IpAddress::where('ip_network_id', $network->id)
            ->where('ip_address', $ip)
            ->first();

        if ($existing) {
            // Update last seen timestamp and pingability
            $existing->update([
                'last_seen_at' => now(),
                'last_ping_at' => now(),
                'is_pingable' => true,
                'ping_count' => $existing->ping_count + 1,
            ]);
            $this->stats['addresses_updated']++;
        } else {
            // Try to get hostname via reverse DNS
            $hostname = $this->getHostname($ip);

            IpAddress::create([
                'ip_network_id' => $network->id,
                'ip_address' => $ip,
                'hostname' => $hostname,
                'status' => 'discovered',
                'assignment_type' => 'unknown',
                'owner' => null,
                'service' => null,
                'allocated_at' => now(),
                'last_seen_at' => now(),
                'last_ping_at' => now(),
                'is_pingable' => true,
                'ping_count' => 1,
                'description' => 'Discovered via network scan',
            ]);
            $this->stats['addresses_created']++;
        }
    }

    /**
     * Try to get hostname via reverse DNS lookup
     */
    protected function getHostname(string $ip): ?string
    {
        $hostname = gethostbyaddr($ip);

        // gethostbyaddr returns the IP if lookup fails
        return ($hostname !== $ip) ? $hostname : null;
    }

    /**
     * Check if a command exists on the system
     */
    protected function commandExists(string $command): bool
    {
        $result = shell_exec("which {$command} 2>/dev/null");

        return ! empty(trim($result ?? ''));
    }

    /**
     * Collect all IPs from VNodes and SSH Hosts
     *
     * @return Collection<int, array{ip: string, source: string, source_id: int, hostname: string|null, name: string}>
     */
    protected function collectIpsFromFleet(): Collection
    {
        $ips = collect();

        // From VNodes
        FleetVnode::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->each(function (FleetVnode $vnode) use ($ips) {
                if ($this->isValidIp($vnode->ip_address)) {
                    $ips->push([
                        'ip' => $vnode->ip_address,
                        'source' => 'vnode',
                        'source_id' => $vnode->id,
                        'hostname' => $vnode->name,
                        'fqdn' => $vnode->fqdn,
                        'name' => $vnode->name,
                    ]);
                }
            });

        // From SSH Hosts (only if hostname is an IP)
        SshHost::all()->each(function (SshHost $host) use ($ips) {
            if ($this->isValidIp($host->hostname)) {
                // Check if this IP is already from a vnode
                $exists = $ips->contains(fn ($item) => $item['ip'] === $host->hostname);

                if (! $exists) {
                    $ips->push([
                        'ip' => $host->hostname,
                        'source' => 'ssh_host',
                        'source_id' => $host->id,
                        'hostname' => $host->host, // The alias
                        'fqdn' => null,
                        'name' => $host->host,
                    ]);
                }
            }
        });

        return $ips;
    }

    /**
     * Group IPs by their network prefix
     *
     * @return array<string, Collection> keyed by CIDR
     */
    protected function groupIpsByNetwork(Collection $ips): array
    {
        $groups = [];

        foreach ($ips as $ipInfo) {
            $ip = $ipInfo['ip'];

            if ($this->isIpv4($ip)) {
                // Group by /24 network
                $networkCidr = $this->getNetworkCidr($ip, 24);
            } else {
                // IPv6 - group by /64
                $networkCidr = $this->getIpv6NetworkCidr($ip, 64);
            }

            if (! isset($groups[$networkCidr])) {
                $groups[$networkCidr] = collect();
            }

            $groups[$networkCidr]->push($ipInfo);
        }

        return $groups;
    }

    /**
     * Process a network group - create/update network and addresses
     */
    protected function processNetwork(string $cidr, Collection $ips): void
    {
        // Parse CIDR
        [$networkAddress, $prefixLength] = explode('/', $cidr);
        $isIpv4 = $this->isIpv4($networkAddress);

        // Find or create network
        $network = IpNetwork::where('cidr', $cidr)->first();

        if (! $network) {
            // Generate a name based on the IPs in this network
            $sampleNames = $ips->take(3)->pluck('name')->filter()->implode(', ');
            $networkName = $sampleNames ?: "Network {$networkAddress}";

            // Detect network type based on IP range
            $networkType = $this->detectNetworkType($networkAddress);

            $network = IpNetwork::create([
                'name' => $networkName,
                'cidr' => $cidr,
                'network_address' => $networkAddress,
                'prefix_length' => (int) $prefixLength,
                'ip_version' => $isIpv4 ? '4' : '6',
                'network_type' => $networkType,
                'is_active' => true,
                'total_addresses' => $isIpv4 ? pow(2, 32 - (int) $prefixLength) - 2 : 0,
                'description' => "Auto-discovered from fleet ({$ips->count()} hosts)",
            ]);

            $this->stats['networks_created']++;
            Log::info("IPAM: Created network {$cidr} with {$ips->count()} addresses");
        } else {
            $this->stats['networks_existing']++;
        }

        // Create/update addresses
        foreach ($ips as $ipInfo) {
            $this->processAddress($network, $ipInfo);
        }
    }

    /**
     * Process a single IP address
     */
    protected function processAddress(IpNetwork $network, array $ipInfo): void
    {
        $existing = IpAddress::where('ip_network_id', $network->id)
            ->where('ip_address', $ipInfo['ip'])
            ->first();

        if ($existing) {
            // Update if we have better info
            $updates = [];

            if (empty($existing->hostname) && ! empty($ipInfo['hostname'])) {
                $updates['hostname'] = $ipInfo['hostname'];
            }

            if (empty($existing->fqdn) && ! empty($ipInfo['fqdn'])) {
                $updates['fqdn'] = $ipInfo['fqdn'];
            }

            // Link to vnode if from vnode source
            if ($ipInfo['source'] === 'vnode' && empty($existing->fleet_vnode_id)) {
                $updates['fleet_vnode_id'] = $ipInfo['source_id'];
            }

            // Store SSH host reference if from ssh_host
            if ($ipInfo['source'] === 'ssh_host' && empty($existing->ssh_host_reference)) {
                $updates['ssh_host_reference'] = $ipInfo['hostname'];
            }

            if (! empty($updates)) {
                $existing->update($updates);
                $this->stats['addresses_updated']++;
            } else {
                $this->stats['addresses_skipped']++;
            }
        } else {
            // Create new address
            IpAddress::create([
                'ip_network_id' => $network->id,
                'ip_address' => $ipInfo['ip'],
                'hostname' => $ipInfo['hostname'],
                'fqdn' => $ipInfo['fqdn'],
                'status' => 'allocated',
                'owner' => 'fleet',
                'service' => $ipInfo['source'] === 'vnode' ? 'VNode' : 'SSH Host',
                'fleet_vnode_id' => $ipInfo['source'] === 'vnode' ? $ipInfo['source_id'] : null,
                'ssh_host_reference' => $ipInfo['source'] === 'ssh_host' ? $ipInfo['hostname'] : null,
                'allocated_at' => now(),
                'description' => "Discovered from {$ipInfo['source']}",
            ]);

            $this->stats['addresses_created']++;
        }
    }

    /**
     * Check if string is a valid IP address
     */
    protected function isValidIp(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if IP is IPv4
     */
    protected function isIpv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Get /24 network CIDR for an IPv4 address
     */
    protected function getNetworkCidr(string $ip, int $prefix = 24): string
    {
        $long = ip2long($ip);
        $mask = -1 << (32 - $prefix);
        $network = long2ip($long & $mask);

        return "{$network}/{$prefix}";
    }

    /**
     * Get network CIDR for an IPv6 address
     */
    protected function getIpv6NetworkCidr(string $ip, int $prefix = 64): string
    {
        // Simplified - just return the IP with prefix for now
        // Full IPv6 network calculation is more complex
        $parts = explode(':', $ip);
        $networkParts = array_slice($parts, 0, 4);
        $network = implode(':', $networkParts).'::';

        return "{$network}/{$prefix}";
    }

    /**
     * Detect network type based on IP range
     */
    protected function detectNetworkType(string $ip): string
    {
        if (! $this->isIpv4($ip)) {
            return 'public'; // Assume IPv6 is public
        }

        $long = ip2long($ip);

        // RFC 1918 private ranges
        // 10.0.0.0/8
        if (($long & 0xFF000000) === 0x0A000000) {
            return 'private';
        }

        // 172.16.0.0/12
        if (($long & 0xFFF00000) === 0xAC100000) {
            return 'private';
        }

        // 192.168.0.0/16
        if (($long & 0xFFFF0000) === 0xC0A80000) {
            return 'private';
        }

        // 100.64.0.0/10 (CGNAT)
        if (($long & 0xFFC00000) === 0x64400000) {
            return 'vpn';
        }

        return 'public';
    }

    /**
     * Get discovery statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
