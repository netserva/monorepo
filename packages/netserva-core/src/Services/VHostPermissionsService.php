<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Exceptions\VHostNotFoundException;
use NetServa\Core\Models\VHost;

/**
 * VHost Permissions Service
 *
 * Provides permission management for virtual hosts that can be used
 * by both CLI commands and Filament resources. Follows NetServa 3.0
 * architecture patterns with models and services.
 *
 * Database-first approach with filesystem fallback for backward compatibility.
 */
class VHostPermissionsService
{
    protected RemoteExecutionService $remoteExecution;

    protected DatabaseVhostConfigService $dbConfig;

    protected VhostConfigService $fileConfig;

    protected VHostResolverService $resolver;

    public function __construct(
        RemoteExecutionService $remoteExecution,
        DatabaseVhostConfigService $dbConfig,
        VhostConfigService $fileConfig,
        VHostResolverService $resolver
    ) {
        $this->remoteExecution = $remoteExecution;
        $this->dbConfig = $dbConfig;
        $this->fileConfig = $fileConfig;
        $this->resolver = $resolver;
    }

    /**
     * Load VHost configuration using database-first approach with filesystem fallback
     */
    protected function loadVhostConfig(string $vnode, string $vhost): array
    {
        try {
            // Try database first
            return $this->dbConfig->loadVhostConfig($vnode, $vhost);
        } catch (VHostNotFoundException $e) {
            Log::debug('VHost not found in database, trying filesystem', [
                'vnode' => $vnode,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            // Fall back to filesystem with smart resolution
            try {
                $context = $this->resolver->resolveVHost($vhost, $vnode);

                if ($context['source'] === 'filesystem') {
                    return $this->fileConfig->parseConfigFile($context['path']);
                }

                throw new VHostNotFoundException('VHost not found in database or filesystem');
            } catch (Exception $fsError) {
                throw new VHostNotFoundException(
                    "VHost config not found for {$vhost} on {$vnode}. ".
                    "Database: {$e->getMessage()}. Filesystem: {$fsError->getMessage()}"
                );
            }
        }
    }

    /**
     * Fix permissions for a single VHost using smart resolution
     */
    public function fixVHostPermissions(string $vnode, string $vhost, array $options = []): array
    {
        try {
            $debug = $options['debug'] ?? false;

            if ($debug) {
                $this->debugOutput("🔧 Starting VHost permissions fix for {$vhost} on {$vnode}");
                $this->debugOutput('📋 Options: '.json_encode($options, JSON_PRETTY_PRINT));
            }

            Log::info('Fixing VHost permissions', [
                'vnode' => $vnode,
                'vhost' => $vhost,
                'options' => $options,
            ]);

            // Load VHost configuration using database-first approach with fallback
            if ($debug) {
                $this->debugOutput('🔍 Loading VHost configuration...');
            }
            $config = $this->loadVhostConfig($vnode, $vhost);

            if (! $config) {
                throw new Exception("VHost configuration not found for {$vhost} on {$vnode}");
            }

            if ($debug) {
                $this->debugOutput('✅ Configuration loaded:');
                $this->debugOutput('   UUSER: '.($config['UUSER'] ?? 'not set'));
                $this->debugOutput('   WUGID: '.($config['WUGID'] ?? 'not set'));
                $this->debugOutput('   UPATH: '.($config['UPATH'] ?? 'not set'));
                $this->debugOutput('   WPATH: '.($config['WPATH'] ?? 'not set'));
                $this->debugOutput('   MPATH: '.($config['MPATH'] ?? 'not set'));
                $this->debugOutput('   VHOST: '.($config['VHOST'] ?? 'not set'));
            }

            // Generate permission commands
            if ($debug) {
                $this->debugOutput('⚙️  Generating permission commands...');
            }
            $commands = $this->generatePermissionCommands($config, $options);

            if ($debug) {
                $this->debugOutput('📝 Generated '.count($commands).' commands:');
                foreach ($commands as $i => $command) {
                    $this->debugOutput('   '.($i + 1).". {$command}");
                }
            }

            // Execute commands if not dry run
            if (! ($options['dry_run'] ?? false)) {
                if ($debug) {
                    $this->debugOutput("🚀 Executing commands on {$vnode}...");
                }
                $executionResult = $this->executePermissionCommands($vnode, $commands, $debug);

                if (! $executionResult['success']) {
                    throw new Exception($executionResult['error'] ?? 'Permission fix failed');
                }

                // Update VHost model if it exists in database
                $this->updateVHostModel($vnode, $vhost);

                return [
                    'success' => true,
                    'vhost' => $vhost,
                    'vnode' => $vnode,
                    'commands_executed' => count($commands),
                    'details' => $executionResult['details'] ?? [],
                ];
            }

            return [
                'success' => true,
                'vhost' => $vhost,
                'vnode' => $vnode,
                'commands' => $commands,
                'dry_run' => true,
            ];

        } catch (Exception $e) {
            Log::error('VHost permission fix failed', [
                'vnode' => $vnode,
                'vhost' => $vhost,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'vhost' => $vhost,
                'vnode' => $vnode,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fix permissions for multiple VHosts
     */
    public function fixMultipleVHostPermissions(string $vnode, array $vhosts, array $options = []): array
    {
        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;

        foreach ($vhosts as $vhost) {
            $result = $this->fixVHostPermissions($vnode, $vhost, $options);
            $results[] = $result;

            if ($result['success']) {
                $totalSuccess++;
            } else {
                $totalErrors++;
            }
        }

        return [
            'success' => $totalErrors === 0,
            'total_processed' => count($vhosts),
            'total_success' => $totalSuccess,
            'total_errors' => $totalErrors,
            'results' => $results,
        ];
    }

    /**
     * Fix permissions for all VHosts on a server
     */
    public function fixAllVHostPermissions(string $vnode, array $options = []): array
    {
        $vhosts = $this->getAvailableVHosts($vnode);

        if (empty($vhosts)) {
            return [
                'success' => true,
                'total_processed' => 0,
                'message' => "No virtual hosts found on {$vnode}",
            ];
        }

        return $this->fixMultipleVHostPermissions($vnode, $vhosts, $options);
    }

    /**
     * Get VHost path information
     */
    public function getVHostPaths(string $vnode, string $vhost): array
    {
        $config = $this->loadVhostConfig($vnode, $vhost);
        if (! $config) {
            return [];
        }

        $paths = [];

        if (! empty($config['UPATH'])) {
            $paths[] = [
                'type' => 'User Home',
                'path' => $config['UPATH'],
                'owner' => $config['UUSER'].':'.$config['WUGID'],
                'permissions' => '755',
            ];
        }

        if (! empty($config['WPATH'])) {
            $paths[] = [
                'type' => 'Web Root',
                'path' => $config['WPATH'],
                'owner' => $config['UUSER'].':'.$config['WUGID'],
                'permissions' => '755/644',
            ];
        }

        if (! empty($config['MPATH'])) {
            $paths[] = [
                'type' => 'Mail Root',
                'path' => $config['MPATH'],
                'owner' => $config['UUSER'].':'.$config['WUGID'],
                'permissions' => '750/640',
            ];
        }

        return $paths;
    }

    /**
     * Generate permission commands based on NetServa standards
     */
    protected function generatePermissionCommands(array $config, array $options = []): array
    {
        $commands = [];
        $UUSER = $config['UUSER'] ?? 'www-data';
        $WUGID = $config['WUGID'] ?? 'www-data';
        $UPATH = $config['UPATH'] ?? '';
        $WPATH = $config['WPATH'] ?? '';
        $MPATH = $config['MPATH'] ?? '';

        // User home directory permissions (/srv/$VHOST)
        if ($UPATH) {
            $commands[] = "chown -R {$UUSER}:{$WUGID} {$UPATH}";
            $commands[] = "chmod 755 {$UPATH}";
        }

        // Web directory permissions (/srv/$VHOST/var/www - most critical)
        if ($WPATH && ! ($options['mail_only'] ?? false)) {
            $commands[] = "chown -R {$UUSER}:{$WUGID} {$WPATH}";
            $commands[] = "find {$WPATH} -type d -exec chmod 755 {} \\;";
            $commands[] = "find {$WPATH} -type f -exec chmod 644 {} \\;";

            // Special permissions for writable directories
            $writableDirs = [
                "{$WPATH}/var/cache",
                "{$WPATH}/var/log",
                "{$WPATH}/var/tmp",
                "{$WPATH}/uploads",
                "{$WPATH}/wp-content",
                "{$WPATH}/storage",          // Laravel storage
                "{$WPATH}/bootstrap/cache",  // Laravel bootstrap cache
            ];

            foreach ($writableDirs as $dir) {
                $commands[] = "[ -d {$dir} ] && chmod 775 {$dir} || true";
                $commands[] = "[ -d {$dir} ] && find {$dir} -type d -exec chmod 775 {} \\; || true";
                $commands[] = "[ -d {$dir} ] && find {$dir} -type f -exec chmod 664 {} \\; || true";
            }
        }

        // Mail directory permissions (/srv/$VHOST/msg)
        if ($MPATH && ! ($options['web_only'] ?? false)) {
            $commands[] = "chown -R {$UUSER}:{$WUGID} {$MPATH}";
            $commands[] = "chmod 750 {$MPATH}";
            $commands[] = "find {$MPATH} -type d -exec chmod 750 {} \\;";
            $commands[] = "find {$MPATH} -type f -exec chmod 640 {} \\;";

            // Note: Mail subdirectories are handled by the recursive find commands above
            // No need to explicitly handle Maildir-specific subdirectories (cur, new, tmp)
            // as they vary by mail storage format and the recursive commands cover them
        }

        // SSL certificates (if they exist) - Check both Let's Encrypt and standard paths
        $vhost = $config['VHOST'] ?? '';

        // Let's Encrypt style path
        $lePath = "/etc/ssl/le/{$vhost}";
        $commands[] = "[ -d {$lePath} ] && chown root:root {$lePath} || true";
        $commands[] = "[ -d {$lePath} ] && chmod 700 {$lePath} || true";
        $commands[] = "[ -f {$lePath}/fullchain.pem ] && chmod 644 {$lePath}/fullchain.pem || true";
        $commands[] = "[ -f {$lePath}/privkey.pem ] && chmod 600 {$lePath}/privkey.pem || true";

        // Standard SSL directory structure
        $sslPath = "/etc/ssl/{$vhost}";
        $commands[] = "[ -d {$sslPath} ] && chown root:root {$sslPath} || true";
        $commands[] = "[ -d {$sslPath} ] && chmod 755 {$sslPath} || true";
        $commands[] = "[ -f {$sslPath}/{$vhost}.crt ] && chmod 644 {$sslPath}/{$vhost}.crt || true";
        $commands[] = "[ -f {$sslPath}/{$vhost}.key ] && chmod 600 {$sslPath}/{$vhost}.key || true";
        $commands[] = "[ -f {$sslPath}/fullchain.pem ] && chmod 644 {$sslPath}/fullchain.pem || true";
        $commands[] = "[ -f {$sslPath}/privkey.pem ] && chmod 600 {$sslPath}/privkey.pem || true";

        // Log directory permissions
        $logPath = "/var/log/{$vhost}";
        $commands[] = "[ -d {$logPath} ] && chown {$UUSER}:{$WUGID} {$logPath} || true";
        $commands[] = "[ -d {$logPath} ] && chmod 755 {$logPath} || true";
        $commands[] = "[ -d {$logPath} ] && find {$logPath} -type f -exec chmod 644 {} \\; || true";

        return array_filter($commands); // Remove empty commands
    }

    /**
     * Execute permission commands on remote server
     */
    protected function executePermissionCommands(string $vnode, array $commands, bool $debug = false): array
    {
        $details = [];
        $allSuccess = true;

        foreach ($commands as $i => $command) {
            if ($debug) {
                $this->debugOutput('  📤 ['.($i + 1).'/'.count($commands)."] Executing: {$command}");
            }

            $result = $this->remoteExecution->executeAsRoot($vnode, $command);

            if ($result['success']) {
                $details[] = "Executed: {$command}";
                if ($debug) {
                    $this->debugOutput('  ✅ Success');
                    if (! empty($result['output'])) {
                        $this->debugOutput('     Output: '.trim($result['output']));
                    }
                }
            } else {
                $details[] = "Failed: {$command} - {$result['error']}";
                $allSuccess = false;
                if ($debug) {
                    $this->debugOutput('  ❌ Failed: '.($result['error'] ?? 'Unknown error'));
                    if (! empty($result['output'])) {
                        $this->debugOutput('     Output: '.trim($result['output']));
                    }
                    if (isset($result['return_code'])) {
                        $this->debugOutput('     Exit Code: '.$result['return_code']);
                    }
                }
            }
        }

        return [
            'success' => $allSuccess,
            'details' => $details,
        ];
    }

    /**
     * Update VHost model if it exists in database
     */
    protected function updateVHostModel(string $vnode, string $vhost): void
    {
        try {
            $vhostModel = VHost::where('domain', $vhost)
                ->where('host', $vnode)
                ->first();

            if ($vhostModel) {
                $vhostModel->touch(); // Update timestamps
                Log::info('VHost model updated after permission fix', [
                    'vnode' => $vnode,
                    'vhost' => $vhost,
                    'model_id' => $vhostModel->id,
                ]);
            }
        } catch (Exception $e) {
            // Silently fail if database is not available or VHost doesn't exist
            Log::debug('VHost model update skipped', [
                'vnode' => $vnode,
                'vhost' => $vhost,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate that VHost exists
     */
    public function validateVHostExists(string $vnode, string $vhost): bool
    {
        return $this->resolver->vhostExists($vhost, $vnode);
    }

    /**
     * Get available VHosts for a server
     */
    public function getAvailableVHosts(string $vnode): array
    {
        return $this->resolver->getVHostsForVNode($vnode)->pluck('vhost')->toArray();
    }

    /**
     * Get permission status for a VHost
     */
    public function getPermissionStatus(string $vnode, string $vhost): array
    {
        try {
            $config = $this->vhostConfig->loadVhostConfig($vnode, $vhost);
            if (! $config) {
                return ['status' => 'unknown', 'error' => 'Configuration not found'];
            }

            // Check if key directories exist and have correct permissions
            $checks = [];

            if (! empty($config['UPATH'])) {
                $checks[] = $this->checkPathPermissions($vnode, $config['UPATH'], '755');
            }

            if (! empty($config['WPATH'])) {
                $checks[] = $this->checkPathPermissions($vnode, $config['WPATH'], '755');
            }

            if (! empty($config['MPATH'])) {
                $checks[] = $this->checkPathPermissions($vnode, $config['MPATH'], '750');
            }

            $allGood = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

            return [
                'status' => $allGood ? 'ok' : 'needs_fix',
                'checks' => $checks,
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check permissions for a specific path
     */
    protected function checkPathPermissions(string $vnode, string $path, string $expectedPerms): array
    {
        $result = $this->remoteExecution->execute($vnode, "stat -c '%a %U:%G' {$path} 2>/dev/null || echo 'not_found'");

        if (! $result['success'] || trim($result['output']) === 'not_found') {
            return [
                'path' => $path,
                'status' => 'missing',
                'expected' => $expectedPerms,
                'actual' => null,
            ];
        }

        $output = trim($result['output']);
        [$perms, $owner] = explode(' ', $output, 2);

        return [
            'path' => $path,
            'status' => $perms === $expectedPerms ? 'ok' : 'wrong_permissions',
            'expected' => $expectedPerms,
            'actual' => $perms,
            'owner' => $owner,
        ];
    }

    /**
     * Output debug information (will be picked up by Laravel's output handling)
     */
    protected function debugOutput(string $message): void
    {
        // Use echo so it appears in command output immediately
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }
    }
}
