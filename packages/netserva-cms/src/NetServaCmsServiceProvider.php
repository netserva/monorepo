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
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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
