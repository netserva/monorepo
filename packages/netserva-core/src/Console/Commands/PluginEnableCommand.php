<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\PluginManager;

class PluginEnableCommand extends Command
{
    protected $signature = 'plugin:enable
                            {name : The plugin name or class}';

    protected $description = 'Enable a plugin';

    public function handle(PluginManager $pluginManager): int
    {
        $name = $this->argument('name');

        if (! $pluginManager->exists($name)) {
            $this->components->error("Plugin [{$name}] not found");

            return self::FAILURE;
        }

        if ($pluginManager->isEnabled($name)) {
            $this->components->warn("Plugin [{$name}] is already enabled");

            return self::SUCCESS;
        }

        $pluginManager->enable($name);

        $this->components->info("Plugin [{$name}] enabled successfully");

        // Check for dependencies
        $plugin = $pluginManager->get($name);
        if (! empty($plugin['dependencies'])) {
            $this->components->info('Dependencies: '.implode(', ', $plugin['dependencies']));
            $this->components->warn('Make sure all dependencies are enabled');
        }

        return self::SUCCESS;
    }
}
