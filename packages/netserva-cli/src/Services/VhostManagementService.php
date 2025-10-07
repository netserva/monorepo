<?php

namespace NetServa\Cli\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * VHost Management Service
 *
 * High-level service for creating and managing virtual hosts.
 * Integrates with existing addvhost functionality while providing
 * modern PHP interfaces.
 */
class VhostManagementService
{
    protected VhostConfigService $vhostConfig;

    protected RemoteExecutionService $remoteExecution;

    protected NetServaConfigurationService $netServaConfig;

    public function __construct(
        VhostConfigService $vhostConfig,
        RemoteExecutionService $remoteExecution,
        NetServaConfigurationService $netServaConfig
    ) {
        $this->vhostConfig = $vhostConfig;
        $this->remoteExecution = $remoteExecution;
        $this->netServaConfig = $netServaConfig;
    }

    /**
     * Create a new virtual host
     *
     * Uses pure PHP NetServa configuration service (no bash dependency)
     */
    public function createVhost(string $host, string $domain): array
    {
        try {
            Log::info('Creating virtual host', [
                'host' => $host,
                'domain' => $domain,
            ]);

            // Step 1: Generate VHost configuration using pure PHP service
            $vhostConfig = $this->netServaConfig->generateVhostConfig($host, $domain);

            // Step 2: Save configuration to ~/.ns/var/
            $envVars = $vhostConfig->toEnvironmentArray();
            $this->vhostConfig->saveVhostConfig($host, $domain, $envVars);

            // Step 3: Save credentials file
            $credentials = $vhostConfig->getCredentials();
            $this->vhostConfig->saveCredentials($host, $domain, $credentials);

            // Step 4: Execute VHost creation operations
            $operations = [
                'user_creation' => $this->createSystemUser($host, $envVars),
                'database_entry' => $this->createDatabaseEntry($host, $domain, $envVars),
                'php_fpm_config' => $this->createPhpFpmConfig($host, $domain, $envVars),
                'directory_setup' => $this->createDirectoryStructure($host, $envVars),
                'web_files' => $this->createWebFiles($host, $domain, $envVars),
                'permissions' => $this->setPermissions($host, $envVars),
            ];

            $results = [];
            $success = true;

            foreach ($operations as $name => $operation) {
                $results[$name] = $operation;
                if (! $operation['success']) {
                    $success = false;
                    Log::error("VHost creation failed at step: {$name}", $operation);
                    break; // Stop on first failure
                }
            }

            if ($success) {
                // Final steps
                $this->runFinalCommands($host, $domain, $config);

                Log::info('VHost created successfully', [
                    'host' => $host,
                    'domain' => $domain,
                    'username' => $username,
                ]);
            }

            return [
                'success' => $success,
                'domain' => $domain,
                'username' => $username,
                'config_path' => "~/.ns/var/{$host}/{$domain}",
                'operations' => $results,
            ];

        } catch (Exception $e) {
            Log::error('VHost creation failed', [
                'host' => $host,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a virtual host
     *
     * Removes VHost configuration, files, database, and SSH cleanup
     */
    public function deleteVhost(string $host, string $domain): array
    {
        try {
            Log::info('Deleting virtual host', [
                'host' => $host,
                'domain' => $domain,
            ]);

            $success = true;
            $operations = [];

            // Step 1: Check if VHost configuration exists
            $configPath = env('HOME')."/.ns/var/{$host}/{$domain}";
            if (! file_exists($configPath)) {
                Log::warning('VHost configuration not found', [
                    'config_path' => $configPath,
                ]);

                return [
                    'success' => false,
                    'error' => "VHost configuration not found: {$configPath}",
                ];
            }

            // Step 2: Load VHost configuration to get user details
            $config = $this->vhostConfig->loadVhostConfig($host, $domain);
            $username = $config['UUSER'] ?? null;

            // Step 3: Remote deletion operations
            if ($username) {
                $operations['remove_user'] = $this->removeSystemUser($host, $username);
                $operations['remove_database'] = $this->removeDatabaseEntry($host, $domain, $config);
                $operations['remove_nginx_config'] = $this->removeNginxConfig($host, $domain);
                $operations['remove_php_fpm_config'] = $this->removePhpFpmConfig($host, $domain);
                $operations['remove_ssl_cert'] = $this->removeSslCertificate($host, $domain);
                $operations['remove_directories'] = $this->removeDirectories($host, $config);
            }

            // Step 4: Remove local configuration files
            $operations['remove_local_config'] = $this->removeLocalConfig($host, $domain);

            // Check if any operations failed
            foreach ($operations as $name => $operation) {
                if (! $operation['success']) {
                    $success = false;
                    Log::error("VHost deletion failed at step: {$name}", $operation);
                }
            }

            if ($success) {
                Log::info('VHost deleted successfully', [
                    'host' => $host,
                    'domain' => $domain,
                    'username' => $username,
                ]);
            }

            return [
                'success' => $success,
                'domain' => $domain,
                'username' => $username,
                'operations' => $operations,
            ];

        } catch (Exception $e) {
            Log::error('VHost deletion failed', [
                'host' => $host,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate VHost configuration using NetServa's canonical sethost() function
     */
    protected function generateVhostConfigViaSethost(string $host, string $domain): array
    {
        // Execute the canonical NetServa sethost() function on remote server
        $result = $this->remoteExecution->executeAsRoot($host,
            "source ~/.sh/_shrc && source ~/.ns/lib/_shared/setup_core.sh && sethost {$domain} && gethost"
        );

        if (! $result['success']) {
            throw new Exception('Failed to generate VHost configuration via sethost(): '.$result['error']);
        }

        // Parse the environment variables from gethost() output
        $config = [];
        $lines = explode("\n", trim($result['output']));

        foreach ($lines as $line) {
            if (preg_match("/^([A-Z_]+)='(.*)'/", trim($line), $matches)) {
                $config[$matches[1]] = $matches[2];
            }
        }

        if (empty($config)) {
            throw new Exception('No configuration variables generated by sethost()');
        }

        return $config;
    }

    /**
     * Create system user on remote host
     */
    protected function createSystemUser(string $host, array $config): array
    {
        try {
            // Check if user already exists
            $userExists = $this->remoteExecution->executeAsRoot(
                $host,
                "getent passwd | awk -F: '\$3 > 999 && \$3 < 9999 {print}' | grep -E ':{$config['VHOST']}:'"
            );

            if ($userExists['success'] && ! empty(trim($userExists['output']))) {
                return [
                    'success' => true,
                    'message' => "User {$config['UUSER']} already exists",
                    'skipped' => true,
                ];
            }

            // Create sudo group if needed
            $this->remoteExecution->executeAsRoot($host, '[[ $(getent group sudo) ]] || groupadd -r sudo');

            // Determine groups
            $groups = ($config['U_UID'] == 1000) ? '-G sudo,adm' : '';

            // Create user
            $result = $this->remoteExecution->executeAsRoot($host,
                "useradd -M -U -s {$config['U_SHL']} -u {$config['U_UID']} -d {$config['UPATH']} -c '{$config['VHOST']}' {$groups} {$config['UUSER']}"
            );

            if (! $result['success']) {
                throw new Exception('Failed to create user: '.$result['output']);
            }

            // Set password if different from admin password
            if (! empty($config['UPASS']) && $config['UPASS'] !== $config['APASS']) {
                $passResult = $this->remoteExecution->executeAsRoot($host,
                    "echo '{$config['UUSER']}:{$config['UPASS']}' | chpasswd"
                );

                if (! $passResult['success']) {
                    throw new Exception('Failed to set user password: '.$passResult['output']);
                }
            }

            return [
                'success' => true,
                'message' => "User {$config['UUSER']} created successfully",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create database entry
     */
    protected function createDatabaseEntry(string $host, string $domain, array $config): array
    {
        try {
            // Check if vhost already exists in database
            $checkResult = $this->remoteExecution->executeAsRoot($host,
                "echo \"SELECT COUNT(id) FROM vhosts WHERE domain = '{$domain}'\" | {$config['SQCMD']}"
            );

            if ($checkResult['success'] && trim($checkResult['output']) > 0) {
                throw new Exception("VHost {$domain} already exists in database");
            }

            $created = date('Y-m-d H:i:s');

            // Insert vhost record
            $insertSql = "INSERT INTO vhosts (active, created, domain, gid, uid, uname, updated) VALUES (1, '{$created}', '{$domain}', {$config['U_GID']}, {$config['U_UID']}, '{$config['UUSER']}', '{$created}')";

            $result = $this->remoteExecution->executeAsRoot($host,
                "echo \"{$insertSql}\" | {$config['SQCMD']}"
            );

            if (! $result['success']) {
                throw new Exception('Failed to insert database record: '.$result['output']);
            }

            return [
                'success' => true,
                'message' => "Database entry created for {$domain}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create PHP-FPM configuration
     */
    protected function createPhpFpmConfig(string $host, string $domain, array $config): array
    {
        try {
            // Check if PHP-FPM directory exists
            $fpmExists = $this->remoteExecution->executeAsRoot($host, "test -d {$config['C_FPM']}");

            if (! $fpmExists['success']) {
                return [
                    'success' => true,
                    'message' => 'PHP-FPM not available, skipping configuration',
                    'skipped' => true,
                ];
            }

            // Determine pool directory based on OS
            $osType = $config['OSTYP'];
            $poolDir = in_array($osType, ['alpine', 'manjaro'])
                ? "{$config['C_FPM']}/php-fpm.d"
                : "{$config['C_FPM']}/pool.d";

            // Check if pool config already exists
            $poolExists = $this->remoteExecution->executeAsRoot($host, "test -f {$poolDir}/{$domain}.conf");

            if ($poolExists['success']) {
                return [
                    'success' => true,
                    'message' => "PHP-FPM pool {$domain}.conf already exists",
                    'skipped' => true,
                ];
            }

            // Create pool configuration
            $poolConfig = "[{$domain}]\nuser = {$config['U_UID']}\ngroup = {$config['U_GID']}\ninclude = {$config['C_FPM']}/common.conf";

            $result = $this->remoteExecution->executeAsRoot($host,
                "cat > {$poolDir}/{$domain}.conf << 'EOF'\n{$poolConfig}\nEOF"
            );

            if (! $result['success']) {
                throw new Exception('Failed to create PHP-FPM pool: '.$result['output']);
            }

            // Rename default www.conf if it exists
            $wwwExists = $this->remoteExecution->executeAsRoot($host, "test -f {$poolDir}/www.conf");
            if ($wwwExists['success']) {
                $this->remoteExecution->executeAsRoot($host, "mv {$poolDir}/www.conf {$config['C_FPM']}/");
            }

            return [
                'success' => true,
                'message' => "PHP-FPM pool created for {$domain}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create directory structure
     */
    protected function createDirectoryStructure(string $host, array $config): array
    {
        try {
            $directories = [
                $config['UPATH'],
                "{$config['UPATH']}/.cache",
                "{$config['UPATH']}/.ssh",
                "{$config['UPATH']}/.nano",
                "{$config['UPATH']}/.wp-cli",
                "{$config['UPATH']}/bin",
                "{$config['UPATH']}/etc",
                "{$config['UPATH']}/home/u",
                "{$config['UPATH']}/var/log",
                "{$config['UPATH']}/var/run",
                "{$config['UPATH']}/var/tmp",
                "{$config['UPATH']}/var/www/html",
            ];

            // Create all directories
            $result = $this->remoteExecution->executeAsRoot($host,
                'mkdir -p '.implode(' ', array_map('escapeshellarg', $directories))
            );

            if (! $result['success']) {
                throw new Exception('Failed to create directories: '.$result['output']);
            }

            return [
                'success' => true,
                'message' => 'Directory structure created',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create web files
     */
    protected function createWebFiles(string $host, string $domain, array $config): array
    {
        try {
            $operations = [];

            // Create index.html
            $indexHtml = "<!DOCTYPE html><title>{$domain}</title><h1 style=\"text-align:center\">{$domain}</h1>";
            $result = $this->remoteExecution->executeAsRoot($host,
                "cat > {$config['WPATH']}/index.html << 'EOF'\n{$indexHtml}\nEOF"
            );
            $operations['index_html'] = $result['success'];

            // Create phpinfo.php
            $phpInfo = "<?php error_log(__FILE__.' '.\$_SERVER['REMOTE_ADDR']); phpinfo();";
            $result = $this->remoteExecution->executeAsRoot($host,
                "cat > {$config['WPATH']}/phpinfo.php << 'EOF'\n{$phpInfo}\nEOF"
            );
            $operations['phpinfo'] = $result['success'];

            return [
                'success' => true,
                'message' => 'Web files created',
                'operations' => $operations,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set proper permissions
     */
    protected function setPermissions(string $host, array $config): array
    {
        try {
            // Set ownership
            $commands = [
                "chown -R {$config['UUSER']}:{$config['WUGID']} {$config['UPATH']}",
                "chmod 755 {$config['UPATH']}",
                "chmod 755 {$config['WPATH']}",
                "chmod 600 {$config['UPATH']}/.ssh/* 2>/dev/null || true",
            ];

            foreach ($commands as $command) {
                $result = $this->remoteExecution->executeAsRoot($host, $command);
                if (! $result['success']) {
                    Log::warning('Permission command failed', [
                        'command' => $command,
                        'output' => $result['output'],
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Permissions set',
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Run final commands (mirrors end of addvhost script)
     */
    protected function runFinalCommands(string $host, string $domain, array $config): void
    {
        try {
            // Source shell functions and run final commands
            $commands = [
                "source ~/.sh/_shrc && logging {$domain} update",
                "source ~/.sh/_shrc && chshpw {$config['UUSER']} {$config['UPASS']}",
                "source ~/.sh/_shrc && chperms {$domain}",
                'source ~/.sh/_shrc && serva restart web',
            ];

            foreach ($commands as $command) {
                $this->remoteExecution->executeAsRoot($host, $command);
            }

        } catch (Exception $e) {
            Log::warning('Final commands failed', [
                'host' => $host,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Helper methods

    protected function generatePasswords(): array
    {
        return [
            'admin' => $this->generateSecurePassword(),
            'database' => $this->generateSecurePassword(),
            'email' => $this->generateSecurePassword(),
            'user' => $this->generateSecurePassword(),
            'web' => $this->generateSecurePassword(),
        ];
    }

    protected function generateSecurePassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    protected function generateRandomString(int $length): string
    {
        return $this->generateSecurePassword($length);
    }

    protected function getRemoteOsInfo(string $host): array
    {
        $osType = $this->remoteExecution->executeAsRoot($host, "grep -E '^ID=' /etc/os-release | cut -d= -f2 | tr -d '\"'");
        $osRelease = $this->remoteExecution->executeAsRoot($host, "grep -E '^VERSION_CODENAME=' /etc/os-release | cut -d= -f2 | tr -d '\"' || echo 'unknown'");

        return [
            'type' => trim($osType['output'] ?? 'unknown'),
            'release' => trim($osRelease['output'] ?? 'unknown'),
            'mirror' => 'deb.debian.org', // Default mirror
        ];
    }

    protected function determineUidGid(string $host): array
    {
        // Find next available UID/GID in range 1000-9999
        $result = $this->remoteExecution->executeAsRoot($host,
            "getent passwd | awk -F: '\$3 >= 1000 && \$3 < 9999 {print \$3}' | sort -n | tail -1"
        );

        $lastUid = ! empty(trim($result['output'])) ? (int) trim($result['output']) : 999;
        $nextUid = $lastUid + 1;

        // Generate username: sysadm for UID 1000, u1001+ for VHosts
        $username = ($nextUid === 1000) ? 'sysadm' : "u{$nextUid}";

        return [
            'uid' => (string) $nextUid,
            'gid' => (string) $nextUid,
            'username' => $username,
        ];
    }

    protected function getWorkstationHostname(): string
    {
        return gethostname() ?: 'workstation';
    }

    protected function getPhpFpmPath(string $osType): string
    {
        return match ($osType) {
            'alpine' => '/etc/php81/php-fpm.d',
            'debian', 'ubuntu' => '/etc/php/8.4/fpm',
            default => '/etc/php/fpm'
        };
    }

    protected function getPhpVersion(array $osInfo): string
    {
        if ($osInfo['type'] === 'debian' && $osInfo['release'] === 'trixie') {
            return '8.4';
        }

        return '8.3'; // Default
    }

    protected function getWebGroup(string $osType): string
    {
        return match ($osType) {
            'alpine' => 'nginx',
            default => 'www-data'
        };
    }

    protected function getServerIP(string $host): string
    {
        $result = $this->remoteExecution->executeAsRoot($host,
            "ip route get 1.1.1.1 | awk '{print \$7; exit}'"
        );

        return trim($result['output'] ?? '192.168.1.100');
    }

    protected function extractRootDomain(string $domain): string
    {
        $parts = explode('.', $domain);

        return count($parts) >= 2
            ? implode('.', array_slice($parts, -2))
            : $domain;
    }

    protected function extractSubdomain(string $domain): string
    {
        $parts = explode('.', $domain);

        return count($parts) > 2 ? $parts[0] : 'www';
    }

    protected function generateCredentials(string $domain, array $config): array
    {
        return [
            'ssh' => [
                'sftp_host' => $domain,
                'username' => $config['UUSER'],
                'password' => $config['UPASS'],
                'port' => '9 (not port 22)',
                'path' => '/var/www',
                'url' => "sftp://{$config['UUSER']}:{$config['UPASS']}@{$domain}:9/var/www",
            ],
            'http_auth' => [
                'admin_web' => "https://{$domain}/hcp",
                'username' => '[TO BE SET]',
                'password' => '[TO BE SET]',
            ],
            'hcp_auth' => [
                'admin_web' => "https://{$domain}/hcp/?o=auth",
                'username' => $config['AMAIL'],
                'password' => $config['APASS'],
            ],
            'phpmyadmin' => [
                'admin_web' => "https://{$domain}/hcp/phpmyadmin",
                'username' => $config['ADMIN'],
                'password' => $config['DPASS'],
            ],
            'roundcube' => [
                'webmail' => "https://{$domain}/",
            ],
        ];
    }

    /**
     * Remove system user on remote server
     */
    protected function removeSystemUser(string $host, string $username): array
    {
        $command = "if id -u {$username} >/dev/null 2>&1; then userdel -rf {$username}; echo 'User {$username} removed'; else echo 'User {$username} not found'; fi";

        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "System user {$username} removed" : "Failed to remove user {$username}",
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove database entry for VHost
     */
    protected function removeDatabaseEntry(string $host, string $domain, array $config): array
    {
        $dbName = $config['DNAME'] ?? null;
        $dbUser = $config['DUSER'] ?? null;

        if (! $dbName || ! $dbUser) {
            return [
                'success' => true,
                'message' => 'No database configuration found',
            ];
        }

        $commands = [
            "mysql -e \"DROP DATABASE IF EXISTS {$dbName};\"",
            "mysql -e \"DROP USER IF EXISTS '{$dbUser}'@'localhost';\"",
            'mysql -e "FLUSH PRIVILEGES;"',
        ];

        $command = implode(' && ', $commands);
        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Database {$dbName} and user {$dbUser} removed" : 'Failed to remove database',
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove Nginx configuration
     */
    protected function removeNginxConfig(string $host, string $domain): array
    {
        $commands = [
            "rm -f /etc/nginx/sites-available/{$domain}",
            "rm -f /etc/nginx/sites-enabled/{$domain}",
            'nginx -t && systemctl reload nginx',
        ];

        $command = implode(' && ', $commands);
        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "Nginx configuration removed for {$domain}" : 'Failed to remove Nginx config',
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove PHP-FPM configuration
     */
    protected function removePhpFpmConfig(string $host, string $domain): array
    {
        $command = "rm -f /etc/php*/fpm/pool.d/{$domain}.conf && systemctl reload php*-fpm";
        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "PHP-FPM pool removed for {$domain}" : 'Failed to remove PHP-FPM config',
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove SSL certificate
     */
    protected function removeSslCertificate(string $host, string $domain): array
    {
        $command = "rm -rf /etc/ssl/le/{$domain} && rm -f /etc/ssl/le/{$domain}.*";
        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "SSL certificate removed for {$domain}" : 'Failed to remove SSL certificate',
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove directories
     */
    protected function removeDirectories(string $host, array $config): array
    {
        $upath = $config['UPATH'] ?? '';
        $wpath = $config['WPATH'] ?? '';
        $mpath = $config['MPATH'] ?? '';

        $paths = array_filter([$upath, $wpath, $mpath]);

        if (empty($paths)) {
            return [
                'success' => true,
                'message' => 'No directories to remove',
            ];
        }

        $command = 'rm -rf '.implode(' ', $paths);
        $result = $this->remoteExecution->executeAsRoot($host, $command);

        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Directories removed: '.implode(', ', $paths) : 'Failed to remove directories',
            'output' => $result['output'] ?? '',
        ];
    }

    /**
     * Remove local configuration files
     */
    protected function removeLocalConfig(string $host, string $domain): array
    {
        $configPath = env('HOME')."/.ns/var/{$host}/{$domain}";
        $confPath = $configPath.'.conf';

        $success = true;
        $removed = [];

        if (file_exists($configPath)) {
            if (unlink($configPath)) {
                $removed[] = $configPath;
            } else {
                $success = false;
            }
        }

        if (file_exists($confPath)) {
            if (unlink($confPath)) {
                $removed[] = $confPath;
            } else {
                $success = false;
            }
        }

        return [
            'success' => $success,
            'message' => $success ? 'Local config files removed: '.implode(', ', $removed) : 'Failed to remove local config files',
            'removed_files' => $removed,
        ];
    }
}
