<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\PluginManager;

class PluginListCommand extends Command
{
    protected $signature = 'plugin:list
                            {--enabled : Show only enabled plugins}
                            {--disabled : Show only disabled plugins}';

    protected $description = 'List all registered plugins';

    public function handle(PluginManager $pluginManager): int
    {
        $plugins = $pluginManager->all();

        if ($this->option('enabled')) {
            $plugins = array_filter($plugins, fn ($plugin) => $plugin['is_enabled']);
        }

        if ($this->option('disabled')) {
            $plugins = array_filter($plugins, fn ($plugin) => ! $plugin['is_enabled']);
        }

        if (empty($plugins)) {
            $this->components->warn('No plugins found');

            return self::SUCCESS;
        }

        $this->components->info('Installed Plugins');
        $this->newLine();

        $rows = [];
        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin['name'],
                $plugin['version'] ?? 'N/A',
                $plugin['is_enabled'] ? '✓' : '✗',
                $plugin['description'] ?? '',
            ];
        }

        $this->table(['Name', 'Version', 'Enabled', 'Description'], $rows);

        $this->newLine();
        $this->components->info('Total: '.count($plugins).' plugin(s)');

        return self::SUCCESS;
    }
}
