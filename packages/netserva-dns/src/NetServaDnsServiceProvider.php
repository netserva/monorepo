<?php

namespace NetServa\Dns;

use Illuminate\Support\ServiceProvider;
use NetServa\Dns\Services\DnsProviderManagementService;
use NetServa\Dns\Services\DnsProviderService;
use NetServa\Dns\Services\DnsRecordManagementService;
use NetServa\Dns\Services\DnsZoneManagementService;
use NetServa\Dns\Services\DnsZoneService;
use NetServa\Dns\Services\DomainRegistrationService;
use NetServa\Dns\Services\PowerDnsService;

/**
 * NetServa DNS Service Provider
 *
 * Provides DNS and domain management services including PowerDNS integration,
 * domain registration, and comprehensive DNS record management.
 */
class NetServaDnsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services
     */
    public function register(): void
    {
        // Register DNS services
        $this->app->singleton(DnsProviderService::class);
        $this->app->singleton(DnsProviderManagementService::class);
        $this->app->singleton(DnsZoneManagementService::class);
        $this->app->singleton(DnsRecordManagementService::class);
        $this->app->singleton(DnsZoneService::class);
        $this->app->singleton(DomainRegistrationService::class);
        $this->app->singleton(PowerDnsService::class);

        // Register configuration
        $this->registerConfig();
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrations();

        // Load views
        $this->loadViews();

        // Register commands
        $this->registerCommands();

        // Register routes
        $this->registerRoutes();

        // Publish assets
        $this->publishAssets();
    }

    /**
     * Register configuration files
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/dns-manager.php',
            'dns-manager'
        );
    }

    /**
     * Load package migrations
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Load package views
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-dns');
    }

    /**
     * Register package commands
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // DNS Provider CRUD (Tier 1) - Commands: shdns, adddns, chdns, deldns
                Console\Commands\AddDnsCommand::class,
                Console\Commands\ShowDnsCommand::class,
                Console\Commands\ChangeDnsCommand::class,
                Console\Commands\DeleteDnsCommand::class,

                // DNS Zone CRUD (Tier 2) - Commands: shzone, addzone, chzone, delzone
                Console\Commands\AddZoneCommand::class,
                Console\Commands\ShowZoneCommand::class,
                Console\Commands\ChangeZoneCommand::class,
                Console\Commands\DeleteZoneCommand::class,

                // DNS Record CRUD (Tier 3) - Commands: shrec, addrec, chrec, delrec
                Console\Commands\AddRecordCommand::class,
                Console\Commands\ShowRecordCommand::class,
                Console\Commands\ChangeRecordCommand::class,
                Console\Commands\DeleteRecordCommand::class,

                // Utility commands
                Console\Commands\PowerDnsCommand::class,
                Console\Commands\PowerDnsManagementCommand::class,
                Console\Commands\DnsVerifyCommand::class,
                Console\Commands\SyncHomelabToPdnsCommand::class,
            ]);
        }
    }

    /**
     * Register package routes
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    /**
     * Publish package assets
     */
    protected function publishAssets(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/dns-manager.php' => config_path('dns-manager.php'),
            ], 'netserva-dns-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'netserva-dns-migrations');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/netserva-dns'),
            ], 'netserva-dns-views');
        }
    }
}
