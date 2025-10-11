<?php

namespace NetServa\Cli\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

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
     * @param FleetVHost $vhost The vhost to validate
     * @return array Validation result with issues list
     */
    public function validateVhost(FleetVHost $vhost): array
    {
        $vnode = $vhost->vnode;

        if (!$vnode) {
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
            !empty($warnings) => 'passed_with_warnings',
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
     * Validate user and permissions
     */
    protected function validateUserAndPermissions(
        FleetVNode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $UUSER = $vars['UUSER'] ?? null;
        $U_UID = $vars['U_UID'] ?? null;
        $U_GID = $vars['U_GID'] ?? null;
        $UPATH = $vars['UPATH'] ?? null;

        if (!$UUSER || !$U_UID || !$U_GID) {
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

        if (!$result['success'] || trim($result['output']) === 'NOTFOUND') {
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
                if (!str_starts_with($ownership, $UUSER.':')) {
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
        FleetVNode $vnode,
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
            if (!$path) {
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

            if (!$result['success'] || trim($result['output']) === 'NOTFOUND') {
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

                if (!$result['success'] || trim($result['output']) === 'NOTFOUND') {
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
        FleetVNode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $VHOST = $vars['VHOST'] ?? null;
        $UPATH = $vars['UPATH'] ?? null;

        if (!$VHOST) {
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

        if (!$result['success'] || trim($result['output']) === 'NOTFOUND') {
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

        if (!$result['success'] || trim($result['output']) === 'NOTFOUND') {
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
     */
    protected function validateDatabaseConsistency(
        FleetVNode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $VHOST = $vars['VHOST'] ?? null;
        $SQCMD = $vars['SQCMD'] ?? 'sqlite3 /var/lib/sqlite/sysadm/sysadm.db';

        if (!$VHOST) {
            return;
        }

        // Check if vhost exists in remote database
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "echo \"SELECT COUNT(*) FROM vhosts WHERE domain = '{$VHOST}'\" | {$SQCMD} 2>/dev/null || echo '0'"
        );

        if ($result['success']) {
            $count = (int) trim($result['output']);

            if ($count === 0) {
                $warnings[] = [
                    'category' => 'database',
                    'message' => "VHost not registered in remote database",
                    'expected' => "Entry in vhosts table for {$VHOST}",
                    'actual' => 'No database entry found',
                ];
            } else {
                $passed[] = [
                    'category' => 'database',
                    'check' => 'VHost registered in remote database',
                ];
            }
        }
    }

    /**
     * Validate service health
     */
    protected function validateServiceHealth(
        FleetVNode $vnode,
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

        // Check if PHP-FPM is running
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            "systemctl is-active 'php*-fpm' 2>/dev/null || echo 'NOTRUNNING'"
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
    }

    /**
     * Validate security settings
     */
    protected function validateSecurity(
        FleetVNode $vnode,
        array $vars,
        array &$issues,
        array &$warnings,
        array &$passed
    ): void {
        $WPATH = $vars['WPATH'] ?? null;

        if (!$WPATH) {
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
                    'message' => "Web directory has incorrect permissions",
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
                    'message' => "Log directory has incorrect permissions",
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
    public function updateVhostValidation(FleetVHost $vhost, array $validationResult): void
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
