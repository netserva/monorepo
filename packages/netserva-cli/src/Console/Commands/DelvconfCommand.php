<?php

namespace NetServa\Cli\Console\Commands;

use NetServa\Fleet\Models\FleetVHost;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

/**
 * Delete VHost Configuration Command
 *
 * Follows NetServa CRUD pattern: delvconf (delete vhost config)
 * Usage: delvconf <vnode> <vhost> [variable]
 * Example: delvconf markc markc.goldcoast.org WPATH
 *
 * DATABASE-FIRST: Removes variables from FleetVHost environment_vars
 */
class DelvconfCommand extends BaseNetServaCommand
{
    protected $signature = 'delvconf {vnode : VNode name}
                           {vhost : VHost domain}
                           {variable? : Specific variable to delete}
                           {--all : Delete all configuration variables}
                           {--force : Skip confirmation}
                           {--interactive : Select variables to delete interactively}';

    protected $description = 'Delete VHost configuration variables (NetServa CRUD pattern)';

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');
            $variable = $this->argument('variable');

            // Find VHost in database
            $vhost = FleetVHost::where('domain', $VHOST)
                ->whereHas('vnode', fn ($q) => $q->where('name', $VNODE))
                ->first();

            if (! $vhost) {
                $this->error("❌ VHost {$VHOST} not found on vnode {$VNODE}");
                $this->line("   💡 Run: php artisan fleet:discover --vnode={$VNODE}");

                return 1;
            }

            $envVars = $vhost->environment_vars ?? [];

            if (empty($envVars)) {
                $this->info("ℹ️  No configuration variables found for {$VHOST}");

                return 0;
            }

            // Delete all variables
            if ($this->option('all')) {
                return $this->deleteAll($vhost);
            }

            // Interactive selection
            if ($this->option('interactive')) {
                return $this->interactiveDelete($vhost, $envVars);
            }

            // Delete specific variable
            if ($variable) {
                return $this->deleteVariable($vhost, strtoupper($variable));
            }

            $this->error('❌ Must specify a variable, use --all, or use --interactive');
            $this->line('   Usage: delvconf <vnode> <vhost> <variable>');
            $this->line('   Or: delvconf <vnode> <vhost> --interactive');
            $this->line('   Or: delvconf <vnode> <vhost> --all');

            return 1;
        });
    }

    protected function deleteVariable(FleetVHost $vhost, string $variable): int
    {
        $currentValue = $vhost->getEnvVar($variable);

        if ($currentValue === null) {
            $this->warn("⚠️  Variable {$variable} is not set for {$vhost->domain}");

            return 0;
        }

        // Confirm deletion
        if (! $this->option('force')) {
            // Mask password in confirmation
            $displayValue = str_contains(strtolower($variable), 'pass')
                ? '[MASKED]'
                : $currentValue;

            $confirmed = confirm(
                label: "Delete {$variable}={$displayValue}?",
                default: false
            );

            if (! $confirmed) {
                $this->info('🛑 Deletion cancelled');

                return 0;
            }
        }

        $vhost->setEnvVar($variable, null);
        $vhost->save();

        $this->info("✅ Deleted {$variable} from {$vhost->domain}");

        return 0;
    }

    protected function deleteAll(FleetVHost $vhost): int
    {
        $count = count($vhost->environment_vars ?? []);

        if ($count === 0) {
            $this->info("ℹ️  No variables to delete for {$vhost->domain}");

            return 0;
        }

        if (! $this->option('force')) {
            $this->warn("⚠️  This will delete ALL {$count} configuration variables!");

            $confirmed = confirm(
                label: "Delete all configuration for {$vhost->domain}?",
                default: false
            );

            if (! $confirmed) {
                $this->info('🛑 Deletion cancelled');

                return 0;
            }
        }

        $vhost->environment_vars = [];
        $vhost->save();

        $this->info("✅ Deleted all {$count} variables from {$vhost->domain}");

        return 0;
    }

    protected function interactiveDelete(FleetVHost $vhost, array $envVars): int
    {
        $this->line("<fg=blue>📋 Select variables to delete from:</> <fg=yellow>{$vhost->domain}</>");
        $this->line('');

        // Build options array
        $options = [];
        foreach ($envVars as $key => $value) {
            // Mask passwords in display
            $displayValue = str_contains(strtolower($key), 'pass')
                ? '[MASKED]'
                : $value;

            $options[$key] = "{$key} = {$displayValue}";
        }

        $selected = multiselect(
            label: 'Select variables to delete (Space to select, Enter to confirm)',
            options: $options,
            hint: 'Use arrow keys and space to select multiple variables'
        );

        if (empty($selected)) {
            $this->info('🛑 No variables selected');

            return 0;
        }

        // Confirm deletion
        if (! $this->option('force')) {
            $this->line('');
            $this->line('<fg=yellow>Variables to be deleted:</>');
            foreach ($selected as $var) {
                $this->line("  • {$var}");
            }
            $this->line('');

            $confirmed = confirm(
                label: 'Delete these '.count($selected).' variables?',
                default: false
            );

            if (! $confirmed) {
                $this->info('🛑 Deletion cancelled');

                return 0;
            }
        }

        // Delete selected variables
        foreach ($selected as $var) {
            $vhost->setEnvVar($var, null);
        }
        $vhost->save();

        $this->info('✅ Deleted '.count($selected)." variables from {$vhost->domain}");

        return 0;
    }
}
