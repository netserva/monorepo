<?php

namespace NetServa\Fleet;

use Illuminate\Support\ServiceProvider;
use NetServa\Fleet\Console\Commands\AddfleetCommand;
use NetServa\Fleet\Console\Commands\AddvconfCommand;
use NetServa\Fleet\Console\Commands\AddvhostCommand;
use NetServa\Fleet\Console\Commands\ChfleetCommand;
use NetServa\Fleet\Console\Commands\ChvconfCommand;
use NetServa\Fleet\Console\Commands\ChvhostCommand;
use NetServa\Fleet\Console\Commands\DelvconfCommand;
use NetServa\Fleet\Console\Commands\DelvhostCommand;
use NetServa\Fleet\Console\Commands\ShvconfCommand;
use NetServa\Fleet\Console\Commands\ShvhostCommand;
use NetServa\Fleet\Console\Commands\VNodeSetupCommand;
use NetServa\Fleet\Services\VhostManagementService;

/**
 * NetServa Fleet Management Service Provider
 *
 * Provides VSite->VNode->VHost infrastructure tracking and management
 */
class FleetServiceProvider extends ServiceProvider
{
    /**
     * Check if CRM integration is available
     *
     * Returns true if NetServa CRM package is installed and the
     * CrmCustomer model exists.
     */
    public static function hasCrmIntegration(): bool
    {
        return class_exists(\NetServa\Crm\Models\CrmCustomer::class);
    }

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

        $this->app->singleton(VhostManagementService::class);
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'netserva-fleet');

        // Register commands (NetServa 3.0 CRUD pattern)
        // Note: Commands registered unconditionally so Artisan::call() works from web context
        $this->commands([
            // Fleet Commands
            AddfleetCommand::class,     // CREATE: Discover/register fleet infrastructure
            ChfleetCommand::class,      // UPDATE: Sync fleet from var/ directory
            VNodeSetupCommand::class,   // Legacy: VNode-specific setup

            // VSite CRUD Commands
            Console\Commands\AddvsiteCommand::class,    // CREATE
            Console\Commands\ShvsiteCommand::class,     // READ
            Console\Commands\ChvsiteCommand::class,     // UPDATE
            Console\Commands\DelvsiteCommand::class,    // DELETE

            // VNode CRUD Commands
            Console\Commands\AddvnodeCommand::class,    // CREATE
            Console\Commands\ShvnodeCommand::class,     // READ
            Console\Commands\ChvnodeCommand::class,     // UPDATE
            Console\Commands\DelvnodeCommand::class,    // DELETE

            // VHost CRUD Commands (moved from CLI package)
            AddvhostCommand::class,     // CREATE
            ShvhostCommand::class,      // READ
            ChvhostCommand::class,      // UPDATE
            DelvhostCommand::class,     // DELETE

            // VConf CRUD Commands (moved from CLI package)
            AddvconfCommand::class,     // CREATE
            ShvconfCommand::class,      // READ
            ChvconfCommand::class,      // UPDATE
            DelvconfCommand::class,     // DELETE

            // VNode Configuration Commands (Non-CRUD)
            Console\Commands\FleetIpv6PtrConfigureCommand::class,  // Configure IPv6 PTR records

            // Dnsmasq CRUD Commands (Infrastructure - Router/Gateway DNS)
            Console\Commands\AddDnsmasqCommand::class,  // CREATE: Add DNS host
            Console\Commands\ShDnsmasqCommand::class,   // READ: Show DNS hosts (--sync to refresh)
            Console\Commands\ChDnsmasqCommand::class,   // UPDATE: Modify DNS host
            Console\Commands\DelDnsmasqCommand::class,  // DELETE: Remove DNS host

            // BinaryLane VPS CRUD Commands
            Console\Commands\AddblCommand::class,       // CREATE: Create BinaryLane server + VNode
            Console\Commands\ShblCommand::class,        // READ: List/show BinaryLane servers
            Console\Commands\ChblCommand::class,        // UPDATE: Power/resize/rename server
            Console\Commands\DelblCommand::class,       // DELETE: Delete server + cleanup

            // BinaryLane Reference Data Commands
            Console\Commands\BlSizesCommand::class,     // List available VPS sizes
            Console\Commands\BlImagesCommand::class,    // List available OS images
            Console\Commands\BlRegionsCommand::class,   // List available regions
            Console\Commands\BlVpcsCommand::class,      // List VPCs
            Console\Commands\BlKeysCommand::class,      // List SSH keys
            Console\Commands\BlSyncCommand::class,      // Sync servers to VNodes
        ]);

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/fleet.php' => config_path('fleet.php'),
        ], 'fleet-config');
    }
}
