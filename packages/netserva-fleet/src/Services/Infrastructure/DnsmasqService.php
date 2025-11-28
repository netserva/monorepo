<?php

namespace NetServa\Fleet\Services\Infrastructure;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetDnsmasqHost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Dnsmasq Infrastructure Service
 *
 * Manages dnsmasq configuration on OpenWrt router/gateway vnodes via SSH.
 * This service handles local LAN DNS/DHCP infrastructure, NOT public DNS zones.
 *
 * Architecture:
 * - Sync FROM remote: Parse dnsmasq/UCI config → return structured data
 * - Sync TO remote: Generate config from data → deploy via SSH
 * - VNode-based: Operates on FleetVnode (typically 'gw' router)
 */
class DnsmasqService
{
    public function __construct(
        private RemoteExecutionService $remoteExecution
    ) {}

    /**
     * Sync FROM remote: Read current dnsmasq configuration
     *
     * @param  FleetVnode  $vnode  The router/gateway vnode running dnsmasq
     * @return array ['uci_hosts' => array, 'config_hosts' => array, 'stats' => array]
     */
    public function syncFromRemote(FleetVnode $vnode): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        // Read UCI DHCP configuration
        $uciHosts = $this->parseUciDhcpHosts($sshHost);

        // Read UCI address list (AAAA records)
        $uciAddresses = $this->parseUciAddressList($sshHost);

        // Read dnsmasq config files
        $configHosts = $this->parseDnsmasqConfFiles($sshHost);

        // Merge all sources and group by zone
        $allHosts = array_merge($uciHosts, $uciAddresses, $configHosts);
        $hostsByZone = $this->groupHostsByZone($allHosts);

        return [
            'uci_hosts' => $uciHosts,
            'config_hosts' => $configHosts,
            'all_hosts' => $allHosts,
            'zones' => $hostsByZone,
            'stats' => [
                'uci_count' => count($uciHosts),
                'config_count' => count($configHosts),
                'total_count' => count($allHosts),
                'zone_count' => count($hostsByZone),
            ],
        ];
    }

    /**
     * Sync TO remote: Deploy dnsmasq configuration
     *
     * @param  FleetVnode  $vnode  The router/gateway vnode
     * @param  array  $hosts  Array of host records to deploy
     * @return array Deployment result
     */
    public function syncToRemote(FleetVnode $vnode, array $hosts): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        // Generate config from hosts data
        $config = $this->generateDnsmasqConfig($hosts);

        // Deploy to remote
        $result = $this->deployConfig($sshHost, $config);

        return $result;
    }

    /**
     * Parse UCI DHCP static host entries
     */
    private function parseUciDhcpHosts(string $sshHost): array
    {
        $script = <<<'BASH'
#!/bin/sh
# Output format: hostname|ip|mac|dns_enabled
# Use simple grep/sed instead of complex awk for BusyBox compatibility

uci show dhcp 2>/dev/null | grep "^dhcp.@host\[" | sed -e "s/dhcp.@host\[\([0-9]*\)\]\./\1|/" -e "s/=/|/" | \
while IFS='|' read idx field value; do
    # Remove quotes from value
    value=$(echo "$value" | tr -d "'")

    # Store in temp file indexed by host number
    case "$field" in
        name) echo "name=$value" >> /tmp/uci_host_$idx ;;
        ip)   echo "ip=$value" >> /tmp/uci_host_$idx ;;
        mac)  echo "mac=$value" >> /tmp/uci_host_$idx ;;
        dns)  echo "dns=$value" >> /tmp/uci_host_$idx ;;
    esac
done

# Output collected data
for f in /tmp/uci_host_*; do
    [ -f "$f" ] || continue

    name=""; ip=""; mac=""; dns="0"

    while IFS='=' read key val; do
        case "$key" in
            name) name="$val" ;;
            ip)   ip="$val" ;;
            mac)  mac="$val" ;;
            dns)  dns="$val" ;;
        esac
    done < "$f"

    echo "$name|$ip|$mac|$dns"
    rm -f "$f"
done

exit 0
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        if (! $result['success']) {
            throw new \RuntimeException('Failed to read UCI DHCP config: '.$result['error']);
        }

        return $this->parseHostOutput($result['output']);
    }

    /**
     * Parse dnsmasq configuration files
     */
    private function parseDnsmasqConfFiles(string $sshHost): array
    {
        $script = <<<'BASH'
#!/bin/sh
# Parse host-record and address directives from dnsmasq config files
# Output format: hostname|ip|type
# Excludes backup files (*.bak, *.old, *.orig, *~)

# Parse host-record entries (host-record=hostname,ip1,ip2)
if [ -d /etc/dnsmasq.d ]; then
    find /etc/dnsmasq.d -type f -name "*.conf" ! -name "*.bak" ! -name "*.old" ! -name "*.orig" ! -name "*~" -exec grep -h "^host-record=" {} \; 2>/dev/null | sed 's/.*host-record=//' | while IFS=',' read -r hostname ips; do
        # Process each IP in the comma-separated list
        for ip in $ips; do
            # Check type using case pattern matching
            case "$ip" in
                *.*.*.*) echo "${hostname}|${ip}|A" ;;
                *:*) echo "${hostname}|${ip}|AAAA" ;;
            esac
        done
    done || true
fi

# Parse address entries (address=/hostname/ip)
if [ -d /etc/dnsmasq.d ]; then
    find /etc/dnsmasq.d -type f -name "*.conf" ! -name "*.bak" ! -name "*.old" ! -name "*.orig" ! -name "*~" -exec grep -h "^address=/" {} \; 2>/dev/null | sed 's/.*address=//' | while IFS='/' read -r dummy hostname ip rest; do
        # Check type using case pattern matching
        case "$ip" in
            *.*.*.*) echo "${hostname}|${ip}|A" ;;
            *:*) echo "${hostname}|${ip}|AAAA" ;;
        esac
    done || true
fi

# Always exit successfully even if no files found
exit 0
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        if (! $result['success']) {
            throw new \RuntimeException('Failed to read dnsmasq config files: '.$result['error']);
        }

        return $this->parseConfigOutput($result['output']);
    }

    /**
     * Parse UCI address list (AAAA records)
     */
    private function parseUciAddressList(string $sshHost): array
    {
        $script = <<<'BASH'
#!/bin/sh
# Output format: hostname|ip|type
# Parse UCI address list: dhcp.@dnsmasq[0].address='/hostname/ip' '/hostname2/ip2'

# Get the address list - it's a single line with multiple quoted entries
uci show dhcp.@dnsmasq[0].address 2>/dev/null | sed "s/.*address='//" | sed "s/'$//" | tr "'" "\n" | while read entry; do
    # Skip empty lines and lines that don't start with /
    [ -z "$entry" ] && continue
    echo "$entry" | grep -q "^/" || continue

    # Remove leading / and parse hostname/ip
    entry=$(echo "$entry" | sed 's|^/||')
    hostname=$(echo "$entry" | cut -d'/' -f1)
    ip=$(echo "$entry" | cut -d'/' -f2)

    # Check type using case pattern matching
    case "$ip" in
        *.*.*.*) echo "${hostname}|${ip}|A" ;;
        *:*) echo "${hostname}|${ip}|AAAA" ;;
    esac
done

exit 0
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        if (! $result['success']) {
            throw new \RuntimeException('Failed to read UCI address list: '.$result['error']);
        }

        return $this->parseConfigOutput($result['output']);
    }

    /**
     * Parse UCI host output into structured array
     */
    private function parseHostOutput(string $output): array
    {
        $hosts = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            [$hostname, $ip, $mac, $dnsEnabled] = array_pad(explode('|', $line), 4, '');

            if (empty($hostname) || empty($ip)) {
                continue;
            }

            // Determine record type
            $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'A';

            $hosts[] = [
                'name' => $hostname,
                'ip' => $ip,
                'type' => $type,
                'source' => 'uci',
                'mac' => $mac,
            ];
        }

        return $hosts;
    }

    /**
     * Parse dnsmasq config output into structured array
     */
    private function parseConfigOutput(string $output): array
    {
        $hosts = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            [$hostname, $ip, $type] = array_pad(explode('|', $line), 3, '');

            if (empty($hostname) || empty($ip)) {
                continue;
            }

            $hosts[] = [
                'name' => $hostname,
                'ip' => $ip,
                'type' => $type,
                'source' => 'config',
            ];
        }

        return $hosts;
    }

    /**
     * Group hosts by zone (extract domain from hostname)
     */
    private function groupHostsByZone(array $hosts): array
    {
        $zones = [];

        foreach ($hosts as $host) {
            // Extract zone from hostname (e.g., "mail.goldcoast.org" → "goldcoast.org")
            $parts = explode('.', $host['name']);

            if (count($parts) >= 2) {
                $zone = $parts[count($parts) - 2].'.'.$parts[count($parts) - 1].'.';
            } else {
                // Unqualified hostname - use special "local" zone
                $zone = 'local.';
            }

            if (! isset($zones[$zone])) {
                $zones[$zone] = [];
            }

            $zones[$zone][] = $host;
        }

        return $zones;
    }

    /**
     * Generate dnsmasq configuration from host data
     */
    private function generateDnsmasqConfig(array $hosts): string
    {
        $config = "# NetServa-managed dnsmasq configuration\n";
        $config .= '# Generated: '.now()->toDateTimeString()."\n";
        $config .= "# DO NOT EDIT - managed by NetServa Fleet\n\n";

        // Group by zone for better organization
        $hostsByZone = $this->groupHostsByZone($hosts);

        foreach ($hostsByZone as $zoneName => $zoneHosts) {
            $config .= "# Zone: {$zoneName}\n";

            foreach ($zoneHosts as $host) {
                $hostname = rtrim($host['name'], '.');

                if (in_array($host['type'], ['A', 'AAAA'])) {
                    $config .= "address=/{$hostname}/{$host['ip']}\n";
                }
            }

            $config .= "\n";
        }

        return $config;
    }

    /**
     * Deploy configuration to remote dnsmasq
     */
    private function deployConfig(string $sshHost, string $config): array
    {
        $script = <<<BASH
#!/bin/bash

# Backup current config
if [ -f /etc/dnsmasq.d/netserva-managed.conf ]; then
    cp /etc/dnsmasq.d/netserva-managed.conf /etc/dnsmasq.d/netserva-managed.conf.bak
fi

# Write new config
cat > /etc/dnsmasq.d/netserva-managed.conf << 'EOC'
{$config}
EOC

# Reload dnsmasq
/etc/init.d/dnsmasq reload

echo "✓ Dnsmasq configuration deployed and reloaded"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Get current dnsmasq status from remote vnode
     */
    public function getStatus(FleetVnode $vnode): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        $script = <<<'BASH'
#!/bin/sh

# Check if dnsmasq is running
if pidof dnsmasq > /dev/null; then
    echo "status=running"
else
    echo "status=stopped"
fi

# Get process count
ps_count=$(ps | grep -c '[d]nsmasq')
echo "processes=$ps_count"

# Check if config file exists
if [ -f /etc/dnsmasq.d/netserva-managed.conf ]; then
    line_count=$(wc -l < /etc/dnsmasq.d/netserva-managed.conf)
    echo "config_exists=true"
    echo "config_lines=$line_count"
else
    echo "config_exists=false"
    echo "config_lines=0"
fi

exit 0
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => $result['error'],
            ];
        }

        // Parse output
        $status = [];
        $lines = explode("\n", trim($result['output']));
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $status[$key] = $value === 'true' ? true : ($value === 'false' ? false : $value);
            }
        }

        return [
            'success' => true,
            'status' => $status,
        ];
    }

    /**
     * Add UCI DHCP static host entry
     */
    public function addUciHost(FleetVnode $vnode, string $hostname, string $ip, ?string $mac = null, bool $dns = true): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        $dnsFlag = $dns ? '1' : '0';
        $macOption = $mac ? "uci set dhcp.@host[-1].mac='$mac'" : '';

        $script = <<<BASH
#!/bin/sh

# Add new DHCP static host
uci add dhcp host
uci set dhcp.@host[-1].name='$hostname'
uci set dhcp.@host[-1].ip='$ip'
uci set dhcp.@host[-1].dns='$dnsFlag'
$macOption
uci commit dhcp

# Restart dnsmasq
/etc/init.d/dnsmasq restart

echo "✓ UCI host added: $hostname → $ip"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Add config file host entry
     */
    public function addConfigHost(FleetVnode $vnode, string $hostname, string $ip): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        $script = <<<BASH
#!/bin/sh

# Add address entry to netserva-managed.conf
echo "address=/$hostname/$ip" >> /etc/dnsmasq.d/netserva-managed.conf

# Reload dnsmasq
/etc/init.d/dnsmasq reload

echo "✓ Config host added: $hostname → $ip"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Update UCI DHCP static host entry
     */
    public function updateUciHost(FleetVnode $vnode, string $hostname, array $changes): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        // Build uci set commands
        $setCommands = [];

        // Find the host index first
        $findScript = <<<BASH
#!/bin/sh
uci show dhcp | grep ".name='$hostname'" | cut -d. -f1-2 | head -1
BASH;

        $findResult = $this->remoteExecution->executeScript($sshHost, $findScript);

        if (! $findResult['success'] || empty(trim($findResult['output']))) {
            return [
                'success' => false,
                'output' => '',
                'error' => "Host not found: $hostname",
            ];
        }

        $hostPath = trim($findResult['output']);

        if (isset($changes['hostname'])) {
            $setCommands[] = "uci set $hostPath.name='{$changes['hostname']}'";
        }
        if (isset($changes['ip'])) {
            $setCommands[] = "uci set $hostPath.ip='{$changes['ip']}'";
        }
        if (isset($changes['mac'])) {
            $setCommands[] = "uci set $hostPath.mac='{$changes['mac']}'";
        }

        $setCommandsStr = implode("\n", $setCommands);

        $script = <<<BASH
#!/bin/sh

$setCommandsStr
uci commit dhcp

# Restart dnsmasq
/etc/init.d/dnsmasq restart

echo "✓ UCI host updated: $hostname"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Update config file host entry
     */
    public function updateConfigHost(FleetVnode $vnode, string $hostname, array $changes): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        $newHostname = $changes['hostname'] ?? $hostname;
        $newIp = $changes['ip'] ?? null;

        if (! $newIp) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'IP address is required for config file updates',
            ];
        }

        // Escape dots in hostname for sed regex
        $escapedHostname = str_replace('.', '\\.', $hostname);

        // Create or ensure netserva-managed.conf exists
        $script = <<<BASH
#!/bin/sh

# Create netserva-managed.conf if it doesn't exist
if [ ! -f /etc/dnsmasq.d/netserva-managed.conf ]; then
    cat > /etc/dnsmasq.d/netserva-managed.conf << 'EOF'
# NetServa-managed dnsmasq configuration
# Generated by NetServa Fleet
EOF
fi

# Remove old entry from all config files
if [ -d /etc/dnsmasq.d ]; then
    find /etc/dnsmasq.d -type f -name "*.conf" -exec sed -i "/address=\\/$escapedHostname\\//d" {} \\;
fi

# Add new entry
echo "address=/$newHostname/$newIp" >> /etc/dnsmasq.d/netserva-managed.conf

# Reload dnsmasq
/etc/init.d/dnsmasq reload

echo "✓ Config host updated: $hostname → $newHostname ($newIp)"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Delete UCI DHCP static host entry
     */
    public function deleteUciHost(FleetVnode $vnode, string $hostname): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        $script = <<<BASH
#!/bin/sh

# Find host index
host_idx=\$(uci show dhcp | grep ".name='$hostname'" | cut -d[ -f2 | cut -d] -f1 | head -1)

if [ -z "\$host_idx" ]; then
    echo "✗ Host not found: $hostname"
    exit 1
fi

# Delete the host
uci delete dhcp.@host[\$host_idx]
uci commit dhcp

# Restart dnsmasq
/etc/init.d/dnsmasq restart

echo "✓ UCI host deleted: $hostname"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Delete config file host entry
     */
    public function deleteConfigHost(FleetVnode $vnode, string $hostname): array
    {
        $sshHost = $vnode->sshHost->host ?? null;
        if (! $sshHost) {
            throw new \InvalidArgumentException('VNode must have SSH host configured');
        }

        // Escape dots in hostname for sed regex
        $escapedHostname = str_replace('.', '\\.', $hostname);

        $script = <<<BASH
#!/bin/sh

# Remove entry from all config files in dnsmasq.d
if [ -d /etc/dnsmasq.d ]; then
    # Use extended regex with -E for better compatibility
    find /etc/dnsmasq.d -type f -name "*.conf" -exec sed -i "/address=\\/$escapedHostname\\//d" {} \\;
fi

# Reload dnsmasq
/etc/init.d/dnsmasq reload

echo "✓ Config host deleted: $hostname"
BASH;

        $result = $this->remoteExecution->executeScript($sshHost, $script);

        return [
            'success' => $result['success'],
            'output' => $result['output'],
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * ========================================
     * Local Database Cache Methods
     * ========================================
     */

    /**
     * Sync from remote and update local cache
     *
     * @return array Sync result with updated cache
     */
    public function syncAndCache(FleetVnode $vnode): array
    {
        // Fetch from remote
        $result = $this->syncFromRemote($vnode);

        // Update local cache
        $this->updateCache($vnode, $result['all_hosts']);

        return $result;
    }

    /**
     * Get hosts from local cache
     */
    public function getCachedHosts(FleetVnode $vnode): Collection
    {
        return FleetDnsmasqHost::forVnode($vnode)
            ->orderBy('type', 'asc')  // A records first, then AAAA
            ->orderBy('hostname')
            ->get();
    }

    /**
     * Update local cache with host data
     *
     * @param  array  $hosts  Array of host records from remote
     */
    public function updateCache(FleetVnode $vnode, array $hosts): void
    {
        DB::transaction(function () use ($vnode, $hosts) {
            // Delete existing cache for this vnode
            FleetDnsmasqHost::where('fleet_vnode_id', $vnode->id)->delete();

            // Insert new records
            foreach ($hosts as $host) {
                FleetDnsmasqHost::create([
                    'fleet_vnode_id' => $vnode->id,
                    'hostname' => $host['name'],
                    'ip' => $host['ip'],
                    'type' => $host['type'],
                    'mac' => $host['mac'] ?? null,
                    'source' => $host['source'],
                    'dns_enabled' => true,
                ]);
            }
        });
    }

    /**
     * Add single host to cache
     */
    public function addToCache(FleetVnode $vnode, string $hostname, string $ip, ?string $mac = null): FleetDnsmasqHost
    {
        $type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'A';

        return FleetDnsmasqHost::updateOrCreate(
            [
                'fleet_vnode_id' => $vnode->id,
                'hostname' => $hostname,
                'ip' => $ip,
                'type' => $type,
            ],
            [
                'mac' => $mac,
                'source' => 'uci',
                'dns_enabled' => true,
            ]
        );
    }

    /**
     * Update host in cache
     */
    public function updateInCache(FleetVnode $vnode, string $hostname, array $changes): void
    {
        $query = FleetDnsmasqHost::forVnode($vnode)->where('hostname', $hostname);

        // If renaming hostname
        if (isset($changes['hostname'])) {
            $query->update(['hostname' => $changes['hostname']]);
        }

        // If changing IP
        if (isset($changes['ip'])) {
            $type = filter_var($changes['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'AAAA' : 'A';
            $query->update([
                'ip' => $changes['ip'],
                'type' => $type,
            ]);
        }

        // If changing MAC
        if (isset($changes['mac'])) {
            $query->update(['mac' => $changes['mac']]);
        }
    }

    /**
     * Delete host from cache
     */
    public function deleteFromCache(FleetVnode $vnode, string $hostname): void
    {
        FleetDnsmasqHost::forVnode($vnode)
            ->where('hostname', $hostname)
            ->delete();
    }

    /**
     * Check if cache is stale
     */
    public function isCacheStale(FleetVnode $vnode, int $maxAgeMinutes = 60): bool
    {
        return FleetDnsmasqHost::isCacheStale($vnode, $maxAgeMinutes);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(FleetVnode $vnode): array
    {
        $lastUpdate = FleetDnsmasqHost::forVnode($vnode)->max('updated_at');

        return [
            'total_records' => FleetDnsmasqHost::forVnode($vnode)->count(),
            'a_records' => FleetDnsmasqHost::forVnode($vnode)->type('A')->count(),
            'aaaa_records' => FleetDnsmasqHost::forVnode($vnode)->type('AAAA')->count(),
            'with_mac' => FleetDnsmasqHost::forVnode($vnode)->withMac()->count(),
            'last_update' => $lastUpdate?->toDateTimeString(),
            'age_minutes' => $lastUpdate ? now()->diffInMinutes($lastUpdate) : null,
            'is_stale' => $this->isCacheStale($vnode),
        ];
    }
}
