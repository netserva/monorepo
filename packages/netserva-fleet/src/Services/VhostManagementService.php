<?php

namespace NetServa\Fleet\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetServa\Cli\Services\BashScriptBuilder;
use NetServa\Cli\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * VHost Management Service - NetServa 3.0
 *
 * Database-First Architecture:
 * - ALL config stored in vconfs table (linked to fleet_vhosts)
 * - Uses RemoteExecutionService::executeScript() with heredoc
 * - NO file-based config storage
 * - Follows venue → vsite → vnode → vhost + vconf → vserv hierarchy
 *
 * Created: 20250108
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class VhostManagementService
{
    protected NetServaConfigurationService $configService;

    protected RemoteExecutionService $remoteExecution;

    protected BashScriptBuilder $scriptBuilder;

    public function __construct(
        NetServaConfigurationService $configService,
        RemoteExecutionService $remoteExecution,
        BashScriptBuilder $scriptBuilder
    ) {
        $this->configService = $configService;
        $this->remoteExecution = $remoteExecution;
        $this->scriptBuilder = $scriptBuilder;
    }

    /**
     * Create a new virtual host (NetServa 3.0 Database-First)
     *
     * Architecture:
     * 1. Find or fail VNode
     * 2. Generate VHost config via NetServaConfigurationService
     * 3. Create FleetVhost database record
     * 4. Store ALL config variables in vconfs table
     * 5. Execute single heredoc script via SSH (never copy scripts to remote)
     *
     * @param  string  $vnodeName  VNode hostname (e.g., "markc")
     * @param  string  $domain  VHost domain (e.g., "wp.goldcoast.org")
     * @return array Result with success status and vhost data
     */
    public function createVhost(string $vnodeName, string $domain): array
    {
        DB::beginTransaction();

        try {
            Log::info('Creating VHost (NetServa 3.0)', [
                'vnode' => $vnodeName,
                'domain' => $domain,
            ]);

            // Step 1: Find VNode in database
            $vnode = FleetVnode::where('name', $vnodeName)->first();
            if (! $vnode) {
                throw new Exception("VNode '{$vnodeName}' not found. Run 'php artisan addfleet {$vnodeName}' first.");
            }

            // Step 2: Check if vhost already exists
            $existing = FleetVhost::where('domain', $domain)
                ->where('vnode_id', $vnode->id)
                ->first();

            if ($existing) {
                throw new Exception("VHost '{$domain}' already exists on node '{$vnodeName}'");
            }

            // Step 3: Generate VHost configuration using NetServaConfigurationService
            $vhostConfig = $this->configService->generateVhostConfig($vnodeName, $domain);

            // Step 4: Create FleetVhost database record
            $fleetVhost = FleetVhost::create([
                'domain' => $domain,
                'vnode_id' => $vnode->id,
                'instance_type' => 'vhost',
                'status' => 'inactive', // Will be set to 'active' after provisioning
                'is_active' => false, // Activate after successful provisioning
            ]);

            // Step 5: Store ALL config variables in vconfs table (Database-First!)
            $platformVars = $this->configService->extractPlatformVariables($vhostConfig);
            $fleetVhost->setEnvVars($platformVars);

            Log::info('VHost database record created', [
                'fleet_vhost_id' => $fleetVhost->id,
                'domain' => $domain,
                'variables_count' => count($platformVars),
            ]);

            // Step 6: Build and execute remote provisioning script via executeScript()
            $result = $this->executeRemoteProvisioning($vnodeName, $domain, $platformVars);

            if (! $result['success']) {
                throw new Exception('Remote provisioning failed: '.$result['error']);
            }

            // Step 7: Mark as active and commit
            $fleetVhost->update([
                'status' => 'active',
                'is_active' => true,
                'last_discovered_at' => now(),
            ]);

            DB::commit();

            Log::info('VHost created successfully', [
                'domain' => $domain,
                'vnode' => $vnodeName,
                'fleet_vhost_id' => $fleetVhost->id,
            ]);

            return [
                'success' => true,
                'domain' => $domain,
                'vnode' => $vnodeName,
                'fleet_vhost_id' => $fleetVhost->id,
                'config' => $vhostConfig,
                'username' => $platformVars['UUSER'],
                'uid' => $platformVars['U_UID'],
                'paths' => [
                    'upath' => $platformVars['UPATH'],
                    'wpath' => $platformVars['WPATH'],
                ],
            ];

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('VHost creation failed', [
                'vnode' => $vnodeName,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute remote provisioning via single heredoc script
     *
     * NetServa 3.0 Pattern:
     * - Uses RemoteExecutionService::executeScript() with heredoc
     * - Runs FROM workstation TO remote server
     * - NEVER copies scripts to remote
     * - All variables sourced from vconfs table
     */
    protected function executeRemoteProvisioning(string $vnode, string $domain, array $vars): array
    {
        // Build heredoc script with ALL operations
        $script = $this->buildProvisioningScript($vars);

        // DEBUG: Extract SQCMD from script to verify
        if (preg_match('/SQCMD=\'([^\']+)\'/', $script, $matches)) {
            Log::info('DEBUG: SQCMD in generated script', [
                'SQCMD' => $matches[1],
                'domain' => $domain,
            ]);
        }

        // DEBUG: Save script to file
        $debugFile = "/tmp/debug-{$domain}.sh";
        file_put_contents($debugFile, $script);

        Log::info('Executing remote provisioning script', [
            'vnode' => $vnode,
            'domain' => $domain,
            'script_lines' => substr_count($script, "\n") + 1,
            'debug_file' => $debugFile,
            'SQCMD_from_vars' => $vars['SQCMD'] ?? 'NOT SET',
        ]);

        // Execute via RemoteExecutionService::executeScript()
        return $this->remoteExecution->executeScript(
            host: $vnode,
            script: $script,
            args: [],
            asRoot: true
        );
    }

    /**
     * Build complete provisioning script using BashScriptBuilder
     *
     * NetServa 3.0 Pure PHP Approach:
     * - Uses BashScriptBuilder class for script generation
     * - All variables from vconfs table (fully expanded)
     * - NO templates - pure PHP string building
     * - Web-centric architecture: NO customer SSH/SCP access by default
     * - Includes: User creation, web directories (msg, web/{app,log,run,app/public}),
     *   PHP-FPM pools, nginx config, web files, permissions
     *
     * @param  array  $vars  Platform variables from vconfs table (fully expanded)
     * @return string Complete bash script ready for execution
     */
    protected function buildProvisioningScript(array $vars): string
    {
        // Use BashScriptBuilder to generate script from database variables
        return $this->scriptBuilder->build($vars);
    }

    /**
     * Delete a virtual host (NetServa 3.0 Database-First)
     *
     * Architecture:
     * 1. Find FleetVhost in database
     * 2. Load config from vconfs table
     * 3. Execute single heredoc cleanup script via SSH
     * 4. Soft-delete FleetVhost (cascades to vconfs via foreign key)
     */
    public function deleteVhost(string $vnodeName, string $domain): array
    {
        DB::beginTransaction();

        try {
            Log::info('Deleting VHost (NetServa 3.0)', [
                'vnode' => $vnodeName,
                'domain' => $domain,
            ]);

            // Step 1: Find VNode
            $vnode = FleetVnode::where('name', $vnodeName)->first();
            if (! $vnode) {
                throw new Exception("VNode '{$vnodeName}' not found");
            }

            // Step 2: Find FleetVhost
            $fleetVhost = FleetVhost::where('domain', $domain)
                ->where('vnode_id', $vnode->id)
                ->first();

            if (! $fleetVhost) {
                throw new Exception("VHost '{$domain}' not found on node '{$vnodeName}'");
            }

            // Step 3: Load config from vconfs table (Database-First!)
            $vars = $fleetVhost->getAllEnvVars();

            if (empty($vars)) {
                Log::warning('No vconfs found for vhost, skipping remote cleanup', [
                    'domain' => $domain,
                ]);
            } else {
                // Step 4: Execute remote cleanup via executeScript()
                $result = $this->executeRemoteCleanup($vnodeName, $domain, $vars);

                if (! $result['success']) {
                    Log::warning('Remote cleanup failed (continuing with database deletion)', [
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            }

            // Step 5: Soft-delete FleetVhost (cascades to vconfs via FK)
            $fleetVhost->delete();

            DB::commit();

            Log::info('VHost deleted successfully', [
                'domain' => $domain,
                'vnode' => $vnodeName,
            ]);

            return [
                'success' => true,
                'domain' => $domain,
                'username' => $vars['UUSER'] ?? null,
            ];

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('VHost deletion failed', [
                'vnode' => $vnodeName,
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
     * Execute remote cleanup via single heredoc script
     */
    protected function executeRemoteCleanup(string $vnode, string $domain, array $vars): array
    {
        $script = $this->buildCleanupScript($vars);
        $args = $this->getCleanupArgs($vars);

        Log::info('Executing remote cleanup script', [
            'vnode' => $vnode,
            'domain' => $domain,
            'username' => $vars['UUSER'] ?? 'unknown',
        ]);

        return $this->remoteExecution->executeScript(
            host: $vnode,
            script: $script,
            args: $args,
            asRoot: true
        );
    }

    /**
     * Build complete cleanup script
     *
     * Safely removes all vhost components with proper error handling
     */
    protected function buildCleanupScript(array $v): string
    {
        // Extract variables with defaults
        $vhost = $v['VHOST'];
        $uuser = $v['UUSER'] ?? 'unknown';
        $upath = $v['UPATH'] ?? "/srv/{$vhost}";
        $cfpm = $v['C_FPM'] ?? '/etc/php/8.4/fpm';
        $ostyp = $v['OSTYP'] ?? 'debian';

        // Build cleanup script with variables declared at top
        $script = <<<'BASH'
#!/bin/bash
set -euo pipefail

# Variables from database
VHOST="$1"
UUSER="$2"
UPATH="$3"
C_FPM="$4"
OSTYP="$5"

echo "=== NetServa VHost Cleanup: $VHOST ==="

# 1. Remove system user
if id -u "$UUSER" &>/dev/null; then
    echo ">>> Step 1: Removing user $UUSER"
    userdel -rf "$UUSER" 2>/dev/null || echo "    ⚠ Warning: userdel failed (continuing)"
else
    echo ">>> Step 1: User $UUSER not found (already removed)"
fi

# 2. Remove SQLite database entry
if command -v sqlite3 &>/dev/null; then
    echo ">>> Step 2: Removing database entry"
    if [[ -f /var/lib/sqlite/sysadm/sysadm.db ]]; then
        echo "DELETE FROM vhosts WHERE domain = '$VHOST'" | sqlite3 /var/lib/sqlite/sysadm/sysadm.db 2>/dev/null || true
        echo "    ✓ Database entry removed"
    fi
fi

# 3. Remove nginx configuration
echo ">>> Step 3: Removing nginx configuration"
rm -f "/etc/nginx/sites-available/$VHOST" "/etc/nginx/sites-enabled/$VHOST"
if command -v nginx &>/dev/null; then
    if nginx -t &>/dev/null; then
        systemctl reload nginx 2>/dev/null && echo "    ✓ nginx reloaded"
    else
        echo "    ⚠ nginx config has errors, skipping reload"
    fi
fi

# 4. Remove PHP-FPM pool (OS-aware)
echo ">>> Step 4: Removing PHP-FPM pool"
if [[ -d "$C_FPM" ]]; then
    if [[ "$OSTYP" == "alpine" ]] || [[ "$OSTYP" == "manjaro" ]] || [[ "$OSTYP" == "cachyos" ]]; then
        rm -f "$C_FPM/php-fpm.d/$VHOST.conf"
    else
        rm -f "$C_FPM/pool.d/$VHOST.conf"
    fi
    systemctl reload php*-fpm 2>/dev/null && echo "    ✓ php-fpm reloaded"
fi

# 5. Remove SSL certificates
echo ">>> Step 5: Removing SSL certificates"
rm -rf "/etc/ssl/le/$VHOST" "/etc/ssl/le/$VHOST."*
[[ -f "/etc/letsencrypt/renewal/$VHOST.conf" ]] && rm -f "/etc/letsencrypt/renewal/$VHOST.conf"

# 6. Remove directories (final step - data loss point)
echo ">>> Step 6: Removing directories"
if [[ -d "$UPATH" ]]; then
    rm -rf "$UPATH"
    echo "    ✓ Removed: $UPATH"
else
    echo "    ✓ Directory already removed: $UPATH"
fi

echo ""
echo "=== ✓ VHost $VHOST cleaned up successfully ==="
BASH;

        // Return script configured for RemoteExecutionService::executeScript() args
        return $script;
    }

    /**
     * Get cleanup script arguments from vhost variables
     */
    protected function getCleanupArgs(array $v): array
    {
        return [
            $v['VHOST'],
            $v['UUSER'] ?? 'unknown',
            $v['UPATH'] ?? "/srv/{$v['VHOST']}",
            $v['C_FPM'] ?? '/etc/php/8.4/fpm',
            $v['OSTYP'] ?? 'debian',
        ];
    }
}
