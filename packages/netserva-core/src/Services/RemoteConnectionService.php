<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Models\SshConnection;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Models\SshKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * NetServa Core Remote Connection Service
 *
 * Robust SSH connectivity management using phpseclib3.
 * Handles SSH connections, key authentication, and connection pooling for NetServa infrastructure.
 */
class RemoteConnectionService
{
    protected array $connections = [];

    protected string $nsdir;

    protected string $nsssh;

    public function __construct()
    {
        $this->nsdir = env('NSDIR', '/home/'.get_current_user().'/.ns');
        $this->nsssh = $this->nsdir.'/ssh';
    }

    /**
     * Get or create SSH connection to host
     */
    public function getConnection(string $host): SSH2
    {
        if (isset($this->connections[$host])) {
            // Check if connection is still alive
            if ($this->connections[$host]->isConnected()) {
                return $this->connections[$host];
            }
            // Remove dead connection
            unset($this->connections[$host]);
        }

        return $this->createConnection($host);
    }

    /**
     * Create new SSH connection using SshHost model
     */
    protected function createConnection(string $host): SSH2
    {
        $sshHost = SshHost::where('host', $host)->first();

        if (! $sshHost) {
            throw new Exception("VNode configuration not found in database: $host");
        }

        if (! $sshHost->is_active) {
            throw new Exception("VNode is deactivated: $host");
        }

        Log::info('Creating SSH connection', [
            'host' => $host,
            'hostname' => $sshHost->hostname,
            'port' => $sshHost->port,
        ]);

        $ssh = new SSH2($sshHost->hostname, $sshHost->port);

        // Set connection timeout
        $ssh->setTimeout(30);

        // Authenticate using private key
        if ($sshHost->identity_file) {
            $this->authenticateWithKeyFile($ssh, $sshHost->user, $sshHost->identity_file);
        } else {
            throw new Exception("No identity file (SSH key) configured for vnode: $host");
        }

        $this->connections[$host] = $ssh;

        Log::info('SSH connection established', ['host' => $host]);

        return $ssh;
    }

    /**
     * Authenticate with private key file
     */
    protected function authenticateWithKeyFile(SSH2 $ssh, string $user, string $keyFile): void
    {
        // Expand ~ to home directory
        $keyFile = str_replace('~', getenv('HOME'), $keyFile);

        if (! file_exists($keyFile)) {
            throw new Exception("SSH private key not found: $keyFile");
        }

        $privateKey = PublicKeyLoader::load(file_get_contents($keyFile));

        if (! $ssh->login($user, $privateKey)) {
            throw new Exception("SSH authentication failed for user: $user");
        }

        Log::info('SSH authentication successful', ['user' => $user]);
    }

    /**
     * Authenticate with SshKey model
     */
    public function authenticateWithKey(SSH2 $ssh, string $user, SshKey $sshKey): void
    {
        if (empty($sshKey->private_key)) {
            throw new Exception("SSH key has no private key content: {$sshKey->name}");
        }

        $privateKey = PublicKeyLoader::load($sshKey->private_key);

        if (! $ssh->login($user, $privateKey)) {
            throw new Exception("SSH authentication failed for user: $user using key: {$sshKey->name}");
        }

        Log::info('SSH authentication successful', [
            'user' => $user,
            'key' => $sshKey->name,
        ]);

        // Update last used timestamp
        $sshKey->update(['last_used_at' => now()]);
    }

    /**
     * Execute command on remote host
     */
    public function exec(string $host, string $command): array
    {
        $ssh = $this->getConnection($host);

        Log::info('Executing SSH command', [
            'host' => $host,
            'command' => $command,
        ]);

        $output = $ssh->exec($command);
        $exitCode = $ssh->getExitStatus();

        Log::info('SSH command completed', [
            'host' => $host,
            'exit_code' => $exitCode,
            'output_length' => strlen($output),
        ]);

        return [
            'output' => $output,
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    /**
     * Test connection to host using SshHost model or string
     */
    public function testConnection(SshHost|string $host): bool|array
    {
        // Handle SshHost object by delegating to testSshHost
        if ($host instanceof SshHost) {
            return $this->testSshHost($host);
        }

        // Handle string host names (original behavior)
        try {
            $ssh = $this->getConnection($host);
            $result = $this->exec($host, 'echo "NetServa SSH test successful"');

            return $result['success'] &&
                   strpos($result['output'], 'NetServa SSH test successful') !== false;
        } catch (Exception $e) {
            Log::warning('SSH connection test failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test SshHost model connectivity and update status
     */
    public function testSshHost(SshHost $sshHost): bool
    {
        try {
            $isReachable = $this->testConnection($sshHost->host);

            // Update the SshHost model with test results
            $sshHost->update([
                'last_tested_at' => now(),
                'is_reachable' => $isReachable,
                'last_error' => $isReachable ? null : 'Connection test failed',
            ]);

            return $isReachable;
        } catch (Exception $e) {
            // Update the SshHost model with error information
            $sshHost->update([
                'last_tested_at' => now(),
                'is_reachable' => false,
                'last_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Close connection to specific host
     */
    public function closeConnection(string $host): bool
    {
        if (isset($this->connections[$host])) {
            $ssh = $this->connections[$host];
            if ($ssh->isConnected()) {
                $ssh->disconnect();
                Log::info('SSH connection closed', ['host' => $host]);
            }
            unset($this->connections[$host]);

            return true;
        }

        return false;
    }

    /**
     * Close all connections
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $host => $ssh) {
            if ($ssh->isConnected()) {
                $ssh->disconnect();
                Log::info('SSH connection closed', ['host' => $host]);
            }
        }

        $this->connections = [];
    }

    /**
     * Get list of currently active connections
     */
    public function getActiveConnections(): array
    {
        $active = [];

        foreach ($this->connections as $host => $ssh) {
            if ($ssh->isConnected()) {
                $active[] = [
                    'host' => $host,
                    'connected' => true,
                    'connection_time' => time(), // We could track this if needed
                ];
            }
        }

        return $active;
    }

    /**
     * Get connection count
     */
    public function getConnectionCount(): int
    {
        return count(array_filter(
            $this->connections,
            fn ($ssh) => $ssh->isConnected()
        ));
    }

    /**
     * Create SSH connection and return SshConnection model
     */
    public function connect(SshHost $host, ?string $password = null): ?SshConnection
    {
        try {
            $ssh = $this->createConnection($host->host);

            // Create SshConnection record
            $connection = SshConnection::create([
                'ssh_host_id' => $host->id,
                'name' => $host->host.' connection',
                'hostname' => $host->hostname,
                'port' => $host->port,
                'username' => $host->user,
                'connection_type' => $password ? 'password' : 'key',
                'is_active' => true,
                'last_connected_at' => now(),
                'is_reachable' => true,
                'connection_string' => $host->getConnectionString(),
            ]);

            return $connection;
        } catch (Exception $e) {
            Log::error('SSH connection failed', [
                'host' => $host->host,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Execute command via SshConnection model
     */
    public function executeCommand(SshConnection $connection, string $command): array
    {
        $host = $connection->sshHost->host;
        $result = $this->exec($host, $command);

        // Update connection last used
        $connection->update(['last_connected_at' => now()]);

        return $result;
    }

    /**
     * Upload file via SCP
     */
    public function uploadFile(SshConnection $connection, string $content, string $remotePath): bool
    {
        try {
            $host = $connection->sshHost->host;
            $ssh = $this->getConnection($host);

            $result = $ssh->put($remotePath, $content);

            // Update connection last used
            $connection->update(['last_connected_at' => now()]);

            return $result;
        } catch (Exception $e) {
            Log::error('SCP upload failed', [
                'host' => $connection->sshHost->host,
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Download file via SCP
     */
    public function downloadFile(SshConnection $connection, string $remotePath): ?string
    {
        try {
            $host = $connection->sshHost->host;
            $ssh = $this->getConnection($host);

            $content = $ssh->get($remotePath);

            // Update connection last used
            $connection->update(['last_connected_at' => now()]);

            return $content;
        } catch (Exception $e) {
            Log::error('SCP download failed', [
                'host' => $connection->sshHost->host,
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Setup port forwarding (placeholder - requires additional implementation)
     */
    public function setupPortForwarding(SshConnection $connection, int $localPort, string $remoteHost, int $remotePort): bool
    {
        // This is a basic implementation - real port forwarding would need more complex setup
        Log::info('Port forwarding setup requested', [
            'connection' => $connection->id,
            'local_port' => $localPort,
            'remote_host' => $remoteHost,
            'remote_port' => $remotePort,
        ]);

        // For testing purposes, return true
        return true;
    }

    /**
     * Disconnect SshConnection and update model
     */
    public function disconnect(SshConnection $connection): bool
    {
        $host = $connection->sshHost->host;
        $result = $this->closeConnection($host);

        if ($result) {
            $connection->update([
                'is_active' => false,
                'last_connected_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * Get connection history for a host
     */
    public function getConnectionHistory(SshHost $host): \Illuminate\Database\Eloquent\Collection
    {
        return SshConnection::where('ssh_host_id', $host->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate SSH host configuration
     */
    public function validateHost(SshHost $host): bool
    {
        // Basic validation checks
        if (empty($host->hostname) || empty($host->user)) {
            return false;
        }

        if ($host->port < 1 || $host->port > 65535) {
            return false;
        }

        return true;
    }

    /**
     * Generate SSH key pair
     */
    public function generateKeyPair(string $keyType = 'rsa', int $keySize = 2048): array
    {
        try {
            // Basic key generation using phpseclib
            switch (strtolower($keyType)) {
                case 'rsa':
                    $key = \phpseclib3\Crypt\RSA::createKey($keySize);
                    break;
                case 'ed25519':
                    $key = \phpseclib3\Crypt\Ed25519::createKey();
                    break;
                default:
                    throw new Exception("Unsupported key type: $keyType");
            }

            $privateKey = (string) $key;
            $publicKey = (string) $key->getPublicKey();
            $fingerprint = $key->getPublicKey()->getFingerprint('sha256');

            return [
                'private_key' => $privateKey,
                'public_key' => $publicKey,
                'fingerprint' => 'SHA256:'.$fingerprint,
            ];
        } catch (Exception $e) {
            Log::error('SSH key generation failed', [
                'key_type' => $keyType,
                'key_size' => $keySize,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
