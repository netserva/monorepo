<?php

namespace NetServa\Wg\Services\HubTypes;

use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;

class CustomerHubService
{
    public function __construct(
        private SshConnectionService $sshService
    ) {}

    /**
     * Configure hub with customer isolation
     */
    public function configureAsCustomerHub(WireguardHub $hub): bool
    {
        try {
            Log::info("Configuring customer hub with isolation: {$hub->name}");

            // Setup customer isolation
            $this->setupCustomerIsolation($hub);

            // Configure customer-specific firewall
            $this->configureCustomerFirewall($hub);

            // Setup customer monitoring
            $this->setupCustomerMonitoring($hub);

            // Configure customer DNS (if specified)
            $this->configureCustomerDNS($hub);

            // Setup customer bandwidth limits
            $this->configureCustomerBandwidth($hub);

            $hub->update([
                'configuration' => json_encode([
                    'customer_role' => true,
                    'isolation_enabled' => true,
                    'firewall_configured' => true,
                    'monitoring_enabled' => true,
                    'bandwidth_limited' => true,
                    'configured_at' => now(),
                ]),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to configure customer hub {$hub->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Setup strict customer isolation
     */
    private function setupCustomerIsolation(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $commands = [
            // Install required packages
            'apt update && apt install -y iptables iptables-persistent',
            'apt install -y netfilter-persistent',

            // Enable IP forwarding only within customer network
            'echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf',
            'sysctl -p',

            // Create customer isolation directory
            'mkdir -p /etc/wireguard/customer-isolation',
            'chmod 700 /etc/wireguard/customer-isolation',
        ];

        foreach ($commands as $command) {
            $this->sshService->executeCommand($connection, $command);
        }

        // Create customer isolation script
        $this->createCustomerIsolationScript($hub);
    }

    /**
     * Create customer isolation script
     */
    private function createCustomerIsolationScript(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Get all other customer networks for blocking
        $otherCustomerNetworks = WireguardHub::where('hub_type', 'customer')
            ->where('id', '!=', $hub->id)
            ->where('status', 'active')
            ->pluck('network_cidr')
            ->toArray();

        $isolationScript = <<<SCRIPT
#!/bin/bash
# Customer Hub Isolation Script for {$hub->name}

WG_INTERFACE="{$hub->interface_name}"
CUSTOMER_NETWORK="{$hub->network_cidr}"
CUSTOMER_ID="{$hub->id}"

# Create custom chains for customer isolation
iptables -N CUSTOMER_\${CUSTOMER_ID}_INPUT 2>/dev/null || true
iptables -N CUSTOMER_\${CUSTOMER_ID}_FORWARD 2>/dev/null || true
iptables -N CUSTOMER_\${CUSTOMER_ID}_OUTPUT 2>/dev/null || true

# Clear custom chains
iptables -F CUSTOMER_\${CUSTOMER_ID}_INPUT
iptables -F CUSTOMER_\${CUSTOMER_ID}_FORWARD
iptables -F CUSTOMER_\${CUSTOMER_ID}_OUTPUT

# Allow traffic within customer network
iptables -A CUSTOMER_\${CUSTOMER_ID}_FORWARD -i \$WG_INTERFACE -o \$WG_INTERFACE -s \$CUSTOMER_NETWORK -d \$CUSTOMER_NETWORK -j ACCEPT

# Allow essential services (DNS, DHCP)
iptables -A CUSTOMER_\${CUSTOMER_ID}_INPUT -i \$WG_INTERFACE -p udp --dport 53 -j ACCEPT
iptables -A CUSTOMER_\${CUSTOMER_ID}_INPUT -i \$WG_INTERFACE -p tcp --dport 53 -j ACCEPT
iptables -A CUSTOMER_\${CUSTOMER_ID}_INPUT -i \$WG_INTERFACE -p icmp -j ACCEPT

# Block access to other customer networks
SCRIPT;

        foreach ($otherCustomerNetworks as $network) {
            $isolationScript .= <<<SCRIPT

# Block access to customer network: {$network}
iptables -A CUSTOMER_\${CUSTOMER_ID}_FORWARD -i \$WG_INTERFACE -d {$network} -j LOG --log-prefix "CUSTOMER_ISOLATION_BLOCK: "
iptables -A CUSTOMER_\${CUSTOMER_ID}_FORWARD -i \$WG_INTERFACE -d {$network} -j DROP
SCRIPT;
        }

        $isolationScript .= <<<'SCRIPT'

# Block access to management networks
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -d 192.168.200.0/24 -j LOG --log-prefix "MGMT_ACCESS_BLOCK: "
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -d 192.168.200.0/24 -j DROP

# Block access to private RFC1918 networks not belonging to customer
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -d 10.0.0.0/8 -j DROP
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -d 172.16.0.0/12 -j DROP
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -d 192.168.0.0/16 ! -d $CUSTOMER_NETWORK -j DROP

# Log and drop any other suspicious traffic
iptables -A CUSTOMER_${CUSTOMER_ID}_FORWARD -i $WG_INTERFACE -m limit --limit 5/min -j LOG --log-prefix "CUSTOMER_SUSPICIOUS: "

# Apply customer chains to main chains
iptables -A INPUT -j CUSTOMER_${CUSTOMER_ID}_INPUT
iptables -A FORWARD -j CUSTOMER_${CUSTOMER_ID}_FORWARD
iptables -A OUTPUT -j CUSTOMER_${CUSTOMER_ID}_OUTPUT

# Save rules
iptables-save > /etc/iptables/rules.v4

echo "Customer isolation configured for hub: $WG_INTERFACE"
echo "Customer network: $CUSTOMER_NETWORK"
echo "Blocked networks: $(echo "${OTHER_NETWORKS[@]}" | tr ' ' ',')"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/customer-isolation/isolation-{$hub->id}.sh << 'EOF'\n{$isolationScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            "chmod +x /etc/wireguard/customer-isolation/isolation-{$hub->id}.sh"
        );

        $this->sshService->executeCommand(
            $connection,
            "/etc/wireguard/customer-isolation/isolation-{$hub->id}.sh"
        );
    }

    /**
     * Configure customer-specific firewall
     */
    private function configureCustomerFirewall(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $firewallScript = <<<SCRIPT
#!/bin/bash
# Customer-specific firewall for {$hub->name}

WG_INTERFACE="{$hub->interface_name}"
CUSTOMER_NETWORK="{$hub->network_cidr}"
CUSTOMER_ID="{$hub->id}"

# Create customer-specific firewall chain
iptables -N CUSTOMER_\${CUSTOMER_ID}_FIREWALL 2>/dev/null || true
iptables -F CUSTOMER_\${CUSTOMER_ID}_FIREWALL

# Default policy: deny all, allow specific
iptables -A CUSTOMER_\${CUSTOMER_ID}_FIREWALL -j DROP

# Allow established and related connections
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# Allow loopback traffic
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i lo -j ACCEPT

# Allow customer internal communication
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -s \$CUSTOMER_NETWORK -d \$CUSTOMER_NETWORK -j ACCEPT

# Allow essential services
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -p udp --dport 53 -j ACCEPT  # DNS
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -p tcp --dport 53 -j ACCEPT  # DNS
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -p icmp --icmp-type echo-request -j ACCEPT  # Ping

# Allow common web services
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -p tcp --dport 80 -j ACCEPT   # HTTP
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -p tcp --dport 443 -j ACCEPT  # HTTPS

# Rate limiting for connection attempts
iptables -I CUSTOMER_\${CUSTOMER_ID}_FIREWALL -i \$WG_INTERFACE -m limit --limit 10/min --limit-burst 20 -j ACCEPT

# Log dropped packets
iptables -A CUSTOMER_\${CUSTOMER_ID}_FIREWALL -m limit --limit 3/min -j LOG --log-prefix "CUSTOMER_FW_DROP: "

# Apply firewall to customer traffic
iptables -I FORWARD -i \$WG_INTERFACE -j CUSTOMER_\${CUSTOMER_ID}_FIREWALL

# Save configuration
iptables-save > /etc/iptables/rules.v4

echo "Customer firewall configured for customer ID: \$CUSTOMER_ID"
echo "Interface: \$WG_INTERFACE"
echo "Network: \$CUSTOMER_NETWORK"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/customer-isolation/firewall-{$hub->id}.sh << 'EOF'\n{$firewallScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            "chmod +x /etc/wireguard/customer-isolation/firewall-{$hub->id}.sh"
        );

        $this->sshService->executeCommand(
            $connection,
            "/etc/wireguard/customer-isolation/firewall-{$hub->id}.sh"
        );
    }

    /**
     * Setup customer-specific monitoring
     */
    private function setupCustomerMonitoring(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $monitoringScript = <<<SCRIPT
#!/bin/bash
# Customer Hub Monitoring for {$hub->name}

LOG_DIR="/var/log/wireguard-customer"
CUSTOMER_LOG="\$LOG_DIR/customer-{$hub->id}.log"
STATS_LOG="\$LOG_DIR/customer-{$hub->id}-stats.json"
ALERTS_LOG="\$LOG_DIR/customer-{$hub->id}-alerts.log"

# Create log directory
mkdir -p "\$LOG_DIR"

log_message() {
    echo "[\$(date '+%Y-%m-%d %H:%M:%S')] \$1" >> "\$CUSTOMER_LOG"
}

# Monitor customer network usage
monitor_customer_usage() {
    local interface="{$hub->interface_name}"
    local customer_network="{$hub->network_cidr}"
    
    log_message "Monitoring customer usage for network: \$customer_network"
    
    # Get interface statistics
    if [ -d "/sys/class/net/\$interface" ]; then
        local rx_bytes=\$(cat "/sys/class/net/\$interface/statistics/rx_bytes")
        local tx_bytes=\$(cat "/sys/class/net/\$interface/statistics/tx_bytes")
        local rx_packets=\$(cat "/sys/class/net/\$interface/statistics/rx_packets")
        local tx_packets=\$(cat "/sys/class/net/\$interface/statistics/tx_packets")
        
        # Generate usage statistics
        cat >> "\$STATS_LOG" << EOF
{
  "timestamp": "\$(date -Iseconds)",
  "customer_id": "{$hub->id}",
  "customer_name": "{$hub->name}",
  "interface": "\$interface",
  "network": "\$customer_network",
  "usage": {
    "rx_bytes": \$rx_bytes,
    "tx_bytes": \$tx_bytes,
    "rx_packets": \$rx_packets,
    "tx_packets": \$tx_packets,
    "total_bytes": \$((\$rx_bytes + \$tx_bytes))
  }
}
EOF
        
        log_message "Usage - RX: \${rx_bytes} bytes, TX: \${tx_bytes} bytes"
        
        # Check for unusual usage patterns
        check_usage_alerts "\$rx_bytes" "\$tx_bytes"
    fi
}

# Check for usage alerts
check_usage_alerts() {
    local rx_bytes="\$1"
    local tx_bytes="\$2"
    local total_bytes=\$((\$rx_bytes + \$tx_bytes))
    
    # Alert thresholds (can be customized per customer)
    local bandwidth_threshold=\${BANDWIDTH_THRESHOLD:-1073741824}  # 1GB default
    local packet_rate_threshold=\${PACKET_RATE_THRESHOLD:-10000}   # 10k packets default
    
    if [ "\$total_bytes" -gt "\$bandwidth_threshold" ]; then
        echo "[\$(date -Iseconds)] ALERT: High bandwidth usage - \$total_bytes bytes" >> "\$ALERTS_LOG"
        log_message "ALERT: High bandwidth usage detected"
    fi
}

# Monitor connected spokes
monitor_customer_spokes() {
    local interface="{$hub->interface_name}"
    
    log_message "Monitoring customer spokes on interface: \$interface"
    
    # Count active peers
    local peer_count=\$(wg show "\$interface" peers 2>/dev/null | wc -l)
    local active_connections=\$(wg show "\$interface" latest-handshakes 2>/dev/null | awk '\$2 > systime() - 180' | wc -l)
    
    log_message "Connected spokes: \$peer_count, Active connections: \$active_connections"
    
    # Log peer details
    wg show "\$interface" dump 2>/dev/null | while read line; do
        if [[ \$line =~ ^[a-zA-Z0-9+/=]+[[:space:]]+[a-zA-Z0-9+/=]+[[:space:]]+[0-9.]+:[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[a-zA-Z0-9./,:]+ ]]; then
            local peer=\$(echo "\$line" | awk '{print \$1}')
            local endpoint=\$(echo "\$line" | awk '{print \$3}')
            local latest_handshake=\$(echo "\$line" | awk '{print \$4}')
            local rx=\$(echo "\$line" | awk '{print \$5}')
            local tx=\$(echo "\$line" | awk '{print \$6}')
            
            log_message "Peer: \$peer, Endpoint: \$endpoint, Handshake: \$latest_handshake, RX: \$rx, TX: \$tx"
        fi
    done
}

# Check customer security events
check_security_events() {
    log_message "Checking security events for customer"
    
    # Check iptables logs for blocked traffic
    local blocked_attempts=\$(journalctl --since "5 minutes ago" | grep "CUSTOMER_ISOLATION_BLOCK\|CUSTOMER_FW_DROP" | wc -l)
    
    if [ "\$blocked_attempts" -gt 0 ]; then
        echo "[\$(date -Iseconds)] SECURITY: \$blocked_attempts blocked attempts in last 5 minutes" >> "\$ALERTS_LOG"
        log_message "SECURITY: \$blocked_attempts blocked connection attempts"
        
        # Log specific blocked attempts
        journalctl --since "5 minutes ago" | grep "CUSTOMER_ISOLATION_BLOCK\|CUSTOMER_FW_DROP" | tail -5 >> "\$ALERTS_LOG"
    fi
}

# Main monitoring routine
main() {
    log_message "=== Starting Customer Monitoring ==="
    
    monitor_customer_usage
    monitor_customer_spokes
    check_security_events
    
    log_message "=== Customer Monitoring Complete ==="
}

main "\$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/customer-isolation/monitor-{$hub->id}.sh << 'EOF'\n{$monitoringScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            "chmod +x /etc/wireguard/customer-isolation/monitor-{$hub->id}.sh"
        );

        // Setup cron job for customer monitoring
        $this->sshService->executeCommand(
            $connection,
            "echo '*/10 * * * * root /etc/wireguard/customer-isolation/monitor-{$hub->id}.sh' > /etc/cron.d/wireguard-customer-{$hub->id}-monitoring"
        );
    }

    /**
     * Configure customer DNS settings
     */
    private function configureCustomerDNS(WireguardHub $hub): void
    {
        if (! $hub->dns_servers) {
            return; // No custom DNS configured
        }

        $connection = $this->sshService->getConnection($hub->sshHost->host);
        $dnsServers = json_decode($hub->dns_servers, true);

        $dnsScript = <<<SCRIPT
#!/bin/bash
# Customer DNS Configuration for {$hub->name}

WG_INTERFACE="{$hub->interface_name}"
CUSTOMER_NETWORK="{$hub->network_cidr}"

# Install and configure dnsmasq for customer-specific DNS
apt update && apt install -y dnsmasq

# Configure dnsmasq for customer network
cat > /etc/dnsmasq.d/customer-{$hub->id}.conf << 'EOF'
# Customer {$hub->id} DNS Configuration
interface=\$WG_INTERFACE
bind-interfaces
domain-needed
bogus-priv
no-resolv
SCRIPT;

        foreach ($dnsServers as $dns) {
            $dnsScript .= "\nserver={$dns}";
        }

        $dnsScript .= <<<'SCRIPT'

# Cache settings
cache-size=1000
dns-forward-max=150

# Log queries for monitoring
log-queries
log-facility=/var/log/customer-dns.log
EOF

# Restart dnsmasq
systemctl enable dnsmasq
systemctl restart dnsmasq

# Configure iptables to redirect DNS queries to local dnsmasq
iptables -t nat -A PREROUTING -i $WG_INTERFACE -p udp --dport 53 -j REDIRECT --to-port 53
iptables -t nat -A PREROUTING -i $WG_INTERFACE -p tcp --dport 53 -j REDIRECT --to-port 53

# Save iptables rules
iptables-save > /etc/iptables/rules.v4

echo "Customer DNS configured with servers: $(echo '${DNS_SERVERS[@]}' | tr ' ' ',')"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/customer-isolation/dns-{$hub->id}.sh << 'EOF'\n{$dnsScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            "chmod +x /etc/wireguard/customer-isolation/dns-{$hub->id}.sh"
        );

        $this->sshService->executeCommand(
            $connection,
            "/etc/wireguard/customer-isolation/dns-{$hub->id}.sh"
        );
    }

    /**
     * Configure customer bandwidth limits
     */
    private function configureCustomerBandwidth(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Get bandwidth limits from hub configuration or use defaults
        $config = json_decode($hub->configuration ?: '{}', true);
        $uploadLimit = $config['bandwidth_limit_upload'] ?? '50mbit';
        $downloadLimit = $config['bandwidth_limit_download'] ?? '100mbit';

        $bandwidthScript = <<<SCRIPT
#!/bin/bash
# Customer Bandwidth Limits for {$hub->name}

WG_INTERFACE="{$hub->interface_name}"
CUSTOMER_NETWORK="{$hub->network_cidr}"
UPLOAD_LIMIT="{$uploadLimit}"
DOWNLOAD_LIMIT="{$downloadLimit}"

# Install traffic control tools
apt update && apt install -y iproute2 tc

# Clear existing QoS rules for this interface
tc qdisc del dev \$WG_INTERFACE root 2>/dev/null || true

# Configure HTB (Hierarchical Token Bucket) for bandwidth limiting
tc qdisc add dev \$WG_INTERFACE root handle 1: htb default 10

# Create main class with total bandwidth limit
tc class add dev \$WG_INTERFACE parent 1: classid 1:1 htb rate \$UPLOAD_LIMIT ceil \$UPLOAD_LIMIT

# Create default class for all customer traffic
tc class add dev \$WG_INTERFACE parent 1:1 classid 1:10 htb rate \$UPLOAD_LIMIT ceil \$UPLOAD_LIMIT

# Add fair queuing to prevent individual connections from hogging bandwidth
tc qdisc add dev \$WG_INTERFACE parent 1:10 handle 10: sfq perturb 10

# Apply bandwidth limiting to customer network
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 1 u32 match ip src \$CUSTOMER_NETWORK flowid 1:10

# Configure download limiting using IFB (Intermediate Functional Block)
modprobe ifb
ip link add ifb-\$WG_INTERFACE type ifb
ip link set dev ifb-\$WG_INTERFACE up

# Redirect ingress traffic to IFB for download limiting
tc qdisc add dev \$WG_INTERFACE ingress
tc filter add dev \$WG_INTERFACE parent ffff: protocol ip u32 match u32 0 0 flowid 1:1 action mirred egress redirect dev ifb-\$WG_INTERFACE

# Configure HTB on IFB for download limiting
tc qdisc add dev ifb-\$WG_INTERFACE root handle 1: htb default 10
tc class add dev ifb-\$WG_INTERFACE parent 1: classid 1:1 htb rate \$DOWNLOAD_LIMIT ceil \$DOWNLOAD_LIMIT
tc class add dev ifb-\$WG_INTERFACE parent 1:1 classid 1:10 htb rate \$DOWNLOAD_LIMIT ceil \$DOWNLOAD_LIMIT
tc qdisc add dev ifb-\$WG_INTERFACE parent 1:10 handle 10: sfq perturb 10

echo "Bandwidth limits configured for customer:"
echo "Interface: \$WG_INTERFACE"
echo "Network: \$CUSTOMER_NETWORK"
echo "Upload limit: \$UPLOAD_LIMIT"
echo "Download limit: \$DOWNLOAD_LIMIT"

# Display current configuration
tc -s qdisc show dev \$WG_INTERFACE
tc -s class show dev \$WG_INTERFACE
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/customer-isolation/bandwidth-{$hub->id}.sh << 'EOF'\n{$bandwidthScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            "chmod +x /etc/wireguard/customer-isolation/bandwidth-{$hub->id}.sh"
        );

        $this->sshService->executeCommand(
            $connection,
            "/etc/wireguard/customer-isolation/bandwidth-{$hub->id}.sh"
        );
    }

    /**
     * Add spoke to customer hub with isolation
     */
    public function addSpokeToCustomerHub(WireguardHub $customerHub, WireguardSpoke $spoke): bool
    {
        try {
            $connection = $this->sshService->getConnection($customerHub->sshHost);

            // Verify spoke IP is within customer network
            if (! $this->isIPInNetwork($spoke->allocated_ip, $customerHub->network_cidr)) {
                throw new \Exception("Spoke IP {$spoke->allocated_ip} is not within customer network {$customerHub->network_cidr}");
            }

            // Add spoke to customer firewall rules
            $spokeIP = $spoke->allocated_ip;

            $commands = [
                "iptables -I CUSTOMER_{$customerHub->id}_FORWARD -s {$spokeIP}/32 -d {$customerHub->network_cidr} -j ACCEPT",
                "iptables -I CUSTOMER_{$customerHub->id}_FORWARD -s {$customerHub->network_cidr} -d {$spokeIP}/32 -j ACCEPT",
                'iptables-save > /etc/iptables/rules.v4',
            ];

            foreach ($commands as $command) {
                $this->sshService->executeCommand($connection, $command);
            }

            Log::info("Added spoke {$spoke->name} ({$spokeIP}) to customer hub {$customerHub->name}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to add spoke to customer hub: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Remove spoke from customer hub
     */
    public function removeSpokeFromCustomerHub(WireguardHub $customerHub, WireguardSpoke $spoke): bool
    {
        try {
            $connection = $this->sshService->getConnection($customerHub->sshHost);

            $spokeIP = $spoke->allocated_ip;

            $commands = [
                "iptables -D CUSTOMER_{$customerHub->id}_FORWARD -s {$spokeIP}/32 -d {$customerHub->network_cidr} -j ACCEPT 2>/dev/null || true",
                "iptables -D CUSTOMER_{$customerHub->id}_FORWARD -s {$customerHub->network_cidr} -d {$spokeIP}/32 -j ACCEPT 2>/dev/null || true",
                'iptables-save > /etc/iptables/rules.v4',
            ];

            foreach ($commands as $command) {
                $this->sshService->executeCommand($connection, $command);
            }

            Log::info("Removed spoke {$spoke->name} ({$spokeIP}) from customer hub {$customerHub->name}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to remove spoke from customer hub: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if IP is within network CIDR
     */
    private function isIPInNetwork(string $ip, string $cidr): bool
    {
        [$network, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        $maskLong = ~((1 << (32 - (int) $mask)) - 1);

        return ($ipLong & $maskLong) === ($networkLong & $maskLong);
    }

    /**
     * Update customer isolation rules when other customer hubs are added/removed
     */
    public function updateCustomerIsolation(WireguardHub $customerHub): bool
    {
        try {
            Log::info("Updating customer isolation rules for hub: {$customerHub->name}");

            // Regenerate isolation script with current customer networks
            $this->createCustomerIsolationScript($customerHub);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update customer isolation: '.$e->getMessage());

            return false;
        }
    }
}
