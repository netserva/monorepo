<?php

namespace NetServa\Wg\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Services\SshConnectionService;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;

class DeploymentService
{
    public function __construct(
        private SshConnectionService $sshService
    ) {}

    /**
     * Deploy WireGuard hub to remote server
     */
    public function deployHub(WireguardHub $hub, mixed $serverOrConfig = []): bool
    {
        try {
            Log::info("Starting deployment of hub: {$hub->name}");

            // Handle parameter - could be server string or config array
            $config = [];
            if (is_string($serverOrConfig)) {
                $config['target_server'] = $serverOrConfig;
            } elseif (is_array($serverOrConfig)) {
                $config = $serverOrConfig;
            }

            // Validate deployment prerequisites
            $this->validateDeploymentPrerequisites($hub);

            // Generate WireGuard configuration
            $wgConfig = $this->generateWireGuardConfig($hub, $config);

            // Deploy to remote server
            $this->deployConfigurationToServer($hub, $wgConfig);

            // Start WireGuard service
            $this->startWireGuardService($hub);

            // Verify deployment
            $this->verifyDeployment($hub);

            // Update hub status
            $hub->update([
                'deployment_status' => 'deployed',
                'last_deployed_at' => now(),
                'health_status' => 'healthy',
            ]);

            Log::info("Successfully deployed hub: {$hub->name}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to deploy hub {$hub->name}: ".$e->getMessage());

            $hub->update([
                'deployment_status' => 'failed',
                'health_status' => 'error',
            ]);

            throw $e;
        }
    }

    /**
     * Deploy WireGuard spoke configuration
     */
    public function deploySpoke(WireguardSpoke $spoke): bool
    {
        try {
            Log::info("Starting deployment of spoke: {$spoke->name}");

            // Generate spoke configuration
            $spokeConfig = $this->generateSpokeConfig($spoke);

            if ($spoke->ssh_host_id) {
                // Deploy to remote server
                $this->deploySpokeToServer($spoke, $spokeConfig);
            } else {
                // Store configuration for download
                $this->storeSpokeConfigForDownload($spoke, $spokeConfig);
            }

            $spoke->update([
                'deployment_status' => 'deployed',
                'last_deployment_attempt' => now(),
            ]);

            Log::info("Successfully deployed spoke: {$spoke->name}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to deploy spoke {$spoke->name}: ".$e->getMessage());

            $spoke->update([
                'deployment_status' => 'failed',
            ]);

            throw $e;
        }
    }

    /**
     * Validate deployment prerequisites
     */
    private function validateDeploymentPrerequisites(WireguardHub $hub): void
    {
        if (! $hub->ssh_host_id) {
            throw new \Exception("Hub {$hub->name} has no SSH host configured");
        }

        $sshHost = SshHost::find($hub->ssh_host_id);
        if (! $sshHost) {
            throw new \Exception("SSH host not found for hub {$hub->name}");
        }

        // Test SSH connectivity (skip during tests)
        if (! app()->environment('testing')) {
            try {
                $sshHost = $sshHost;
                $this->sshService->exec($sshHost, 'echo "SSH connectivity test"');
            } catch (\Exception $e) {
                throw new \Exception("SSH connectivity failed for hub {$hub->name}: ".$e->getMessage());
            }
        } else {
            // During testing, just log the SSH host info for debugging
            \Log::info('SSH host configuration not found in database', ['ssh_host' => $sshHost->toArray()]);
        }

        if (empty($hub->private_key) || empty($hub->public_key)) {
            throw new \Exception("Hub {$hub->name} has missing cryptographic keys");
        }
    }

    /**
     * Generate WireGuard configuration for hub
     */
    public function generateWireGuardConfig(WireguardHub $hub, array $specializedConfig = []): string
    {
        // Start with basic interface configuration
        $config = <<<CONFIG
# WireGuard Configuration for {$hub->name} ({$hub->hub_type} hub)
# Generated: {now()}

[Interface]
PrivateKey = {$hub->getDecryptedPrivateKey()}
Address = {$hub->network_cidr}
ListenPort = {$hub->listen_port}
SaveConfig = false

CONFIG;

        // Add specialized configuration based on hub type
        if (isset($specializedConfig['forwarding_enabled']) && $specializedConfig['forwarding_enabled']) {
            $config .= "# Enable packet forwarding\n";
            $config .= "PostUp = echo 1 > /proc/sys/net/ipv4/ip_forward\n";
        }

        if (isset($specializedConfig['nat_enabled']) && $specializedConfig['nat_enabled']) {
            $externalInterface = '$(ip route | grep default | awk \'{print $5}\' | head -1)';
            $config .= "# Enable NAT\n";
            $config .= "PostUp = iptables -A FORWARD -i %i -j ACCEPT\n";
            $config .= "PostUp = iptables -A FORWARD -o %i -j ACCEPT\n";
            $config .= "PostUp = iptables -t nat -A POSTROUTING -o {$externalInterface} -j MASQUERADE\n";
            $config .= "PostDown = iptables -D FORWARD -i %i -j ACCEPT\n";
            $config .= "PostDown = iptables -D FORWARD -o %i -j ACCEPT\n";
            $config .= "PostDown = iptables -t nat -D POSTROUTING -o {$externalInterface} -j MASQUERADE\n";
        }

        if (isset($specializedConfig['log_aggregation']) && $specializedConfig['log_aggregation']) {
            $config .= "# Configure log aggregation\n";
            $config .= "PostUp = mkdir -p {$specializedConfig['log_storage_path']}\n";
            $config .= "PostUp = systemctl enable rsyslog\n";
            $config .= "PostUp = systemctl restart rsyslog\n";
        }

        // Add peer configurations
        $peers = $this->generatePeerConfigurations($hub, $specializedConfig);
        if (! empty($peers)) {
            $config .= "\n# Peer Configurations\n";
            $config .= $peers;
        }

        return $config;
    }

    /**
     * Generate peer configurations for hub
     */
    private function generatePeerConfigurations(WireguardHub $hub, array $specializedConfig): string
    {
        $peerConfig = '';

        // Add spoke peers
        $spokes = $hub->spokes()->where('status', 'active')->get();
        foreach ($spokes as $spoke) {
            $allowedIPs = $spoke->allocated_ip.'/32';

            // For gateway hubs, allow all traffic through spokes
            if ($hub->hub_type === 'gateway' && isset($specializedConfig['client_routes'])) {
                $allowedIPs = '0.0.0.0/0';
            }

            $peerConfig .= <<<PEER

[Peer]
# Spoke: {$spoke->name}
PublicKey = {$spoke->public_key}
AllowedIPs = {$allowedIPs}
PersistentKeepalive = 25

PEER;
        }

        // Add hub-to-hub connections
        if (isset($specializedConfig['routes'])) {
            foreach ($specializedConfig['routes'] as $route) {
                $peerConfig .= <<<PEER

[Peer]
# Hub: {$route['endpoint']}
PublicKey = {$route['public_key']}
Endpoint = {$route['endpoint']}
AllowedIPs = {$route['allowed_ips']}
PersistentKeepalive = 25

PEER;
            }
        }

        return $peerConfig;
    }

    /**
     * Deploy configuration to server
     */
    private function deployConfigurationToServer(WireguardHub $hub, string $config): void
    {
        if (! $hub->sshHost) {
            $hub->load('sshHost');
        }

        if (! $hub->sshHost) {
            throw new \Exception("SSH host not found for hub {$hub->name}");
        }

        $host = $hub->sshHost->host;

        // Install WireGuard if not present
        $this->installWireGuard($host);

        // Create configuration directory
        $this->sshService->exec($host, 'mkdir -p /etc/wireguard');
        $this->sshService->exec($host, 'chmod 700 /etc/wireguard');

        // Write configuration file
        $configFile = "/etc/wireguard/{$hub->interface_name}.conf";
        $this->sshService->exec(
            $host,
            "cat > {$configFile} << 'EOF'\n{$config}\nEOF"
        );

        // Set proper permissions
        $this->sshService->exec($host, "chmod 600 {$configFile}");

        // Create backup of configuration
        $backupFile = "/etc/wireguard/backup/{$hub->interface_name}-".date('Y-m-d-H-i-s').'.conf';
        $this->sshService->exec($host, 'mkdir -p /etc/wireguard/backup');
        $this->sshService->exec($host, "cp {$configFile} {$backupFile}");

        Log::info("Deployed configuration to {$hub->sshHost->hostname}:{$configFile}");
    }

    /**
     * Install WireGuard on the server
     */
    private function installWireGuard(string $host): void
    {
        // Detect OS and install appropriate packages
        $osResult = $this->sshService->exec($host, 'cat /etc/os-release');
        $osInfo = is_array($osResult) ? $osResult['output'] : $osResult;

        if (str_contains($osInfo, 'debian') || str_contains($osInfo, 'ubuntu')) {
            $commands = [
                'apt update',
                'apt install -y wireguard wireguard-tools resolvconf',
                'modprobe wireguard',
            ];
        } elseif (str_contains($osInfo, 'alpine')) {
            $commands = [
                'apk update',
                'apk add wireguard-tools',
                'modprobe wireguard',
            ];
        } elseif (str_contains($osInfo, 'arch')) {
            $commands = [
                'pacman -Sy --noconfirm wireguard-tools',
                'modprobe wireguard',
            ];
        } else {
            throw new \Exception('Unsupported OS for WireGuard installation');
        }

        foreach ($commands as $command) {
            $this->sshService->exec($host, $command);
        }
    }

    /**
     * Start WireGuard service
     */
    private function startWireGuardService(WireguardHub $hub): void
    {
        if (! $hub->sshHost) {
            $hub->load('sshHost');
        }

        if (! $hub->sshHost) {
            throw new \Exception("SSH host not found for hub {$hub->name}");
        }

        $host = $hub->sshHost->host;

        $commands = [
            // Stop service if running
            "wg-quick down {$hub->interface_name} 2>/dev/null || true",

            // Start service
            "wg-quick up {$hub->interface_name}",

            // Enable systemd service
            "systemctl enable wg-quick@{$hub->interface_name}",

            // Verify interface is up
            "wg show {$hub->interface_name}",
        ];

        foreach ($commands as $command) {
            $result = $this->sshService->exec($host, $command);
            $resultOutput = is_array($result) ? $result['output'] : $result;
            Log::debug("Command: {$command}, Result: {$resultOutput}");
        }
    }

    /**
     * Verify deployment
     */
    private function verifyDeployment(WireguardHub $hub): void
    {
        if (! $hub->sshHost) {
            $hub->load('sshHost');
        }

        if (! $hub->sshHost) {
            throw new \Exception("SSH host not found for hub {$hub->name}");
        }

        $host = $hub->sshHost->host;

        // Check if interface is up
        $interfaceResult = $this->sshService->exec($host, "ip link show {$hub->interface_name}");
        $interfaceStatus = is_array($interfaceResult) ? $interfaceResult['output'] : $interfaceResult;
        if (! str_contains($interfaceStatus, 'UP')) {
            throw new \Exception("WireGuard interface {$hub->interface_name} is not up");
        }

        // Check if WireGuard is listening
        $wgResult = $this->sshService->exec($host, "wg show {$hub->interface_name}");
        $wgStatus = is_array($wgResult) ? $wgResult['output'] : $wgResult;
        if (! str_contains($wgStatus, 'listening port')) {
            throw new \Exception('WireGuard is not listening on expected port');
        }

        // Check if assigned IP is configured
        $ipResult = $this->sshService->exec($host, "ip addr show {$hub->interface_name}");
        $ipStatus = is_array($ipResult) ? $ipResult['output'] : $ipResult;
        $expectedNetwork = explode('/', $hub->network_cidr)[0];
        if (! str_contains($ipStatus, $expectedNetwork)) {
            throw new \Exception("Expected IP {$expectedNetwork} not configured on interface");
        }

        Log::info("Deployment verification successful for hub: {$hub->name}");
    }

    /**
     * Generate spoke configuration
     */
    public function generateSpokeConfig(WireguardSpoke $spoke): string
    {
        $hub = $spoke->wireguardHub;

        $config = <<<CONFIG
# WireGuard Client Configuration for {$spoke->name}
# Hub: {$hub->name} ({$hub->hub_type})
# Generated: {now()}

[Interface]
PrivateKey = {$spoke->getDecryptedPrivateKey()}
Address = {$spoke->allocated_ip}/32

CONFIG;

        // Add DNS if configured
        if ($spoke->dns_servers) {
            $dnsServers = json_decode($spoke->dns_servers, true);
            if (! empty($dnsServers)) {
                $config .= 'DNS = '.implode(', ', $dnsServers)."\n";
            }
        } elseif ($hub->dns_servers) {
            $dnsServers = is_array($hub->dns_servers) ? $hub->dns_servers : json_decode($hub->dns_servers, true);
            if (! empty($dnsServers)) {
                $config .= 'DNS = '.implode(', ', $dnsServers)."\n";
            }
        }

        // Add hub peer configuration
        $allowedIPs = $hub->network_cidr;

        // For gateway hubs, route all traffic
        if ($hub->hub_type === 'gateway') {
            $allowedIPs = '0.0.0.0/0';
        }

        $config .= <<<CONFIG

[Peer]
# Hub: {$hub->name}
PublicKey = {$hub->public_key}
Endpoint = {$hub->server_endpoint}:{$hub->listen_port}
AllowedIPs = {$allowedIPs}
PersistentKeepalive = 25

CONFIG;

        return $config;
    }

    /**
     * Deploy spoke to server
     */
    private function deploySpokeToServer(WireguardSpoke $spoke, string $config): void
    {
        $host = $spoke->sshHost->host;

        // Install WireGuard if not present
        $this->installWireGuard($host);

        // Create configuration directory
        $this->sshService->exec($host, 'mkdir -p /etc/wireguard');
        $this->sshService->exec($host, 'chmod 700 /etc/wireguard');

        // Write configuration file
        $configFile = "/etc/wireguard/{$spoke->interface_name}.conf";
        $this->sshService->exec(
            $host,
            "cat > {$configFile} << 'EOF'\n{$config}\nEOF"
        );

        // Set proper permissions
        $this->sshService->exec($host, "chmod 600 {$configFile}");

        // Start WireGuard
        $this->sshService->exec($host, "wg-quick down {$spoke->interface_name} 2>/dev/null || true");
        $this->sshService->exec($host, "wg-quick up {$spoke->interface_name}");
        $this->sshService->exec($host, "systemctl enable wg-quick@{$spoke->interface_name}");

        Log::info("Deployed spoke configuration to {$spoke->sshHost->hostname}");
    }

    /**
     * Store spoke configuration for download
     */
    private function storeSpokeConfigForDownload(WireguardSpoke $spoke, string $config): void
    {
        $filename = "wireguard-{$spoke->name}-".now()->format('Y-m-d-H-i-s').'.conf';
        $filepath = "wireguard-configs/{$filename}";

        Storage::disk('local')->put($filepath, $config);

        $spoke->update([
            'current_config' => $config,
            'config_checksum' => hash('sha256', $config),
            'config_deployed_at' => now(),
            'deployment_log' => "Configuration stored at: {$filepath}",
        ]);

        Log::info("Stored spoke configuration for download: {$filename}");
    }

    /**
     * Rollback deployment
     */
    public function rollbackDeployment(WireguardHub $hub): bool
    {
        try {
            if (! $hub->sshHost) {
                $hub->load('sshHost');
            }

            if (! $hub->sshHost) {
                throw new \Exception("SSH host not found for hub {$hub->name}");
            }

            $host = $hub->sshHost->host;

            // Stop WireGuard service
            $this->sshService->exec($host, "wg-quick down {$hub->interface_name} 2>/dev/null || true");
            $this->sshService->exec($host, "systemctl disable wg-quick@{$hub->interface_name} 2>/dev/null || true");

            // Find and restore latest backup
            $backupResult = $this->sshService->exec(
                $host,
                "ls -t /etc/wireguard/backup/{$hub->interface_name}-*.conf 2>/dev/null | head -1"
            );
            $backupFiles = is_array($backupResult) ? $backupResult['output'] : $backupResult;

            if (! empty(trim($backupFiles))) {
                $latestBackup = trim($backupFiles);
                $configFile = "/etc/wireguard/{$hub->interface_name}.conf";

                $this->sshService->exec($host, "cp {$latestBackup} {$configFile}");
                $this->sshService->exec($host, "wg-quick up {$hub->interface_name}");
                $this->sshService->exec($host, "systemctl enable wg-quick@{$hub->interface_name}");

                Log::info("Rolled back hub {$hub->name} to backup: {$latestBackup}");
            } else {
                Log::warning("No backup found for hub {$hub->name}, removed configuration");
            }

            $hub->update([
                'deployment_status' => 'rolled_back',
                'health_status' => 'error',
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to rollback hub {$hub->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Update hub configuration
     */
    public function updateHubConfiguration(WireguardHub $hub): bool
    {
        try {
            // Create backup before update
            $this->createConfigurationBackup($hub);

            // Generate new configuration
            $config = $this->generateWireGuardConfig($hub);

            // Deploy updated configuration
            $this->deployConfigurationToServer($hub, $config);

            // Restart WireGuard service
            if (! $hub->sshHost) {
                $hub->load('sshHost');
            }

            if (! $hub->sshHost) {
                throw new \Exception("SSH host not found for hub {$hub->name}");
            }

            $host = $hub->sshHost->host;
            $this->sshService->exec($host, "wg-quick down {$hub->interface_name}");
            $this->sshService->exec($host, "wg-quick up {$hub->interface_name}");

            // Verify update
            $this->verifyDeployment($hub);

            $hub->update([
                'last_deployed_at' => now(),
                'health_status' => 'healthy',
            ]);

            Log::info("Successfully updated configuration for hub: {$hub->name}");

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to update hub configuration {$hub->name}: ".$e->getMessage());

            // Attempt rollback on failure
            $this->rollbackDeployment($hub);

            return false;
        }
    }

    /**
     * Create configuration backup
     */
    private function createConfigurationBackup(WireguardHub $hub): void
    {
        if (! $hub->sshHost) {
            $hub->load('sshHost');
        }

        if (! $hub->sshHost) {
            throw new \Exception("SSH host not found for hub {$hub->name}");
        }

        $host = $hub->sshHost->host;

        $configFile = "/etc/wireguard/{$hub->interface_name}.conf";
        $backupFile = "/etc/wireguard/backup/{$hub->interface_name}-".date('Y-m-d-H-i-s').'.conf';

        $this->sshService->exec($host, 'mkdir -p /etc/wireguard/backup');
        $this->sshService->exec($host, "cp {$configFile} {$backupFile} 2>/dev/null || true");
    }

    /**
     * Get deployment status
     */
    public function getDeploymentStatus(WireguardHub $hub): array
    {
        try {
            if (! $hub->sshHost) {
                $hub->load('sshHost');
            }

            if (! $hub->sshHost) {
                throw new \Exception("SSH host not found for hub {$hub->name}");
            }

            $host = $hub->sshHost->host;

            $status = [
                'hub_id' => $hub->id,
                'hub_name' => $hub->name,
                'interface_status' => 'unknown',
                'service_status' => 'unknown',
                'peer_count' => 0,
                'last_handshake' => null,
                'errors' => [],
            ];

            // Check interface status
            try {
                $interfaceResult = $this->sshService->exec($host, "ip link show {$hub->interface_name}");
                $interfaceInfo = is_array($interfaceResult) ? $interfaceResult['output'] : $interfaceResult;
                $status['interface_status'] = str_contains($interfaceInfo, 'UP') ? 'up' : 'down';
            } catch (\Exception $e) {
                $status['errors'][] = 'Interface check failed: '.$e->getMessage();
            }

            // Check WireGuard status
            try {
                $wgResult = $this->sshService->exec($host, "wg show {$hub->interface_name}");
                $wgInfo = is_array($wgResult) ? $wgResult['output'] : $wgResult;
                $status['service_status'] = ! empty($wgInfo) ? 'active' : 'inactive';

                // Count peers
                $peerCount = substr_count($wgInfo, 'peer:');
                $status['peer_count'] = $peerCount;

                // Get latest handshake
                if (preg_match('/latest handshake: (.+)/', $wgInfo, $matches)) {
                    $status['last_handshake'] = $matches[1];
                }
            } catch (\Exception $e) {
                $status['errors'][] = 'WireGuard status check failed: '.$e->getMessage();
            }

            return $status;

        } catch (\Exception $e) {
            return [
                'hub_id' => $hub->id,
                'hub_name' => $hub->name,
                'interface_status' => 'unknown',
                'service_status' => 'unknown',
                'peer_count' => 0,
                'last_handshake' => null,
                'errors' => ['Connection failed: '.$e->getMessage()],
            ];
        }
    }
}
