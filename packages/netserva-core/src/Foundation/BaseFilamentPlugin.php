<?php

namespace NetServa\Core\Foundation;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Log;

abstract class BaseFilamentPlugin implements Plugin
{
    /**
     * Plugin dependencies (plugin IDs)
     */
    protected array $dependencies = [];

    /**
     * Whether this plugin has been booted
     */
    protected bool $booted = false;

    /**
     * Plugin configuration
     */
    protected array $config = [];

    public function __construct()
    {
        $this->loadConfiguration();
    }

    // ================================
    // Filament Native Interface
    // ================================

    /**
     * Get the plugin's unique identifier
     */
    abstract public function getId(): string;

    /**
     * Register the plugin with the panel
     */
    public function register(Panel $panel): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        // Check dependencies first
        if (! $this->checkDependencies($panel)) {
            Log::warning("Plugin {$this->getId()} disabled due to missing dependencies");

            return;
        }

        // Register plugin components
        $this->registerResources($panel);
        $this->registerPages($panel);
        $this->registerWidgets($panel);
        $this->registerNavigationItems($panel);
        $this->registerAssets($panel);

        Log::info("Plugin {$this->getId()} registered successfully");
    }

    /**
     * Boot the plugin (called after all plugins are registered)
     */
    public function boot(Panel $panel): void
    {
        if (! $this->isEnabled() || $this->booted) {
            return;
        }

        // Plugin-specific boot logic
        $this->bootPlugin($panel);
        $this->booted = true;

        Log::info("Plugin {$this->getId()} booted successfully");
    }

    // ================================
    // Enhanced NS Features
    // ================================

    /**
     * Check if the plugin is enabled
     */
    public function isEnabled(): bool
    {
        // For now, all plugins are enabled by default
        // TODO: Implement proper plugin management with database
        return true;
    }

    /**
     * Enable the plugin at runtime
     */
    public function enable(): void
    {
        // TODO: Implement database-backed plugin management
        Log::info("Plugin {$this->getId()} enabled");
    }

    /**
     * Disable the plugin at runtime
     */
    public function disable(): void
    {
        // TODO: Implement database-backed plugin management
        Log::info("Plugin {$this->getId()} disabled");
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? '1.0.0';
    }

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Get plugin configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get default configuration
     */
    public function getDefaultConfig(): array
    {
        return [
            'version' => $this->getVersion(),
            'enabled_features' => [],
            'settings' => [],
        ];
    }

    // ================================
    // Asset Management
    // ================================

    /**
     * Register plugin assets
     */
    protected function registerAssets(Panel $panel): void
    {
        // TODO: Implement asset registration
    }

    /**
     * Unload plugin assets
     */
    protected function unloadAssets(): void
    {
        // TODO: Implement asset unloading
    }

    // ================================
    // Dependency Management
    // ================================

    /**
     * Check if all dependencies are satisfied
     */
    protected function checkDependencies(Panel $panel): bool
    {
        // During development/testing, skip dependency checks to avoid circular issues
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        foreach ($this->dependencies as $dependencyId) {
            if (! $panel->hasPlugin($dependencyId)) {
                return false;
            }

            $dependency = $panel->getPlugin($dependencyId);
            if (method_exists($dependency, 'isEnabled') && ! $dependency->isEnabled()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get plugins that depend on this plugin
     */
    public function getDependents(Panel $panel): array
    {
        $dependents = [];

        foreach ($panel->getPlugins() as $plugin) {
            if (method_exists($plugin, 'getDependencies')) {
                if (in_array($this->getId(), $plugin->getDependencies())) {
                    $dependents[] = $plugin->getId();
                }
            }
        }

        return $dependents;
    }

    // ================================
    // Helper Methods
    // ================================

    /**
     * Get the installed plugin record
     */
    protected function getInstalledPlugin(): ?object
    {
        // TODO: Implement database-backed plugin management
        return null;
    }

    /**
     * Get current Filament panel
     */
    protected function getCurrentPanel(): ?Panel
    {
        try {
            return filament()->getCurrentPanel();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Load plugin configuration
     */
    protected function loadConfiguration(): void
    {
        $configKey = str_replace('ns-', '', $this->getId());
        $this->config = config("ns-plugins.{$configKey}", []);
    }

    /**
     * Static factory method
     */
    public static function make(): static
    {
        return new static;
    }

    // ================================
    // Abstract Methods for Child Classes
    // ================================

    /**
     * Register Filament resources
     */
    abstract protected function registerResources(Panel $panel): void;

    /**
     * Register Filament pages
     */
    abstract protected function registerPages(Panel $panel): void;

    /**
     * Register Filament widgets
     */
    abstract protected function registerWidgets(Panel $panel): void;

    /**
     * Register navigation items and groups
     */
    protected function registerNavigationItems(Panel $panel): void
    {
        // Default implementation - override in child classes if needed
    }

    /**
     * Plugin-specific boot logic
     */
    protected function bootPlugin(Panel $panel): void
    {
        // Default implementation - override in child classes if needed
    }
}
