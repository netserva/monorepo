<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Contracts\ConfigManagerInterface;

/**
 * VHost Configuration Service
 *
 * Manages ~/.ns/var/$VNODE/$VHOST bash environment files and .conf credential files.
 * Preserves exact bash format for backward compatibility with existing scripts.
 */
class VhostConfigService implements ConfigManagerInterface
{
    protected string $basePath;

    protected array $expectedVariables = [
        // Simplified NetServa variables - removed enterprise bloat (HNODE, VTECH, STACK)
        'ADMIN', 'AHOST', 'AMAIL', 'ANAME', 'APASS', 'A_GID', 'A_UID',
        'BPATH', 'CIMAP', 'CSMTP', 'C_DNS', 'C_FPM', 'C_SQL', 'C_SSL', 'C_WEB',
        'DBMYS', 'DBSQL', 'DHOST', 'DNAME', 'DPASS', 'DPATH', 'DPORT', 'DTYPE', 'DUSER',
        'EPASS', 'EXMYS', 'EXSQL', 'HDOMN', 'HNAME', 'IP4_0',
        'MHOST', 'MPATH', 'OSMIR', 'OSREL', 'OSTYP',
        'VNODE', 'SQCMD', 'SQDNS', 'TAREA', 'TCITY', 'UPASS', 'UPATH', 'UUSER',
        'U_GID', 'U_SHL', 'U_UID', 'VHOST', 'VPATH', 'VUSER', 'V_PHP',
        'WPASS', 'WPATH', 'WPUSR', 'WUGID',
    ];

    public function __construct()
    {
        $this->basePath = config('netserva-cli.paths.ns').'/var';

        // Ensure base directory exists
        if (! File::exists($this->basePath)) {
            File::makeDirectory($this->basePath, 0755, true);
        }
    }

    /**
     * Load VHost configuration for specific server and vhost
     */
    public function loadVhostConfig(string $shost, string $vhost): array
    {
        $filePath = $this->getVhostPath($shost, $vhost);

        if (! File::exists($filePath)) {
            throw new Exception("VHost config not found: {$shost}/{$vhost}");
        }

        return $this->parseConfigFile($filePath);
    }

    /**
     * Save VHost configuration in bash format
     */
    public function saveVhostConfig(string $shost, string $vhost, array $config): bool
    {
        $filePath = $this->getVhostPath($shost, $vhost);
        $serverDir = dirname($filePath);

        // Ensure server directory exists
        if (! File::exists($serverDir)) {
            File::makeDirectory($serverDir, 0755, true);
        }

        // Backup existing config if it exists
        if (File::exists($filePath)) {
            $this->backupVhostConfig($shost, $vhost);
        }

        // Sort variables to match expected order
        $sortedConfig = $this->sortConfigVariables($config);

        // Generate bash format content
        $content = $this->generateBashFormat($sortedConfig);

        try {
            File::put($filePath, $content);
            Log::info('VHost config saved', ['shost' => $shost, 'vhost' => $vhost]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save VHost config', [
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Load credential information from .conf file
     */
    public function loadCredentials(string $shost, string $vhost): array
    {
        $confPath = $this->getVhostPath($shost, $vhost).'.conf';

        if (! File::exists($confPath)) {
            return [];
        }

        return $this->parseCredentialsFile($confPath);
    }

    /**
     * Save credential information to .conf file
     */
    public function saveCredentials(string $shost, string $vhost, array $credentials): bool
    {
        $confPath = $this->getVhostPath($shost, $vhost).'.conf';

        // Backup existing credentials if they exist
        if (File::exists($confPath)) {
            $this->backupCredentialsFile($shost, $vhost);
        }

        $content = $this->generateCredentialsFormat($credentials);

        try {
            File::put($confPath, $content);
            Log::info('VHost credentials saved', ['shost' => $shost, 'vhost' => $vhost]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to save VHost credentials', [
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all vhosts for a specific server
     */
    public function getVhostsForServer(string $shost): array
    {
        $serverPath = $this->basePath.'/'.$shost;

        if (! File::exists($serverPath)) {
            return [];
        }

        $vhosts = [];
        $files = File::files($serverPath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip .conf files and backups
            if (! str_ends_with($filename, '.conf') && ! str_contains($filename, '.backup')) {
                $vhosts[] = $filename;
            }
        }

        return $vhosts;
    }

    /**
     * Get all servers that have vhost configurations
     */
    public function getAllServers(): array
    {
        if (! File::exists($this->basePath)) {
            return [];
        }

        return array_filter(
            File::directories($this->basePath),
            fn ($dir) => count(File::files($dir)) > 0
        );
    }

    // Implementation of ConfigManagerInterface methods

    public function load(string $identifier): array
    {
        [$shost, $vhost] = $this->parseIdentifier($identifier);

        return $this->loadVhostConfig($shost, $vhost);
    }

    public function save(string $identifier, array $config): bool
    {
        [$shost, $vhost] = $this->parseIdentifier($identifier);

        return $this->saveVhostConfig($shost, $vhost, $config);
    }

    public function exists(string $identifier): bool
    {
        [$shost, $vhost] = $this->parseIdentifier($identifier);

        return File::exists($this->getVhostPath($shost, $vhost));
    }

    public function delete(string $identifier): bool
    {
        [$shost, $vhost] = $this->parseIdentifier($identifier);

        $configPath = $this->getVhostPath($shost, $vhost);
        $credentialsPath = $configPath.'.conf';

        $deleted = 0;

        if (File::exists($configPath)) {
            File::delete($configPath);
            $deleted++;
        }

        if (File::exists($credentialsPath)) {
            File::delete($credentialsPath);
            $deleted++;
        }

        return $deleted > 0;
    }

    public function list(): array
    {
        $configs = [];
        $servers = $this->getAllServers();

        foreach ($servers as $server) {
            $serverName = basename($server);
            $vhosts = $this->getVhostsForServer($serverName);

            foreach ($vhosts as $vhost) {
                $configs[] = "{$serverName}/{$vhost}";
            }
        }

        return $configs;
    }

    public function validate(array $config): bool
    {
        // Check for required variables
        $requiredVars = ['VHOST', 'UUSER', 'DNAME', 'DPASS'];

        foreach ($requiredVars as $var) {
            if (! isset($config[$var]) || empty($config[$var])) {
                return false;
            }
        }

        // Validate variable count (should be around 53)
        $expectedCount = config('netserva-cli.vhost.env_file_variables', 53);
        $actualCount = count($config);

        // Allow some flexibility in variable count
        if ($actualCount < ($expectedCount - 5) || $actualCount > ($expectedCount + 5)) {
            Log::warning('VHost config variable count mismatch', [
                'expected' => $expectedCount,
                'actual' => $actualCount,
            ]);
        }

        return true;
    }

    public function backup(string $identifier): ?string
    {
        [$shost, $vhost] = $this->parseIdentifier($identifier);

        return $this->backupVhostConfig($shost, $vhost);
    }

    // Protected helper methods

    protected function getVhostPath(string $shost, string $vhost): string
    {
        return $this->basePath.'/'.$shost.'/'.$vhost;
    }

    protected function parseConfigFile(string $filePath): array
    {
        $content = File::get($filePath);
        $config = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse bash variable format: VAR='value'
            if (preg_match("/^([A-Z_]+)='(.*)'/", $line, $matches)) {
                $config[$matches[1]] = $matches[2];
            }
        }

        return $config;
    }

    protected function generateBashFormat(array $config): string
    {
        $lines = [];

        foreach ($config as $key => $value) {
            // Escape single quotes in values
            $escapedValue = str_replace("'", "'\"'\"'", $value);
            $lines[] = "{$key}='{$escapedValue}'";
        }

        return implode("\n", $lines)."\n";
    }

    protected function sortConfigVariables(array $config): array
    {
        $sorted = [];

        // First add variables in expected order
        foreach ($this->expectedVariables as $var) {
            if (isset($config[$var])) {
                $sorted[$var] = $config[$var];
            }
        }

        // Then add any additional variables
        foreach ($config as $key => $value) {
            if (! in_array($key, $this->expectedVariables)) {
                $sorted[$key] = $value;
            }
        }

        return $sorted;
    }

    protected function parseCredentialsFile(string $filePath): array
    {
        $content = File::get($filePath);
        $credentials = [];
        $currentSection = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Check for section headers
            if (preg_match('/^([A-Za-z\s]+)$/', $line) && str_contains($line, '=')) {
                // This is a section divider like "SSH/SFTP" followed by "========="
                continue;
            } elseif (preg_match('/^([A-Za-z\s]+)$/', $line) && ! str_contains($line, ':')) {
                $currentSection = strtolower(str_replace(' ', '_', $line));

                continue;
            } elseif (str_contains($line, '=')) {
                // Section divider, skip
                continue;
            }

            // Parse key-value pairs
            if (str_contains($line, ':') && $currentSection) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                $credentials[$currentSection][strtolower($key)] = $value;
            }
        }

        return $credentials;
    }

    protected function generateCredentialsFormat(array $credentials): string
    {
        $content = [];

        foreach ($credentials as $section => $values) {
            $sectionTitle = ucwords(str_replace('_', ' ', $section));
            $content[] = $sectionTitle;
            $content[] = str_repeat('=', strlen($sectionTitle));
            $content[] = '';

            foreach ($values as $key => $value) {
                $keyTitle = ucwords(str_replace('_', ' ', $key));
                $content[] = "{$keyTitle}: {$value}";
            }

            $content[] = '';
        }

        return implode("\n", $content);
    }

    protected function parseIdentifier(string $identifier): array
    {
        if (! str_contains($identifier, '/')) {
            throw new Exception("Invalid identifier format. Expected 'shost/vhost', got: {$identifier}");
        }

        return explode('/', $identifier, 2);
    }

    protected function backupVhostConfig(string $shost, string $vhost): ?string
    {
        $configPath = $this->getVhostPath($shost, $vhost);

        if (! File::exists($configPath)) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $configPath.".backup.{$timestamp}";

        try {
            File::copy($configPath, $backupPath);

            return $backupPath;
        } catch (Exception $e) {
            Log::error('Failed to backup VHost config', [
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function backupCredentialsFile(string $shost, string $vhost): ?string
    {
        $confPath = $this->getVhostPath($shost, $vhost).'.conf';

        if (! File::exists($confPath)) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $confPath.".backup.{$timestamp}";

        try {
            File::copy($confPath, $backupPath);

            return $backupPath;
        } catch (Exception $e) {
            Log::error('Failed to backup VHost credentials', [
                'shost' => $shost,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
