<?php

namespace NetServa\Core\Services;

use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * VHost Validation Service - NetServa 3.0
 *
 * Validates discovered vhosts for compliance with NetServa 3.0 standards
 * Security-by-default: No customer SSH access, web-centric architecture only
 *
 * Validation Checks:
 * 1. User & Permissions - Correct UID/GID, proper ownership
 * 2. Directory Structure - Web-centric paths (msg, web/{app,log,run,app/public})
 * 3. Configuration Files - PHP-FPM, nginx configurations
 * 4. Database Consistency - vconfs match remote reality
 * 5. Service Health - Required services running (nginx, php-fpm)
 * 6. Security - Web directory permissions (no SSH checks)
 *
 * Created: 20251009
 * Copyright (C) 1995-2025 Mark Constable <mc@netserva.org> (MIT License)
 */
class VhostValidationService
{
    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        $this->remoteExecution = $remoteExecution;
    }

    /**
     * Validate a discovered vhost for NetServa 3.0 compliance
     *
     * @param  FleetVhost  $vhost  The vhost to validate
     * @return array Validation result with issues list
     */
    public function validateVhost(FleetVhost $vhost): array
    {
        $vnode = $vhost->vnode;

        if (! $vnode) {
            return [
                'success' => false,
                'error' => 'VHost has no associated VNode',
            ];
        }

        Log::info('Validating vhost', [
            'vhost' => $vhost->domain,
            'vnode' => $vnode->name,
            'migration_status' => $vhost->migration_status,
        ]);

        $issues = [];
        $warnings = [];
        $passed = [];

        // Get vhost configuration variables
        $vars = $vhost->getAllEnvVars();

        if (empty($vars)) {
            return [
                'success' => false,
                'error' => 'VHost has no configuration variables',
                'issues' => [
                    [
                        'severity' => 'critical',
                        'category' => 'configuration',
                        'message' => 'No vconfs found - cannot validate',
                    ],
                ],
            ];
        }

        // Run validation checks
        // CRITICAL: Check vconf consistency with actual system first
        $this->validateVconfConsistency($vnode, $vars, $issues, $warnings, $passed);
        $this->validateUserAndPermissions($vnode, $vars, $issues, $warnings, $passed);
        $this->validateDirectoryStructure($vnode, $vars, $issues, $warnings, $passed);
        $this->validateConfigurationFiles($vnode, $vars, $issues, $warnings, $passed);
        $this->validateDatabaseConsistency($vnode, $vars, $issues, $warnings, $passed);
        $this->validateServiceHealth($vnode, $vars, $issues, $warnings, $passed);
        $this->validateSecurity($vnode, $vars, $issues, $warnings, $passed);

        // Determine overall validation status
        $hasCritical = collect($issues)->where('severity', 'critical')->isNotEmpty();
        $hasErrors = collect($issues)->where('severity', 'error')->isNotEmpty();

        $validationStatus = match (true) {
            $hasCritical => 'failed',
            $hasErrors => 'needs_fixes',
            ! empty($warnings) => 'passed_with_warnings',
            default => 'passed',
        };

        $result = [
            'success' => true,
            'status' => $validationStatus,
            'issues' => $issues,
            'warnings' => $warnings,
            'passed' => $passed,
            'summary' => [
                'total_checks' => count($passed) + count($issues) + count($warnings),
                'passed' => count($passed),
                'warnings' => count($warnings),
                'errors' => count($issues),
                'critical' => collect($issues)->where('severity', 'critical')->count(),
            ],
        ];

        Log::info('Validation complete', [
            'vhost' => $vhost->domain,
            'status' => $validationStatus,
            'summary' => $result['summary'],
        ]);

        return $result;
    }

    /**
     * Validate vconf consistency with actual system state
     *
     * CRITICAL CHECK: Detects when vconfs don't match actual system reality
     * This enables bidirectional repair (fix vconfs OR fix system)
     *
     * Checks:
     * 1. UUSER/U_UID/U_GID in vconfs vs actual owner of UPATH
     * 2. WUGID matches OSTYP (www-data for debian, nginx for alpine/manjaro)
     */
    protected function validateVconfConsistency(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $UPATH = $vars['UPATH'] ?? null;
        $UUSER = $vars['UUSER'] ?? null;
        $U_UID = $vars['U_UID'] ?? null;
        $U_GID = $vars['U_GID'] ?? null;
        $WUGID = $vars['WUGID'] ?? null;
        $OSTYP = $vars['OSTYP'] ?? 'debian';

        // Skip if critical vconfs missing
        if (! $UPATH || ! $UUSER || ! $U_UID || ! $U_GID) {
            return;
        }

        // Get actual owner of UPATH directory (username:uid:gid)
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "stat -c '%U:%u:%g' {$UPATH} 2>/dev/null || echo 'NOTFOUND'"
        );

        if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
            // UPATH doesn't exist - will be caught by directory validation
            return;
        }

        // Parse: username:uid:gid (e.g., "u1001:1001:1001" or "sysadm:1000:1000")
        $ownerInfo = trim($result['output']);
        $parts = explode(':', $ownerInfo);

        if (count($parts) !== 3) {
            $warnings[] = [
                'category' => 'vconf_mismatch',
                'message' => 'Could not parse UPATH owner information',
                'expected' => 'username:uid:gid format',
                'actual' => $ownerInfo,
            ];

            return;
        }

        [$actualUser, $actualUid, $actualGid] = $parts;

        // Check if a vhost-specific user exists (uXXXX pattern)
        // NetServa 3.0 standard: vhost should use dedicated uXXXX user, not sysadm
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "getent passwd | grep -E '^u[0-9]+:.*:{$UPATH}:' || echo 'NOTFOUND'"
        );

        $vhostSpecificUser = null;
        $vhostSpecificUid = null;
        $vhostSpecificGid = null;

        if ($result['success'] && trim($result['output']) !== 'NOTFOUND') {
            // Found a vhost-specific user with UPATH as home
            // Format: u1001:x:1001:1001::/srv/nc.goldcoast.org:/bin/bash
            $passwdEntry = trim($result['output']);
            $passwdParts = explode(':', $passwdEntry);

            if (count($passwdParts) >= 4) {
                $vhostSpecificUser = $passwdParts[0];
                $vhostSpecificUid = $passwdParts[2];
                $vhostSpecificGid = $passwdParts[3];
            }
        }

        // Determine correct state
        $correctUser = $vhostSpecificUser ?? $actualUser;
        $correctUid = $vhostSpecificUid ?? $actualUid;
        $correctGid = $vhostSpecificGid ?? $actualGid;

        // Check if vconfs match correct/ideal state
        $vconfMismatch = false;
        $mismatchDetails = [];

        if ($UUSER !== $correctUser) {
            $vconfMismatch = true;
            $mismatchDetails[] = "UUSER vconf={$UUSER}, should be={$correctUser}";
        }

        if ($U_UID !== $correctUid) {
            $vconfMismatch = true;
            $mismatchDetails[] = "U_UID vconf={$U_UID}, should be={$correctUid}";
        }

        if ($U_GID !== $correctGid) {
            $vconfMismatch = true;
            $mismatchDetails[] = "U_GID vconf={$U_GID}, should be={$correctGid}";
        }

        if ($vconfMismatch) {
            $warnings[] = [
                'category' => 'vconf_mismatch',
                'message' => $vhostSpecificUser
                    ? "VConfs should use vhost-specific user {$vhostSpecificUser} (NetServa 3.0 standard)"
                    : 'VConfs do not match actual UPATH owner',
                'expected_vconf' => "UUSER={$correctUser}, U_UID={$correctUid}, U_GID={$correctGid}",
                'actual_vconf' => "UUSER={$UUSER}, U_UID={$U_UID}, U_GID={$U_GID}",
                'actual_system' => "owner={$actualUser}:{$actualUid}:{$actualGid}".
                    ($vhostSpecificUser ? ", vhost_user={$correctUser}:{$correctUid}:{$correctGid}" : ''),
                'details' => implode(', ', $mismatchDetails),
                'repair_hint' => 'Use --repair to fix vconfs AND ownership to use correct user',
            ];
        } else {
            $passed[] = [
                'category' => 'vconf_consistency',
                'check' => "VConfs use correct user: {$correctUser}:{$correctUid}:{$correctGid}",
            ];
        }

        // Check WUGID matches OSTYP
        if ($WUGID) {
            $expectedWugid = match ($OSTYP) {
                'debian' => 'www-data',
                'alpine', 'manjaro' => 'nginx',
                default => 'www-data', // Default to www-data for unknown OSTYP
            };

            if ($WUGID !== $expectedWugid) {
                $warnings[] = [
                    'category' => 'vconf_mismatch',
                    'message' => 'WUGID does not match OSTYP',
                    'expected' => "WUGID should be '{$expectedWugid}' for OSTYP='{$OSTYP}'",
                    'actual' => "WUGID='{$WUGID}'",
                    'repair_hint' => 'Use --repair to update WUGID in vconfs',
                ];
            } else {
                $passed[] = [
                    'category' => 'vconf_consistency',
                    'check' => "WUGID '{$WUGID}' matches OSTYP '{$OSTYP}'",
                ];
            }
        }
    }

    /**
     * Validate user and permissions
     */
    protected function validateUserAndPermissions(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $UUSER = $vars['UUSER'] ?? null;
        $U_UID = $vars['U_UID'] ?? null;
        $U_GID = $vars['U_GID'] ?? null;
        $UPATH = $vars['UPATH'] ?? null;

        if (! $UUSER || ! $U_UID || ! $U_GID) {
            $issues[] = [
                'severity' => 'critical',
                'category' => 'user',
                'message' => 'Missing user configuration (UUSER, U_UID, or U_GID)',
                'expected' => 'UUSER, U_UID, U_GID must be set',
                'actual' => sprintf('UUSER=%s, U_UID=%s, U_GID=%s', $UUSER ?? 'null', $U_UID ?? 'null', $U_GID ?? 'null'),
            ];

            return;
        }

        // Check if user exists on remote system
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "id -u {$UUSER} 2>/dev/null || echo 'NOTFOUND'"
        );

        if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
            $issues[] = [
                'severity' => 'error',
                'category' => 'user',
                'message' => "User {$UUSER} does not exist on remote system",
                'expected' => "User {$UUSER} with UID {$U_UID}",
                'actual' => 'User not found',
            ];

            return;
        }

        $actualUid = trim($result['output']);
        if ($actualUid !== $U_UID) {
            $issues[] = [
                'severity' => 'error',
                'category' => 'user',
                'message' => "User {$UUSER} has incorrect UID",
                'expected' => "UID {$U_UID}",
                'actual' => "UID {$actualUid}",
            ];
        } else {
            $passed[] = [
                'category' => 'user',
                'check' => "User {$UUSER} exists with correct UID {$U_UID}",
            ];
        }

        // Check home directory ownership
        if ($UPATH) {
            $result = $this->remoteExecution->executeAsRoot(
                $vnode->name,
                "stat -c '%U:%G' {$UPATH} 2>/dev/null || echo 'NOTFOUND'"
            );

            if ($result['success'] && trim($result['output']) !== 'NOTFOUND') {
                $ownership = trim($result['output']);
                if (! str_starts_with($ownership, $UUSER.':')) {
                    $warnings[] = [
                        'category' => 'permissions',
                        'message' => "Directory {$UPATH} has incorrect ownership",
                        'expected' => "{$UUSER}:*",
                        'actual' => $ownership,
                    ];
                } else {
                    $passed[] = [
                        'category' => 'permissions',
                        'check' => "Directory {$UPATH} has correct ownership",
                    ];
                }
            }
        }
    }

    /**
     * Validate directory structure
     */
    protected function validateDirectoryStructure(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $requiredDirs = [
            'UPATH' => $vars['UPATH'] ?? null,
            'WPATH' => $vars['WPATH'] ?? null,
            'MPATH' => $vars['MPATH'] ?? null,
        ];

        foreach ($requiredDirs as $varName => $path) {
            if (! $path) {
                $warnings[] = [
                    'category' => 'directory',
                    'message' => "Missing {$varName} configuration",
                    'expected' => "{$varName} must be set",
                    'actual' => 'Not configured',
                ];

                continue;
            }

            $result = $this->remoteExecution->executeAsRoot(
                $vnode->name,
                "test -d {$path} && echo 'EXISTS' || echo 'NOTFOUND'"
            );

            if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
                $issues[] = [
                    'severity' => 'error',
                    'category' => 'directory',
                    'message' => "Required directory missing: {$path}",
                    'expected' => "Directory {$path} should exist",
                    'actual' => 'Directory not found',
                ];
            } else {
                $passed[] = [
                    'category' => 'directory',
                    'check' => "Required directory exists: {$path}",
                ];
            }
        }

        // Check critical subdirectories (NetServa 3.0 structure - Web-centric, No SSH)
        $WPATH = $vars['WPATH'] ?? null;
        if ($WPATH) {
            // NetServa 3.0 web-centric structure: web/{app,log,run,app/public}
            $criticalSubdirs = ['app', 'log', 'run', 'app/public'];

            foreach ($criticalSubdirs as $subdir) {
                $fullPath = "{$WPATH}/{$subdir}";
                $result = $this->remoteExecution->executeAsRoot(
                    $vnode->name,
                    "test -d {$fullPath} && echo 'EXISTS' || echo 'NOTFOUND'"
                );

                if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
                    $warnings[] = [
                        'category' => 'directory',
                        'message' => "Required web directory missing: {$fullPath}",
                        'expected' => "Directory {$fullPath} should exist",
                        'actual' => 'Directory not found',
                    ];
                } else {
                    $passed[] = [
                        'category' => 'directory',
                        'check' => "Web directory exists: {$fullPath}",
                    ];
                }
            }
        }
    }

    /**
     * Validate configuration files
     */
    protected function validateConfigurationFiles(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $VHOST = $vars['VHOST'] ?? null;
        $UPATH = $vars['UPATH'] ?? null;

        if (! $VHOST) {
            $warnings[] = [
                'category' => 'configuration',
                'message' => 'VHOST variable not set',
            ];

            return;
        }

        // Check PHP-FPM pool configuration
        $C_FPM = $vars['C_FPM'] ?? '/etc/php/8.4/fpm';
        $OSTYP = $vars['OSTYP'] ?? 'debian';

        $poolDir = ($OSTYP === 'alpine' || $OSTYP === 'manjaro')
            ? "{$C_FPM}/php-fpm.d"
            : "{$C_FPM}/pool.d";

        $poolConfig = "{$poolDir}/{$VHOST}.conf";

        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "test -f {$poolConfig} && echo 'EXISTS' || echo 'NOTFOUND'"
        );

        if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
            $issues[] = [
                'severity' => 'error',
                'category' => 'php',
                'message' => "PHP-FPM pool configuration missing: {$poolConfig}",
                'expected' => "PHP-FPM pool file should exist at {$poolConfig}",
                'actual' => 'File not found',
            ];
        } else {
            $passed[] = [
                'category' => 'php',
                'check' => 'PHP-FPM pool configuration exists',
            ];
        }

        // Check nginx configuration (optional but recommended)
        $nginxConfig = "/etc/nginx/sites-enabled/{$VHOST}";
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "test -f {$nginxConfig} && echo 'EXISTS' || echo 'NOTFOUND'"
        );

        if (! $result['success'] || trim($result['output']) === 'NOTFOUND') {
            $warnings[] = [
                'category' => 'nginx',
                'message' => "Nginx configuration not found: {$nginxConfig}",
                'expected' => 'Nginx site configuration should exist',
                'actual' => 'File not found',
            ];
        } else {
            $passed[] = [
                'category' => 'nginx',
                'check' => 'Nginx configuration exists',
            ];
        }
    }

    /**
     * Validate database consistency
     *
     * NetServa 3.0: Single source of truth - local database only
     * Validates vconfs completeness (vhost already exists since we're validating it)
     */
    protected function validateDatabaseConsistency(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $VHOST = $vars['VHOST'] ?? null;

        if (! $VHOST) {
            return;
        }

        // Check vconfs completeness in local database (NetServa 3.0)
        // The vars array comes from getAllEnvVars() which reads vconfs
        // So if we have vars, we have vconfs - just count them for the check
        $vconfCount = count($vars);

        if ($vconfCount < 10) {
            $warnings[] = [
                'category' => 'database',
                'message' => "VHost has incomplete vconfs (found {$vconfCount})",
                'expected' => "At least 10 vconf entries for {$VHOST}",
                'actual' => "{$vconfCount} vconfs found",
            ];
        } else {
            $passed[] = [
                'category' => 'database',
                'check' => "VHost has {$vconfCount} vconfs in local database",
            ];
        }
    }

    /**
     * Validate service health
     */
    protected function validateServiceHealth(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        // Check if nginx is running
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "systemctl is-active nginx 2>/dev/null || echo 'NOTRUNNING'"
        );

        if ($result['success']) {
            $status = trim($result['output']);
            if ($status === 'active') {
                $passed[] = [
                    'category' => 'service',
                    'check' => 'Nginx service is running',
                ];
            } else {
                $warnings[] = [
                    'category' => 'service',
                    'message' => 'Nginx service is not running',
                    'expected' => 'Nginx should be active',
                    'actual' => $status,
                ];
            }
        }

        // Check if PHP-FPM is running (find any php*-fpm service)
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "systemctl list-units --type=service --state=running 'php*-fpm*' | grep -q 'php.*-fpm' && echo 'active' || echo 'NOTRUNNING'"
        );

        if ($result['success']) {
            $status = trim($result['output']);
            if ($status === 'active') {
                $passed[] = [
                    'category' => 'service',
                    'check' => 'PHP-FPM service is running',
                ];
            } else {
                $warnings[] = [
                    'category' => 'service',
                    'message' => 'PHP-FPM service is not running',
                    'expected' => 'PHP-FPM should be active',
                    'actual' => $status,
                ];
            }
        }

        // Check if Postfix is running (mail server)
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "systemctl is-active postfix 2>/dev/null || echo 'NOTRUNNING'"
        );

        if ($result['success']) {
            $status = trim($result['output']);
            if ($status === 'active') {
                $passed[] = [
                    'category' => 'service',
                    'check' => 'Postfix service is running',
                ];
            } else {
                $warnings[] = [
                    'category' => 'service',
                    'message' => 'Postfix service is not running',
                    'expected' => 'Postfix should be active',
                    'actual' => $status,
                ];
            }
        }

        // Check if Dovecot is running (mail server)
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "systemctl is-active dovecot 2>/dev/null || echo 'NOTRUNNING'"
        );

        if ($result['success']) {
            $status = trim($result['output']);
            if ($status === 'active') {
                $passed[] = [
                    'category' => 'service',
                    'check' => 'Dovecot service is running',
                ];
            } else {
                $warnings[] = [
                    'category' => 'service',
                    'message' => 'Dovecot service is not running',
                    'expected' => 'Dovecot should be active',
                    'actual' => $status,
                ];
            }
        }
    }

    /**
     * Validate security settings
     */
    protected function validateSecurity(
        FleetVnode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $WPATH = $vars['WPATH'] ?? null;

        if (! $WPATH) {
            return;
        }

        // Check web directory permissions (should be readable by web server)
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "stat -c '%a' {$WPATH} 2>/dev/null || echo 'NOTFOUND'"
        );

        if ($result['success'] && trim($result['output']) !== 'NOTFOUND') {
            $perms = trim($result['output']);
            if ($perms !== '755') {
                $warnings[] = [
                    'category' => 'security',
                    'message' => 'Web directory has incorrect permissions',
                    'expected' => '755',
                    'actual' => $perms,
                ];
            } else {
                $passed[] = [
                    'category' => 'security',
                    'check' => 'Web directory has correct permissions (755)',
                ];
            }
        }

        // Check log directory permissions (should be restrictive)
        $logPath = "{$WPATH}/log";
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "stat -c '%a' {$logPath} 2>/dev/null || echo 'NOTFOUND'"
        );

        if ($result['success'] && trim($result['output']) !== 'NOTFOUND') {
            $perms = trim($result['output']);
            if ($perms !== '750') {
                $warnings[] = [
                    'category' => 'security',
                    'message' => 'Log directory has incorrect permissions',
                    'expected' => '750',
                    'actual' => $perms,
                ];
            } else {
                $passed[] = [
                    'category' => 'security',
                    'check' => 'Log directory has correct permissions (750)',
                ];
            }
        }
    }

    /**
     * Update vhost with validation results
     */
    public function updateVhostValidation(FleetVhost $vhost, array $validationResult): void
    {
        $status = $validationResult['status'] ?? 'unknown';

        // Map validation status to migration_status
        $migrationStatus = match ($status) {
            'passed', 'passed_with_warnings' => 'validated',
            'failed', 'needs_fixes' => $vhost->migration_status, // Keep current status
            default => $vhost->migration_status,
        };

        // Store issues in migration_issues field
        $vhost->update([
            'migration_status' => $migrationStatus,
            'migration_issues' => [
                'validation_status' => $status,
                'validation_date' => now()->toIso8601String(),
                'issues' => $validationResult['issues'] ?? [],
                'warnings' => $validationResult['warnings'] ?? [],
                'summary' => $validationResult['summary'] ?? [],
            ],
        ]);

        Log::info('VHost validation results stored', [
            'vhost' => $vhost->domain,
            'migration_status' => $migrationStatus,
            'validation_status' => $status,
        ]);
    }
}
