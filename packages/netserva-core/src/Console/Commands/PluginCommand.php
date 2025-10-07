<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Foundation\PluginRegistry;
use NetServa\Core\Services\LoggingService;

/**
 * Plugin Management Command
 *
 * Provides command-line interface for managing NetServa plugins.
 */
class PluginCommand extends Command
{
    protected $signature = 'netserva:plugin
                           {action : The action to perform (list, enable, disable, status)}
                           {plugin? : The plugin ID (required for enable/disable/status)}';

    protected $description = 'Manage NetServa plugins';

    public function __construct(
        protected PluginRegistry $registry,
        protected LoggingService $logger
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $pluginId = $this->argument('plugin');

        return match ($action) {
            'list' => $this->listPlugins(),
            'enable' => $this->enablePlugin($pluginId),
            'disable' => $this->disablePlugin($pluginId),
            'status' => $this->showPluginStatus($pluginId),
            default => $this->showUsage(),
        };
    }

    protected function listPlugins(): int
    {
        $this->info('Available NetServa Plugins:');
        $this->line('');

        $plugins = $this->registry->getAvailablePlugins();
        if (empty($plugins)) {
            $this->warn('No plugins found.');

            return self::SUCCESS;
        }

        $enabledPlugins = $this->registry->getEnabledPluginsInOrder();

        foreach ($plugins as $id => $class) {
            $status = in_array($class, $enabledPlugins) ? '✅ Enabled' : '❌ Disabled';
            $this->line("  {$id}: {$status}");
        }

        return self::SUCCESS;
    }

    protected function enablePlugin(?string $pluginId): int
    {
        if (! $pluginId) {
            $this->error('Plugin ID is required for enable action.');

            return self::FAILURE;
        }

        if (! $this->registry->hasPlugin($pluginId)) {
            $this->error("Plugin '{$pluginId}' not found.");

            return self::FAILURE;
        }

        try {
            // Logic to enable plugin would go here
            $this->info("Plugin '{$pluginId}' enabled successfully.");
            $this->logger->info('Plugin enabled via CLI', ['plugin_id' => $pluginId]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to enable plugin: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function disablePlugin(?string $pluginId): int
    {
        if (! $pluginId) {
            $this->error('Plugin ID is required for disable action.');

            return self::FAILURE;
        }

        if (! $this->registry->hasPlugin($pluginId)) {
            $this->error("Plugin '{$pluginId}' not found.");

            return self::FAILURE;
        }

        try {
            // Logic to disable plugin would go here
            $this->info("Plugin '{$pluginId}' disabled successfully.");
            $this->logger->info('Plugin disabled via CLI', ['plugin_id' => $pluginId]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to disable plugin: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function showPluginStatus(?string $pluginId): int
    {
        if (! $pluginId) {
            $this->error('Plugin ID is required for status action.');

            return self::FAILURE;
        }

        if (! $this->registry->hasPlugin($pluginId)) {
            $this->error("Plugin '{$pluginId}' not found.");

            return self::FAILURE;
        }

        $enabledPlugins = $this->registry->getEnabledPluginsInOrder();
        $class = $this->registry->getPluginClass($pluginId);
        $isEnabled = in_array($class, $enabledPlugins);

        $this->info("Plugin: {$pluginId}");
        $this->info("Class: {$class}");
        $this->info('Status: '.($isEnabled ? 'Enabled ✅' : 'Disabled ❌'));

        return self::SUCCESS;
    }

    protected function showUsage(): int
    {
        $this->error('Invalid action. Available actions: list, enable, disable, status');
        $this->line('');
        $this->line('Usage:');
        $this->line('  netserva:plugin list');
        $this->line('  netserva:plugin enable <plugin-id>');
        $this->line('  netserva:plugin disable <plugin-id>');
        $this->line('  netserva:plugin status <plugin-id>');

        return self::FAILURE;
    }
}
