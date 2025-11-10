<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Models\InstalledPlugin;

class PluginListCommand extends Command
{
    protected $signature = 'plugin:list
                            {--enabled : Show only enabled plugins}
                            {--disabled : Show only disabled plugins}';

    protected $description = 'List all registered plugins';

    public function handle(PluginRegistry $pluginRegistry): int
    {
        // Build query based on options
        $query = InstalledPlugin::query();

        if ($this->option('enabled')) {
            $query->where('is_enabled', true);
        }

        if ($this->option('disabled')) {
            $query->where('is_enabled', false);
        }

        $plugins = $query->get();

        if ($plugins->isEmpty()) {
            $this->components->warn('No plugins found');

            return self::SUCCESS;
        }

        $this->components->info('Installed Plugins');
        $this->newLine();

        $rows = [];
        foreach ($plugins as $plugin) {
            $rows[] = [
                $plugin->name,
                $plugin->version ?? 'N/A',
                $plugin->is_enabled ? '✓' : '✗',
                $plugin->description ?? '',
            ];
        }

        $this->table(['Name', 'Version', 'Enabled', 'Description'], $rows);

        $this->newLine();
        $this->components->info('Total: '.$plugins->count().' plugin(s)');

        return self::SUCCESS;
    }
}
