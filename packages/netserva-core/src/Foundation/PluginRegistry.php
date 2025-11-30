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

            // Read composer.json for additional metadata
            $composerData = $this->readComposerJson($pluginId);

            InstalledPlugin::updateOrCreate(
                ['name' => $pluginId],
                [
                    'plugin_class' => $pluginClass,
                    'is_enabled' => $enable,
                    'version' => $composerData['version'] ?? (method_exists($plugin, 'getVersion') ? $plugin->getVersion() : '1.0.0'),
                    'config' => method_exists($plugin, 'getDefaultConfig') ? $plugin->getDefaultConfig() : [],
                    'package_name' => $composerData['name'] ?? null,
                    'description' => $composerData['description'] ?? null,
                    'author' => $composerData['author'] ?? null,
                    'source' => $composerData['source'] ?? 'local',
                    'source_url' => $composerData['source_url'] ?? null,
                    'category' => $composerData['category'] ?? null,
                    'composer_data' => $composerData,
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
     * Sync plugin metadata from composer.json
     */
    public function syncPlugin(InstalledPlugin $plugin): bool
    {
        try {
            $composerData = $this->readComposerJson($plugin->name);

            if (empty($composerData)) {
                Log::warning("No composer.json found for plugin {$plugin->name}");

                return false;
            }

            $plugin->update([
                'package_name' => $composerData['name'] ?? $plugin->package_name,
                'description' => $composerData['description'] ?? $plugin->description,
                'author' => $composerData['author'] ?? $plugin->author,
                'version' => $composerData['version'] ?? $plugin->version,
                'source' => $composerData['source'] ?? $plugin->source ?? 'local',
                'source_url' => $composerData['source_url'] ?? $plugin->source_url,
                'category' => $composerData['category'] ?? $plugin->category,
                'composer_data' => $composerData,
            ]);

            Log::info("Plugin {$plugin->name} synced successfully");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to sync plugin {$plugin->name}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Sync all plugins from composer.json
     */
    public function syncAllPlugins(): array
    {
        $results = ['synced' => 0, 'failed' => 0];

        foreach (InstalledPlugin::all() as $plugin) {
            if ($this->syncPlugin($plugin)) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Read composer.json for a plugin and extract metadata
     */
    protected function readComposerJson(string $pluginId): array
    {
        // Map plugin ID to package directory
        $packageDir = str_replace('netserva-', '', $pluginId);
        $composerPath = base_path("packages/netserva-{$packageDir}/composer.json");

        if (! File::exists($composerPath)) {
            return [];
        }

        try {
            $composer = json_decode(File::get($composerPath), true);

            if (! $composer) {
                return [];
            }

            // Extract author name from authors array
            $author = null;
            if (! empty($composer['authors'][0]['name'])) {
                $author = $composer['authors'][0]['name'];
            }

            // Determine source from support URLs
            $source = 'local';
            $sourceUrl = null;
            if (! empty($composer['support']['source'])) {
                $sourceUrl = $composer['support']['source'];
                if (str_contains($sourceUrl, 'github.com')) {
                    $source = 'github';
                } elseif (str_contains($sourceUrl, 'packagist.org')) {
                    $source = 'packagist';
                }
            }

            // Derive category from keywords or package type
            $category = $this->deriveCategory($pluginId, $composer['keywords'] ?? []);

            return [
                'name' => $composer['name'] ?? null,
                'description' => $composer['description'] ?? null,
                'version' => $composer['version'] ?? '0.0.1',
                'author' => $author,
                'source' => $source,
                'source_url' => $sourceUrl,
                'category' => $category,
                'keywords' => $composer['keywords'] ?? [],
                'license' => $composer['license'] ?? null,
                'homepage' => $composer['homepage'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning("Failed to read composer.json for {$pluginId}: ".$e->getMessage());

            return [];
        }
    }

    /**
     * Derive plugin category from ID and keywords
     */
    protected function deriveCategory(string $pluginId, array $keywords): string
    {
        // Check keywords first
        $keywordMap = [
            'infrastructure' => 'Infrastructure',
            'fleet' => 'Infrastructure',
            'ssh' => 'Infrastructure',
            'dns' => 'Services',
            'mail' => 'Services',
            'email' => 'Services',
            'web' => 'Services',
            'cms' => 'Content',
            'content' => 'Content',
            'core' => 'Foundation',
            'config' => 'Foundation',
        ];

        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);
            if (isset($keywordMap[$keyword])) {
                return $keywordMap[$keyword];
            }
        }

        // Fallback to plugin ID
        return match (true) {
            str_contains($pluginId, 'fleet'), str_contains($pluginId, 'ipam'), str_contains($pluginId, 'wg') => 'Infrastructure',
            str_contains($pluginId, 'dns'), str_contains($pluginId, 'mail'), str_contains($pluginId, 'web') => 'Services',
            str_contains($pluginId, 'cms') => 'Content',
            str_contains($pluginId, 'core'), str_contains($pluginId, 'config') => 'Foundation',
            default => 'Other',
        };
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
        // NOTE: After package consolidation, active packages are:
        // core, cms, fleet (includes IPAM + WireGuard), dns, web, mail
        // Removed: config (unused), ops (enterprise bloat)
        $potentialPlugins = [
            // Core foundation (includes CLI resources)
            'netserva-core' => \NetServa\Core\CorePlugin::class,
            // Content Management
            'netserva-cms' => \NetServa\Cms\NetServaCmsPlugin::class,
            // Infrastructure (Fleet includes IPAM + WireGuard)
            'netserva-fleet' => \NetServa\Fleet\Filament\FleetPlugin::class,
            // Services
            'netserva-dns' => \NetServa\Dns\Filament\NetServaDnsPlugin::class,
            'netserva-web' => \NetServa\Web\Filament\NetServaWebPlugin::class,
            'netserva-mail' => \NetServa\Mail\Filament\NetServaMailPlugin::class,
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
