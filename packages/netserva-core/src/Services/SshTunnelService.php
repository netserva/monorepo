<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Models\SshHost;

/**
 * SSH Tunnel Service
 *
 * Manages SSH tunnels for secure access to remote services.
 * Uses background SSH processes for port forwarding to PowerDNS, databases, etc.
 *
 * Architecture:
 * - Dynamic port allocation (10000-19999 range)
 * - Process-based tunnels (ssh -L command)
 * - Automatic tunnel reuse and health checking
 * - Graceful cleanup on service shutdown
 */
class SshTunnelService
{
    /**
     * Active SSH tunnel processes
     *
     * Format: ['{host}:{service}' => ['pid' => int, 'local_port' => int, 'created_at' => timestamp]]
     *
     * @var array
     */
    protected array $activeTunnels = [];

    /**
     * Remote connection service for SSH operations
     */
    protected RemoteConnectionService $remoteConnection;

    /**
     * Base directory for tunnel PID files
     */
    protected string $pidDir;

    public function __construct(RemoteConnectionService $remoteConnection)
    {
        $this->remoteConnection = $remoteConnection;
        $this->pidDir = storage_path('app/ssh-tunnels');

        // Ensure PID directory exists
        if (! is_dir($this->pidDir)) {
            mkdir($this->pidDir, 0755, true);
        }

        // Load existing tunnels from PID files
        $this->loadExistingTunnels();
    }

    /**
     * Ensure SSH tunnel is active
     *
     * @param  string  $sshHost  SSH host to tunnel through
     * @param  string  $service  Service name (e.g., 'powerdns', 'mysql')
     * @param  int  $remotePort  Remote port to tunnel
     * @param  string  $remoteHost  Remote host (default: localhost)
     * @return array Tunnel establishment result
     */
    public function ensureTunnel(
        string $sshHost,
        string $service,
        int $remotePort,
        string $remoteHost = 'localhost'
    ): array {
        $tunnelKey = "{$sshHost}:{$service}";

        // Check if tunnel already exists and is active
        if (isset($this->activeTunnels[$tunnelKey])) {
            $tunnel = $this->activeTunnels[$tunnelKey];

            if ($this->isTunnelActive($sshHost, $tunnel['local_port'])) {
                Log::debug('SSH tunnel already active', [
                    'ssh_host' => $sshHost,
                    'service' => $service,
                    'local_port' => $tunnel['local_port'],
                ]);

                return [
                    'success' => true,
                    'local_port' => $tunnel['local_port'],
                    'remote_port' => $remotePort,
                    'endpoint' => "http://localhost:{$tunnel['local_port']}",
                    'created' => false,
                    'message' => 'Using existing tunnel',
                ];
            }

            // Tunnel is dead, clean it up
            $this->cleanupTunnel($tunnelKey);
        }

        // Create new tunnel
        return $this->createTunnel($sshHost, $service, $remotePort, $remoteHost);
    }

    /**
     * Create new SSH tunnel
     *
     * @param  string  $sshHost  SSH host identifier
     * @param  string  $service  Service name
     * @param  int  $remotePort  Remote port
     * @param  string  $remoteHost  Remote host
     * @return array Creation result
     */
    protected function createTunnel(
        string $sshHost,
        string $service,
        int $remotePort,
        string $remoteHost = 'localhost'
    ): array {
        try {
            // Get SSH host configuration
            $sshHostModel = SshHost::where('host', $sshHost)->first();

            if (! $sshHostModel) {
                throw new Exception("SSH host not found: {$sshHost}");
            }

            // Generate deterministic local port
            $localPort = $this->generateLocalPort($sshHost, $service);

            // Build SSH tunnel command
            $identityFile = str_replace('~', getenv('HOME'), $sshHostModel->identity_file);
            $command = sprintf(
                'ssh -f -N -L %d:%s:%d -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i %s %s@%s',
                $localPort,
                $remoteHost,
                $remotePort,
                escapeshellarg($identityFile),
                $sshHostModel->user,
                $sshHostModel->hostname
            );

            Log::info('Creating SSH tunnel', [
                'ssh_host' => $sshHost,
                'service' => $service,
                'local_port' => $localPort,
                'remote_port' => $remotePort,
                'remote_host' => $remoteHost,
            ]);

            // Execute SSH tunnel command
            $result = Process::run($command);

            if (! $result->successful()) {
                throw new Exception('SSH tunnel command failed: '.$result->errorOutput());
            }

            // Wait a moment for tunnel to establish
            usleep(500000); // 500ms

            // Verify tunnel is active
            if (! $this->isPortListening($localPort)) {
                throw new Exception("Tunnel created but port {$localPort} not listening");
            }

            // Find the SSH process PID
            $pid = $this->findTunnelPid($localPort);

            // Store tunnel information
            $tunnelKey = "{$sshHost}:{$service}";
            $this->activeTunnels[$tunnelKey] = [
                'pid' => $pid,
                'local_port' => $localPort,
                'remote_port' => $remotePort,
                'remote_host' => $remoteHost,
                'created_at' => time(),
                'ssh_host' => $sshHost,
                'service' => $service,
            ];

            // Save PID file
            $this->savePidFile($tunnelKey, $this->activeTunnels[$tunnelKey]);

            Log::info('SSH tunnel created successfully', [
                'ssh_host' => $sshHost,
                'service' => $service,
                'local_port' => $localPort,
                'pid' => $pid,
            ]);

            return [
                'success' => true,
                'local_port' => $localPort,
                'remote_port' => $remotePort,
                'endpoint' => "http://localhost:{$localPort}",
                'pid' => $pid,
                'created' => true,
                'message' => 'SSH tunnel created successfully',
            ];

        } catch (Exception $e) {
            Log::error('Failed to create SSH tunnel', [
                'ssh_host' => $sshHost,
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create tunnel: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate deterministic local port for SSH tunnel
     *
     * @param  string  $sshHost  SSH host
     * @param  string  $service  Service name
     * @return int Local port number (10000-19999)
     */
    public function generateLocalPort(string $sshHost, string $service): int
    {
        // Generate deterministic port number based on host and service
        // Range: 10000-19999
        $hash = md5($sshHost.$service);
        $port = 10000 + (hexdec(substr($hash, 0, 4)) % 10000);

        return $port;
    }

    /**
     * Check if SSH tunnel is active
     *
     * @param  string  $sshHost  SSH host
     * @param  int  $localPort  Local port
     * @return bool Whether tunnel is active
     */
    public function isTunnelActive(string $sshHost, int $localPort): bool
    {
        return $this->isPortListening($localPort);
    }

    /**
     * Check if a local port is listening
     *
     * @param  int  $port  Port number
     * @return bool Whether port is listening
     */
    protected function isPortListening(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);

        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Find PID of SSH tunnel process
     *
     * @param  int  $localPort  Local port
     * @return int|null PID or null if not found
     */
    protected function findTunnelPid(int $localPort): ?int
    {
        $result = Process::run("lsof -ti:$localPort");

        if ($result->successful() && ! empty(trim($result->output()))) {
            return (int) trim($result->output());
        }

        return null;
    }

    /**
     * Close SSH tunnel
     *
     * @param  string  $sshHost  SSH host
     * @param  int  $localPort  Local port
     * @return array Close result
     */
    public function closeTunnel(string $sshHost, int $localPort): array
    {
        $tunnelKey = null;

        // Find tunnel by local port
        foreach ($this->activeTunnels as $key => $tunnel) {
            if ($tunnel['local_port'] === $localPort) {
                $tunnelKey = $key;
                break;
            }
        }

        if (! $tunnelKey) {
            return [
                'success' => false,
                'message' => 'Tunnel not found',
            ];
        }

        return $this->cleanupTunnel($tunnelKey);
    }

    /**
     * Cleanup tunnel by key
     *
     * @param  string  $tunnelKey  Tunnel identifier
     * @return array Cleanup result
     */
    protected function cleanupTunnel(string $tunnelKey): array
    {
        if (! isset($this->activeTunnels[$tunnelKey])) {
            return [
                'success' => false,
                'message' => 'Tunnel not found',
            ];
        }

        $tunnel = $this->activeTunnels[$tunnelKey];

        // Kill the SSH process if it exists
        if (isset($tunnel['pid'])) {
            Process::run("kill {$tunnel['pid']} 2>/dev/null");

            Log::info('SSH tunnel closed', [
                'tunnel_key' => $tunnelKey,
                'pid' => $tunnel['pid'],
                'local_port' => $tunnel['local_port'],
            ]);
        }

        // Remove PID file
        $this->deletePidFile($tunnelKey);

        // Remove from active tunnels
        unset($this->activeTunnels[$tunnelKey]);

        return [
            'success' => true,
            'message' => 'Tunnel closed successfully',
        ];
    }

    /**
     * Close all active tunnels
     *
     * @return array Cleanup result
     */
    public function closeAllTunnels(): array
    {
        $closed = 0;
        $errors = [];

        foreach (array_keys($this->activeTunnels) as $tunnelKey) {
            $result = $this->cleanupTunnel($tunnelKey);

            if ($result['success']) {
                $closed++;
            } else {
                $errors[] = $tunnelKey;
            }
        }

        return [
            'success' => empty($errors),
            'closed' => $closed,
            'errors' => $errors,
        ];
    }

    /**
     * Get all active tunnels
     *
     * @return array Active tunnel information
     */
    public function getActiveTunnels(): array
    {
        return $this->activeTunnels;
    }

    /**
     * Load existing tunnels from PID files
     */
    protected function loadExistingTunnels(): void
    {
        if (! is_dir($this->pidDir)) {
            return;
        }

        $pidFiles = glob($this->pidDir.'/*.pid');

        foreach ($pidFiles as $pidFile) {
            $tunnelKey = basename($pidFile, '.pid');
            $data = json_decode(file_get_contents($pidFile), true);

            if ($data && isset($data['local_port'])) {
                // Verify tunnel is still active
                if ($this->isPortListening($data['local_port'])) {
                    $this->activeTunnels[$tunnelKey] = $data;
                } else {
                    // Dead tunnel, clean up PID file
                    @unlink($pidFile);
                }
            }
        }
    }

    /**
     * Save tunnel PID file
     *
     * @param  string  $tunnelKey  Tunnel identifier
     * @param  array  $data  Tunnel data
     */
    protected function savePidFile(string $tunnelKey, array $data): void
    {
        $pidFile = $this->pidDir.'/'.str_replace(':', '_', $tunnelKey).'.pid';
        file_put_contents($pidFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Delete tunnel PID file
     *
     * @param  string  $tunnelKey  Tunnel identifier
     */
    protected function deletePidFile(string $tunnelKey): void
    {
        $pidFile = $this->pidDir.'/'.str_replace(':', '_', $tunnelKey).'.pid';

        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }
    }

    /**
     * Cleanup on service shutdown
     */
    public function __destruct()
    {
        // Optionally close all tunnels on shutdown
        // Disabled by default to keep tunnels running
        // Uncomment to enable: $this->closeAllTunnels();
    }
}
