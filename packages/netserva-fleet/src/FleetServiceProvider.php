<?php

namespace NetServa\Fleet;

use Illuminate\Support\ServiceProvider;
use NetServa\Fleet\Console\Commands\FleetDiscoverCommand;
use NetServa\Fleet\Console\Commands\FleetImportCommand;
use NetServa\Fleet\Console\Commands\FleetMigrateVarCommand;
use NetServa\Fleet\Console\Commands\VNodeSetupCommand;

/**
 * NetServa Fleet Management Service Provider
 *
 * Provides VSite->VNode->VHost infrastructure tracking and management
 */
class FleetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/fleet.php',
            'fleet'
        );

        $this->app->singleton('fleet.discovery', function ($app) {
            return new Services\FleetDiscoveryService;
        });
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                FleetImportCommand::class,
                FleetDiscoverCommand::class,
                FleetMigrateVarCommand::class,
                VNodeSetupCommand::class,
            ]);
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/fleet.php' => config_path('fleet.php'),
        ], 'fleet-config');
    }
}
