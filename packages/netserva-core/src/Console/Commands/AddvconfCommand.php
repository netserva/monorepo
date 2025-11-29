<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Core\Services\DatabaseVhostConfigService;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * Add VHost Configuration Command
 *
 * Follows NetServa CRUD pattern: addvconf (add vhost config)
 * Usage: addvconf <vnode> <vhost>
 * Example: addvconf markc markc.goldcoast.org
 *
 * DATABASE-FIRST: Populates environment_vars in FleetVhost model
 * Replicates NetServa 1.0 sethost() and gethost() functions
 */
class AddvconfCommand extends BaseNetServaCommand
{
    protected $signature = 'addvconf {vnode : VNode name}
                           {vhost : VHost domain}
                           {--minimal : Only set essential variables (13 vars)}
                           {--force : Overwrite existing variables}
                           {--dry-run : Show what would be configured}';

    protected $description = 'Initialize VHost configuration (53+ environment variables)';

    public function handle(
        DatabaseVhostConfigService $configService,
        RemoteExecutionService $remoteExec
    ): int {
        return $this->executeWithContext(function () use ($configService, $remoteExec) {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Find VHost in database
            $vhost = FleetVhost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                // Dry-run mode - just preview variables
                if ($this->option('dry-run')) {
                    return $this->showDryRunPreview($VNODE, $VHOST, $configService, $remoteExec);
                }

                // Create FleetVhost record if it doesn't exist
                $this->warn("⚠️  VHost '{$VHOST}' not found - creating database record...");

                $vnodeModel = \NetServa\Fleet\Models\FleetVnode::where('name', $VNODE)->first();

                $vhost = FleetVhost::create([
                    'domain' => $VHOST,
                    'vnode_id' => $vnodeModel->id,
                    'instance_type' => 'vhost',
                    'status' => 'active',
                    'is_active' => true,
                ]);

                $this->line("   ✅ Created fleet_vhosts record (ID: {$vhost->id})");
            }

            // Dry-run mode
            if ($this->option('dry-run')) {
                return $this->showDryRunPreview($VNODE, $VHOST, $configService, $remoteExec);
            }

            // Check if already configured
            $existingVars = $vhost->environment_vars ?? [];
            if (! empty($existingVars) && ! $this->option('force')) {
                $this->warn("⚠️  VHost {$VHOST} already has ".count($existingVars).' variables configured');

                $action = select(
                    label: 'What do you want to do?',
                    options: [
                        'cancel' => 'Cancel (keep existing)',
                        'merge' => 'Merge with new values (keep existing passwords)',
                        'regenerate' => 'Regenerate all (new passwords)',
                    ],
                    default: 'cancel'
                );

                if ($action === 'cancel') {
                    $this->info('ℹ️  No changes made');

                    return 0;
                }

                if ($action === 'merge') {
                    // Keep existing passwords
                    $passwords = [];
                    foreach (['APASS', 'DPASS', 'EPASS', 'UPASS', 'WPASS'] as $pwVar) {
                        if (isset($existingVars[$pwVar])) {
                            $passwords[$pwVar] = $existingVars[$pwVar];
                        }
                    }

                    return $this->generateConfiguration($vhost, $configService, $remoteExec, $passwords);
                }
                // action === 'regenerate' falls through to full generation
            }

            // Generate configuration
            return $this->generateConfiguration($vhost, $configService, $remoteExec);
        });
    }

    protected function generateConfiguration(
        FleetVhost $vhost,
        DatabaseVhostConfigService $configService,
        RemoteExecutionService $remoteExec,
        array $overrides = []
    ): int {
        $this->line('');
        $this->line("<fg=blue>🔧 Initializing configuration for:</> <fg=yellow>{$vhost->domain}</>");
        $this->line("<fg=gray>   Server:</> <fg=cyan>{$vhost->vnode->name}</>");
        $this->line('');

        // Detect OS from remote server
        $detectedOs = spin(
            callback: fn () => $remoteExec->getOsVariables($vhost->vnode->name),
            message: 'Detecting OS from /etc/os-release...'
        );

        if ($detectedOs && $detectedOs['OSTYP'] !== 'unknown') {
            $this->line("   <fg=gray>Detected OS:</> <fg=green>{$detectedOs['OSTYP']} {$detectedOs['OSREL']}</>");
        } else {
            $this->line('   <fg=yellow>⚠️  Could not detect OS, using defaults</>');
            $detectedOs = null;
        }

        // Use minimal or full generation
        if ($this->option('minimal')) {
            $this->line('   Mode: <fg=yellow>Minimal</> (13 essential variables)');
            $envVars = $configService->initializeMinimal($vhost, $detectedOs);
        } else {
            $this->line('   Mode: <fg=yellow>Full</> (53+ environment variables)');
            $envVars = $configService->initialize($vhost, $overrides, $detectedOs);
        }

        $this->line('');
        $this->line('<fg=green>✅ Configured '.count($envVars).' variables</> for '.$vhost->domain);
        $this->line('');

        // Show key variables
        $this->showKeyVariables($envVars);

        return 0;
    }

    protected function showKeyVariables(array $envVars): void
    {
        $this->line('<fg=blue>Key variables:</> ');
        $this->line('');

        $keyVars = [
            'UPATH' => 'Base path',
            'WPATH' => 'Web root',
            'UUSER' => 'System user',
            'U_UID' => 'User ID',
            'DNAME' => 'Database name',
            'DUSER' => 'Database user',
            'V_PHP' => 'PHP version',
            'OSTYP' => 'OS type',
        ];

        foreach ($keyVars as $var => $label) {
            $value = $envVars[$var] ?? '<not set>';
            $this->line("   <fg=gray>{$label}:</> <fg=white>{$value}</>");
        }

        $this->line('');
        $this->line('<fg=blue>View all variables:</> <fg=gray>shvconf '.$envVars['VNODE'].' '.$envVars['VHOST'].'</>');
        $this->line('<fg=blue>Change a variable:</> <fg=gray>chvconf '.$envVars['VNODE'].' '.$envVars['VHOST'].' WPATH /new/path</>');
    }

    /**
     * Show dry-run preview of what variables would be created
     * Output in plain bash-sourceable format (sorted, no decorations)
     */
    protected function showDryRunPreview(
        string $VNODE,
        string $VHOST,
        DatabaseVhostConfigService $configService,
        RemoteExecutionService $remoteExec
    ): int {
        // Detect OS silently
        $detectedOs = $remoteExec->getOsVariables($VNODE);

        // Create temporary VNode
        $tempVnode = new \NetServa\Fleet\Models\FleetVnode(['name' => $VNODE]);

        // Generate variables without saving (dry-run mode)
        $envVars = $configService->previewVariables($tempVnode, $VHOST, [], $detectedOs);

        // Sort variables alphabetically
        ksort($envVars);

        // Output in plain bash-sourceable format (ready to source)
        foreach ($envVars as $key => $value) {
            // Escape single quotes in value for bash
            $escapedValue = str_replace("'", "'\\''", $value);
            $this->line("{$key}='{$escapedValue}'");
        }

        return 0;
    }
}
