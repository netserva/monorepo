<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Legacy Import Service - NetServa 3.0
 *
 * Discovers and imports existing NetServa 1.0 vhosts from remote vnodes
 *
 * Process:
 * 1. SSH to vnode
 * 2. Find /srv/* directories (existing vhosts)
 * 3. Read /root/.vhosts/* config files (1.0 format)
 * 4. Parse legacy config variables
 * 5. Create FleetVhost records with 'discovered' status
 * 6. Store original config in legacy_config field
 * 7. Import parsed variables to vconfs table
 *
 * Created: 20251009
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class LegacyImportService
{
    protected RemoteExecutionService $remoteExecution;

    protected NetServaConfigurationService $configService;

    public function __construct(
        RemoteExecutionService $remoteExecution,
        NetServaConfigurationService $configService
    ) {
        $this->remoteExecution = $remoteExecution;
        $this->configService = $configService;
    }

    /**
     * Discover existing vhosts on a vnode (NetServa 1.0 format)
     *
     * @param  FleetVnode  $vnode  The vnode to scan
     * @return array Result with discovered vhosts count
     */
    public function discoverLegacyVhosts(FleetVnode $vnode): array
    {
        try {
            Log::info('Discovering legacy vhosts', ['vnode' => $vnode->name]);

            // Step 1: Find /srv/* directories on remote vnode
            $vhosts = $this->findVhostDirectories($vnode);

            if (empty($vhosts)) {
                return [
                    'success' => true,
                    'discovered' => 0,
                    'message' => 'No vhosts found in /srv/',
                ];
            }

            Log::info('Found vhost directories', [
                'vnode' => $vnode->name,
                'count' => count($vhosts),
                'vhosts' => $vhosts,
            ]);

            // Step 2: Import each discovered vhost
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($vhosts as $domain) {
                try {
                    $result = $this->importLegacyVhost($vnode, $domain);

                    if ($result['success']) {
                        $imported++;
                    } else {
                        $skipped++;
                        $errors[] = [
                            'domain' => $domain,
                            'error' => $result['error'] ?? 'Unknown error',
                        ];
                    }
                } catch (Exception $e) {
                    $skipped++;
                    $errors[] = [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Failed to import vhost', [
                        'vnode' => $vnode->name,
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'discovered' => count($vhosts),
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ];

        } catch (Exception $e) {
            Log::error('Legacy discovery failed', [
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find vhost directories in /srv/ on remote vnode
     *
     * @param  FleetVnode  $vnode  The vnode to scan
     * @return array List of domain names (directory names from /srv/)
     */
    protected function findVhostDirectories(FleetVnode $vnode): array
    {
        // Use simpler command approach instead of executeScript for better debugging
        $command = <<<'BASH'
if [[ ! -d /srv ]]; then exit 0; fi; cd /srv && for d in */; do [[ "$d" =~ ^(http|lost\+found|\.snapshot)/$ ]] || echo "${d%/}"; done
BASH;

        $result = $this->remoteExecution->executeAsRoot(
            host: $vnode->name,
            command: $command,
            useSudo: true
        );

        if (! $result['success']) {
            $errorMsg = $result['error'] ?? 'Unknown error';
            $output = $result['output'] ?? '';

            Log::error('Failed to scan /srv/ directory', [
                'vnode' => $vnode->name,
                'error' => $errorMsg,
                'output' => $output,
                'exit_code' => $result['exit_code'] ?? null,
            ]);

            throw new Exception("Failed to scan /srv/ directory: {$errorMsg}");
        }

        // Parse output into array of domains
        $output = trim($result['output']);
        if (empty($output)) {
            return [];
        }

        $domains = array_filter(explode("\n", $output), fn ($d) => ! empty(trim($d)) && ! str_starts_with($d, '#'));

        return array_values($domains);
    }

    /**
     * Import a single legacy vhost
     *
     * @param  FleetVnode  $vnode  The vnode this vhost is on
     * @param  string  $domain  The domain name
     * @return array Result with success status
     */
    protected function importLegacyVhost(FleetVnode $vnode, string $domain): array
    {
        // Check if already imported
        $existing = FleetVhost::where('domain', $domain)
            ->where('vnode_id', $vnode->id)
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'error' => 'VHost already exists in database',
            ];
        }

        // Read legacy config file if exists
        $legacyConfig = $this->readLegacyConfig($vnode, $domain);

        // Parse legacy config or generate new config
        if ($legacyConfig) {
            $platformVars = $this->parseLegacyConfig($legacyConfig, $domain);
        } else {
            // No legacy config file, generate from discovery
            Log::info('No legacy config found, generating new config', [
                'vnode' => $vnode->name,
                'domain' => $domain,
            ]);

            $vhostConfig = $this->configService->generateVhostConfig($vnode->name, $domain);
            $platformVars = $this->configService->extractPlatformVariables($vhostConfig);
        }

        // Create FleetVhost record with 'discovered' migration_status
        $fleetVhost = FleetVhost::create([
            'domain' => $domain,
            'vnode_id' => $vnode->id,
            'instance_type' => 'vhost',
            'status' => 'active', // Operational status (assume active since it exists)
            'is_active' => true,
            'migration_status' => 'discovered', // Migration tracking status
            'legacy_config' => $legacyConfig, // Store original config
            'discovered_at' => now(),
        ]);

        // Store variables in vconfs table
        $fleetVhost->setEnvVars($platformVars);

        Log::info('Legacy vhost imported', [
            'vnode' => $vnode->name,
            'domain' => $domain,
            'fleet_vhost_id' => $fleetVhost->id,
            'has_legacy_config' => ! empty($legacyConfig),
        ]);

        return [
            'success' => true,
            'fleet_vhost_id' => $fleetVhost->id,
            'domain' => $domain,
        ];
    }

    /**
     * Read legacy config file from /root/.vhosts/$VHOST on remote vnode
     *
     * @param  FleetVnode  $vnode  The vnode
     * @param  string  $domain  The domain name
     * @return array|null Parsed config as associative array, or null if not found
     */
    protected function readLegacyConfig(FleetVnode $vnode, string $domain): ?array
    {
        $script = <<<BASH
#!/bin/bash
# Read legacy config file if exists
if [[ -f /root/.vhosts/{$domain} ]]; then
    cat /root/.vhosts/{$domain}
fi
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            asRoot: true
        );

        if (! $result['success'] || empty(trim($result['output']))) {
            return null;
        }

        // Parse bash variable assignments
        return $this->parseBashVariables($result['output']);
    }

    /**
     * Parse bash variable assignments from legacy config file
     *
     * Parses lines like: VHOST='example.com'
     *
     * @param  string  $content  Raw bash config file content
     * @return array Associative array of variables
     */
    protected function parseBashVariables(string $content): array
    {
        $variables = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse VAR=value or VAR='value' or VAR="value"
            if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.+)$/', $line, $matches)) {
                $var = $matches[1];
                $value = $matches[2];

                // Remove quotes
                $value = trim($value, '"\'');

                $variables[$var] = $value;
            }
        }

        return $variables;
    }

    /**
     * Parse legacy config variables into NetServa 3.0 format
     *
     * @param  array  $legacyConfig  Parsed legacy config
     * @param  string  $domain  Domain name (fallback if not in config)
     * @return array Platform variables for vconfs table
     */
    protected function parseLegacyConfig(array $legacyConfig, string $domain): array
    {
        // Map legacy variables to NetServa 3.0 format
        // Most variables have same names, but add defaults for missing ones

        $platformVars = [];

        // Core variables
        $platformVars['VHOST'] = $legacyConfig['VHOST'] ?? $domain;
        $platformVars['VNODE'] = $legacyConfig['HNAME'] ?? $legacyConfig['VNODE'] ?? 'unknown';

        // User variables
        $platformVars['UUSER'] = $legacyConfig['UUSER'] ?? 'u1000';
        $platformVars['U_UID'] = $legacyConfig['U_UID'] ?? '1000';
        $platformVars['U_GID'] = $legacyConfig['U_GID'] ?? $platformVars['U_UID'];
        $platformVars['U_SHL'] = $legacyConfig['U_SHL'] ?? '/bin/bash';

        // Paths
        $platformVars['VPATH'] = $legacyConfig['VPATH'] ?? '/srv';
        $platformVars['UPATH'] = $legacyConfig['UPATH'] ?? "/srv/{$domain}";
        $platformVars['WPATH'] = $legacyConfig['WPATH'] ?? "{$platformVars['UPATH']}/web";
        $platformVars['MPATH'] = $legacyConfig['MPATH'] ?? "{$platformVars['UPATH']}/msg";
        $platformVars['BPATH'] = $legacyConfig['BPATH'] ?? '/home/backups';
        $platformVars['DPATH'] = $legacyConfig['DPATH'] ?? '/var/lib/sqlite/sysadm/sysadm.db';

        // Config paths
        $platformVars['C_FPM'] = $legacyConfig['C_FPM'] ?? '/etc/php/8.2/fpm';
        $platformVars['C_WEB'] = $legacyConfig['C_WEB'] ?? '/etc/nginx';
        $platformVars['C_SSL'] = $legacyConfig['C_SSL'] ?? '/etc/ssl';
        $platformVars['C_SQL'] = $legacyConfig['C_SQL'] ?? '/etc/mysql';
        $platformVars['C_DNS'] = $legacyConfig['C_DNS'] ?? '/etc/powerdns';
        $platformVars['CSMTP'] = $legacyConfig['CSMTP'] ?? '/etc/postfix';
        $platformVars['CIMAP'] = $legacyConfig['CIMAP'] ?? '/etc/dovecot';

        // Database variables
        $platformVars['DTYPE'] = $legacyConfig['DTYPE'] ?? 'mysql';
        $platformVars['DHOST'] = $legacyConfig['DHOST'] ?? 'localhost';
        $platformVars['DPORT'] = $legacyConfig['DPORT'] ?? '3306';
        $platformVars['DNAME'] = $legacyConfig['DNAME'] ?? 'sysadm';
        $platformVars['DUSER'] = $legacyConfig['DUSER'] ?? 'sysadm';
        $platformVars['DPASS'] = $legacyConfig['DPASS'] ?? $this->generateSecurePassword();
        $platformVars['SQCMD'] = $legacyConfig['SQCMD'] ?? 'mariadb -BN sysadm';
        $platformVars['EXMYS'] = $legacyConfig['EXMYS'] ?? 'mariadb -BN sysadm';
        $platformVars['EXSQL'] = $legacyConfig['EXSQL'] ?? 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db';
        $platformVars['SQDNS'] = $legacyConfig['SQDNS'] ?? 'mariadb -BN pdns';
        $platformVars['DBMYS'] = $legacyConfig['DBMYS'] ?? '/var/lib/mysql';
        $platformVars['DBSQL'] = $legacyConfig['DBSQL'] ?? '/var/lib/sqlite';

        // Admin variables
        $platformVars['ADMIN'] = $legacyConfig['ADMIN'] ?? 'sysadm';
        $platformVars['AHOST'] = $legacyConfig['AHOST'] ?? $platformVars['VNODE'];
        $platformVars['AMAIL'] = $legacyConfig['AMAIL'] ?? "admin@{$domain}";
        $platformVars['ANAME'] = $legacyConfig['ANAME'] ?? 'System Administrator';
        $platformVars['APASS'] = $legacyConfig['APASS'] ?? $this->generateSecurePassword();
        $platformVars['A_UID'] = $legacyConfig['A_UID'] ?? '1000';
        $platformVars['A_GID'] = $legacyConfig['A_GID'] ?? '1000';

        // Passwords (generate new if missing)
        $platformVars['UPASS'] = $legacyConfig['UPASS'] ?? $this->generateSecurePassword();
        $platformVars['WPASS'] = $legacyConfig['WPASS'] ?? $this->generateSecurePassword();
        $platformVars['EPASS'] = $legacyConfig['EPASS'] ?? $this->generateSecurePassword();
        $platformVars['WPUSR'] = $legacyConfig['WPUSR'] ?? $this->generateRandomString(6);

        // System variables
        $platformVars['OSTYP'] = $legacyConfig['OSTYP'] ?? 'debian';
        $platformVars['OSREL'] = $legacyConfig['OSREL'] ?? 'bookworm';
        $platformVars['OSMIR'] = $legacyConfig['OSMIR'] ?? 'deb.debian.org';
        $platformVars['IP4_0'] = $legacyConfig['IP4_0'] ?? '0.0.0.0';

        // Domain variables
        $platformVars['HNAME'] = $legacyConfig['HNAME'] ?? $platformVars['VNODE'];
        $platformVars['HDOMN'] = $legacyConfig['HDOMN'] ?? $domain;
        $platformVars['MHOST'] = $legacyConfig['MHOST'] ?? $domain;

        // Web/mail variables
        $platformVars['WUGID'] = $legacyConfig['WUGID'] ?? 'www-data';
        $platformVars['VUSER'] = $legacyConfig['VUSER'] ?? 'admin';
        $platformVars['V_PHP'] = $legacyConfig['V_PHP'] ?? '8.2';

        // Timezone
        $platformVars['TAREA'] = $legacyConfig['TAREA'] ?? 'Australia';
        $platformVars['TCITY'] = $legacyConfig['TCITY'] ?? 'Sydney';

        return $platformVars;
    }

    /**
     * Generate secure password (12 chars alphanumeric)
     */
    protected function generateSecurePassword(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Generate random string (lowercase only)
     */
    protected function generateRandomString(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $string;
    }
}
