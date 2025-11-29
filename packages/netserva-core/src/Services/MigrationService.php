<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Models\SshHost;

/**
 * NS Migration Service
 *
 * Handles server migration operations, converting from bash migrate script functionality
 * to Laravel-based operations using SSH connections and Laravel Prompts for interaction.
 */
class MigrationService
{
    protected array $options = [];

    protected string $nsdir;

    protected string $backupDir;

    public function __construct(
        protected SshConnectionService $sshService
    ) {
        $this->nsdir = env('NSDIR', '/home/'.get_current_user().'/.ns');
        $this->backupDir = '/home/backups/migrate-'.date('Ymd-His');
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);

        if (isset($options['backup_dir'])) {
            $this->backupDir = $options['backup_dir'];
        }
    }

    public function getAvailableHosts(): Collection
    {
        return SshHost::where('is_active', true)
            ->get()
            ->map(function ($host) {
                return [
                    'host' => $host->host,
                    'description' => "{$host->host} ({$host->hostname}:{$host->port}) - {$host->description}",
                ];
            });
    }

    /**
     * Check system and service status on remote host
     */
    public function checkStatus(string $host): array
    {
        Log::info("Checking status of host: {$host}");

        $status = [
            'System' => [],
            'Services' => [],
            'Environment' => [],
            'Migration State' => [],
        ];

        try {
            // Check basic connectivity
            $status['System']['SSH Connection'] = $this->testSshConnection($host);

            // Check system info
            $status['System']['OS Information'] = $this->getOsInfo($host);
            $status['System']['Disk Space'] = $this->getDiskSpace($host);
            $status['System']['Memory'] = $this->getMemoryInfo($host);

            // Check shell environment
            $status['Environment']['Shell Environment'] = $this->checkShellEnvironment($host);
            $status['Environment']['NetServa System'] = $this->checkNetServaSystem($host);

            // Check services
            $status['Services']['Web Server'] = $this->checkService($host, 'nginx');
            $status['Services']['Database'] = $this->checkService($host, 'mariadb');
            $status['Services']['Mail Server'] = $this->checkService($host, 'postfix');
            $status['Services']['IMAP Server'] = $this->checkService($host, 'dovecot');

            // Check migration state
            $status['Migration State']['Legacy System'] = $this->checkLegacySystem($host);
            $status['Migration State']['NS Standard'] = $this->checkNsStandard($host);

        } catch (Exception $e) {
            Log::error("Status check failed for {$host}: {$e->getMessage()}");
            $status['System']['Error'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return $status;
    }

    protected function testSshConnection(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'echo "NS SSH test successful"');

            return [
                'status' => $result['success'] ? 'ok' : 'error',
                'message' => $result['success'] ? 'Connected' : 'Connection failed',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => "Failed: {$e->getMessage()}",
            ];
        }
    }

    protected function getOsInfo(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'cat /etc/os-release | grep PRETTY_NAME');

            if ($result['success']) {
                $osName = trim(str_replace(['PRETTY_NAME=', '"'], '', $result['output']));

                return [
                    'status' => 'ok',
                    'message' => $osName,
                ];
            }
        } catch (Exception $e) {
            // Fallback
        }

        return [
            'status' => 'warning',
            'message' => 'Could not determine OS',
        ];
    }

    protected function getDiskSpace(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'df -h / | tail -1 | awk \'{print $5 " used (" $4 " available)"}\'');

            if ($result['success']) {
                return [
                    'status' => 'ok',
                    'message' => trim($result['output']),
                ];
            }
        } catch (Exception $e) {
            // Fallback
        }

        return [
            'status' => 'warning',
            'message' => 'Could not check disk space',
        ];
    }

    protected function getMemoryInfo(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'free -h | grep Mem | awk \'{print $3 "/" $2 " used"}\'');

            if ($result['success']) {
                return [
                    'status' => 'ok',
                    'message' => trim($result['output']),
                ];
            }
        } catch (Exception $e) {
            // Fallback
        }

        return [
            'status' => 'warning',
            'message' => 'Could not check memory',
        ];
    }

    protected function checkShellEnvironment(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'test -f ~/.rc/_shrc && echo "present" || echo "missing"');

            $isPresent = trim($result['output']) === 'present';

            return [
                'status' => $isPresent ? 'ok' : 'warning',
                'message' => $isPresent ? 'Shell environment installed' : 'Shell environment missing',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not check shell environment',
            ];
        }
    }

    protected function checkNetServaSystem(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'test -d ~/.ns && echo "present" || echo "missing"');

            $isPresent = trim($result['output']) === 'present';

            return [
                'status' => $isPresent ? 'warning' : 'ok',
                'message' => $isPresent
                    ? 'NetServa system present (should be workstation-only)'
                    : 'No NetServa system (correct for remote server)',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not check NetServa system',
            ];
        }
    }

    protected function checkService(string $host, string $service): array
    {
        try {
            $result = $this->sshService->exec($host, "systemctl is-active {$service} 2>/dev/null || echo 'inactive'");

            $isActive = trim($result['output']) === 'active';

            return [
                'status' => $isActive ? 'ok' : 'warning',
                'message' => $isActive ? 'Running' : 'Not running',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Could not check service',
            ];
        }
    }

    protected function checkLegacySystem(string $host): array
    {
        try {
            $result = $this->sshService->exec($host, 'test -d /root/.vhosts && echo "present" || echo "missing"');

            $hasLegacy = trim($result['output']) === 'present';

            return [
                'status' => $hasLegacy ? 'warning' : 'ok',
                'message' => $hasLegacy
                    ? 'Legacy /root/.vhosts system present (needs migration)'
                    : 'No legacy system detected',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not check legacy system',
            ];
        }
    }

    protected function checkNsStandard(string $host): array
    {
        // Check if server follows NS standard (centralized management)
        try {
            $result = $this->sshService->exec($host, 'test -d /root/.vhosts && echo "old" || echo "new"');

            $isNsStandard = trim($result['output']) === 'new';

            return [
                'status' => $isNsStandard ? 'ok' : 'warning',
                'message' => $isNsStandard
                    ? 'Follows NS standard (centralized management)'
                    : 'Still using old SH standard',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Could not determine migration standard',
            ];
        }
    }

    /**
     * Environment sync operations
     */
    public function prepareEnvironment(string $host): bool
    {
        Log::info("Preparing shell environment for {$host}");

        if ($this->options['dry_run'] ?? false) {
            Log::info("DRY RUN: Would prepare environment for {$host}");

            return true;
        }

        // Implementation would sync ~/.rc/ environment
        return true;
    }

    public function uploadShellFiles(string $host): bool
    {
        Log::info("Uploading shell files to {$host}");

        if ($this->options['dry_run'] ?? false) {
            Log::info("DRY RUN: Would upload shell files to {$host}");

            return true;
        }

        // Implementation would use rsync via SSH to upload ~/.rc/
        return true;
    }

    public function setPermissions(string $host): bool
    {
        Log::info("Setting permissions on {$host}");

        if ($this->options['dry_run'] ?? false) {
            Log::info("DRY RUN: Would set permissions on {$host}");

            return true;
        }

        try {
            $result = $this->sshService->exec($host, 'chmod -R 755 ~/.rc/');

            return $result['success'];
        } catch (Exception $e) {
            Log::error("Failed to set permissions on {$host}: {$e->getMessage()}");

            return false;
        }
    }

    public function verifySync(string $host): bool
    {
        Log::info("Verifying sync on {$host}");

        try {
            $result = $this->sshService->exec($host, 'test -f ~/.rc/_shrc && echo "verified"');

            return trim($result['output']) === 'verified';
        } catch (Exception $e) {
            Log::error("Failed to verify sync on {$host}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Migration operations
     */
    public function assessSystem(string $host): bool
    {
        Log::info("Assessing system state on {$host}");

        return true;
    }

    public function createBackup(string $host): bool
    {
        Log::info("Creating backup on {$host}");

        return true;
    }

    public function syncEnvironment(string $host): bool
    {
        Log::info("Syncing environment on {$host}");

        return $this->uploadShellFiles($host) && $this->setPermissions($host);
    }

    public function migrateConfigurations(string $host): bool
    {
        Log::info("Migrating configurations on {$host}");

        return true;
    }

    public function updateServices(string $host): bool
    {
        Log::info("Updating services on {$host}");

        return true;
    }

    public function verifyMigration(string $host): bool
    {
        Log::info("Verifying migration on {$host}");

        return true;
    }

    /**
     * Cleanup operations
     */
    public function removeLegacyFiles(string $host): bool
    {
        Log::info("Removing legacy files on {$host}");

        return true;
    }

    public function cleanOldConfigs(string $host): bool
    {
        Log::info("Cleaning old configurations on {$host}");

        return true;
    }

    public function updateSystem(string $host): bool
    {
        Log::info("Updating system on {$host}");

        return true;
    }

    /**
     * Simple migration execution status
     */
    public function getStatus(string $migrationId): array
    {
        return [
            'id' => $migrationId,
            'status' => 'pending',
            'created_at' => now()->toISOString(),
        ];
    }
}
