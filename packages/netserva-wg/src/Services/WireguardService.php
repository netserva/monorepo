<?php

namespace NetServa\Wg\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use NetServa\Wg\Models\WireguardPeer;
use NetServa\Wg\Models\WireguardServer;

class WireguardService
{
    /**
     * Generate a new WireGuard key pair
     */
    public function generateKeyPair(): array
    {
        $privateKey = Process::run('wg genkey')->output();
        $privateKey = trim($privateKey);

        $publicKey = Process::input($privateKey)->run('wg pubkey')->output();
        $publicKey = trim($publicKey);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Create a new WireGuard server
     */
    public function createServer(array $data): WireguardServer
    {
        $keys = $this->generateKeyPair();

        return WireguardServer::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'network_cidr' => $data['network_cidr'],
            'server_ip' => $data['server_ip'],
            'listen_port' => $data['listen_port'] ?? 51820,
            'public_key' => $keys['public_key'],
            'private_key_encrypted' => app()->environment('testing')
                ? base64_encode($keys['private_key'])
                : Crypt::encrypt($keys['private_key']),
            'endpoint' => $data['endpoint'],
            'ssh_host_id' => $data['ssh_host_id'] ?? null,
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    /**
     * Create a new peer for a server
     */
    public function createPeer(WireguardServer $server, array $data): WireguardPeer
    {
        $keys = $this->generateKeyPair();
        $allocatedIp = $data['allocated_ip'] ?? $server->getNextAvailableIp();

        return WireguardPeer::create([
            'name' => $data['name'],
            'wireguard_server_id' => $server->id,
            'allocated_ip' => $allocatedIp,
            'allowed_ips' => $data['allowed_ips'] ?? ['0.0.0.0/0'],
            'public_key' => $keys['public_key'],
            'private_key_encrypted' => app()->environment('testing')
                ? base64_encode($keys['private_key'])
                : Crypt::encrypt($keys['private_key']),
            'status' => 'disconnected',
            'is_active' => true,
        ]);
    }

    /**
     * Generate WireGuard server configuration
     */
    public function generateServerConfig(WireguardServer $server): string
    {
        $privateKey = app()->environment('testing')
            ? base64_decode($server->private_key_encrypted)
            : Crypt::decrypt($server->private_key_encrypted);

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = {$server->network_cidr}\n";
        $config .= "ListenPort = {$server->listen_port}\n";
        $config .= "PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE\n";
        $config .= "PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -t nat -D POSTROUTING -o eth0 -j MASQUERADE\n";

        // Add peers
        foreach ($server->activePeers as $peer) {
            $config .= "\n[Peer]\n";
            $config .= "PublicKey = {$peer->public_key}\n";
            $config .= "AllowedIPs = {$peer->allocated_ip}/32\n";
        }

        return $config;
    }

    /**
     * Generate client configuration for a peer
     */
    public function generatePeerConfig(WireguardPeer $peer): string
    {
        $server = $peer->server;
        $privateKey = app()->environment('testing')
            ? base64_decode($peer->private_key_encrypted)
            : Crypt::decrypt($peer->private_key_encrypted);

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = {$peer->allocated_ip}/32\n";
        $config .= "DNS = 1.1.1.1, 8.8.8.8\n";

        $config .= "\n[Peer]\n";
        $config .= "PublicKey = {$server->public_key}\n";
        $config .= "Endpoint = {$server->endpoint}:{$server->listen_port}\n";
        $config .= 'AllowedIPs = '.implode(', ', $peer->allowed_ips)."\n";
        $config .= "PersistentKeepalive = 25\n";

        return $config;
    }

    /**
     * Deploy server configuration via SSH
     */
    public function deployServer(WireguardServer $server): bool
    {
        if (! $server->ssh_host_id) {
            throw new \Exception('SSH host not configured for server');
        }

        try {
            $config = $this->generateServerConfig($server);
            $configPath = "/etc/wireguard/{$server->name}.conf";

            // Create config file on remote server
            $commands = [
                'sudo mkdir -p /etc/wireguard',
                "echo '{$config}' | sudo tee {$configPath}",
                "sudo chmod 600 {$configPath}",
                "sudo systemctl enable wg-quick@{$server->name}",
                "sudo systemctl restart wg-quick@{$server->name}",
            ];

            foreach ($commands as $command) {
                $result = Process::run(['ssh', $server->ssh_host_id, $command]);
                if ($result->failed()) {
                    throw new \Exception("SSH command failed: {$command}");
                }
            }

            $server->update([
                'status' => 'active',
            ]);

            return true;

        } catch (\Exception $e) {
            $server->update([
                'status' => 'error',
            ]);

            throw $e;
        }
    }

    /**
     * Check WireGuard server status
     */
    public function checkServerStatus(WireguardServer $server): array
    {
        if (! $server->ssh_host_id) {
            return ['status' => 'unknown', 'peers' => []];
        }

        try {
            $result = Process::run(['ssh', $server->ssh_host_id, 'sudo wg show']);

            if ($result->failed()) {
                return ['status' => 'error', 'peers' => []];
            }

            $output = $result->output();
            $peers = $this->parseWgShow($output);

            // Update peer statuses
            $this->updatePeerStatuses($server, $peers);

            return [
                'status' => 'active',
                'peers' => $peers,
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'peers' => []];
        }
    }

    /**
     * Parse output from 'wg show' command
     */
    private function parseWgShow(string $output): array
    {
        $peers = [];
        $lines = explode("\n", $output);
        $currentPeer = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'peer:')) {
                $currentPeer = substr($line, 6);
                $peers[$currentPeer] = [
                    'public_key' => $currentPeer,
                    'endpoint' => null,
                    'allowed_ips' => [],
                    'latest_handshake' => null,
                    'transfer' => null,
                ];
            } elseif ($currentPeer && str_starts_with($line, 'endpoint:')) {
                $peers[$currentPeer]['endpoint'] = substr($line, 10);
            } elseif ($currentPeer && str_starts_with($line, 'allowed ips:')) {
                $peers[$currentPeer]['allowed_ips'] = explode(', ', substr($line, 13));
            } elseif ($currentPeer && str_starts_with($line, 'latest handshake:')) {
                $peers[$currentPeer]['latest_handshake'] = substr($line, 18);
            } elseif ($currentPeer && str_starts_with($line, 'transfer:')) {
                $peers[$currentPeer]['transfer'] = substr($line, 10);
            }
        }

        return $peers;
    }

    /**
     * Update peer statuses based on WireGuard output
     */
    private function updatePeerStatuses(WireguardServer $server, array $wgPeers): void
    {
        foreach ($server->peers as $peer) {
            if (isset($wgPeers[$peer->public_key])) {
                $wgPeer = $wgPeers[$peer->public_key];
                $lastHandshake = $wgPeer['latest_handshake']
                    ? now()->parse($wgPeer['latest_handshake'])
                    : null;

                $peer->update([
                    'status' => $lastHandshake && $lastHandshake->gt(now()->subMinutes(5))
                        ? 'connected'
                        : 'disconnected',
                    'last_handshake' => $lastHandshake,
                ]);
            } else {
                $peer->update([
                    'status' => 'disconnected',
                ]);
            }
        }
    }

    /**
     * Remove peer from server
     */
    public function removePeer(WireguardPeer $peer): bool
    {
        $server = $peer->server;

        // Remove from database
        $peer->delete();

        // Redeploy server if it's active
        if ($server->status === 'active') {
            return $this->deployServer($server);
        }

        return true;
    }

    /**
     * Get server statistics
     */
    public function getServerStats(WireguardServer $server): array
    {
        return [
            'total_peers' => $server->peers()->count(),
            'active_peers' => $server->activePeers()->count(),
            'connected_peers' => $server->peers()->where('status', 'connected')->count(),
            'status' => $server->status,
        ];
    }
}
