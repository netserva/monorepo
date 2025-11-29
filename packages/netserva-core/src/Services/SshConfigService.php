<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Contracts\ConfigManagerInterface;

/**
 * SSH Configuration Service
 *
 * Manages standard ~/.ssh/ directory structure:
 * - ~/.ssh/hosts/* - Individual SSH host configuration files
 * - ~/.ssh/keys/* - SSH key files
 * - ~/.ssh/config - Main SSH configuration
 * - ~/.ssh/mux/* - Control sockets
 */
class SshConfigService implements ConfigManagerInterface
{
    protected string $sshPath;

    protected string $hostsPath;

    protected string $keysPath;

    protected string $runtimePath;

    protected string $varSshPath;

    public function __construct()
    {
        $this->sshPath = env('HOME').'/.ssh';
        $this->hostsPath = $this->sshPath.'/hosts';
        $this->keysPath = $this->sshPath.'/keys';
        $this->runtimePath = $this->sshPath.'/mux';
        $this->varSshPath = $this->sshPath;

        $this->ensureDirectories();
    }

    /**
     * Load SSH host configuration
     */
    public function loadHostConfig(string $host): array
    {
        $filePath = $this->getHostConfigPath($host);

        if (! File::exists($filePath)) {
            throw new Exception("SSH host config not found: {$host}");
        }

        return $this->parseHostConfig($filePath);
    }

    /**
     * Save SSH host configuration
     */
    public function saveHostConfig(string $host, array $config): bool
    {
        $filePath = $this->getHostConfigPath($host);

        // Backup existing config if it exists
        if (File::exists($filePath)) {
            $this->backupHostConfig($host);
        }

        $content = $this->generateHostConfigFormat($host, $config);

        try {
            File::put($filePath, $content);
            Log::info('SSH host config saved', ['host' => $host]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save SSH host config', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate main SSH configuration
     */
    public function regenerateMainConfig(): bool
    {
        $mainConfigPath = $this->sshPath.'/config';

        $configContent = [];
        $configContent[] = '# NetServa SSH Configuration - Generated from standalone system';
        $configContent[] = '# Works with or without NetServa Laravel application';
        $configContent[] = 'Ciphers aes128-ctr,aes192-ctr,aes256-ctr,aes128-gcm@openssh.com,aes256-gcm@openssh.com,chacha20-poly1305@openssh.com';
        $configContent[] = '';
        $configContent[] = '# Include individual host configurations';
        $configContent[] = 'Include ~/.ssh/hosts/*';
        $configContent[] = '';
        $configContent[] = '# Global SSH settings';
        $configContent[] = 'Host *';
        $configContent[] = '  TCPKeepAlive yes';
        $configContent[] = '  ServerAliveInterval 30';
        $configContent[] = '  IdentitiesOnly yes';
        $configContent[] = '  User root';
        $configContent[] = '  ForwardAgent yes';
        $configContent[] = '  AddKeysToAgent yes';
        $configContent[] = '  ControlMaster auto';
        $configContent[] = '  ControlPath ~/.ssh/mux/%h_%p_%r';
        $configContent[] = '  ControlPersist 10m';
        $configContent[] = '#  StrictHostKeyChecking no';

        $finalContent = implode("\n", $configContent);

        try {
            File::put($mainConfigPath, $finalContent);
            File::chmod($mainConfigPath, 0600);
            Log::info('SSH main config regenerated', ['path' => $mainConfigPath]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to regenerate SSH main config', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get path to SSH key file
     */
    public function getKeyPath(string $keyName): string
    {
        return $this->keysPath.'/'.$keyName;
    }

    /**
     * Check if SSH key exists
     */
    public function keyExists(string $keyName): bool
    {
        return File::exists($this->getKeyPath($keyName));
    }

    /**
     * List all SSH keys
     */
    public function listKeys(): array
    {
        if (! File::exists($this->keysPath)) {
            return [];
        }

        $keys = [];
        $files = File::files($this->keysPath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip public key files and backups
            if (! str_ends_with($filename, '.pub') && ! str_contains($filename, '.backup')) {
                $keys[] = $filename;
            }
        }

        return $keys;
    }

    /**
     * List all SSH hosts
     */
    public function listHosts(): array
    {
        if (! File::exists($this->hostsPath)) {
            return [];
        }

        $hosts = [];
        $files = File::files($this->hostsPath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip backup files
            if (! str_contains($filename, '.backup')) {
                $hosts[] = $filename;
            }
        }

        return $hosts;
    }

    /**
     * Get SSH mux socket path for host
     */
    public function getMuxSocketPath(string $host, string $user = 'root'): string
    {
        // Ensure var/ssh directory exists
        if (! File::exists($this->varSshPath)) {
            File::makeDirectory($this->varSshPath, 0700, true);
        }

        return $this->varSshPath.'/'.$user.'@'.$host.':22';
    }

    /**
     * Clean up stale mux sockets
     */
    public function cleanupMuxSockets(): int
    {
        if (! File::exists($this->varSshPath)) {
            return 0;
        }

        $sockets = File::files($this->varSshPath);
        $cleaned = 0;

        foreach ($sockets as $socket) {
            $path = $socket->getPathname();

            // Check if socket is still active
            if (! $this->isMuxSocketActive($path)) {
                try {
                    File::delete($path);
                    $cleaned++;
                    Log::info('Cleaned up stale mux socket', ['path' => $path]);
                } catch (Exception $e) {
                    Log::error('Failed to clean up mux socket', [
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $cleaned;
    }

    // Implementation of ConfigManagerInterface methods

    public function load(string $identifier): array
    {
        return $this->loadHostConfig($identifier);
    }

    public function save(string $identifier, array $config): bool
    {
        return $this->saveHostConfig($identifier, $config);
    }

    public function exists(string $identifier): bool
    {
        return File::exists($this->getHostConfigPath($identifier));
    }

    public function delete(string $identifier): bool
    {
        $configPath = $this->getHostConfigPath($identifier);

        if (File::exists($configPath)) {
            try {
                File::delete($configPath);
                Log::info('SSH host config deleted', ['host' => $identifier]);

                return true;
            } catch (Exception $e) {
                Log::error('Failed to delete SSH host config', [
                    'host' => $identifier,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return false;
    }

    public function list(): array
    {
        return $this->listHosts();
    }

    public function validate(array $config): bool
    {
        $requiredFields = ['Hostname'];

        foreach ($requiredFields as $field) {
            if (! isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }

        // Validate port if provided
        if (isset($config['Port'])) {
            $port = (int) $config['Port'];
            if ($port < 1 || $port > 65535) {
                return false;
            }
        }

        return true;
    }

    public function backup(string $identifier): ?string
    {
        return $this->backupHostConfig($identifier);
    }

    // Protected helper methods

    protected function ensureDirectories(): void
    {
        $directories = [
            $this->sshPath => 0700,
            $this->hostsPath => 0700,
            $this->keysPath => 0700,
            $this->runtimePath => 0700,
            $this->varSshPath => 0700,
        ];

        foreach ($directories as $dir => $mode) {
            if (! File::exists($dir)) {
                File::makeDirectory($dir, $mode, true);
            }
        }
    }

    protected function getHostConfigPath(string $host): string
    {
        return $this->hostsPath.'/'.$host;
    }

    protected function parseHostConfig(string $filePath): array
    {
        $content = File::get($filePath);
        $config = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Skip Host line (we already know the host)
            if (str_starts_with($line, 'Host ')) {
                continue;
            }

            // Parse SSH config format: "  Key value"
            if (preg_match('/^\s*(\w+)\s+(.+)$/', $line, $matches)) {
                $config[$matches[1]] = trim($matches[2]);
            }
        }

        return $config;
    }

    protected function generateHostConfigFormat(string $host, array $config): string
    {
        $lines = [];
        $lines[] = "Host {$host}";

        // Standard SSH config options in typical order
        $standardOrder = [
            'Hostname', 'Port', 'User', 'IdentityFile', 'ProxyCommand',
            'ProxyJump', 'ForwardAgent', 'ForwardX11', 'Compression',
        ];

        // Add options in standard order first
        foreach ($standardOrder as $option) {
            if (isset($config[$option])) {
                $lines[] = "  {$option} {$config[$option]}";
            }
        }

        // Add any remaining options
        foreach ($config as $option => $value) {
            if (! in_array($option, $standardOrder)) {
                $lines[] = "  {$option} {$value}";
            }
        }

        $lines[] = ''; // Empty line at end

        return implode("\n", $lines);
    }

    protected function backupHostConfig(string $host): ?string
    {
        $configPath = $this->getHostConfigPath($host);

        if (! File::exists($configPath)) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $configPath.".backup.{$timestamp}";

        try {
            File::copy($configPath, $backupPath);

            return $backupPath;
        } catch (Exception $e) {
            Log::error('Failed to backup SSH host config', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function isMuxSocketActive(string $socketPath): bool
    {
        // Check if socket file exists and is actually a socket
        if (! file_exists($socketPath) || ! is_link($socketPath)) {
            return false;
        }

        // Try to use SSH to check the connection
        $escapedPath = escapeshellarg($socketPath);
        $checkCommand = "ssh -O check -S {$escapedPath} dummy 2>/dev/null";

        exec($checkCommand, $output, $returnCode);

        // SSH returns 0 if the connection is active
        return $returnCode === 0;
    }
}
