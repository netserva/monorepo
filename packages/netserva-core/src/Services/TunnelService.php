<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * SSH Tunnel Management Service
 *
 * Manages SSH tunnels for remote service access (PowerDNS, MySQL, Redis, etc.)
 * Uses SSH multiplexing for persistent connections with automatic port assignment.
 *
 * Port Calculation:
 * - PowerDNS: 1{hash}1 (e.g., 10621)
 * - MySQL:    1{hash}6 (e.g., 10626)
 * - Redis:    1{hash}9 (e.g., 10629)
 * - Default:  1{hash}0 (e.g., 10620)
 *
 * Where {hash} = first 3 hex chars of md5(hostname) converted to 0-5 range
 */
class TunnelService
{
    /**
     * Default remote ports for services
     */
    protected const SERVICE_PORTS = [
        'powerdns' => 8081,
        'pdns' => 8081,
        'mysql' => 3306,
        'db' => 3306,
        'redis' => 6379,
        'api' => 8080,
    ];

    /**
     * Calculate local port for host/service combination
     */
    public function calculateLocalPort(string $host, string $service = 'api'): int
    {
        // Get first 3 characters of md5 hash
        // Note: Match bash behavior where echo adds newline
        $hash = substr(md5($host."\n"), 0, 3);

        // Convert hex a-f to numeric 0-5
        $hash = strtr($hash, ['a' => '0', 'b' => '1', 'c' => '2', 'd' => '3', 'e' => '4', 'f' => '5']);

        // Service suffix determines last digit
        $suffix = match ($service) {
            'powerdns', 'pdns' => '1',
            'mysql', 'db' => '6',
            'redis' => '9',
            default => '0',
        };

        return (int) "1{$hash}{$suffix}";
    }

    /**
     * Get default remote port for service
     */
    public function getRemotePort(string $service = 'api'): int
    {
        return self::SERVICE_PORTS[$service] ?? 8080;
    }

    /**
     * Create SSH tunnel
     *
     * @param  string  $host  SSH host identifier
     * @param  string  $service  Service type (powerdns, mysql, redis, api)
     * @param  int|null  $localPort  Override local port (optional)
     * @param  int|null  $remotePort  Override remote port (optional)
     * @param  string  $remoteHost  Remote host (default: localhost)
     * @return array{success: bool, local_port: int, endpoint: string, message?: string, error?: string}
     */
    public function create(
        string $host,
        string $service = 'api',
        ?int $localPort = null,
        ?int $remotePort = null,
        string $remoteHost = 'localhost'
    ): array {
        $localPort = $localPort ?? $this->calculateLocalPort($host, $service);
        $remotePort = $remotePort ?? $this->getRemotePort($service);

        // Check if tunnel already active
        if ($this->isActive($host, $localPort)) {
            return [
                'success' => true,
                'local_port' => $localPort,
                'endpoint' => "http://localhost:{$localPort}",
                'message' => "Tunnel already active on port {$localPort}",
            ];
        }

        try {
            // Create SSH tunnel using SSH multiplexing
            // -f: Background process
            // -N: No command execution
            // -M: Master mode for multiplexing
            // -S: Control socket path
            // -L: Local port forwarding
            $socketPath = $this->getSocketPath($host, $localPort);
            $this->ensureSocketDirectory();

            $command = sprintf(
                'ssh -f -N -M -S %s -L %d:%s:%d %s',
                escapeshellarg($socketPath),
                $localPort,
                escapeshellarg($remoteHost),
                $remotePort,
                escapeshellarg($host)
            );

            $result = Process::run($command);

            if ($result->successful()) {
                Log::info('SSH tunnel created', [
                    'host' => $host,
                    'service' => $service,
                    'local_port' => $localPort,
                    'remote_port' => $remotePort,
                ]);

                return [
                    'success' => true,
                    'local_port' => $localPort,
                    'endpoint' => "http://localhost:{$localPort}",
                    'message' => "Tunnel created on port {$localPort}",
                ];
            } else {
                throw new Exception($result->errorOutput() ?: 'Failed to create tunnel');
            }
        } catch (Exception $e) {
            Log::error('Failed to create SSH tunnel', [
                'host' => $host,
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'local_port' => $localPort,
                'endpoint' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if tunnel is active
     */
    public function isActive(string $host, ?int $localPort = null): bool
    {
        if ($localPort === null) {
            // Check if any tunnel exists for this host
            $pattern = $this->getSocketPath($host, '*');
            $result = Process::run("ls {$pattern} 2>/dev/null");

            return $result->successful() && ! empty(trim($result->output()));
        }

        $socketPath = $this->getSocketPath($host, $localPort);

        // Check if socket exists
        if (! file_exists($socketPath)) {
            return false;
        }

        // Verify connection is still active using SSH control check
        $command = sprintf(
            'ssh -S %s -O check %s 2>/dev/null',
            escapeshellarg($socketPath),
            escapeshellarg($host)
        );

        $result = Process::run($command);

        return $result->successful();
    }

    /**
     * Get tunnel endpoint URL
     *
     * @return array{success: bool, endpoint?: string, local_port?: int, error?: string}
     */
    public function getEndpoint(string $host, string $service = 'api'): array
    {
        $localPort = $this->calculateLocalPort($host, $service);

        if ($this->isActive($host, $localPort)) {
            return [
                'success' => true,
                'endpoint' => "http://localhost:{$localPort}",
                'local_port' => $localPort,
            ];
        }

        return [
            'success' => false,
            'error' => "No active tunnel for {$host}/{$service}",
        ];
    }

    /**
     * Ensure tunnel exists (create if not active)
     *
     * @return array{success: bool, endpoint: string, local_port: int, created: bool, error?: string}
     */
    public function ensure(string $host, string $service = 'api', ?int $remotePort = null): array
    {
        $localPort = $this->calculateLocalPort($host, $service);

        if ($this->isActive($host, $localPort)) {
            return [
                'success' => true,
                'endpoint' => "http://localhost:{$localPort}",
                'local_port' => $localPort,
                'created' => false,
            ];
        }

        $result = $this->create($host, $service, $localPort, $remotePort);

        return [
            ...$result,
            'created' => $result['success'],
        ];
    }

    /**
     * Close active tunnel
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public function close(string $host, ?int $localPort = null): array
    {
        if ($localPort === null) {
            // Close all tunnels for this host
            return $this->closeAllForHost($host);
        }

        $socketPath = $this->getSocketPath($host, $localPort);

        if (! $this->isActive($host, $localPort)) {
            return [
                'success' => true,
                'message' => "No active tunnel on port {$localPort}",
            ];
        }

        try {
            // Exit the SSH master connection
            $command = sprintf(
                'ssh -S %s -O exit %s 2>/dev/null',
                escapeshellarg($socketPath),
                escapeshellarg($host)
            );

            Process::run($command);

            // Remove socket file
            if (file_exists($socketPath)) {
                @unlink($socketPath);
            }

            Log::info('SSH tunnel closed', [
                'host' => $host,
                'local_port' => $localPort,
            ]);

            return [
                'success' => true,
                'message' => "Tunnel closed on port {$localPort}",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Close all tunnels for a host
     */
    protected function closeAllForHost(string $host): array
    {
        $basePath = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');
        $pattern = "{$basePath}/{$host}_*";

        $result = Process::run("ls {$pattern} 2>/dev/null");

        if (! $result->successful() || empty(trim($result->output()))) {
            return [
                'success' => true,
                'message' => "No active tunnels for {$host}",
            ];
        }

        $sockets = explode("\n", trim($result->output()));
        $closed = 0;

        foreach ($sockets as $socketPath) {
            if (! empty($socketPath) && file_exists($socketPath)) {
                // Extract port from socket path
                if (preg_match('/_(\d+)$/', $socketPath, $matches)) {
                    $this->close($host, (int) $matches[1]);
                    $closed++;
                }
            }
        }

        return [
            'success' => true,
            'message' => "Closed {$closed} tunnel(s) for {$host}",
        ];
    }

    /**
     * List all active tunnels
     *
     * @return array<array{host: string, local_port: int, endpoint: string}>
     */
    public function listActive(): array
    {
        $basePath = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');

        if (! is_dir($basePath)) {
            return [];
        }

        $result = Process::run("ls {$basePath}/* 2>/dev/null");

        if (! $result->successful() || empty(trim($result->output()))) {
            return [];
        }

        $tunnels = [];
        $sockets = explode("\n", trim($result->output()));

        foreach ($sockets as $socketPath) {
            if (empty($socketPath) || ! file_exists($socketPath)) {
                continue;
            }

            // Parse socket filename: {host}_{port}
            $filename = basename($socketPath);
            if (preg_match('/^(.+)_(\d+)$/', $filename, $matches)) {
                $host = $matches[1];
                $localPort = (int) $matches[2];

                if ($this->isActive($host, $localPort)) {
                    $tunnels[] = [
                        'host' => $host,
                        'local_port' => $localPort,
                        'endpoint' => "http://localhost:{$localPort}",
                    ];
                }
            }
        }

        return $tunnels;
    }

    /**
     * Get socket path for tunnel
     */
    protected function getSocketPath(string $host, int|string $localPort): string
    {
        $basePath = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');

        return "{$basePath}/{$host}_{$localPort}";
    }

    /**
     * Ensure socket directory exists
     */
    protected function ensureSocketDirectory(): void
    {
        $basePath = config('netserva-cli.ssh_mux_dir', env('HOME').'/.ssh/mux');

        if (! is_dir($basePath)) {
            mkdir($basePath, 0700, true);
        }
    }
}
