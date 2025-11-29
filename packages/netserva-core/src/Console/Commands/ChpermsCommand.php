<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;

/**
 * Change Permissions Command
 *
 * Follows NetServa CRUD pattern: chperms (critical NetServa operation)
 * Usage: chperms <vnode> <vhost>
 * Example: chperms markc markc.goldcoast.org
 * System-wide: chperms <vnode> --all
 *
 * DATABASE-FIRST: Uses FleetVhost model (environment_vars JSON column)
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
            $vhost = FleetVhost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on {$VNODE}");
                $this->line("   💡 Run: php artisan addfleet {$VNODE}");

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

                // Show stderr if available
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                // Show stdout if available (may contain error details)
                if (! empty($result['output'])) {
                    $this->line('');
                    $this->line('<fg=red>📋 Script Output:</>');
                    foreach (explode("\n", trim($result['output'])) as $line) {
                        if (! empty($line)) {
                            $this->line("   {$line}");
                        }
                    }
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
        $vhosts = FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
            ->pluck('domain')
            ->toArray();

        if (empty($vhosts)) {
            $this->line("ℹ️  No vhosts found on server {$VNODE}");
            $this->line("   💡 Run: php artisan addfleet {$VNODE}");

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
     * Based on NetServa 1.0 _chperms() logic adapted for 3.0 paths:
     * - UPATH structure: /srv/domain.com/ (not /home/uXXXX)
     * - Paths: web/app/public (webroot), var/msg (mail), var/log, var/run, var/tmp
     * - Security: SSH chroot, restrictive permissions, root-owned sensitive dirs
     * - WUGID: www-data (group name, not numeric GID)
     * - CRITICAL: Top-level /srv/vhost must be user:www-data with setgid (02750)
     */
    protected function buildPermissionScript(): string
    {
        return <<<'BASH'
#!/bin/bash
set -euo pipefail

# Arguments from caller (NS 3.0 paths)
upath=$1      # /srv/domain.com
wpath=$2      # /srv/domain.com/web/app/public
mpath=$3      # /srv/domain.com/msg
uuser=$4      # u1001 or sysadm
p_uid=$5      # 1001 or 1000
p_gid=$6      # 1001 or 1000
wugid=$7      # www-data (web server group)
domain=$8

# Validate user UID >= 1000 (safety check)
if [ "$p_uid" -lt 1000 ]; then
    echo "ERROR: UID $p_uid is less than 1000 (system user)"
    exit 10
fi

# Validate UPATH exists
if [ ! -d "$upath" ]; then
    echo "ERROR: $upath does not exist"
    exit 10
fi

echo "🔧 Fixing permissions for: $domain (UID: $p_uid, User: $uuser)"

# Step 1: Set base ownership and restrictive permissions for subdirectories
chown "$p_uid:$p_gid" -R "$upath"/*
find "$upath" -type d -exec chmod 00750 {} +
find "$upath" -type f -exec chmod 00640 {} +
echo "✓ Base permissions applied to subdirectories (750/640)"

# Step 2: CRITICAL - Fix top-level /srv/vhost ownership for SSH chroot
# For SSH chroot to work, top-level MUST be root:root for all vhosts EXCEPT sysadm (UID 1000)
if [ "$p_uid" -eq 1000 ]; then
    # sysadm vhost (primary) - user:user ownership (1000:1000)
    chown "$p_uid:$p_gid" "$upath"
    chmod 0755 "$upath"
    echo "✓ Top-level $upath: 0755 $p_uid:$p_gid (sysadm primary vhost)"
else
    # Regular vhost - MUST be root:root for SSH chroot
    chown root:root "$upath"
    chmod 0755 "$upath"
    echo "✓ Top-level $upath: 0755 root:root (SSH chroot requirement)"
fi

# Step 3: Directory structure - create if missing (NS 3.0 paths)
mkdir -p "$upath/msg"
mkdir -p "$upath/web"/{log,run,tmp}
chmod 750 "$upath/msg"
chmod 02750 "$upath/web"  # CRITICAL: setgid on web/ so new files inherit www-data group
echo "✓ msg/ and web/ directories created"

# Step 4: SSH directory (critical for security)
if [ -d "$upath/.ssh" ]; then
    chmod 700 "$upath/.ssh"
    chmod 600 "$upath/.ssh"/*
    echo "✓ .ssh/ directory: 700, keys: 600"
fi

# Step 5: GnuPG directory (if exists)
if [ -d "$upath/.gnupg" ]; then
    find "$upath/.gnupg" -type d -exec chmod 00700 {} +
    find "$upath/.gnupg" -type f -exec chmod 00600 {} +
    echo "✓ .gnupg/ directory: 700/600"
fi

# Step 6: Chroot executables (busybox, nano, rsync)
if [ -f "$upath/bin/busybox" ]; then
    chmod 750 "$upath/bin/busybox"
    echo "✓ bin/busybox: 750"
fi
[ -f "$upath/bin/nano" ] && chmod 750 "$upath/bin/nano"
[ -f "$upath/bin/rsync" ] && chmod 750 "$upath/bin/rsync"

# Step 7: Shell scripts (NS 3.0 uses ~/.rc/, legacy .sh/ support for migration)
if [ -d "$upath/.rc/bin" ]; then
    chmod 700 "$upath/.rc/bin"/*
    echo "✓ .rc/bin/ scripts: 700"
fi
if [ -d "$upath/.rc/www" ]; then
    chmod 700 "$upath/.rc/www"/*
    echo "✓ .rc/www/ scripts: 700"
fi

# Step 8: Create log files if missing (NS 3.0: logs under web/)
[ ! -f "$upath/web/log/access.log" ] && touch "$upath/web/log/access.log"
[ ! -f "$upath/web/log/cache.log" ] && touch "$upath/web/log/cache.log"

# Step 9: Web/mail directories - setgid + web group ownership
if [ -d "$mpath" ]; then
    chown "$p_uid:$p_gid" -R "$mpath"
    chmod 750 "$mpath"
    echo "✓ msg/: 750 (mail only)"
fi

# Fix web directory ownership to www-data group (CRITICAL for nginx/PHP-FPM access)
chown "$p_uid:$wugid" -R "$upath/web"
chmod 02750 "$upath/web"             # setgid so new files inherit www-data group
echo "✓ web/: 02750 with setgid (files inherit www-data group)"

# Set proper permissions for web subdirectories
chmod 660 "$upath/web/log/access.log" 2>/dev/null || true
chmod 660 "$upath/web/log/cache.log" 2>/dev/null || true
chmod 02770 "$upath/web/log"         # setgid for log rotation
chmod 02750 "$upath/web/run"         # setgid for PHP-FPM sockets
chmod 02750 "$upath/web/tmp"         # setgid for temp files
echo "✓ web/log: 02770, web/run: 02750, web/tmp: 02750 (setgid)"

# Step 10: Web root - setgid on all directories (CRITICAL for ACME challenges)
if [ -d "$wpath" ]; then
    # Apply setgid to webroot and all subdirectories
    find "$wpath" -type d -exec chown "$p_uid:$wugid" {} \;
    find "$wpath" -type d -exec chmod 02750 {} \;
    echo "✓ web/app/public: 02750 (setgid on all dirs, www-data group)"

    # Ensure files are group-readable
    find "$wpath" -type f -exec chown "$p_uid:$wugid" {} \;
    find "$wpath" -type f -exec chmod 00640 {} \;
    echo "✓ web/app/public files: 640 (group-readable by www-data)"
fi

# Step 11: WHMCS configuration files (read-only for security)
webroot="$upath/web/app/public"
[ -f "$webroot/configuration.php" ] && chmod 400 "$webroot/configuration.php"
[ -f "$webroot/whmcs/configuration.php" ] && chmod 400 "$webroot/whmcs/configuration.php"
[ -f "$webroot/billing/configuration.php" ] && chmod 400 "$webroot/billing/configuration.php"

# Step 12: lib/sh/ scripts - root-owned, web-readable index.html only
if [ -d "$webroot/lib/sh" ]; then
    chown 0:0 -R "$webroot/lib/sh"/*
    chmod 700 -R "$webroot/lib/sh"/*
    echo "✓ web/app/public/lib/sh: root-owned scripts"

    if [ -f "$webroot/lib/sh/index.html" ]; then
        chown ":$wugid" "$webroot/lib/sh/index.html"
        chmod 640 "$webroot/lib/sh/index.html"
        echo "✓ web/app/public/lib/sh/index.html: 640 (web-readable)"
    fi
fi

echo "✅ Permissions fixed successfully for $domain"
BASH;
    }

    /**
     * Build script arguments from FleetVhost environment variables
     */
    protected function buildScriptArguments(FleetVhost $vhost): array
    {
        return [
            $vhost->getEnvVar('UPATH') ?? '',      // /srv/domain.com
            $vhost->getEnvVar('WPATH') ?? '',      // /srv/domain.com/web/app/public
            $vhost->getEnvVar('MPATH') ?? '',      // /srv/domain.com/msg
            $vhost->getEnvVar('UUSER') ?? 'www-data',
            $vhost->getEnvVar('U_UID') ?? '1000',
            $vhost->getEnvVar('U_GID') ?? '1000',
            $vhost->getEnvVar('WUGID') ?? 'www-data',  // Web server group name
            $vhost->domain,
        ];
    }
}
