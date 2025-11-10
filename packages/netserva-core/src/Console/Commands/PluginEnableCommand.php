<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Models\InstalledPlugin;

class PluginEnableCommand extends Command
{
    protected $signature = 'plugin:enable
                            {name : The plugin name or class}';

    protected $description = 'Enable a plugin';

    public function handle(PluginRegistry $pluginRegistry): int
    {
        $name = $this->argument('name');

        if (! $pluginRegistry->hasPlugin($name)) {
            $this->components->error("Plugin [{$name}] not found");

            return self::FAILURE;
        }

        $installedPlugin = InstalledPlugin::where('name', $name)->first();

        if ($installedPlugin && $installedPlugin->is_enabled) {
            $this->components->warn("Plugin [{$name}] is already enabled");

            return self::SUCCESS;
        }

        if ($pluginRegistry->enablePlugin($name)) {
            $this->components->info("Plugin [{$name}] enabled successfully");

            // Check for dependencies
            $installedPlugin = InstalledPlugin::where('name', $name)->first();
            if ($installedPlugin && ! empty($installedPlugin->dependencies)) {
                $this->components->info('Dependencies: '.implode(', ', $installedPlugin->dependencies));
                $this->components->warn('Make sure all dependencies are enabled');
            }

            return self::SUCCESS;
        }

        $this->components->error("Failed to enable plugin [{$name}]");

        return self::FAILURE;
    }
}
