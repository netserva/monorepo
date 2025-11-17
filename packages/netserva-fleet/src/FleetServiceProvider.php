<?php

namespace NetServa\Fleet;

use Illuminate\Support\ServiceProvider;
use NetServa\Fleet\Console\Commands\AddfleetCommand;
use NetServa\Fleet\Console\Commands\ChfleetCommand;
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

        // Register commands (NetServa 3.0 CRUD pattern)
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Fleet Commands
                AddfleetCommand::class,     // CREATE: Discover/register fleet infrastructure
                ChfleetCommand::class,      // UPDATE: Sync fleet from var/ directory
                VNodeSetupCommand::class,   // Legacy: VNode-specific setup

                // Venue CRUD Commands
                Console\Commands\AddvenueCommand::class,    // CREATE
                Console\Commands\ShvenueCommand::class,     // READ
                Console\Commands\ChvenueCommand::class,     // UPDATE
                Console\Commands\DelvenueCommand::class,    // DELETE

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

                // VNode Configuration Commands (Non-CRUD)
                Console\Commands\FleetIpv6PtrConfigureCommand::class,  // Configure IPv6 PTR records

                // Dnsmasq CRUD Commands (Infrastructure - Router/Gateway DNS)
                Console\Commands\AddDnsmasqCommand::class,  // CREATE: Add DNS host
                Console\Commands\ShDnsmasqCommand::class,   // READ: Show DNS hosts (--sync to refresh)
                Console\Commands\ChDnsmasqCommand::class,   // UPDATE: Modify DNS host
                Console\Commands\DelDnsmasqCommand::class,  // DELETE: Remove DNS host
            ]);
        }

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/fleet.php' => config_path('fleet.php'),
        ], 'fleet-config');
    }
}
