<?php

namespace NetServa\Wg\Services\HubTypes;

use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Services\DeploymentService;

class WorkstationHubService
{
    public function __construct(
        private SshConnectionService $sshService,
        private DeploymentService $deploymentService
    ) {}

    /**
     * Configure workstation hub as central management point
     */
    public function configureAsManagementHub(WireguardHub $hub): bool
    {
        try {
            Log::info("Configuring workstation hub as management point: {$hub->name}");

            // Install management tools on the workstation
            $this->installManagementTools($hub);

            // Configure hub discovery and monitoring
            $this->configureHubDiscovery($hub);

            // Setup central configuration management
            $this->setupCentralConfiguration($hub);

            // Configure secure tunnels to other hubs
            $this->configureMgmtTunnels($hub);

            $hub->update([
                'configuration' => json_encode([
                    'management_role' => true,
                    'hub_discovery' => true,
                    'central_config' => true,
                    'secure_tunnels' => true,
                    'configured_at' => now(),
                ]),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to configure workstation hub {$hub->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Install WireGuard management tools on workstation
     */
    private function installManagementTools(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $commands = [
            // Install WireGuard tools
            'apt update && apt install -y wireguard wireguard-tools',

            // Install network monitoring tools
            'apt install -y iftop nethogs nmap wireshark-common',

            // Install configuration management tools
            'apt install -y rsync git jq curl',

            // Create management directories
            'mkdir -p /etc/wireguard/management/{configs,keys,logs,scripts}',
            'chmod 700 /etc/wireguard/management',

            // Install management scripts
            'cat > /etc/wireguard/management/scripts/hub-status.sh << "EOF"
#!/bin/bash
# Check status of all managed hubs
echo "=== WireGuard Hub Status ==="
wg show all
echo ""
echo "=== Network Connectivity ==="
for hub in $(wg show interfaces); do
    echo "Hub: $hub"
    wg show $hub endpoints
    echo ""
done
EOF',
            'chmod +x /etc/wireguard/management/scripts/hub-status.sh',
        ];

        foreach ($commands as $command) {
            $this->sshService->executeCommand($connection, $command);
        }
    }

    /**
     * Configure automatic discovery of other hubs
     */
    private function configureHubDiscovery(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Create hub discovery script
        $discoveryScript = <<<'SCRIPT'
#!/bin/bash
# Automated hub discovery and health monitoring

MANAGEMENT_DIR="/etc/wireguard/management"
DISCOVERY_LOG="$MANAGEMENT_DIR/logs/discovery.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$DISCOVERY_LOG"
}

# Function to check hub connectivity
check_hub_connectivity() {
    local hub_name="$1"
    local hub_endpoint="$2"
    local hub_port="$3"
    
    if nc -z -w5 "$hub_endpoint" "$hub_port" 2>/dev/null; then
        log_message "Hub $hub_name is reachable at $hub_endpoint:$hub_port"
        return 0
    else
        log_message "Hub $hub_name is NOT reachable at $hub_endpoint:$hub_port"
        return 1
    fi
}

# Function to discover active interfaces
discover_interfaces() {
    log_message "Starting hub discovery..."
    
    wg show interfaces | while read interface; do
        if [[ -n "$interface" ]]; then
            log_message "Found WireGuard interface: $interface"
            
            # Get interface details
            peer_count=$(wg show "$interface" peers | wc -l)
            endpoint=$(wg show "$interface" endpoints | head -1 | awk '{print $2}')
            
            log_message "Interface $interface has $peer_count peers"
            if [[ -n "$endpoint" ]]; then
                log_message "Primary endpoint: $endpoint"
            fi
        fi
    done
}

# Main discovery routine
main() {
    # Rotate logs
    if [[ -f "$DISCOVERY_LOG" ]] && [[ $(stat -f%z "$DISCOVERY_LOG" 2>/dev/null || stat -c%s "$DISCOVERY_LOG") -gt 1048576 ]]; then
        mv "$DISCOVERY_LOG" "$DISCOVERY_LOG.old"
    fi
    
    log_message "=== Starting Hub Discovery ==="
    discover_interfaces
    log_message "=== Discovery Complete ==="
}

main "$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/management/scripts/hub-discovery.sh << 'EOF'\n{$discoveryScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            'chmod +x /etc/wireguard/management/scripts/hub-discovery.sh'
        );

        // Setup cron job for regular discovery
        $this->sshService->executeCommand(
            $connection,
            'echo "*/5 * * * * root /etc/wireguard/management/scripts/hub-discovery.sh" > /etc/cron.d/wireguard-discovery'
        );
    }

    /**
     * Setup central configuration management
     */
    private function setupCentralConfiguration(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        // Create configuration management structure
        $commands = [
            'mkdir -p /etc/wireguard/management/configs/{hubs,spokes,templates}',
            'mkdir -p /etc/wireguard/management/keys/{hubs,spokes,backup}',
            'mkdir -p /etc/wireguard/management/logs/{deployment,monitoring,errors}',
        ];

        foreach ($commands as $command) {
            $this->sshService->executeCommand($connection, $command);
        }

        // Create configuration template for different hub types
        $this->createConfigurationTemplates($hub);

        // Setup configuration synchronization
        $this->setupConfigSynchronization($hub);
    }

    /**
     * Create configuration templates for different hub types
     */
    private function createConfigurationTemplates(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $templates = [
            'logging-hub.conf.template' => $this->getLoggingHubTemplate(),
            'gateway-hub.conf.template' => $this->getGatewayHubTemplate(),
            'customer-hub.conf.template' => $this->getCustomerHubTemplate(),
            'spoke.conf.template' => $this->getSpokeTemplate(),
        ];

        foreach ($templates as $filename => $content) {
            $this->sshService->executeCommand(
                $connection,
                "cat > /etc/wireguard/management/configs/templates/{$filename} << 'EOF'\n{$content}\nEOF"
            );
        }
    }

    /**
     * Configure secure management tunnels to other hubs
     */
    private function configureMgmtTunnels(WireguardHub $hub): void
    {
        $otherHubs = WireguardHub::where('id', '!=', $hub->id)
            ->where('status', 'active')
            ->get();

        $connection = $this->sshService->getConnection($hub->sshHost->host);

        foreach ($otherHubs as $otherHub) {
            // Create management tunnel configuration
            $tunnelConfig = $this->generateMgmtTunnelConfig($hub, $otherHub);

            $configFile = "/etc/wireguard/mgmt-{$otherHub->name}.conf";

            $this->sshService->executeCommand(
                $connection,
                "cat > {$configFile} << 'EOF'\n{$tunnelConfig}\nEOF"
            );

            // Set proper permissions
            $this->sshService->executeCommand($connection, "chmod 600 {$configFile}");
        }
    }

    /**
     * Generate management tunnel configuration
     */
    private function generateMgmtTunnelConfig(WireguardHub $workstation, WireguardHub $targetHub): string
    {
        $mgmtIP = $this->allocateManagementIP($workstation, $targetHub);

        return <<<CONFIG
# Management tunnel from {$workstation->name} to {$targetHub->name}
[Interface]
PrivateKey = {$workstation->private_key}
Address = {$mgmtIP}/30
Table = off

[Peer]
PublicKey = {$targetHub->public_key}
Endpoint = {$targetHub->endpoint}
AllowedIPs = {$this->getTargetMgmtNetwork($targetHub)}
PersistentKeepalive = 25

# Management-specific routes
PostUp = ip route add {$targetHub->network_cidr} dev %i table mgmt-{$targetHub->id}
PostDown = ip route del {$targetHub->network_cidr} dev %i table mgmt-{$targetHub->id}
CONFIG;
    }

    /**
     * Setup configuration synchronization system
     */
    private function setupConfigSynchronization(WireguardHub $hub): void
    {
        $connection = $this->sshService->getConnection($hub->sshHost->host);

        $syncScript = <<<'SCRIPT'
#!/bin/bash
# Configuration synchronization script

MANAGEMENT_DIR="/etc/wireguard/management"
SYNC_LOG="$MANAGEMENT_DIR/logs/config-sync.log"

log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$SYNC_LOG"
}

# Sync configurations to remote hubs
sync_to_hub() {
    local hub_name="$1"
    local hub_endpoint="$2"
    local config_file="$3"
    
    log_message "Syncing configuration to hub: $hub_name"
    
    if scp -o StrictHostKeyChecking=no -o ConnectTimeout=10 \
        "$config_file" "root@$hub_endpoint:/etc/wireguard/"; then
        log_message "Successfully synced config to $hub_name"
        
        # Restart WireGuard on remote hub
        ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 \
            "root@$hub_endpoint" "systemctl restart wg-quick@$(basename $config_file .conf)"
            
        log_message "Restarted WireGuard on $hub_name"
    else
        log_message "Failed to sync config to $hub_name"
    fi
}

# Main synchronization routine
main() {
    log_message "=== Starting Configuration Sync ==="
    
    # Sync all hub configurations
    for config in "$MANAGEMENT_DIR/configs/hubs"/*.conf; do
        if [[ -f "$config" ]]; then
            hub_name=$(basename "$config" .conf)
            # Extract endpoint from config or use default
            sync_to_hub "$hub_name" "hub-$hub_name" "$config"
        fi
    done
    
    log_message "=== Configuration Sync Complete ==="
}

main "$@"
SCRIPT;

        $this->sshService->executeCommand(
            $connection,
            "cat > /etc/wireguard/management/scripts/config-sync.sh << 'EOF'\n{$syncScript}\nEOF"
        );

        $this->sshService->executeCommand(
            $connection,
            'chmod +x /etc/wireguard/management/scripts/config-sync.sh'
        );
    }

    /**
     * Allocate management IP for tunnel
     */
    private function allocateManagementIP(WireguardHub $workstation, WireguardHub $targetHub): string
    {
        // Use a /30 network for point-to-point management tunnels
        // Start from 192.168.200.0/24 for management
        $baseOctet = 200 + ($targetHub->id % 50);

        return "192.168.{$baseOctet}.1";
    }

    /**
     * Get target management network
     */
    private function getTargetMgmtNetwork(WireguardHub $targetHub): string
    {
        $baseOctet = 200 + ($targetHub->id % 50);

        return "192.168.{$baseOctet}.2/32";
    }

    /**
     * Get configuration templates for different hub types
     */
    private function getLoggingHubTemplate(): string
    {
        return <<<'TEMPLATE'
# Central Logging Hub Configuration Template
[Interface]
PrivateKey = {{PRIVATE_KEY}}
Address = {{HUB_ADDRESS}}
ListenPort = {{LISTEN_PORT}}
SaveConfig = false

# Enable packet forwarding for log collection
PostUp = echo 1 > /proc/sys/net/ipv4/ip_forward
PostUp = sysctl -w net.core.rmem_max=26214400
PostUp = sysctl -w net.core.rmem_default=26214400

# Configure rsyslog for centralized logging
PostUp = systemctl enable rsyslog
PostUp = systemctl restart rsyslog

# Log management
PostUp = mkdir -p /var/log/wireguard-central
PostUp = chmod 755 /var/log/wireguard-central

{{PEER_CONFIGURATIONS}}
TEMPLATE;
    }

    private function getGatewayHubTemplate(): string
    {
        return <<<'TEMPLATE'
# Gateway Hub Configuration Template
[Interface]
PrivateKey = {{PRIVATE_KEY}}
Address = {{HUB_ADDRESS}}
ListenPort = {{LISTEN_PORT}}
SaveConfig = false

# Enable NAT and forwarding
PostUp = echo 1 > /proc/sys/net/ipv4/ip_forward
PostUp = iptables -A FORWARD -i %i -j ACCEPT
PostUp = iptables -A FORWARD -o %i -j ACCEPT
PostUp = iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE

# Gateway-specific firewall rules
PostUp = iptables -A INPUT -i %i -p tcp --dport 22 -j DROP
PostUp = iptables -A INPUT -i %i -p tcp --dport 3389 -j DROP

PostDown = iptables -D FORWARD -i %i -j ACCEPT
PostDown = iptables -D FORWARD -o %i -j ACCEPT
PostDown = iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE

{{PEER_CONFIGURATIONS}}
TEMPLATE;
    }

    private function getCustomerHubTemplate(): string
    {
        return <<<'TEMPLATE'
# Customer Hub Configuration Template (Isolated)
[Interface]
PrivateKey = {{PRIVATE_KEY}}
Address = {{HUB_ADDRESS}}
ListenPort = {{LISTEN_PORT}}
SaveConfig = false

# Customer isolation rules
PostUp = echo 1 > /proc/sys/net/ipv4/ip_forward
PostUp = iptables -A FORWARD -i %i -o %i -j ACCEPT
PostUp = iptables -A FORWARD -i %i ! -o %i -j DROP
PostUp = iptables -A FORWARD ! -i %i -o %i -j DROP

# Block inter-customer communication
{{ISOLATION_RULES}}

PostDown = iptables -D FORWARD -i %i -o %i -j ACCEPT
PostDown = iptables -D FORWARD -i %i ! -o %i -j DROP
PostDown = iptables -D FORWARD ! -i %i -o %i -j DROP

{{PEER_CONFIGURATIONS}}
TEMPLATE;
    }

    private function getSpokeTemplate(): string
    {
        return <<<'TEMPLATE'
# Spoke Configuration Template
[Interface]
PrivateKey = {{PRIVATE_KEY}}
Address = {{SPOKE_ADDRESS}}

# DNS configuration
DNS = {{DNS_SERVERS}}

{{HUB_CONFIGURATIONS}}
TEMPLATE;
    }
}
