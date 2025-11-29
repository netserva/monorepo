<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVhost;

/**
 * VHost Repair Service
 *
 * Repairs vhost infrastructure issues detected during validation
 * Uses vconfs table as source of truth for all repairs
 *
 * Created: 20251012
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class VhostRepairService
{
    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        $this->remoteExecution = $remoteExecution;
    }

    /**
     * Repair vhost infrastructure based on validation issues
     *
     * @param  FleetVhost  $vhost  The vhost to repair
     * @param  array  $validationResult  Validation result with issues
     * @param  bool  $dryRun  Show repair plan without executing
     * @return array Result with repairs performed
     */
    public function repairVhost(FleetVhost $vhost, array $validationResult, bool $dryRun = false): array
    {
        $vnode = $vhost->vnode;

        if (! $vnode) {
            return [
                'success' => false,
                'error' => 'VHost has no associated VNode',
            ];
        }

        Log::info('Starting vhost repair', [
            'vhost' => $vhost->domain,
            'vnode' => $vnode->name,
            'dry_run' => $dryRun,
        ]);

        $repairs = [];
        $errors = [];

        // Get vhost configuration from vconfs
        $vars = $vhost->getAllEnvVars();
        if (empty($vars)) {
            return [
                'success' => false,
                'error' => 'No vhost configuration found (vconfs empty)',
            ];
        }

        // Analyze validation issues and plan repairs
        $issues = $validationResult['issues'] ?? [];
        $warnings = $validationResult['warnings'] ?? [];

        // Combine issues and warnings for repair
        $allIssues = array_merge($issues, $warnings);

        foreach ($allIssues as $issue) {
            $category = $issue['category'] ?? 'unknown';
            $message = $issue['message'];

            // Plan repairs based on issue category and message content
            match ($category) {
                'vconf_mismatch' => $repairs[] = [
                    'type' => 'vconf_mismatch',
                    'action' => 'fix_vconf_mismatch',
                    'message' => $message,
                    'issue_data' => $issue, // Preserve full issue data for repair logic
                ],
                'user' => $repairs[] = [
                    'type' => 'user',
                    'action' => 'create_missing_user',
                    'message' => $message,
                ],
                'directory' => $repairs[] = [
                    'type' => 'directory',
                    'action' => 'create_missing_directory',
                    'message' => $message,
                ],
                'permissions' => $repairs[] = [
                    'type' => 'permissions',
                    'action' => 'fix_ownership_permissions',
                    'message' => $message,
                ],
                'security' => $repairs[] = [
                    'type' => 'security',
                    'action' => 'fix_security_permissions',
                    'message' => $message,
                ],
                'service' => $repairs[] = [
                    'type' => 'service',
                    'action' => 'restart_services',
                    'message' => $message,
                ],
                'database' => null, // Database completeness checked, not repairable
                default => null, // Skip non-repairable issues
            };
        }

        if (empty($repairs)) {
            return [
                'success' => true,
                'message' => 'No repairable issues found',
                'repairs' => [],
            ];
        }

        // Dry run: return repair plan without executing
        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'repairs_planned' => $repairs,
            ];
        }

        // Execute repairs
        foreach ($repairs as $index => $repair) {
            $result = match ($repair['action']) {
                'fix_vconf_mismatch' => $this->repairVconfMismatch($vhost, $vnode, $vars, $repair['issue_data'] ?? []),
                'create_missing_user' => $this->repairMissingUser($vnode, $vars),
                'create_missing_directory' => $this->repairMissingDirectories($vnode, $vars),
                'fix_ownership_permissions' => $this->repairOwnershipPermissions($vnode, $vars),
                'fix_security_permissions' => $this->repairSecurityPermissions($vnode, $vars),
                'restart_services' => $this->restartServices($vnode),
                default => ['success' => false, 'error' => 'Unknown repair action'],
            };

            if ($result['success']) {
                $repairs[$index]['status'] = 'completed';
            } else {
                $repairs[$index]['status'] = 'failed';
                $repairs[$index]['error'] = $result['error'];
                $errors[] = $result['error'];
            }
        }

        $success = empty($errors);

        Log::info('VHost repair completed', [
            'vhost' => $vhost->domain,
            'success' => $success,
            'repairs' => count($repairs),
            'errors' => count($errors),
        ]);

        return [
            'success' => $success,
            'repairs' => $repairs,
            'errors' => $errors,
        ];
    }

    /**
     * Repair vconf mismatch with actual system state
     *
     * Intelligent bidirectional repair:
     * - WUGID mismatch: Always update vconfs to match OSTYP
     * - UUSER/UID/GID mismatch: Fix vconfs to match actual system owner (reality wins)
     *
     * This follows the principle: If the system is working, fix the database.
     */
    protected function repairVconfMismatch($vhost, $vnode, array $vars, array $issueData): array
    {
        $message = $issueData['message'] ?? '';

        // Handle WUGID mismatch (simple case)
        if (str_contains($message, 'WUGID')) {
            return $this->repairWugidMismatch($vhost, $vars, $issueData);
        }

        // Handle UUSER/U_UID/U_GID mismatch (complex case)
        // Match messages like: "UPATH owner", "vhost-specific user", "VConfs do not match"
        if (str_contains($message, 'UPATH owner') ||
            str_contains($message, 'vhost-specific user') ||
            str_contains($message, 'VConfs do not match')) {
            return $this->repairOwnerMismatch($vhost, $vnode, $vars, $issueData);
        }

        return [
            'success' => false,
            'error' => 'Unknown vconf mismatch type: '.$message,
        ];
    }

    /**
     * Repair WUGID mismatch - Always fix vconfs to match OSTYP
     */
    protected function repairWugidMismatch($vhost, array $vars, array $issueData): array
    {
        $OSTYP = $vars['OSTYP'] ?? 'debian';

        $expectedWugid = match ($OSTYP) {
            'debian' => 'www-data',
            'alpine', 'manjaro' => 'nginx',
            default => 'www-data',
        };

        // Update WUGID in vconfs (use correct column names: name, value)
        $vconf = \NetServa\Core\Models\VConf::where('fleet_vhost_id', $vhost->id)
            ->where('name', 'WUGID')
            ->first();

        if ($vconf) {
            $vconf->update(['value' => $expectedWugid]);
        } else {
            // Create WUGID if doesn't exist
            \NetServa\Core\Models\VConf::create([
                'fleet_vhost_id' => $vhost->id,
                'name' => 'WUGID',
                'value' => $expectedWugid,
                'category' => 'web',
                'is_sensitive' => false,
            ]);
        }

        Log::info('Updated WUGID in vconfs', [
            'vhost' => $vhost->domain,
            'ostyp' => $OSTYP,
            'new_wugid' => $expectedWugid,
        ]);

        return ['success' => true];
    }

    /**
     * Repair UUSER/U_UID/U_GID mismatch
     *
     * Strategy: Use vhost-specific user if exists, otherwise use actual owner
     * This handles both vconf corrections and ownership fixes
     */
    protected function repairOwnerMismatch($vhost, $vnode, array $vars, array $issueData): array
    {
        // Extract expected (correct) values from issue data
        $expectedVconf = $issueData['expected_vconf'] ?? '';

        // Parse: "UUSER=u1001, U_UID=1001, U_GID=1001"
        if (! preg_match('/UUSER=(\w+), U_UID=(\d+), U_GID=(\d+)/', $expectedVconf, $matches)) {
            return [
                'success' => false,
                'error' => 'Could not parse expected vconf values from issue data',
            ];
        }

        $correctUser = $matches[1];
        $correctUid = $matches[2];
        $correctGid = $matches[3];

        $UPATH = $vars['UPATH'] ?? null;
        $WPATH = $vars['WPATH'] ?? null;

        if (! $UPATH) {
            return [
                'success' => false,
                'error' => 'UPATH not found in vconfs',
            ];
        }

        // Update vconfs first (use correct column names: name, value)
        $updates = [
            'UUSER' => $correctUser,
            'U_UID' => $correctUid,
            'U_GID' => $correctGid,
        ];

        foreach ($updates as $key => $value) {
            $vconf = \NetServa\Core\Models\VConf::where('fleet_vhost_id', $vhost->id)
                ->where('name', $key)
                ->first();

            if ($vconf) {
                $vconf->update(['value' => $value]);
            } else {
                \NetServa\Core\Models\VConf::create([
                    'fleet_vhost_id' => $vhost->id,
                    'name' => $key,
                    'value' => $value,
                    'category' => 'user',
                    'is_sensitive' => false,
                ]);
            }
        }

        // Fix ownership on remote system
        // Note: WUGID should already be updated in database, so get fresh value
        $OSTYP = $vars['OSTYP'] ?? 'debian';
        $WUGID = match ($OSTYP) {
            'debian' => 'www-data',
            'alpine', 'manjaro' => 'nginx',
            default => 'www-data',
        };

        $script = <<<'BASH'
#!/bin/bash
set -euo pipefail

# Verify correct user exists
if ! id -u "$correctUser" &>/dev/null; then
    echo "ERROR: User $correctUser does not exist"
    exit 1
fi

# Fix base directory ownership (UPATH)
chown -R "$correctUser":"$correctGid" "$UPATH"

BASH;

        if ($WPATH) {
            $script .= <<<'BASH'
# Fix web directory ownership (WPATH) - user:webgroup
chown -R "$correctUser":"$WUGID" "$WPATH"

BASH;
        }

        $script .= <<<'BASH'
echo "OWNERSHIP_FIXED=true"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [
                'correctUser' => $correctUser,
                'correctGid' => $correctGid,
                'UPATH' => $UPATH,
                'WPATH' => $WPATH ?? '',
                'WUGID' => $WUGID,
            ],
            asRoot: true
        );

        if (! $result['success']) {
            $errorDetails = $result['error'];
            if (isset($result['output'])) {
                $errorDetails .= "\nOutput: ".$result['output'];
            }

            return [
                'success' => false,
                'error' => 'Ownership fix failed: '.$errorDetails,
            ];
        }

        Log::info('Fixed vconfs and ownership', [
            'vhost' => $vhost->domain,
            'vconf_updates' => $updates,
            'new_owner' => "{$correctUser}:{$correctGid}",
        ]);

        return ['success' => true];
    }

    /**
     * Repair missing user
     *
     * Creates system user based on vconfs: UUSER, U_UID, U_GID, U_SHL, UPASS
     */
    protected function repairMissingUser($vnode, array $vars): array
    {
        $UUSER = $vars['UUSER'] ?? null;
        $U_UID = $vars['U_UID'] ?? null;
        $U_GID = $vars['U_GID'] ?? null;
        $U_SHL = $vars['U_SHL'] ?? '/bin/bash';
        $UPASS = $vars['UPASS'] ?? null;
        $UPATH = $vars['UPATH'] ?? null;
        $VHOST = $vars['VHOST'] ?? null;

        if (! $UUSER || ! $U_UID || ! $U_GID) {
            return [
                'success' => false,
                'error' => 'Missing required vconfs: UUSER, U_UID, U_GID',
            ];
        }

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

# Check if user already exists
if id -u "$UUSER" &>/dev/null; then
    echo "USER_EXISTS=true"
    exit 0
fi

# Create user with specific UID/GID
useradd -M -U -s "$U_SHL" -u "$U_UID" -d "$UPATH" -c "$VHOST" "$UUSER"

# Set password if provided
BASH;

        if ($UPASS) {
            $script .= <<<BASH

echo "$UUSER:$UPASS" | chpasswd
BASH;
        }

        $script .= <<<'BASH'

echo "USER_CREATED=true"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [
                'UUSER' => $UUSER,
                'U_UID' => $U_UID,
                'U_GID' => $U_GID,
                'U_SHL' => $U_SHL,
                'UPATH' => $UPATH,
                'VHOST' => $VHOST,
                'UPASS' => $UPASS ?? '',
            ],
            asRoot: true
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'User creation failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Repair missing directories
     *
     * Creates required directory structure based on vconfs
     */
    protected function repairMissingDirectories($vnode, array $vars): array
    {
        $UPATH = $vars['UPATH'] ?? null;
        $WPATH = $vars['WPATH'] ?? null;
        $U_UID = $vars['U_UID'] ?? null;
        $U_GID = $vars['U_GID'] ?? null;

        if (! $UPATH || ! $WPATH || ! $U_UID || ! $U_GID) {
            return [
                'success' => false,
                'error' => 'Missing required vconfs: UPATH, WPATH, U_UID, U_GID',
            ];
        }

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

# Create base vhost directory
mkdir -p "$UPATH"

# Create web-centric structure (NetServa 3.0)
mkdir -p "$WPATH"/app/public
mkdir -p "$WPATH"/log
mkdir -p "$WPATH"/run

# Create msg directory
mkdir -p "$UPATH"/msg

# Set ownership
chown -R "$U_UID":"$U_GID" "$UPATH"

echo "DIRECTORIES_CREATED=true"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [
                'UPATH' => $UPATH,
                'WPATH' => $WPATH,
                'U_UID' => $U_UID,
                'U_GID' => $U_GID,
            ],
            asRoot: true
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'Directory creation failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Repair ownership and permissions
     *
     * Applies NetServa 3.0 ownership rules:
     * - Base vhost directory: U_UID:U_GID
     * - Web directory: U_UID:www-data (or WUGID)
     */
    protected function repairOwnershipPermissions($vnode, array $vars): array
    {
        $UPATH = $vars['UPATH'];
        $WPATH = $vars['WPATH'];
        $U_UID = $vars['U_UID'];
        $U_GID = $vars['U_GID'];
        $WUGID = $vars['WUGID'] ?? 'www-data';

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

# Determine web group (handle numeric GID or group name)
WEB_GROUP="$WUGID"

# If WUGID is numeric, use it directly; otherwise try to resolve group name
if [[ "\$WEB_GROUP" =~ ^[0-9]+$ ]]; then
    # Numeric GID - find the actual group name
    WEB_GROUP=\$(getent group "\$WEB_GROUP" | cut -d: -f1)
    [[ -z "\$WEB_GROUP" ]] && WEB_GROUP="www-data"  # Fallback to www-data
else
    # Group name - verify it exists, fallback to www-data
    getent group "\$WEB_GROUP" > /dev/null 2>&1 || WEB_GROUP="www-data"
fi

# HARD REQUIRED: NetServa 3.0 Ownership Rules
# Rule 1: Base vhost directory owned by U_UID:U_GID (e.g., u1001:1001 or sysadm:1000)
chown -R "$U_UID":"$U_GID" "$UPATH"

# Rule 2: Web directory group changed to www-data for web server access
chown -R "$U_UID":\$WEB_GROUP "$WPATH"

# Set web-centric permissions
chmod 755 "$UPATH"
chmod 755 "$WPATH"

[[ -d "$WPATH"/app ]] && chmod 755 "$WPATH"/app
[[ -d "$WPATH"/app/public ]] && chmod 755 "$WPATH"/app/public
[[ -d "$WPATH"/log ]] && chmod 750 "$WPATH"/log
[[ -d "$WPATH"/run ]] && chmod 750 "$WPATH"/run

echo "PERMISSIONS_UPDATED=true"
echo "BASE_OWNER=$U_UID:$U_GID"
echo "WEB_OWNER=$U_UID:\$WEB_GROUP"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [
                'UPATH' => $UPATH,
                'WPATH' => $WPATH,
                'U_UID' => $U_UID,
                'U_GID' => $U_GID,
                'WUGID' => $WUGID,
            ],
            asRoot: true
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'Permission update failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Repair security permissions
     *
     * Fixes directory permissions for security compliance
     */
    protected function repairSecurityPermissions($vnode, array $vars): array
    {
        $WPATH = $vars['WPATH'] ?? null;

        if (! $WPATH) {
            return [
                'success' => false,
                'error' => 'Missing required vconf: WPATH',
            ];
        }

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

# Fix log directory permissions (750 for security)
[[ -d "$WPATH"/log ]] && chmod 750 "$WPATH"/log

# Fix run directory permissions (750 for security)
[[ -d "$WPATH"/run ]] && chmod 750 "$WPATH"/run

echo "SECURITY_PERMISSIONS_FIXED=true"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [
                'WPATH' => $WPATH,
            ],
            asRoot: true
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'Security permission fix failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Restart services (nginx, PHP-FPM, postfix, dovecot)
     *
     * Ensures web and mail services are running for the vhost
     */
    protected function restartServices($vnode): array
    {
        $script = <<<'BASH'
#!/bin/bash
set -euo pipefail

# Restart nginx
systemctl restart nginx 2>/dev/null && echo "NGINX_RESTARTED=true"

# Restart PHP-FPM (find and restart any php*-fpm service)
for service in $(systemctl list-units --type=service --all 'php*-fpm*' --no-legend | awk '{print $1}'); do
    systemctl restart "$service" 2>/dev/null && echo "PHP_FPM_RESTARTED=true"
done

# Restart Postfix if exists
if systemctl list-unit-files | grep -q postfix; then
    systemctl restart postfix 2>/dev/null && echo "POSTFIX_RESTARTED=true"
fi

# Restart Dovecot if exists
if systemctl list-unit-files | grep -q dovecot; then
    systemctl restart dovecot 2>/dev/null && echo "DOVECOT_RESTARTED=true"
fi

echo "SERVICES_RESTARTED=true"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [],
            asRoot: true
        );

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'Service restart failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }
}
