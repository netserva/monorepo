<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Models\InstalledPlugin;

class PluginDisableCommand extends Command
{
    protected $signature = 'plugin:disable
                            {name : The plugin name or class}
                            {--force : Skip dependency check}';

    protected $description = 'Disable a plugin';

    public function handle(PluginRegistry $pluginRegistry): int
    {
        $name = $this->argument('name');

        if (! $pluginRegistry->hasPlugin($name)) {
            $this->components->error("Plugin [{$name}] not found");

            return self::FAILURE;
        }

        $installedPlugin = InstalledPlugin::where('name', $name)->first();

        if (! $installedPlugin || ! $installedPlugin->is_enabled) {
            $this->components->warn("Plugin [{$name}] is already disabled");

            return self::SUCCESS;
        }

        // Check if other plugins depend on this one
        if (! $this->option('force')) {
            $dependents = $pluginRegistry->getDependents($name);

            if (! empty($dependents)) {
                $this->components->error("Cannot disable [{$name}] - required by:");

                foreach ($dependents as $dependent) {
                    $this->line("  - {$dependent}");
                }

                $this->newLine();
                $this->components->info('Use --force to disable anyway (may cause errors)');

                return self::FAILURE;
            }
        }

        if ($pluginRegistry->disablePlugin($name)) {
            $this->components->info("Plugin [{$name}] disabled successfully");

            return self::SUCCESS;
        }

        $this->components->error("Failed to disable plugin [{$name}]");

        return self::FAILURE;
    }
}
