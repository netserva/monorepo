<?php

namespace NetServa\Core\Foundation;

use Filament\Contracts\Plugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Core\Models\InstalledPlugin;
use NetServa\Core\Services\DependencyResolver;

class PluginRegistry
{
    /**
     * All available plugin classes mapped by plugin ID
     */
    protected array $availablePlugins = [];

    /**
     * Dependency resolver instance
     */
    protected DependencyResolver $dependencyResolver;

    public function __construct(DependencyResolver $dependencyResolver)
    {
        $this->dependencyResolver = $dependencyResolver;
        $this->loadAvailablePlugins();
    }

    /**
     * Get all available plugin IDs
     */
    public function getAvailablePluginIds(): array
    {
        return array_keys($this->availablePlugins);
    }

    /**
     * Get all available plugins
     */
    public function getAvailablePlugins(): array
    {
        return $this->availablePlugins;
    }

    /**
     * Get plugin class by ID
     */
    public function getPluginClass(string $pluginId): ?string
    {
        return $this->availablePlugins[$pluginId] ?? null;
    }

    /**
     * Check if plugin is available
     */
    public function hasPlugin(string $pluginId): bool
    {
        return isset($this->availablePlugins[$pluginId]);
    }

    /**
     * Get enabled plugins in dependency order
     */
    public function getEnabledPluginsInOrder(): array
    {
        return Cache::remember('enabled_plugins_ordered', 300, function () {
            // Get enabled plugins from database
            $enabledPlugins = InstalledPlugin::where('is_enabled', true)
                ->pluck('plugin_class', 'name')
                ->toArray();

            // Filter to only include available plugins
            $availableEnabled = array_intersect_key($enabledPlugins, $this->availablePlugins);

            // Resolve dependencies and return plugin IDs in correct order
            $orderedIds = $this->dependencyResolver->resolve($availableEnabled);

            // Map plugin IDs back to their class names
            return array_map(fn ($id) => $availableEnabled[$id], $orderedIds);
        });
    }

    /**
     * Get all enabled plugins
     */
    public function getEnabledPlugins(): Collection
    {
        return InstalledPlugin::where('is_enabled', true)
            ->whereIn('name', $this->getAvailablePluginIds())
            ->get();
    }

    /**
     * Get disabled plugins
     */
    public function getDisabledPlugins(): Collection
    {
        return InstalledPlugin::where('is_enabled', false)
            ->whereIn('name', $this->getAvailablePluginIds())
            ->get();
    }

    /**
     * Get plugins that are available but not installed
     */
    public function getUninstalledPlugins(): array
    {
        $installedPluginIds = InstalledPlugin::pluck('name')->toArray();

        return array_diff($this->getAvailablePluginIds(), $installedPluginIds);
    }

    /**
     * Install a plugin (add to database)
     */
    public function installPlugin(string $pluginId, bool $enable = true): bool
    {
        if (! $this->hasPlugin($pluginId)) {
            Log::error("Plugin {$pluginId} is not available");

            return false;
        }

        $pluginClass = $this->availablePlugins[$pluginId];

        try {
            $plugin = new $pluginClass;

            InstalledPlugin::updateOrCreate(
                ['name' => $pluginId],
                [
                    'plugin_class' => $pluginClass,
                    'is_enabled' => $enable,
                    'version' => method_exists($plugin, 'getVersion') ? $plugin->getVersion() : '1.0.0',
                    'config' => method_exists($plugin, 'getDefaultConfig') ? $plugin->getDefaultConfig() : [],
                ]
            );

            $this->clearCache();
            Log::info("Plugin {$pluginId} installed successfully");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to install plugin {$pluginId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Uninstall a plugin (remove from database)
     */
    public function uninstallPlugin(string $pluginId): bool
    {
        try {
            $plugin = InstalledPlugin::where('name', $pluginId)->first();
            if ($plugin) {
                $plugin->delete();
                $this->clearCache();
                Log::info("Plugin {$pluginId} uninstalled successfully");
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to uninstall plugin {$pluginId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Enable a plugin
     */
    public function enablePlugin(string $pluginId): bool
    {
        try {
            $plugin = InstalledPlugin::where('name', $pluginId)->first();
            if (! $plugin) {
                // Install if not exists
                return $this->installPlugin($pluginId, true);
            }

            // Check dependencies
            if (! $this->checkDependencies($pluginId)) {
                Log::error("Cannot enable plugin {$pluginId}: missing dependencies");

                return false;
            }

            $plugin->update(['is_enabled' => true]);
            $this->clearCache();

            Log::info("Plugin {$pluginId} enabled successfully");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to enable plugin {$pluginId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Disable a plugin
     */
    public function disablePlugin(string $pluginId): bool
    {
        try {
            // Check if other plugins depend on this one
            $dependents = $this->getDependents($pluginId);
            if (! empty($dependents)) {
                Log::error("Cannot disable plugin {$pluginId}: required by ".implode(', ', $dependents));

                return false;
            }

            $plugin = InstalledPlugin::where('name', $pluginId)->first();
            if ($plugin) {
                $plugin->update(['is_enabled' => false]);
                $this->clearCache();
            }

            Log::info("Plugin {$pluginId} disabled successfully");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to disable plugin {$pluginId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Check if plugin dependencies are satisfied
     */
    public function checkDependencies(string $pluginId): bool
    {
        if (! $this->hasPlugin($pluginId)) {
            return false;
        }

        try {
            $pluginClass = $this->availablePlugins[$pluginId];
            $plugin = new $pluginClass;

            if (! method_exists($plugin, 'getDependencies')) {
                return true;
            }

            $dependencies = $plugin->getDependencies();
            $enabledPlugins = $this->getEnabledPlugins()->pluck('name')->toArray();

            foreach ($dependencies as $dependencyId) {
                if (! in_array($dependencyId, $enabledPlugins)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to check dependencies for plugin {$pluginId}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Get plugins that depend on the given plugin
     */
    public function getDependents(string $pluginId): array
    {
        $dependents = [];
        $enabledPlugins = $this->getEnabledPlugins();

        foreach ($enabledPlugins as $installedPlugin) {
            try {
                $pluginClass = $installedPlugin->plugin_class;
                if (class_exists($pluginClass)) {
                    $plugin = new $pluginClass;
                    if (method_exists($plugin, 'getDependencies')) {
                        if (in_array($pluginId, $plugin->getDependencies())) {
                            $dependents[] = $installedPlugin->name;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check dependencies for plugin {$installedPlugin->name}: ".$e->getMessage());
            }
        }

        return $dependents;
    }

    /**
     * Discover available plugins from the packages directory
     */
    public function discoverPlugins(): void
    {
        $packagesPath = base_path('packages');

        if (! File::exists($packagesPath)) {
            return;
        }

        $pluginFiles = File::glob($packagesPath.'/ns-*/src/*Plugin.php');

        foreach ($pluginFiles as $file) {
            try {
                $className = $this->extractClassName($file);

                if ($className && class_exists($className) && is_subclass_of($className, Plugin::class)) {
                    $reflection = new \ReflectionClass($className);
                    if (! $reflection->isAbstract()) {
                        $plugin = new $className;
                        $this->availablePlugins[$plugin->getId()] = $className;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to load plugin from {$file}: ".$e->getMessage());
            }
        }

        // Plugin discovery completed silently
        // (Logging removed to prevent noise on every request)
    }

    /**
     * Load predefined available plugins
     */
    protected function loadAvailablePlugins(): void
    {
        // Only include plugins that actually implement the Plugin interface
        $potentialPlugins = [
            'netserva-core' => \NetServa\Core\CorePlugin::class,
            'netserva-admin' => \NetServa\Admin\AdminPlugin::class,
            'netserva-cli' => \NetServa\Cli\Filament\NetServaCliPlugin::class,
            'netserva-cms' => \NetServa\Cms\NetServaCmsPlugin::class,
            'netserva-cron' => \NetServa\Cron\Filament\NetServaCronPlugin::class,
            'netserva-wg' => \NetServa\Wg\Filament\NetServaWgPlugin::class,
            'netserva-fleet' => \NetServa\Fleet\Filament\FleetPlugin::class,
            'netserva-ipam' => \NetServa\Ipam\Filament\NetServaIpamPlugin::class,
            'netserva-dns' => \NetServa\Dns\Filament\NetServaDnsPlugin::class,
            'netserva-web' => \NetServa\Web\Filament\NetServaWebPlugin::class,
            'netserva-mail' => \NetServa\Mail\Filament\NetServaMailPlugin::class,
            'netserva-config' => \NetServa\Config\Filament\NetServaConfigPlugin::class,
            'netserva-ops' => \NetServa\Ops\Filament\NetServaOpsPlugin::class,
        ];

        // Verify each plugin class exists and implements Plugin interface
        foreach ($potentialPlugins as $id => $class) {
            if (class_exists($class) && is_subclass_of($class, Plugin::class)) {
                try {
                    $reflection = new \ReflectionClass($class);
                    if (! $reflection->isAbstract()) {
                        $this->availablePlugins[$id] = $class;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load plugin {$id}: ".$e->getMessage());
                }
            }
        }

        // Also discover from filesystem
        $this->discoverPlugins();
    }

    /**
     * Extract class name from file path
     */
    protected function extractClassName(string $filePath): ?string
    {
        $relativePath = str_replace(base_path().'/', '', $filePath);
        $parts = explode('/', $relativePath);

        if (count($parts) < 4 || ! str_starts_with($parts[1], 'ns-')) {
            return null;
        }

        $packageName = $parts[1]; // ns-core, ns-ssh, etc.
        $fileName = pathinfo($parts[array_key_last($parts)], PATHINFO_FILENAME);

        // Convert package name to namespace
        $namespace = 'Ns\\'.ucfirst(str_replace('ns-', '', $packageName));

        return $namespace.'\\'.$fileName;
    }

    /**
     * Clear all plugin-related caches
     */
    public function clearCache(): void
    {
        Cache::forget('enabled_plugins_ordered');
        Cache::forget('disabled_plugins');
        Cache::forget('available_plugins');

        // Clear individual plugin caches
        foreach ($this->getAvailablePluginIds() as $pluginId) {
            Cache::forget("plugin_enabled_{$pluginId}");
        }
    }
}
