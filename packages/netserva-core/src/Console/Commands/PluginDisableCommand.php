<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\PluginManager;

class PluginDisableCommand extends Command
{
    protected $signature = 'plugin:disable
                            {name : The plugin name or class}
                            {--force : Skip dependency check}';

    protected $description = 'Disable a plugin';

    public function handle(PluginManager $pluginManager): int
    {
        $name = $this->argument('name');

        if (! $pluginManager->exists($name)) {
            $this->components->error("Plugin [{$name}] not found");

            return self::FAILURE;
        }

        if (! $pluginManager->isEnabled($name)) {
            $this->components->warn("Plugin [{$name}] is already disabled");

            return self::SUCCESS;
        }

        // Check if other plugins depend on this one
        if (! $this->option('force')) {
            $dependents = $pluginManager->getDependents($name);

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

        $pluginManager->disable($name);

        $this->components->info("Plugin [{$name}] disabled successfully");

        return self::SUCCESS;
    }
}
