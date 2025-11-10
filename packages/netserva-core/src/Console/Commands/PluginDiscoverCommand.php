<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;

class PluginDiscoverCommand extends Command
{
    protected $signature = 'plugin:discover
                            {--install : Automatically install discovered plugins}
                            {--enable : Enable plugins after installation}';

    protected $description = 'Discover available plugins from the packages directory';

    public function handle(PluginRegistry $pluginRegistry): int
    {
        $this->components->info('Discovering plugins...');
        $this->newLine();

        // Get plugins before discovery
        $pluginsBefore = $pluginRegistry->getAvailablePluginIds();

        // 1. Discover plugins from filesystem
        $pluginRegistry->discoverPlugins();

        // Get plugins after discovery
        $pluginsAfter = $pluginRegistry->getAvailablePluginIds();

        // Find newly discovered plugins
        $newPlugins = array_diff($pluginsAfter, $pluginsBefore);

        if (empty($newPlugins)) {
            $this->components->warn('No new plugins discovered');
            $this->components->info('Total available plugins: '.count($pluginsAfter));

            return self::SUCCESS;
        }

        $this->components->success('Discovered '.count($newPlugins).' new plugin(s):');
        foreach ($newPlugins as $pluginId) {
            $this->line("  • {$pluginId}");
        }
        $this->newLine();

        // 2. Optionally auto-install discovered plugins
        if ($this->option('install')) {
            $enablePlugins = $this->option('enable');

            foreach ($newPlugins as $pluginId) {
                $this->components->task(
                    "Installing plugin: {$pluginId}",
                    fn () => $pluginRegistry->installPlugin($pluginId, $enablePlugins)
                );
            }

            $this->newLine();
            $this->components->info('Installation completed');
        } else {
            $this->components->info('Use --install flag to automatically install discovered plugins');
        }

        // 3. Clear the plugin cache
        $pluginRegistry->clearCache();
        $this->components->info('Plugin cache cleared');

        $this->newLine();
        $this->components->success('Plugin discovery completed successfully');
        $this->components->info('Total available plugins: '.count($pluginsAfter));

        return self::SUCCESS;
    }
}
