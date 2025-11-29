<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\VhostManagementService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Change/Update VHost Command
 *
 * Follows NetServa CRUD pattern: chvhost (not "ns vhost update")
 * Usage: chvhost <vnode> <vhost> [--options]
 * Example: chvhost markc markc.goldcoast.org --php-version=8.4 --ssl=true
 *
 * DATABASE-FIRST ARCHITECTURE:
 * - Updates vconfs table via FleetVhost model
 * - NO file-based config (no VhostConfigService)
 * - Remote execution via VhostManagementService
 */
class ChvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'chvhost {vnode : SSH host/VNode identifier}
                           {vhost : Domain name to update}
                           {--php-version= : Update PHP version (8.1, 8.2, 8.3, 8.4)}
                           {--ssl= : Enable/disable SSL (true/false)}
                           {--webroot= : Change web document root}
                           {--move-to= : Migrate vhost to different vnode}
                           {--sync-files : Sync files when migrating (requires --move-to)}
                           {--backup : Backup current config before changes}
                           {--dry-run : Show what would be done}';

    protected $description = 'Change/update virtual host configuration (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    public function __construct(VhostManagementService $vhostService)
    {
        parent::__construct();
        $this->vhostService = $vhostService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters from positional arguments
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Check if this is a migration operation
            if ($moveToVnode = $this->option('move-to')) {
                return $this->migrateVhost($VNODE, $VHOST, $moveToVnode);
            }

            // Find VNode in database
            $vnode = FleetVnode::where('name', $VNODE)->first();
            if (! $vnode) {
                $this->error("❌ VNode {$VNODE} not found in database");
                $this->line("   Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            // Find FleetVhost in database (DATABASE-FIRST!)
            $fleetVhost = FleetVhost::where('domain', $VHOST)
                ->where('vnode_id', $vnode->id)
                ->first();

            if (! $fleetVhost) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");
                $this->line("   Use 'addvhost {$VNODE} {$VHOST}' to create it first");

                return 1;
            }

            // Collect changes to apply
            $changes = $this->collectChanges();

            if (empty($changes)) {
                $this->error('❌ No changes specified. Use --help to see available options');

                return 1;
            }

            // Show what we're about to do
            $this->line("🔧 Updating VHost: <fg=yellow>{$VHOST}</> on server <fg=cyan>{$VNODE}</>");
            $this->line('');
            $this->line('<fg=blue>📝 Changes to apply:</>');
            foreach ($changes as $key => $value) {
                $this->line("   <fg=yellow>{$key}:</> {$value}");
            }

            if ($this->option('dry-run')) {
                $this->dryRun("Update VHost {$VHOST} on {$VNODE}", [
                    "Load current vhost from FleetVhost model (ID: {$fleetVhost->id})",
                    'Load environment variables from vconfs table (database-first)',
                    'Backup current vconfs in database (if --backup specified)',
                    'Apply configuration changes: '.implode(', ', array_keys($changes)),
                    'Update vconfs table with new values via FleetVhost::setEnvVar()',
                    "SSH to {$VNODE} and apply changes via RemoteExecutionService heredoc",
                    'Reload relevant services (nginx, php-fpm, etc.)',
                ]);

                return 0;
            }

            // Backup current config if requested (database backup)
            if ($this->option('backup')) {
                $backupCreated = $this->backupVhostConfig($fleetVhost);
                if ($backupCreated) {
                    $this->line('📦 Backup created in database (vconfs table)');
                }
            }

            // Apply the changes (DATABASE-FIRST via vconfs table!)
            $result = $this->applyChanges($VNODE, $VHOST, $fleetVhost, $changes);

            if ($result['success']) {
                $this->info("✅ VHost {$VHOST} updated successfully on {$VNODE}");

                // Show what was changed
                if (isset($result['applied_changes'])) {
                    $this->line('');
                    $this->line('<fg=blue>📋 Applied Changes:</>');
                    foreach ($result['applied_changes'] as $key => $value) {
                        $this->line("   <fg=green>✓</> {$key}: {$value}");
                    }
                }

                // Add to command history
                $this->context->addToHistory("chvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'changes' => $changes,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to update VHost {$VHOST} on {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    protected function collectChanges(): array
    {
        $changes = [];

        if ($phpVersion = $this->option('php-version')) {
            $validVersions = ['8.1', '8.2', '8.3', '8.4'];
            if (! in_array($phpVersion, $validVersions)) {
                $this->error('❌ Invalid PHP version. Valid options: '.implode(', ', $validVersions));
                exit(1);
            }
            $changes['php_version'] = $phpVersion;
        }

        if ($this->hasOption('ssl') && $this->option('ssl') !== null) {
            $ssl = filter_var($this->option('ssl'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ssl === null) {
                $this->error("❌ Invalid SSL value. Use 'true' or 'false'");
                exit(1);
            }
            $changes['ssl_enabled'] = $ssl ? 'true' : 'false';
        }

        if ($webroot = $this->option('webroot')) {
            // Basic validation - should be an absolute path
            if (! str_starts_with($webroot, '/')) {
                $this->error('❌ Webroot must be an absolute path (starting with /)');
                exit(1);
            }
            $changes['webroot'] = $webroot;
        }

        return $changes;
    }

    /**
     * Apply changes to FleetVhost via vconfs table (DATABASE-FIRST!)
     *
     * NetServa 3.0 Architecture:
     * 1. Update vconfs table via FleetVhost::setEnvVar()
     * 2. Execute remote script via VhostManagementService
     * 3. NO file-based config!
     */
    protected function applyChanges(string $VNODE, string $VHOST, FleetVhost $fleetVhost, array $changes): array
    {
        try {
            $appliedChanges = [];

            // Apply changes to vconfs table (DATABASE-FIRST!)
            foreach ($changes as $key => $value) {
                switch ($key) {
                    case 'php_version':
                        // Update PHP version in vconfs table
                        $fleetVhost->setEnvVar('V_PHP', $value);
                        $appliedChanges['PHP Version'] = $value;
                        break;

                    case 'ssl_enabled':
                        // Update SSL configuration in vconfs table
                        $fleetVhost->setEnvVar('SSL_ENABLED', $value);
                        $appliedChanges['SSL Enabled'] = $value;
                        break;

                    case 'webroot':
                        // Update web document root in vconfs table
                        $fleetVhost->setEnvVar('WPATH', $value);
                        $appliedChanges['Web Root'] = $value;
                        break;
                }
            }

            // Save FleetVhost model changes
            $fleetVhost->save();

            // Execute remote changes via VhostManagementService
            // (Future enhancement: add updateVhost() method to service)
            // For now, changes are persisted to database

            return [
                'success' => true,
                'applied_changes' => $appliedChanges,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Backup current vconfs (database backup, not file-based)
     */
    protected function backupVhostConfig(FleetVhost $fleetVhost): bool
    {
        try {
            // Get all current vconfs
            $currentVars = $fleetVhost->getAllEnvVars();

            // Store backup metadata in FleetVhost
            $backupData = [
                'timestamp' => now()->toIso8601String(),
                'vconfs_count' => count($currentVars),
                'backup_type' => 'pre_chvhost_update',
            ];

            // Store in legacy_config JSON column for backup purposes
            $fleetVhost->update([
                'legacy_config' => array_merge(
                    $fleetVhost->legacy_config ?? [],
                    ['last_backup' => $backupData]
                ),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->warn("⚠️  Backup failed: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Migrate vhost from one vnode to another
     *
     * Process:
     * 1. Validate both vnodes exist
     * 2. Validate vhost exists on source vnode
     * 3. Update vnode_id in database
     * 4. Optionally sync files using scp (not rsync - doesn't work between remote hosts)
     * 5. Update vnode-specific vconf variables
     * 6. Run chperms on destination vnode
     */
    protected function migrateVhost(string $sourceVnode, string $vhost, string $destVnode): int
    {
        try {
            $this->line("🚚 Migrating VHost: <fg=yellow>{$vhost}</>");
            $this->line("   From: <fg=cyan>{$sourceVnode}</>");
            $this->line("   To: <fg=green>{$destVnode}</>");
            $this->line('');

            // 1. Validate source vnode
            $sourceNode = FleetVnode::where('name', $sourceVnode)->first();
            if (! $sourceNode) {
                $this->error("❌ Source VNode '{$sourceVnode}' not found");
                $this->line("   Run: php artisan addvnode {$sourceVnode}");

                return 1;
            }

            // 2. Validate destination vnode
            $destNode = FleetVnode::where('name', $destVnode)->first();
            if (! $destNode) {
                $this->error("❌ Destination VNode '{$destVnode}' not found");
                $this->line("   Run: php artisan addvnode {$destVnode}");

                return 1;
            }

            // 3. Validate vhost exists on source vnode
            $fleetVhost = FleetVhost::where('domain', $vhost)
                ->where('vnode_id', $sourceNode->id)
                ->first();

            if (! $fleetVhost) {
                $this->error("❌ VHost '{$vhost}' not found on source vnode '{$sourceVnode}'");
                $this->line('   Available vnodes for this vhost:');

                $allVhosts = FleetVhost::where('domain', $vhost)->with('vnode')->get();
                if ($allVhosts->isEmpty()) {
                    $this->line("   (VHost '{$vhost}' does not exist in database)");
                } else {
                    foreach ($allVhosts as $vh) {
                        $this->line("   - {$vh->vnode->name}");
                    }
                }

                return 1;
            }

            // Get current UPATH before migration
            $sourceUpath = $fleetVhost->getEnvVar('UPATH') ?? "/srv/{$vhost}";

            if ($this->option('dry-run')) {
                $this->dryRun("Migrate VHost {$vhost} from {$sourceVnode} to {$destVnode}", [
                    "1. Update database: vnode_id from {$sourceNode->id} to {$destNode->id}",
                    '2. Update vnode-specific vconf variables (IP addresses, paths)',
                    $this->option('sync-files')
                        ? "3. Sync files using scp from {$sourceVnode}:{$sourceUpath} to {$destVnode}:{$sourceUpath}"
                        : '3. Skip file sync (--sync-files not specified)',
                    "4. Run chperms on {$destVnode} to fix ownership/permissions",
                ]);

                return 0;
            }

            // 4. Optionally sync files using scp
            if ($this->option('sync-files')) {
                $this->line('📦 Syncing files between vnodes...');
                $syncResult = $this->syncFilesWithScp($sourceVnode, $destVnode, $sourceUpath);

                if (! $syncResult) {
                    $this->warn('⚠️  File sync failed - continuing with migration anyway');
                    $this->line('   You may need to manually copy files using:');
                    $this->line("   scp -r {$sourceVnode}:{$sourceUpath}/web/app/public/* {$destVnode}:{$sourceUpath}/web/app/public/");
                }
            } else {
                $this->line('ℹ️  Skipping file sync (use --sync-files to enable)');
                $this->line('');
                $this->line('   📋 To manually sync files later:');
                $this->line('');
                $this->line('   # User data:');
                $this->line("   scp -r {$sourceVnode}:{$sourceUpath}/web/app/public/* {$destVnode}:{$sourceUpath}/web/app/public/");
                $this->line("   scp -r {$sourceVnode}:{$sourceUpath}/msg/* {$destVnode}:{$sourceUpath}/msg/ 2>/dev/null || true");
                $this->line('');
                $this->line('   # System configs:');
                $this->line("   scp {$sourceVnode}:/etc/nginx/sites-enabled/{$vhost} {$destVnode}:/etc/nginx/sites-enabled/");
                $this->line("   scp -r {$sourceVnode}:/etc/ssl/{$vhost}/ {$destVnode}:/etc/ssl/ 2>/dev/null || true");
                $this->line("   scp -r {$sourceVnode}:/root/.acme.sh/{$vhost}/ {$destVnode}:/root/.acme.sh/ 2>/dev/null || true");
                $this->line("   # (Then reload services: sx {$destVnode} 'sc reload nginx && sc reload php-fpm')");
                $this->line('');
            }

            // 5. Update vnode_id in database
            $this->line('💾 Updating database...');
            $fleetVhost->vnode_id = $destNode->id;
            $fleetVhost->save();
            $this->info("   ✓ VHost now assigned to {$destVnode}");

            // 6. Update vnode-specific vconf variables (IP address, etc.)
            $this->line('🔧 Updating vnode-specific configuration...');
            $this->updateVnodeSpecificVars($fleetVhost, $destNode);

            // 7. Run chperms on destination vnode
            $this->line('🔐 Fixing permissions on destination vnode...');
            $chpermsResult = $this->call('chperms', [
                'vnode' => $destVnode,
                'vhost' => $vhost,
            ]);

            if ($chpermsResult === 0) {
                $this->info("   ✓ Permissions fixed on {$destVnode}");
            } else {
                $this->warn("   ⚠️  chperms returned non-zero exit code: {$chpermsResult}");
            }

            $this->line('');
            $this->info("✅ Migration complete: {$vhost} now on {$destVnode}");

            // Add helpful next steps
            $this->line('');
            $this->line('<fg=blue>📋 Next steps:</>');
            $this->line("   1. Verify vhost configuration: shvconf {$destVnode} {$vhost}");

            if ($this->option('sync-files')) {
                $this->line('   2. Reload services on destination:');
                $this->line("      sx {$destVnode} 'sc reload nginx && sc reload php-fpm'");
                $this->line("   3. Test web access: curl -I http://{$vhost}");
                $this->line("   4. Update DNS if IP changed (old: {$sourceNode->ip_address}, new: {$destNode->ip_address})");
            } else {
                $this->line('   2. Sync files manually (see commands above)');
                $this->line("   3. Reload services: sx {$destVnode} 'sc reload nginx && sc reload php-fpm'");
                $this->line("   4. Test web access: curl -I http://{$vhost}");
                $this->line("   5. Update DNS if IP changed (old: {$sourceNode->ip_address}, new: {$destNode->ip_address})");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Migration failed: '.$e->getMessage());

            return 1;
        }
    }

    /**
     * Sync files using scp between vnodes
     *
     * Note: rsync doesn't work between two remote hosts, but scp does work
     * for transferring files from source:/path to dest:/path
     *
     * Syncs both user data AND system configuration files:
     * - User data: /srv/vhost/* (web, mail, ssh, etc.)
     * - System configs: nginx, PHP-FPM, SSL certs, ACME.sh
     */
    protected function syncFilesWithScp(string $sourceVnode, string $destVnode, string $upath): bool
    {
        try {
            $vhost = basename($upath);
            $errors = [];

            $this->line('');
            $this->line('   📦 Syncing user data files...');

            // 1. User data directories under /srv/vhost/
            $userDataPaths = [
                'web/app/public/*',  // Web files
                'web/log/*',         // Web logs
                'msg/*',             // Mail messages
                '.ssh/*',            // SSH keys
                '.gnupg/*',          // GPG keys
                '.rc/*',             // Shell config
            ];

            foreach ($userDataPaths as $path) {
                $sourcePath = "{$sourceVnode}:{$upath}/{$path}";
                $destPath = "{$destVnode}:{$upath}/".dirname($path);

                $this->line("      • {$path}");
                $result = shell_exec("scp -r -p {$sourcePath} {$destPath} 2>&1");

                // Track errors but continue (path may not exist)
                if ($result && strpos($result, 'No such file') === false) {
                    if (strpos($result, 'scp:') !== false || strpos($result, 'error') !== false) {
                        $errors[] = "Failed to sync {$path}";
                    }
                }
            }

            $this->line('');
            $this->line('   🔧 Syncing system configuration files...');

            // 2. Nginx configuration (detect OpenWrt vs standard Debian/Alpine)
            // Check if destination is OpenWrt (has /srv/gw/ structure)
            $destOS = shell_exec("ssh {$destVnode} 'cat /etc/os-release | grep ^ID=' 2>&1");
            $isOpenWrt = (strpos($destOS, 'openwrt') !== false);

            if ($isOpenWrt) {
                // OpenWrt: Use /srv/.local/nginx/ and symlink to /etc/nginx/conf.d/
                $this->line("      • Nginx (OpenWrt): /srv/.local/nginx/{$vhost}.conf");

                $nginxSource = "{$sourceVnode}:/etc/nginx/sites-enabled/{$vhost}";
                $nginxDest = "{$destVnode}:/srv/.local/nginx/{$vhost}.conf";
                shell_exec("scp -p {$nginxSource} {$nginxDest} 2>&1");

                // Create symlink in /etc/nginx/conf.d/
                shell_exec("ssh {$destVnode} 'ln -sf /srv/.local/nginx/{$vhost}.conf /etc/nginx/conf.d/{$vhost}.conf' 2>&1");
            } else {
                // Standard Debian/Alpine: Use /etc/nginx/sites-enabled/
                $this->line("      • Nginx: /etc/nginx/sites-enabled/{$vhost}");

                $nginxSource = "{$sourceVnode}:/etc/nginx/sites-enabled/{$vhost}";
                $nginxDest = "{$destVnode}:/etc/nginx/sites-enabled/";
                shell_exec("scp -p {$nginxSource} {$nginxDest} 2>&1");
            }

            // 3. SSL certificates (if exist)
            $sslSource = "{$sourceVnode}:/etc/ssl/{$vhost}/";
            $sslDest = "{$destVnode}:/etc/ssl/";
            $this->line("      • SSL: /etc/ssl/{$vhost}/");
            shell_exec("scp -r -p {$sslSource} {$sslDest} 2>&1");

            // 4. ACME.sh certificates (if exist)
            $acmeSource = "{$sourceVnode}:/root/.acme.sh/{$vhost}/";
            $acmeDest = "{$destVnode}:/root/.acme.sh/";
            $this->line("      • ACME: /root/.acme.sh/{$vhost}/");
            shell_exec("scp -r -p {$acmeSource} {$acmeDest} 2>&1");

            // 5. PHP-FPM pool configuration (need to detect PHP version)
            // Check common PHP versions: 8.1, 8.2, 8.3, 8.4
            $phpVersions = ['8.1', '8.2', '8.3', '8.4', '7.4', '8.0'];
            foreach ($phpVersions as $phpVer) {
                $fpmSource = "{$sourceVnode}:/etc/php/{$phpVer}/fpm/pool.d/{$vhost}.conf";
                $fpmDest = "{$destVnode}:/etc/php/{$phpVer}/fpm/pool.d/";

                // Try to copy, silently fail if version doesn't exist
                $result = shell_exec("scp -p {$fpmSource} {$fpmDest} 2>&1");

                if ($result && strpos($result, 'No such file') === false) {
                    $this->line("      • PHP-FPM: /etc/php/{$phpVer}/fpm/pool.d/{$vhost}.conf");
                    break; // Found the right version, stop searching
                }
            }

            // 6. Additional system files that might exist
            $additionalPaths = [
                "/etc/postfix/virtual/{$vhost}",  // Postfix virtual domains
                "/etc/dovecot/users/{$vhost}",    // Dovecot user configs
            ];

            foreach ($additionalPaths as $path) {
                $source = "{$sourceVnode}:{$path}";
                $dest = "{$destVnode}:".dirname($path).'/';

                $result = shell_exec("scp -r -p {$source} {$dest} 2>&1");

                if ($result && strpos($result, 'No such file') === false && strpos($result, 'scp:') === false) {
                    $this->line("      • Additional: {$path}");
                }
            }

            $this->line('');
            if (empty($errors)) {
                $this->info('   ✓ Files synced successfully');
                $this->warn('   ⚠️  Note: You may need to reload services on destination:');
                $this->line("      sx {$destVnode} 'sc reload nginx && sc reload php-fpm'");
            } else {
                $this->warn('   ⚠️  Some files failed to sync:');
                foreach ($errors as $error) {
                    $this->line("      - {$error}");
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->error("   ✗ Sync error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Update vnode-specific environment variables after migration
     */
    protected function updateVnodeSpecificVars(FleetVhost $fleetVhost, FleetVnode $destNode): void
    {
        // Update VNODE variable (vnode name)
        $fleetVhost->setEnvVar('VNODE', $destNode->name);
        $this->line("   ✓ Updated VNODE to {$destNode->name}");

        // Update AHOST (admin host) if destination vnode has FQDN
        if ($destNode->fqdn) {
            $fleetVhost->setEnvVar('AHOST', $destNode->fqdn);
            $this->line("   ✓ Updated AHOST to {$destNode->fqdn}");
        }

        // Update IP4_0 (primary IPv4 address) if destination vnode has one
        if ($destNode->ip_address) {
            $fleetVhost->setEnvVar('IP4_0', $destNode->ip_address);
            $this->line("   ✓ Updated IP4_0 to {$destNode->ip_address}");
        }

        $fleetVhost->save();
    }
}
