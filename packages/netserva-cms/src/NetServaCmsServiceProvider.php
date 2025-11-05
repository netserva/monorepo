<?php

declare(strict_types=1);

namespace NetServa\Cms;

use Illuminate\Support\ServiceProvider;

/**
 * NetServa CMS Service Provider
 *
 * Standard Laravel service provider - NO NetServa dependencies
 * This package is designed to be deployed standalone
 */
class NetServaCmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/netserva-cms.php',
            'netserva-cms'
        );

        // Load helper functions
        require_once __DIR__.'/helpers.php';

        // Register as NetServa plugin if core is available
        $this->registerAsPluginIfCoreAvailable();
    }

    /**
     * Register CMS as a NetServa plugin if core package is available
     */
    protected function registerAsPluginIfCoreAvailable(): void
    {
        // Check if NetServa Core is installed
        if (! class_exists(\NetServa\Core\Services\PluginManager::class)) {
            return;
        }

        // Register CMS as a plugin for discovery
        try {
            $pluginManager = $this->app->make(\NetServa\Core\Services\PluginManager::class);

            // CMS metadata for plugin system
            $pluginManager->registerPlugin([
                'name' => 'NetServa CMS',
                'package_name' => 'netserva/cms',
                'plugin_class' => static::class,
                'version' => '3.0.0',
                'description' => 'Professional Laravel CMS with Filament 4 admin panel',
                'category' => 'content',
                'dependencies' => [],
            ]);
        } catch (\Exception $e) {
            // Silently fail - CMS works standalone
            // Core integration is optional enhancement only
        }
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load settings migrations (only if Spatie Settings is available)
        if (class_exists(\Spatie\LaravelSettings\Settings::class)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/settings');
        }

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-cms');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Publishable assets
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/netserva-cms.php' => config_path('netserva-cms.php'),
            ], 'netserva-cms-config');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/netserva-cms'),
            ], 'netserva-cms-views');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'netserva-cms-migrations');
        }
    }
}
