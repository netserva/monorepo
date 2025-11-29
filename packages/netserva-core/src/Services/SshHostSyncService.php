<?php

declare(strict_types=1);

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Models\SshHost;

/**
 * SSH Host Sync Service
 *
 * Manages synchronization between database SshHost records
 * and filesystem SSH config files in ~/.ssh/hosts/
 *
 * Like DNS sync, database is source of truth, filesystem
 * is synced so regular `ssh hostname` commands work.
 */
class SshHostSyncService
{
    protected string $hostsDir;

    protected string $sshDir;

    public function __construct()
    {
        $home = env('HOME', '/home/'.get_current_user());
        $this->sshDir = "{$home}/.ssh";
        $this->hostsDir = "{$this->sshDir}/hosts";
    }

    /**
     * Sync a single host to filesystem
     */
    public function syncHost(SshHost $host): bool
    {
        if (! $host->is_active) {
            // Remove config file if host is inactive
            return $this->deleteHostConfig($host->host);
        }

        try {
            $this->ensureDirectoryExists();

            $configPath = "{$this->hostsDir}/{$host->host}";
            $configContent = $this->generateHostConfig($host);

            File::put($configPath, $configContent);
            chmod($configPath, 0600);

            Log::info('SSH host config synced to filesystem', [
                'host' => $host->host,
                'path' => $configPath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync SSH host config', [
                'host' => $host->host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete a host config file from filesystem
     */
    public function deleteHostConfig(string $hostName): bool
    {
        $configPath = "{$this->hostsDir}/{$hostName}";

        if (File::exists($configPath)) {
            try {
                File::delete($configPath);

                Log::info('SSH host config deleted from filesystem', [
                    'host' => $hostName,
                    'path' => $configPath,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error('Failed to delete SSH host config', [
                    'host' => $hostName,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return true; // File doesn't exist, nothing to delete
    }

    /**
     * Sync all active hosts to filesystem
     */
    public function syncAllHosts(): array
    {
        $results = ['synced' => 0, 'failed' => 0, 'errors' => []];

        $hosts = SshHost::where('is_active', true)->get();

        foreach ($hosts as $host) {
            if ($this->syncHost($host)) {
                $results['synced']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $host->host;
            }
        }

        // Remove orphaned config files (hosts no longer in DB)
        $this->cleanOrphanedConfigs($hosts->pluck('host')->toArray());

        Log::info('SSH hosts sync completed', $results);

        return $results;
    }

    /**
     * Import a single host from filesystem into database (update existing)
     */
    public function importSingleHost(string $hostName): bool
    {
        $configPath = "{$this->hostsDir}/{$hostName}";

        if (! File::exists($configPath)) {
            Log::warning('SSH host config file not found', [
                'host' => $hostName,
                'path' => $configPath,
            ]);

            return false;
        }

        try {
            $content = File::get($configPath);
            $hostData = $this->parseHostConfig($content, $hostName);

            if (! $hostData || ! $hostData['hostname']) {
                Log::warning('Invalid SSH host config file', [
                    'host' => $hostName,
                ]);

                return false;
            }

            // Find existing host and update it
            $host = SshHost::where('host', $hostName)->first();

            if ($host) {
                // Preserve identity_file as full path if it exists in file
                if (isset($hostData['identity_file']) && $hostData['identity_file']) {
                    $hostData['identity_file'] = "~/.ssh/keys/{$hostData['identity_file']}";
                }

                $host->update($hostData);

                Log::info('SSH host updated from filesystem', [
                    'host' => $hostName,
                ]);

                return true;
            }

            Log::warning('SSH host not found in database', [
                'host' => $hostName,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to import SSH host from filesystem', [
                'host' => $hostName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import hosts from filesystem into database
     */
    public function importFromFilesystem(): array
    {
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if (! File::isDirectory($this->hostsDir)) {
            return $results;
        }

        $files = File::files($this->hostsDir);

        foreach ($files as $file) {
            $fileName = $file->getFilename();

            // Skip hidden files and special files
            if (str_starts_with($fileName, '.')) {
                continue;
            }

            // Check if host already exists
            if (SshHost::where('host', $fileName)->exists()) {
                $results['skipped']++;

                continue;
            }

            try {
                $content = File::get($file->getPathname());
                $hostData = $this->parseHostConfig($content, $fileName);

                if ($hostData) {
                    SshHost::create($hostData);
                    $results['imported']++;

                    Log::info('SSH host imported from filesystem', [
                        'host' => $fileName,
                    ]);
                }
            } catch (\Exception $e) {
                $results['errors'][] = "{$fileName}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Generate SSH config content for a host
     */
    protected function generateHostConfig(SshHost $host): string
    {
        $lines = [];
        $lines[] = "Host {$host->host}";
        $lines[] = "  Hostname {$host->hostname}";

        if ($host->port !== 22) {
            $lines[] = "  Port {$host->port}";
        }

        if ($host->user !== 'root') {
            $lines[] = "  User {$host->user}";
        }

        // Identity file - if it's just a name, prepend the keys path
        if ($host->identity_file) {
            $identityPath = $host->identity_file;
            // If it's just a key name without path, use ~/.ssh/keys/
            if (! str_contains($identityPath, '/')) {
                $identityPath = "~/.ssh/keys/{$identityPath}";
            }
            $lines[] = "  IdentityFile {$identityPath}";
        }

        if ($host->proxy_command) {
            $lines[] = "  ProxyCommand {$host->proxy_command}";
        }

        if ($host->jump_host) {
            $lines[] = "  ProxyJump {$host->jump_host}";
        }

        // Custom options
        if ($host->custom_options && is_array($host->custom_options)) {
            foreach ($host->custom_options as $option => $value) {
                $lines[] = "  {$option} {$value}";
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Parse SSH config file content into host data
     */
    protected function parseHostConfig(string $content, string $fileName): ?array
    {
        $hostData = [
            'host' => $fileName,
            'hostname' => '',
            'port' => 22,
            'user' => 'root',
            'identity_file' => null,
            'proxy_command' => null,
            'jump_host' => null,
            'custom_options' => [],
            'is_active' => true,
        ];

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse directive and value
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $directive = strtolower($parts[0]);
            $value = $parts[1];

            switch ($directive) {
                case 'host':
                    $hostData['host'] = $value;
                    break;
                case 'hostname':
                    $hostData['hostname'] = $value;
                    break;
                case 'port':
                    $hostData['port'] = (int) $value;
                    break;
                case 'user':
                    $hostData['user'] = $value;
                    break;
                case 'identityfile':
                    // Extract just the key name from full path
                    $keyName = basename($value);
                    $hostData['identity_file'] = $keyName;
                    break;
                case 'proxycommand':
                    $hostData['proxy_command'] = $value;
                    break;
                case 'proxyjump':
                    $hostData['jump_host'] = $value;
                    break;
                default:
                    // Store other options as custom (exclude Host which is the alias)
                    if (strtolower($parts[0]) !== 'host') {
                        $hostData['custom_options'][$parts[0]] = $value;
                    }
                    break;
            }
        }

        // Must have hostname to be valid
        return $hostData['hostname'] ? $hostData : null;
    }

    /**
     * Remove config files for hosts no longer in database
     */
    protected function cleanOrphanedConfigs(array $activeHosts): void
    {
        if (! File::isDirectory($this->hostsDir)) {
            return;
        }

        $files = File::files($this->hostsDir);

        foreach ($files as $file) {
            $fileName = $file->getFilename();

            // Skip hidden files
            if (str_starts_with($fileName, '.')) {
                continue;
            }

            if (! in_array($fileName, $activeHosts)) {
                File::delete($file->getPathname());

                Log::info('Orphaned SSH config removed', ['host' => $fileName]);
            }
        }
    }

    /**
     * Ensure the hosts directory exists with correct permissions
     */
    protected function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->sshDir)) {
            File::makeDirectory($this->sshDir, 0700, true);
        }

        if (! File::isDirectory($this->hostsDir)) {
            File::makeDirectory($this->hostsDir, 0700, true);
        }
    }

    /**
     * Get hosts directory path
     */
    public function getHostsDir(): string
    {
        return $this->hostsDir;
    }
}
