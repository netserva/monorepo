<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVHost;

/**
 * Change Permissions Command
 *
 * Follows NetServa CRUD pattern: chperms (critical NetServa operation)
 * Usage: chperms <vnode> <vhost>
 * Example: chperms markc markc.goldcoast.org
 * System-wide: chperms <vnode> --all
 *
 * DATABASE-FIRST: Uses FleetVHost model (environment_vars JSON column)
 * SSH EXECUTION: Uses heredoc-based executeScript() for safe remote execution
 */
class ChpermsCommand extends BaseNetServaCommand
{
    protected $signature = 'chperms {vnode : SSH host/VNode identifier}
                           {vhost? : Domain name to fix permissions for}
                           {--all : Fix permissions for all vhosts on server}
                           {--dry-run : Show what would be done without executing}
                           {--recursive : Apply permissions recursively}
                           {--web-only : Only fix web directory permissions}
                           {--mail-only : Only fix mail directory permissions}';

    protected $description = 'Fix file and directory permissions (NetServa CRUD pattern)';

    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        parent::__construct();
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Handle --all flag (fix all vhosts on server)
            if ($this->option('all')) {
                return $this->fixAllVhostPermissions($VNODE);
            }

            // Require VHOST for single vhost operations
            if (! $VHOST) {
                $this->error('❌ VHOST required. Usage: chperms <vnode> <vhost> or chperms <vnode> --all');

                return 1;
            }

            return $this->fixVhostPermissions($VNODE, $VHOST);
        });
    }

    protected function fixVhostPermissions(string $VNODE, string $VHOST): int
    {
        try {
            // Find VHost in database
            $vhost = FleetVHost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");
                $this->line("   💡 Run: php artisan fleet:discover --vnode={$VNODE}");

                return 1;
            }

            // Check if environment variables are configured
            if (! $vhost->environment_vars || empty($vhost->getEnvVar('UPATH'))) {
                $this->error("❌ VHost {$VHOST} has no environment variables configured");
                $this->line("   💡 Run: php artisan addvconf {$VNODE} {$VHOST}");
                $this->line('   This initializes the 53 required NS environment variables');

                return 1;
            }

            $this->line("🔧 Fixing permissions for VHost: <fg=yellow>{$VHOST}</> on server <fg=cyan>{$VNODE}</>");

            // Build permission script
            $script = $this->buildPermissionScript();
            $args = $this->buildScriptArguments($vhost);

            if ($this->option('dry-run')) {
                $this->dryRun("Fix permissions for {$VHOST} on {$VNODE}", [$script]);

                return 0;
            }

            // Execute script on remote server using heredoc-based execution
            $result = $this->remoteExecution->executeScript(
                host: $VNODE,
                script: $script,
                args: $args,
                asRoot: true
            );

            if ($result['success']) {
                $this->info("✅ Permissions fixed for {$VHOST} on {$VNODE}");

                if (! empty($result['output'])) {
                    $this->line('');
                    $this->line('<fg=blue>📋 Output:</>');
                    foreach (explode("\n", trim($result['output'])) as $line) {
                        if (! empty($line)) {
                            $this->line("   {$line}");
                        }
                    }
                }

                return 0;
            } else {
                $this->error("❌ Failed to fix permissions for {$VHOST}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return 1;
        }
    }

    protected function fixAllVhostPermissions(string $VNODE): int
    {
        // Get all vhosts for this vnode from database
        $vhosts = FleetVHost::whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
            ->pluck('domain')
            ->toArray();

        if (empty($vhosts)) {
            $this->line("ℹ️  No vhosts found on server {$VNODE}");
            $this->line("   💡 Run: php artisan fleet:discover --vnode={$VNODE}");

            return 0;
        }

        $this->line('🔧 Fixing permissions for <fg=yellow>'.count($vhosts)."</> vhosts on server <fg=cyan>{$VNODE}</>");

        $totalFixed = 0;
        $totalErrors = 0;

        foreach ($vhosts as $vhost) {
            $this->line("   Processing: <fg=yellow>{$vhost}</>");

            $result = $this->fixVhostPermissions($VNODE, $vhost);

            if ($result === 0) {
                $totalFixed++;
            } else {
                $totalErrors++;
            }
        }

        $this->line('');
        $this->info("✅ Completed: {$totalFixed} fixed, {$totalErrors} errors");

        return $totalErrors > 0 ? 1 : 0;
    }

    /**
     * Build bash script for fixing permissions (heredoc-based)
     *
     * This uses a single heredoc script instead of multiple separate commands
     * for better reliability and proper quoting handling.
     */
    protected function buildPermissionScript(): string
    {
        return <<<'BASH'
#!/bin/bash
set -euo pipefail

# Arguments from caller
upath=$1
wpath=$2
mpath=$3
uuser=$4
wugid=$5
domain=$6
web_only=${7:-false}
mail_only=${8:-false}

# User home directory
if [ -n "$upath" ] && [ -d "$upath" ]; then
    chown -R "$uuser:$wugid" "$upath"
    chmod 755 "$upath"
    echo "✓ Fixed user home: $upath"
fi

# Web directory permissions (most critical)
if [ -n "$wpath" ] && [ -d "$wpath" ] && [ "$mail_only" != "true" ]; then
    chown -R "$uuser:$wugid" "$wpath"
    find "$wpath" -type d -exec chmod 755 {} \;
    find "$wpath" -type f -exec chmod 644 {} \;
    echo "✓ Fixed web directory: $wpath"

    # Special permissions for writable directories
    for dir in var/cache var/log var/tmp uploads wp-content; do
        if [ -d "$wpath/$dir" ]; then
            chmod 775 "$wpath/$dir"
            echo "  ✓ Fixed writable dir: $wpath/$dir"
        fi
    done
fi

# Mail directory permissions
if [ -n "$mpath" ] && [ -d "$mpath" ] && [ "$web_only" != "true" ]; then
    chown -R "$uuser:$wugid" "$mpath"
    chmod 750 "$mpath"
    find "$mpath" -type f -exec chmod 640 {} \;
    echo "✓ Fixed mail directory: $mpath"
fi

# SSL certificates (if they exist)
ssl_path="/etc/ssl/le/$domain"
if [ -d "$ssl_path" ]; then
    chown root:root "$ssl_path"
    chmod 700 "$ssl_path"
    echo "✓ Fixed SSL certificates: $ssl_path"
fi

echo "Permissions fixed successfully"
BASH;
    }

    /**
     * Build script arguments from FleetVHost environment variables
     */
    protected function buildScriptArguments(FleetVHost $vhost): array
    {
        return [
            $vhost->getEnvVar('UPATH') ?? '',
            $vhost->getEnvVar('WPATH') ?? '',
            $vhost->getEnvVar('MPATH') ?? '',
            $vhost->getEnvVar('UUSER') ?? 'www-data',
            $vhost->getEnvVar('WUGID') ?? 'www-data',
            $vhost->domain,
            $this->option('web-only') ? 'true' : 'false',
            $this->option('mail-only') ? 'true' : 'false',
        ];
    }
}
