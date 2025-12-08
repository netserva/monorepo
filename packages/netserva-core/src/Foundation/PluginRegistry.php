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
            // Auto-discover and install plugins if table is empty
            $this->autoDiscoverIfEmpty();

            // Get enabled plugins from database using model scope
            // Standardized on 'is_active' column across all models
            $enabledPluginNames = InstalledPlugin::enabled()
                ->pluck('name')
                ->toArray();

            // Map names to plugin classes from available plugins
            $enabledPlugins = [];
            foreach ($enabledPluginNames as $name) {
                if (isset($this->availablePlugins[$name])) {
                    $enabledPlugins[$name] = $this->availablePlugins[$name];
                }
            }

            // Filter to only include available plugins
            $availableEnabled = array_intersect_key($enabledPlugins, $this->availablePlugins);

            // Resolve dependencies and return plugin IDs in correct order
            $orderedIds = $this->dependencyResolver->resolve($availableEnabled);

            // Map plugin IDs back to their class names
            return array_map(fn ($id) => $availableEnabled[$id], $orderedIds);
        });
    }

    /**
     * Auto-discover and install plugins if the installed_plugins table is empty
     *
     * This ensures that after a fresh migration, all available plugins in
     * packages/* are automatically discovered and enabled.
     */
    protected function autoDiscoverIfEmpty(): void
    {
        try {
            // Check if table exists and is empty
            if (! \Illuminate\Support\Facades\Schema::hasTable('installed_plugins')) {
                return;
            }

            if (InstalledPlugin::count() > 0) {
                return;
            }

            Log::info('PluginRegistry: installed_plugins table is empty, auto-discovering plugins...');

            // Sort plugins alphabetically for predictable default order
            // This scales well as plugin count grows (30+ plugins in future)
            $sortedPlugins = $this->availablePlugins;
            ksort($sortedPlugins);

            $navigationSort = 1;
            foreach ($sortedPlugins as $pluginId => $pluginClass) {
                try {
                    $plugin = new $pluginClass;

                    // Read composer.json for metadata
                    $composerData = $this->readComposerJson($pluginId);

                    InstalledPlugin::create([
                        'name' => $pluginId,
                        'plugin_class' => $pluginClass,
                        'is_active' => true,
                        'navigation_sort' => $navigationSort++,
                        'version' => $composerData['version'] ?? (method_exists($plugin, 'getVersion') ? $plugin->getVersion() : '1.0.0'),
                        'config' => method_exists($plugin, 'getDefaultConfig') ? $plugin->getDefaultConfig() : [],
                        'package_name' => $composerData['name'] ?? $pluginId,
                        'description' => $composerData['description'] ?? null,
                        'author' => $composerData['author'] ?? null,
                        'source' => $composerData['source'] ?? 'local',
                        'source_url' => $composerData['source_url'] ?? null,
                        'category' => $composerData['category'] ?? null,
                        'composer_data' => $composerData,
                    ]);

                    Log::info("Auto-discovered plugin: {$pluginId}");
                } catch (\Exception $e) {
                    Log::warning("Failed to auto-discover plugin {$pluginId}: ".$e->getMessage());
                }
            }

            Log::info('Plugin auto-discovery complete: '.count($this->availablePlugins).' plugins installed');
        } catch (\Exception $e) {
            Log::error('Plugin auto-discovery failed: '.$e->getMessage());
        }
    }

    /**
     * Get all enabled plugins
     */
    public function getEnabledPlugins(): Collection
    {
        return InstalledPlugin::enabled()
            ->whereIn('name', $this->getAvailablePluginIds())
            ->get();
    }

    /**
     * Get disabled plugins
     */
    public function getDisabledPlugins(): Collection
    {
        return InstalledPlugin::disabled()
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
                    'is_active' => $enable,
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

            $plugin->update(['is_active' => true]);
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
                $plugin->update(['is_active' => false]);
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
     *
     * Scans netserva-* packages for Plugin implementations.
     * This allows dynamic discovery without maintaining a hardcoded list.
     */
    public function discoverPlugins(): void
    {
        $packagesPath = base_path('packages');

        if (! File::exists($packagesPath)) {
            return;
        }

        // Scan netserva-* packages (top-level and nested src directories)
        $patterns = [
            $packagesPath.'/netserva-*/src/*Plugin.php',
            $packagesPath.'/netserva-*/src/**/*Plugin.php',
        ];

        $pluginFiles = [];
        foreach ($patterns as $pattern) {
            $pluginFiles = array_merge($pluginFiles, File::glob($pattern));
        }

        // Remove duplicates (in case of overlapping patterns)
        $pluginFiles = array_unique($pluginFiles);

        foreach ($pluginFiles as $file) {
            try {
                $className = $this->extractClassNameFromFile($file);

                if ($className && class_exists($className) && is_subclass_of($className, Plugin::class)) {
                    $reflection = new \ReflectionClass($className);
                    if (! $reflection->isAbstract()) {
                        $plugin = new $className;
                        $pluginId = $plugin->getId();
                        // Only add if not already in the list (hardcoded takes precedence)
                        if (! isset($this->availablePlugins[$pluginId])) {
                            $this->availablePlugins[$pluginId] = $className;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to load plugin from {$file}: ".$e->getMessage());
            }
        }
    }

    /**
     * Load available plugins via filesystem discovery
     *
     * Scans packages/netserva-* for Plugin implementations.
     * No hardcoded list - fully dynamic discovery.
     */
    protected function loadAvailablePlugins(): void
    {
        $this->discoverPlugins();
    }

    /**
     * Extract fully qualified class name from a PHP file
     *
     * Parses the file to find namespace and class declarations.
     */
    protected function extractClassNameFromFile(string $filePath): ?string
    {
        if (! File::exists($filePath)) {
            return null;
        }

        $content = File::get($filePath);

        // Extract namespace
        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name
        $className = null;
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $className = trim($matches[1]);
        }

        if (! $className) {
            return null;
        }

        return $namespace ? $namespace.'\\'.$className : $className;
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
