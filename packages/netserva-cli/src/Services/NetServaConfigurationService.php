<?php

namespace NetServa\Cli\Services;

use Illuminate\Support\Facades\Log;
use NetServa\Cli\Contracts\ConfigManagerInterface;
use NetServa\Cli\Enums\NetServaConstants;
use NetServa\Cli\Enums\NetServaStrings;
use NetServa\Cli\Enums\OsType;
use NetServa\Cli\ValueObjects\OsConfiguration;
use NetServa\Cli\ValueObjects\VhostConfiguration;
use NetServa\Cli\ValueObjects\VhostPasswords;
use NetServa\Cli\ValueObjects\VhostPaths;

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
     * Get server FQDN
     */
    protected function getServerFqdn(string $VNODE): string
    {
        $result = $this->remoteExecution->executeAsRoot($VNODE, 'hostname -f | tr "A-Z" "a-z"');

        return $result['success'] ? trim($result['output']) : $VNODE;
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
     * Get next available UID (mirrors newuid() function)
     */
    protected function getNextAvailableUid(string $VNODE): int
    {
        $minUid = NetServaConstants::MIN_USER_UID->value;
        $maxUid = NetServaConstants::MAX_USER_UID->value;

        $result = $this->remoteExecution->executeAsRoot($VNODE,
            "getent passwd | awk -F: '\$3 > {$minUid} && \$3 < {$maxUid} {print}' | cut -d: -f3 | sort -n | tail -n1"
        );

        if (! $result['success'] || empty(trim($result['output']))) {
            return NetServaConstants::ADMIN_UID->value; // Default to admin UID
        }

        $lastUid = (int) trim($result['output']);
        $nextUid = $lastUid + 1;

        return ($nextUid === 1) ? NetServaConstants::ADMIN_UID->value : $nextUid;
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
     */
    protected function extractPlatformVariables(VhostConfiguration $config): array
    {
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
            'DNAME' => 'sysadm',
            'DPASS' => $config->passwords->database,
            'DPATH' => dirname($config->paths->dbpath),
            'DPORT' => '3306',
            'DTYPE' => 'sqlite',
            'DUSER' => 'sysadm',
            'EPASS' => $config->passwords->email,
            'EXMYS' => 'mysql -u$DUSER -p$DPASS $DNAME',
            'EXSQL' => 'sqlite3 $DPATH/$DNAME.db',
            'HDOMN' => $config->VHOST,
            'HNAME' => $config->VNODE,
            'IP4_0' => $config->IP4_0,
            'MHOST' => $config->VHOST,
            'MPATH' => $config->paths->mpath,
            'OSMIR' => 'http://dl-cdn.alpinelinux.org',
            'OSREL' => $config->osConfig->release ?? 'edge',
            'OSTYP' => $config->osConfig->type->value,
            'SPATH' => $config->paths->sslPath,
            'SQCMD' => 'sqlite3 $DPATH/$DNAME.db',
            'SQDNS' => 'sqlite3 $DPATH/powerdns.db',
            'TAREA' => 'Australia',
            'TCITY' => 'Sydney',
            'UPASS' => $config->passwords->user,
            'UPATH' => $config->paths->upath,
            'UUSER' => $config->UUSER,
            'U_GID' => (string) $config->U_GID,
            'U_SHL' => '/bin/bash',
            'U_UID' => (string) $config->U_UID,
            'VHOST' => $config->VHOST,
            'VNODE' => $config->VNODE,
            'VPATH' => $config->paths->vpath,
            'VUSER' => 'admin',
            'V_PHP' => '8.4',
            'WPASS' => $config->passwords->web,
            'WPATH' => $config->paths->wpath,
            'WPUSR' => $config->passwords->wordpress,
            'WUGID' => 'nginx',
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
