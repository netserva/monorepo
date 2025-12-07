<?php

declare(strict_types=1);

namespace NetServa\Crm;

use Illuminate\Support\ServiceProvider;
use NetServa\Crm\Services\ClientManagementService;

/**
 * NetServa CRM Service Provider
 *
 * Standard Laravel service provider - NO NetServa dependencies required.
 * This package is designed to be deployed standalone with optional
 * integration when other NetServa packages are present.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/netserva-crm.php',
            'netserva-crm'
        );

        // Register ClientManagementService as singleton
        $this->app->singleton(ClientManagementService::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\AddcrmCommand::class,
                Console\Commands\ShcrmCommand::class,
                Console\Commands\ChcrmCommand::class,
                Console\Commands\DelcrmCommand::class,
            ]);
        }

        // Register as NetServa plugin if core is available
        $this->registerAsPluginIfCoreAvailable();
    }

    /**
     * Register CRM as a NetServa plugin if core package is available
     */
    protected function registerAsPluginIfCoreAvailable(): void
    {
        // Check if NetServa Core is installed
        if (! class_exists(\NetServa\Core\Services\PluginManager::class)) {
            return;
        }

        // Register CRM as a plugin for discovery
        try {
            $pluginManager = $this->app->make(\NetServa\Core\Services\PluginManager::class);

            // CRM metadata for plugin system
            $pluginManager->registerPlugin([
                'name' => 'NetServa CRM',
                'package_name' => 'netserva/crm',
                'plugin_class' => static::class,
                'version' => '1.0.0',
                'description' => 'Professional Laravel CRM with optional fleet and domain integration',
                'category' => 'business',
                'dependencies' => [],
            ]);
        } catch (\Exception $e) {
            // Silently fail - CRM works standalone
            // Core integration is optional enhancement only
        }
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-crm');

        // Publishable assets
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/netserva-crm.php' => config_path('netserva-crm.php'),
            ], 'netserva-crm-config');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/netserva-crm'),
            ], 'netserva-crm-views');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'netserva-crm-migrations');
        }
    }

    /**
     * Check if Fleet integration is available
     */
    public static function hasFleetIntegration(): bool
    {
        return config('netserva-crm.enable_fleet_integration', true)
            && class_exists(\NetServa\Fleet\Models\FleetVsite::class);
    }

    /**
     * Check if Domain integration is available
     */
    public static function hasDomainIntegration(): bool
    {
        return config('netserva-crm.enable_domain_integration', true)
            && class_exists(\App\Models\SwDomain::class);
    }

    /**
     * Check if Core integration is available
     */
    public static function hasCoreIntegration(): bool
    {
        return class_exists(\NetServa\Core\Services\PluginManager::class);
    }
}
