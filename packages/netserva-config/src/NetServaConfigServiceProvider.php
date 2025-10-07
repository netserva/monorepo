<?php

namespace NetServa\Config;

use Illuminate\Support\ServiceProvider;
use NetServa\Config\Services\ConfigurationService;
use NetServa\Config\Services\DatabaseService;
use NetServa\Config\Services\SecretsService;

/**
 * NetServa Configuration Service Provider
 */
class NetServaConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigurationService::class);
        $this->app->singleton(SecretsService::class);
        $this->app->singleton(DatabaseService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/config-manager.php', 'config-manager');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-config');

        if ($this->app->runningInConsole()) {
            // Commands temporarily disabled during migration

            $this->publishes([
                __DIR__.'/../config/config-manager.php' => config_path('config-manager.php'),
            ], 'netserva-config-config');
        }
    }
}
