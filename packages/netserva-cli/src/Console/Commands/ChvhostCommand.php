<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\VhostConfigService;
use NetServa\Cli\Services\VhostManagementService;

/**
 * Change/Update VHost Command
 *
 * Follows NetServa CRUD pattern: chvhost (not "ns vhost update")
 * Usage: chvhost <vnode> <vhost> [--options]
 * Example: chvhost markc markc.goldcoast.org --php-version=8.4 --ssl=true
 */
class ChvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'chvhost {vnode : SSH host/VNode identifier}
                           {vhost : Domain name to update}
                           {--php-version= : Update PHP version (8.1, 8.2, 8.4)}
                           {--ssl= : Enable/disable SSL (true/false)}
                           {--webroot= : Change web document root}
                           {--backup : Backup current config before changes}';

    protected $description = 'Change/update virtual host configuration (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    protected VhostConfigService $vhostConfig;

    public function __construct(
        VhostManagementService $vhostService,
        VhostConfigService $vhostConfig
    ) {
        parent::__construct();
        $this->vhostService = $vhostService;
        $this->vhostConfig = $vhostConfig;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters from positional arguments
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Check if VHost exists
            if (! $this->vhostConfig->exists("{$VNODE}/{$VHOST}")) {
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
                    "Load current config from ~/.ns/var/{$VNODE}/{$VHOST}",
                    'Backup current config (if --backup specified)',
                    'Apply configuration changes: '.implode(', ', array_keys($changes)),
                    "Save updated config to ~/.ns/var/{$VNODE}/{$VHOST}",
                    "SSH to {$VNODE} and apply changes",
                    'Reload relevant services (nginx, php-fpm, etc.)',
                ]);

                return 0;
            }

            // Backup current config if requested
            if ($this->option('backup')) {
                $backupPath = $this->vhostConfig->backup("{$VNODE}/{$VHOST}");
                if ($backupPath) {
                    $this->line("📦 Backup created: <fg=gray>{$backupPath}</>");
                }
            }

            // Apply the changes
            $result = $this->applyChanges($VNODE, $VHOST, $changes);

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

    protected function applyChanges(string $VNODE, string $VHOST, array $changes): array
    {
        try {
            // Load current configuration
            $config = $this->vhostConfig->loadVhostConfig($VNODE, $VHOST);

            // Apply changes to configuration
            $updatedConfig = $config;
            $appliedChanges = [];

            foreach ($changes as $key => $value) {
                switch ($key) {
                    case 'php_version':
                        // Update PHP version in configuration
                        $updatedConfig['V_PHP'] = $value;
                        $appliedChanges['PHP Version'] = $value;
                        break;

                    case 'ssl_enabled':
                        // Update SSL configuration
                        $updatedConfig['SSL_ENABLED'] = $value;
                        $appliedChanges['SSL Enabled'] = $value;
                        break;

                    case 'webroot':
                        // Update web document root
                        $updatedConfig['WPATH'] = $value;
                        $appliedChanges['Web Root'] = $value;
                        break;
                }
            }

            // Save updated configuration
            if ($this->vhostConfig->saveVhostConfig($VNODE, $VHOST, $updatedConfig)) {
                return [
                    'success' => true,
                    'applied_changes' => $appliedChanges,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to save updated configuration',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
