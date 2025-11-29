<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Log;
use NetServa\Core\Contracts\ConfigManagerInterface;
use NetServa\Core\Enums\NetServaConstants;
use NetServa\Core\Enums\NetServaStrings;
use NetServa\Core\Enums\OsType;
use NetServa\Core\ValueObjects\OsConfiguration;
use NetServa\Core\ValueObjects\VhostConfiguration;
use NetServa\Core\ValueObjects\VhostPasswords;
use NetServa\Core\ValueObjects\VhostPaths;

/**
 * NetServa Configuration Service
 *
 * Pure PHP implementation of NetServa's sethost/gethost functionality.
 * Replaces bash dependency with type-safe Laravel service.
 */
class NetServaConfigurationService implements ConfigManagerInterface
{
    protected RemoteExecutionService $remoteExecution;

    protected LazyConfigurationCache $cache;

    public function __construct(
        RemoteExecutionService $remoteExecution,
        LazyConfigurationCache $cache
    ) {
        $this->remoteExecution = $remoteExecution;
        $this->cache = $cache;
    }

    /**
     * Generate complete VHost configuration
     *
     * Pure PHP implementation of NetServa's sethost() function
     */
    public function generateVhostConfig(string $VNODE, string $VHOST): VhostConfiguration
    {
        Log::info('Generating VHost configuration', [
            'VNODE' => $VNODE,
            'VHOST' => $VHOST,
        ]);

        return $this->cache->getVhostConfiguration(
            $VNODE,
            $VHOST,
            fn () => $this->detectRemoteOs($VNODE),
            fn () => $this->getServerFqdn($VNODE),
            fn () => $this->getNextAvailableUid($VNODE),
            fn () => $this->getServerIp($VNODE)
        );
    }

    /**
     * Generate VHost configuration from cached dependencies (used by LazyConfigurationCache)
     */
    public function generateVhostConfigFromCache(
        string $VNODE,
        string $VHOST,
        int $U_UID,
        OsConfiguration $osConfig,
        string $serverIp
    ): VhostConfiguration {
        // Generate username using constants
        $UUSER = NetServaConstants::getUsernameForUid($U_UID);

        // Generate secure passwords
        $passwords = $this->generateSecurePasswords();

        // Generate paths
        $paths = $this->generateVhostPaths($VHOST, $osConfig);

        return new VhostConfiguration(
            VHOST: $VHOST,
            VNODE: $VNODE,
            U_UID: $U_UID,
            U_GID: $U_UID, // Same as UID in NetServa
            UUSER: $UUSER,
            passwords: $passwords,
            paths: $paths,
            osConfig: $osConfig,
            IP4_0: $serverIp
        );
    }

    /**
     * Detect remote operating system configuration
     */
    protected function detectRemoteOs(string $VNODE): OsConfiguration
    {
        $result = $this->remoteExecution->executeAsRoot($VNODE,
            'grep -E "^ID=|^VERSION_CODENAME=" /etc/os-release'
        );

        if (! $result['success']) {
            // Default to Debian if detection fails
            return OsConfiguration::forType(OsType::DEBIAN);
        }

        $osType = 'debian';
        $osRelease = null;

        foreach (explode("\n", $result['output']) as $line) {
            if (str_starts_with($line, 'ID=')) {
                $osType = trim(str_replace(['ID=', '"'], '', $line));
            } elseif (str_starts_with($line, 'VERSION_CODENAME=')) {
                $osRelease = trim(str_replace(['VERSION_CODENAME=', '"'], '', $line));
            }
        }

        return OsConfiguration::fromStrings($osType, $osRelease);
    }

    /**
     * Get server FQDN using database-first approach
     *
     * Strategy order:
     * 1. Load from fleet_vnodes.fqdn (primary source - set during discovery)
     * 2. Try hostname -f (for backwards compatibility)
     * 3. Try /etc/hosts parsing
     * 4. Try DNS reverse lookup
     * 5. Return short hostname as fallback
     */
    protected function getServerFqdn(string $VNODE): string
    {
        // Strategy 1: Load from database (fastest, most reliable)
        $vnode = \NetServa\Fleet\Models\FleetVnode::where('name', $VNODE)->first();
        if ($vnode && $vnode->fqdn && $this->isValidFqdn($vnode->fqdn)) {
            return $vnode->fqdn;
        }

        // Strategy 2: Try hostname -f
        $fqdn = $this->getServerFqdnFromHostname($VNODE);
        if ($this->isValidFqdn($fqdn)) {
            return $fqdn;
        }

        // Strategy 3: Try /etc/hosts
        $fqdn = $this->getServerFqdnFromEtcHosts($VNODE);
        if ($this->isValidFqdn($fqdn)) {
            return $fqdn;
        }

        // Strategy 4: Try DNS reverse lookup
        $fqdn = $this->getServerFqdnFromDns($VNODE);
        if ($this->isValidFqdn($fqdn)) {
            return $fqdn;
        }

        // Fallback: Return short hostname (will need manual configuration)
        Log::warning('Could not determine valid FQDN for VNode', [
            'vnode' => $VNODE,
            'fallback' => $VNODE,
            'suggestion' => "Run 'php artisan addfleet {$VNODE} --force' to detect FQDN",
        ]);

        return $VNODE;
    }

    /**
     * Get FQDN from hostname -f command
     */
    protected function getServerFqdnFromHostname(string $VNODE): string
    {
        $result = $this->remoteExecution->executeAsRoot($VNODE, 'hostname -f | tr "A-Z" "a-z"');

        return $result['success'] ? trim($result['output']) : '';
    }

    /**
     * Get FQDN from /etc/hosts file
     */
    protected function getServerFqdnFromEtcHosts(string $VNODE): string
    {
        // Look for 127.0.1.1 entry (Debian/Ubuntu pattern)
        $result = $this->remoteExecution->executeAsRoot($VNODE,
            "grep -E '^127\\.0\\.1\\.1|^127\\.0\\.0\\.1' /etc/hosts | awk '{print \$2}' | grep '\\.'"
        );

        return $result['success'] ? trim($result['output']) : '';
    }

    /**
     * Get FQDN from DNS reverse lookup
     */
    protected function getServerFqdnFromDns(string $VNODE): string
    {
        try {
            $ip = $this->getServerIp($VNODE);
            $fqdn = gethostbyaddr($ip);

            // gethostbyaddr returns IP if lookup fails
            return ($fqdn !== $ip) ? strtolower($fqdn) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Validate if hostname is a proper FQDN
     *
     * Valid FQDN must:
     * - Contain at least one dot
     * - Use only alphanumeric, hyphens, and dots
     * - Not start/end with hyphen or dot
     */
    protected function isValidFqdn(string $hostname): bool
    {
        if (empty($hostname) || ! str_contains($hostname, '.')) {
            return false;
        }

        return (bool) preg_match(
            '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i',
            $hostname
        );
    }

    /**
     * Determine VHost UID (mirrors setup_core.sh logic)
     */
    protected function determineVhostUid(string $VNODE, string $serverFqdn, string $VHOST): int
    {
        // NetServa logic: if VHOST == server FQDN, use admin UID
        if ($serverFqdn === $VHOST) {
            return NetServaConstants::ADMIN_UID->value;
        }

        // Otherwise, get next available UID
        return $this->getNextAvailableUid($VNODE);
    }

    /**
     * Get next available UID - finds first gap in sequence
     *
     * NetServa 3.0: Fills gaps in UID sequence rather than always using highest + 1
     * Example: If UIDs are 1001, 1002, 1004, 1005 → returns 1003 (fills gap)
     *
     * Returns first available UID starting from ADMIN_UID + 1 (1001)
     * If no users exist, returns 1001 (1000 is reserved for admin)
     */
    protected function getNextAvailableUid(string $VNODE): int
    {
        $adminUid = NetServaConstants::ADMIN_UID->value;
        $maxUid = NetServaConstants::MAX_USER_UID->value;

        // Get all UIDs in range as array
        $result = $this->remoteExecution->executeAsRoot($VNODE,
            "getent passwd | awk -F: '\$3 > {$adminUid} && \$3 < {$maxUid} {print \$3}' | sort -n"
        );

        if (! $result['success'] || empty(trim($result['output']))) {
            // No users found - return first user UID (1001 = ADMIN_UID + 1)
            return $adminUid + 1; // 1000 + 1 = 1001
        }

        // Parse all existing UIDs into array
        $existingUids = array_map('intval', array_filter(explode("\n", trim($result['output']))));

        // Find first gap in sequence starting from 1001
        $candidateUid = $adminUid + 1; // Start at 1001
        foreach ($existingUids as $uid) {
            if ($uid === $candidateUid) {
                // This UID is taken, try next
                $candidateUid++;
            } elseif ($uid > $candidateUid) {
                // Found a gap! Return the missing UID
                return $candidateUid;
            }
        }

        // No gaps found, return next after highest
        return $candidateUid;
    }

    /**
     * Generate username (mirrors setup_core.sh logic)
     */
    protected function generateUsername(int $U_UID): string
    {
        return NetServaConstants::getUsernameForUid($U_UID);
    }

    /**
     * Generate secure passwords (mirrors setup_core.sh password generation)
     */
    protected function generateSecurePasswords(): VhostPasswords
    {
        return new VhostPasswords(
            admin: $this->generateSecurePassword(),
            database: $this->generateSecurePassword(),
            email: $this->generateSecurePassword(),
            user: $this->generateSecurePassword(),
            web: $this->generateSecurePassword(),
            wordpress: $this->generateRandomString(NetServaConstants::WORDPRESS_USER_LENGTH->value)
        );
    }

    /**
     * Generate secure password (12 chars, alphanumeric)
     */
    protected function generateSecurePassword(?int $length = null): string
    {
        $length ??= NetServaConstants::SECURE_PASSWORD_LENGTH->value;
        $characters = NetServaStrings::ALPHANUMERIC_CHARS->value;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Generate random string (lowercase letters only)
     */
    protected function generateRandomString(int $length): string
    {
        $characters = NetServaStrings::LOWERCASE_CHARS->value;
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $string;
    }

    /**
     * Generate VHost paths (NetServa 3.0 /srv/ layout)
     */
    protected function generateVhostPaths(string $VHOST, OsConfiguration $osConfig): VhostPaths
    {
        return new VhostPaths(
            vhost: $VHOST,
            vpath: '/srv',
            upath: "/srv/{$VHOST}",
            wpath: "/srv/{$VHOST}/web",
            mpath: "/srv/{$VHOST}/msg",
            bpath: '/home/backups',
            dbpath: '/var/lib/sqlite/sysadm/sysadm.db',
            sslPath: '/etc/ssl',
            phpFpmPath: $osConfig->type->getPhpFpmPath(),
            nginxPath: '/etc/nginx',
            postfixPath: '/etc/postfix',
            dovecotPath: '/etc/dovecot',
            dnsPath: $osConfig->type->getDnsPath(),
            mysqlPath: $osConfig->type->getMysqlPath()
        );
    }

    /**
     * Get server IP address
     */
    protected function getServerIp(string $VNODE): string
    {
        $result = $this->remoteExecution->executeAsRoot($VNODE,
            "ip -4 route get 1.1.1.1 | awk '/src/ {print \$7}'"
        );

        return $result['success'] ? trim($result['output']) : '192.168.1.1';
    }

    /**
     * NetServa 3.0 INI Configuration Management
     *
     * Implements the three-tier configuration hierarchy:
     * Tier 1: Global API credentials (.env)
     * Tier 2: Platform variables ($NSVAR/$VNODE/$VHOST.ini)
     * Tier 3: Database storage
     */

    /**
     * Load INI configuration file
     *
     * @param  string  $identifier  Configuration identifier (VHOST or path)
     * @return array Configuration data as associative array
     */
    public function load(string $identifier): array
    {
        $iniPath = $this->getIniPath($identifier);

        if (! file_exists($iniPath)) {
            return [];
        }

        $config = parse_ini_file($iniPath, true, INI_SCANNER_TYPED);

        if ($config === false) {
            Log::error('Failed to parse INI file', ['path' => $iniPath]);

            return [];
        }

        return $config;
    }

    /**
     * Save configuration to INI file
     *
     * @param  string  $identifier  Configuration identifier
     * @param  array  $config  Configuration data
     * @return bool Success status
     */
    public function save(string $identifier, array $config): bool
    {
        $iniPath = $this->getIniPath($identifier);
        $iniDir = dirname($iniPath);

        // Ensure directory exists
        if (! is_dir($iniDir)) {
            if (! mkdir($iniDir, 0755, true)) {
                Log::error('Failed to create config directory', ['path' => $iniDir]);

                return false;
            }
        }

        $iniContent = $this->arrayToIni($config);

        if (file_put_contents($iniPath, $iniContent) === false) {
            Log::error('Failed to write INI file', ['path' => $iniPath]);

            return false;
        }

        Log::info('Configuration saved', ['path' => $iniPath]);

        return true;
    }

    /**
     * Check if configuration exists
     *
     * @param  string  $identifier  Configuration identifier
     * @return bool True if configuration exists
     */
    public function exists(string $identifier): bool
    {
        return file_exists($this->getIniPath($identifier));
    }

    /**
     * Delete configuration
     *
     * @param  string  $identifier  Configuration identifier
     * @return bool Success status
     */
    public function delete(string $identifier): bool
    {
        $iniPath = $this->getIniPath($identifier);

        if (! file_exists($iniPath)) {
            return true; // Already deleted
        }

        if (unlink($iniPath)) {
            Log::info('Configuration deleted', ['path' => $iniPath]);

            return true;
        }

        Log::error('Failed to delete configuration', ['path' => $iniPath]);

        return false;
    }

    /**
     * List all available configurations
     *
     * @return array List of configuration identifiers
     */
    public function list(): array
    {
        $nsvar = env('NSVAR', $_SERVER['HOME'].'/.ns/var');
        $configurations = [];

        if (! is_dir($nsvar)) {
            return $configurations;
        }

        // Find all .ini files in $NSVAR/*/*.ini structure
        $vnodes = glob($nsvar.'/*', GLOB_ONLYDIR);

        foreach ($vnodes as $vnodePath) {
            $vnode = basename($vnodePath);
            $iniFiles = glob($vnodePath.'/*.ini');

            foreach ($iniFiles as $iniFile) {
                $vhost = basename($iniFile, '.ini');
                $configurations[] = "{$vnode}/{$vhost}";
            }
        }

        return $configurations;
    }

    /**
     * Validate configuration structure
     *
     * @param  array  $config  Configuration data
     * @return bool True if valid
     */
    public function validate(array $config): bool
    {
        // Check for required sections
        $requiredSections = ['metadata', 'platform', 'credentials', 'services'];

        foreach ($requiredSections as $section) {
            if (! isset($config[$section]) || ! is_array($config[$section])) {
                Log::warning('Missing required config section', ['section' => $section]);

                return false;
            }
        }

        // Validate metadata section
        if (! isset($config['metadata']['version']) || ! isset($config['metadata']['vhost'])) {
            Log::warning('Invalid metadata section in config');

            return false;
        }

        return true;
    }

    /**
     * Backup configuration before changes
     *
     * @param  string  $identifier  Configuration identifier
     * @return string|null Backup file path on success, null on failure
     */
    public function backup(string $identifier): ?string
    {
        $iniPath = $this->getIniPath($identifier);

        if (! file_exists($iniPath)) {
            return null;
        }

        $backupPath = $iniPath.'.backup.'.date('Y-m-d_H-i-s');

        if (copy($iniPath, $backupPath)) {
            Log::info('Configuration backed up', [
                'original' => $iniPath,
                'backup' => $backupPath,
            ]);

            return $backupPath;
        }

        Log::error('Failed to backup configuration', ['path' => $iniPath]);

        return null;
    }

    /**
     * Generate INI file path from identifier
     */
    protected function getIniPath(string $identifier): string
    {
        $nsvar = env('NSVAR', $_SERVER['HOME'].'/.ns/var');

        // Handle VNODE/VHOST format
        if (str_contains($identifier, '/')) {
            [$vnode, $vhost] = explode('/', $identifier, 2);

            return "{$nsvar}/{$vnode}/{$vhost}.ini";
        }

        // Fallback: treat as direct path
        return $identifier;
    }

    /**
     * Convert array to INI format
     */
    protected function arrayToIni(array $config): string
    {
        $ini = '';

        foreach ($config as $section => $values) {
            $ini .= "[{$section}]\n";

            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $ini .= "{$key} = ".$this->formatIniValue($value)."\n";
                }
            }

            $ini .= "\n";
        }

        return $ini;
    }

    /**
     * Format value for INI file
     */
    protected function formatIniValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value) && str_contains($value, ' ')) {
            return '"'.addslashes($value).'"';
        }

        return (string) $value;
    }

    /**
     * Generate complete INI configuration from VHost configuration
     */
    public function generateIniConfig(string $VNODE, string $VHOST): array
    {
        $vhostConfig = $this->generateVhostConfig($VNODE, $VHOST);

        return [
            'metadata' => [
                'version' => '3.0',
                'vhost' => $VHOST,
                'vnode' => $VNODE,
                'generated' => date('Y-m-d H:i:s'),
            ],
            'platform' => $this->extractPlatformVariables($vhostConfig),
            'credentials' => $this->extractCredentials($vhostConfig),
            'services' => $this->extractServiceConfig($vhostConfig),
        ];
    }

    /**
     * Extract 54 canonical platform variables
     *
     * NetServa 3.0: ALL values FULLY EXPANDED (no $VAR references)
     * Database stores concrete values ready for immediate use
     */
    public function extractPlatformVariables(VhostConfiguration $config): array
    {
        // Get dynamic values from VhostConfiguration (includes database detection)
        $configVars = $config->toEnvironmentArray();

        // Compute base values for expansion
        $dname = 'sysadm';
        $duser = 'sysadm';
        $dpath = dirname($config->paths->dbpath);

        // Detect web server group from OS type
        $wugid = match ($config->osConfig->type) {
            OsType::DEBIAN, OsType::UBUNTU => 'www-data',
            OsType::ALPINE, OsType::MANJARO, OsType::CACHYOS => 'http',
            default => 'nginx',
        };

        return [
            'ADMIN' => 'sysadm',
            'AHOST' => $config->VNODE,
            'AMAIL' => "admin@{$config->VHOST}",
            'ANAME' => 'NetServa Admin',
            'APASS' => $config->passwords->admin,
            'A_GID' => (string) $config->U_GID,
            'A_UID' => (string) $config->U_UID,
            'BPATH' => $config->paths->bpath,
            'CIMAP' => $config->paths->dovecotPath,
            'CSMTP' => $config->paths->postfixPath,
            'C_DNS' => $config->paths->dnsPath,
            'C_FPM' => $config->paths->phpFpmPath,
            'C_SQL' => $config->paths->mysqlPath,
            'C_WEB' => $config->paths->nginxPath,
            'DBMYS' => '/var/lib/mysql',
            'DBSQL' => '/var/lib/sqlite',
            'DHOST' => 'localhost',
            'DNAME' => $dname,
            'DPASS' => $config->passwords->database,
            'DPATH' => $dpath,
            'DPORT' => '3306',
            'DTYPE' => $configVars['DTYPE'],  // ✅ Use dynamic value (mysql or sqlite)
            'DUSER' => $duser,
            'EPASS' => $config->passwords->email,
            // ✅ FULLY EXPANDED - no $VAR references!
            'EXMYS' => $configVars['EXMYS'],  // ✅ Use dynamic value (mariadb or mysql)
            'EXSQL' => $configVars['EXSQL'],  // ✅ Use dynamic value
            'HDOMN' => $configVars['HDOMN'],
            'HNAME' => $configVars['HNAME'],
            'IP4_0' => $config->IP4_0,
            'MHOST' => $configVars['MHOST'],
            'MPATH' => $config->paths->mpath,
            'OSMIR' => $config->osConfig->mirror,
            'OSREL' => $config->osConfig->release,
            'OSTYP' => $config->osConfig->type->value,
            // ✅ FULLY EXPANDED - no $VAR references!
            'SQCMD' => $configVars['SQCMD'],  // ✅ Use dynamic value (mariadb or sqlite3)
            'SQDNS' => $configVars['SQDNS'],  // ✅ Use dynamic value
            'TAREA' => 'Australia',
            'TCITY' => 'Sydney',
            'UPASS' => $config->passwords->user,
            'UPATH' => $config->paths->upath,
            'UUSER' => $config->UUSER,
            'U_GID' => (string) $config->U_GID,
            'U_SHL' => $configVars['U_SHL'],  // ✅ Use dynamic value (/bin/bash or /bin/sh)
            'U_UID' => (string) $config->U_UID,
            'VHOST' => $config->VHOST,
            'VNODE' => $config->VNODE,
            'VPATH' => $config->paths->vpath,
            'VUSER' => 'admin',
            'V_PHP' => $configVars['V_PHP'],  // ✅ Use dynamic value (OS-specific PHP version)
            'WPASS' => $config->passwords->web,
            'WPATH' => $config->paths->wpath,
            'WPUSR' => $config->passwords->wordpress,
            'WUGID' => $wugid,
        ];
    }

    /**
     * Extract credentials section
     */
    protected function extractCredentials(VhostConfiguration $config): array
    {
        return [
            'mail.admin' => $config->passwords->admin,
            'ssh.deploy' => $config->passwords->user,
            'db.app_user' => $config->passwords->database,
            'web.service' => $config->passwords->web,
            'email.service' => $config->passwords->email,
        ];
    }

    /**
     * Extract service-specific configurations
     */
    protected function extractServiceConfig(VhostConfiguration $config): array
    {
        return [
            'nginx.workers' => 'auto',
            'php.memory_limit' => '256M',
            'php.version' => '8.4',
            'mysql.port' => '3306',
            'ssl.provider' => 'letsencrypt',
            'dns.provider' => 'powerdns',
        ];
    }
}
