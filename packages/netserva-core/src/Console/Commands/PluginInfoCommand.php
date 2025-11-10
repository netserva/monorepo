<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Models\InstalledPlugin;

class PluginInfoCommand extends Command
{
    protected $signature = 'plugin:info
                            {name : The plugin name or class}';

    protected $description = 'Show detailed information about a plugin';

    public function handle(PluginRegistry $pluginRegistry): int
    {
        $name = $this->argument('name');

        if (! $pluginRegistry->hasPlugin($name)) {
            $this->components->error("Plugin [{$name}] not found");

            return self::FAILURE;
        }

        $plugin = InstalledPlugin::where('name', $name)->first();

        if (! $plugin) {
            $this->components->warn("Plugin [{$name}] is available but not installed");
            $pluginClass = $pluginRegistry->getPluginClass($name);
            $this->line("  <fg=gray>Class:</> {$pluginClass}");

            return self::SUCCESS;
        }

        $this->components->info("Plugin: {$plugin->name}");
        $this->newLine();

        $this->line("  <fg=gray>Class:</> {$plugin->plugin_class}");
        $this->line('  <fg=gray>Package:</> '.($plugin->package_name ?? 'N/A'));
        $this->line('  <fg=gray>Version:</> '.($plugin->version ?? 'N/A'));
        $this->line('  <fg=gray>Status:</> '.($plugin->is_enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));
        $this->line('  <fg=gray>Source:</> '.($plugin->source ?? 'N/A'));
        $this->line('  <fg=gray>Category:</> '.($plugin->category ?? 'N/A'));
        $this->newLine();

        if (! empty($plugin->description)) {
            $this->line("  <fg=gray>Description:</> {$plugin->description}");
            $this->newLine();
        }

        if (! empty($plugin->dependencies)) {
            $this->line('  <fg=gray>Dependencies:</>');
            foreach ($plugin->dependencies as $dependency) {
                $depPlugin = InstalledPlugin::where('name', $dependency)->first();
                $status = $depPlugin && $depPlugin->is_enabled
                    ? '<fg=green>✓</>'
                    : '<fg=red>✗</>';
                $this->line("    {$status} {$dependency}");
            }
            $this->newLine();
        }

        // Show dependents
        $dependents = $pluginRegistry->getDependents($name);
        if (! empty($dependents)) {
            $this->line('  <fg=gray>Required by:</>');
            foreach ($dependents as $dependent) {
                $this->line("    - {$dependent}");
            }
        }

        return self::SUCCESS;
    }
}
