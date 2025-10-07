<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Cli\Services\DatabaseVhostConfigService;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVHost;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

/**
 * Add VHost Configuration Command
 *
 * Follows NetServa CRUD pattern: addvconf (add vhost config)
 * Usage: addvconf <vnode> <vhost>
 * Example: addvconf markc markc.goldcoast.org
 *
 * DATABASE-FIRST: Populates environment_vars in FleetVHost model
 * Replicates NetServa 1.0 sethost() and gethost() functions
 */
class AddvconfCommand extends BaseNetServaCommand
{
    protected $signature = 'addvconf {vnode : VNode name}
                           {vhost : VHost domain}
                           {--minimal : Only set essential variables (13 vars)}
                           {--force : Overwrite existing variables}';

    protected $description = 'Initialize VHost configuration (53+ environment variables)';

    public function handle(
        DatabaseVhostConfigService $configService,
        RemoteExecutionService $remoteExec
    ): int {
        return $this->executeWithContext(function () use ($configService, $remoteExec) {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Find VHost in database
            $vhost = FleetVHost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on vnode {$VNODE}");
                $this->line("   💡 Run: php artisan fleet:discover --vnode={$VNODE}");

                return 1;
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
        FleetVHost $vhost,
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
}
