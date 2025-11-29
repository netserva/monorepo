<?php

namespace NetServa\Core\Console\Commands;

use NetServa\Fleet\Models\FleetVhost;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Change VHost Configuration Command
 *
 * Follows NetServa CRUD pattern: chvconf (change vhost config)
 * Usage: chvconf <vnode> <vhost> <variable> [value]
 * Example: chvconf markc markc.goldcoast.org WPATH /srv/markc.goldcoast.org/web
 *
 * DATABASE-FIRST: Updates environment_vars in FleetVhost model
 */
class ChvconfCommand extends BaseNetServaCommand
{
    protected $signature = 'chvconf {vnode : VNode name}
                           {vhost : VHost domain}
                           {variable : Variable name to set}
                           {value? : Variable value (prompts if not provided)}
                           {--unset : Remove the variable instead of setting it}
                           {--force : Skip confirmation}
                           {--dry-run : Show what would be changed}';

    protected $description = 'Change VHost configuration variable (NetServa CRUD pattern)';

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');
            $variable = strtoupper($this->argument('variable'));
            $value = $this->argument('value');

            // Find VHost in database
            $vhost = FleetVhost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on vnode {$VNODE}");
                $this->line("   💡 Run: php artisan addfleet {$VNODE}");

                return 1;
            }

            // Handle --unset flag
            if ($this->option('unset')) {
                return $this->unsetVariable($vhost, $variable);
            }

            // Get current value
            $currentValue = $vhost->getEnvVar($variable);

            // Prompt for value if not provided
            if ($value === null) {
                if ($this->option('dry-run')) {
                    $this->error('❌ Value required in dry-run mode');
                    $this->line("   Usage: chvconf {$VNODE} {$VHOST} {$variable} <value> --dry-run");

                    return 1;
                }

                if ($currentValue !== null) {
                    $this->line("Current value: <fg=yellow>{$currentValue}</>");
                }

                $value = text(
                    label: "Enter value for {$variable}",
                    default: $currentValue ?? '',
                    required: true,
                    hint: 'Press Enter to keep current value'
                );
            }

            // Dry-run mode
            if ($this->option('dry-run')) {
                $this->info("🔍 DRY RUN: Would change {$variable} for {$VHOST}");
                $this->line('');

                if ($currentValue !== null) {
                    $this->line("   Current: <fg=red>{$currentValue}</>");
                    $this->line("   New:     <fg=green>{$value}</>");
                    $this->line('');
                    $this->line('   Would update vconfs table');
                } else {
                    $this->line("   New:     <fg=green>{$value}</>");
                    $this->line('');
                    $this->line('   Would create new variable in vconfs table');
                }

                return 0;
            }

            // Set the variable
            $vhost->setEnvVar($variable, $value);
            $vhost->save();

            $action = $currentValue === null ? 'Set' : 'Updated';
            $this->info("✅ {$action} {$variable}={$value} for {$VHOST}");

            return 0;
        });
    }

    protected function unsetVariable(FleetVhost $vhost, string $variable): int
    {
        $currentValue = $vhost->getEnvVar($variable);

        if ($currentValue === null) {
            $this->warn("⚠️  Variable {$variable} is not set for {$vhost->domain}");

            return 0;
        }

        // Dry-run mode
        if ($this->option('dry-run')) {
            $this->info("🔍 DRY RUN: Would remove {$variable} from {$vhost->domain}");
            $this->line('');
            $this->line("   Current value: <fg=red>{$currentValue}</>");
            $this->line('');
            $this->line('   Would delete from vconfs table');

            return 0;
        }

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: "Remove {$variable} (current value: '{$currentValue}')?",
                default: false
            );

            if (! $confirmed) {
                $this->info('🛑 Removal cancelled');

                return 0;
            }
        }

        $vhost->setEnvVar($variable, null);
        $vhost->save();

        $this->info("✅ Removed {$variable} from {$vhost->domain}");

        return 0;
    }
}
