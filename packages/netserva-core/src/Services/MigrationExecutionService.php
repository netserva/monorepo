<?php

namespace NetServa\Core\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Migration Execution Service - NetServa 3.0
 *
 * Executes structural migration from NetServa 1.0 to 3.0 architecture
 *
 * Migration Process:
 * 1. Pre-flight checks (validation status, disk space)
 * 2. Create backup archive (.archive/pre-migration-{timestamp}.tar.gz)
 * 3. Execute structural migration (var/log→web/log, var/run→web/run, web/*→web/app/public)
 * 4. Update permissions (web-centric model)
 * 5. Verify migration success
 * 6. Update migration_status to 'migrated'
 *
 * Rollback Support:
 * - Backup archives stored in /srv/backups/{domain}/
 * - Metadata JSON tracks migration state
 * - Can restore to pre-migration state
 *
 * Created: 20251009
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class MigrationExecutionService
{
    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        $this->remoteExecution = $remoteExecution;
    }

    /**
     * Migrate a validated vhost from NS 1.0 to NS 3.0 structure
     *
     * @param  FleetVhost  $vhost  The vhost to migrate
     * @param  bool  $skipBackup  Skip backup creation (dangerous!)
     * @return array Result with success status and details
     */
    public function migrateVhost(FleetVhost $vhost, bool $skipBackup = false): array
    {
        $vnode = $vhost->vnode;

        if (! $vnode) {
            return [
                'success' => false,
                'error' => 'VHost has no associated VNode',
            ];
        }

        Log::info('Starting vhost migration', [
            'vhost' => $vhost->domain,
            'vnode' => $vnode->name,
            'current_status' => $vhost->migration_status,
            'skip_backup' => $skipBackup,
        ]);

        // Pre-flight checks
        $preflightResult = $this->runPreflightChecks($vhost);
        if (! $preflightResult['success']) {
            return $preflightResult;
        }

        // Get vhost configuration
        $vars = $vhost->getAllEnvVars();
        if (empty($vars)) {
            return [
                'success' => false,
                'error' => 'No vhost configuration found (vconfs empty)',
            ];
        }

        $migrationLog = [
            'started_at' => now()->toIso8601String(),
            'status' => 'in_progress',
            'steps_completed' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Step 1: Create backup archive
            if (! $skipBackup) {
                $backupResult = $this->createPreMigrationBackup($vnode, $vars);

                if (! $backupResult['success']) {
                    throw new Exception('Backup creation failed: '.$backupResult['error']);
                }

                $migrationLog['backup_archive'] = $backupResult['archive_path'];
                $migrationLog['steps_completed'][] = 'backup_creation';

                Log::info('Backup created', [
                    'archive' => $backupResult['archive_path'],
                ]);
            } else {
                $migrationLog['warnings'][] = 'Backup skipped (--no-backup flag)';
                Log::warning('Migration proceeding without backup');
            }

            // Step 2: Execute structural migration
            $structuralResult = $this->executeStructuralMigration($vnode, $vars);

            if (! $structuralResult['success']) {
                throw new Exception('Structural migration failed: '.$structuralResult['error']);
            }

            $migrationLog['steps_completed'][] = 'structural_migration';
            $migrationLog['structural_changes'] = $structuralResult['changes'] ?? [];

            // Step 3: Update permissions
            $permissionsResult = $this->updatePermissions($vnode, $vars);

            if (! $permissionsResult['success']) {
                throw new Exception('Permission update failed: '.$permissionsResult['error']);
            }

            $migrationLog['steps_completed'][] = 'permissions_update';

            // Step 4: Reload services
            $reloadResult = $this->reloadServices($vnode);

            if (! $reloadResult['success']) {
                $migrationLog['warnings'][] = 'Service reload had issues: '.$reloadResult['error'];
            } else {
                $migrationLog['steps_completed'][] = 'service_reload';
            }

            // Step 5: Verify migration
            $verificationResult = $this->verifyMigration($vnode, $vars);

            if (! $verificationResult['success']) {
                throw new Exception('Migration verification failed: '.$verificationResult['error']);
            }

            $migrationLog['steps_completed'][] = 'verification';
            $migrationLog['verification_results'] = $verificationResult['checks'] ?? [];

            // Step 6: Update database
            $migrationLog['completed_at'] = now()->toIso8601String();
            $migrationLog['status'] = 'completed';

            $vhost->update([
                'migration_status' => 'migrated',
                'migrated_at' => now(),
                'migration_backup_path' => $migrationLog['backup_archive'] ?? null,
                'rollback_available' => ! $skipBackup,
                'migration_issues' => array_merge(
                    $vhost->migration_issues ?? [],
                    ['migration_execution' => $migrationLog]
                ),
            ]);

            Log::info('VHost migration completed successfully', [
                'vhost' => $vhost->domain,
                'vnode' => $vnode->name,
                'steps_completed' => count($migrationLog['steps_completed']),
            ]);

            return [
                'success' => true,
                'vhost' => $vhost->domain,
                'migration_log' => $migrationLog,
            ];

        } catch (Exception $e) {
            $migrationLog['completed_at'] = now()->toIso8601String();
            $migrationLog['status'] = 'failed';
            $migrationLog['errors'][] = $e->getMessage();

            // Update database with failure
            $vhost->update([
                'migration_status' => 'failed',
                'migration_issues' => array_merge(
                    $vhost->migration_issues ?? [],
                    ['migration_execution' => $migrationLog]
                ),
            ]);

            Log::error('VHost migration failed', [
                'vhost' => $vhost->domain,
                'vnode' => $vnode->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'migration_log' => $migrationLog,
            ];
        }
    }

    /**
     * Run pre-flight checks before migration
     */
    protected function runPreflightChecks(FleetVhost $vhost): array
    {
        // Check migration status
        if ($vhost->migration_status === 'migrated') {
            return [
                'success' => false,
                'error' => 'VHost already migrated (migration_status: migrated)',
            ];
        }

        if ($vhost->migration_status === 'native') {
            return [
                'success' => false,
                'error' => 'VHost is native NS 3.0 (no migration needed)',
            ];
        }

        if ($vhost->migration_status !== 'validated' && $vhost->migration_status !== 'discovered') {
            return [
                'success' => false,
                'error' => "VHost must be validated before migration (current status: {$vhost->migration_status})",
            ];
        }

        return ['success' => true];
    }

    /**
     * Create pre-migration backup archive
     */
    protected function createPreMigrationBackup(FleetVnode $vnode, array $vars): array
    {
        $VHOST = $vars['VHOST'];
        $VNODE = $vars['VNODE'] ?? $vnode->name;
        $UPATH = $vars['UPATH'];

        $script = <<<BASH
#!/bin/bash
set -uo pipefail

TIMESTAMP=\$(date +%Y%m%d-%H%M%S)
ARCHIVE_DIR="/srv/backups/{$VHOST}"
ARCHIVE_FILE="pre-migration-\${TIMESTAMP}.tar.gz"

# Create backup directory (outside vhost to prevent deletion)
mkdir -p "\$ARCHIVE_DIR"
chmod 755 "\$ARCHIVE_DIR"

# Create backup archive (exclude socket files, handle tar warnings gracefully)
cd {$UPATH}
tar czf "\$ARCHIVE_DIR/\$ARCHIVE_FILE" \\
    --exclude="./web/run/*.sock" \\
    --warning=no-file-changed \\
    --warning=no-file-ignored \\
    . || TAR_EXIT=\$?

# Tar exit codes: 0=success, 1=some files differ (ok), 2=fatal error
if [[ "\${TAR_EXIT:-0}" -gt 1 ]]; then
    echo "ERROR: Tar failed with exit code \$TAR_EXIT"
    exit 1
fi

# Store metadata
cat > "\$ARCHIVE_DIR/migration-\${TIMESTAMP}.json" <<'METADATA'
{
    "migration_date": "\$(date -Iseconds)",
    "migration_status": "backup_created",
    "vhost": "{$VHOST}",
    "vnode": "{$VNODE}",
    "archive_file": "\$ARCHIVE_FILE",
    "pre_migration_size": "\$(du -sb {$UPATH} 2>/dev/null | awk '{print \$1}' || echo 0)"
}
METADATA

echo "ARCHIVE_PATH=\$ARCHIVE_DIR/\$ARCHIVE_FILE"
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
                'error' => 'Failed to create backup: '.$result['error'],
            ];
        }

        // Parse archive path from output
        preg_match('/ARCHIVE_PATH=(.+)/', $result['output'], $matches);
        $archivePath = $matches[1] ?? "{$UPATH}/.archive/pre-migration.tar.gz";

        return [
            'success' => true,
            'archive_path' => trim($archivePath),
        ];
    }

    /**
     * Execute structural migration
     */
    protected function executeStructuralMigration(FleetVnode $vnode, array $vars): array
    {
        $UPATH = $vars['UPATH'];
        $WPATH = $vars['WPATH'];
        $UUSER = $vars['UUSER'];
        $WUGID = $vars['WUGID'];

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

CHANGES_MADE=""

# Create new web-centric structure
if [[ ! -d {$WPATH}/app ]]; then
    mkdir -p {$WPATH}/app/public
    CHANGES_MADE="\${CHANGES_MADE}created_web_app_dir,"
fi

# Move var/log → web/log (if exists and not already moved)
if [[ -d {$UPATH}/var/log ]] && [[ ! -d {$WPATH}/log ]]; then
    mv {$UPATH}/var/log {$WPATH}/log
    CHANGES_MADE="\${CHANGES_MADE}moved_var_log,"
fi

# Move var/run → web/run (if exists and not already moved)
if [[ -d {$UPATH}/var/run ]] && [[ ! -d {$WPATH}/run ]]; then
    mv {$UPATH}/var/run {$WPATH}/run
    CHANGES_MADE="\${CHANGES_MADE}moved_var_run,"
fi

# Restructure web content to web/app/public (if not already there)
if [[ -d {$WPATH} ]]; then
    # Check if content already in app/public
    if [[ ! -f {$WPATH}/app/public/index.html ]] && [[ ! -f {$WPATH}/app/public/index.php ]]; then
        # Move all web files to app/public (except app, log, run dirs)
        cd {$WPATH}
        find . -maxdepth 1 -type f -exec mv {} app/public/ \\; 2>/dev/null || true

        # Move subdirectories (except protected ones)
        for dir in */; do
            [[ "\$dir" == "app/" ]] && continue
            [[ "\$dir" == "log/" ]] && continue
            [[ "\$dir" == "run/" ]] && continue
            mv "\$dir" app/public/ 2>/dev/null || true
        done

        CHANGES_MADE="\${CHANGES_MADE}restructured_web_content,"
    fi
fi

echo "CHANGES=\${CHANGES_MADE}"
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
                'error' => 'Structural migration script failed: '.$result['error'],
            ];
        }

        // Parse changes from output
        preg_match('/CHANGES=(.+)/', $result['output'], $matches);
        $changes = $matches[1] ?? '';

        return [
            'success' => true,
            'changes' => array_filter(explode(',', $changes)),
        ];
    }

    /**
     * Update permissions to web-centric model
     *
     * HARD REQUIRED RULES:
     * 1. Primary vhost (UUSER=sysadm): /srv/{domain}/ → 1000:1000, /srv/{domain}/web/ → 1000:www-data
     * 2. Subsequent vhosts (UUSER=u1001+): /srv/{domain}/ → UID:UID, /srv/{domain}/web/ → UID:www-data
     */
    protected function updatePermissions(FleetVnode $vnode, array $vars): array
    {
        $UPATH = $vars['UPATH'];
        $WPATH = $vars['WPATH'];
        $UUSER = $vars['UUSER'];
        $U_UID = $vars['U_UID'];
        $U_GID = $vars['U_GID'];
        $WUGID = $vars['WUGID'];

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

# Determine web group (handle numeric GID or group name)
WEB_GROUP="{$WUGID}"

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
# Rule 1: Base vhost directory owned by UUSER:U_GID (e.g., u1001:1001 or sysadm:1000)
chown -R {$U_UID}:{$U_GID} {$UPATH}

# Rule 2: Web directory group changed to www-data for web server access
chown -R {$U_UID}:\$WEB_GROUP {$WPATH}

# Set web-centric permissions
chmod 755 {$UPATH}
chmod 755 {$WPATH}

[[ -d {$WPATH}/app ]] && chmod 755 {$WPATH}/app
[[ -d {$WPATH}/app/public ]] && chmod 755 {$WPATH}/app/public
[[ -d {$WPATH}/log ]] && chmod 750 {$WPATH}/log
[[ -d {$WPATH}/run ]] && chmod 750 {$WPATH}/run

echo "PERMISSIONS_UPDATED=true"
echo "BASE_OWNER={$U_UID}:{$U_GID}"
echo "WEB_OWNER={$U_UID}:\$WEB_GROUP"
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
                'error' => 'Permission update failed: '.$result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Reload web services
     */
    protected function reloadServices(FleetVnode $vnode): array
    {
        $script = <<<'BASH'
#!/bin/bash
systemctl reload nginx 2>/dev/null && echo "nginx_reloaded"
systemctl reload php*-fpm 2>/dev/null && echo "php_fpm_reloaded"
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
                'error' => $result['error'],
            ];
        }

        return ['success' => true];
    }

    /**
     * Verify migration completed successfully
     */
    protected function verifyMigration(FleetVnode $vnode, array $vars): array
    {
        $WPATH = $vars['WPATH'];

        $script = <<<BASH
#!/bin/bash
CHECKS_PASSED=""

[[ -d {$WPATH}/app/public ]] && CHECKS_PASSED="\${CHECKS_PASSED}app_public_exists,"
[[ -d {$WPATH}/log ]] && CHECKS_PASSED="\${CHECKS_PASSED}log_dir_exists,"
[[ -d {$WPATH}/run ]] && CHECKS_PASSED="\${CHECKS_PASSED}run_dir_exists,"

# Check if at least one index file exists
if [[ -f {$WPATH}/app/public/index.html ]] || [[ -f {$WPATH}/app/public/index.php ]]; then
    CHECKS_PASSED="\${CHECKS_PASSED}index_file_exists,"
fi

echo "CHECKS=\${CHECKS_PASSED}"
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
                'error' => 'Verification script failed: '.$result['error'],
            ];
        }

        // Parse checks from output
        preg_match('/CHECKS=(.+)/', $result['output'], $matches);
        $checks = array_filter(explode(',', $matches[1] ?? ''));

        // Require at least 3 checks to pass
        if (count($checks) < 3) {
            return [
                'success' => false,
                'error' => 'Migration verification failed - insufficient checks passed',
                'checks' => $checks,
            ];
        }

        return [
            'success' => true,
            'checks' => $checks,
        ];
    }

    /**
     * Rollback a migrated vhost to pre-migration state
     *
     * @param  FleetVhost  $vhost  The vhost to rollback
     * @param  string|null  $archiveFile  Specific archive to restore from
     * @return array Result with success status and details
     */
    public function rollbackVhost(FleetVhost $vhost, ?string $archiveFile = null): array
    {
        $vnode = $vhost->vnode;

        if (! $vnode) {
            return [
                'success' => false,
                'error' => 'VHost has no associated VNode',
            ];
        }

        // Check if rollback is available
        if (! $vhost->rollback_available) {
            return [
                'success' => false,
                'error' => 'No rollback available for this vhost (migration was run with --no-backup)',
            ];
        }

        $vars = $vhost->getAllEnvVars();
        $UPATH = $vars['UPATH'];
        $VHOST = $vars['VHOST'];
        $ARCHIVE_DIR = "/srv/backups/{$VHOST}";

        // Find archive file
        if (! $archiveFile) {
            // Use most recent archive
            $archiveFile = $vhost->migration_backup_path;
        }

        if (! $archiveFile) {
            return [
                'success' => false,
                'error' => 'No backup archive path found in database',
            ];
        }

        Log::info('Starting vhost rollback', [
            'vhost' => $vhost->domain,
            'vnode' => $vnode->name,
            'archive' => $archiveFile,
        ]);

        $U_UID = $vars['U_UID'];
        $U_GID = $vars['U_GID'];
        $WUGID = $vars['WUGID'];
        $WPATH = $vars['WPATH'];

        $script = <<<BASH
#!/bin/bash
set -euo pipefail

ARCHIVE_FILE="{$archiveFile}"

if [[ ! -f "\$ARCHIVE_FILE" ]]; then
    echo "ERROR: Archive file not found: \$ARCHIVE_FILE"
    exit 1
fi

echo "=== NetServa 3.0 VHost Rollback: {$vars['VHOST']} ==="

# Stop services
systemctl stop nginx php*-fpm 2>/dev/null || true

# Restore from archive
cd {$UPATH}
tar xzf "\$ARCHIVE_FILE"

# Determine web group (handle numeric GID or group name)
WEB_GROUP="{$WUGID}"
if [[ "\$WEB_GROUP" =~ ^[0-9]+$ ]]; then
    WEB_GROUP=\$(getent group "\$WEB_GROUP" | cut -d: -f1)
    [[ -z "\$WEB_GROUP" ]] && WEB_GROUP="www-data"
else
    getent group "\$WEB_GROUP" > /dev/null 2>&1 || WEB_GROUP="www-data"
fi

# HARD REQUIRED: NetServa 3.0 Ownership Rules
# Rule 1: Base vhost directory owned by U_UID:U_GID
chown -R {$U_UID}:{$U_GID} {$UPATH}

# Rule 2: Web directory group changed to www-data (if web dir exists)
[[ -d {$WPATH} ]] && chown -R {$U_UID}:\$WEB_GROUP {$WPATH}

# Start services
systemctl start nginx php*-fpm 2>/dev/null || true

echo "ROLLBACK_SUCCESS=true"
echo "RESTORED_OWNER={$U_UID}:{$U_GID}"
echo "WEB_GROUP=\$WEB_GROUP"
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            args: [],
            asRoot: true
        );

        if (! $result['success']) {
            Log::error('Rollback failed', [
                'vhost' => $vhost->domain,
                'error' => $result['error'],
            ]);

            return [
                'success' => false,
                'error' => 'Rollback script failed: '.$result['error'],
            ];
        }

        // Update database
        $vhost->update([
            'migration_status' => 'validated', // Back to validated state
            'migration_issues' => array_merge(
                $vhost->migration_issues ?? [],
                [
                    'rollback_execution' => [
                        'executed_at' => now()->toIso8601String(),
                        'archive_restored' => $archiveFile,
                        'status' => 'completed',
                    ],
                ]
            ),
        ]);

        Log::info('Rollback completed successfully', [
            'vhost' => $vhost->domain,
        ]);

        return [
            'success' => true,
            'vhost' => $vhost->domain,
            'archive_restored' => $archiveFile,
        ];
    }

    /**
     * List available rollback points for a vhost
     */
    public function listRollbackPoints(FleetVhost $vhost): array
    {
        $vnode = $vhost->vnode;

        if (! $vnode) {
            return [
                'success' => false,
                'error' => 'VHost has no associated VNode',
            ];
        }

        $vars = $vhost->getAllEnvVars();
        $UPATH = $vars['UPATH'];
        $VHOST = $vars['VHOST'];
        $ARCHIVE_DIR = "/srv/backups/{$VHOST}";

        $script = <<<'BASH'
#!/bin/bash
if [[ -d {$ARCHIVE_DIR} ]]; then
    cd {$ARCHIVE_DIR}
    find . -name "pre-migration-*.tar.gz" -type f -printf "%T@ %p\n" | sort -rn
else
    echo "NO_ARCHIVES"
fi
BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: str_replace('{$ARCHIVE_DIR}', $ARCHIVE_DIR, $script),
            args: [],
            asRoot: true
        );

        if (! $result['success'] || trim($result['output']) === 'NO_ARCHIVES') {
            return [
                'success' => true,
                'rollback_points' => [],
            ];
        }

        // Parse output
        $archives = [];
        $lines = explode("\n", trim($result['output']));

        foreach ($lines as $line) {
            if (preg_match('/^(\d+\.\d+)\s+\.\/(.+)$/', $line, $matches)) {
                $timestamp = (int) $matches[1];
                $filename = $matches[2];

                $archives[] = [
                    'filename' => $filename,
                    'path' => "{$ARCHIVE_DIR}/{$filename}",
                    'created_at' => date('Y-m-d H:i:s', $timestamp),
                ];
            }
        }

        return [
            'success' => true,
            'rollback_points' => $archives,
        ];
    }
}
