<?php

namespace NetServa\Wg\Services\HubTypes;

use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;

class GatewayHubService
{
    public function __construct(
        private SshConnectionService $sshService
    ) {}

    /**
     * Configure hub as internet gateway
     */
    public function configureAsGateway(WireguardHub $hub): bool
    {
        try {
            Log::info("Configuring gateway hub: {$hub->name}");

            // Setup NAT and forwarding
            $this->setupNatForwarding($hub);

            // Configure firewall rules
            $this->configureGatewayFirewall($hub);

            // Setup traffic monitoring
            $this->setupTrafficMonitoring($hub);

            // Configure QoS and bandwidth management
            $this->configureQoS($hub);

            // Setup load balancing for multiple uplinks
            $this->configureLoadBalancing($hub);

            $hub->update([
                'configuration' => json_encode([
                    'gateway_role' => true,
                    'nat_enabled' => true,
                    'firewall_configured' => true,
                    'traffic_monitoring' => true,
                    'qos_enabled' => true,
                    'configured_at' => now(),
                ]),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to configure gateway hub {$hub->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Setup NAT and packet forwarding
     */
    private function setupNatForwarding(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $commands = [
            // Enable IP forwarding
            'echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf',
            'echo "net.ipv6.conf.all.forwarding=1" >> /etc/sysctl.conf',
            'sysctl -p',

            // Install iptables and related tools
            'apt update && apt install -y iptables iptables-persistent',
            'apt install -y netfilter-persistent',

            // Install traffic monitoring tools
            'apt install -y iftop nethogs vnstat tcpdump',

            // Install QoS tools
            'apt install -y tc wondershaper',

            // Create iptables directory
            'mkdir -p /etc/iptables',
        ];

        foreach ($commands as $command) {
            $this->sshService->executeCommand($connection, $command);
        }

        // Configure basic NAT rules
        $this->configureBasicNAT($hub);
    }

    /**
     * Configure basic NAT rules
     */
    private function configureBasicNAT(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $natScript = <<<SCRIPT
#!/bin/bash
# WireGuard Gateway NAT Configuration

WG_INTERFACE="{$hub->interface_name}"
WG_NETWORK="{$hub->network_cidr}"
EXTERNAL_INTERFACE="\$(ip route | grep default | awk '{print \$5}' | head -1)"

# Clear existing rules
iptables -t nat -F
iptables -t filter -F FORWARD

# Enable NAT for WireGuard traffic
iptables -t nat -A POSTROUTING -s \$WG_NETWORK -o \$EXTERNAL_INTERFACE -j MASQUERADE

# Allow forwarding for WireGuard interface
iptables -A FORWARD -i \$WG_INTERFACE -j ACCEPT
iptables -A FORWARD -o \$WG_INTERFACE -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

# Allow established connections
iptables -A FORWARD -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

# Save rules
iptables-save > /etc/iptables/rules.v4

echo "NAT configuration completed for interface \$WG_INTERFACE"
echo "External interface: \$EXTERNAL_INTERFACE"
echo "WireGuard network: \$WG_NETWORK"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-gateway-nat.sh << 'EOF'\n{$natScript}\nEOF"
        );

        $this->sshService->executeCommand($connection, 'chmod +x /opt/wireguard-gateway-nat.sh');
        $this->sshService->executeCommand($connection, '/opt/wireguard-gateway-nat.sh');
    }

    /**
     * Configure gateway firewall rules
     */
    private function configureGatewayFirewall(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $firewallScript = <<<SCRIPT
#!/bin/bash
# WireGuard Gateway Firewall Configuration

WG_INTERFACE="{$hub->interface_name}"
WG_NETWORK="{$hub->network_cidr}"
EXTERNAL_INTERFACE="\$(ip route | grep default | awk '{print \$5}' | head -1)"

# Create custom chains
iptables -N WG_INPUT 2>/dev/null || true
iptables -N WG_FORWARD 2>/dev/null || true
iptables -N WG_OUTPUT 2>/dev/null || true

# Clear custom chains
iptables -F WG_INPUT
iptables -F WG_FORWARD
iptables -F WG_OUTPUT

# INPUT rules for WireGuard interface
iptables -A WG_INPUT -i \$WG_INTERFACE -p icmp -j ACCEPT
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 53 -j ACCEPT
iptables -A WG_INPUT -i \$WG_INTERFACE -p udp --dport 53 -j ACCEPT
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 80 -j ACCEPT
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 443 -j ACCEPT

# Block dangerous services from WireGuard clients
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 22 -j LOG --log-prefix "WG_SSH_BLOCK: "
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 22 -j DROP
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 3389 -j LOG --log-prefix "WG_RDP_BLOCK: "
iptables -A WG_INPUT -i \$WG_INTERFACE -p tcp --dport 3389 -j DROP

# FORWARD rules for WireGuard traffic
iptables -A WG_FORWARD -i \$WG_INTERFACE -o \$EXTERNAL_INTERFACE -j ACCEPT
iptables -A WG_FORWARD -i \$EXTERNAL_INTERFACE -o \$WG_INTERFACE -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

# Block inter-client communication (optional)
iptables -A WG_FORWARD -i \$WG_INTERFACE -o \$WG_INTERFACE -j LOG --log-prefix "WG_CLIENT_BLOCK: "
iptables -A WG_FORWARD -i \$WG_INTERFACE -o \$WG_INTERFACE -j DROP

# Apply custom chains to main chains
iptables -A INPUT -j WG_INPUT
iptables -A FORWARD -j WG_FORWARD
iptables -A OUTPUT -j WG_OUTPUT

# Rate limiting for WireGuard connections
iptables -A INPUT -i \$WG_INTERFACE -m limit --limit 25/min --limit-burst 100 -j ACCEPT

# Save rules
iptables-save > /etc/iptables/rules.v4

echo "Gateway firewall configured"
echo "WireGuard interface: \$WG_INTERFACE"
echo "External interface: \$EXTERNAL_INTERFACE"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-gateway-firewall.sh << 'EOF'\n{$firewallScript}\nEOF"
        );

        $this->sshService->executeCommand($connection, 'chmod +x /opt/wireguard-gateway-firewall.sh');
        $this->sshService->executeCommand($connection, '/opt/wireguard-gateway-firewall.sh');
    }

    /**
     * Setup traffic monitoring
     */
    private function setupTrafficMonitoring(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $monitoringScript = <<<'SCRIPT'
#!/bin/bash
# WireGuard Gateway Traffic Monitoring

LOG_DIR="/var/log/wireguard-gateway"
STATS_FILE="$LOG_DIR/traffic-stats.log"
INTERFACE_STATS="$LOG_DIR/interface-stats.log"

# Create log directory
mkdir -p "$LOG_DIR"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$STATS_FILE"
}

# Monitor WireGuard interface traffic
monitor_interface_traffic() {
    local wg_interface="$1"
    
    if [ -z "$wg_interface" ]; then
        log_message "ERROR: No WireGuard interface specified"
        return 1
    fi
    
    # Get interface statistics
    if [ -d "/sys/class/net/$wg_interface" ]; then
        local rx_bytes=$(cat "/sys/class/net/$wg_interface/statistics/rx_bytes")
        local tx_bytes=$(cat "/sys/class/net/$wg_interface/statistics/tx_bytes")
        local rx_packets=$(cat "/sys/class/net/$wg_interface/statistics/rx_packets")
        local tx_packets=$(cat "/sys/class/net/$wg_interface/statistics/tx_packets")
        local rx_errors=$(cat "/sys/class/net/$wg_interface/statistics/rx_errors")
        local tx_errors=$(cat "/sys/class/net/$wg_interface/statistics/tx_errors")
        
        # Log statistics in JSON format
        cat >> "$INTERFACE_STATS" << EOF
{
  "timestamp": "$(date -Iseconds)",
  "interface": "$wg_interface",
  "rx_bytes": $rx_bytes,
  "tx_bytes": $tx_bytes,
  "rx_packets": $rx_packets,
  "tx_packets": $tx_packets,
  "rx_errors": $rx_errors,
  "tx_errors": $tx_errors
}
EOF
        
        log_message "Interface $wg_interface - RX: ${rx_bytes} bytes, TX: ${tx_bytes} bytes"
    else
        log_message "ERROR: Interface $wg_interface not found"
    fi
}

# Monitor connected peers
monitor_peer_traffic() {
    wg show all | while read line; do
        if [[ $line =~ ^interface:\ (.+) ]]; then
            interface="${BASH_REMATCH[1]}"
        elif [[ $line =~ ^peer:\ (.+) ]]; then
            peer="${BASH_REMATCH[1]}"
        elif [[ $line =~ ^\ \ transfer:\ (.+)\ received,\ (.+)\ sent ]]; then
            received="${BASH_REMATCH[1]}"
            sent="${BASH_REMATCH[2]}"
            
            log_message "Peer $peer on $interface - Received: $received, Sent: $sent"
        fi
    done
}

# Check bandwidth usage
check_bandwidth_usage() {
    local interface="$1"
    local threshold_mbps="${2:-100}"  # Default 100 Mbps threshold
    
    # Get current bandwidth using vnstat (if available)
    if command -v vnstat >/dev/null 2>&1; then
        local current_rate=$(vnstat -i "$interface" -tr 5 | grep 'average rate' | awk '{print $3}' | sed 's/[^0-9.]//g')
        
        if [ ! -z "$current_rate" ] && (( $(echo "$current_rate > $threshold_mbps" | bc -l) )); then
            log_message "WARNING: High bandwidth usage on $interface: ${current_rate} Mbps"
        fi
    fi
}

# Main monitoring routine
main() {
    local wg_interface="${1:-wg0}"
    
    log_message "=== Starting Traffic Monitoring ==="
    
    monitor_interface_traffic "$wg_interface"
    monitor_peer_traffic
    check_bandwidth_usage "$wg_interface"
    
    log_message "=== Traffic Monitoring Complete ==="
}

main "$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-gateway-monitor.sh << 'EOF'\n{$monitoringScript}\nEOF"
        );

        $this->sshService->executeCommand($connection, 'chmod +x /opt/wireguard-gateway-monitor.sh');

        // Setup cron job for monitoring
        $this->sshService->executeCommand(
            $connection,
            "echo '*/5 * * * * root /opt/wireguard-gateway-monitor.sh {$hub->interface_name}' > /etc/cron.d/wireguard-gateway-monitoring"
        );
    }

    /**
     * Configure QoS and bandwidth management
     */
    private function configureQoS(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $qosScript = <<<SCRIPT
#!/bin/bash
# WireGuard Gateway QoS Configuration

WG_INTERFACE="{$hub->interface_name}"
EXTERNAL_INTERFACE="\$(ip route | grep default | awk '{print \$5}' | head -1)"

# Default bandwidth limits (can be customized)
UPLOAD_LIMIT="\${UPLOAD_LIMIT:-50mbit}"
DOWNLOAD_LIMIT="\${DOWNLOAD_LIMIT:-100mbit}"

# Clear existing QoS rules
tc qdisc del dev \$WG_INTERFACE root 2>/dev/null || true
tc qdisc del dev \$EXTERNAL_INTERFACE root 2>/dev/null || true

# Configure HTB (Hierarchical Token Bucket) on WireGuard interface
tc qdisc add dev \$WG_INTERFACE root handle 1: htb default 30

# Create main class with total bandwidth
tc class add dev \$WG_INTERFACE parent 1: classid 1:1 htb rate \$UPLOAD_LIMIT

# Create classes for different traffic types
# High priority: VoIP, DNS, SSH management
tc class add dev \$WG_INTERFACE parent 1:1 classid 1:10 htb rate 10mbit ceil \$UPLOAD_LIMIT prio 1
# Medium priority: Web browsing, email
tc class add dev \$WG_INTERFACE parent 1:1 classid 1:20 htb rate 20mbit ceil \$UPLOAD_LIMIT prio 2
# Low priority: File transfers, bulk data
tc class add dev \$WG_INTERFACE parent 1:1 classid 1:30 htb rate 20mbit ceil \$UPLOAD_LIMIT prio 3

# Add filters to classify traffic
# DNS traffic (high priority)
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 1 u32 match ip dport 53 0xffff flowid 1:10
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 1 u32 match ip sport 53 0xffff flowid 1:10

# HTTP/HTTPS traffic (medium priority)
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 2 u32 match ip dport 80 0xffff flowid 1:20
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 2 u32 match ip dport 443 0xffff flowid 1:20

# SSH management traffic (high priority)
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 1 u32 match ip dport 22 0xffff flowid 1:10

# ICMP traffic (high priority)
tc filter add dev \$WG_INTERFACE protocol ip parent 1:0 prio 1 u32 match ip protocol 1 0xff flowid 1:10

# Configure download shaping on external interface
tc qdisc add dev \$EXTERNAL_INTERFACE root handle 1: htb default 30
tc class add dev \$EXTERNAL_INTERFACE parent 1: classid 1:1 htb rate \$DOWNLOAD_LIMIT

# Create corresponding download classes
tc class add dev \$EXTERNAL_INTERFACE parent 1:1 classid 1:10 htb rate 20mbit ceil \$DOWNLOAD_LIMIT prio 1
tc class add dev \$EXTERNAL_INTERFACE parent 1:1 classid 1:20 htb rate 40mbit ceil \$DOWNLOAD_LIMIT prio 2
tc class add dev \$EXTERNAL_INTERFACE parent 1:1 classid 1:30 htb rate 40mbit ceil \$DOWNLOAD_LIMIT prio 3

echo "QoS configured for WireGuard gateway"
echo "Upload limit: \$UPLOAD_LIMIT"
echo "Download limit: \$DOWNLOAD_LIMIT"
echo "WireGuard interface: \$WG_INTERFACE"
echo "External interface: \$EXTERNAL_INTERFACE"

# Display current configuration
echo "Current QoS configuration:"
tc -s qdisc show dev \$WG_INTERFACE
tc -s class show dev \$WG_INTERFACE
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-gateway-qos.sh << 'EOF'\n{$qosScript}\nEOF"
        );

        $this->sshService->executeCommand($connection, 'chmod +x /opt/wireguard-gateway-qos.sh');
        $this->sshService->executeCommand($connection, '/opt/wireguard-gateway-qos.sh');
    }

    /**
     * Configure load balancing for multiple uplinks
     */
    private function configureLoadBalancing(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $loadBalancingScript = <<<'SCRIPT'
#!/bin/bash
# WireGuard Gateway Load Balancing Configuration

# Detect available interfaces
INTERFACES=($(ip route | grep default | awk '{print $5}' | sort -u))
INTERFACE_COUNT=${#INTERFACES[@]}

if [ $INTERFACE_COUNT -le 1 ]; then
    echo "Only one default interface found, load balancing not needed"
    exit 0
fi

echo "Configuring load balancing for ${INTERFACE_COUNT} interfaces: ${INTERFACES[*]}"

# Install iproute2 advanced routing tools
apt install -y iproute2

# Create custom routing tables
for i in "${!INTERFACES[@]}"; do
    table_id=$((100 + i))
    interface="${INTERFACES[$i]}"
    
    echo "$table_id $interface" >> /etc/iproute2/rt_tables
    
    # Get interface gateway
    gateway=$(ip route | grep "default.*$interface" | awk '{print $3}' | head -1)
    
    if [ ! -z "$gateway" ]; then
        # Add route to custom table
        ip route add default via "$gateway" dev "$interface" table "$table_id"
        
        # Add rule for outgoing traffic
        ip rule add from all fwmark "$table_id" table "$table_id"
        
        echo "Configured table $table_id for interface $interface with gateway $gateway"
    fi
done

# Configure iptables for load balancing
# Mark packets for different routing tables
iptables -t mangle -N LOAD_BALANCE 2>/dev/null || true
iptables -t mangle -F LOAD_BALANCE

# Use statistic module for load balancing
for i in "${!INTERFACES[@]}"; do
    table_id=$((100 + i))
    probability=$(echo "scale=6; 1/${INTERFACE_COUNT}" | bc)
    
    if [ $i -eq $((INTERFACE_COUNT - 1)) ]; then
        # Last interface gets remaining probability
        iptables -t mangle -A LOAD_BALANCE -j MARK --set-mark $table_id
    else
        iptables -t mangle -A LOAD_BALANCE -m statistic --mode random --probability $probability -j MARK --set-mark $table_id
    fi
done

# Apply load balancing to WireGuard traffic
iptables -t mangle -A PREROUTING -i wg+ -j LOAD_BALANCE

echo "Load balancing configuration completed"
echo "Configured ${INTERFACE_COUNT} interfaces for load balancing"

# Save configuration
iptables-save > /etc/iptables/rules.v4
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /opt/wireguard-gateway-loadbalancing.sh << 'EOF'\n{$loadBalancingScript}\nEOF"
        );

        $this->sshService->executeCommand($connection, 'chmod +x /opt/wireguard-gateway-loadbalancing.sh');
        $this->sshService->executeCommand($connection, '/opt/wireguard-gateway-loadbalancing.sh');
    }

    /**
     * Add spoke to gateway routing
     */
    public function addSpokeToGateway(WireguardHub $gateway, WireguardSpoke $spoke): bool
    {
        try {
            $connection = $this->sshService->getConnection($gateway->sshHost);

            // Add iptables rule for the new spoke
            $spokeIP = $spoke->allocated_ip;

            $commands = [
                "iptables -A FORWARD -s {$spokeIP}/32 -i {$gateway->interface_name} -j ACCEPT",
                "iptables -A FORWARD -d {$spokeIP}/32 -o {$gateway->interface_name} -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT",
                'iptables-save > /etc/iptables/rules.v4',
            ];

            foreach ($commands as $command) {
                $this->sshService->executeCommand($connection, $command);
            }

            Log::info("Added spoke {$spoke->name} ({$spokeIP}) to gateway {$gateway->name}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to add spoke to gateway: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Remove spoke from gateway routing
     */
    public function removeSpokeFromGateway(WireguardHub $gateway, WireguardSpoke $spoke): bool
    {
        try {
            $connection = $this->sshService->getConnection($gateway->sshHost);

            // Remove iptables rules for the spoke
            $spokeIP = $spoke->allocated_ip;

            $commands = [
                "iptables -D FORWARD -s {$spokeIP}/32 -i {$gateway->interface_name} -j ACCEPT 2>/dev/null || true",
                "iptables -D FORWARD -d {$spokeIP}/32 -o {$gateway->interface_name} -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT 2>/dev/null || true",
                'iptables-save > /etc/iptables/rules.v4',
            ];

            foreach ($commands as $command) {
                $this->sshService->executeCommand($connection, $command);
            }

            Log::info("Removed spoke {$spoke->name} ({$spokeIP}) from gateway {$gateway->name}");

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to remove spoke from gateway: '.$e->getMessage());

            return false;
        }
    }
}
